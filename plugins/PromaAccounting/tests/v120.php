<?php

declare(strict_types=1);

$plugin = dirname(__DIR__);
$root = dirname($plugin, 2);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$manifest = json_decode((string) file_get_contents($plugin . '/plugin.json'), true);
$assert(version_compare((string) ($manifest['version'] ?? '0.0.0'), '1.2.3', '>='), 'Plugin version must retain V1.2.3 compatibility.');
$assert((bool) preg_match('/^(?:>=)?1\\.3\\.2$/', trim((string) ($manifest['requires_core'] ?? ''))), 'Plugin must require route-scoped asset support from core 1.3.2 or newer.');
$routeOrder = array_map(static fn (array $route): string => (string) ($route['path'] ?? ''), $manifest['routes'] ?? []);
$ledgerPostIndex = array_search('plugin/accounting/ledger/post', $routeOrder, true);
$ledgerReverseIndex = array_search('plugin/accounting/ledger/reverse/{entryId}', $routeOrder, true);
$ledgerDynamicIndex = array_search('plugin/accounting/ledger/{userId}', $routeOrder, true);
$assert($ledgerPostIndex !== false && $ledgerDynamicIndex !== false && $ledgerPostIndex < $ledgerDynamicIndex, 'Specific ledger post route must be registered before dynamic ledger route.');
$assert($ledgerReverseIndex !== false && $ledgerDynamicIndex !== false && $ledgerReverseIndex < $ledgerDynamicIndex, 'Specific ledger reverse route must be registered before dynamic ledger route.');
$assert(in_array('plugin/accounting/rules/delete/{ruleId}', $routeOrder, true), 'Commission rule delete route is missing.');
foreach (['assets/css/accounting.css', 'assets/css/accounting-responsive.css', 'assets/js/accounting.js'] as $asset) {
    $assert(in_array($asset, $manifest['assets'] ?? [], true), 'Manifest asset missing: ' . $asset);
    $assert(is_file($plugin . '/' . $asset), 'Asset file missing: ' . $asset);
}

$css = (string) file_get_contents($plugin . '/assets/css/accounting.css');
$responsive = (string) file_get_contents($plugin . '/assets/css/accounting-responsive.css');
$js = (string) file_get_contents($plugin . '/assets/js/accounting.js');
$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$manager = (string) file_get_contents($root . '/core/PluginManager.php');

foreach (['#6a1b9a', '#4a148c', '#8e24aa', '--pa-radius-lg: 16px', '--pa-shadow:', '--pa-control-height: 44px', '.proma-accounting-dialog', '.proma-accounting-data-card', '[data-theme="dark"] .proma-accounting'] as $token) {
    $assert(stripos($css, $token) !== false, 'Design-system token or component missing: ' . $token);
}
foreach (['max-width: 1199.98px', 'max-width: 767.98px', 'max-width: 479.98px', 'prefers-reduced-motion'] as $breakpoint) {
    $assert(strpos($responsive, $breakpoint) !== false, 'Responsive coverage missing: ' . $breakpoint);
}
$assert(!preg_match('/(^|\})\s*(input|select|textarea|label|button|table|\.card|\.modal)\s*\{/m', $css . "\n" . $responsive), 'Unscoped global UI selector found.');
$assert(strpos($js, 'window.confirm') === false && strpos($js, 'alert(') === false, 'Browser alert or confirm remains in Accounting UI.');
foreach (['setupConfirmation', 'setupCharts', 'setupMoneyInputs', 'setupSubmitLoading', 'setupHelpSearch'] as $interaction) {
    $assert(strpos($js, $interaction) !== false, 'Accounting interaction missing: ' . $interaction);
}
$assert(strpos($layout, 'cdn.jsdelivr.net/npm/chart.js') === false, 'Remote Chart.js CDN remains.');
$assert(strpos($layout, "asset_url('assets/vendor/chart.umd.min.js')") !== false, 'Local Chart.js is not loaded.');
$assert(is_file($root . '/assets/vendor/chart.umd.min.js'), 'Local Chart.js file is missing.');
$assert(strpos($layout, 'assetsForRoute($route)') !== false && strpos($manager, 'function assetsForRoute') !== false, 'Route-scoped plugin asset loading is missing.');

$components = (string) file_get_contents($plugin . '/views/components/ui.php');
foreach (['pa_page_header', 'pa_user_cell', 'pa_status', 'pa_money_effect', 'pa_empty_state', 'pa_financial_dialog'] as $component) {
    $assert(strpos($components, 'function ' . $component) !== false, 'Reusable component missing: ' . $component);
}
foreach (['dashboard', 'accounts', 'ledger', 'sales', 'commissions', 'rules', 'settings', 'setup', 'help', 'backfill'] as $view) {
    $source = (string) file_get_contents($plugin . '/views/' . $view . '.php');
    $assert(strpos($source, 'class="proma-accounting ') !== false, 'Scoped Accounting root missing in ' . $view);
}

$migrations = glob($plugin . '/migrations/*.sql') ?: [];
$manifest = json_decode((string) file_get_contents($plugin . '/plugin.json'), true);
$declaredMigrations = array_values($manifest['migrations'] ?? []);
$filesystemMigrations = array_map(static function (string $path): string {
    return 'migrations/' . basename($path);
}, $migrations);
sort($declaredMigrations);
sort($filesystemMigrations);
$assert($declaredMigrations === $filesystemMigrations, 'Migration manifest and filesystem inventory differ.');
$assert(in_array('migrations/2026_07_18_accounting_request_integrity.sql', $declaredMigrations, true), 'Request-integrity migration is missing.');
$assert(version_compare((string) ($manifest['version'] ?? '0.0.0'), '1.2.3', '>='), 'Accounting request-integrity changes require plugin version 1.2.3 or newer.');
$integrityMigration = (string) file_get_contents($plugin . '/migrations/2026_07_18_accounting_request_integrity.sql');
$assert(strpos($integrityMigration, 'accounting_requests') !== false, 'Accounting request idempotency table is missing.');
$ledger = (string) file_get_contents($plugin . '/src/Services/LedgerService.php');
$controller = (string) file_get_contents($plugin . '/src/Controllers/AccountingController.php');
$idempotency = (string) file_get_contents($plugin . '/src/Services/AccountingIdempotencyService.php');
$assert(strpos($ledger, 'AccountingIdempotencyService::begin') !== false, 'Ledger request idempotency coordination is missing.');
$assert(strpos($controller, 'accounting_request_uuid') !== false, 'Financial form request UUID is not required.');
$assert(strpos($controller, 'function ledgerPostFallback') !== false, 'Ledger POST fallback route handler is missing.');
$assert(strpos($controller, 'function deleteRule') !== false, 'Commission rule delete handler is missing.');
$assert(strpos($controller, 'commission_rule_updated') !== false, 'Commission rule update audit is missing.');
$assert(strpos($idempotency, 'ON DUPLICATE KEY UPDATE request_uuid = request_uuid') !== false, 'Concurrent accounting requests are not atomically coordinated.');

echo "PROMA_ACCOUNTING_V120_OK\n";
