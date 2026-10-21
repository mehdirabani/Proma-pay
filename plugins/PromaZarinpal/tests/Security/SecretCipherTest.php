<?php

require_once dirname(__DIR__, 2) . '/src/Support/SecretCipher.php';

use Proma\Plugins\Zarinpal\Support\SecretCipher;

$keyPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proma-zarinpal-test-' . bin2hex(random_bytes(5)) . '.key';
$cipher = new SecretCipher($keyPath);
$merchant = '123e4567-e89b-42d3-a456-426614174000';
$encrypted = $cipher->encrypt($merchant);
if (strpos($encrypted, $merchant) !== false || $cipher->decrypt($encrypted) !== $merchant) {
    throw new RuntimeException('Secret encryption failed.');
}
$tampered = substr($encrypted, 0, -2) . 'xx';
try {
    $cipher->decrypt($tampered);
    throw new RuntimeException('Tampered ciphertext was accepted.');
} catch (RuntimeException $expected) {
}
@unlink($keyPath);

echo "ZARINPAL_SECRET_CIPHER_OK\n";
