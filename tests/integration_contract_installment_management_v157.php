<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required for this destructive integration test.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.157';
require dirname(__DIR__) . '/bootstrap.php';
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo'); $property->setAccessible(true); $property->setValue(null, $pdo);
foreach (['2026_08_01_contract_installment_integrity_v157.sql', '2026_08_01_legal_cost_approval_v157.sql'] as $migration) {
    ${'migrationSql'} = preg_replace('/^--.*$/m', '', (string) file_get_contents(dirname(__DIR__) . '/database/migrations/' . $migration));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', ${'migrationSql'}) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
}
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
$digits = substr(preg_replace('/[^0-9]/', '7', hash('sha256', $suffix)), 0, 10);
$adminId = User::create(['role' => 'admin', 'username' => 'qa-change-' . $suffix, 'full_name' => 'مدیر تغییر قسط', 'national_id' => $digits, 'mobile' => '0912' . substr($digits, 0, 7), 'email' => 'qa-change-' . $suffix . '@example.test', 'password' => 'Admin#157Pass', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => substr(strrev($digits), 0, 10), 'full_name' => 'مشتری تغییر قسط', 'national_id' => substr(strrev($digits), 0, 10), 'mobile' => '0935' . substr($digits, 0, 7), 'email' => 'qa-change-c-' . $suffix . '@example.test', 'password' => 'Customer#157Pass', 'status' => 'active']);
$contractId = Contract::createWithInstallments(['customer_id' => $customerId, 'principal_amount' => 2000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2, 'start_date' => '2027-01-01', 'first_due_date' => '2027-02-01', 'created_by' => $adminId]);
$rows = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number', [$contractId]);
$assert(count($rows) === 2, 'Expected two generated installments.');
$first = $rows[0]; $second = $rows[1]; $totalBefore = normalize_money($first['base_amount']) + normalize_money($second['base_amount']);
$result = InstallmentChangeService::change((int) $first['id'], ['version_token' => InstallmentChangeService::versionToken($first), 'due_date' => '2027-02-05', 'base_amount' => normalize_money($first['base_amount']) + 10000, 'custom_title' => 'اصلاح QA', 'custom_description' => 'تغییر کنترل‌شده', 'internal_note' => 'qa', 'difference_mode' => 'transfer_to_last_unpaid', 'reason' => 'تست تغییر امن قسط'], $adminId);
$assert(($result['mode'] ?? '') === 'applied', 'Safe unpaid installment change was not applied.');
$changed = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number', [$contractId]);
$assert(normalize_money($changed[0]['base_amount']) + normalize_money($changed[1]['base_amount']) === $totalBefore, 'Transfer-to-last-unpaid did not preserve schedule total.');
$assert((int) (Model::fetch("SELECT COUNT(*) AS total FROM installment_change_requests WHERE contract_id = ? AND status = 'applied'", [$contractId])['total'] ?? 0) === 1, 'Applied installment change lacks audit request.');
$void = InstallmentChangeService::void((int) $changed[0]['id'], ['version_token' => InstallmentChangeService::versionToken($changed[0]), 'confirm_void' => '1', 'typed_confirmation' => 'ابطال قسط شماره ' . to_persian_digits($changed[0]['installment_number']), 'reason' => 'تست ابطال امن قسط'], $adminId);
$assert(($void['mode'] ?? '') === 'applied', 'Safe installment void was not applied.');
$assert((Model::fetch('SELECT status FROM installments WHERE id = ?', [(int) $changed[0]['id']])['status'] ?? '') === 'cancelled', 'Installment void did not retain logical cancellation.');
$assert((int) (Model::fetch('SELECT COUNT(*) AS total FROM installment_voids WHERE installment_id = ?', [(int) $changed[0]['id']])['total'] ?? 0) === 1, 'Installment void lacks immutable record.');
$costLogId = 900000000 + random_int(1, 999999);
LegalCaseCostService::syncFromLegalLog($costLogId, ['contract_id' => $contractId, 'cost_amount' => 50000, 'cost_type' => 'هزینه دادرسی', 'action_date' => '2027-01-01', 'description' => 'QA cost'], 'هزینه QA', $adminId);
$cost = Model::fetch('SELECT * FROM legal_case_costs WHERE source_legal_log_id = ?', [$costLogId]);
$assert(($cost['approval_status'] ?? '') === 'pending_approval', 'New legal cost must require approval.');
$beforeApproval = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($beforeApproval['outstanding_chargeable_legal_costs'] ?? 0) === 0, 'Pending legal cost entered customer debt.');
LegalCaseCostService::approve((int) $cost['id'], $adminId);
$afterApproval = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($afterApproval['outstanding_chargeable_legal_costs'] ?? 0) === 50000, 'Approved legal cost was not included in contract debt.');
echo "INTEGRATION_CONTRACT_INSTALLMENT_MANAGEMENT_V157_OK\n";
