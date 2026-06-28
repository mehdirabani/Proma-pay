<?php

class OpenRouterClient
{
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
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.2,
        ];
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
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
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $status >= 400) {
            return ['ok' => false, 'error' => 'ارتباط با سرویس هوش مصنوعی برقرار نشد.'];
        }
        $json = json_decode($response, true);
        $content = $json['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            return ['ok' => false, 'error' => 'پاسخ قابل استفاده‌ای دریافت نشد.'];
        }
        return ['ok' => true, 'content' => $content];
    }
}
