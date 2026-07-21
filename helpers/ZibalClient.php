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
        $payload = [
            'merchant' => $merchant,
            'amount' => (int) round($amountToman * 10),
            'callbackUrl' => $callbackUrl,
            'description' => $description,
        ];
        $result = $this->postJson('https://gateway.zibal.ir/v1/request', $payload);
        if (!$result['ok']) {
            return $result;
        }
        $body = $result['body'];
        if (($body['result'] ?? 0) !== 100) {
            $code = (int) ($body['result'] ?? 0);
            return [
                'ok' => false,
                'message' => self::resultMessage($code, $body['message'] ?? ''),
                'gateway_code' => $code,
            ];
        }
        return ['ok' => true, 'track_id' => $body['trackId'], 'start_url' => 'https://gateway.zibal.ir/start/' . $body['trackId']];
    }

    public function verify($trackId)
    {
        $merchant = $this->effectiveMerchant();
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'مرچنت درگاه پرداخت تنظیم نشده است.'];
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
        return [
            'ok' => $code === 100,
            'message' => $code === 100 ? 'پرداخت تأیید شد.' : self::resultMessage($code, $body['message'] ?? ''),
            'ref_id' => $body['refNumber'] ?? null,
            'amount_toman' => isset($body['amount']) ? ((float) $body['amount'] / 10) : null,
            'gateway_code' => $code,
        ];
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
        $body = json_decode($response, true);
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
