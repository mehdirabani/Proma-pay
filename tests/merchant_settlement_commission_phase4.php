<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/MerchantSettlementCalculator.php';
require_once __DIR__ . '/../helpers/MerchantSettlementPolicy.php';
require_once __DIR__ . '/../helpers/MerchantCommissionPolicy.php';
require_once __DIR__ . '/../helpers/MerchantSettlementCalendar.php';

function merchantPhase4Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function merchantPhase4Rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        return;
    }
    throw new RuntimeException($message);
}

$receivable = MerchantSettlementCalculator::calculateReceivable(10000000, 2500000, 100000, 20000);
merchantPhase4Assert($receivable['gross_receivable_toman'] === 7500000, 'Gross receivable did not exclude only the verified down payment.');
merchantPhase4Assert($receivable['net_settlement_toman'] === 7420000, 'Contractual deductions or approved adjustments were misapplied.');
merchantPhase4Assert($receivable['currency_unit'] === 'toman', 'Settlement currency unit is ambiguous.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementCalculator::calculateReceivable(1000, 1001);
}, 'Down payment above cash price was accepted.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementCalculator::calculateReceivable(1000, 0, 1001);
}, 'Deductions above gross receivable were accepted.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementCalculator::calculateReceivable(1000, 0, 0, -1001);
}, 'Negative net settlement was accepted.');
$gates = [
    'initial_payment_verified' => true,
    'contract_approved' => true,
    'guarantee_confirmed' => true,
    'documents_complete' => true,
    'delivery_confirmed' => true,
    'no_hold_or_dispute' => true,
    'exposure_within_limit' => true,
    'legal_and_funding_approved' => true,
    'adjustments_resolved' => true,
    'positive_net_settlement' => true,
];
merchantPhase4Assert(MerchantSettlementCalculator::eligibility($gates)['status'] === 'eligible', 'All verified gates did not make an obligation eligible.');
$pending = $gates;
$pending['delivery_confirmed'] = false;
merchantPhase4Assert(MerchantSettlementCalculator::eligibility($pending)['status'] === 'pending', 'A missing delivery check did not block eligibility.');
$held = $gates;
$held['hold_or_dispute'] = true;
merchantPhase4Assert(MerchantSettlementCalculator::eligibility($held)['status'] === 'suspended', 'A hold did not suspend eligibility.');

$disabledSettlement = MerchantSettlementPolicy::resolve([
    'enabled' => false,
    'currency_unit' => 'toman',
    'settlement_basis' => 'cash_product_price_less_verified_down_payment',
    'schedule' => ['type' => 'every_n_days', 'interval_days' => 15],
]);
merchantPhase4Assert($disabledSettlement['enabled'] === false, 'Settlement policy did not default safely to disabled.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementPolicy::resolve([
        'enabled' => true,
        'currency_unit' => 'toman',
        'settlement_basis' => 'cash_product_price_less_verified_down_payment',
        'receivable_ownership_model' => 'unapproved',
        'schedule' => ['type' => 'manual'],
    ]);
}, 'Operational settlement policy without legal and funding references was accepted.');
$approvedSettlement = MerchantSettlementPolicy::resolve([
    'enabled' => true,
    'currency_unit' => 'toman',
    'settlement_basis' => 'cash_product_price_less_verified_down_payment',
    'receivable_ownership_model' => 'third_party_funder',
    'funding_account_uuid' => '123e4567-e89b-42d3-a456-426614174000',
    'legal_contract_reference' => 'MERCHANT-CONTRACT-2026-01',
    'legal_approval_reference' => 'LEGAL-APPROVAL-2026-01',
    'max_single_settlement_toman' => 5000000,
    'max_merchant_exposure_toman' => 20000000,
    'max_platform_exposure_toman' => 100000000,
    'schedule' => [
        'type' => 'fixed_jalali_days', 'days' => [15, 31],
        'short_month_behavior' => 'clamp_to_month_end',
        'holiday_policy' => 'next_business_day', 'business_weekdays_iso' => [1, 2, 3, 4, 6, 7],
    ],
]);
merchantPhase4Assert($approvedSettlement['schedule']['type'] === 'fixed_jalali_days', 'Jalali settlement schedule was not retained.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementPolicy::resolve([
        'enabled' => false,
        'settlement_basis' => 'installment_total',
        'schedule' => ['type' => 'every_n_days', 'interval_days' => 15],
    ]);
}, 'Installment total was allowed as seller receivable basis.');

$everyFifteen = MerchantSettlementCalendar::nextDueDate(
    ['type' => 'every_n_days', 'interval_days' => 15, 'holiday_policy' => 'no_shift'],
    '2026-01-01',
    '2026-01-17'
);
merchantPhase4Assert($everyFifteen === '2026-01-31', 'Every 15 days was confused with monthly dates.');
$fixedJalali = MerchantSettlementCalendar::nextDueDate(
    $approvedSettlement,
    '2026-03-21',
    '2026-03-21'
);
[$y, $m, $d] = gregorian_to_jalali(2026, 3, 21);
[$gy, $gm, $gd] = jalali_to_gregorian($y, $m, 15);
$expectedFixed = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
merchantPhase4Assert($fixedJalali === $expectedFixed, 'The 15th of the Jalali month was calculated incorrectly.');
$endOfMonth = MerchantSettlementCalendar::nextDueDate(
    ['type' => 'jalali_month_end', 'holiday_policy' => 'no_shift'],
    '2026-03-21',
    '2026-03-21'
);
[$nextYear, $nextMonth] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
[$ny, $nm, $nd] = jalali_to_gregorian($nextYear, $nextMonth, 1);
$expectedEnd = (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ny, $nm, $nd)))->modify('-1 day')->format('Y-m-d');
merchantPhase4Assert($endOfMonth === $expectedEnd, 'The Jalali month-end schedule was not independent from every-N-days.');
$holidayBase = MerchantSettlementCalendar::nextDueDate(
    ['type' => 'every_n_days', 'interval_days' => 1, 'holiday_policy' => 'next_business_day', 'business_weekdays_iso' => [1, 2, 3, 4, 5, 6, 7]],
    '2026-03-21',
    '2026-03-21',
    ['2026-03-21']
);
merchantPhase4Assert($holidayBase === '2026-03-22', 'Holiday transfer policy did not move the due date to the next business day.');
merchantPhase4Rejects(static function (): void {
    MerchantSettlementCalendar::nextDueDate(
        ['type' => 'manual'], '2026-03-21', '2026-03-21', [], '2026-03-22', false
    );
}, 'Unapproved manual settlement date was accepted.');

