<?php

namespace Proma\Plugins\PartnershipLedger\Domain;

final class ProfitCalculator
{
    public static function landedCost($purchaseCost, $allocatedExpenses)
    {
        return Money::add(Money::integer($purchaseCost), Money::integer($allocatedExpenses));
    }

    public static function netProfit($netSalesRevenue, $costOfGoodsSold, $allocatedSellingExpenses)
    {
        $revenue = Money::integer($netSalesRevenue);
        $cost = Money::integer($costOfGoodsSold);
        $expenses = Money::integer($allocatedSellingExpenses);
        if ($revenue >= $cost) {
            return ($revenue - $cost) - $expenses;
        }
        $loss = $cost - $revenue;
        if ($expenses > PHP_INT_MAX - $loss) {
            throw new \OverflowException('زیان از محدوده محاسبات صحیح بیشتر است.');
        }
        return -($loss + $expenses);
    }

    public static function distributeProfit($netProfit, array $shares)
    {
        $netProfit = (int) $netProfit;
        if ($netProfit < 0) {
            $lossShares = Money::allocate(abs($netProfit), $shares);
            foreach ($lossShares as &$loss) {
                $loss = -$loss;
            }
            unset($loss);
            return $lossShares;
        }
        return Money::allocate($netProfit, $shares);
    }
}
