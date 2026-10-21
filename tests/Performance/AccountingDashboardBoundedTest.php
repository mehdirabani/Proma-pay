<?php

$root = dirname(__DIR__, 2);
$repository = file_get_contents($root . '/plugins/PromaAccounting/src/Services/AccountingRepository.php');
$controller = file_get_contents($root . '/plugins/PromaAccounting/src/Controllers/AccountingController.php');
$migration = file_get_contents($root . '/plugins/PromaAccounting/migrations/2026_07_21_accounting_dashboard_performance.sql');
$failures = [];
$assert = static function ($condition, $message) use (&$failures) { if (!$condition) $failures[] = $message; };
$dashboardMethod = substr($repository, strpos($repository, 'public static function dashboard()'), strpos($repository, 'public static function dashboardSeries()') - strpos($repository, 'public static function dashboard()'));
$assert(substr_count($dashboardMethod, '\\Model::fetch(') === 1, 'Dashboard summary must execute one aggregate statement.');
$assert(strpos($dashboardMethod, 'FROM accounting_ledger_entries)') === false, 'Dashboard summary still scans the complete ledger.');
$assert(strpos($dashboardMethod, "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") !== false, 'Monthly ledger aggregation is not date bounded.');
$assert(strpos($controller, "AccountingRepository::accounts('', 1, 8)") === false, 'Dashboard still loads the expensive account listing preview.');
$assert(strpos($migration, 'idx_accounting_ledger_created_direction_type') !== false, 'Dashboard date index migration is missing.');
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PASSED AccountingDashboardBoundedTest\n";
