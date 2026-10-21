<?php

namespace Proma\Plugins\Repair\Support;

final class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private $keyPath;

    public function __construct($keyPath = null)
    {
        $this->keyPath = $keyPath ?: dirname(__DIR__, 4) . '/storage/private/proma-repair.key';
    }

    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipherText === false || strlen($tag) !== 16) {
            throw new \RuntimeException('رمزنگاری امن رمز دستگاه انجام نشد.');
        }
        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherText),
        ], JSON_UNESCAPED_SLASHES));
    }

    public function decrypt(string $encoded): string
    {
        $payload = json_decode((string) base64_decode($encoded, true), true);
        if (!is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) {
            throw new \RuntimeException('قالب رمز دستگاه معتبر نیست.');
        }
        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $data = base64_decode((string) ($payload['data'] ?? ''), true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $data === false) {
            throw new \RuntimeException('داده رمز دستگاه آسیب دیده است.');
        }
        $plainText = openssl_decrypt($data, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '');
        if ($plainText === false) {
            throw new \RuntimeException('بازخوانی رمز دستگاه انجام نشد.');
        }
        return $plainText;
    }

    private function key(): string
    {
        $env = trim((string) getenv('PROMA_REPAIR_SECRET_KEY'));
        if ($env !== '') {
            $decoded = base64_decode($env, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
            throw new \RuntimeException('کلید PROMA_REPAIR_SECRET_KEY باید Base64 و ۳۲ بایت باشد.');
        }
        if (is_file($this->keyPath)) {
            $key = base64_decode(trim((string) file_get_contents($this->keyPath)), true);
            if ($key !== false && strlen($key) === 32) {
                return $key;
            }
            throw new \RuntimeException('کلید رمزنگاری تعمیرات معتبر نیست.');
        }
        $directory = dirname($this->keyPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('پوشه امن رمزهای تعمیرات ساخته نشد.');
        }
        $key = random_bytes(32);
        $handle = @fopen($this->keyPath, 'x');
        if (!$handle) {
            if (is_file($this->keyPath)) {
                return $this->key();
            }
            throw new \RuntimeException('کلید رمزنگاری تعمیرات ایجاد نشد.');
        }
        try {
            if (fwrite($handle, base64_encode($key)) === false) {
                throw new \RuntimeException('کلید رمزنگاری تعمیرات ذخیره نشد.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($this->keyPath, 0600);
        return $key;
    }
}
