<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required for this destructive integration test.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.158';
require dirname(__DIR__) . '/bootstrap.php';
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo'); $property->setAccessible(true); $property->setValue(null, $pdo);
$migrationSql = preg_replace('/^--.*$/m', '', (string) file_get_contents(dirname(__DIR__) . '/database/migrations/2026_08_01_legal_cost_approval_v157.sql'));
foreach (preg_split('/;\s*(?:\r?\n|$)/', $migrationSql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
$digits = substr(preg_replace('/[^0-9]/', '8', hash('sha256', $suffix)), 0, 10);
$adminId = User::create(['role' => 'admin', 'username' => 'qa-legal-finance-' . $suffix, 'full_name' => 'مدیر مالی حقوقی', 'national_id' => $digits, 'mobile' => '0912' . substr($digits, 0, 7), 'email' => 'qa-legal-finance-' . $suffix . '@example.test', 'password' => 'Admin#158Pass', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => substr(strrev($digits), 0, 10), 'full_name' => 'مشتری مالی حقوقی', 'national_id' => substr(strrev($digits), 0, 10), 'mobile' => '0935' . substr($digits, 0, 7), 'email' => 'qa-legal-finance-c-' . $suffix . '@example.test', 'password' => 'Customer#158Pass', 'status' => 'active']);
$contractId = Contract::createWithInstallments(['customer_id' => $customerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1, 'start_date' => '2026-01-01', 'first_due_date' => '2026-02-01', 'created_by' => $adminId]);
$logId = 800000000 + random_int(1, 999999);
LegalCaseCostService::syncFromLegalLog($logId, ['contract_id' => $contractId, 'cost_amount' => 75000, 'cost_type' => 'هزینه دادرسی', 'action_date' => '2026-03-01', 'description' => 'QA V158'], 'هزینه تست مالی V158', $adminId);
$pending = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($pending['total_legal_costs'] ?? 0) === 75000, 'Registered legal cost is absent from the legal cost total.');
$assert(normalize_money($pending['pending_approval_legal_costs'] ?? 0) === 75000, 'Pending legal cost is not presented separately.');
$assert(normalize_money($pending['outstanding_chargeable_legal_costs'] ?? 0) === 0, 'Pending legal cost incorrectly became payable.');
$before = ContractFinancialSummaryService::summarize($contractId);
$assert(normalize_money($before['legal_costs_total'] ?? 0) === 0, 'Pending legal cost incorrectly entered current payable.');
$beforePayable = normalize_money($before['final_collectable_amount'] ?? 0);
$cost = Model::fetch('SELECT * FROM legal_case_costs WHERE source_legal_log_id = ?', [$logId]);
LegalCaseCostService::approve((int) $cost['id'], $adminId);
$approved = ContractLegalCostSummaryService::forContract($contractId);
$assert(normalize_money($approved['pending_approval_legal_costs'] ?? 0) === 0, 'Approved legal cost remains in pending total.');
$assert(normalize_money($approved['outstanding_chargeable_legal_costs'] ?? 0) === 75000, 'Approved legal cost is absent from payable cost total.');
$after = ContractFinancialSummaryService::summarize($contractId);
$assert(normalize_money($after['legal_costs_total'] ?? 0) === 75000, 'Canonical financial summary does not include approved legal cost.');
$assert(normalize_money($after['final_collectable_amount'] ?? 0) === $beforePayable + 75000, 'Current payable does not reconcile with the approved legal cost.');
echo "INTEGRATION_LEGAL_FINANCIAL_SUMMARY_V158_OK\n";
