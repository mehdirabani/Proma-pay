<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_GET['route'] = 'health/live';
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.142';
require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/plugins/PromaAccounting/src/Services/AccountingRepository.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$durations = [];
for ($iteration = 0; $iteration < 30; $iteration++) {
    $startedAt = microtime(true);
    $summary = \Proma\Plugins\Accounting\Services\AccountingRepository::dashboard();
    $series = \Proma\Plugins\Accounting\Services\AccountingRepository::dashboardSeries();
    $recent = \Proma\Plugins\Accounting\Services\AccountingRepository::recentLedger(8);
    $sellers = \Proma\Plugins\Accounting\Services\AccountingRepository::topSellers(5);
    $durations[] = (microtime(true) - $startedAt) * 1000;
}
sort($durations, SORT_NUMERIC);
$p50 = $durations[(int) floor((count($durations) - 1) * 0.50)];
$p95 = $durations[(int) floor((count($durations) - 1) * 0.95)];

if (!isset($summary['positive_balances'], $summary['payments_this_month']) || count($series['labels'] ?? []) !== 6 || count($recent) > 8 || count($sellers) > 5) {
    throw new RuntimeException('Accounting dashboard result shape is invalid.');
}
if ($p95 > 500) {
    throw new RuntimeException('Accounting dashboard p95 exceeded the local 500 ms diagnostic limit: ' . round($p95, 2));
}

$monthlyPlan = $pdo->query("EXPLAIN SELECT SUM(amount) FROM accounting_ledger_entries WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetch();
$commissionPlan = $pdo->query("EXPLAIN SELECT SUM(calculated_amount) FROM plugin_accounting_commissions WHERE status IN ('pending','approved','posted')")->fetch();
if (($monthlyPlan['key'] ?? '') !== 'idx_accounting_ledger_created_direction_type' || stripos((string) ($monthlyPlan['Extra'] ?? ''), 'Using index') === false) {
    throw new RuntimeException('Monthly ledger query does not use the covering dashboard index.');
}
if (($commissionPlan['key'] ?? '') !== 'idx_plugin_accounting_commission_status_amount' || stripos((string) ($commissionPlan['Extra'] ?? ''), 'Using index') === false) {
    throw new RuntimeException('Commission query does not use the covering status index.');
}

echo json_encode([
    'status' => 'PASSED',
    'iterations' => count($durations),
    'p50_ms' => round($p50, 2),
    'p95_ms' => round($p95, 2),
    'max_ms' => round(max($durations), 2),
    'ledger_rows' => (int) $pdo->query('SELECT COUNT(*) FROM accounting_ledger_entries')->fetchColumn(),
    'commission_rows' => (int) $pdo->query('SELECT COUNT(*) FROM plugin_accounting_commissions')->fetchColumn(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
