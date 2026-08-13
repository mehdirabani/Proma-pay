<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.2', '>='), 'Core version must retain V1.5.2 panel capabilities.');

$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$history = (string) file_get_contents($root . '/views/portal/history.php');
$panels = (string) file_get_contents($root . '/assets/css/components/panels.css');
$forms = (string) file_get_contents($root . '/assets/css/components/forms.css');

$assert(strpos($layout, "assets/css/components/panels.css") !== false, 'Shared panel stylesheet must load in the application layout.');
foreach (['.proma-page-content .card', '.proma-history-kpis', '.proma-history-card', 'height: auto !important', '@media (max-width: 767.98px)'] as $needle) {
    $assert(strpos($panels, $needle) !== false, 'Panel system is missing: ' . $needle);
}
foreach (['proma-customer-history', 'proma-history-kpis', 'proma-purchase-contract-list', 'proma-purchase-contract-card', 'proma-payment-progress'] as $needle) {
    $assert(strpos($history, $needle) !== false, 'Customer history composition is missing: ' . $needle);
}
$assert(strpos($forms, 'td.actions {') !== false, 'Table action cells must preserve native table layout.');
$assert(strpos($forms, 'table .actions {') === false, 'Table action cells must not become flex containers.');

$releaseBuilder = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($releaseBuilder, "'output/'") !== false, 'QA screenshots and other output artifacts must be excluded from release archives.');

echo "STATIC_UI_PANELS_V152_OK\n";
