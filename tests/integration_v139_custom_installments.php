<?php

declare(strict_types=1);

// Regression coverage for the v7.0.4 contract-amendment boundary. This test
// intentionally retains the historic filename referenced by the legacy suite.
$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (preg_match('/(?:^|;)dbname=proma_qa_contract_amendment_[a-f0-9]{10}(?:;|$)/i', $dsn) !== 1 || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use an isolated proma_qa_contract_amendment_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}
putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_USER=' . ((string) getenv('PROMA_TEST_DB_USER') ?: 'root'));
putenv('PROMA_DB_PASSWORD=' . (string) getenv('PROMA_TEST_DB_PASSWORD'));
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.139';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setValue(null, $pdo);
$migrationPath = 'database/migrations/2026_10_27_v704_contract_amendment_versioning.sql';
$migrationRunner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$migrationRunner->invoke(null, $migrationPath, (string) file_get_contents(dirname(__DIR__) . '/' . $migrationPath));
SchemaGuard::clearCache();

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$mustReject = static function (callable $operation, string $message): void {
    try {
        $operation();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException($message);
};
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
$digits = substr(preg_replace('/[^0-9]/', '7', hash('sha256', $suffix)), 0, 10);
$approverDigits = substr(preg_replace('/[^0-9]/', '8', hash('sha256', 'approver-' . $suffix)), 0, 10);
$adminId = User::create(['role' => 'admin', 'username' => 'qa-amend-' . $suffix, 'full_name' => 'مدیر درخواست QA', 'national_id' => $digits, 'mobile' => '0912' . substr($digits, 0, 7), 'email' => 'qa-amend-' . $suffix . '@example.test', 'password' => 'Admin#139Pass', 'status' => 'active']);
$approverId = User::create(['role' => 'admin', 'username' => 'qa-review-' . $suffix, 'full_name' => 'مدیر تأیید QA', 'national_id' => $approverDigits, 'mobile' => '0911' . substr($approverDigits, 0, 7), 'email' => 'qa-review-' . $suffix . '@example.test', 'password' => 'Review#139Pass', 'status' => 'active']);
$customerMobile = '0935' . substr($digits, 0, 7);
$customerId = User::create(['role' => 'customer', 'username' => substr(strrev($digits), 0, 10), 'full_name' => 'مشتری آزمون اصلاحیه', 'national_id' => substr(strrev($digits), 0, 10), 'mobile' => $customerMobile, 'email' => 'qa-customer-' . $suffix . '@example.test', 'password' => substr($customerMobile, -4), 'status' => 'active']);
$contractId = Contract::createWithInstallments(['customer_id' => $customerId, 'principal_amount' => 2000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2, 'start_date' => '2027-01-01', 'first_due_date' => '2027-02-01', 'created_by' => $adminId]);
$creationVersion = Model::fetch('SELECT provenance, history_complete, created_by FROM contract_financial_versions WHERE contract_id = ? AND version_number = 1', [$contractId]);
$assert($creationVersion && $creationVersion['provenance'] === 'created_state_approval_unrecorded' && (int) $creationVersion['history_complete'] === 1 && (int) $creationVersion['created_by'] === (int) $adminId, 'New contract did not receive a complete state snapshot without asserting legal approval.');
$regular = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? AND COALESCE(is_custom, 0) = 0 ORDER BY installment_number', [$contractId]);
$assert(count($regular) === 2, 'Expected two regular installments.');
$first = $regular[0];
$nextNumber = (int) (Model::fetch('SELECT COALESCE(MAX(installment_number), 0) AS n FROM installments WHERE contract_id = ?', [$contractId])['n'] ?? 0) + 1;
Model::execute('INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, is_custom, custom_title, custom_description, customer_visible, created_at) VALUES (?, ?, ?, ?, 0, ?, ?, 1, ?, ?, 1, NOW())', [$contractId, $nextNumber, '2027-04-01', 25000, 25000, 'pending', 'تعهد سفارشی قدیمی', 'ردیف موجود پیش از اصلاحیه']);
$custom = Model::fetch('SELECT * FROM installments WHERE contract_id = ? AND installment_number = ?', [$contractId, $nextNumber]);

$mustReject(static fn() => Installment::createCustom($contractId, '2027-05-01', 10000, 'ورودی مستقیم'), 'Direct custom installment creation bypassed the amendment boundary.');
$mustReject(static fn() => Installment::updateInstallment((int) $first['id'], ['due_date' => '2027-02-03', 'base_amount' => normalize_money($first['base_amount']) + 1]), 'Direct installment amount/date update bypassed the amendment boundary.');
$mustReject(static fn() => Installment::deleteCustom((int) $custom['id']), 'Custom installment was physically deletable.');
$mustReject(static fn() => Contract::updateContract((int) $contractId, ['principal_amount' => normalize_money(Model::fetch('SELECT principal_amount FROM contracts WHERE id = ?', [$contractId])['principal_amount']) + 1]), 'Legacy contract update API changed protected financial terms.');
$mustReject(static fn() => Installment::bulkAction((int) $contractId, [(int) $first['id']], 'cancel', 'تلاش مستقیم برای لغو', $adminId), 'Bulk cancellation bypassed amendment approval.');
$existingContract = Contract::find((int) $contractId);
$importSummary = PreparedExcelImportService::import(['installments' => [[
    'contract_number' => (string) $existingContract['contract_number'], 'installment_number' => 99,
    'due_date' => '2027-06-01', 'amount_toman' => 1000,
]]], $adminId);
$assert((int) ($importSummary['installments_created'] ?? 0) === 0 && !Model::fetch('SELECT id FROM installments WHERE contract_id = ? AND installment_number = 99', [$contractId]), 'Excel import added an installment to an existing contract outside the amendment flow.');
$importedNumber = 'IMP-QA-' . strtoupper($suffix);
$existingCustomer = Model::fetch('SELECT national_id FROM users WHERE id = ?', [(int) $customerId]);
$importBaseline = PreparedExcelImportService::import([
    'contracts' => [[
        'contract_number' => $importedNumber, 'customer_national_id' => (string) ($existingCustomer['national_id'] ?? ''),
        'first_due_date' => '2027-08-01', 'total_installments_amount_toman' => 100000, 'installments_count' => 1,
    ]],
    'installments' => [[
        'contract_number' => $importedNumber, 'installment_number' => 1, 'due_date' => '2027-08-01', 'amount_toman' => 100000,
    ]],
], $adminId);
$importedContract = Model::fetch('SELECT id FROM contracts WHERE contract_number = ?', [$importedNumber]);
$importedFinancialVersion = $importedContract ? Model::fetch('SELECT provenance, history_complete FROM contract_financial_versions WHERE contract_id = ?', [(int) $importedContract['id']]) : null;
$assert((int) ($importBaseline['contracts_created'] ?? 0) === 1 && $importedFinancialVersion && $importedFinancialVersion['provenance'] === 'imported_state_approval_unrecorded' && (int) $importedFinancialVersion['history_complete'] === 0, 'Imported contract did not receive an explicit incomplete-history baseline.');
$legacyNumber = 'LEGACY-QA-' . strtoupper($suffix);
Model::execute('INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, notes, created_at) VALUES (?, ?, ?, ?, ?, 0, 0, ?, 1, ?, ?, ?, ?, NOW())', [$customerId, $legacyNumber, 'LEGQA', 900000000 + random_int(1, 999999), 500000, 'simple', '2027-10-01', '2027-10-01', 'active', 'Legacy QA fixture']);
$legacyContractId = (int) Model::lastInsertId();
Model::execute('INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, is_custom, created_at) VALUES (?, 1, ?, 500000, 0, 500000, ?, 0, NOW())', [$legacyContractId, '2027-10-01', 'pending']);
$legacyVersion = ContractAmendmentService::ensureLegacyBaseline($legacyContractId);
$assert(($legacyVersion['provenance'] ?? '') === 'legacy_baseline_incomplete_history' && (int) ($legacyVersion['history_complete'] ?? 1) === 0 && empty($legacyVersion['created_by']), 'Legacy baseline invented an original approval or complete history.');
$sync = new ReflectionMethod(Contract::class, 'syncInstallmentSchedule');
$sync->setAccessible(true);
$syncBlocked = false;
try {
    $sync->invoke(null, (int) $contractId, []);
} catch (LogicException $e) {
    $syncBlocked = true;
}
$assert($syncBlocked, 'Direct invocation of syncInstallmentSchedule was not rejected without an approved-amendment context.');
$assert(normalize_money(Model::fetch('SELECT base_amount FROM installments WHERE id = ?', [(int) $first['id']])['base_amount']) === normalize_money($first['base_amount']), 'A rejected legacy/direct route changed an installment.');

$terms = ContractAmendmentService::normaliseTerms(Model::fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]));
$documentsBefore = ContractDocument::versions((int) $contractId);
$snapshot = ContractAmendmentService::snapshot($contractId);
$schedule = array_map(static function (array $line) use ($first): array {
    if ((int) $line['installment_id'] === (int) $first['id']) $line['base_amount'] = normalize_money($line['base_amount']) + 10000;
    return ['installment_id' => $line['installment_id'], 'due_date' => $line['due_date'], 'base_amount' => $line['base_amount']];
}, ContractAmendmentService::candidateSchedule($snapshot['schedule'], date('Y-m-d')));
$preview = ContractAmendmentService::preview($contractId, ['effective_on' => date('Y-m-d'), 'terms' => $terms, 'schedule' => $schedule]);
$request = ContractAmendmentService::request($contractId, ['effective_on' => date('Y-m-d'), 'reason' => 'تست نسخه‌بندی و حفظ قسط سفارشی', 'terms' => $terms, 'schedule' => $schedule, 'preview_fingerprint' => $preview['base_fingerprint'], 'idempotency_key' => 'custom-' . $suffix], $adminId);
$assert(($request['status'] ?? '') === 'pending_approval', 'Amendment request did not require independent review.');
$assert(ContractAmendmentService::approve((int) $request['id'], $approverId)['status'] === 'approved', 'Independent reviewer could not approve amendment.');
$assert(ContractAmendmentService::apply((int) $request['id'], $adminId)['status'] === 'applied', 'Approved amendment did not apply.');
$duplicateRequest = ContractAmendmentService::request($contractId, ['effective_on' => date('Y-m-d'), 'reason' => 'تست نسخه‌بندی و حفظ قسط سفارشی', 'terms' => $terms, 'schedule' => $schedule, 'preview_fingerprint' => $preview['base_fingerprint'], 'idempotency_key' => 'custom-' . $suffix], $adminId);
$assert(!empty($duplicateRequest['duplicate']) && (int) $duplicateRequest['id'] === (int) $request['id'], 'Repeated amendment request created a duplicate workflow.');
$duplicateApply = ContractAmendmentService::apply((int) $request['id'], $adminId);
$assert(!empty($duplicateApply['duplicate']) && (int) $duplicateApply['version_id'] > 0, 'Repeated apply created a second financial effect.');
$customAfter = Model::fetch('SELECT * FROM installments WHERE id = ?', [(int) $custom['id']]);
$assert($customAfter && (int) $customAfter['is_custom'] === 1 && $customAfter['status'] === 'pending', 'Existing custom installment was changed or removed by the regular schedule sync.');
$assert((string) $customAfter['due_date'] === (string) $custom['due_date'] && normalize_money($customAfter['base_amount']) === normalize_money($custom['base_amount']), 'Existing custom installment financial values changed during amendment application.');
$assert(normalize_money(Model::fetch('SELECT base_amount FROM installments WHERE id = ?', [(int) $first['id']])['base_amount']) === normalize_money($first['base_amount']) + 10000, 'Approved amendment schedule was not applied.');
$amendment = Model::fetch('SELECT a.document_version_id, a.result_version_id, d.version_number, d.source, d.is_published, d.is_finalized, d.checksum FROM contract_amendments a LEFT JOIN contract_document_versions d ON d.id = a.document_version_id WHERE a.id = ?', [(int) $request['id']]);
$assert(!empty($amendment['document_version_id']) && ($amendment['source'] ?? '') === 'amendment_draft', 'Applied amendment is not linked to an addendum document version.');
$assert((int) $amendment['is_published'] === 0 && (int) $amendment['is_finalized'] === 0, 'Internal approval incorrectly published or finalized the amendment document.');
$mustReject(static fn() => ContractDocument::publishVersion((int) $amendment['document_version_id'], $adminId), 'Amendment draft was publishable without recorded party consent.');
$mustReject(static fn() => ContractDocument::finalizeVersion((int) $amendment['document_version_id'], $adminId), 'Amendment draft was finalizable without recorded party consent.');
$mustReject(static fn() => ContractDocument::generate((int) $contractId, $adminId), 'Legacy generation published a current contract after an unconsented internal amendment.');
$mustReject(static fn() => ContractDocument::saveRenderedBody((int) $contractId, '<p>متن جدید</p>', $adminId, 'تست انتشار پس از اصلاحیه'), 'Manual document editing bypassed the party-consent boundary.');
if (!empty($documentsBefore[0]['id'])) {
    $mustReject(static fn() => ContractDocument::publishVersion((int) $documentsBefore[0]['id'], $adminId), 'An old contract draft was publishable after an unconsented amendment.');
    $mustReject(static fn() => ContractDocument::finalizeVersion((int) $documentsBefore[0]['id'], $adminId), 'An old contract draft was finalizable after an unconsented amendment.');
}
$financialDocLink = Model::fetch('SELECT document_version_id FROM contract_financial_versions WHERE id = ?', [(int) $amendment['result_version_id']]);
$assert((int) ($financialDocLink['document_version_id'] ?? 0) === (int) $amendment['document_version_id'], 'Financial version does not reference its addendum document.');
$firstVersion = Model::fetch('SELECT id FROM contract_financial_versions WHERE contract_id = ? AND version_number = 1', [$contractId]);
$reconstructedFirst = ContractAmendmentService::reconstructVersion((int) $contractId, (int) $firstVersion['id']);
$reconstructedSecond = ContractAmendmentService::reconstructVersion((int) $contractId, (int) $amendment['result_version_id']);
$versionComparison = ContractAmendmentService::compareVersions((int) $contractId, (int) $firstVersion['id'], (int) $amendment['result_version_id']);
$asOf = ContractAmendmentService::reconstructAsOf((int) $contractId, date('Y-m-d'));
$assert(normalize_money($reconstructedFirst['schedule'][0]['base_amount']) === normalize_money($first['base_amount']) && normalize_money($reconstructedSecond['schedule'][0]['base_amount']) === normalize_money($first['base_amount']) + 10000, 'Verified financial snapshots did not reconstruct their historical schedules.');
$assert(!empty($versionComparison['schedule_changes']) && !empty($asOf['available']) && (int) $asOf['snapshot']['version_number'] === 2, 'Version comparison or date-based contractual reconstruction is missing.');
$documentsAfter = ContractDocument::versions((int) $contractId);
foreach ($documentsBefore as $oldDocument) {
    $stillPresent = array_values(array_filter($documentsAfter, static fn(array $document): bool => (int) $document['id'] === (int) $oldDocument['id']));
    $assert($stillPresent && (string) $stillPresent[0]['checksum'] === (string) $oldDocument['checksum'] && (int) $stillPresent[0]['is_published'] === (int) $oldDocument['is_published'] && (int) $stillPresent[0]['is_finalized'] === (int) $oldDocument['is_finalized'], 'Existing legal document version was overwritten by the amendment draft.');
}

