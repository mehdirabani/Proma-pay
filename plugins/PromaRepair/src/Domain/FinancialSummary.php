<?php

namespace Proma\Plugins\Repair\Domain;

final class FinancialSummary
{
    public static function calculate(array $charges, array $payments, array $expenses): array
    {
        $revenue = self::sum($charges);
        $received = self::sum($payments);
        $cost = self::sum($expenses);
        return [
            'total_revenue' => $revenue,
            'total_received' => $received,
            'customer_balance' => max(0, $revenue - $received),
            'overpayment' => max(0, $received - $revenue),
            'total_cost' => $cost,
            'gross_profit' => $revenue - $cost,
            'net_profit' => $revenue - $cost,
        ];
    }

    private static function sum(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $amount = RepairMoney::nonNegativeStored($row['amount'] ?? 0);
            if ($amount > PHP_INT_MAX - $sum) throw new \OverflowException('Repair financial total exceeds the PHP integer range.');
            $sum += $amount;
        }
        return $sum;
    }
}
