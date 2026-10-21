<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require $root . '/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$breakdown = ContractFinancialSummaryService::buildOverdueBreakdown(
    ['principal_total' => 10000000, 'normal_penalty_total' => 1000000, 'legal_penalty_total' => 250000, 'selected_count' => 3],
    ['outstanding_chargeable_legal_costs' => 2000000]
);
$assert($breakdown['overdue_installments_total'] === 10000000, 'Overdue principal bucket changed.');
$assert($breakdown['outstanding_normal_late_fees'] === 1000000, 'Normal penalty bucket changed.');
$assert($breakdown['outstanding_legal_late_fees'] === 250000, 'Legal penalty bucket changed.');
$assert($breakdown['outstanding_legal_costs'] === 2000000, 'Legal cost bucket changed.');
$assert($breakdown['total_overdue_debt'] === 13250000, 'Overdue total does not equal its four independent buckets.');
$assert($breakdown['overdue_installment_count'] === 3, 'Overdue installment count was lost.');

$empty = ContractFinancialSummaryService::buildOverdueBreakdown([], []);
$assert($empty['total_overdue_debt'] === 0, 'An empty contract must have no overdue debt.');
echo "FINANCIAL_V501_OK\n";
