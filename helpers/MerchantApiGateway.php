<?php

declare(strict_types=1);

/** Test-environment-only REST gateway for verified merchant connections. */
final class MerchantApiGateway
{
    public const MAX_BODY_BYTES = 65536;
    public const API_VERSION = 'v1';

    public static function dispatch(): void
    {
        $rawBody = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        $response = self::handle($_SERVER, is_string($rawBody) ? $rawBody : '', self::requestHeaders());
        http_response_code((int) $response['status']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        foreach (($response['headers'] ?? []) as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode(
            $response['body'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /** Request adapter is separately callable so HTTP behavior can be exercised without a web server. */
    public static function handle(array $server, string $rawBody, array $headers = []): array
    {
        try {
            if (strlen($rawBody) > self::MAX_BODY_BYTES) {
                throw new HttpException(413, 'request_too_large');
            }
            if (!self::isSecureTransport($server)) {
                throw new HttpException(426, 'https_required');
            }

            $method = strtoupper(trim((string) ($server['REQUEST_METHOD'] ?? 'GET')));
            if (!in_array($method, ['GET', 'POST'], true)) {
                throw new HttpException(405, 'method_not_allowed', [], ['Allow' => 'GET, POST']);
            }
            $path = self::requestPath($server);
            $headerMap = self::normalizeHeaders($headers);
            if (!isset($headerMap['authorization']) && isset($server['HTTP_AUTHORIZATION'])) {
                $headerMap['authorization'] = trim((string) $server['HTTP_AUTHORIZATION']);
            }
            if (!preg_match('/\ABearer\s+(pmp_test_[A-Za-z0-9_-]{43})\z/iD', (string) ($headerMap['authorization'] ?? ''), $match)) {
                throw new HttpException(401, 'authentication_required', [], ['WWW-Authenticate' => 'Bearer']);
            }

            $context = MerchantApiCredentialRepository::authenticate($match[1]);
            if (!$context) {
                throw new HttpException(401, 'invalid_or_inactive_credential', [], ['WWW-Authenticate' => 'Bearer']);
            }
            $limit = MerchantApiGatewayRepository::consumeRateLimit($context);
            if (!$limit['allowed']) {
                throw new HttpException(429, 'rate_limit_exceeded', [], ['Retry-After' => (string) $limit['retry_after']]);
            }

            $input = self::decodeBody($method, $rawBody, $headerMap);
            $data = self::dispatchRoute($context, $method, $path, $input, $headerMap);
            return ['status' => (int) ($data['_http_status'] ?? 200), 'body' => ['data' => self::withoutHttpStatus($data)]];
        } catch (HttpException $e) {
            return [
                'status' => $e->status(),
                'headers' => $e->headers(),
                'body' => ['error' => ['code' => self::safeErrorCode($e->getMessage()), 'request_id' => self::requestId()]],
            ];
        } catch (InvalidArgumentException $e) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'invalid_request', 'request_id' => self::requestId()]]];
        } catch (DomainException $e) {
            $code = $e->getMessage();
            $status = $code === 'merchant_scope_denied' ? 403
                : (str_contains($code, 'not_found') || str_contains($code, 'not_available') ? 404 : 409);
            return ['status' => $status, 'body' => ['error' => ['code' => self::safeErrorCode($code), 'request_id' => self::requestId()]]];
        } catch (OverflowException $e) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'quote_amount_out_of_range', 'request_id' => self::requestId()]]];
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log('merchant_api_gateway', $e, 500);
            }
            return ['status' => 500, 'body' => ['error' => ['code' => 'internal_error', 'request_id' => self::requestId()]]];
        }
    }

    public static function requestPath(array $server, ?array $query = null): string
    {
        $query = $query ?? $_GET;
        $path = (string) ($query['path'] ?? $server['PATH_INFO'] ?? '');
        if ($path === '') {
            $uriPath = parse_url((string) ($server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $path = is_string($uriPath) ? $uriPath : '';
            foreach (['/api/v1/index.php', '/api/v1'] as $prefix) {
                if ($path === $prefix) {
                    $path = '';
                    break;
                }
                if (strpos($path, $prefix . '/') === 0) {
                    $path = substr($path, strlen($prefix));
                    break;
                }
            }
        }
        $path = '/' . trim(rawurldecode($path), '/');
        if ($path === '/') {
            return '/';
        }
        if (strlen($path) > 300 || str_contains($path, '\\') || preg_match('#(?:^|/)\.\.?(/|$)#', $path)) {
            throw new HttpException(400, 'invalid_path');
        }
        return $path;
    }

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{15,99}\z/D', $key)) {
            throw new InvalidArgumentException('merchant_api_idempotency_key_invalid');
        }
        return $key;
    }

    public static function canonicalRequestHash(string $method, string $path, array $payload): string
    {
        $method = strtoupper(trim($method));
        $path = '/' . trim($path, '/');
        if (!in_array($method, ['POST'], true) || strlen($path) > 300) {
            throw new InvalidArgumentException('merchant_api_request_hash_input_invalid');
        }
        $canonical = self::canonicalize($payload);
        return hash('sha256', $method . "\n" . $path . "\n" . json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ));
    }

    /** Calculate only from a published connection policy; the caller cannot override its rate. */
    public static function calculateQuote(array $policy, array $input): array
    {
        foreach (['cash_price_toman', 'down_payment_toman', 'months'] as $field) {
            if (!isset($input[$field]) || !is_int($input[$field])) {
                throw new InvalidArgumentException('merchant_api_quote_integer_required');
            }
        }
        if (array_key_exists('monthly_rate_bps', $input) || array_key_exists('monthly_interest_rate', $input)) {
            throw new InvalidArgumentException('merchant_api_rate_override_forbidden');
        }

        $cashPrice = $input['cash_price_toman'];
        $downPayment = $input['down_payment_toman'];
        $months = $input['months'];
        $limits = is_array($policy['limits'] ?? null) ? $policy['limits'] : [];
        $methods = array_values(array_unique(array_filter((array) ($policy['methods'] ?? []), 'is_string')));
        $rateBps = $policy['monthly_rate_bps'] ?? null;
        if (!is_int($rateBps) || $rateBps < 0 || $rateBps > 10000 || !$methods) {
            throw new DomainException('merchant_api_financial_policy_unavailable');
        }
        $minOrder = $limits['min_order_toman'] ?? 1;
        $maxOrder = $limits['max_order_toman'] ?? PHP_INT_MAX;
        $maxMonths = $limits['max_months'] ?? 480;
        $minDownPaymentBps = $limits['min_down_payment_bps'] ?? 0;
        if (!is_int($minOrder) || !is_int($maxOrder) || !is_int($maxMonths) || !is_int($minDownPaymentBps)
            || $minOrder < 1 || $maxOrder < $minOrder || $maxMonths < 1 || $maxMonths > 480
            || $minDownPaymentBps < 0 || $minDownPaymentBps > 10000) {
            throw new DomainException('merchant_api_financial_policy_invalid');
        }
        $method = strtolower(trim((string) ($input['interest_method'] ?? '')));
        if (!in_array($method, ['flat', 'compound', 'reducing_balance'], true) || !in_array($method, $methods, true)) {
            throw new InvalidArgumentException('merchant_api_interest_method_not_allowed');
        }
        if ($cashPrice < $minOrder || $cashPrice > $maxOrder || $months < 1 || $months > $maxMonths
            || $downPayment < 0 || $downPayment >= $cashPrice) {
            throw new InvalidArgumentException('merchant_api_quote_outside_policy');
        }
        if ($minDownPaymentBps > 0 && $downPayment < MoneyMath::ceilMulDiv($cashPrice, $minDownPaymentBps, 10000)) {
            throw new InvalidArgumentException('merchant_api_down_payment_below_policy');
        }

        return InstallmentFinancialCalculator::calculate([
            'cash_price' => $cashPrice,
            'down_payment' => $downPayment,
            'months' => $months,
            'monthly_interest_rate' => MoneyMath::rateLabel($rateBps * 100),
            'interest_method' => $method,
        ]);
    }

    private static function dispatchRoute(
        MerchantAccessContext $context,
        string $method,
        string $path,
        array $input,
        array $headers
    ): array {
        if ($method === 'GET' && $path === '/connection') {
            $context->requireScope('merchant.read');
            return ['connection' => MerchantApiGatewayRepository::connectionSummary($context)];
        }
        if ($method === 'GET' && $path === '/policies/financial') {
            $context->requireScope('policy:read');
            $policy = MerchantApiGatewayRepository::financialPolicy($context);
            $document = self::publicFinancialPolicy($policy['document']);
            $canonical = json_encode(
                $document,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
            return ['policy' => [
                'policy_type' => 'financial',
                'policy_version_uuid' => $policy['policy_version_uuid'],
                'policy_sha256' => hash('sha256', $canonical),
                'source_policy_sha256' => $policy['content_sha256'],
                'document' => $document,
            ]];
        }
        if ($method === 'POST' && $path === '/quotes') {
            $context->requireScope('quote:create');
            self::assertAllowedKeys($input, ['cash_price_toman','down_payment_toman','months','interest_method']);
            return ['quote' => self::quote($context, $input)];
        }
        if ($method === 'POST' && $path === '/orders') {
            $context->requireScope('order:create');
            self::assertAllowedKeys($input, ['external_order_id','cash_price_toman','down_payment_toman','months','interest_method']);
            $key = self::normalizeIdempotencyKey((string) ($headers['idempotency-key'] ?? ''));
            $externalOrderId = self::externalOrderId($input['external_order_id'] ?? null);
            $hash = self::canonicalRequestHash('POST', $path, $input);
            $replay = MerchantApiGatewayRepository::replayIdempotent($context, $key, 'POST', $path, $hash);
            if ($replay !== null) {
                return $replay + ['_http_status' => 200];
            }
            $quote = self::quote($context, $input);
            $result = MerchantApiGatewayRepository::createOrderRequest($context, $key, $hash, $externalOrderId, $input, $quote);
            return $result + ['_http_status' => ($result['idempotent'] ?? false) ? 200 : 201];
        }
        if ($method === 'POST' && $path === '/contracts') {
            $context->requireScope('contract:write');
            self::assertAllowedKeys($input, ['order_uuid','external_customer_reference']);
            $key = self::normalizeIdempotencyKey((string) ($headers['idempotency-key'] ?? ''));
            $orderUuid = self::uuid($input['order_uuid'] ?? null, 'merchant_api_order_uuid_invalid');
            $customerReference = self::customerReference($input['external_customer_reference'] ?? null);
            $hash = self::canonicalRequestHash('POST', $path, $input);
            $result = MerchantApiGatewayRepository::submitContractRequest(
                $context, $key, $hash, $orderUuid, $customerReference
            );
            return $result + ['_http_status' => ($result['idempotent'] ?? false) ? 200 : 202];
        }
        if ($method === 'GET' && preg_match('#\A/contracts/([0-9a-f-]{36})\z#iD', $path, $match)) {
            $context->requireScope('contract:read');
            return ['contract' => MerchantApiGatewayRepository::contractStatus($context, strtolower($match[1]))];
        }
        if ($method === 'GET' && preg_match('#\A/guarantees/([0-9a-f-]{36})\z#iD', $path, $match)) {
            $context->requireScope('guarantee:read');
            return ['guarantee' => MerchantApiGatewayRepository::guaranteeStatus($context, strtolower($match[1]))];
        }
        if ($method === 'GET' && $path === '/webhooks') {
            $context->requireScope('webhook:read');
            return ['webhooks' => MerchantApiGatewayRepository::webhookSummary($context)];
        }
        if ($method === 'POST' && $path === '/connection/emergency-disable') {
            $context->requireScope('connection:manage');
            self::assertAllowedKeys($input, ['reason_code']);
            $key = self::normalizeIdempotencyKey((string) ($headers['idempotency-key'] ?? ''));
            $reason = self::disableReasonCode($input['reason_code'] ?? null);
            $hash = self::canonicalRequestHash('POST', $path, $input);
            $result = MerchantApiGatewayRepository::emergencyDisable($context, $key, $hash, $reason);
            return $result + ['_http_status' => ($result['idempotent'] ?? false) ? 200 : 202];
        }
        throw new HttpException(404, 'route_not_found');
    }

    private static function quote(MerchantAccessContext $context, array $input): array
    {
        $policy = MerchantApiGatewayRepository::financialPolicy($context);
        $snapshot = self::calculateQuote($policy['document'], $input);
        return [
            'quote_uuid' => MerchantAuditLog::newUuid(),
            'policy_version_uuid' => $policy['policy_version_uuid'],
            'policy_sha256' => $policy['content_sha256'],
            'expires_at' => (new DateTimeImmutable('+15 minutes'))->format(DateTimeInterface::ATOM),
            'calculation' => $snapshot,
        ];
    }

    /** Expose only the approved display/calculation fields from the stored document. */
    private static function publicFinancialPolicy(array $document): array
    {
        $rate = $document['monthly_rate_bps'] ?? null;
        $methods = $document['methods'] ?? null;
        if (!is_int($rate) || $rate < 0 || $rate > 10000 || !is_array($methods)) {
            throw new DomainException('merchant_api_financial_policy_invalid');
        }
        $methods = array_values(array_unique(array_filter(
            $methods,
            static fn ($method): bool => is_string($method) && in_array($method, ['flat','compound','reducing_balance'], true)
        )));
        sort($methods, SORT_STRING);
        if (!$methods) {
            throw new DomainException('merchant_api_financial_policy_invalid');
        }
        $limits = [];
        $sourceLimits = $document['limits'] ?? [];
        if (!is_array($sourceLimits)) {
            throw new DomainException('merchant_api_financial_policy_invalid');
        }
        foreach (['min_order_toman','max_order_toman','max_months','min_down_payment_bps'] as $key) {
            if (array_key_exists($key, $sourceLimits)) {
                if (!is_int($sourceLimits[$key]) || $sourceLimits[$key] < 0) {
                    throw new DomainException('merchant_api_financial_policy_invalid');
                }
                $limits[$key] = $sourceLimits[$key];
            }
        }
        ksort($limits, SORT_STRING);
        return ['monthly_rate_bps' => $rate, 'methods' => $methods, 'limits' => $limits];
    }

    private static function decodeBody(string $method, string $body, array $headers): array
    {
        if ($method === 'GET') {
            if ($body !== '') {
                throw new HttpException(400, 'body_not_allowed');
            }
            return [];
        }
        $contentType = strtolower(trim(explode(';', (string) ($headers['content-type'] ?? ''))[0]));
        if ($contentType !== 'application/json') {
            throw new HttpException(415, 'application_json_required');
        }
        try {
            $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpException(400, 'invalid_json');
        }
        if (!is_array($input) || array_is_list($input)) {
            throw new HttpException(400, 'json_object_required');
        }
        return $input;
    }

    private static function isSecureTransport(array $server): bool
    {
        $https = strtolower(trim((string) ($server['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }
        return in_array(trim((string) ($server['REMOTE_ADDR'] ?? '')), ['127.0.0.1', '::1'], true);
    }

    private static function requestHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        return is_array($headers) ? $headers : [];
    }

    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $normalized[strtolower(str_replace('_', '-', trim($name)))] = trim((string) $value);
            }
        }
        return $normalized;
    }

    private static function externalOrderId($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('merchant_api_external_order_id_invalid');
        }
        $value = trim($value);
        if (strlen($value) < 1 || strlen($value) > 190 || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $value)) {
            throw new InvalidArgumentException('merchant_api_external_order_id_invalid');
        }
        return $value;
    }

    private static function customerReference($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('merchant_api_customer_reference_invalid');
        }
        $value = trim($value);
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{7,189}\z/D', $value)) {
            throw new InvalidArgumentException('merchant_api_customer_reference_invalid');
        }
        return $value;
    }

    private static function disableReasonCode($value): string
    {
        $allowed = ['security_incident','credential_compromised','merchant_requested','other'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('merchant_api_disable_reason_invalid');
        }
        return $value;
    }

    private static function assertAllowedKeys(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('merchant_api_unknown_request_field');
            }
        }
    }

    private static function uuid($value, string $error): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $value)) {
            throw new InvalidArgumentException($error);
        }
        return strtolower($value);
    }

    private static function requiredText($value, int $minimum, int $maximum, string $error): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($error);
        }
        $value = trim($value);
        if (strlen($value) < $minimum || strlen($value) > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    private static function safeErrorCode(string $code): string
    {
        $code = strtolower(trim($code));
        return preg_match('/\A[a-z0-9_]{1,80}\z/D', $code) ? $code : 'request_failed';
    }

    private static function requestId(): string
    {
        return class_exists('ErrorHandler') ? (string) ErrorHandler::requestId() : bin2hex(random_bytes(12));
    }

    private static function withoutHttpStatus(array $data): array
    {
        unset($data['_http_status']);
        return $data;
    }
}
