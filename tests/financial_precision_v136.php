<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/helpers/MoneyMath.php';
require_once $root . '/helpers/FinanceHelper.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(MoneyMath::amount('۱٬۲۳۴٬۵۶۷.۵') === 1234568, 'Persian money normalization must use exact integer Tomans.');
$assert(MoneyMath::rateUnits('2.5%') === 25000, 'Percentage rate must keep four decimal places.');
$assert(MoneyMath::rateLabel(25000) === '2.5', 'Percentage rate label is not canonical.');
$assert(MoneyMath::rateForDays(1000000, 100000, 30) === 100000, 'Thirty-day late penalty is incorrect.');
$assert(MoneyMath::applyMonthlyRate(1000000, 25000) === 1025000, 'Compound monthly rate is incorrect.');
$assert(FinanceHelper::installmentAmount(1000000, 2, '5', 'simple') === 600000, 'Simple installment rounding is incorrect.');
$assert(FinanceHelper::installmentAmount(1000000, 2, '5', 'compound') === 600000, 'Compound installment rounding is incorrect.');

$settings = [
    'monthly_penalty_rate' => '10',
    'legal_monthly_penalty_rate' => '20',
    'late_penalty_grace_days' => '0',
    'monthly_reward_rate' => '0',
];
$installment = [
    'base_amount' => 1000000,
    'paid_amount' => 0,
    'due_date' => '2026-07-01',
    'status' => 'overdue',
    'contract_status' => 'active',
];
$normal = FinanceHelper::preview($installment, [], $settings, '2026-07-31');
$assert($normal['penalty_mode'] === 'normal' && $normal['penalty'] === 100000, 'Normal late penalty is incorrect.');
$installment['legal_status'] = 'filed';
$installment['legal_started_at'] = '2026-07-01';
$legal = FinanceHelper::preview($installment, [], $settings, '2026-07-31');
$assert($legal['penalty_mode'] === 'legal' && $legal['penalty'] === 200000, 'Legal late penalty is incorrect.');

echo "FINANCIAL_PRECISION_V136_OK\n";
