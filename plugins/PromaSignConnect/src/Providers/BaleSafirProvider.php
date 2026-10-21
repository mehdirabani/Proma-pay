<?php
namespace Proma\Plugins\SignConnect\Providers;
use Proma\Plugins\SignConnect\Services\BaleSafirPayloadBuilder;
final class BaleSafirProvider extends AbstractProvider
{
    public function getKey(): string { return 'bale'; }
    public function getCapabilities(): array { return ['text', 'otp', 'secure', 'template', 'button']; }

    public function send(array $message): array
    {
        $requestId = (string) ($message['request_id'] ?? $message['idempotency_key'] ?? '');
        if (!preg_match('/^[0-9a-f-]{32,64}$/i', $requestId)) {
            throw new \InvalidArgumentException('bale_request_id_invalid');
        }
        $payload = (new BaleSafirPayloadBuilder())->build($message, $requestId, $this->required('bot_id'));
        $started = microtime(true);
        $response = $this->http->request(
            'POST',
            $this->config['base_url'] ?? 'https://safir.bale.ai/api/v3/send_message',
            [
                'api-access-key' => $this->required('api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Request-ID' => $requestId,
            ],
            $payload,
            max(3, min(30, (int) ($this->config['timeout'] ?? 10)))
        );

        $body = $response['body'];
        $error = $this->errorDetails($body);
        $id = $body['message_id'] ?? $body['data']['message_id'] ?? null;
        $hasId = is_scalar($id) && (string) $id !== '';
        $ok = $response['status'] >= 200 && $response['status'] < 300 && !$error['present'] && $hasId;
        return [
            'ok' => $ok,
            'external_id' => $hasId ? (string) $id : null,
            'status' => $response['status'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $this->mapError($error['name'], (int) $response['status'], $error['code']),
            'response' => $this->redact($body),
        ];
    }

    public function healthCheck(): array
    {
        return [
            'ok' => trim((string) ($this->config['api_key'] ?? '')) !== ''
                && preg_match('/^\d+$/', (string) ($this->config['bot_id'] ?? '')) === 1,
            'passive' => true,
        ];
    }

    private function errorDetails(array $body): array
    {
        $errorData = $body['error_data'] ?? null;
        if (is_array($errorData) && array_is_list($errorData)) {
            $errorData = $errorData[0] ?? null;
        }
        $errorData = is_array($errorData) ? $errorData : [];
        $code = $errorData['code'] ?? $body['error']['code'] ?? $body['error_code'] ?? null;
        $name = (string) ($body['error']['name'] ?? $body['error_name'] ?? '');
        $namesByCode = [
            2 => 'InternalServerError',
            3 => 'RateLimitExceeded',
            4 => 'InvalidInput',
            8 => 'InvalidPhone',
            17 => 'NotBaleUser',
            20 => 'PaymentRequired',
            21 => 'MaximumContactLimitReached',
        ];
        if ($name === '' && is_numeric($code)) $name = $namesByCode[(int) $code] ?? '';
        return [
            'present' => !empty($errorData) || $name !== '',
            'name' => $name,
            'code' => is_numeric($code) ? (int) $code : null,
        ];
    }

    private function mapError(string $name, int $status, ?int $code = null): array
    {
        $map = [
            'InternalServerError' => ['retryable', true],
            'RateLimitExceeded' => ['retryable_after_delay', true],
            'InvalidInput' => ['invalid_payload', false],
            'InvalidPhone' => ['invalid_recipient', false],
            'NotBaleUser' => ['channel_unavailable', false],
            'PaymentRequired' => ['provider_credit_problem', false],
            'MaximumContactLimitReached' => ['provider_account_limit', false],
        ];
        $statusMap = [
            401 => 'provider_authentication_failed',
            403 => 'provider_access_denied',
            404 => 'provider_endpoint_not_found',
            408 => 'retryable',
            429 => 'retryable_after_delay',
        ];
        if ($status >= 500) $statusMap[$status] = 'retryable';
        if (isset($map[$name])) {
            return ['name' => $name, 'code' => $code, 'category' => $map[$name][0], 'retryable' => $map[$name][1]];
        }
        $category = $statusMap[$status] ?? 'provider_error';
        return [
            'name' => $name,
            'code' => $code,
            'category' => $category,
            'retryable' => in_array($category, ['retryable', 'retryable_after_delay'], true),
        ];
    }

    private function redact(array $body): array
    {
        $sensitive = ['api_access_key', 'api-access-key', 'token', 'authorization'];
        foreach ($body as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                unset($body[$key]);
                continue;
            }
            if (strtolower((string) $key) === 'phone_number') {
                $body[$key] = is_scalar($value) ? IranianPhoneNumberNormalizer::mask((string) $value) : null;
            } elseif (is_array($value)) {
                $body[$key] = $this->redact($value);
            }
        }
        return $body;
    }
}
