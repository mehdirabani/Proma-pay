<?php

declare(strict_types=1);

/** Real-MariaDB coverage for schedule-scoped overflow in manual and gateway paths. */
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
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
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$digits = static function ($value, string $seed): string { return substr(preg_replace('/\D/', '', hash('sha256', $seed . $value)), 0, 10); };
$adminId = User::create([
    'role' => 'admin', 'username' => 'qa-overflow-admin-' . $suffix, 'full_name' => 'مدیر آزمون اضافه‌پرداخت',
    'national_id' => $digits($suffix, 'admin'), 'mobile' => '0912' . substr($digits($suffix, 'admin-mobile'), 0, 7),
    'email' => 'qa-overflow-admin-' . $suffix . '@example.test', 'password' => 'Admin#Overflow204', 'status' => 'active',
]);
$customerMobile = '0915' . substr($digits($suffix, 'customer-mobile'), 0, 7);
$customerId = User::create([
    'role' => 'customer', 'username' => $digits($suffix, 'customer'), 'full_name' => 'مشتری آزمون اضافه‌پرداخت',
    'national_id' => $digits($suffix, 'customer-id'), 'mobile' => $customerMobile,
    'email' => 'qa-overflow-customer-' . $suffix . '@example.test', 'password' => substr($customerMobile, -4), 'status' => 'active',
]);
Settings::saveMany([
    'monthly_penalty_rate' => '0', 'legal_monthly_penalty_rate' => '0',
    'monthly_reward_rate' => '0', 'late_penalty_grace_days' => '0',
]);

$seedSchedule = static function () use ($customerId, $adminId): array {
    $contractId = Contract::createWithInstallments([
        'customer_id' => $customerId, 'principal_amount' => 7000000, 'down_payment_amount' => 0,
        'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 3,
        'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01', 'created_by' => $adminId,
    ]);
    $rows = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number, id', [$contractId]);
    $bases = [1000000, 2000000, 4000000];
    $dates = ['2026-11-01', '2026-12-01', '2027-01-01'];
    foreach ($rows as $index => $row) {
        Model::execute('UPDATE installments SET base_amount = ?, paid_amount = 0, remaining_amount = ?, due_date = ?, status = ? WHERE id = ?', [$bases[$index], $bases[$index], $dates[$index], 'pending', (int) $row['id']]);
    }
    return [$contractId, SettlementQuoteService::loadInstallments($contractId, array_column($rows, 'id'))];
};

[$manualContractId, $manualRows] = $seedSchedule();
$costUuid = 'QA-LCC-' . strtoupper($suffix);
Model::execute(
    "INSERT INTO legal_case_costs (cost_uuid, contract_id, category, title, amount_toman, cost_date, payment_status, approval_status, chargeable_to_customer, created_by, approved_by, created_at, approved_at)
     VALUES (?, ?, 'هزینه آزمون', 'هزینه حقوقی تأییدشده آزمون', 500000, '2026-10-01', 'pending', 'approved', 1, ?, ?, NOW(), NOW())",
    [$costUuid, $manualContractId, $adminId, $adminId]
);
$scheduleQuote = SettlementQuoteService::create($manualContractId, [], $adminId, 'schedule', '2026-10-01');
$contractQuote = SettlementQuoteService::create($manualContractId, [], $adminId, 'contract', '2026-10-01');
$assert(normalize_money($scheduleQuote['legal_cost_total'] ?? 0) === 0, 'Schedule overflow quote incorrectly included legal costs.');
$assert(normalize_money($scheduleQuote['full_settlement_total'] ?? 0) === 7000000, 'Schedule overflow quote does not equal installment debt only.');
$assert(normalize_money($contractQuote['legal_cost_total'] ?? 0) === 500000, 'Contract settlement quote stopped including approved legal costs.');
$manualKey = 'qa-overflow-manual-' . $suffix;
$manual = PaymentGroupService::create($manualContractId, [(int) $manualRows[0]['id']], 4000000, $adminId, 'manual', 'QA automatic next-installment allocation', false, $manualKey, null, '2026-10-01', '10:00', 'schedule');
$manualAllocations = Model::fetchAll('SELECT * FROM payment_allocations WHERE payment_group_id = ? AND COALESCE(is_reversal, 0) = 0 ORDER BY installment_id', [(int) $manual['id']]);
$assert(($manual['allocation_scope'] ?? '') === 'schedule', 'Manual group did not persist schedule allocation scope.');
$assert(array_map(static function ($row) { return (int) $row['allocated_amount']; }, $manualAllocations) === [1000000, 2000000, 1000000], 'Manual overflow was not allocated across future installments in order.');
$assert(array_sum(array_map(static function ($row) { return (int) $row['allocated_amount']; }, $manualAllocations)) === 4000000, 'Manual overflow did not allocate the exact amount received.');
$costAfterSchedulePayment = Model::fetch('SELECT paid_amount_toman, approval_status FROM legal_case_costs WHERE cost_uuid = ?', [$costUuid]);
$assert(normalize_money($costAfterSchedulePayment['paid_amount_toman'] ?? 0) === 0, 'Schedule overflow payment was incorrectly applied to a legal cost.');
$manualRepeat = PaymentGroupService::create($manualContractId, [(int) $manualRows[0]['id']], 4000000, $adminId, 'manual', 'QA retry', false, $manualKey, null, '2026-10-01', '10:00', 'schedule');
$assert((int) $manualRepeat['id'] === (int) $manual['id'], 'Manual overflow retry created a duplicate payment group.');

[$gatewayContractId, $gatewayRows] = $seedSchedule();
$gatewayQuote = SettlementQuoteService::create($gatewayContractId, [], $customerId, 'schedule', '2026-10-01');
$gatewayIds = array_values(array_unique(array_map('intval', json_decode((string) $gatewayQuote['selected_installment_ids_json'], true) ?: [])));
$pending = PaymentGroupService::createPendingGateway($gatewayContractId, $gatewayIds, 4000000, $customerId, 'qa-overflow-track-' . $suffix, 'qa-overflow-gateway-' . $suffix, 'zibal', (string) $gatewayQuote['quote_uuid'], 'schedule');
$completed = PaymentGroupService::completeGateway((int) $pending['id'], 4000000, 'qa-overflow-ref-' . $suffix, $customerId);
$gatewayAllocations = Model::fetchAll('SELECT * FROM payment_allocations WHERE payment_group_id = ? AND COALESCE(is_reversal, 0) = 0 ORDER BY installment_id', [(int) $pending['id']]);
$assert(($completed['status'] ?? '') === 'completed' && ($pending['allocation_scope'] ?? '') === 'schedule', 'Gateway overflow group did not complete with its persisted scope.');
$assert(array_map(static function ($row) { return (int) $row['allocated_amount']; }, $gatewayAllocations) === [1000000, 2000000, 1000000], 'Gateway callback allocation differs from the approved schedule quote.');
$repeatCallback = PaymentGroupService::completeGateway((int) $pending['id'], 4000000, 'qa-overflow-ref-' . $suffix, $customerId);
$assert((int) $repeatCallback['id'] === (int) $pending['id'], 'Repeated gateway callback did not remain idempotent.');

echo "INTEGRATION_PAYMENT_OVERFLOW_ALLOCATION_V204_OK\n";
