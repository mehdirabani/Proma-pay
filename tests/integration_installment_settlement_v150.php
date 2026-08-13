<?php

declare(strict_types=1);

/**
 * Real-MariaDB acceptance test for the v1.5 settlement engine.
 *
 * It deliberately uses a single 5,000,000-Toman contract, then accepts a
 * 4,000,000-Toman selected payment.  The expected immutable allocation is
 * 1,500,000 + 1,500,000 + 1,000,000 and must never spill to another row.
 */
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.150';

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
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$mobile = '09' . substr(preg_replace('/[^0-9]/', '7', $suffix . '777777777'), 0, 9);
$nationalId = substr(preg_replace('/[^0-9]/', '6', hash('sha256', $suffix)), 0, 10);

$adminId = User::create([
    'role' => 'admin',
    'username' => 'qa-settlement-admin-' . $suffix,
    'full_name' => 'مدیر آزمون تسویه',
    'national_id' => substr(preg_replace('/[^0-9]/', '8', hash('sha256', 'a' . $suffix)), 0, 10),
    'mobile' => '0912' . substr(preg_replace('/[^0-9]/', '4', $suffix . '4444444'), 0, 7),
    'email' => 'qa-settlement-admin-' . $suffix . '@example.test',
    'password' => 'Admin#Settlement150',
    'status' => 'active',
]);
$customerId = User::create([
    'role' => 'customer',
    'username' => $nationalId,
    'full_name' => 'مشتری آزمون تسویه',
    'national_id' => $nationalId,
    'mobile' => $mobile,
    'email' => 'qa-settlement-customer-' . $suffix . '@example.test',
    'password' => substr($mobile, -4),
    'status' => 'active',
]);

Settings::saveMany([
    'monthly_penalty_rate' => '0',
    'legal_monthly_penalty_rate' => '0',
    'monthly_reward_rate' => '0',
    'late_penalty_grace_days' => '0',
    'settlement_quote_ttl_seconds' => '300',
]);

$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 5000000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 3,
    'start_date' => '2026-07-01',
    'first_due_date' => '2026-08-01',
    'created_by' => $adminId,
]);
$rows = Model::fetchAll('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number, id', [$contractId]);
$assert(count($rows) === 3, 'Settlement QA fixture does not contain three installments.');
$expectedBases = [1500000, 1500000, 2000000];
foreach ($rows as $index => $row) {
    Model::execute(
        'UPDATE installments SET base_amount = ?, paid_amount = 0, remaining_amount = ?, due_date = ?, status = ? WHERE id = ?',
        [$expectedBases[$index], $expectedBases[$index], '2026-08-01', 'pending', (int) $row['id']]
    );
}
$rows = SettlementQuoteService::loadInstallments($contractId, array_column($rows, 'id'));
$quote = SettlementQuoteService::persistQuote(Model::fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]), $rows, $adminId, 'selected', '2026-07-26');
$assert((int) ($quote['full_settlement_total'] ?? 0) === 5000000, 'Server quote for all selected installments is not exactly 5,000,000 Toman.');

$requestUuid = 'qa-settlement-' . $suffix;
$group = PaymentGroupService::create(
    $contractId,
    array_reverse(array_column($rows, 'id')),
    4000000,
    $adminId,
    'manual',
    'QA deterministic partial settlement',
    false,
    $requestUuid,
    (string) $quote['quote_uuid'],
    '2026-07-26',
    '11:30'
);
$assert((int) ($group['allocated_amount'] ?? 0) === 4000000 && ($group['status'] ?? '') === 'completed', 'Parent payment group was not completed with the exact accepted amount.');

$allocations = Model::fetchAll(
    'SELECT * FROM payment_allocations WHERE payment_group_id = ? AND COALESCE(is_reversal, 0) = 0 ORDER BY id',
    [(int) $group['id']]
);
$assert(count($allocations) === 3, 'The selected payment did not create exactly three allocation rows.');
$assert(array_map(static function ($row) { return (int) $row['allocated_amount']; }, $allocations) === [1500000, 1500000, 1000000], 'Allocation did not follow deterministic server ordering.');
$assert(array_map(static function ($row) { return (int) $row['installment_id']; }, $allocations) === array_map(static function ($row) { return (int) $row['id']; }, $rows), 'Checkbox order changed installment allocation order.');

