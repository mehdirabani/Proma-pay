<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (preg_match('/(?:^|;)dbname=proma_qa_contract_amendment_[a-f0-9]{10}(?:;|$)/i', $dsn) !== 1 || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use an isolated proma_qa_contract_amendment_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}
putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_USER=' . ((string) getenv('PROMA_TEST_DB_USER') ?: 'root'));
putenv('PROMA_DB_PASSWORD=' . (string) getenv('PROMA_TEST_DB_PASSWORD'));
$_SERVER['HTTP_HOST'] = 'qa.local'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.157';
require dirname(__DIR__) . '/bootstrap.php';
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo');  $property->setValue(null, $pdo);
foreach (['2026_08_01_contract_installment_integrity_v157.sql', '2026_08_01_legal_cost_approval_v157.sql'] as $migration) {
    ${'migrationSql'} = preg_replace('/^--.*$/m', '', (string) file_get_contents(dirname(__DIR__) . '/database/migrations/' . $migration));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', ${'migrationSql'}) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
}
$amendmentMigration = 'database/migrations/2026_10_27_v704_contract_amendment_versioning.sql';
$pdo->exec('DROP TABLE IF EXISTS contract_amendment_history, contract_amendments, contract_financial_versions');
$migrationRunner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$migrationRunner->invoke(null, $amendmentMigration, (string) file_get_contents(dirname(__DIR__) . '/' . $amendmentMigration));
SchemaGuard::clearCache();
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
$digits = substr(preg_replace('/[^0-9]/', '7', hash('sha256', $suffix)), 0, 10);
$approverDigits = substr(preg_replace('/[^0-9]/', '8', hash('sha256', 'approver-' . $suffix)), 0, 10);
$adminId = User::create(['role' => 'admin', 'username' => 'qa-change-' . $suffix, 'full_name' => 'مدیر تغییر قسط', 'national_id' => $digits, 'mobile' => '0912' . substr($digits, 0, 7), 'email' => 'qa-change-' . $suffix . '@example.test', 'password' => 'Admin#157Pass', 'status' => 'active']);
$approverId = User::create(['role' => 'admin', 'username' => 'qa-approve-' . $suffix, 'full_name' => 'مدیر تأیید اصلاحیه', 'national_id' => $approverDigits, 'mobile' => '0911' . substr($approverDigits, 0, 7), 'email' => 'qa-approve-' . $suffix . '@example.test', 'password' => 'Approve#157Pass', 'status' => 'active']);
$customerMobile = '0935' . substr($digits, 0, 7);
$customerId = User::create(['role' => 'customer', 'username' => substr(strrev($digits), 0, 10), 'full_name' => 'مشتری تغییر قسط', 'national_id' => substr(strrev($digits), 0, 10), 'mobile' => $customerMobile, 'email' => 'qa-change-c-' . $suffix . '@example.test', 'password' => substr($customerMobile, -4), 'status' => 'active']);
$contractId = Contract::createWithInstallments(['customer_id' => $customerId, 'principal_amount' => 2000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2, 'start_date' => '2027-01-01', 'first_due_date' => '2027-02-01', 'created_by' => $adminId]);
$rows = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number', [$contractId]);
$assert(count($rows) === 2, 'Expected two generated installments.');
$first = $rows[0]; $second = $rows[1];
$result = InstallmentChangeService::change((int) $first['id'], ['version_token' => InstallmentChangeService::versionToken($first), 'due_date' => '2027-02-05', 'base_amount' => normalize_money($first['base_amount']) + 10000, 'difference_mode' => 'manual_schedule', 'reason' => 'تست تغییر امن قسط'], $adminId, $contractId);
$assert(($result['mode'] ?? '') === 'approval_required', 'Financial installment change was applied without contract-amendment approval.');
$amendmentId = (int) $result['id'];
$unchanged = Model::fetch('SELECT base_amount FROM installments WHERE id = ?', [(int) $first['id']]);
$assert(normalize_money($unchanged['base_amount']) === normalize_money($first['base_amount']), 'Unapproved amendment changed an installment amount.');
$assert(ContractAmendmentService::approve($amendmentId, $approverId)['status'] === 'approved', 'Independent amendment approval failed.');
$assert(ContractAmendmentService::apply($amendmentId, $adminId)['status'] === 'applied', 'Approved amendment did not apply.');
$changed = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number', [$contractId]);
$assert(normalize_money($changed[0]['base_amount']) === normalize_money($first['base_amount']) + 10000, 'Approved explicit schedule amount was not applied.');
$assert(normalize_money($changed[1]['base_amount']) === normalize_money($second['base_amount']), 'Amendment changed an unrelated future installment.');
$assert((int) (Model::fetch('SELECT COUNT(*) AS total FROM contract_financial_versions WHERE contract_id = ?', [$contractId])['total'] ?? 0) === 2, 'Applied amendment did not create an immutable financial version.');
$void = InstallmentChangeService::void((int) $changed[0]['id'], ['version_token' => InstallmentChangeService::versionToken($changed[0]), 'confirm_void' => '1', 'typed_confirmation' => 'ابطال قسط شماره ' . to_persian_digits($changed[0]['installment_number']), 'reason' => 'تست ابطال امن قسط'], $adminId, $contractId);
$assert(($void['mode'] ?? '') === 'approval_required', 'Installment void skipped contract-amendment approval.');
$voidId = (int) $void['id'];
$assert(ContractAmendmentService::approve($voidId, $approverId)['status'] === 'approved', 'Independent void approval failed.');
$assert(ContractAmendmentService::apply($voidId, $adminId)['status'] === 'applied', 'Approved installment void did not apply.');
$assert((Model::fetch('SELECT status FROM installments WHERE id = ?', [(int) $changed[0]['id']])['status'] ?? '') === 'cancelled', 'Installment void did not retain logical cancellation.');
$assert((int) (Model::fetch('SELECT COUNT(*) AS total FROM installment_voids WHERE installment_id = ?', [(int) $changed[0]['id']])['total'] ?? 0) === 1, 'Installment void lacks immutable record.');
$legalCaseId = 0;
Model::execute(
    "INSERT INTO legal_cases (customer_id, contract_id, status, stage, notes, expense_amount, legal_referred_at, created_at, updated_at)
     VALUES (?, ?, 'referred', 'QA referral', 'Approved-cost billing precondition', 0, NOW(), NOW(), NOW())",
    [$customerId, $contractId]
);
$legalCaseId = (int) Model::lastInsertId();
$costLogId = 900000000 + random_int(1, 999999);
LegalCaseCostService::syncFromLegalLog($costLogId, ['legal_case_id' => $legalCaseId, 'contract_id' => $contractId, 'cost_amount' => 50000, 'cost_type' => 'هزینه دادرسی', 'action_date' => '2027-01-01', 'description' => 'QA cost'], 'هزینه QA', $adminId);
$cost = Model::fetch('SELECT * FROM legal_case_costs WHERE source_legal_log_id = ?', [$costLogId]);
$assert(($cost['approval_status'] ?? '') === 'pending_approval', 'New legal cost must require approval.');
$beforeApproval = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($beforeApproval['outstanding_chargeable_legal_costs'] ?? 0) === 0, 'Pending legal cost entered customer debt.');
LegalCaseCostService::approve((int) $cost['id'], $adminId);
$afterApproval = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($afterApproval['outstanding_chargeable_legal_costs'] ?? 0) === 50000, 'Approved legal cost was not included in contract debt.');
echo "INTEGRATION_CONTRACT_INSTALLMENT_MANAGEMENT_V157_OK\n";
