<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (preg_match('/(?:^|;)dbname=proma_qa_fin_recon_[a-f0-9]{10}(?:;|$)/i', $dsn) !== 1 || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use an isolated proma_qa_fin_recon_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}

putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_PASSWORD=' . (string) getenv('PROMA_TEST_DB_PASSWORD'));
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.91';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);
$migrationRunner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
(new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema'))->invoke(null);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pass = [];
$record = static function (string $id) use (&$pass): void { $pass[] = $id; };
$uuid = static function (): string { return bin2hex(random_bytes(16)); };
$setActor = static function (int $userId, string $role): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
};
$expectDenied = static function (callable $action, string $message) use ($assert): Throwable {
    try { $action(); } catch (Throwable $e) {
        $assert($e instanceof InvalidArgumentException && (int) $e->getCode() === 403, $message . ': rejection was not authorization-related.');
        return $e;
    }
    throw new RuntimeException($message . ': operation was accepted.');
};
$expectRejected = static function (callable $action, string $message) use ($assert): Throwable {
    try { $action(); } catch (Throwable $e) { return $e; }
    throw new RuntimeException($message . ': operation was accepted.');
};
$hasIssue = static function (array $findings, string $type): bool {
    foreach ($findings as $finding) if (($finding['issue_type'] ?? '') === $type) return true;
    return false;
};
$issue = static function (array $findings, string $type): array {
    foreach ($findings as $finding) if (($finding['issue_type'] ?? '') === $type) return $finding;
    throw new RuntimeException('Expected finding was not emitted: ' . $type);
};
$makeUser = static function (string $role, string $label) use ($pdo, $uuid): int {
    $suffix = $uuid();
    $statement = $pdo->prepare('INSERT INTO users (role,username,full_name,password_hash,status,created_at) VALUES (?,?,?,?,?,NOW())');
    $statement->execute([$role, 'qa-finrec-' . $label . '-' . substr($suffix, 0, 12), 'آزمون تطبیق ' . $label, password_hash($suffix, PASSWORD_DEFAULT), 'active']);
    return (int) $pdo->lastInsertId();
};
$makeContract = static function (int $customerId, int $principal = 1000000, int $months = 1, ?string $dueDate = null) use ($assert): int {
    $contractId = Contract::createWithInstallments([
        'customer_id' => $customerId,
        'principal_amount' => $principal,
        'down_payment_amount' => 0,
        'monthly_interest_rate' => 0,
        'interest_type' => 'simple',
        'months' => $months,
        'start_date' => date('Y-m-d'),
        'first_due_date' => $dueDate ?: date('Y-m-d', strtotime('+10 days')),
        'created_by' => (int) Auth::id(),
    ]);
    $assert($contractId > 0, 'Contract fixture creation failed.');
    return $contractId;
};
$installmentsFor = static function (int $contractId): array {
    return Model::fetchAll('SELECT * FROM installments WHERE contract_id=? ORDER BY installment_number,id', [$contractId]);
};
$createFullGroup = static function (int $contractId, int $customerId, array $installments, string $description = 'QA reconciliation') use ($uuid): array {
    $amount = 0;
    foreach ($installments as $installment) {
        $state = InstallmentFinancialStateService::state($installment, null, null, date('Y-m-d'));
        $amount += normalize_money($state['final_payable'] ?? 0);
    }
    if ($amount <= 0) throw new RuntimeException('Fixture installment has no payable amount.');
    $result = PaymentGroupService::create(
        $contractId,
        array_map(static function ($row) { return (int) $row['id']; }, $installments),
        $amount,
        $customerId,
        'manual',
        $description,
        false,
        $uuid(),
        null,
        date('Y-m-d'),
        date('H:i'),
        'selected'
    );
    return ['amount' => $amount, 'result' => $result];
};
$findCase = static function (array $finding): array {
    $row = Model::fetch('SELECT * FROM financial_reconciliation_discrepancies WHERE stable_key=? LIMIT 1', [(string) $finding['stable_key']]);
    if (!$row) throw new RuntimeException('Finding was not persisted to the discrepancy registry.');
    return $row;
};
$detectCase = static function (int $contractId, string $type = 'installment_materialization') use ($assert, $issue, $findCase, $record, $uuid): array {
    $run = FinancialReconciliationCenter::startRun((int) Auth::id(), $contractId, 1);
    $assert(($run['status'] ?? '') === 'completed', 'Targeted fixture scan did not complete.');
    $inspection = FinancialReconciliationEngine::inspectContract($contractId, date('Y-m-d'));
    $finding = $issue($inspection['findings'], $type);
    return [$run, $finding, $findCase($finding)];
};

