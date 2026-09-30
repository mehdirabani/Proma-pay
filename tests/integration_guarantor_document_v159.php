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
$nationalId = static function () use ($pdo): string {
    // Fixture identities must be distinct both within this run and from prior runs.
    do {
        $candidate = (string) random_int(1000000000, 9999999999);
        $query = $pdo->prepare('SELECT 1 FROM users WHERE national_id = ? LIMIT 1');
        $query->execute([$candidate]);
    } while ($query->fetchColumn());
    return $candidate;
};
$mobileSeed = hexdec(substr($suffix, 0, 6)) % 10000000;
$mobile = static fn (string $prefix, int $offset): string => $prefix . str_pad((string) (($mobileSeed + $offset) % 10000000), 7, '0', STR_PAD_LEFT);

$adminId = User::create(['role' => 'admin', 'username' => 'qa-g-admin-' . $suffix, 'full_name' => 'مدیر تست ضامن', 'national_id' => $nationalId(), 'mobile' => $mobile('0912', 1), 'email' => 'qa-g-admin-' . $suffix . '@example.test', 'password' => 'Admin#159Pass', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => 'qa-g-c-' . $suffix, 'full_name' => 'مشتری تست ضامن', 'national_id' => $nationalId(), 'mobile' => $mobile('0935', 2), 'email' => 'qa-g-c-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);
$guarantorA = User::create(['role' => 'customer', 'username' => 'qa-g-a-' . $suffix, 'full_name' => 'ضامن موجود الف', 'national_id' => $nationalId(), 'mobile' => $mobile('0901', 3), 'email' => 'qa-g-a-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);
$guarantorB = User::create(['role' => 'customer', 'username' => 'qa-g-b-' . $suffix, 'full_name' => 'ضامن موجود ب', 'national_id' => $nationalId(), 'mobile' => $mobile('0901', 4), 'email' => 'qa-g-b-' . $suffix . '@example.test', 'password' => 'Customer#159Pass', 'status' => 'active']);

$payload = ['customer_id' => $customerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1, 'start_date' => '2026-01-01', 'first_due_date' => '2026-02-01', 'created_by' => $adminId];
$existingOnlyContract = Contract::createWithInstallments($payload, [$guarantorA, $guarantorB]);
$existingOnly = ContractDocument::guarantorsForDocument($existingOnlyContract);
$existingNames = array_column($existingOnly, 'full_name');
$assert($existingNames === ['ضامن موجود الف', 'ضامن موجود ب'], 'Existing guarantors are not returned in contract order.');
$renderedExisting = ContractDocument::document($existingOnlyContract);
$assert(strpos((string) ($renderedExisting['rendered_body'] ?? ''), 'ضامن موجود الف') !== false && strpos((string) ($renderedExisting['rendered_body'] ?? ''), 'ضامن موجود ب') !== false, 'Print document omits existing guarantors.');
$existingView = ContractDocument::viewModel($existingOnlyContract);
$assert($existingView['guarantors'] === $existingOnly, 'Preview and print do not share the guarantor document view-model.');
$assert((string) $existingView['body'] === (string) ($renderedExisting['rendered_body'] ?? ''), 'Preview and print do not share the same rendered contract body.');

Model::execute('UPDATE users SET full_name = ? WHERE id = ?', ['نام تغییرکرده پس از قرارداد', $guarantorA]);
$snapshotNames = array_column(ContractDocument::guarantorsForDocument($existingOnlyContract), 'full_name');
$assert($snapshotNames[0] === 'ضامن موجود الف', 'Contract guarantor snapshot was overwritten by a profile change.');

$newPerson = ['full_name' => 'ضامن جدید ج', 'father_name' => 'پدر ج', 'national_id' => $nationalId(), 'mobile' => $mobile('0919', 5), 'relationship' => 'ضامن', 'address' => 'نشانی تست'];
$mixedContract = Contract::createWithInstallments($payload, [$guarantorA], [], [], [$newPerson]);
$mixedNames = array_column(ContractDocument::guarantorsForDocument($mixedContract), 'full_name');
$assert($mixedNames === ['نام تغییرکرده پس از قرارداد', 'ضامن جدید ج'], 'Mixed existing/new guarantors are not resolved by one document model.');
$renderedMixed = ContractDocument::document($mixedContract);
$assert(strpos((string) ($renderedMixed['rendered_body'] ?? ''), 'نام تغییرکرده پس از قرارداد') !== false && strpos((string) ($renderedMixed['rendered_body'] ?? ''), 'ضامن جدید ج') !== false, 'Mixed guarantors are not printed together.');
$mixedView = ContractDocument::viewModel($mixedContract);
$assert(array_column($mixedView['guarantors'], 'full_name') === $mixedNames, 'Mixed-guarantor preview differs from print data.');

$createdCustomer = Model::fetch('SELECT * FROM users WHERE national_id = ?', [$newPerson['national_id']]);
$assert($createdCustomer && $createdCustomer['role'] === 'customer' && $createdCustomer['status'] === 'active', 'New guarantor has no active customer account.');
$assert(password_verify(substr($newPerson['mobile'], -4), $createdCustomer['password_hash']), 'New guarantor default login must use last four mobile digits.');
$assert($createdCustomer['username'] === $newPerson['national_id'], 'New guarantor cannot sign in by national ID.');
$assert((int) Model::fetch('SELECT COUNT(*) AS n FROM contract_guarantors WHERE contract_id = ? AND guarantor_id = ?', [$mixedContract, $createdCustomer['id']])['n'] === 1, 'New guarantor account not linked to contract.');
$originalHash = $createdCustomer['password_hash'];
$reuseContract = Contract::createWithInstallments($payload, [], [], [], [$newPerson]);
$assert((int) Model::fetch('SELECT COUNT(*) AS n FROM users WHERE national_id = ?', [$newPerson['national_id']])['n'] === 1, 'Reusing a guarantor created duplicate customers.');
$assert(User::find($createdCustomer['id'])['password_hash'] === $originalHash, 'Reusing a guarantor reset the existing password.');
$assert(count(ContractDocument::guarantorsForDocument($reuseContract)) === 1, 'Account link duplicated printed guarantor.');

$emptyContract = Contract::createWithInstallments($payload);
Contract::updateContract($emptyContract, $payload + ['updated_by' => $adminId], [], [], [], [$newPerson]);
$assert(count(ContractDocument::guarantorsForDocument($emptyContract)) === 1, 'Editing contract did not create guarantor link.');

$secondPerson = $newPerson;
$secondPerson['national_id'] = $nationalId();
$secondPerson['mobile'] = $mobile('0919', 6);
$conflict = $newPerson;
$conflict['mobile'] = User::find($guarantorB)['mobile'];
$beforeContracts = (int) Model::fetch('SELECT COUNT(*) AS n FROM contracts')['n'];
try {
    Contract::createWithInstallments($payload, [], [], [], [$secondPerson, $conflict]);
    throw new RuntimeException('Conflicting identities accepted.');
} catch (InvalidArgumentException $expected) {
    $assert(strpos($expected->getMessage(), 'دو حساب') !== false, 'Unexpected conflict rejection.');
}
$assert(!Model::fetch('SELECT id FROM users WHERE national_id = ?', [$secondPerson['national_id']]), 'Failed contract left an orphan customer.');
$assert((int) Model::fetch('SELECT COUNT(*) AS n FROM contracts')['n'] === $beforeContracts, 'Failed contract was not rolled back.');
foreach ([['national_id' => 'bad'], ['mobile' => '123'], ['national_id' => User::find($customerId)['national_id'], 'mobile' => User::find($customerId)['mobile']]] as $invalid) {
    try {
        Contract::createWithInstallments($payload, [], [], [], [array_replace($newPerson, $invalid)]);
        throw new RuntimeException('Invalid/self guarantor accepted.');
    } catch (InvalidArgumentException $expected) {}
}
echo 'GUARANTOR_ACCOUNT_V202_OK contract=' . $mixedContract . ' no_guarantor=' . Contract::createWithInstallments($payload) . "\n";

echo "INTEGRATION_GUARANTOR_DOCUMENT_V159_OK\n";
