<?php

/** Daily, bounded installment reminders and operational escalation tasks. */
final class InstallmentMilestoneAutomation extends Model
{
    private const STAGES = [
        'due_in_1_day' => [
            'days_offset' => 1,
            'target' => 'customer', 'type' => 'due_soon',
            'title' => 'فردا سررسید قسط است',
            'body' => 'یک روز تا سررسید قسط %s قرارداد %s باقی مانده است.',
            'url' => 'installments/panel',
        ],
        'due_in_3_days' => [
            'days_offset' => 3,
            'target' => 'customer', 'type' => 'due_soon',
            'title' => 'یادآوری سررسید قسط',
            'body' => 'سه روز تا سررسید قسط %s قرارداد %s باقی مانده است.',
            'url' => 'installments/panel',
        ],
        'due_today' => [
            'days_offset' => 0,
            'target' => 'customer', 'type' => 'due_today',
            'title' => 'سررسید قسط امروز است',
            'body' => 'قسط %s قرارداد %s امروز سررسید می‌شود.',
            'url' => 'installments/panel',
        ],
        'overdue_5_days' => [
            'days_offset' => -5,
            'target' => 'customer', 'type' => 'overdue',
            'title' => 'هشدار دیرکرد قسط',
            'body' => 'قسط %s قرارداد %s بیش از پنج روز از سررسید گذشته و نیازمند بررسی جریمه دیرکرد است.',
            'url' => 'installments/panel',
        ],
        'overdue_15_days' => [
            'days_offset' => -15,
            'target' => 'operator', 'type' => 'followup',
            'title' => 'وظیفه پیگیری قسط معوق',
            'body' => 'قسط %s قرارداد %s پانزده روز معوق است؛ با مشتری تماس بگیرید و نتیجه را ثبت کنید.',
            'url' => 'overdue',
        ],
        'overdue_30_days' => [
            'days_offset' => -30,
            'target' => 'legal', 'type' => 'legal',
            'title' => 'بررسی اولیه حقوقی قسط معوق',
            'body' => 'قسط %s قرارداد %s سی روز معوق است؛ شرایط قرارداد را برای ارزیابی حقوقی بررسی کنید.',
            'url' => 'legal/eligible',
        ],
    ];

    public static function run(int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $result = ['checked' => 0, 'created' => 0, 'failed' => 0, 'by_stage' => []];
        foreach (self::STAGES as $stage => $config) {
            $offset = (int) $config['days_offset'];
            $stageDate = (new DateTimeImmutable(date('Y-m-d')))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
            $rows = self::candidates($stage, $offset < 0 ? 'i.due_date <= ?' : 'i.due_date = ?', $stageDate, $limit);
            $result['checked'] += count($rows);
            $created = 0;
            foreach ($rows as $row) {
                try {
                    if (self::dispatch($stage, $config, $row)) $created++;
                } catch (\Throwable $e) {
                    $result['failed']++;
                    if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('customer_automation.' . $stage, $e);
                }
            }
            $result['created'] += $created;
            $result['by_stage'][$stage] = ['checked' => count($rows), 'created' => $created];
        }
        return $result;
    }

    private static function candidates(string $stage, string $dateCondition, string $stageDate, int $limit): array
    {
        return self::fetchAll(
            "SELECT i.id AS installment_id, i.installment_number, i.due_date, i.status AS installment_status,
                    c.id AS contract_id, c.contract_number, c.customer_id, c.assigned_operator_id,
                    u.full_name AS customer_name, op.id AS operator_id
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             LEFT JOIN users op ON op.id = c.assigned_operator_id AND op.role = 'operator' AND op.status = 'active'
             WHERE c.status NOT IN ('cancelled','deleted','completed','closed')
               AND i.status NOT IN ('paid','cancelled')
               AND u.status = 'active'
               AND {$dateCondition}
               AND NOT EXISTS (
                   SELECT 1 FROM customer_automation_events a
                   WHERE a.event_key = CONCAT('installment:', i.id, ':', ?)
               )
             ORDER BY i.due_date ASC, i.id ASC
             LIMIT {$limit}",
            [$stageDate, $stage]
        );
    }

    private static function dispatch(string $stage, array $config, array $row): bool
    {
        $customerId = (int) $row['customer_id'];
        $contractId = (int) $row['contract_id'];
        $installmentId = (int) $row['installment_id'];
        $recipientId = self::recipient($config['target'], $row);
        if ($recipientId <= 0) return false;

        $key = 'installment:' . $installmentId . ':' . $stage;
        $number = to_persian_digits((string) $row['installment_number']);
        $contractNumber = (string) $row['contract_number'];
        $title = $config['title'];
        $body = sprintf($config['body'], $number, $contractNumber);
        $eventId = null;

        self::begin();
        try {
            $inserted = self::execute(
                'INSERT IGNORE INTO customer_automation_events
                 (event_key, event_type, customer_id, contract_id, installment_id, payload_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$key, $stage, $customerId, $contractId, $installmentId, json_encode(['title' => $title, 'description' => $body, 'route' => $config['url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
            );
            if ((int) $inserted !== 1) {
                self::commit();
                return false;
            }

            if (in_array($config['target'], ['operator', 'legal'], true)) {
                $eventType = $config['target'] === 'legal' ? 'legal' : 'followup';
                self::execute(
                    'INSERT INTO events (user_id, assigned_user_id, created_by, title, event_date, event_type, priority, status, description, color, created_at)
                     VALUES (?, ?, NULL, ?, ?, ?, ?, \'scheduled\', ?, ?, NOW())',
                    [$recipientId, $recipientId, $title . ' · ' . $contractNumber, date('Y-m-d'), $eventType, $config['target'] === 'legal' ? 'high' : 'urgent', 'customer:' . $customerId . ' installment:' . $installmentId . ' · ' . $body, $config['target'] === 'legal' ? 'danger' : 'warning']
                );
                $eventId = (int) self::lastInsertId();
                self::execute('UPDATE customer_automation_events SET event_id = ? WHERE event_key = ?', [$eventId, $key]);
            }

            SystemOutbox::enqueue('notification.create', [
                'user_id' => $recipientId,
                'title' => $title,
                'body' => $body,
                'type' => $config['type'],
                'url' => url($config['url']),
            ], 'installment', $installmentId);
            self::commit();
            return true;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    private static function recipient(string $target, array $row): int
    {
        if ($target === 'customer') return (int) $row['customer_id'];
        if ($target === 'operator' && (int) ($row['operator_id'] ?? 0) > 0) return (int) $row['operator_id'];
        // The current legal eligibility queue is administrator-only, so assign
        // the review task to an admin who can open and route it to legal staff.
        $recipient = self::fetch("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        return (int) ($recipient['id'] ?? 0);
    }
}