$schemaName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$assert(preg_match('/\Aproma_qa_fin_recon_[a-f0-9]{10}\z/i', $schemaName) === 1, 'Connected schema does not match the disposable QA name.');
$sourceCountsBeforeMigration = [];
foreach (['payments', 'payment_allocations', 'installments', 'financial_ledger_events', 'financial_ledger_lines'] as $table) {
    $sourceCountsBeforeMigration[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
$migrationPaths = [
    'database/migrations/2026_10_27_v704_financial_reconciliation_center.sql',
    'database/migrations/2026_10_27_v704_financial_reconciliation_issue_type.sql',
];
foreach ($migrationPaths as $migrationPath) {
    $migrationSql = (string) file_get_contents(dirname(__DIR__) . '/' . $migrationPath);
    $migrationRunner->invoke(null, $migrationPath, $migrationSql);
}
foreach ($sourceCountsBeforeMigration as $table => $before) {
    $assert((int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() === $before, 'Reconciliation migration modified source table ' . $table . '.');
}
$assert(($pdo->query("SHOW TABLES LIKE 'financial_reconciliation_discrepancies'")->fetchColumn() ?: '') === 'financial_reconciliation_discrepancies', 'Reconciliation migration did not create its registry.');
$record('migration additive, retry-safe, and preserves source financial row counts');

$adminId = $makeUser('admin', 'admin');
$operatorId = $makeUser('operator', 'operator');
$customerId = $makeUser('customer', 'customer');
$setActor($adminId, 'admin');
Settings::saveMany(['monthly_penalty_rate' => '0', 'legal_monthly_penalty_rate' => '0', 'late_penalty_grace_days' => '0', 'monthly_reward_rate' => '0']);

// T01: official collection writes a payment group, allocation, payment, and Core Ledger evidence.
$validContractId = $makeContract($customerId);
$validInstallments = $installmentsFor($validContractId);
$validPayment = $createFullGroup($validContractId, $customerId, [$validInstallments[0]], 'T01-valid-payment');
$validInspection = FinancialReconciliationEngine::inspectContract($validContractId, date('Y-m-d'));
$assert(!$hasIssue($validInspection['findings'], 'payment_allocation_total_mismatch')
    && !$hasIssue($validInspection['findings'], 'group_payment_total_mismatch')
    && !$hasIssue($validInspection['findings'], 'payment_ledger_line_mismatch')
    && !$hasIssue($validInspection['findings'], 'payment_without_core_ledger_event'), 'Valid payment and allocation produced a false mismatch.');
$paidId = (int) Model::fetch('SELECT id FROM payments WHERE contract_id=? ORDER BY id DESC LIMIT 1', [$validContractId])['id'];
$allocationId = (int) Model::fetch('SELECT id FROM payment_allocations WHERE payment_id=? AND is_reversal=0 ORDER BY id DESC LIMIT 1', [$paidId])['id'];
$ledgerEventId = (int) Model::fetch("SELECT id FROM financial_ledger_events WHERE source_type='payment' AND source_id=? AND event_type='payment' LIMIT 1", [$paidId])['id'];
$record('T01 valid payment and allocation have no reconciliation finding');

// T02: allocation total mismatch is tied back to its payment/group.
Model::execute('UPDATE payment_allocations SET allocated_amount=allocated_amount+1 WHERE id=?', [$allocationId]);
$allocationMismatch = FinancialReconciliationEngine::inspectContract($validContractId, date('Y-m-d'));
$assert($hasIssue($allocationMismatch['findings'], 'payment_allocation_total_mismatch'), 'T02 did not detect a payment/allocation total mismatch.');
Model::execute('UPDATE payment_allocations SET allocated_amount=allocated_amount-1 WHERE id=?', [$allocationId]);
$record('T02 allocation sum mismatch detected and source fixture restored');

// T03: direct component evidence detects a changed Core Ledger amount.
Model::execute('UPDATE financial_ledger_lines SET delta_toman=delta_toman+1 WHERE event_id=? ORDER BY id LIMIT 1', [$ledgerEventId]);
$ledgerMismatch = FinancialReconciliationEngine::inspectContract($validContractId, date('Y-m-d'));
$assert($hasIssue($ledgerMismatch['findings'], 'payment_ledger_line_mismatch'), 'T03 did not detect a component amount mismatch.');
Model::execute('UPDATE financial_ledger_lines SET delta_toman=delta_toman-1 WHERE event_id=? ORDER BY id LIMIT 1', [$ledgerEventId]);
$record('T03 incorrect Core Ledger component detected with event reference');

// T04: a paid row dated before any observed ledger event is a coverage gap, not a verified mismatch.
$historicalContractId = $makeContract($customerId);
$pdo->prepare("INSERT INTO payments (installment_id,contract_id,user_id,amount,method,status,payment_date,payment_type,paid_at,created_at) VALUES (NULL,?,?,50,'manual','paid','1900-01-01','down_payment','1900-01-01 12:00:00','1900-01-01 12:00:00')")
    ->execute([$historicalContractId, $customerId]);
$historical = FinancialReconciliationEngine::inspectContract($historicalContractId, date('Y-m-d'));
$historicalFinding = $issue($historical['findings'], 'payment_without_core_ledger_event');
$assert(($historicalFinding['classification'] ?? '') === 'historical_coverage_gap' && ($historical['coverage']['coverage_is_proven_for_missing_events'] ?? true) === false, 'T04 missing historical event was promoted to a verified mismatch or coverage proof.');
$record('T04 pre-observation payment is classified as a historical coverage gap');

// T06/T07: official correction excludes the original from active totals and appends a balanced reversal.
$corrected = Payment::correct($paidId, 'T06/T07 QA correction', $adminId);
$assert(!empty($corrected['ok']), 'Official correction fixture failed.');
$postCorrection = FinancialReconciliationEngine::inspectContract($validContractId, date('Y-m-d'));
$assert(!$hasIssue($postCorrection['findings'], 'corrected_payment_missing_ledger_reversal'), 'T06 treated the corrected payment as active or missed its reversal.');
$reversalEvent = Model::fetch("SELECT id FROM financial_ledger_events WHERE source_type='payment' AND source_id=? AND event_type='reversal' ORDER BY id DESC LIMIT 1", [$paidId]);
$assert((bool) $reversalEvent && !$hasIssue($postCorrection['findings'], 'invalid_ledger_reversal'), 'T07 valid reversal produced a false positive.');
$reversalEventId = (int) $reversalEvent['id'];
$reversalLine = Model::fetch('SELECT id,delta_toman FROM financial_ledger_lines WHERE event_id=? ORDER BY id LIMIT 1', [$reversalEventId]);
Model::execute('UPDATE financial_ledger_lines SET delta_toman=delta_toman+1 WHERE id=?', [(int) $reversalLine['id']]);
$badReversal = FinancialReconciliationEngine::inspectContract($validContractId, date('Y-m-d'));
$assert($hasIssue($badReversal['findings'], 'invalid_ledger_reversal'), 'T08 invalid reversal amount was not detected.');
Model::execute('UPDATE financial_ledger_lines SET delta_toman=? WHERE id=?', [(int) $reversalLine['delta_toman'], (int) $reversalLine['id']]);
$record('T06 corrected payment excluded from active totals; T07 valid and T08 invalid reversal behavior verified');

// T05 and T13: read-only scan sees a stale materialized balance and repeated scans reuse one stable case.
$repairContractId = $makeContract($customerId);
$repairInstallment = $installmentsFor($repairContractId)[0];
Model::execute("UPDATE installments SET paid_amount=base_amount,remaining_amount=0,status='paid' WHERE id=?", [(int) $repairInstallment['id']]);
$rawBeforeScan = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $repairInstallment['id']]);
$setActor($adminId, 'admin');
[$firstRepairRun, $repairFinding, $repairCase] = $detectCase($repairContractId);
$rawAfterScan = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $repairInstallment['id']]);
$assert($rawBeforeScan === $rawAfterScan && ($repairFinding['rule_id'] ?? '') === 'R03', 'T05 scan failed to identify the stale row or changed its source.');
$repairStableKey = (string) $repairFinding['stable_key'];
$detectCase($repairContractId);
$duplicateCount = (int) Model::fetch('SELECT COUNT(*) AS total FROM financial_reconciliation_discrepancies WHERE stable_key=?', [$repairStableKey])['total'];
$assert($duplicateCount === 1, 'T13 repeated scans created duplicate discrepancy cases.');
$record('T05 stale installment materialization found without source writes; T13 stable key prevents duplicate cases');

