<?php

class ZibalClient
{
    protected $merchant;

    public function __construct($merchant)
    {
        $this->merchant = trim((string) $merchant);
    }

    public function request($amountToman, $callbackUrl, $description)
    {
        if ($this->merchant === '') {
            return ['ok' => false, 'message' => 'مرچنت درگاه پرداخت تنظیم نشده است.'];
        }
        $payload = [
            'merchant' => $this->merchant,
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
            return ['ok' => false, 'message' => 'درگاه پرداخت درخواست را نپذیرفت.'];
        }
        return ['ok' => true, 'track_id' => $body['trackId'], 'start_url' => 'https://gateway.zibal.ir/start/' . $body['trackId']];
    }

    public function verify($trackId)
    {
        if ($this->merchant === '') {
            return ['ok' => false, 'message' => 'مرچنت درگاه پرداخت تنظیم نشده است.'];
        }
        $result = $this->postJson('https://gateway.zibal.ir/v1/verify', [
            'merchant' => $this->merchant,
            'trackId' => (int) $trackId,
        ]);
        if (!$result['ok']) {
            return $result;
        }
        $body = $result['body'];
        return [
            'ok' => (($body['result'] ?? 0) === 100),
            'message' => (($body['result'] ?? 0) === 100) ? 'پرداخت تأیید شد.' : 'پرداخت تأیید نشد.',
            'ref_id' => $body['refNumber'] ?? null,
            'amount_toman' => isset($body['amount']) ? ((float) $body['amount'] / 10) : null,
        ];
    }

    protected function postJson($url, array $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $status >= 400) {
            return ['ok' => false, 'message' => 'ارتباط با درگاه پرداخت برقرار نشد.'];
        }
        $body = json_decode($response, true);
        if (!is_array($body)) {
            return ['ok' => false, 'message' => 'پاسخ درگاه پرداخت قابل خواندن نیست.'];
        }
        return ['ok' => true, 'body' => $body];
    }
}
