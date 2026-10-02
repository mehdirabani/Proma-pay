<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '' || !preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/', $dsn)) {
    fwrite(STDERR, "Use only the disposable proma_qa_v204 database.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.204';
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

$migrationName = 'database/migrations/2026_10_02_gateway_reconciliation_queue.sql';
$schemaSetup = new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema');
$schemaSetup->setAccessible(true);
$schemaSetup->invoke(null);
$runner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$runner->setAccessible(true);
$runner->invoke(null, $migrationName, (string) file_get_contents(dirname(__DIR__) . '/' . $migrationName));
foreach (['gateway_verified_amount', 'gateway_ref_id', 'reconciliation_reason'] as $column) {
    $assert((bool) $pdo->query("SHOW COLUMNS FROM payment_groups LIKE '" . $column . "'")->fetch(), 'Controlled migration missed ' . $column);
}
$migration = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
$assert(($migration['status'] ?? '') === 'success', 'Updater did not record successful reconciliation migration.');

$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$digits = static function (string $seed): string { return substr(preg_replace('/\D/', '', hash('sha256', $seed)), 0, 10); };
$adminId = User::create([
    'role' => 'admin', 'username' => 'qa-review-admin-' . $suffix, 'full_name' => 'مدیر آزمون تطبیق',
    'national_id' => $digits('admin-' . $suffix), 'mobile' => '0912' . substr($digits('admin-mobile-' . $suffix), 0, 7),
    'email' => 'qa-review-admin-' . $suffix . '@example.test', 'password' => 'Admin#Review204', 'status' => 'active',
]);
$mobile = '0915' . substr($digits('customer-mobile-' . $suffix), 0, 7);
$customerId = User::create([
    'role' => 'customer', 'username' => $digits('customer-' . $suffix), 'full_name' => 'مشتری آزمون تطبیق',
    'national_id' => $digits('customer-id-' . $suffix), 'mobile' => $mobile,
    'email' => 'qa-review-customer-' . $suffix . '@example.test', 'password' => substr($mobile, -4), 'status' => 'active',
]);
Settings::saveMany(['monthly_penalty_rate' => '0', 'legal_monthly_penalty_rate' => '0', 'monthly_reward_rate' => '0']);
$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId, 'principal_amount' => 4000000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2,
    'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01', 'created_by' => $adminId,
]);
$rows = SettlementQuoteService::loadInstallments($contractId);
$ids = array_map(static function ($row) { return (int) $row['id']; }, $rows);
$quote = SettlementQuoteService::create($contractId, $ids, $customerId, 'selected', '2026-10-01');
$pending = PaymentGroupService::createPendingGateway($contractId, $ids, 3000000, $customerId, 'qa-review-track-' . $suffix, 'qa-review-key-' . $suffix, 'zibal', (string) $quote['quote_uuid'], 'selected');

// A separate confirmed payment changes the financial snapshot after the
// customer leaves for the gateway. The gateway still captures 3m Toman.
PaymentGroupService::create($contractId, [(int) $rows[0]['id']], 1000000, $adminId, 'manual', 'QA competing payment', false, 'qa-review-competing-' . $suffix, null, '2026-10-01', '10:00', 'selected');
$review = PaymentGroupService::completeGateway((int) $pending['id'], 3000000, 'qa-review-ref-' . $suffix, $customerId);
$assert(($review['status'] ?? '') === 'review_required', 'Stale externally verified payment was not preserved for review.');
$assert(normalize_money($review['gateway_verified_amount'] ?? 0) === 3000000, 'Verified money was not stored exactly.');
$assert(($review['gateway_ref_id'] ?? '') === 'qa-review-ref-' . $suffix, 'Gateway reference was lost.');
$allocated = Model::fetch('SELECT COUNT(*) AS n FROM payment_allocations WHERE payment_group_id = ?', [(int) $pending['id']]);
$assert((int) $allocated['n'] === 0, 'Stale gateway payment was allocated with an invalid quote.');
$again = PaymentGroupService::completeGateway((int) $pending['id'], 3000000, 'qa-review-ref-' . $suffix, $customerId);
$assert((int) $again['id'] === (int) $review['id'] && ($again['status'] ?? '') === 'review_required', 'Gateway review callback is not idempotent.');
$visible = Model::fetch(
    "SELECT pg.id FROM payment_groups pg JOIN contracts c ON c.id = pg.contract_id JOIN users u ON u.id = pg.customer_id
     WHERE pg.status = 'review_required' AND pg.id = ? LIMIT 1",
    [(int) $pending['id']]
);
$assert((int) ($visible['id'] ?? 0) === (int) $pending['id'], 'Admin reconciliation queue cannot find the preserved gateway payment.');

$nextQuote = SettlementQuoteService::create($contractId, $ids, $customerId, 'selected', '2026-10-01');
$incomplete = PaymentGroupService::createPendingGateway($contractId, $ids, 1000000, $customerId, 'qa-review-incomplete-' . $suffix, 'qa-review-incomplete-key-' . $suffix, 'zibal', (string) $nextQuote['quote_uuid'], 'selected');
$incompleteReview = PaymentGroupService::recordGatewayReview((int) $incomplete['id'], null, null, 'Gateway success response without an exact amount.');
$assert(($incompleteReview['status'] ?? '') === 'review_required' && $incompleteReview['gateway_verified_amount'] === null, 'Malformed verified gateway response was not retained for manual review.');

echo "INTEGRATION_GATEWAY_RECONCILIATION_V204_OK\n";