$raceContractId = Contract::createWithInstallments(['customer_id' => $customerId, 'principal_amount' => 1800000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2, 'start_date' => '2027-01-01', 'first_due_date' => '2027-09-01', 'created_by' => $adminId]);
$raceRows = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? AND COALESCE(is_custom, 0) = 0 ORDER BY installment_number', [$raceContractId]);
$raceTerms = ContractAmendmentService::normaliseTerms(Model::fetch('SELECT * FROM contracts WHERE id = ?', [$raceContractId]));
$raceSnapshot = ContractAmendmentService::snapshot($raceContractId);
$raceSchedule = array_map(static function (array $line) use ($raceRows): array {
    if ((int) $line['installment_id'] === (int) $raceRows[0]['id']) $line['base_amount'] = normalize_money($line['base_amount']) + 5000;
    return ['installment_id' => $line['installment_id'], 'due_date' => $line['due_date'], 'base_amount' => $line['base_amount']];
}, ContractAmendmentService::candidateSchedule($raceSnapshot['schedule'], date('Y-m-d')));
$racePreview = ContractAmendmentService::preview($raceContractId, ['effective_on' => date('Y-m-d'), 'terms' => $raceTerms, 'schedule' => $raceSchedule]);
$raceRequest = ContractAmendmentService::request($raceContractId, ['effective_on' => date('Y-m-d'), 'reason' => 'آزمون تغییر وضعیت پس از تأیید', 'terms' => $raceTerms, 'schedule' => $raceSchedule, 'preview_fingerprint' => $racePreview['base_fingerprint'], 'idempotency_key' => 'race-' . $suffix], $adminId);
$assert(ContractAmendmentService::approve((int) $raceRequest['id'], $approverId)['status'] === 'approved', 'Race-test amendment was not approved.');
$partialPayment = max(1, intdiv(normalize_money($raceRows[0]['base_amount']), 2));
Payment::record((int) $raceRows[0]['id'], (int) $raceContractId, (int) $adminId, $partialPayment, 'manual', 'paid', null, null, 'QA payment after amendment approval');
$raceApply = ContractAmendmentService::apply((int) $raceRequest['id'], $adminId);
$raceAfter = Model::fetch('SELECT * FROM installments WHERE id = ?', [(int) $raceRows[0]['id']]);
$assert(($raceApply['status'] ?? '') === 'conflict', 'Amendment applied after the financial base changed with a payment.');
$assert(normalize_money($raceAfter['base_amount']) === normalize_money($raceRows[0]['base_amount']) && normalize_money($raceAfter['paid_amount']) === $partialPayment, 'Payment made after approval was overwritten by amendment application.');
$assert((int) (Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE installment_id = ? AND status = \'paid\'', [(int) $raceRows[0]['id']])['total'] ?? 0) === 1, 'Payment history was lost during stale amendment conflict handling.');
$rejectSnapshot = ContractAmendmentService::snapshot((int) $raceContractId);
$rejectTerms = ContractAmendmentService::normaliseTerms(Model::fetch('SELECT * FROM contracts WHERE id = ?', [$raceContractId]));
$rejectSchedule = array_map(static function (array $line) use ($raceRows): array {
    if ((int) $line['installment_id'] === (int) $raceRows[1]['id']) $line['base_amount'] = normalize_money($line['base_amount']) + 7000;
    return ['installment_id' => $line['installment_id'], 'due_date' => $line['due_date'], 'base_amount' => $line['base_amount']];
}, ContractAmendmentService::candidateSchedule($rejectSnapshot['schedule'], date('Y-m-d')));
$rejectPreview = ContractAmendmentService::preview($raceContractId, ['effective_on' => date('Y-m-d'), 'terms' => $rejectTerms, 'schedule' => $rejectSchedule]);
$rejectRequest = ContractAmendmentService::request($raceContractId, ['effective_on' => date('Y-m-d'), 'reason' => 'آزمون رد اصلاحیه', 'terms' => $rejectTerms, 'schedule' => $rejectSchedule, 'preview_fingerprint' => $rejectPreview['base_fingerprint'], 'idempotency_key' => 'reject-' . $suffix], $adminId);
$rejectResult = ContractAmendmentService::reject((int) $rejectRequest['id'], $approverId, 'تغییر پس از بررسی رد شد');
$assert(($rejectResult['status'] ?? '') === 'rejected' && normalize_money(Model::fetch('SELECT base_amount FROM installments WHERE id = ?', [(int) $raceRows[1]['id']])['base_amount']) === normalize_money($raceRows[1]['base_amount']), 'Rejected amendment changed the approved contract schedule.');

echo "INTEGRATION_V139_CUSTOM_INSTALLMENTS_OK\n";
