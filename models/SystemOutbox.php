<?php

class SystemOutbox extends Model
{
    public static function enqueue($eventType, array $payload, $aggregateType = null, $aggregateId = null, $maxAttempts = 5)
    {
        self::execute(
            'INSERT INTO system_outbox
             (event_type, aggregate_type, aggregate_id, payload_json, status, attempts, max_attempts, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, NOW())',
            [
                trim((string) $eventType),
                $aggregateType ? trim((string) $aggregateType) : null,
                $aggregateId ? (int) $aggregateId : null,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pending',
                max(1, (int) $maxAttempts),
            ]
        );
        return (int) self::lastInsertId();
    }

    public static function safeEnqueuePluginHook($event, array $payload, $aggregateType = null, $aggregateId = null)
    {
        return self::safeEnqueue('plugin_hook', [
            'event' => trim((string) $event),
            'payload' => $payload,
        ], $aggregateType, $aggregateId);
    }

    public static function safeEnqueueNotification($userId, $title, $body, $type, $url = null, $aggregateType = null, $aggregateId = null)
    {
        if (!$userId) {
            return null;
        }
        return self::safeEnqueue('notification.create', [
            'user_id' => (int) $userId,
            'title' => (string) $title,
            'body' => (string) $body,
            'type' => (string) $type,
            'url' => $url,
        ], $aggregateType, $aggregateId);
    }

    public static function safeEnqueue($eventType, array $payload, $aggregateType = null, $aggregateId = null)
    {
        try {
            return self::enqueue($eventType, $payload, $aggregateType, $aggregateId);
        } catch (Throwable $e) {
            self::logFailure('outbox.enqueue:' . $eventType, $e);
            return null;
        }
    }

    public static function processPending($limit = 25, $aggregateType = null, $aggregateId = null)
    {
        try {
            $where = ["status IN ('pending','failed')", '(next_attempt_at IS NULL OR next_attempt_at <= NOW())', 'attempts < max_attempts'];
            $params = [];
            if ($aggregateType !== null) {
                $where[] = 'aggregate_type = ?';
                $params[] = trim((string) $aggregateType);
            }
            if ($aggregateId !== null) {
                $where[] = 'aggregate_id = ?';
                $params[] = (int) $aggregateId;
            }
            $rows = self::fetchAll(
                'SELECT * FROM system_outbox WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT ' . max(1, min(100, (int) $limit)),
                $params
            );
        } catch (Throwable $e) {
            self::logFailure('outbox.select', $e);
            return ['processed' => 0, 'failed' => 0];
        }

        $processed = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                self::processRow($row);
                $processed++;
            } catch (Throwable $e) {
                $failed++;
                self::markFailed((int) $row['id'], $e);
            }
        }
        return ['processed' => $processed, 'failed' => $failed];
    }

    protected static function processRow(array $row)
    {
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Outbox payload is not valid JSON.');
        }
        if ($row['event_type'] === 'plugin_hook') {
            if (class_exists('PluginManager')) {
                PluginManager::fire((string) ($payload['event'] ?? ''), is_array($payload['payload'] ?? null) ? $payload['payload'] : [], true);
            }
        } elseif ($row['event_type'] === 'notification.create') {
            if (class_exists('Notification')) {
                Notification::create(
                    (int) ($payload['user_id'] ?? 0),
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['body'] ?? ''),
                    (string) ($payload['type'] ?? 'system'),
                    $payload['url'] ?? null
                );
            }
        } else {
            throw new RuntimeException('Unknown outbox event type: ' . (string) $row['event_type']);
        }
        self::execute("UPDATE system_outbox SET status = 'processed', processed_at = NOW(), updated_at = NOW(), last_error = NULL WHERE id = ?", [(int) $row['id']]);
    }

    protected static function markFailed($id, Throwable $e)
    {
        try {
            $row = self::fetch('SELECT attempts, max_attempts FROM system_outbox WHERE id = ? LIMIT 1', [(int) $id]);
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? 5));
            $status = $attempts >= $maxAttempts ? 'dead' : 'failed';
            self::execute(
                'UPDATE system_outbox
                 SET status = ?, attempts = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), updated_at = NOW()
                 WHERE id = ?',
                [$status, $attempts, substr($e->getMessage(), 0, 2000), min(60, max(1, $attempts * 5)), (int) $id]
            );
        } catch (Throwable $inner) {
            self::logFailure('outbox.failed', $inner);
        }
        self::logFailure('outbox.process', $e);
    }

    protected static function logFailure($event, Throwable $e)
    {
        try {
            if (class_exists('PluginRegistry')) {
                PluginRegistry::logRuntimeError($event, $e);
            }
        } catch (Throwable $ignored) {
        }
    }
}
