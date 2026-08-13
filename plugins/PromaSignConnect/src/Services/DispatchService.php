<?php
namespace Proma\Plugins\SignConnect\Services;
final class DispatchService
{
    public function enqueue(array $message): int
    {
        $canonical = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $key = hash('sha256', (string) ($message['idempotency_key'] ?? $canonical));
        $encrypted = SecretCipher::encrypt($canonical);
        \Model::execute(
            "INSERT IGNORE INTO proma_connect_deliveries
             (idempotency_key,provider_key,channel,recipient_hash,template_key,payload_json,encrypted_payload,status,attempts,created_at)
             VALUES (?,?,?,?,?,NULL,?,'outbox_pending',0,NOW())",
            [
                $key,
                (string) ($message['provider'] ?? 'auto'),
                (string) ($message['channel'] ?? 'sms'),
                hash('sha256', (string) ($message['to'] ?? $message['chat_id'] ?? '')),
                (string) ($message['template_key'] ?? ''),
                $encrypted,
            ]
        );
        $id = (int) \Model::lastInsertId();
        if ($id === 0) {
            $existing = \Model::fetch('SELECT id FROM proma_connect_deliveries WHERE idempotency_key=? LIMIT 1', [$key]);
            return (int) ($existing['id'] ?? 0);
        }
        $outboxId = \SystemOutbox::safeEnqueuePluginHook(
            'proma.connect.dispatch',
            ['delivery_id' => $id],
            'proma_connect_delivery',
            $id
        );
        if (!$outboxId) {
            \Model::execute(
                "UPDATE proma_connect_deliveries
                 SET status='enqueue_failed', last_error_code='core_outbox_unavailable',
                     encrypted_payload=NULL, payload_purged_at=NOW(), updated_at=NOW()
                 WHERE id=?",
                [$id]
            );
            throw new \RuntimeException('core_outbox_unavailable');
        }
        \Model::execute("UPDATE proma_connect_deliveries SET status='queued',updated_at=NOW() WHERE id=?", [$id]);
        return $id;
    }

    public function work(int $limit = 25): array
    {
        return ['processed' => 0, 'failed' => 0, 'delegated_to' => 'core_outbox'];
    }

    public function processDelivery(int $deliveryId): void
    {
        \Model::begin();
        try {
            $row = \Model::fetch(
                "SELECT * FROM proma_connect_deliveries WHERE id=? FOR UPDATE",
                [$deliveryId]
            );
            if (!$row || in_array($row['status'], ['sent', 'delivered', 'dead'], true)) {
                \Model::commit();
                return;
            }
            \Model::execute(
                "UPDATE proma_connect_deliveries
                 SET status='processing',attempts=attempts+1,updated_at=NOW()
                 WHERE id=?",
                [$deliveryId]
            );
            \Model::commit();
            $payload = SecretCipher::decrypt((string) ($row['encrypted_payload'] ?? ''));
            $message = json_decode($payload, true);
            if (!is_array($message)) {
                throw new \RuntimeException('delivery_payload_invalid');
            }
            $settings = (new SettingsService())->all(true);
            $priority = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($settings['provider_priority'] ?? 'ippanel'))
            )));
            $providers = $row['provider_key'] === 'auto' ? $priority : [$row['provider_key']];
            $last = 'no_provider';
            $registry = new ProviderRegistry($settings);
            $requestedCapability = (string) ($message['capability'] ?? '');
            if ($requestedCapability === '' && ($message['kind'] ?? '') === 'otp') $requestedCapability = 'otp_message';
            foreach ($providers as $providerKey) {
                try {
                    $capability = $requestedCapability;
                    if ($capability === '') {
                        $candidate = (string) ($message['event_key'] ?? $message['template_key'] ?? '');
                        $capability = ProviderCapabilityCatalog::resolve($providerKey, $candidate);
                    }
                    if (!$registry->supports($providerKey, $capability)) {
                        $last = 'provider_capability_disabled';
                        continue;
                    }
                    $result = $registry->get($providerKey, $message + ['capability'=>$capability])->send(
                        $message + ['idempotency_key' => $row['idempotency_key']]
                    );
                    if (!empty($result['ok'])) {
                        \Model::execute(
                            "UPDATE proma_connect_deliveries
                             SET provider_key=?,provider_message_id=?,status='sent',
                                 encrypted_payload=NULL,payload_json=NULL,
                                 payload_purged_at=NOW(),updated_at=NOW(),
                                 last_error_code=NULL,last_error_message=NULL
                             WHERE id=?",
                            [$providerKey, $result['external_id'] ?? null, $deliveryId]
                        );
                        return;
                    }
                    $last = 'provider_rejected';
                } catch (\Throwable $e) {
                    $last = substr($e->getMessage(), 0, 450);
                }
            }
            throw new \RuntimeException($last);
        } catch (\Throwable $e) {
            if (\Model::db()->inTransaction()) {
                \Model::rollBack();
            }
            \Model::execute(
                "UPDATE proma_connect_deliveries
                 SET status=IF(attempts>=5,'dead','retry'),
                     encrypted_payload=IF(attempts>=5,NULL,encrypted_payload),
                     payload_purged_at=IF(attempts>=5,NOW(),payload_purged_at),
                     last_error_message=?,updated_at=NOW()
                 WHERE id=?",
                [substr($e->getMessage(), 0, 450), $deliveryId]
            );
            throw $e;
        }
    }
}