$commissionPolicy = MerchantCommissionPolicy::resolve([
    'enabled' => true,
    'commission_rules' => [
        [
            'rule_code' => 'cash-sale', 'type' => 'cash_price_percent', 'rate_bps' => 100,
            'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
            'recognition_event' => 'contract_activated', 'payable_rule' => 'after_refund_window',
            'refund_window_days' => 30,
            'reversal_policy' => 'proportional',
        ],
        [
            'rule_code' => 'volume-tier', 'type' => 'tiered_cash_volume_percent',
            'tiers' => [
                ['minimum_monthly_cash_volume_toman' => 0, 'rate_bps' => 100],
                ['minimum_monthly_cash_volume_toman' => 5000000, 'rate_bps' => 200],
            ],
            'payer' => 'merchant', 'payee_type' => 'user', 'payee_user_id' => 71,
            'recognition_event' => 'settlement_paid', 'payable_rule' => 'manual_approval',
            'reversal_policy' => 'full_refund_only',
        ],
    ],
    'reward_rules' => [[
        'rule_code' => 'referral', 'type' => 'fixed_per_referral', 'fixed_amount_toman' => 50000,
        'payer' => 'platform', 'payee_type' => 'referral_member', 'payee_user_id' => null,
        'recognition_event' => 'installment_collected', 'payable_rule' => 'manual_approval',
        'reversal_policy' => 'proportional',
    ]],
]);
merchantPhase4Assert(count($commissionPolicy['commission_rules']) === 2, 'Commission rules were not versionable.');
merchantPhase4Assert(count($commissionPolicy['reward_rules']) === 1, 'Referral rewards were not kept in their own policy collection.');
merchantPhase4Assert(
    MerchantSettlementCalculator::calculateEarning($commissionPolicy['commission_rules'][0], ['cash_product_price_toman' => 1000000]) === 10000,
    'Cash-price commission percentage is incorrect.'
);
merchantPhase4Assert(
    MerchantSettlementCalculator::calculateEarning($commissionPolicy['commission_rules'][1], ['cash_product_price_toman' => 1000000, 'monthly_cash_volume_toman' => 5000000]) === 20000,
    'Tiered commission did not use the approved monthly volume tier.'
);
merchantPhase4Assert(
    MerchantSettlementCalculator::calculateEarning($commissionPolicy['reward_rules'][0], []) === 50000,
    'Referral reward did not use its independent amount.'
);
merchantPhase4Rejects(static function (): void {
    MerchantCommissionPolicy::resolve([
        'enabled' => true,
        'commission_rules' => [[
            'rule_code' => 'bad', 'type' => 'cash_price_percent', 'rate_bps' => 10001,
            'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
            'recognition_event' => 'contract_activated', 'payable_rule' => 'on_recognition',
            'reversal_policy' => 'proportional',
        ]],
    ]);
}, 'Commission rate above 100 percent was accepted.');
merchantPhase4Rejects(static function (): void {
    MerchantCommissionPolicy::resolve([
        'enabled' => true,
        'commission_rules' => [],
        'reward_rules' => [[
            'rule_code' => 'bad-tier', 'type' => 'tiered_cash_volume_percent',
            'tiers' => [
                ['minimum_monthly_cash_volume_toman' => 500, 'rate_bps' => 200],
                ['minimum_monthly_cash_volume_toman' => 0, 'rate_bps' => 100],
            ],
            'payer' => 'platform', 'payee_type' => 'user', 'payee_user_id' => 72,
            'recognition_event' => 'installment_collected', 'payable_rule' => 'manual_approval',
            'reversal_policy' => 'proportional',
        ]],
    ]);
}, 'Unordered commission tiers were accepted.');
merchantPhase4Rejects(static function (): void {
    MerchantCommissionPolicy::resolve([
        'enabled' => true,
        'commission_rules' => [[
            'rule_code' => 'repeated-sale', 'type' => 'cash_price_percent', 'rate_bps' => 100,
            'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
            'recognition_event' => 'installment_collected', 'payable_rule' => 'on_recognition',
            'reversal_policy' => 'proportional',
        ]],
    ]);
}, 'A one-time cash-price commission was allowed to recur on each installment.');
merchantPhase4Rejects(static function (): void {
    MerchantCommissionPolicy::resolve([
        'enabled' => true,
        'commission_rules' => [[
            'rule_code' => 'missing-window', 'type' => 'cash_price_percent', 'rate_bps' => 100,
            'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
            'recognition_event' => 'contract_activated', 'payable_rule' => 'after_refund_window',
            'reversal_policy' => 'proportional',
        ]],
    ]);
}, 'A refund-window commission without a duration was accepted.');

echo "MERCHANT_SETTLEMENT_COMMISSION_PHASE4_OK\n";