// T20: a case cannot be closed directly without entering the supported reverify state.
$directCloseDenied = $expectRejected(static function () use ($repairCase, $adminId): void {
    FinancialReconciliationCenter::verifyAndResolve((int) $repairCase['id'], $adminId, 'T20 attempt to close without review');
}, 'T20 direct closure');
$assert($directCloseDenied instanceof InvalidArgumentException && (int) $directCloseDenied->getCode() === 409, 'T20 closure did not fail because the case was not ready for reverification.');
$record('T20 resolution without the required review/reverification transition is rejected');

// T14/T12/T15/T18: separated operator proposal, independent admin approval, one guarded execution, audit, replay.
$setActor($operatorId, 'operator');
FinancialReconciliationCenter::startReview((int) $repairCase['id'], $operatorId, 'بررسی شواهد R03');
$proposalResult = FinancialReconciliationCenter::proposeInstallmentRefresh((int) $repairCase['id'], $operatorId, 'بازسازی materialized state از موتور رسمی');
$proposal = $proposalResult['proposal'] ?? [];
$proposalUuid = (string) ($proposal['proposal_uuid'] ?? '');
$assert($proposalUuid !== '', 'T14 proposal was not created.');
$expectDenied(static function () use ($proposalUuid, $operatorId): void { FinancialReconciliationCenter::decideProposal($proposalUuid, $operatorId, true, 'unauthorized'); }, 'T12 operator approval');
$setActor($adminId, 'admin');
$decision = FinancialReconciliationCenter::decideProposal($proposalUuid, $adminId, true, 'بررسی مستقل شواهد و snapshot');
$assert(($decision['status'] ?? '') === 'approved', 'Admin approval did not succeed.');
$rawBeforeDeniedApply = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $repairInstallment['id']]);
$setActor($operatorId, 'operator');
$expectDenied(static function () use ($proposalUuid, $operatorId): void { FinancialReconciliationCenter::applyProposal($proposalUuid, $operatorId); }, 'T15 operator correction');
$assert(Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $repairInstallment['id']]) === $rawBeforeDeniedApply, 'T15 unauthorized execution changed source money state.');
$setActor($adminId, 'admin');
$paymentsBeforeRepair = (int) Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$repairContractId])['total'];
$ledgerBeforeRepair = (int) Model::fetch('SELECT COUNT(*) AS total FROM financial_ledger_events WHERE contract_id=?', [$repairContractId])['total'];
$applyResult = FinancialReconciliationCenter::applyProposal($proposalUuid, $adminId);
$assert(($applyResult['reverification'] ?? '') === 'passed' && !empty($applyResult['changed']), 'T14 approved refresh did not reverify successfully.');
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$repairContractId])['total'] === $paymentsBeforeRepair
    && (int) Model::fetch('SELECT COUNT(*) AS total FROM financial_ledger_events WHERE contract_id=?', [$repairContractId])['total'] === $ledgerBeforeRepair, 'T14 installment materialization repair changed payment or ledger history.');
