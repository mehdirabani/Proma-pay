<?php

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/plugin.json'), true);
if (!is_array($manifest) || ($manifest['id'] ?? '') !== 'proma-zarinpal' || ($manifest['version'] ?? '') !== '1.0.0') {
    throw new RuntimeException('Plugin manifest mismatch.');
}
foreach ([
    'src/ZarinpalServiceProvider.php',
    'src/Services/ZarinpalClient.php',
    'src/Services/ZarinpalGateway.php',
    'src/Services/ZarinpalRequestService.php',
    'src/Services/ZarinpalVerificationService.php',
    'src/Services/ZarinpalCallbackService.php',
    'src/Services/ZarinpalTransactionService.php',
    'migrations/2026_07_13_zarinpal_gateway.sql',
    'views/settings/index.php',
    'views/transactions/index.php',
    'views/callback/result.php',
] as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Missing plugin file: ' . $file);
    }
}

echo "PROMA_ZARINPAL_STATIC_OK\n";
