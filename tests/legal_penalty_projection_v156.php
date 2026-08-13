<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/helpers/MoneyMath.php';

class Model
{
    public static $payments = [];
    public static function fetchAll($sql, $params = []) { return self::$payments; }
}

require_once $root . '/helpers/InstallmentFinancialStateService.php';
require_once $root . '/helpers/PaymentAllocationService.php';
require_once $root . '/helpers/CustomerPenaltyPresentationService.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$settings = [
    'monthly_penalty_rate' => '6',
    'legal_monthly_penalty_rate' => '15',
    'monthly_reward_rate' => '0',
    'late_penalty_grace_days' => '0',
    'show_projected_legal_penalty_to_customer' => '1',
    'projected_legal_penalty_customer_message' => 'پیام آزمون مقایسه‌ای',
];
$row = [
    'id' => 15601, 'contract_id' => 156, 'installment_number' => 1,
    'due_date' => '2026-01-01', 'base_amount' => 1000000,
    'paid_amount' => 0, 'remaining_amount' => 1000000, 'status' => 'overdue',
    'contract_status' => 'active', 'legal_case_count' => 1,
    // An internal case alone does not provide this canonical timestamp.
    'legal_started_at' => null,
];

$before = InstallmentFinancialStateService::state($row, [], $settings, '2026-01-31');
$assert($before['normal_penalty_accrued'] === 60000, 'Normal penalty before referral is incorrect.');
$assert($before['legal_penalty_accrued'] === 0, 'Internal case incorrectly activated actual legal penalty.');
$assert($before['projected_legal_penalty'] === 150000, 'Projected legal comparison is incorrect.');
$assert($before['effective_penalty_payable'] === 60000 && $before['final_payable'] === 1060000, 'Projected amount leaked into payable debt.');
$presentation = CustomerPenaltyPresentationService::forState($before, $settings);
$assert(!empty($presentation['show_projected_legal_penalty']) && $presentation['effective_penalty_payable'] === 60000, 'Customer projection presentation is not correctly isolated.');
$beforeHtml = penalty_display_html($presentation);
$assert(strpos($beforeHtml, '<del>جریمه حقوقی احتمالی:') !== false && strpos($beforeHtml, 'فعلاً اعمال نشده') !== false, 'Customer projection display is not explicitly struck through and labelled.');
$assert(strpos($beforeHtml, money_toman(60000)) !== false && strpos($beforeHtml, money_toman(150000)) !== false, 'Customer comparison display does not show the actual and projected amounts.');

$quote = PaymentAllocationService::quote([$row], '2026-01-31', $settings);
$assert($quote['full_settlement_total'] === 1060000, 'Settlement quote included projected legal penalty.');
$assert(!array_key_exists('projected_legal_penalty_total', $quote), 'Payment allocation must not expose a projected amount as a settlement total.');

$afterRow = $row;
$afterRow['legal_started_at'] = '2026-01-16 09:30:00';
$after = InstallmentFinancialStateService::state($afterRow, [], $settings, '2026-01-31');
$assert($after['normal_penalty_accrued'] === 30000 && $after['legal_penalty_accrued'] === 75000, 'Actual referral did not segment normal/legal penalties at the referral date.');
$assert($after['effective_penalty_payable'] === 105000 && $after['final_payable'] === 1105000, 'Actual legal penalty was not included exactly once after referral.');
$assert($after['projected_legal_penalty'] === 0 && empty(CustomerPenaltyPresentationService::forState($after, $settings)['show_projected_legal_penalty']), 'Projection remained visible after actual legal referral.');
$afterHtml = penalty_display_html(CustomerPenaltyPresentationService::forState($after, $settings));
$assert(strpos($afterHtml, 'جریمه حقوقی پس از ارجاع') !== false && strpos($afterHtml, 'فعلاً اعمال نشده') === false, 'Actual legal display must use the canonical legal breakdown without the comparison badge.');

$partialPayment = [[
    'id' => 1, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-01-16',
    'amount' => 500000, 'principal_applied' => 500000, 'normal_penalty_applied' => 0,
    'legal_penalty_applied' => 0, 'reward_applied' => 0,
]];
$partial = InstallmentFinancialStateService::state($row, $partialPayment, $settings, '2026-01-31');
$assert($partial['remaining_principal'] === 500000 && $partial['final_payable'] > 0, 'Partial payment changed the principal state incorrectly.');
$assert($partial['final_payable'] === $partial['remaining_principal'] + $partial['effective_penalty_payable'], 'Partial payment payable includes a non-financial projection.');

$paid = InstallmentFinancialStateService::state($row, [[
    'id' => 2, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-01-31',
    'amount' => 1060000, 'principal_applied' => 1000000, 'normal_penalty_applied' => 60000,
    'legal_penalty_applied' => 0, 'reward_applied' => 0,
]], $settings, '2026-01-31');
$assert($paid['final_payable'] === 0 && $paid['projected_legal_penalty'] === 0, 'Full settlement left a projected legal balance.');

$missingRate = $settings;
$missingRate['legal_monthly_penalty_rate'] = '0';
$missing = InstallmentFinancialStateService::state($row, [], $missingRate, '2026-01-31');
$assert($missing['projected_legal_penalty'] === 0 && empty(CustomerPenaltyPresentationService::forState($missing, $missingRate)['show_projected_legal_penalty']), 'A missing legal rate must hide the comparison.');

$inconsistentRow = $row;
$inconsistentRow['inconsistent_legal_referral_count'] = 1;
$inconsistent = InstallmentFinancialStateService::state($inconsistentRow, [], $settings, '2026-01-31');
$assert($inconsistent['calculation_status'] === 'inconsistent_referral' && !$inconsistent['payment_allowed'], 'A formal case without canonical referral timestamp was treated as safe zero.');
$missingActual = $afterRow;
$missingActualRate = $settings;
$missingActualRate['legal_monthly_penalty_rate'] = '0';
$missingActual = InstallmentFinancialStateService::state($missingActual, [], $missingActualRate, '2026-01-31');
$assert($missingActual['calculation_status'] === 'configuration_missing' && !$missingActual['payment_allowed'], 'A missing actual legal rate was treated as a payable zero.');

echo "LEGAL_PENALTY_PROJECTION_V156_OK\n";