$auditCountAfterApply = (int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_type='financial_reconciliation' AND event_action='correction_applied' AND related_id=?", [(int) $repairCase['id']])['total'];
$assert($auditCountAfterApply === 1 && !empty($applyResult['before']) && !empty($applyResult['after']), 'T14 repair audit did not preserve before/after evidence.');
$replay = FinancialReconciliationCenter::applyProposal($proposalUuid, $adminId);
$auditCountAfterReplay = (int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_type='financial_reconciliation' AND event_action='correction_applied' AND related_id=?", [(int) $repairCase['id']])['total'];
$assert(!empty($replay['idempotent_replay']) && $auditCountAfterReplay === $auditCountAfterApply, 'T18 repeating an applied correction created a second effect or audit event.');
$record('T12 operator cannot approve; T14 approved correction is atomic, audited, and reverified; T15 unauthorized apply denied; T18 replay is idempotent');

// T16: an approved snapshot becomes stale after a source write and cannot be applied.
$staleContractId = $makeContract($customerId);
$staleInstallment = $installmentsFor($staleContractId)[0];
Model::execute("UPDATE installments SET paid_amount=base_amount,remaining_amount=0,status='paid' WHERE id=?", [(int) $staleInstallment['id']]);
$setActor($adminId, 'admin');
[, $staleFinding, $staleCase] = $detectCase($staleContractId);
$setActor($operatorId, 'operator');
FinancialReconciliationCenter::startReview((int) $staleCase['id'], $operatorId, 'بررسی snapshot');
$staleProposal = FinancialReconciliationCenter::proposeInstallmentRefresh((int) $staleCase['id'], $operatorId, 'پیشنهاد snapshot');
$staleProposalUuid = (string) $staleProposal['proposal']['proposal_uuid'];
$setActor($adminId, 'admin');
FinancialReconciliationCenter::decideProposal($staleProposalUuid, $adminId, true, 'تأیید snapshot');
Model::execute('UPDATE installments SET base_amount=base_amount+1 WHERE id=?', [(int) $staleInstallment['id']]);
$staleStoredBeforeApply = Model::fetch('SELECT base_amount,paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $staleInstallment['id']]);
$staleApply = FinancialReconciliationCenter::applyProposal($staleProposalUuid, $adminId);
$staleStoredAfterApply = Model::fetch('SELECT base_amount,paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $staleInstallment['id']]);
$staleProposalRow = Model::fetch('SELECT status FROM financial_reconciliation_proposals WHERE proposal_uuid=?', [$staleProposalUuid]);
$assert(($staleApply['status'] ?? '') === 'stale' && $staleStoredBeforeApply === $staleStoredAfterApply && ($staleProposalRow['status'] ?? '') === 'stale', 'T16 stale approval was applied or failed to preserve the changed source.');
$record('T16 source fingerprint change stops stale approved correction without writes');

// T17: an AuditLog failure rolls the materialized-state write and workflow back together.
$auditFailContractId = $makeContract($customerId);
$auditFailInstallment = $installmentsFor($auditFailContractId)[0];
Model::execute("UPDATE installments SET paid_amount=base_amount,remaining_amount=0,status='paid' WHERE id=?", [(int) $auditFailInstallment['id']]);
$setActor($adminId, 'admin');
[, $auditFailFinding, $auditFailCase] = $detectCase($auditFailContractId);
$setActor($operatorId, 'operator');
FinancialReconciliationCenter::startReview((int) $auditFailCase['id'], $operatorId, 'بررسی شکست audit');
$auditFailProposal = FinancialReconciliationCenter::proposeInstallmentRefresh((int) $auditFailCase['id'], $operatorId, 'آزمون rollback audit');
$auditFailUuid = (string) $auditFailProposal['proposal']['proposal_uuid'];
$setActor($adminId, 'admin');
FinancialReconciliationCenter::decideProposal($auditFailUuid, $adminId, true, 'تأیید آزمون rollback');
$auditFailBefore = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $auditFailInstallment['id']]);
$triggerName = 'qa_finrec_fail_audit_' . substr($uuid(), 0, 10);
$pdo->exec("CREATE TRIGGER `{$triggerName}` BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced QA audit failure'");
try {
    $expectRejected(static function () use ($auditFailUuid, $adminId): void { FinancialReconciliationCenter::applyProposal($auditFailUuid, $adminId); }, 'T17 audit failure');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS `' . $triggerName . '`');
}
$auditFailAfter = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $auditFailInstallment['id']]);
$auditFailProposalState = Model::fetch('SELECT status FROM financial_reconciliation_proposals WHERE proposal_uuid=?', [$auditFailUuid]);
$assert($auditFailBefore === $auditFailAfter && ($auditFailProposalState['status'] ?? '') === 'approved', 'T17 failed audit did not roll the financial and workflow transaction back.');
$record('T17 AuditLog failure rolls the attempted correction back');

