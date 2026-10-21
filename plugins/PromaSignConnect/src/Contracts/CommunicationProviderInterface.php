<?php
namespace Proma\Plugins\SignConnect\Contracts;
interface CommunicationProviderInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function getCapabilities(): array;
    public function validateConfiguration(): array;
    public function testConnection(): array;
    public function normalizeRecipient(string $recipient): string;
    public function send(array $message): array;
    public function queryStatus(string $externalId): array;
    public function mapProviderError(string $code, array $response = []): array;
    public function isRetryable(array $error): bool;
    public function redactResponse(array $response): array;
    public function healthCheck(): array;
}
