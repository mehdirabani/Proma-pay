<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/plugin.json'), true);
$assert = static function ($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$assert(version_compare((string) ($manifest['version'] ?? ''), '1.2.7', '>='), 'Manifest must retain 1.2.7 features.');
$assert(in_array('migrations/2026_07_26_accounting_analytics_salary.sql', $manifest['migrations'] ?? [], true), 'Analytics/salary migration missing.');
$paths = array_column($manifest['routes'] ?? [], 'path');
foreach (['plugin/accounting/my-finance', 'plugin/accounting/analytics/users', 'plugin/accounting/analytics/user/{userId}/summary'] as $route) {
    $assert(in_array($route, $paths, true), 'Required route missing: ' . $route);
}
$assert(strpos((string) file_get_contents($root . '/src/Services/AnalyticsService.php'), 'net_result') !== false, 'Analytics net result is missing.');
$assert(strpos((string) file_get_contents($root . '/assets/js/accounting.js'), 'data-money-input') !== false, 'Money input formatter is missing.');
$analyticsView = (string) file_get_contents($root . '/views/analytics.php');
$assert(strpos($analyticsView, 'گزارش درآمد، هزینه و خالص عملکرد مالی') !== false, 'Approved financial terminology is missing.');
echo "PROMA_ACCOUNTING_V127_OK\n";