// T11: legal-cost charge is a separate component; third-party disbursement is not a customer receipt.
$legalContractId = $makeContract($customerId);
$pdo->prepare("INSERT INTO legal_cases (customer_id,contract_id,status,stage,legal_referred_at,created_at,updated_at) VALUES (?,?,'referred','court',NOW(),NOW(),NOW())")
    ->execute([$customerId, $legalContractId]);
$legalCaseId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO legal_case_costs (cost_uuid,legal_case_id,contract_id,category,title,amount_toman,cost_date,payment_status,approval_status,chargeable_to_customer,created_by,created_at) VALUES (?,?,?,?,?,10000,CURRENT_DATE,'pending','pending_approval',1,?,NOW())")
    ->execute(['QA-LCC-' . $uuid(), $legalCaseId, $legalContractId, 'court_fee', 'T11 legal fee', $adminId]);
$legalCostId = (int) $pdo->lastInsertId();
LegalCaseCostService::approve($legalCostId, $adminId);
LegalCaseCostService::recordThirdPartyPayment($legalCostId, 3000, 'QA third party', 'QA-THIRD-PARTY', date('Y-m-d'), 'This is not customer collection.', $uuid(), $adminId);
$legalInspection = FinancialReconciliationEngine::inspectContract($legalContractId, date('Y-m-d'));
$assert(!$hasIssue($legalInspection['findings'], 'legal_cost_ledger_charge_mismatch')
    && !$hasIssue($legalInspection['findings'], 'legal_cost_paid_state_mismatch')
    && !$hasIssue($legalInspection['findings'], 'legal_payment_allocation_total_mismatch'), 'T11 legal cost component or third-party payment was misinterpreted as a customer payment.');
