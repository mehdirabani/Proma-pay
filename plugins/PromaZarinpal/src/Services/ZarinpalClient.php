<?php

namespace Proma\Plugins\Zarinpal\Services;

class ZarinpalClient
{
    private const ENDPOINTS = [
        'production' => [
            'request' => 'https://payment.zarinpal.com/pg/v4/payment/request.json',
            'verify' => 'https://payment.zarinpal.com/pg/v4/payment/verify.json',
            'start' => 'https://payment.zarinpal.com/pg/StartPay/',
        ],
        'sandbox' => [
            'request' => 'https://sandbox.zarinpal.com/pg/v4/payment/request.json',
            'verify' => 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json',
            'start' => 'https://sandbox.zarinpal.com/pg/StartPay/',
        ],
    ];

    private $connectTimeout;
    private $responseTimeout;
    private $transport;

    public function __construct($connectTimeout = 5, $responseTimeout = 20, callable $transport = null)
    {
        $this->connectTimeout = max(3, min(30, (int) $connectTimeout));
        $this->responseTimeout = max($this->connectTimeout, min(60, (int) $responseTimeout));
        $this->transport = $transport;
    }

    public function request($environment, array $payload)
    {
        return $this->post($this->endpoint($environment, 'request'), $payload);
    }

    public function verify($environment, array $payload)
    {
        return $this->post($this->endpoint($environment, 'verify'), $payload);
    }

    public function startUrl($environment, $authority)
    {
        $authority = trim((string) $authority);
        if (!self::validAuthorityForEnvironment($authority, $environment)) {
            throw new \InvalidArgumentException('Authority زرین‌پال معتبر نیست.');
        }
        return $this->endpoint($environment, 'start') . rawurlencode($authority);
    }

    public static function validAuthority($authority)
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{20,100}$/', trim((string) $authority));
    }

    public static function validAuthorityForEnvironment($authority, $environment)
    {
        $authority = trim((string) $authority);
        $prefix = strtolower(trim((string) $environment)) === 'sandbox' ? 'S' : 'A';
        return self::validAuthority($authority) && strtoupper(substr($authority, 0, 1)) === $prefix;
    }

    public static function endpointFor($environment, $operation)
    {
        $environment = strtolower(trim((string) $environment));
        $operation = strtolower(trim((string) $operation));
        if (!isset(self::ENDPOINTS[$environment][$operation])) {
            throw new \InvalidArgumentException('محیط یا عملیات زرین‌پال معتبر نیست.');
        }
        return self::ENDPOINTS[$environment][$operation];
    }

    private function endpoint($environment, $operation)
    {
        return self::endpointFor($environment, $operation);
    }

    private function post($url, array $payload)
    {
        if ($this->transport) {
            return $this->normalizeTransport(call_user_func($this->transport, $url, $payload, $this->connectTimeout, $this->responseTimeout));
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'network_uncertain' => false, 'error_code' => 'curl_missing', 'message' => 'افزونه cURL روی سرور فعال نیست.'];
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return ['ok' => false, 'network_uncertain' => false, 'error_code' => 'json_encode', 'message' => 'ساخت درخواست درگاه انجام نشد.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->responseTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $startedAt = microtime(true);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($ch);
        if (class_exists('RequestTelemetry', false)) {
            \RequestTelemetry::recordExternal('zarinpal', (microtime(true) - $startedAt) * 1000, $status, $response !== false && $status >= 200 && $status < 300);
        }
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'network_uncertain' => true, 'http_status' => $status, 'error_code' => 'network_' . $errorNumber, 'message' => 'پاسخ قطعی از زرین‌پال دریافت نشد. وضعیت تراکنش باید بررسی شود.'];
        }
        return $this->normalizeTransport(['http_status' => $status, 'body' => $response, 'network_uncertain' => false]);
    }

    private function normalizeTransport($result)
    {
        if (!is_array($result)) {
            return ['ok' => false, 'network_uncertain' => true, 'error_code' => 'transport_invalid', 'message' => 'پاسخ قطعی از زرین‌پال دریافت نشد.'];
        }
        $status = (int) ($result['http_status'] ?? 0);
        $body = $result['body'] ?? null;
        if (is_string($body)) {
            if (strlen($body) > 1024 * 1024) {
                return ['ok' => false, 'network_uncertain' => false, 'http_status' => $status, 'error_code' => 'response_too_large', 'message' => 'پاسخ درگاه بیش از حد مجاز بود.'];
            }
            $body = json_decode($body, true);
        }
        if (!is_array($body)) {
            return ['ok' => false, 'network_uncertain' => !empty($result['network_uncertain']), 'http_status' => $status, 'error_code' => $result['error_code'] ?? 'invalid_json', 'message' => 'پاسخ زرین‌پال قابل خواندن نیست.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'network_uncertain' => false, 'http_status' => $status, 'body' => $body, 'error_code' => 'http_' . $status, 'message' => 'زرین‌پال درخواست را نپذیرفت.'];
        }
        return ['ok' => true, 'network_uncertain' => false, 'http_status' => $status, 'body' => $body];
    }
}
