<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/', $dsn)) {
    fwrite(STDERR, "Use only the disposable proma_qa_v204 database.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.221';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$property = new ReflectionProperty(Model::class, 'pdo');

$property->setValue(null, $pdo);

$rows = $pdo->query("SELECT DISTINCT c.customer_id
                     FROM payments p JOIN contracts c ON c.id = p.contract_id
                     WHERE p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
                     ORDER BY c.customer_id LIMIT 24")->fetchAll();
$ids = array_map(static fn ($row) => (int) $row['customer_id'], $rows);
if (count($ids) < 2) {
    throw new RuntimeException('QA fixture needs payments for at least two customers.');
}
$batched = Payment::monthlyTrendsForCustomers($ids);
foreach ($ids as $id) {
    if (($batched[$id] ?? null) !== Payment::monthlyTrendForCustomer($id)) {
        throw new RuntimeException('Payment chart changed for customer ' . $id);
    }
}
echo 'INTEGRATION_CUSTOMER_TREND_BATCH_OK customers=' . count($ids) . PHP_EOL;