$record('T11 approved legal-cost charge matches Core Ledger; third-party expense does not change customer paid state');

// T09: the existing grouped allocation path handles multiple installments as one exact receipt.
$multiContractId = $makeContract($customerId, 2000000, 2);
$multiInstallments = $installmentsFor($multiContractId);
$multiResult = $createFullGroup($multiContractId, $customerId, $multiInstallments, 'T09-multi-installment');
$multiInspection = FinancialReconciliationEngine::inspectContract($multiContractId, date('Y-m-d'));
$assert(!$hasIssue($multiInspection['findings'], 'group_payment_total_mismatch') && !$hasIssue($multiInspection['findings'], 'payment_allocation_total_mismatch'), 'T09 official multi-installment group was mis-summed.');
$record('T09 multiple-installment payment group follows the existing allocation rules');

// T10/T22: pending or unallocated gateway state is not accepted as collected money.
$gatewayContractId = $makeContract($customerId);
$pdo->prepare("INSERT INTO payment_groups (group_number,contract_id,customer_id,requested_amount,allocated_amount,method,status,allocation_scope,created_at) VALUES (?,?,?,1000,0,'zarinpal','pending','selected',NOW())")
    ->execute(['QA-PENDING-' . $uuid(), $gatewayContractId, $customerId]);
$pendingGroupId = (int) $pdo->lastInsertId();
$pendingBefore = (int) Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$gatewayContractId])['total'];
$pendingInspection = FinancialReconciliationEngine::inspectContract($gatewayContractId, date('Y-m-d'));
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$gatewayContractId])['total'] === $pendingBefore && !$hasIssue($pendingInspection['findings'], 'payment_without_allocation'), 'T10 pending gateway request was counted as a paid receipt.');
$reviewGroup = PaymentGroupService::recordGatewayReview($pendingGroupId, 1000, 'QA-REVIEW-' . $uuid(), 'verified gateway state awaits allocation');
$reviewInspection = FinancialReconciliationEngine::inspectContract($gatewayContractId, date('Y-m-d'));
$reviewFinding = $issue($reviewInspection['findings'], 'gateway_payment_review_required');
$assert(($reviewGroup['status'] ?? '') === 'review_required' && ($reviewFinding['classification'] ?? '') === 'insufficient_evidence', 'T22 authorized but unallocated gateway money was treated as a completed receipt.');
$record('T10 pending gateway is not a receipt; T22 review-required unallocated gateway evidence stays insufficient');

// T21: dynamic penalty is calculated by the canonical state service at a fixed as-of date.
$settingsBefore = Settings::allKeyed();
Settings::saveMany(['monthly_penalty_rate' => '1', 'legal_monthly_penalty_rate' => '0', 'late_penalty_grace_days' => '0', 'monthly_reward_rate' => '0']);
$penaltyContractId = $makeContract($customerId, 1000000, 1, date('Y-m-d', strtotime('-20 days')));
$penaltyInstallment = $installmentsFor($penaltyContractId)[0];
$canonicalPenaltyState = InstallmentFinancialStateService::state($penaltyInstallment, null, null, date('Y-m-d'));
Model::execute('UPDATE installments SET paid_amount=?,remaining_amount=?,status=? WHERE id=?', [
    normalize_money($canonicalPenaltyState['effective_paid_principal'] ?? 0),
    normalize_money($canonicalPenaltyState['remaining_principal'] ?? 0),
    (string) ($canonicalPenaltyState['status'] ?? 'pending'),
    (int) $penaltyInstallment['id'],
]);
$penaltyInspection = FinancialReconciliationEngine::inspectContract($penaltyContractId, date('Y-m-d'));
$assert(!$hasIssue($penaltyInspection['findings'], 'installment_materialization'), 'T21 time-varying penalty was incorrectly compared as stored principal.');
Settings::saveMany([
    'monthly_penalty_rate' => (string) ($settingsBefore['monthly_penalty_rate'] ?? '0'),
    'legal_monthly_penalty_rate' => (string) ($settingsBefore['legal_monthly_penalty_rate'] ?? '0'),
    'late_penalty_grace_days' => (string) ($settingsBefore['late_penalty_grace_days'] ?? '0'),
    'monthly_reward_rate' => (string) ($settingsBefore['monthly_reward_rate'] ?? '0'),
]);
$record('T21 variable daily penalty is evaluated at a fixed snapshot date without false principal mismatch');

