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
$assert(($plugin['version'] ?? '') === '1.2.0', 'Proma Accounting version must be 1.2.0.');
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
$assert(strpos($build, "PromaAccounting-' . \$pluginVersion") !== false, 'Canonical plugin package name is missing.');
$assert(strpos($build, "'assets/vendor/chart.umd.min.js'") !== false, 'Update package omits local Chart.js.');

$migrations = glob($root . '/plugins/PromaAccounting/migrations/*.sql') ?: [];
$assert(count($migrations) === 2, 'UI release must not introduce accounting migrations.');

echo "STATIC_V132_OK\n";
