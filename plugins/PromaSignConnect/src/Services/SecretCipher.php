<?php
namespace Proma\Plugins\SignConnect\Services;
final class SecretCipher
{
    public static function isReady(): bool
    {
        if (self::configuredMaterial() !== '') return true;
        $path = self::keyFile();
        return is_file($path) && filesize($path) >= 32;
    }
    public static function ensureMasterKey(): void
    {
        if (self::configuredMaterial() !== '') return;
        $path = self::keyFile();
        if (is_file($path) && filesize($path) >= 32) return;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('ساخت فضای امن نگهداری کلید افزونه انجام نشد.');
        }
        $key = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            if (is_file($path) && filesize($path) >= 32) return;
            throw new \RuntimeException('ایجاد کلید اصلی افزونه انجام نشد.');
        }
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('قفل فایل کلید ایجاد نشد.');
            if (fwrite($handle, $key) !== strlen($key)) throw new \RuntimeException('ذخیره کلید اصلی افزونه کامل نشد.');
            fflush($handle);
        } finally {
            fclose($handle);
            @chmod($path, 0600);
        }
    }

    private static function key(): string
    {
        $material = self::configuredMaterial();
        if ($material === '') {
            $path = self::keyFile();
            $material = is_file($path) ? trim((string) file_get_contents($path)) : '';
        }
        if (strlen($material) < 32) throw new \RuntimeException('کلید اصلی رمزگذاری افزونه آماده نیست؛ عملیات تعمیر یا بروزرسانی افزونه را اجرا کنید.');
        return hash('sha256', $material, true);
    }
    private static function configuredMaterial(): string
    {
        $material = trim((string) getenv('PROMA_APP_KEY'));
        if ($material === '' && defined('APP_KEY')) $material = trim((string) APP_KEY);
        if ($material === '' && function_exists('app_config')) {
            $config = app_config();
            foreach (['app_key','encryption_key','secret_key'] as $key) {
                if (!empty($config[$key])) { $material = trim((string) $config[$key]); break; }
            }
        }
        return strlen($material) >= 32 ? $material : '';
    }
    private static function keyFile(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'secure_uploads' . DIRECTORY_SEPARATOR . '.proma-sign-connect.key';
    }
    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, 'proma-sign-connect');
        if ($cipher === false) throw new \RuntimeException('secret_encryption_failed');
        return base64_encode($nonce . $tag . $cipher);
    }
    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) throw new \RuntimeException('invalid_encrypted_secret');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'proma-sign-connect');
        if ($plain === false) throw new \RuntimeException('secret_decryption_failed');
        return $plain;
    }
}
