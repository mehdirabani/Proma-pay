<?php

require_once __DIR__ . '/../src/Domain/Money.php';
require_once __DIR__ . '/../src/Domain/ProfitCalculator.php';
require_once __DIR__ . '/../src/Services/ProductService.php';

use Proma\Plugins\InventoryPartners\Domain\Money;
use Proma\Plugins\InventoryPartners\Domain\ProfitCalculator;
use Proma\Plugins\InventoryPartners\Services\ProductService;

function pipSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pipThrows(callable $callback, $label)
{
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

echo "14 domain checks passed.\n";
