<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/core/PluginStatus.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.2.8', '>='), 'Application version must be V1.2.8 or newer.');
$assert(PluginStatus::normalize('uninstalled') === PluginStatus::REMOVED, 'Legacy plugin status normalization failed.');
$assert(PluginStatus::canInstall(PluginStatus::UPLOADED, true), 'Uploaded plugin must be installable.');
$assert(!PluginStatus::canInstall(PluginStatus::ACTIVE, true), 'Active plugin must not be installable.');

$requiredFiles = [
    'assets/css/components/forms.css',
    'assets/css/components/contract-print.css',
    'docs/plugins/PLUGIN_UI_DESIGN_SYSTEM.md',
    'scripts/build_release.php',
];
foreach ($requiredFiles as $file) {
    $assert(is_file($root . '/' . $file), 'Required V1.2.8 file missing: ' . $file);
}

$printView = (string) file_get_contents($root . '/views/contracts/print.php');
$assert(strpos($printView, 'proma-contract-letterhead') !== false, 'Professional contract letterhead is missing.');
$assert(strpos($printView, 'technical_path') === false, 'Contract view unexpectedly contains plugin technical path.');

$pluginView = (string) file_get_contents($root . '/views/plugins/index.php');
$assert(strpos($pluginView, 'plugins/rescan') !== false, 'Plugin rescan action is missing.');
$assert(strpos($pluginView, "PluginStatus::canInstall") !== false, 'Plugin install action is not centralized.');

$formsCss = (string) file_get_contents($root . '/assets/css/components/forms.css');
$assert(strpos($formsCss, '--proma-control-height: 44px') !== false, 'Shared control height is not defined.');
$assert(strpos($formsCss, 'textarea.proma-form-control') !== false, 'Textarea exception is missing.');

$printCss = (string) file_get_contents($root . '/assets/css/components/contract-print.css');
$assert(strpos($printCss, 'margin: 18mm 17mm') !== false, 'A4 safe print margins are missing.');
$assert(strpos($printCss, 'grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr)') !== false, 'Symmetric letterhead grid is missing.');

echo "STATIC_V128_OK\n";
