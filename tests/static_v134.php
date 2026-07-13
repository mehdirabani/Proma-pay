<?php

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';
if (version_compare((string) ($version['application'] ?? '0'), '1.3.4', '<')) {
    throw new RuntimeException('Core version is below 1.3.4.');
}
foreach ([
    'core/PaymentGatewayProviderInterface.php',
    'core/PaymentGatewayRegistry.php',
    'helpers/ZibalGatewayProvider.php',
    'plugins/PromaZarinpal/plugin.json',
    'docs/debug/PROMA_ZARINPAL_PLUGIN_AUDIT.md',
] as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Missing V1.3.4 file: ' . $file);
    }
}
$controller = file_get_contents($root . '/controllers/PaymentsController.php');
$view = file_get_contents($root . '/views/installments/index.php');
$groups = file_get_contents($root . '/helpers/PaymentGroupService.php');
$pluginSettings = file_get_contents($root . '/plugins/PromaZarinpal/src/Services/ZarinpalSettingsService.php');
$pluginManifest = file_get_contents($root . '/plugins/PromaZarinpal/plugin.json');
$build = file_get_contents($root . '/scripts/build_release.php');
if (strpos($controller, 'PaymentGatewayRegistry::boot()') === false || strpos($controller, "new Zarinpal") !== false) {
    throw new RuntimeException('Payment controller bypasses gateway registry.');
}
if (strpos($view, "payments/gateway") === false || strpos($view, 'proma-gateway-card') === false) {
    throw new RuntimeException('Customer gateway selection UI is incomplete.');
}
if (strpos($groups, "\$group['method']") === false || strpos($groups, 'allocationTrackId') === false) {
    throw new RuntimeException('Generic group allocation is incomplete.');
}
if (strpos($pluginSettings, "'sandbox_financial_effects' => '0'") === false
    || strpos($pluginManifest, 'plugin/zarinpal/sandbox/create') === false) {
    throw new RuntimeException('Sandbox safety controls are incomplete.');
}
if (strpos($build, "'PromaZarinpal'") === false || strpos($build, "\$pluginDir = \$dist . '/plugins'") === false) {
    throw new RuntimeException('PromaZarinpal release packaging is missing.');
}

echo "STATIC_V134_OK\n";
