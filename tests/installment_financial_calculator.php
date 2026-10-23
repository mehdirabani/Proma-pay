<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/helpers/MoneyMath.php';
require_once $root . '/helpers/InstallmentFinancialCalculator.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$sumField = static function (array $rows, string $field): int {
    $sum = 0;
    foreach ($rows as $row) {
        $sum += (int) $row[$field];
    }
    return $sum;
};
$expectOverflow = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (OverflowException $error) {
        return;
    }
    throw new RuntimeException($message);
};

$expectInvalid = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        return;
    }
    throw new RuntimeException($message);
};

$flat = InstallmentFinancialCalculator::calculate([
    'cash_price' => '10000000',
    'down_payment' => '5000000',
    'months' => 6,
    'monthly_interest_rate' => '5',
    'interest_method' => 'flat',
]);
$assert($flat['rounding_policy'] === 'input_amount_half_up_to_toman; positive_interest_ceil_to_toman; final_installment_absorbs_remainder', 'Rounding policy is not part of the reproducible output.');
$assert($flat['financed_principal'] === 5000000, 'Down payment was not excluded from the financed principal.');
$assert($flat['total_interest'] === 1500000, 'Flat interest formula is incorrect.');
$assert($flat['total_installments'] === 6500000, 'Flat installment total is incorrect.');
$assert($flat['total_customer_payable'] === 11500000, 'Customer payable total is incorrect.');
$assert(count($flat['installment_schedule']) === 6, 'Flat schedule has the wrong number of installments.');
$assert($sumField($flat['installment_schedule'], 'principal_amount') === 5000000, 'Flat principal allocation does not reconcile.');
$assert($sumField($flat['installment_schedule'], 'interest_amount') === 1500000, 'Flat interest allocation does not reconcile.');
$assert($sumField($flat['installment_schedule'], 'amount') === $flat['total_installments'], 'Flat schedule does not equal the contract installment total.');
$assert($flat['installment_schedule'][5]['amount'] === 1083335, 'Final installment did not absorb the indivisible remainder.');
$assert($flat['calculation_snapshot'] === array_diff_key($flat, ['calculation_snapshot' => true]), 'Calculation snapshot differs from the returned quote values.');

$compound = InstallmentFinancialCalculator::calculate([
    'cash_price' => 5000000,
    'down_payment' => 0,
    'months' => 2,
    'monthly_interest_rate' => '10%',
    'interest_method' => 'compound',
]);
$assert($compound['total_interest'] === 1050000, 'Compound interest must accrue and round monthly.');
$assert($compound['total_installments'] === 6050000, 'Compound total installments are incorrect.');
$assert($sumField($compound['installment_schedule'], 'amount') === 6050000, 'Compound schedule does not reconcile.');

$reducing = InstallmentFinancialCalculator::calculate([
    'cash_price' => 6000000,
    'down_payment' => 0,
    'months' => 3,
    'monthly_interest_rate' => '10',
    'interest_method' => 'reducing_balance',
]);
$assert($reducing['calculation_variant'] === 'equal_principal', 'Reducing-balance calculation variant must be explicit in the snapshot.');
$assert($reducing['total_interest'] === 1200000, 'Reducing-balance interest must use the outstanding principal each period.');
$assert($reducing['installment_schedule'][0]['interest_amount'] === 600000, 'First reducing-balance interest is incorrect.');
$assert($reducing['installment_schedule'][1]['interest_amount'] === 400000, 'Second reducing-balance interest is incorrect.');
$assert($reducing['installment_schedule'][2]['interest_amount'] === 200000, 'Third reducing-balance interest is incorrect.');
$assert($reducing['installment_schedule'][0]['amount'] > $reducing['installment_schedule'][1]['amount'], 'Reducing-balance payments should decrease as interest declines.');
$assert($sumField($reducing['installment_schedule'], 'amount') === $reducing['total_installments'], 'Reducing-balance schedule does not reconcile.');
$assert($reducing['installment_schedule'][2]['remaining_principal'] === 0, 'Reducing-balance principal was not fully amortized.');

$zeroRate = InstallmentFinancialCalculator::calculate([
    'cash_price' => 10,
    'down_payment' => 0,
    'months' => 3,
    'monthly_interest_rate' => '0',
    'interest_method' => 'simple',
]);
$assert($zeroRate['interest_method'] === 'flat', 'Simple method alias was not normalized to flat.');
$assert($zeroRate['installment_schedule'][2]['amount'] === 4, 'Zero-rate final installment did not absorb the remainder.');
$assert($zeroRate['total_installments'] === 10, 'Zero-rate installment total changed.');

$repeat = InstallmentFinancialCalculator::calculate([
    'cash_price' => 10000000,
    'down_payment' => 5000000,
    'months' => 6,
    'monthly_interest_rate' => '5',
    'interest_method' => 'flat',
]);
$assert($repeat === $flat, 'Identical calculation inputs must return identical snapshots.');

$expectInvalid(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => -1, 'months' => 1, 'monthly_interest_rate' => '0', 'interest_method' => 'flat']);
}, 'Negative cash price was accepted.');
$expectInvalid(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => 100, 'down_payment' => 101, 'months' => 1, 'monthly_interest_rate' => '0', 'interest_method' => 'flat']);
}, 'Down payment above cash price was accepted.');
$expectInvalid(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => 100, 'months' => 1, 'monthly_interest_rate' => '100.0001', 'interest_method' => 'flat']);
}, 'Rate above the supported bound was accepted.');
$expectInvalid(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => 100, 'months' => 0, 'monthly_interest_rate' => '0', 'interest_method' => 'flat']);
}, 'Zero month term was accepted.');
$expectOverflow(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => '999999999999999999999999999999', 'months' => 1, 'monthly_interest_rate' => '0', 'interest_method' => 'flat']);
}, 'Saturated money input was accepted.');

$expectInvalid(static function (): void {
    InstallmentFinancialCalculator::calculate(['cash_price' => 100, 'months' => 1, 'monthly_interest_rate' => '0', 'interest_method' => 'unknown']);
}, 'Unknown interest method was accepted.');

fwrite(STDOUT, "INSTALLMENT_FINANCIAL_CALCULATOR_OK\n");