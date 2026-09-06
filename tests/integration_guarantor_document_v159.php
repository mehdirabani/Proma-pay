<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required for this destructive integration test.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$migrationSql = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/2026_09_06_contract_guarantor_snapshots.sql');
foreach (preg_split('/;\s*(?:\r?\n|$)/', $migrationSql) as $statement) {
    if (trim($statement) !== '') $pdo->exec($statement);
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 8);
$digits = substr(preg_replace('/[^0-9]/', '7', hash('sha256', $suffix)), 0, 10);
$mobile = static fn (string $prefix, int $offset): string => $prefix . str_pad((string) $offset, 7, '0', STR_PAD_LEFT);

$adminId = User::create(['role' => 'admin', 'username' => 'qa-g-admin-' . $suffix, 'full_name' => 'مدیر تست ضامن', 'national_id' => $digits, 'mobile' => $mobile('0912', 1), 'email' => 'qa-g-admin-' . $suffix . '@example.test', 'password' => 'Admin#159Pass', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => 'qa-g-c-' . $suffix, 'full_name' => 'مشتری تست ضامن', 'national_id' => strrev($digits), 'mobile' => $mobile('0935', 2), 'email' => 'qa-g-c-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);
$guarantorA = User::create(['role' => 'customer', 'username' => 'qa-g-a-' . $suffix, 'full_name' => 'ضامن موجود الف', 'national_id' => substr($digits, 0, 9) . '1', 'mobile' => $mobile('0901', 3), 'email' => 'qa-g-a-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);
$guarantorB = User::create(['role' => 'customer', 'username' => 'qa-g-b-' . $suffix, 'full_name' => 'ضامن موجود ب', 'national_id' => substr($digits, 0, 9) . '2', 'mobile' => $mobile('0901', 4), 'email' => 'qa-g-b-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);

$payload = ['customer_id' => $customerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1, 'start_date' => '2026-01-01', 'first_due_date' => '2026-02-01', 'created_by' => $adminId];
$existingOnlyContract = Contract::createWithInstallments($payload, [$guarantorA, $guarantorB]);
$existingOnly = ContractDocument::guarantorsForDocument($existingOnlyContract);
$existingNames = array_column($existingOnly, 'full_name');
$assert($existingNames === ['ضامن موجود الف', 'ضامن موجود ب'], 'Existing guarantors are not returned in contract order.');
$renderedExisting = ContractDocument::document($existingOnlyContract);
$assert(strpos((string) ($renderedExisting['rendered_body'] ?? ''), 'ضامن موجود الف') !== false && strpos((string) ($renderedExisting['rendered_body'] ?? ''), 'ضامن موجود ب') !== false, 'Print document omits existing guarantors.');

Model::execute('UPDATE users SET full_name = ? WHERE id = ?', ['نام تغییرکرده پس از قرارداد', $guarantorA]);
$snapshotNames = array_column(ContractDocument::guarantorsForDocument($existingOnlyContract), 'full_name');
$assert($snapshotNames[0] === 'ضامن موجود الف', 'Contract guarantor snapshot was overwritten by a profile change.');

$mixedContract = Contract::createWithInstallments($payload, [$guarantorA], [], [], [[
    'full_name' => 'ضامن جدید ج', 'father_name' => 'پدر ج', 'national_id' => '1234567890', 'mobile' => '09120000000', 'relationship' => 'ضامن', 'address' => 'نشانی تست',
]]);
$mixedNames = array_column(ContractDocument::guarantorsForDocument($mixedContract), 'full_name');
$assert($mixedNames === ['ضامن موجود الف', 'ضامن جدید ج'], 'Mixed existing/new guarantors are not resolved by one document model.');
$renderedMixed = ContractDocument::document($mixedContract);
$assert(strpos((string) ($renderedMixed['rendered_body'] ?? ''), 'ضامن موجود الف') !== false && strpos((string) ($renderedMixed['rendered_body'] ?? ''), 'ضامن جدید ج') !== false, 'Mixed guarantors are not printed together.');

echo "INTEGRATION_GUARANTOR_DOCUMENT_V159_OK\n";
