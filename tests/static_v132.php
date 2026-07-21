<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.3.2', '>='), 'Application version must be V1.3.2 or newer.');

$plugin = json_decode((string) file_get_contents($root . '/plugins/PromaAccounting/plugin.json'), true);
$assert(version_compare((string) ($plugin['version'] ?? '0.0.0'), '1.2.3', '>='), 'Proma Accounting version must retain V1.2.3 compatibility.');
$assert(($plugin['requires_core'] ?? '') === '1.3.2', 'Proma Accounting must require core 1.3.2.');

$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$manager = (string) file_get_contents($root . '/core/PluginManager.php');
$build = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($layout, 'assetsForRoute($route)') !== false, 'Plugin assets are not route scoped in the application layout.');
$assert(strpos($manager, 'function assetsForRoute') !== false, 'Route-scoped plugin asset resolver is missing.');
$assert(strpos($layout, 'cdn.jsdelivr.net/npm/chart.js') === false, 'Remote Chart.js reference remains.');
$assert(strpos($layout, "asset_url('assets/vendor/chart.umd.min.js')") !== false, 'Local Chart.js is not loaded.');
$assert(is_file($root . '/assets/vendor/chart.umd.min.js'), 'Local Chart.js asset is missing.');
$assert(strpos($build, "'plugins/'") !== false, 'Core package does not explicitly exclude plugins.');
$assert(strpos($build, "'/plugins/PromaAccounting'") !== false && strpos($build, "\$pluginArchiveName = 'PromaAccounting'") !== false, 'Proma Accounting package declaration is missing.');
$assert(strpos($build, "\$pluginArchiveName") !== false && strpos($build, "\$pluginVersion") !== false, 'Canonical versioned plugin package naming is missing.');
$assert(strpos($build, "'assets/'") !== false && strpos($build, 'diff --name-only --diff-filter=ACMRT') !== false, 'Differential update asset inventory is missing.');

$migrations = glob($root . '/plugins/PromaAccounting/migrations/*.sql') ?: [];
$assert(count($migrations) === 3, 'Accounting migration inventory is incomplete.');
$assert(in_array('migrations/2026_07_18_accounting_request_integrity.sql', $plugin['migrations'] ?? [], true), 'Accounting request-integrity migration is not declared in the plugin manifest.');
$assert(is_file($root . '/plugins/PromaAccounting/src/Services/AccountingIdempotencyService.php'), 'Accounting idempotency service is missing.');

echo "STATIC_V132_OK\n";
