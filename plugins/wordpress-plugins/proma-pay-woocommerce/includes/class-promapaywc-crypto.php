<?php

declare(strict_types=1);

/** Encrypts merchant API tokens and policy snapshots using the site's secret salts. */
final class PromaPay_WC_Crypto
{
    private const VERSION = 1;

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '' || !function_exists('wp_salt') || !function_exists('openssl_encrypt')) {
            throw new RuntimeException('promapaywc_encryption_unavailable');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'promapaywc:v1',
            16
        );
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('promapaywc_encryption_failed');
        }

        return implode('.', [
            (string) self::VERSION,
            self::encode($iv),
            self::encode($tag),
            self::encode($ciphertext),
        ]);
    }

    public static function decrypt(string $encoded): string
    {
        if (!function_exists('wp_salt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('promapaywc_encryption_unavailable');
        }
        $parts = explode('.', $encoded);
        if (count($parts) !== 4 || $parts[0] !== (string) self::VERSION) {
            throw new UnexpectedValueException('promapaywc_ciphertext_invalid');
        }
        $iv = self::decode($parts[1]);
        $tag = self::decode($parts[2]);
        $ciphertext = self::decode($parts[3]);
        if (strlen($iv) !== 12 || strlen($tag) !== 16 || $ciphertext === '') {
            throw new UnexpectedValueException('promapaywc_ciphertext_invalid');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'promapaywc:v1'
        );
        if (!is_string($plaintext)) {
            throw new UnexpectedValueException('promapaywc_ciphertext_authentication_failed');
        }

        return $plaintext;
    }

    private static function key(): string
    {
        $salt = (string) wp_salt('auth');
        if ($salt === '' || $salt === 'put your unique phrase here') {
            throw new RuntimeException('promapaywc_site_secret_unconfigured');
        }
        return hash_hmac('sha256', 'promapaywc-token-and-policy-v1', $salt, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        if ($value === '' || !preg_match('/\A[A-Za-z0-9_-]+\z/D', $value)) {
            throw new UnexpectedValueException('promapaywc_ciphertext_invalid');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if (!is_string($decoded)) {
            throw new UnexpectedValueException('promapaywc_ciphertext_invalid');
        }
        return $decoded;
    }
}