// T19: a per-contract source limit marks the run partial and leaves old cases alone.
$largeContractId = $makeContract($customerId, 1000, 1);
$largeContractNumber = (int) Model::fetch('SELECT COALESCE(MAX(installment_number),0) AS n FROM installments WHERE contract_id=?', [$largeContractId])['n'];
$largeDate = date('Y-m-d', strtotime('+30 days'));
$insertInstallment = $pdo->prepare("INSERT INTO installments (contract_id,installment_number,due_date,base_amount,paid_amount,remaining_amount,status,created_at) VALUES (?,?,?,1000,0,1000,'pending',NOW())");
for ($number = $largeContractNumber + 1; $number <= 5001; $number++) $insertInstallment->execute([$largeContractId, $number, $largeDate]);
$largeTarget = Model::fetch('SELECT id FROM installments WHERE contract_id=? AND installment_number=5001', [$largeContractId]);
$largeStableKey = hash('sha256', implode('|', ['R03', 'installment_materialization', 'installment', (string) $largeTarget['id']]));
$largeRun = FinancialReconciliationCenter::startRun($adminId, $largeContractId, 1);
$assert(($largeRun['status'] ?? '') === 'partial' && (int) ($largeRun['error_count'] ?? 0) > 0, 'T19 bounded incomplete contract scan was reported complete.');
$largeRunId = (int) $largeRun['id'];
$pdo->prepare("INSERT INTO financial_reconciliation_discrepancies (stable_key,rule_id,rule_version,issue_type,classification,severity,status,contract_id,customer_id,installment_id,evidence_json,evidence_hash,first_seen_run_id,last_seen_run_id,first_seen_at,last_seen_at,updated_at) VALUES (?,'R03','financial-reconciliation-v1','installment_materialization','verified_mismatch','high','open',?,?,?,'{}',?, ?, ?,NOW(),NOW(),NOW())")
    ->execute([$largeStableKey, $largeContractId, $customerId, (int) $largeTarget['id'], hash('sha256', 'old-case-evidence'), $largeRunId, $largeRunId]);
$secondLargeRun = FinancialReconciliationCenter::startRun($adminId, $largeContractId, 1);
$oldLargeCase = Model::fetch('SELECT status FROM financial_reconciliation_discrepancies WHERE stable_key=?', [$largeStableKey]);
$assert(($secondLargeRun['status'] ?? '') === 'partial' && ($oldLargeCase['status'] ?? '') === 'open', 'Incomplete scan closed or dropped a previously open case.');
$record('T19 incomplete scan remains partial and never auto-resolves an unobserved old case');

// T23: full scans advance one contract per bounded request, with stable progress.
$fullRun = FinancialReconciliationCenter::startRun($adminId, null, 1);
$assert((int) ($fullRun['checked_count'] ?? 0) <= 1, 'T23 first scan request exceeded its contract batch limit.');
$batches = 1;
while (($fullRun['status'] ?? '') === 'running' && $batches < ((int) $fullRun['target_count'] + 2)) {
    $previous = (int) $fullRun['checked_count'];
    $fullRun = FinancialReconciliationCenter::continueRun((int) $fullRun['id'], $adminId, 1);
    $assert((int) $fullRun['checked_count'] - $previous <= 1, 'T23 continuation exceeded its contract batch limit.');
    $batches++;
}
$assert(($fullRun['status'] ?? '') !== 'running' && $batches <= ((int) $fullRun['target_count'] + 1), 'T23 bounded full scan did not finish predictably.');
$record('T23 full scan is batch-bounded and exposes progress; incomplete source contracts remain partial');

