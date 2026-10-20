<?php

class SystemOutbox extends Model
{
    protected static $processing = false;

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
        $eventId = self::safeEnqueue('plugin_hook', [
            'event' => trim((string) $event),
            'payload' => $payload,
        ], $aggregateType, $aggregateId);
        if ($eventId && (strpos((string) $event, 'contract.') === 0
            || $event === 'payment.completed'
            || $event === 'installment.financial_state_changed')) {
            try {
                $customerId = (int) ($payload['customer_id'] ?? 0);
                if ($customerId <= 0 && !empty($payload['contract_id'])) {
                    $contract = self::fetch('SELECT customer_id FROM contracts WHERE id = ? LIMIT 1', [(int) $payload['contract_id']]);
                    $customerId = (int) ($contract['customer_id'] ?? 0);
                }
                self::safeEnqueueMedalEvaluation($customerId);
            } catch (Throwable $e) {
                self::logFailure('outbox.medal_enqueue', $e);
            }
        }
        return $eventId;
    }

    public static function safeEnqueueMedalEvaluation($customerId)
    {
        $customerId = (int) $customerId;
        return $customerId > 0
            ? self::safeEnqueue('medal.evaluate', ['customer_id' => $customerId], 'customer', $customerId)
            : null;
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
        // Outbox work must never run as part of a normal browser request. The
        // contract/payment paths enqueue atomically and a token-protected cron
        // or CLI worker drains bounded batches separately.
        if (!self::workerContext()) {
            return ['processed' => 0, 'failed' => 0, 'skipped' => 'not_worker_context'];
        }
        if (self::$processing) {
            return ['processed' => 0, 'failed' => 0, 'skipped' => 'already_processing'];
        }
        $lockName = null;
        $lockAcquired = false;
        try {
            if (self::db()->inTransaction()) {
                return ['processed' => 0, 'failed' => 0, 'skipped' => 'transaction_open'];
            }
            $database = self::fetch('SELECT DATABASE() AS name');
            $lockName = 'proma_outbox_' . substr(hash('sha256', (string) ($database['name'] ?? '')), 0, 32);
            $lock = self::fetch('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
            if ((int) ($lock['acquired'] ?? 0) !== 1) {
                return ['processed' => 0, 'failed' => 0, 'skipped' => 'worker_busy'];
            }
            $lockAcquired = true;
            self::$processing = true;
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
        } catch (Throwable $e) {
            self::logFailure('outbox.process_pending', $e);
            return ['processed' => 0, 'failed' => 0, 'skipped' => 'worker_error'];
        } finally {
            self::$processing = false;
            if ($lockAcquired) {
                try {
                    self::fetch('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (Throwable $e) {
                    self::logFailure('outbox.unlock', $e);
                }
            }
        }
    }

    protected static function workerContext()
    {
        return (PHP_SAPI === 'cli') || (defined('PROMA_OUTBOX_WORKER') && PROMA_OUTBOX_WORKER === true);
    }

    protected static function processRow(array $row)
    {
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Outbox payload is not valid JSON.');
        }
        if ($row['event_type'] === 'plugin_hook') {
            if (class_exists('PluginManager')) {
                $eventPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
                $eventPayload['outbox_event_id'] = (int) $row['id'];
                $eventName = (string) ($payload['event'] ?? '');
                if (class_exists('CustomerLoyalty')) {
                    CustomerLoyalty::processSystemEvent($eventName, $eventPayload);
                }
                PluginManager::fire($eventName, $eventPayload, true);
            } else {
                throw new RuntimeException('Plugin manager is unavailable.');
            }
        } elseif ($row['event_type'] === 'notification.create') {
            if (class_exists('Notification')) {
                Notification::create(
                    (int) ($payload['user_id'] ?? 0),
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['body'] ?? ''),
                    (string) ($payload['type'] ?? 'system'),
                    $payload['url'] ?? null,
                    'outbox:' . (int) $row['id']
                );
            } else {
                throw new RuntimeException('Notification service is unavailable.');
            }
        } elseif ($row['event_type'] === 'web_push.notification') {
            WebPushService::deliverNotification((int) ($payload['notification_id'] ?? 0));
        } elseif ($row['event_type'] === 'web_push.direct_notification') {
            WebPushService::deliverDirectNotification($payload);
        } elseif ($row['event_type'] === 'web_push.announcement_batch') {
            WebPushService::processAnnouncementBatch(
                (int) ($payload['announcement_id'] ?? 0),
                (int) ($payload['after_user_id'] ?? 0)
            );
        } elseif ($row['event_type'] === 'medal.evaluate') {
            $customerId = (int) ($payload['customer_id'] ?? 0);
            $customer = $customerId > 0 ? User::find($customerId) : null;
            if ($customer && ($customer['role'] ?? '') === 'customer') {
                CustomerLoyalty::reconcileCustomer($customerId);
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
                [$status, $attempts, substr($e->getMessage(), 0, 2000), min(60, 1 << min(6, $attempts - 1)), (int) $id]
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
            error_log('[PromaPay][outbox_fallback] ' . substr($event, 0, 120) . ': ' . substr($ignored->getMessage(), 0, 300));
        }
    }
}
