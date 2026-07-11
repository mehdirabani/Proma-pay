<?php

class IppanelClient
{
    protected const BASE_URL = 'https://edge.ippanel.com/v1';

    protected $apiKey;
    protected $fromNumber;
    protected $patternCode;
    protected $patternKey;

    public function __construct(array $settings)
    {
        $this->apiKey = trim((string) ($settings['ippanel_api_key'] ?? ''));
        $this->fromNumber = self::normalizePhone((string) ($settings['ippanel_from_number'] ?? ''));
        $this->patternCode = trim((string) ($settings['ippanel_password_reset_pattern_code'] ?? ''));
        $this->patternKey = trim((string) ($settings['ippanel_password_reset_pattern_key'] ?? 'code')) ?: 'code';
    }

    public function configured()
    {
        return $this->apiKey !== '' && $this->fromNumber !== '';
    }

    public function sendPasswordReset($mobile, $code)
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'پنل پیامکی IPPanel تنظیم نشده است.'];
        }

        $recipient = self::normalizePhone($mobile);
        if ($recipient === '') {
            return ['ok' => false, 'message' => 'شماره موبایل کاربر معتبر نیست.'];
        }

        if ($this->patternCode !== '') {
            return $this->sendPattern($recipient, [$this->patternKey => (string) $code]);
        }

        return $this->sendWebservice(
            $recipient,
            "کد بازیابی رمز عبور پرما: {$code}\nاعتبار کد: ۱۰ دقیقه"
        );
    }

    protected function sendWebservice($recipient, $message)
    {
        return $this->post('/api/send', [
            'sending_type' => 'webservice',
            'from_number' => $this->fromNumber,
            'message' => $message,
            'params' => [
                'recipients' => [$recipient],
            ],
        ]);
    }

    protected function sendPattern($recipient, array $params)
    {
        return $this->post('/api/send', [
            'sending_type' => 'pattern',
            'from_number' => $this->fromNumber,
            'code' => $this->patternCode,
            'recipients' => [$recipient],
            'params' => $params,
        ]);
    }

    protected function post($path, array $payload)
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'افزونه cURL برای ارسال پیامک فعال نیست.'];
        }

        $ch = curl_init(self::BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'message' => 'ارتباط با IPPanel برقرار نشد.', 'details' => $error];
        }

        $body = json_decode($response, true);
        if (!is_array($body)) {
            return ['ok' => false, 'message' => 'پاسخ IPPanel قابل خواندن نیست.'];
        }

        $meta = $body['meta'] ?? [];
        if ($status >= 400 || !($meta['status'] ?? false)) {
            return [
                'ok' => false,
                'message' => $meta['message'] ?? 'ارسال پیامک ناموفق بود.',
                'details' => $meta['errors'] ?? null,
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'message' => $meta['message'] ?? 'پیامک ارسال شد.',
            'data' => $body['data'] ?? null,
        ];
    }

    public static function normalizePhone($phone)
    {
        $phone = to_english_digits((string) $phone);
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (strpos($phone, '+') === 0) {
            return $phone;
        }
        if (strpos($phone, '00') === 0) {
            return '+' . substr($phone, 2);
        }
        if (strpos($phone, '0') === 0) {
            return '+98' . substr($phone, 1);
        }
        if (strpos($phone, '98') === 0) {
            return '+' . $phone;
        }
        return '+' . $phone;
    }
}
