<?php

declare(strict_types=1);

/** Small bounded REST client for the test-only, versioned Proma Pay API. */
final class PromaPay_WC_Api_Client
{
    public const MAX_RESPONSE_BYTES = 65536;
    private $transport;
    private PromaPay_WC_Connection_Repository $repository;

    public function __construct(PromaPay_WC_Connection_Repository $repository, ?callable $transport = null)
    {
        $this->repository = $repository;
        $this->transport = $transport;
    }

    public static function normalize_base_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 255 || preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new InvalidArgumentException('promapaywc_api_url_invalid');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('promapaywc_api_url_invalid');
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('promapaywc_https_required');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || !preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/iD', $host)) {
            throw new InvalidArgumentException('promapaywc_api_host_invalid');
        }
        if (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            throw new InvalidArgumentException('promapaywc_api_port_invalid');
        }
        $path = (string) ($parts['path'] ?? '');
        if (strlen($path) > 120 || str_contains($path, '\\') || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', rawurldecode($path))) {
            throw new InvalidArgumentException('promapaywc_api_path_invalid');
        }
        return $scheme . '://' . (str_contains($host, ':') ? '[' . $host . ']' : $host)
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . rtrim($path, '/');
    }

    public function request(array $connection, string $method, string $path): array
    {
        return $this->request_json($connection, $method, $path);
    }

    /** Send one explicitly allow-listed API operation; request data is never written to logs. */
    public function request_json(array $connection, string $method, string $path, array $payload = [], string $idempotencyKey = ''): array
    {
        $allowed = [
            'GET /connection' => 'connection_read',
            'GET /policies/financial' => 'policy_read',
            'GET /policies/guarantee' => 'guarantee_policy_read',
            'POST /quotes' => 'quote_create',
            'POST /orders' => 'order_create',
            'POST /contracts' => 'contract_submit',
        ];
        $method = strtoupper($method);
        $operation = $allowed[$method . ' ' . $path] ?? '';
        if ($method === 'GET' && preg_match('#\A/contracts/[0-9a-f-]{36}\z#iD', $path)) {
            $operation = 'contract_status_read';
        } elseif ($method === 'GET' && preg_match('#\A/guarantees/[0-9a-f-]{36}\z#iD', $path)) {
            $operation = 'guarantee_status_read';
        } elseif ($method === 'POST' && preg_match('#\A/orders/[0-9a-f-]{36}/cancel\z#iD', $path)) {
            $operation = 'order_cancel';
        }
        if ($operation === '') {
            return self::failure('invalid_request', 'invalid_response');
        }
        if ($method === 'GET' && $payload !== []) {
            return self::failure('invalid_request', 'invalid_response');
        }
        $allowedFields = [
            'POST /quotes' => ['cash_price_toman','down_payment_toman','months','interest_method'],
            'POST /orders' => ['external_order_id','quote_uuid','cash_price_toman','down_payment_toman','months','interest_method'],
            'POST /contracts' => ['order_uuid','external_customer_reference'],
            'ORDER_CANCEL' => ['reason_code'],
        ];
        $fieldSet = $allowedFields[$method . ' ' . $path] ?? ($operation === 'order_cancel' ? $allowedFields['ORDER_CANCEL'] : null);
        if (is_array($fieldSet) && array_diff(array_keys($payload), $fieldSet) !== []) {
            return self::failure('invalid_request', 'invalid_response');
        }
        if ($method === 'POST' && $path !== '/quotes'
            && !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{15,99}\z/D', $idempotencyKey)) {
            return self::failure('invalid_idempotency_key', 'invalid_response');
        }
        $uuid = (string) ($connection['connection_uuid'] ?? '');
        $started = microtime(true);
        try {
            $token = $this->repository->secret($connection);
            $baseUrl = self::normalize_base_url((string) ($connection['base_url'] ?? ''));
            $url = $baseUrl . '/api/v1' . $path;
            $args = [
                'method' => $method,
                'timeout' => 5,
                'redirection' => 0,
                'sslverify' => true,
                'reject_unsafe_urls' => true,
                'limit_response_size' => self::MAX_RESPONSE_BYTES,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Cache-Control' => 'no-store',
                ],
            ];
            if ($method === 'POST') {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if ($idempotencyKey !== '') {
                    $args['headers']['Idempotency-Key'] = $idempotencyKey;
                }
            }
            $response = is_callable($this->transport)
                ? ($this->transport)($url, $args)
                : wp_safe_remote_request($url, $args);
            if (is_wp_error($response)) {
                $errorCode = self::safe_code((string) $response->get_error_code());
                return $this->finish($uuid, $operation, self::failure($errorCode ?: 'transport_error', 'transport_error'), $started);
            }
            if (!is_array($response)) {
                return $this->finish($uuid, $operation, self::failure('invalid_response', 'invalid_response'), $started);
            }
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);
            if ($status < 100 || $status > 599 || strlen($body) > self::MAX_RESPONSE_BYTES) {
                return $this->finish($uuid, $operation, self::failure('invalid_response', 'invalid_response', $status), $started);
            }
            $decoded = json_decode($body, true, 32);
            if (!is_array($decoded)) {
                return $this->finish($uuid, $operation, self::failure('invalid_response', 'invalid_response', $status), $started);
            }
            if ($status < 200 || $status >= 300) {
                $errorCode = self::safe_code((string) ($decoded['error']['code'] ?? 'api_request_failed'));
                $failure = self::failure($errorCode ?: 'api_request_failed', 'http_error', $status);
                $failure['request_id'] = self::safe_request_id((string) ($decoded['error']['request_id'] ?? ''));
                return $this->finish($uuid, $operation, $failure, $started);
            }
            if (!isset($decoded['data']) || !is_array($decoded['data'])) {
                return $this->finish($uuid, $operation, self::failure('invalid_response', 'invalid_response', $status), $started);
            }
            $requestId = self::safe_request_id((string) ($decoded['error']['request_id'] ?? $decoded['data']['request_id'] ?? ''));
            return $this->finish($uuid, $operation, [
                'ok' => true,
                'status' => $status,
                'data' => $decoded['data'],
                'error_code' => '',
                'request_id' => $requestId,
                'outcome' => 'success',
            ], $started);
        } catch (Throwable $error) {
            $code = self::safe_code($error->getMessage());
            return $this->finish($uuid, $operation, self::failure($code ?: 'client_error', 'transport_error'), $started);
        }
    }

    private function finish(string $uuid, string $operation, array $result, float $started): array
    {
        $duration = (int) min(600000, round((microtime(true) - $started) * 1000));
        $this->repository->log(
            $uuid,
            $operation,
            (int) $result['status'],
            (string) ($result['outcome'] ?? 'invalid_response'),
            $duration,
            (string) ($result['request_id'] ?? ''),
            (string) ($result['error_code'] ?? '')
        );
        unset($result['outcome']);
        return $result;
    }

    private static function failure(string $code, string $outcome, int $status = 0): array
    {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error_code' => self::safe_code($code), 'request_id' => '', 'outcome' => $outcome];
    }

    private static function safe_code(string $value): string
    {
        return preg_match('/\A[a-z0-9_-]{1,64}\z/D', $value) ? $value : 'api_error';
    }

    private static function safe_request_id(string $value): string
    {
        return PromaPay_WC_Connection_Repository::valid_uuid($value) ? strtolower($value) : '';
    }
}
