<?php

declare(strict_types=1);

$plugin = dirname(__DIR__);
$root = dirname($plugin, 2);
require_once $root . '/helpers/functions.php';
require_once $plugin . '/src/Services/Money.php';
require_once $plugin . '/src/Services/CommissionCalculationService.php';

use Proma\Plugins\Accounting\Services\CommissionCalculationService;

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$baseRule = [
    'name' => 'Test',
    'commission_type' => 'percentage',
    'commission_value' => '2',
    'calculation_basis' => 'financed_amount',
    'minimum_enabled' => false,
    'maximum_enabled' => false,
    'rounding_method' => 'none',
    'rounding_unit' => 1000,
    'rule_source' => 'default',
];
$amounts = ['principal_amount' => 25000000, 'financed_amount' => 20000000, 'profit_amount' => 5000000, 'collected_amount' => 3000000];
$result = CommissionCalculationService::calculate($amounts, $baseRule);
$assert($result['raw_commission'] === 400000 && $result['final_commission'] === 400000, 'Raw percentage calculation failed.');

$minimum = CommissionCalculationService::calculate($amounts, array_merge($baseRule, ['commission_value' => '0.9', 'minimum_enabled' => true, 'minimum_amount' => 250000]));
$assert($minimum['raw_commission'] === 180000 && $minimum['minimum_adjustment'] === 70000 && $minimum['final_commission'] === 250000, 'Minimum commission order failed.');

$maximum = CommissionCalculationService::calculate($amounts, array_merge($baseRule, ['commission_value' => '6', 'maximum_enabled' => true, 'maximum_amount' => 800000]));
$assert($maximum['raw_commission'] === 1200000 && $maximum['maximum_adjustment'] === -400000 && $maximum['final_commission'] === 800000, 'Maximum commission order failed.');

$assert(CommissionCalculationService::round(403480, 'none', 1000) === 403480, 'No-rounding failed.');
$assert(CommissionCalculationService::round(403480, 'nearest', 1000) === 403000, 'Nearest rounding failed.');
$assert(CommissionCalculationService::round(403480, 'down', 1000) === 403000, 'Round-down failed.');
$assert(CommissionCalculationService::round(403480, 'up', 1000) === 404000, 'Round-up failed.');

$settingsView = (string) file_get_contents($plugin . '/views/settings.php');
$rulesView = (string) file_get_contents($plugin . '/views/rules.php');
$css = (string) file_get_contents($plugin . '/assets/css/accounting.css');
$js = (string) file_get_contents($plugin . '/assets/js/accounting.js');
$manifest = json_decode((string) file_get_contents($plugin . '/plugin.json'), true);
$assert(($manifest['version'] ?? '') === '1.1.0', 'Plugin version must be 1.1.0.');

foreach (['مقدار پیش‌فرض کمیسیون', 'مبنای پیش‌فرض محاسبه کمیسیون', 'اعمال حداقل کمیسیون', 'اعمال حداکثر کمیسیون', 'پیش‌نمایش محاسبه کمیسیون'] as $label) {
    $assert(strpos($settingsView, $label) !== false, 'Settings label missing: ' . $label);
}
$assert(strpos($settingsView . $rulesView, 'کمسیون') === false, 'Incorrect Persian commission spelling remains.');
$assert(strpos($settingsView, 'مقدار عمومی') === false && strpos($settingsView, 'مبنای عمومی') === false, 'Ambiguous legacy labels remain.');

foreach (['--accounting-page-padding: 24px', '--accounting-card-padding: 22px', '--accounting-control-height: 44px', '.proma-accounting-switch-row', '@media (max-width: 767.98px)', '[data-theme="dark"] .proma-accounting'] as $rule) {
    $assert(strpos($css, $rule) !== false, 'Accounting UI rule missing: ' . $rule);
}
$assert(!preg_match('/(^|\})\s*(input|select|textarea|label|button)\s*\{/m', $css), 'Unscoped global control selector found.');
$assert(strpos($js, 'data-accounting-dirty-form') !== false && strpos($js, 'data-accounting-ledger-form') !== false, 'Accounting interactions are incomplete.');

$routes = array_column($manifest['routes'] ?? [], 'path');
foreach (['plugin/accounting/settings/preview', 'plugin/accounting/setup', 'plugin/accounting/help'] as $route) {
    $assert(in_array($route, $routes, true), 'Required plugin route missing: ' . $route);
}
$assert(in_array('migrations/2026_07_13_accounting_v1_1.sql', $manifest['migrations'] ?? [], true), 'V1.1 migration is missing.');

echo "PROMA_ACCOUNTING_V110_OK\n";
