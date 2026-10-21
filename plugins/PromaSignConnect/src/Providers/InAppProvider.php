<?php
namespace Proma\Plugins\SignConnect\Providers;

final class InAppProvider extends AbstractProvider
{
    public function getKey(): string { return 'in_app'; }
    public function getName(): string { return 'اعلان داخل سامانه'; }
    public function getCapabilities(): array { return ['text', 'security_alert', 'custom_notification']; }
    public function normalizeRecipient(string $recipient): string
    {
        if (!preg_match('/^\d+$/', $recipient) || (int) $recipient < 1) {
            throw new \InvalidArgumentException('invalid_user_recipient');
        }
        return $recipient;
    }
    public function send(array $message): array
    {
        $userId = (int) ($message['user_id'] ?? $message['to'] ?? 0);
        $this->normalizeRecipient((string) $userId);
        $id = \SystemOutbox::safeEnqueueNotification(
            $userId,
            (string) ($message['title'] ?? 'اعلان سامانه'),
            (string) ($message['text'] ?? ''),
            (string) ($message['type'] ?? 'system'),
            $message['url'] ?? null,
            'proma_connect_delivery',
            isset($message['delivery_id']) ? (int) $message['delivery_id'] : null
        );
        return ['ok' => $id !== null, 'external_id' => (string) $id, 'status' => $id ? 202 : 500];
    }
    public function healthCheck(): array { return ['ok' => class_exists('SystemOutbox'), 'passive' => true]; }
}

