<?php
declare(strict_types=1);
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if (!preg_match('/(?:^|;)dbname=(?:proma_qa_v404_20261008|proma_qa_backup_roundtrip_v405_20261008)(?:;|$)/i', $dsn)) { fwrite(STDERR, "Owned payment QA database required\n"); exit(2); }
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.45';
require dirname(__DIR__) . '/bootstrap.php';
$pdo = new PDO($dsn, 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);
if (($argv[1] ?? '') === 'worker') {
    try {
        PaymentGroupService::create((int) $argv[2], [(int) $argv[3]], 100000, (int) $argv[4], 'manual', '', false, $argv[5], null, '2026-10-01', '12:00', 'schedule');
        echo "COMPLETED\n";
    } catch (RuntimeException $e) {
        if ($e->getCode() !== 409) throw $e;
        echo "IN_PROGRESS\n";
    }
    exit;
}
$admin = Model::fetch("SELECT id FROM users WHERE username LIKE 'qa-overflow-admin-%' ORDER BY id DESC LIMIT 1");
$customer = Model::fetch("SELECT id FROM users WHERE email LIKE 'qa-overflow-customer-%@example.test' ORDER BY id DESC LIMIT 1");
if (!$admin || !$customer) throw new RuntimeException('Run v404 retry fixtures first');
$contract = Contract::createWithInstallments(['customer_id' => (int) $customer['id'], 'principal_amount' => 1000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1, 'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01', 'created_by' => (int) $admin['id']]);
$installment = Model::fetch('SELECT id FROM installments WHERE contract_id = ?', [$contract]);
$key = 'qa-race-v404-' . bin2hex(random_bytes(8));
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string) $contract, (string) $installment['id'], (string) $admin['id'], $key], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Could not start competing payment worker');
    fclose($pipes[0]); $workers[] = [$process, $pipes];
}
foreach ($workers as [$process, $pipes]) {
    $output = trim(stream_get_contents($pipes[1])); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || !in_array($output, ['COMPLETED','IN_PROGRESS'], true)) throw new RuntimeException('Competing payment worker failed: ' . $error);
}
$groups = Model::fetchAll('SELECT id,allocated_amount FROM payment_groups WHERE idempotency_key = ?', [$key]);
$payments = Model::fetch('SELECT COUNT(*) AS n, SUM(amount) AS amount FROM payments WHERE contract_id = ?', [$contract]);
$allocation = Model::fetch('SELECT SUM(allocated_amount) AS amount FROM payment_allocations WHERE contract_id = ?', [$contract]);
if (count($groups) !== 1 || (int) $payments['n'] !== 1 || normalize_money($payments['amount']) !== 100000 || normalize_money($allocation['amount']) !== 100000) throw new RuntimeException('Competing requests duplicated or lost money');
echo "INTEGRATION_PAYMENT_REAL_TWO_WORKER_RACE_V404_OK\n";
