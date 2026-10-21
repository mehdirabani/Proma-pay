<?php

require_once __DIR__ . '/../src/Domain/Money.php';
require_once __DIR__ . '/../src/Domain/ProfitCalculator.php';
require_once __DIR__ . '/../src/Domain/InstallmentPricingCalculator.php';
require_once __DIR__ . '/../src/Services/ProductService.php';
require_once __DIR__ . '/../src/Services/FinancialPolicyService.php';

use Proma\Plugins\PartnershipLedger\Domain\Money;
use Proma\Plugins\PartnershipLedger\Domain\ProfitCalculator;
use Proma\Plugins\PartnershipLedger\Domain\InstallmentPricingCalculator;
use Proma\Plugins\PartnershipLedger\Services\FinancialPolicyService;
use Proma\Plugins\PartnershipLedger\Services\ProductService;

$pipChecks = 0;

function pipSame($expected, $actual, $label)
{
    global $pipChecks;
    $pipChecks++;
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pipThrows(callable $callback, $label)
{
    global $pipChecks;
    $pipChecks++;
    try {
        $callback();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($label . ': expected an exception.');
}

$shares = [
    ['partner_id' => 1, 'weight_bp' => 6000],
    ['partner_id' => 2, 'weight_bp' => 4000],
];

pipSame(300000000, Money::fromToman('۳۰٬۰۰۰٬۰۰۰'), 'Persian digits and Toman conversion');
pipSame(31, Money::fromToman('3.1'), 'One Rial fractional Toman');
pipSame('۳,۱۰۰,۰۰۰.۱ تومان', Money::formatToman(31000001), 'Rial display');
pipSame([1 => 60, 2 => 40], Money::allocate(100, $shares), 'Weighted allocation');
pipSame(4000, Money::basisPoints('۴۰'), 'Persian partner share percentage');
pipSame([1 => 400000, 2 => 600000], Money::allocate(1000000, [
    ['partner_id' => 1, 'weight_bp' => 4000],
    ['partner_id' => 2, 'weight_bp' => 6000],
]), '40/60 installment allocation');
pipSame([2 => 0, 1 => 1], Money::allocate(1, [
    ['partner_id' => 2, 'weight_bp' => 5000],
    ['partner_id' => 1, 'weight_bp' => 5000],
]), 'Largest remainder partner-ID tie-break');
pipSame([1 => 20, 2 => 80], Money::allocateCapped(100, [
    ['partner_id' => 1, 'weight_bp' => 6000, 'cap_rial' => 20],
    ['partner_id' => 2, 'weight_bp' => 4000, 'cap_rial' => 80],
]), 'Capped allocation');
pipSame([1 => -30, 2 => -20], ProfitCalculator::distributeProfit(-50, $shares), 'Loss distribution');
pipSame(50000000, ProfitCalculator::netProfit(360000000, 310000000, 0), 'Net profit in integer Rials');
pipThrows(static function () { Money::fromToman('1.01'); }, 'Sub-Rial precision rejection');
pipThrows(static function () { Money::allocateCapped(101, [
    ['partner_id' => 1, 'weight_bp' => 10000, 'cap_rial' => 100],
]); }, 'Capped allocation cannot exceed capacity');
pipSame('{type}_{brand}_{model}_{color}_{storage}_{ram}', ProductService::validateSkuPattern('{type}_{brand}_{model}_{color}_{storage}_{ram}'), 'SKU template validation');
pipThrows(static function () { ProductService::validateSkuPattern('{type}_{unknown}'); }, 'Unknown SKU template field rejection');

$simpleQuote = InstallmentPricingCalculator::quote(10000, 2000, 1000, 2, 'simple', 'full_base_price');
pipSame(2000, $simpleQuote['financing_premium_rial'], 'Simple interest on full base');
pipSame(12000, $simpleQuote['final_sale_price_rial'], 'Final price after simple interest');
pipSame([5000, 5000], $simpleQuote['installments_rial'], 'Installments sum after down payment');
$fundedQuote = InstallmentPricingCalculator::quote(10000, 2000, 1000, 2, 'simple', 'funded_amount');
pipSame(1600, $fundedQuote['financing_premium_rial'], 'Simple interest on funded amount');
$compoundQuote = InstallmentPricingCalculator::quote(10000, 0, 1000, 2, 'compound', 'full_base_price');
pipSame(2100, $compoundQuote['financing_premium_rial'], 'Compound interest');
$remainderQuote = InstallmentPricingCalculator::quote(10001, 0, 0, 3, 'simple', 'full_base_price');
pipSame(10001, array_sum($remainderQuote['installments_rial']), 'Installment remainder is conserved');
pipThrows(static function () { InstallmentPricingCalculator::quote(10000, 0, 0, 121, 'simple', 'full_base_price'); }, 'Installment term upper bound');
$policy = FinancialPolicyService::validatePolicyInput([
    'policy_name' => 'test-policy', 'effective_on' => '2026-10-21', 'monthly_rate_pct' => '2.5', 'months' => '6',
    'method' => 'simple', 'basis' => 'full_base_price', 'settlement_policy' => 'collection_based',
    'base_profit_shares' => [1 => '60', 2 => '40'], 'financing_profit_shares' => [1 => '0', 2 => '100'],
    'reinvestment_pct' => [1 => '25', 2 => '40'],
], [['id' => 1, 'full_name' => 'Partner A'], ['id' => 2, 'full_name' => 'Partner B']]);
pipSame(250, $policy['monthly_rate_bp'], 'Policy rate stored in basis points');
pipSame([1 => 6000, 2 => 4000], $policy['base_profit_shares'], 'Independent base profit shares');
pipSame([1 => 0, 2 => 10000], $policy['financing_profit_shares'], 'Independent financing profit shares');
pipThrows(static function () {
    FinancialPolicyService::validatePolicyInput([
        'policy_name' => 'bad-policy', 'effective_on' => '2026-10-21', 'monthly_rate_pct' => '2.5', 'months' => '6',
        'method' => 'simple', 'basis' => 'full_base_price', 'settlement_policy' => 'instant',
        'base_profit_shares' => [1 => '60', 2 => '30'], 'financing_profit_shares' => [1 => '0', 2 => '100'],
    ], [['id' => 1, 'full_name' => 'Partner A'], ['id' => 2, 'full_name' => 'Partner B']]);
}, 'Policy shares must total 100 percent');

echo $pipChecks . " domain checks passed.\n";
