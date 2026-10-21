<?php

class ZibalClient
{
    protected $merchant;
    protected $testMode = false;

    public function __construct($merchant, $testMode = false)
    {
        $this->merchant = preg_replace('/\s+/', '', trim((string) $merchant));
        $this->testMode = (bool) $testMode;
    }

    public function request($amountToman, $callbackUrl, $description)
    {
        $merchant = $this->effectiveMerchant();
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'مرچنت درگاه پرداخت تنظیم نشده است.'];
        }
        $amountToman = normalize_money($amountToman);
        if ($amountToman <= 0 || $amountToman > min(intdiv(PHP_INT_MAX, 10), 9999999999999999)) {
            return ['ok' => false, 'message' => 'مبلغ پرداخت برای درگاه معتبر نیست.'];
        }
        $payload = [
            'merchant' => $merchant,
            'amount' => $amountToman * 10,
            'callbackUrl' => $callbackUrl,
            'description' => $description,
        ];
        $result = $this->postJson('https://gateway.zibal.ir/v1/request', $payload);
        if (!$result['ok']) {
            return $result;
        }
        $body = $result['body'];
        if ((int) ($body['result'] ?? 0) !== 100) {
            $code = (int) ($body['result'] ?? 0);
            return [
                'ok' => false,
                'message' => self::resultMessage($code, $body['message'] ?? ''),
                'gateway_code' => $code,
            ];
        }
        if (!isset($body['trackId']) || self::positiveIntegerString($body['trackId']) === null) {
            return ['ok' => false, 'message' => 'شناسه پیگیری معتبر از درگاه دریافت نشد.'];
        }
        return ['ok' => true, 'track_id' => $body['trackId'], 'start_url' => 'https://gateway.zibal.ir/start/' . $body['trackId']];
    }

    public function verify($trackId)
    {
        $merchant = $this->effectiveMerchant();
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'مرچنت درگاه پرداخت تنظیم نشده است.'];
        }
        $trackId = self::positiveIntegerString($trackId);
        if ($trackId === null) {
            return ['ok' => false, 'message' => 'شناسه پیگیری درگاه معتبر نیست.'];
        }
        $result = $this->postJson('https://gateway.zibal.ir/v1/verify', [
            'merchant' => $merchant,
            'trackId' => (int) $trackId,
        ]);
        if (!$result['ok']) {
            return $result;
        }
        $body = $result['body'];
        $code = (int) ($body['result'] ?? 0);
        $amountToman = $code === 100 ? self::verifiedAmountToman($body['amount'] ?? null) : null;
        if ($code === 100 && $amountToman === null) {
            return [
                'ok' => false,
                'gateway_verified' => true,
                'message' => 'مبلغ تأییدشدهٔ درگاه معتبر نیست و برای بررسی نیاز به پیگیری دارد.',
                'ref_id' => $body['refNumber'] ?? null,
                'gateway_code' => $code,
            ];
        }
        if ($code === 100 && trim((string) ($body['refNumber'] ?? '')) === '') {
            return ['ok' => false, 'gateway_verified' => true, 'amount_toman' => $amountToman,
                'message' => 'شناسه مرجع پرداخت از درگاه دریافت نشد و به بررسی مالی نیاز دارد.', 'gateway_code' => $code];
        }
        return [
            'ok' => $code === 100,
            'message' => $code === 100 ? 'پرداخت تأیید شد.' : self::resultMessage($code, $body['message'] ?? ''),
            'ref_id' => $body['refNumber'] ?? null,
            'amount_toman' => $amountToman,
            'gateway_code' => $code,
        ];
    }

    private static function verifiedAmountToman($amountRial)
    {
        $amountRial = to_english_digits((string) $amountRial);
        if (!preg_match('/^[0-9]+$/', $amountRial)) return null;
        $digits = ltrim($amountRial, '0');
        if ($digits === '' || strlen($digits) > strlen((string) PHP_INT_MAX)
            || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            return null;
        }
        $rials = (int) $digits;
        return $rials % 10 === 0 ? intdiv($rials, 10) : null;
    }

    private static function positiveIntegerString($value)
    {
        $digits = ltrim(to_english_digits((string) $value), '0');
        if ($digits === '' || !preg_match('/^[0-9]+$/', $digits)
            || strlen($digits) > strlen((string) PHP_INT_MAX)
            || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            return null;
        }
        return $digits;
    }

    protected function effectiveMerchant()
    {
        return $this->testMode ? 'zibal' : $this->merchant;
    }

    protected function postJson($url, array $payload)
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'افزونه cURL روی PHP فعال نیست و اتصال به زیبال ممکن نیست.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $startedAt = microtime(true);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if (class_exists('RequestTelemetry', false)) {
            RequestTelemetry::recordExternal('zibal', (microtime(true) - $startedAt) * 1000, $status, $response !== false && $status >= 200 && $status < 300);
        }
        curl_close($ch);
        if ($response === false || $status >= 400) {
            $message = 'ارتباط با درگاه پرداخت برقرار نشد.';
            if ($curlError !== '') {
                $message .= ' خطای اتصال: ' . $curlError;
            } elseif ($status >= 400) {
                $message .= ' کد HTTP: ' . $status;
            }
            return ['ok' => false, 'message' => $message];
        }
        $body = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        if (!is_array($body)) {
            return ['ok' => false, 'message' => 'پاسخ درگاه پرداخت قابل خواندن نیست.'];
        }
        return ['ok' => true, 'body' => $body];
    }

    protected static function resultMessage($code, $gatewayMessage = '')
    {
        $messages = [
            102 => 'مرچنت زیبال پیدا نشد یا اشتباه وارد شده است.',
            103 => 'مرچنت زیبال غیرفعال است.',
            104 => 'مرچنت زیبال معتبر نیست.',
            105 => 'مبلغ پرداخت برای زیبال معتبر نیست.',
            106 => 'نشانی بازگشت درگاه معتبر نیست. نشانی پایه بازگشت را در تنظیمات درگاه بررسی کنید.',
            113 => 'مبلغ تراکنش کمتر از حداقل مجاز زیبال است.',
            201 => 'این تراکنش قبلاً تأیید شده است.',
            202 => 'پرداخت توسط درگاه تأیید نشده است یا مشتری پرداخت را کامل نکرده است.',
            203 => 'شناسه پیگیری زیبال معتبر نیست.',
        ];
        $message = $messages[(int) $code] ?? 'درگاه پرداخت درخواست را نپذیرفت.';
        if (trim((string) $gatewayMessage) !== '') {
            $message .= ' پیام زیبال: ' . trim((string) $gatewayMessage);
        }
        if ((int) $code !== 0) {
            $message .= ' کد زیبال: ' . to_persian_digits((string) $code);
        }
        return $message;
    }
}
