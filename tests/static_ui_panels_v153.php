<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.3', '>='), 'Core version must be V1.5.3 or newer.');
$installments = (string) file_get_contents($root . '/views/installments/index.php');
$overdue = (string) file_get_contents($root . '/views/overdue/index.php');
$history = (string) file_get_contents($root . '/views/portal/history.php');
$criteria = (string) file_get_contents($root . '/models/OverdueFilterCriteria.php');
$service = (string) file_get_contents($root . '/models/OverdueAggregationService.php');
$javascript = (string) file_get_contents($root . '/assets/js/app.js');
$panels = (string) file_get_contents($root . '/assets/css/components/panels.css');

foreach (['proma-status-tabs', 'proma-empty-state', 'render_pagination($pagination, $pageUrl)', 'data-filter-reset-page="1"', "'paid' => ['پرداخت‌شده'"] as $needle) {
    $assert(strpos($installments, $needle) !== false, 'Installment operational UI is missing: ' . $needle);
}
$assert(strpos($installments, 'for ($page = 1; $page <=') === false, 'Installment page must not render every pagination button.');
foreach (['OverdueFilterCriteria', 'proma-overdue-mobile-list', 'proma-action-menu', 'render_pagination($pagination, $pageUrl)'] as $needle) {
    $assert(strpos($overdue, $needle) !== false, 'Overdue UI is missing: ' . $needle);
}
foreach (['class OverdueFilterCriteria', 'public const SORTS', 'queryParameters', 'to_english_digits'] as $needle) {
    $assert(strpos($criteria, $needle) !== false, 'Overdue criteria safety is missing: ' . $needle);
}
foreach (['GROUP BY {$groupBy}', 'overdue_count', 'max_overdue_days', 'estimated_payable', 'hydrateFinancialState'] as $needle) {
    $assert(strpos($service, $needle) !== false, 'Overdue aggregation is missing: ' . $needle);
}
foreach (['proma-purchase-contract-list', 'proma-purchase-contract-card', 'پیشرفت پرداخت', 'مشاهده جزئیات'] as $needle) {
    $assert(strpos($history, $needle) !== false, 'Customer purchase history redesign is missing: ' . $needle);
}
foreach (['resetFilterPage', 'data-filter-reset-page', "pageField.value = '1'"] as $needle) {
    $assert(strpos($javascript, $needle) !== false, 'Filter page reset is missing: ' . $needle);
}
foreach (['.proma-operational-filter', '.proma-overdue-mobile-card', '.proma-purchase-contract-card', '.proma-empty-state'] as $needle) {
    $assert(strpos($panels, $needle) !== false, 'Shared operational panel CSS is missing: ' . $needle);
}

echo "STATIC_UI_PANELS_V153_OK\n";