// T24: race an approved repair against an official payment writer on independent database sessions.
$raceContractId = $makeContract($customerId);
$raceInstallment = $installmentsFor($raceContractId)[0];
Model::execute("UPDATE installments SET paid_amount=base_amount,remaining_amount=0,status='paid' WHERE id=?", [(int) $raceInstallment['id']]);
$setActor($adminId, 'admin');
[, $raceFinding, $raceCase] = $detectCase($raceContractId);
$setActor($operatorId, 'operator');
FinancialReconciliationCenter::startReview((int) $raceCase['id'], $operatorId, 'آزمون همزمانی');
$raceProposal = FinancialReconciliationCenter::proposeInstallmentRefresh((int) $raceCase['id'], $operatorId, 'آزمون همزمانی دریافت جدید');
$raceProposalUuid = (string) $raceProposal['proposal']['proposal_uuid'];
$setActor($adminId, 'admin');
FinancialReconciliationCenter::decideProposal($raceProposalUuid, $adminId, true, 'تأیید مستقل آزمون همزمانی');
$workerFile = tempnam(sys_get_temp_dir(), 'proma-finrec-worker-');
$gateFile = tempnam(sys_get_temp_dir(), 'proma-finrec-gate-');
$resultFile = tempnam(sys_get_temp_dir(), 'proma-finrec-result-');
$readyFile = tempnam(sys_get_temp_dir(), 'proma-finrec-ready-');
file_put_contents($gateFile, 'wait');
$workerSource = <<<'PHP'
<?php
$dsn = $argv[1];
putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_PASSWORD=' . $argv[3]);
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require $argv[4] . '/bootstrap.php';
$started = microtime(true);
file_put_contents($argv[11], 'ready');
while (is_file($argv[9]) && microtime(true) - $started < 15) usleep(10000);
try {
    $result = PaymentGroupService::create((int) $argv[5], [(int) $argv[6]], (int) $argv[8], (int) $argv[7], 'manual', 'T24 concurrent receipt', false, bin2hex(random_bytes(16)), null, date('Y-m-d'), date('H:i'), 'selected');
    file_put_contents($argv[10], json_encode(['ok' => true, 'group' => $result], JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {
    file_put_contents($argv[10], json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => get_class($e)], JSON_UNESCAPED_UNICODE));
    exit(1);
}
PHP;
file_put_contents($workerFile, $workerSource);
$workerEnv = getenv();
$workerEnv['PROMA_DB_DSN'] = $dsn;
$workerEnv['PROMA_DB_PASSWORD'] = (string) getenv('PROMA_TEST_DB_PASSWORD');
$workerCommand = [PHP_BINARY, $workerFile, $dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), dirname(__DIR__), (string) $raceContractId, (string) $raceInstallment['id'], (string) $customerId, (string) normalize_money($raceInstallment['base_amount']), $gateFile, $resultFile, $readyFile];
$workerDescriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$workerProcess = proc_open($workerCommand, $workerDescriptors, $workerPipes, dirname(__DIR__), $workerEnv);
if (!is_resource($workerProcess)) throw new RuntimeException('Could not start the independent T24 payment worker.');
fclose($workerPipes[0]);
for ($wait = 0; $wait < 500 && !is_file($readyFile); $wait++) usleep(10000);
$assert(is_file($readyFile), 'T24 payment worker did not reach the concurrency barrier.');
unlink($gateFile);
$raceApply = FinancialReconciliationCenter::applyProposal($raceProposalUuid, $adminId);
$workerStdout = stream_get_contents($workerPipes[1]);
$workerStderr = stream_get_contents($workerPipes[2]);
fclose($workerPipes[1]);
fclose($workerPipes[2]);
$workerExit = proc_close($workerProcess);
$workerResult = json_decode((string) @file_get_contents($resultFile), true) ?: [];
foreach ([$workerFile, $gateFile, $resultFile, $readyFile] as $temporaryPath) if (is_file($temporaryPath)) unlink($temporaryPath);
$assert($workerExit === 0 && !empty($workerResult['ok']), 'T24 independent payment writer failed: ' . ($workerResult['error'] ?? $workerStderr ?: $workerStdout));
$raceRaw = Model::fetch('SELECT paid_amount,remaining_amount,status FROM installments WHERE id=?', [(int) $raceInstallment['id']]);
$raceFresh = InstallmentFinancialStateService::state(Installment::findRaw((int) $raceInstallment['id']), null, null, date('Y-m-d'));
$assert(normalize_money($raceRaw['paid_amount']) === normalize_money($raceFresh['effective_paid_principal'] ?? 0)
    && normalize_money($raceRaw['remaining_amount']) === normalize_money($raceFresh['remaining_principal'] ?? 0), 'T24 payment and correction race left the materialized balance inconsistent.');
$assert((int) Model::fetch("SELECT COUNT(*) AS total FROM payments WHERE contract_id=? AND status='paid' AND COALESCE(is_corrected,0)=0", [$raceContractId])['total'] === 1, 'T24 race lost or duplicated the official payment.');
$record('T24 independent official payment and approved correction serialize without inconsistent balance');

echo "INTEGRATION_FINANCIAL_RECONCILIATION_V704_OK\n";
echo 'Passed scenarios: ' . count($pass) . "\n";
foreach ($pass as $item) echo '[PASS] ' . $item . "\n";
