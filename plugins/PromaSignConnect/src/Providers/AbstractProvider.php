<?php
namespace Proma\Plugins\SignConnect\Providers;
use Proma\Plugins\SignConnect\Contracts\CommunicationProviderInterface;
use Proma\Plugins\SignConnect\Services\HttpClient;
abstract class AbstractProvider implements CommunicationProviderInterface
{
    protected array $config;
    protected HttpClient $http;
    public function __construct(array $config, ?HttpClient $http = null) { $this->config = $config; $this->http = $http ?: new HttpClient(); }
    public function getName(): string { return strtoupper($this->getKey()); }
    public function validateConfiguration(): array
    {
        return ['ok' => !empty($this->config), 'errors' => empty($this->config) ? ['provider_not_configured'] : []];
    }
    public function testConnection(): array { return $this->healthCheck(); }
    protected function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') throw new \RuntimeException('provider_not_configured:' . $key);
        return $value;
    }
    protected function normalizeIranMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if (str_starts_with($digits, '0098')) $digits = substr($digits, 2);
        if (str_starts_with($digits, '09')) $digits = '98' . substr($digits, 1);
        if (str_starts_with($digits, '9')) $digits = '98' . $digits;
        if (!preg_match('/^989\d{9}$/', $digits)) throw new \InvalidArgumentException('invalid_mobile');
        return '+' . $digits;
    }
    public function normalizeRecipient(string $recipient): string { return $this->normalizeIranMobile($recipient); }
    public function queryStatus(string $externalId): array { return ['ok' => false, 'status' => 'unknown', 'external_id' => $externalId]; }
    public function delivery(string $externalId): array { return $this->queryStatus($externalId); }
    public function mapProviderError(string $code, array $response = []): array
    {
        $status = (int) ($response['status'] ?? 0);
        return [
            'code' => preg_replace('/[^a-z0-9_.-]/i', '', $code) ?: 'provider_error',
            'retryable' => $status === 429 || $status >= 500 || $status === 0,
        ];
    }
    public function isRetryable(array $error): bool { return !empty($error['retryable']); }
    public function redactResponse(array $response): array
    {
        foreach (['token', 'bot_token', 'api_key', 'api-access-key', 'x-api-key', 'authorization'] as $key) {
            unset($response[$key]);
        }
        return $response;
    }
}
