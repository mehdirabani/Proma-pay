<?php
$root = dirname(__DIR__);
$m = json_decode(file_get_contents($root . '/plugin.json'), true);
if (version_compare((string)($m['version'] ?? ''), '1.2.10', '<')) { fwrite(STDERR, "version mismatch\n"); exit(1); }
$controller = file_get_contents($root . '/src/Controllers/AccountingController.php');
$service = file_get_contents($root . '/src/Services/AnalyticsService.php');
$view = file_get_contents($root . '/views/ledger.php');
$js = file_get_contents($root . '/assets/js/accounting.js');
foreach ([[$controller,'monthlyChart'],[$service,'monthlyChart'],[$view,'data-accounting-finance-chart'],[$js,'setupFinanceCharts']] as $pair) if (strpos($pair[0], $pair[1]) === false) { fwrite(STDERR, "missing {$pair[1]}\n"); exit(1); }
echo "PROMA_ACCOUNTING_V1210_OK\n";
