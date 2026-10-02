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
$reviewGuard = new ReflectionMethod(PaymentsController::class, 'hasUnresolvedGatewayReview');
$reviewGuard->setAccessible(true);
$assert($reviewGuard->invoke(new PaymentsController(), $contractId, $customerId) === true, 'Customer gateway retry was not blocked while captured money awaits review.');
$assert($reviewGuard->invoke(new PaymentsController(), $contractId, $adminId) === false, 'Gateway guard leaked a customer review to another actor.');
$panelTemplate = (string) file_get_contents(dirname(__DIR__) . '/views/installments/index.php');
$assert(strpos($panelTemplate, 'پرداخت در حال بررسی مالی') !== false && strpos($panelTemplate, 'gatewayReviewsByContract') !== false, 'Customer panel lacks a visible gateway reconciliation warning.');

$nextQuote = SettlementQuoteService::create($contractId, $ids, $customerId, 'selected', '2026-10-01');
$incomplete = PaymentGroupService::createPendingGateway($contractId, $ids, 1000000, $customerId, 'qa-review-incomplete-' . $suffix, 'qa-review-incomplete-key-' . $suffix, 'zibal', (string) $nextQuote['quote_uuid'], 'selected');
$incompleteReview = PaymentGroupService::recordGatewayReview((int) $incomplete['id'], null, null, 'Gateway success response without an exact amount.');
$assert(($incompleteReview['status'] ?? '') === 'review_required' && $incompleteReview['gateway_verified_amount'] === null, 'Malformed verified gateway response was not retained for manual review.');
$gatewayReviewGroups = Model::fetchAll(
    "SELECT pg.*, c.contract_number, u.full_name AS customer_name
     FROM payment_groups pg JOIN contracts c ON c.id = pg.contract_id JOIN users u ON u.id = pg.customer_id
     WHERE pg.id IN (?, ?) ORDER BY pg.id",
    [(int) $pending['id'], (int) $incomplete['id']]
);
$payments = [];
ob_start();
require dirname(__DIR__) . '/views/payments/index.php';
$reviewMarkup = ob_get_clean();
$assert(strpos($reviewMarkup, e(url('payments/reconcileGatewayGroup/' . (int) $pending['id']))) !== false, 'Admin review form is missing from rendered payments page.');
$assert(strpos($reviewMarkup, 'name="confirm_bank_receipt"') !== false && strpos($reviewMarkup, 'name="gateway_ref_id"') !== false, 'Bank attestation or typed reference is missing.');
$assert(strpos($reviewMarkup, e(url('payments/reconcileGatewayGroup/' . (int) $incomplete['id']))) === false, 'Incomplete gateway proof exposed an unsafe allocation action.');

$rejected = 0;
foreach ([
    [$customerId, 'qa-review-ref-' . $suffix, 'role'],
    [$adminId, 'wrong-reference', 'reference'],
] as $invalidAttempt) {
    try {
        PaymentGroupService::reconcileGatewayReview((int) $pending['id'], (int) $invalidAttempt[0], $invalidAttempt[1], 'QA rejection');
    } catch (InvalidArgumentException $expected) {
        $rejected++;
    }
}
$assert($rejected === 2, 'Unauthorized actor or mismatched gateway reference was accepted.');
$reconciled = PaymentGroupService::reconcileGatewayReview((int) $pending['id'], $adminId, 'qa-review-ref-' . $suffix, 'Verified against synthetic bank statement');
$assert(($reconciled['status'] ?? '') === 'completed', 'Authorized reconciliation did not complete.');
$assert(normalize_money($reconciled['allocated_amount'] ?? 0) === 3000000, 'Reconciliation lost a portion of the verified payment.');
$assert(($reconciled['gateway_ref_id'] ?? '') === 'qa-review-ref-' . $suffix, 'Reconciliation lost the external reference.');
$assert($reviewGuard->invoke(new PaymentsController(), $contractId, $customerId) === true, 'Another unresolved receipt was not respected by the gateway guard.');
$reconciledAllocations = Model::fetchAll('SELECT allocated_amount FROM payment_allocations WHERE payment_group_id = ? AND COALESCE(is_reversal, 0) = 0', [(int) $pending['id']]);
$assert(array_sum(array_map(static function ($row) { return normalize_money($row['allocated_amount']); }, $reconciledAllocations)) === 3000000, 'Reconciliation allocations do not equal the verified amount.');
$audit = Model::fetch("SELECT id FROM audit_logs WHERE event_action = 'review_reconciled' AND related_type = 'payment_group' AND related_id = ? LIMIT 1", [(int) $pending['id']]);
$assert((int) ($audit['id'] ?? 0) > 0, 'Reconciliation lacks a durable audit record.');
$repeat = PaymentGroupService::completeGateway((int) $pending['id'], 3000000, 'qa-review-ref-' . $suffix, $customerId);
$assert((int) $repeat['id'] === (int) $pending['id'], 'Callback retry after reconciliation is not idempotent.');
$allocationCount = Model::fetch('SELECT COUNT(*) AS n FROM payment_allocations WHERE payment_group_id = ? AND COALESCE(is_reversal, 0) = 0', [(int) $pending['id']]);
$assert((int) $allocationCount['n'] === count($reconciledAllocations), 'Callback retry duplicated allocations.');

// An external receipt larger than the remaining debt stays in review until
// a separate refund/credit policy is available.
$excessContractId = Contract::createWithInstallments([
    'customer_id' => $customerId, 'principal_amount' => 2000000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1,
    'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01', 'created_by' => $adminId,
]);
$excessRows = SettlementQuoteService::loadInstallments($excessContractId);
$excessIds = array_map(static function ($row) { return (int) $row['id']; }, $excessRows);
$excessQuote = SettlementQuoteService::create($excessContractId, $excessIds, $customerId, 'selected', '2026-10-01');
$excessPending = PaymentGroupService::createPendingGateway($excessContractId, $excessIds, 2000000, $customerId, 'qa-excess-track-' . $suffix, 'qa-excess-key-' . $suffix, 'zibal', (string) $excessQuote['quote_uuid'], 'selected');
PaymentGroupService::create($excessContractId, $excessIds, 1000000, $adminId, 'manual', 'QA competing excess payment', false, 'qa-excess-competing-' . $suffix, null, '2026-10-01', '10:00', 'selected');
$excessReview = PaymentGroupService::completeGateway((int) $excessPending['id'], 2000000, 'qa-excess-ref-' . $suffix, $customerId);
$assert(($excessReview['status'] ?? '') === 'review_required', 'Excess verified funds did not enter review.');
try {
    PaymentGroupService::reconcileGatewayReview((int) $excessPending['id'], $adminId, 'qa-excess-ref-' . $suffix, 'QA excess balance');
    throw new RuntimeException('Reconciliation allocated money beyond contract debt.');
} catch (InvalidArgumentException $expected) {
    $remainingReview = Model::fetch('SELECT status, gateway_verified_amount FROM payment_groups WHERE id = ?', [(int) $excessPending['id']]);
    $assert(($remainingReview['status'] ?? '') === 'review_required' && normalize_money($remainingReview['gateway_verified_amount'] ?? 0) === 2000000, 'Excess funds were not preserved after rejected reconciliation.');
    $excessAllocation = Model::fetch('SELECT COUNT(*) AS n FROM payment_allocations WHERE payment_group_id = ?', [(int) $excessPending['id']]);
    $assert((int) $excessAllocation['n'] === 0, 'Excess funds were partially allocated without a credit/refund policy.');
}

echo "INTEGRATION_GATEWAY_RECONCILIATION_V204_OK\n";
