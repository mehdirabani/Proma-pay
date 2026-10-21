<?php

$root = dirname(__DIR__, 2);
$client = file_get_contents($root . '/src/Services/ZarinpalClient.php');
$callback = file_get_contents($root . '/src/Services/ZarinpalCallbackService.php');
$verification = file_get_contents($root . '/src/Services/ZarinpalVerificationService.php');
$transaction = file_get_contents($root . '/src/Services/ZarinpalTransactionService.php');
$settings = file_get_contents($root . '/src/Services/ZarinpalSettingsService.php');
$repository = file_get_contents($root . '/src/Repositories/ZarinpalTransactionRepository.php');
$controller = file_get_contents($root . '/src/Controllers/TransactionsController.php');
$migration = file_get_contents($root . '/migrations/2026_07_13_zarinpal_gateway.sql');

foreach (['CURLOPT_SSL_VERIFYPEER => true', 'CURLOPT_SSL_VERIFYHOST => 2', 'CURLOPT_FOLLOWLOCATION => false'] as $needle) {
    if (strpos($client, $needle) === false) {
        throw new RuntimeException('Missing transport protection: ' . $needle);
    }
}
if (strpos($callback, "['route', 'Authority', 'Status']") === false || strpos($callback, "['amount'") !== false) {
    throw new RuntimeException('Callback input allowlist is incomplete.');
}
if (strpos($verification, "'amount' => (int) \$transaction['gateway_amount']") === false) {
    throw new RuntimeException('Verification does not use stored amount.');
}
if (strpos($transaction, "[100, 101]") === false || strpos($migration, 'uq_proma_zarinpal_environment_authority') === false) {
    throw new RuntimeException('Idempotency controls are incomplete.');
}
if (strpos($client, 'validAuthorityForEnvironment') === false
    || strpos($settings, "'sandbox_financial_effects' => '0'") === false
    || strpos($transaction, "'status' => 'sandbox_verified'") === false) {
    throw new RuntimeException('Production/sandbox isolation controls are incomplete.');
}
if (strpos($repository, "'manual_review'") === false || strpos($controller, "'/^[=+\\-@]/u'") === false) {
    throw new RuntimeException('Manual review or CSV injection controls are incomplete.');
}
if (stripos($migration, 'card_pan ') !== false || stripos($migration, 'merchant_id ') !== false) {
    throw new RuntimeException('Sensitive raw field found in schema.');
}

echo "ZARINPAL_SECURITY_STATIC_OK\n";
