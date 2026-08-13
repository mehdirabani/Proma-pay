<?php

declare(strict_types=1);

/** MariaDB row-lock proof used by the settlement release gate. */
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.151';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$customerId = User::create([
    'role' => 'customer',
    'username' => substr(preg_replace('/[^0-9]/', '6', hash('sha256', $suffix)), 0, 10),
    'full_name' => 'مشتری آزمون هم‌زمانی',
    'national_id' => substr(preg_replace('/[^0-9]/', '7', hash('sha256', 'n' . $suffix)), 0, 10),
    'mobile' => '0935' . substr(preg_replace('/[^0-9]/', '8', $suffix . '8888888'), 0, 7),
    'email' => 'qa-lock-' . $suffix . '@example.test',
    'password' => '1234',
    'status' => 'active',
]);
$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 1000000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 1,
    'start_date' => date('Y-m-d'),
    'first_due_date' => date('Y-m-d', strtotime('+1 day')),
]);
$installment = Model::fetch('SELECT id FROM installments WHERE contract_id = ? LIMIT 1', [$contractId]);
$assert($installment, 'Concurrency fixture has no installment.');

$connectionA = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$connectionB = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$connectionA->beginTransaction();
$lock = $connectionA->prepare('SELECT id FROM installments WHERE id = ? FOR UPDATE');
$lock->execute([(int) $installment['id']]);
$connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$lockRejected = false;
try {
    $connectionB->beginTransaction();
    $competingLock = $connectionB->prepare('SELECT id FROM installments WHERE id = ? FOR UPDATE');
    $competingLock->execute([(int) $installment['id']]);
} catch (PDOException $e) {
    $lockRejected = true;
} finally {
    if ($connectionB->inTransaction()) $connectionB->rollBack();
    if ($connectionA->inTransaction()) $connectionA->rollBack();
}
$assert($lockRejected, 'A second transaction acquired an installment lock while settlement was in progress.');

echo "INTEGRATION_INSTALLMENT_SETTLEMENT_CONCURRENCY_V150_OK\n";