$states = [];
foreach ($rows as $row) {
    $states[] = InstallmentFinancialStateService::stateForInstallment((int) $row['id'], '2026-07-26');
}
$assert(($states[0]['status'] ?? '') === 'paid' && ($states[1]['status'] ?? '') === 'paid', 'The first two installments were not settled.');
$assert(($states[2]['status'] ?? '') === 'partial' && (int) ($states[2]['remaining_principal'] ?? -1) === 1000000, 'The final installment did not retain exactly 1,000,000 Toman.');
$assert((int) ($states[2]['eligible_reward'] ?? -1) === 0, 'A partial payment incorrectly received a settlement reward.');

$duplicate = PaymentGroupService::create(
    $contractId,
    array_column($rows, 'id'),
    4000000,
    $adminId,
    'manual',
    'QA duplicate',
    false,
    $requestUuid,
    (string) $quote['quote_uuid'],
    '2026-07-26',
    '11:30'
);
$assert((int) ($duplicate['id'] ?? 0) === (int) $group['id'], 'The same payment request UUID created a duplicate group.');
$count = Model::fetch('SELECT COUNT(*) AS total FROM payment_groups WHERE contract_id = ? AND idempotency_key = ?', [$contractId, $requestUuid]);
$assert((int) ($count['total'] ?? 0) === 1, 'Idempotency table contains more than one parent group.');

$paidRejected = false;
try {
    Payment::record((int) $rows[0]['id'], $contractId, $adminId, 1, 'manual', 'paid', null, null, 'Should fail', '2026-07-26');
} catch (InvalidArgumentException $e) {
    $paidRejected = $e->getCode() === 409 && $e->getMessage() === InstallmentSettlementService::SETTLED_MESSAGE;
}
$assert($paidRejected, 'A paid installment accepted a second payment.');

$correction = Payment::correct((int) $allocations[2]['payment_id'], 'QA immutable reversal', $adminId);
$assert(!empty($correction['ok']), 'Payment correction did not complete.');
$reversal = Model::fetch('SELECT * FROM payment_allocations WHERE reversal_of_allocation_id = ? LIMIT 1', [(int) $allocations[2]['id']]);
$assert($reversal && (int) $reversal['is_reversal'] === 1 && (int) $reversal['allocated_amount'] === -1000000, 'Correction did not append an immutable negative allocation.');
$afterCorrection = InstallmentFinancialStateService::stateForInstallment((int) $rows[2]['id'], '2026-07-26');
$assert((int) ($afterCorrection['remaining_principal'] ?? -1) === 2000000, 'Correction did not restore the corrected installment state.');

$gatewayQuote = SettlementQuoteService::create($contractId, [(int) $rows[2]['id']], $customerId, 'selected', '2026-07-26');
$gatewayTrack = 'qa-gateway-' . $suffix;
$gatewayPaymentId = Payment::createPendingGatewayFor('zibal', (int) $rows[2]['id'], $contractId, $customerId, 2000000, $gatewayTrack, (string) $gatewayQuote['quote_uuid']);
$gatewayResult = Payment::completeGateway($gatewayTrack, 'qa-ref-' . $suffix, 2000000, ['notify' => false]);
$assert(!empty($gatewayResult['ok']) && (int) ($gatewayResult['payment_id'] ?? 0) === $gatewayPaymentId, 'Gateway payment was not committed from its server quote.');
$gatewayRepeat = Payment::completeGateway($gatewayTrack, 'qa-ref-' . $suffix, 2000000, ['notify' => false]);
$assert(!empty($gatewayRepeat['already_paid']), 'Gateway callback idempotency did not return the original payment.');
$usedQuote = Model::fetch('SELECT status FROM settlement_quotes WHERE quote_uuid = ?', [(string) $gatewayQuote['quote_uuid']]);
$assert(($usedQuote['status'] ?? '') === SettlementQuoteService::STATUS_USED, 'Completed single gateway payment did not consume its quote.');

echo "INTEGRATION_INSTALLMENT_SETTLEMENT_V150_OK\n";
