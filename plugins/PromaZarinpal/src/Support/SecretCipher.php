<?php

namespace Proma\Plugins\Zarinpal\Support;

class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private $keyPath;

    public function __construct($keyPath = null)
    {
        $this->keyPath = $keyPath ?: dirname(__DIR__, 4) . '/storage/private/proma-zarinpal.key';
    }

    public function encrypt($plainText)
    {
        $plainText = (string) $plainText;
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipherText === false) {
            throw new \RuntimeException('رمزنگاری تنظیمات محرمانه زرین‌پال انجام نشد.');
        }
        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherText),
        ], JSON_UNESCAPED_SLASHES));
    }

    public function decrypt($encoded)
    {
        $payload = json_decode((string) base64_decode((string) $encoded, true), true);
        if (!is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) {
            throw new \RuntimeException('قالب تنظیمات محرمانه زرین‌پال معتبر نیست.');
        }
        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $cipherText = base64_decode((string) ($payload['data'] ?? ''), true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $cipherText === false) {
            throw new \RuntimeException('داده محرمانه زرین‌پال آسیب دیده است.');
        }
        $plainText = openssl_decrypt($cipherText, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plainText === false) {
            throw new \RuntimeException('بازخوانی تنظیمات محرمانه زرین‌پال انجام نشد.');
        }
        return $plainText;
    }

    private function key()
    {
        $environmentKey = trim((string) getenv('PROMA_ZARINPAL_SECRET_KEY'));
        if ($environmentKey !== '') {
            $decoded = base64_decode($environmentKey, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        if (is_file($this->keyPath)) {
            $key = base64_decode(trim((string) file_get_contents($this->keyPath)), true);
            if ($key !== false && strlen($key) === 32) {
                return $key;
            }
            throw new \RuntimeException('کلید رمزنگاری زرین‌پال معتبر نیست.');
        }
        $directory = dirname($this->keyPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('پوشه امن تنظیمات زرین‌پال قابل ساخت نیست.');
        }
        $key = random_bytes(32);
        if (file_put_contents($this->keyPath, base64_encode($key), LOCK_EX) === false) {
            throw new \RuntimeException('کلید رمزنگاری زرین‌پال قابل ذخیره نیست.');
        }
        @chmod($this->keyPath, 0600);
        return $key;
    }
}
