<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/helpers/MoneyMath.php';

// This unit test intentionally avoids a database; integration coverage uses
// the same public services against MariaDB in the release gate.
class Model
{
    public static $payments = [];
    public static function fetchAll($sql, $params = []) { return self::$payments; }
}

require_once $root . '/helpers/InstallmentFinancialStateService.php';
require_once $root . '/helpers/PaymentAllocationService.php';
require_once $root . '/helpers/InstallmentSettlementService.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$settings = ['monthly_penalty_rate' => '0', 'legal_monthly_penalty_rate' => '0', 'monthly_reward_rate' => '0', 'late_penalty_grace_days' => '0'];
$rows = [];
foreach ([[1, 1500000], [2, 1500000], [3, 2000000]] as $pair) {
    $rows[] = ['id' => $pair[0], 'contract_id' => 77, 'installment_number' => $pair[0], 'due_date' => '2026-08-01', 'base_amount' => $pair[1], 'paid_amount' => 0, 'remaining_amount' => $pair[1], 'status' => 'pending', 'contract_status' => 'active'];
}
$plan = PaymentAllocationService::plan($rows, 4000000, '2026-07-26', $settings);
$assert($plan['full_settlement_total'] === 5000000, 'Selected settlement total is not exact.');
$assert(array_column($plan['allocations'], 'allocated_amount') === [1500000, 1500000, 1000000], 'Allocation order or partial allocation is not deterministic.');
$assert($plan['allocations'][2]['remaining_after'] === 1000000 && $plan['allocations'][2]['reward_applied'] === 0, 'Partial third installment incorrectly settled or rewarded.');

$late = ['id' => 11, 'contract_id' => 77, 'installment_number' => 1, 'due_date' => '2026-07-01', 'base_amount' => 1000000, 'paid_amount' => 0, 'remaining_amount' => 1000000, 'status' => 'overdue', 'contract_status' => 'active'];
$latePlan = PaymentAllocationService::plan([$late], 100000, '2026-07-26', ['monthly_penalty_rate' => '3', 'legal_monthly_penalty_rate' => '6', 'monthly_reward_rate' => '1', 'late_penalty_grace_days' => '0']);
$lateAllocation = $latePlan['allocations'][0];
$assert($lateAllocation['normal_penalty_applied'] > 0 && $lateAllocation['principal_applied'] < 100000, 'Penalty was not allocated before principal.');
$assert($lateAllocation['reward_applied'] === 0, 'Partial payment received an early-settlement reward.');

$settled = InstallmentFinancialStateService::state(
    $rows[0],
    [['id' => 1, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-07-26', 'amount' => 1500000, 'principal_applied' => 1500000, 'normal_penalty_applied' => 0, 'legal_penalty_applied' => 0, 'reward_applied' => 0]],
    $settings,
    '2026-07-26'
);
$assert($settled['status'] === 'paid' && !$settled['payment_allowed'] && $settled['final_payable'] === 0, 'Settled installment remains payable.');

$stalePartial = $rows[2];
$stalePartial['status'] = 'paid';
$stalePartial['contract_status'] = 'completed';
$stalePartial['paid_amount'] = 2000000;
$stalePartial['remaining_amount'] = 0;
$derivedPartial = InstallmentFinancialStateService::state($stalePartial, [[
    'id' => 2, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-07-26', 'amount' => 1000000,
    'principal_applied' => 1000000, 'normal_penalty_applied' => 0, 'legal_penalty_applied' => 0, 'reward_applied' => 0,
]], $settings, '2026-07-26');
$assert($derivedPartial['status'] === 'partial' && $derivedPartial['payment_allowed'] && $derivedPartial['remaining_principal'] === 1000000, 'A stale paid display field hid a partially paid installment.');
$assert(!InstallmentSettlementService::isSettled(array_merge($stalePartial, $derivedPartial)), 'Settlement guard rejected a partially paid installment from stale display fields.');

echo "INSTALLMENT_SETTLEMENT_ENGINE_V150_OK\n";
