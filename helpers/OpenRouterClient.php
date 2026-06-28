<?php

class OpenRouterClient
{
    protected const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    protected $apiKey;
    protected $model;

    public function __construct($apiKey, $model)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->model = trim((string) $model);
    }

    public function configured()
    {
        return $this->apiKey !== '' && $this->model !== '';
    }

    public function analyze($text, $systemPrompt)
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'کلید یا مدل هوش مصنوعی تنظیم نشده است.'];
        }
        $result = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $text],
        ], ['temperature' => 0.2]);
        if (!$result['ok']) {
            return $result;
        }
        $content = $result['content'] ?? '';
        if ($content === '') {
            return ['ok' => false, 'error' => 'پاسخ قابل استفاده‌ای دریافت نشد.'];
        }
        return ['ok' => true, 'content' => $content, 'raw' => $result['raw'] ?? null];
    }

    public function testConnection()
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'کلید API یا مدل وارد نشده است.', 'type' => 'missing_config'];
        }
        $result = $this->chat([
            ['role' => 'system', 'content' => 'فقط با یک جمله کوتاه فارسی پاسخ بده.'],
            ['role' => 'user', 'content' => 'برای تست اتصال، فقط بنویس اتصال برقرار است.'],
        ], ['max_tokens' => 20, 'temperature' => 0]);
        if ($result['ok']) {
            return ['ok' => true, 'message' => 'اتصال موفق بود', 'details' => 'مدل پاسخ معتبر برگرداند.'];
        }
        return [
            'ok' => false,
            'message' => $result['error'] ?? 'خطا در ارتباط با OpenRouter',
            'details' => $result['details'] ?? '',
            'type' => $result['type'] ?? 'connection_error',
        ];
    }

    protected function chat(array $messages, array $options = [])
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'امکان ارتباط امن با OpenRouter روی سرور فعال نیست.', 'type' => 'curl_missing'];
        }
        $payload = array_merge([
            'model' => $this->model,
            'messages' => $messages,
        ], $options);
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
                'X-Title: Proma Pay',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'error' => 'خطا در ارتباط با OpenRouter', 'details' => $error, 'type' => 'connection_error'];
        }
        $json = json_decode($response, true);
        if ($status >= 400) {
            return $this->mapError($status, is_array($json) ? $json : null, $response);
        }
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'پاسخ OpenRouter قابل خواندن نیست.', 'details' => mb_substr($response, 0, 300, 'UTF-8'), 'type' => 'bad_response'];
        }
        $content = $json['choices'][0]['message']['content'] ?? '';
        return ['ok' => $content !== '', 'content' => $content, 'raw' => $json, 'error' => $content === '' ? 'پاسخ قابل استفاده‌ای دریافت نشد.' : null];
    }

    protected function mapError($status, ?array $json, $raw)
    {
        $message = (string) ($json['error']['message'] ?? $json['message'] ?? '');
        $lower = strtolower($message);
        if (in_array((int) $status, [401, 403], true)) {
            return ['ok' => false, 'error' => 'کلید API نامعتبر است', 'details' => $this->friendlyDetails($message), 'type' => 'invalid_key'];
        }
        if (strpos($lower, 'model') !== false || strpos($lower, 'not found') !== false || (int) $status === 404) {
            return ['ok' => false, 'error' => 'مدل انتخاب‌شده در دسترس نیست', 'details' => $this->friendlyDetails($message), 'type' => 'model_unavailable'];
        }
        if ((int) $status === 429) {
            return ['ok' => false, 'error' => 'سهمیه یا محدودیت درخواست OpenRouter فعال شده است.', 'details' => $this->friendlyDetails($message), 'type' => 'rate_limit'];
        }
        return [
            'ok' => false,
            'error' => 'خطا در ارتباط با OpenRouter',
            'details' => $this->friendlyDetails($message ?: mb_substr((string) $raw, 0, 300, 'UTF-8')),
            'type' => 'connection_error',
        ];
    }

    protected function friendlyDetails($message)
    {
        $message = trim((string) $message);
        if ($message === '') {
            return 'جزئیات بیشتری از سرویس دریافت نشد.';
        }
        return preg_replace('/sk-or-[A-Za-z0-9_\-]+/', 'کلید مخفی', $message);
    }
}
