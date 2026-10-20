<?php

class Notification extends Model
{
    private static ?array $enabledTypes = null;

    public static function create($userId, $title, $body, $type, $url = null, $dedupeKey = null)
    {
        if (!$userId) {
            return;
        }
        $type = preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/i', (string) $type) ? (string) $type : 'system';
        $inAppEnabled = self::eventTypeEnabled($type);
        $pushEnabled = class_exists('WebPushService') && WebPushService::enabledForType($type);
        if (!$inAppEnabled && !$pushEnabled) return null;
        if (!$inAppEnabled) {
            WebPushService::enqueueDirectNotification((int) $userId, (string) $title, (string) $body, $type, $url, $dedupeKey);
            return null;
        }
        $dedupeKey = trim((string) $dedupeKey) ?: null;
        if ($dedupeKey) {
            $existing = self::fetch(
                'SELECT id FROM notifications WHERE user_id = ? AND dedupe_key = ? LIMIT 1',
                [(int) $userId, mb_substr($dedupeKey, 0, 190, 'UTF-8')]
            );
            if ($existing) {
                return (int) $existing['id'];
            }
        }
        try {
            self::execute(
                'INSERT INTO notifications (user_id, title, body, type, url, is_read, delivered_at, dedupe_key, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?, NOW())',
                [(int) $userId, $title, $body, $type, $url, $dedupeKey ? mb_substr($dedupeKey, 0, 190, 'UTF-8') : null]
            );
        } catch (PDOException $e) {
            if ($dedupeKey && (string) ($e->getCode() ?? '') === '23000') {
                $existing = self::fetch('SELECT id FROM notifications WHERE user_id = ? AND dedupe_key = ? LIMIT 1', [(int) $userId, mb_substr($dedupeKey, 0, 190, 'UTF-8')]);
                if ($existing) {
                    return (int) $existing['id'];
                }
            }
            throw $e;
        }
        $notificationId = (int) self::lastInsertId();
        if ($notificationId > 0 && class_exists('WebPushService')) {
            WebPushService::enqueueNotification($notificationId);
        }
        return $notificationId;
    }

    public static function createForUsers(array $userIds, $title, $body, $type, $url = null)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        foreach ($userIds as $userId) {
            self::create($userId, $title, $body, $type, $url);
        }
    }

    public static function createForLegalCase(array $case, $title, $body, $url = null)
    {
        if (!empty($case['customer_id'])) {
            self::create((int) $case['customer_id'], $title, $body, 'legal', url('notifications'));
        }
        if (!empty($case['lawyer_id'])) {
            self::create((int) $case['lawyer_id'], $title, $body, 'legal', $url ?: url('legal/show/' . (int) ($case['id'] ?? 0)));
        }
        foreach (User::all('admin', null, 'active') as $admin) {
            self::create((int) $admin['id'], $title, $body, 'legal', $url ?: url('legal/show/' . (int) ($case['id'] ?? 0)));
        }
    }

    public static function unreadCount($userId)
    {
        if (!$userId) {
            return 0;
        }
        $personal = self::fetch('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0 AND archived_at IS NULL AND deleted_at IS NULL', [(int) $userId]);
        return (int) ($personal['total'] ?? 0) + (class_exists('SystemAnnouncement') ? SystemAnnouncement::unreadCount((int) $userId) : 0);
    }

    public static function latest($userId, $limit = 6)
    {
        if (!$userId) {
            return [];
        }
        $limit = max(1, min(200, (int) $limit));
        $rows = self::fetchAll('SELECT id, title, body, type, url, is_read, read_at, created_at FROM notifications WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . $limit, [(int) $userId]);
        if (class_exists('SystemAnnouncement')) $rows = array_merge($rows, SystemAnnouncement::forUser((int) $userId, $limit));
        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as &$row) {
            $row['relative_time'] = relative_time($row['created_at'] ?? '');
        }
        unset($row);
        return $rows;
    }

    public static function forUser($userId, $limit = 100)
    {
        if (!$userId) {
            return [];
        }
        $limit = max(1, min(200, (int) $limit));
        $rows = self::fetchAll(
            "SELECT id, title, body, type, url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT {$limit}",
            [(int) $userId]
        );
        if (class_exists('SystemAnnouncement')) $rows = array_merge($rows, SystemAnnouncement::forUser((int) $userId, $limit));
        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as &$row) {
            $row['relative_time'] = relative_time($row['created_at'] ?? '');
        }
        unset($row);
        return $rows;
    }

    public static function latestId($userId)
    {
        if (!$userId) {
            return 0;
        }
        $row = self::fetch('SELECT MAX(id) AS latest_id FROM notifications WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL', [(int) $userId]);
        return (int) ($row['latest_id'] ?? 0) + (class_exists('SystemAnnouncement') ? SystemAnnouncement::latestId((int) $userId) : 0);
    }

    public static function feed($userId, $limit = 8)
    {
        if (!$userId) {
            return ['unread_count' => 0, 'latest_id' => 0, 'items' => []];
        }
        return [
            'unread_count' => self::unreadCount((int) $userId),
            'latest_id' => self::latestId((int) $userId),
            'items' => self::latest($userId, $limit),
        ];
    }

    public static function markAllRead($userId)
    {
        self::execute('UPDATE notifications SET is_read = 1, seen_at = COALESCE(seen_at, NOW()), read_at = COALESCE(read_at, NOW()) WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL', [(int) $userId]);
        if (class_exists('SystemAnnouncement')) SystemAnnouncement::markAllRead((int) $userId);
    }

    public static function markRead($id, $userId, $actioned = false)
    {
        if (class_exists('SystemAnnouncement') && (int) $id >= SystemAnnouncement::NOTIFICATION_ID_OFFSET) return SystemAnnouncement::markRead((int) $id, (int) $userId);
        $fields = 'is_read = 1, seen_at = COALESCE(seen_at, NOW()), read_at = COALESCE(read_at, NOW())';
        if ($actioned) {
            $fields .= ', actioned_at = COALESCE(actioned_at, NOW())';
        }
        return self::execute(
            'UPDATE notifications SET ' . $fields . ' WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [(int) $id, (int) $userId]
        );
    }

    public static function findForUser($id, $userId)
    {
        if (class_exists('SystemAnnouncement') && (int) $id >= SystemAnnouncement::NOTIFICATION_ID_OFFSET) return SystemAnnouncement::findForUser((int) $id, (int) $userId);
        return self::fetch(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1',
            [(int) $id, (int) $userId]
        );
    }

    public static function archive($id, $userId)
    {
        if (class_exists('SystemAnnouncement') && (int) $id >= SystemAnnouncement::NOTIFICATION_ID_OFFSET) return SystemAnnouncement::markRead((int) $id, (int) $userId);
        return self::execute(
            'UPDATE notifications SET archived_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [(int) $id, (int) $userId]
        );
    }

    public static function deleteForUser($id, $userId)
    {
        if (class_exists('SystemAnnouncement') && (int) $id >= SystemAnnouncement::NOTIFICATION_ID_OFFSET) return SystemAnnouncement::markRead((int) $id, (int) $userId);
        return self::execute(
            'UPDATE notifications SET deleted_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [(int) $id, (int) $userId]
        );
    }

    public static function deleteManyForUser(array $ids, $userId)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !$userId) {
            return 0;
        }
        $broadcastCount = 0;
        foreach ($ids as $id) {
            if (class_exists('SystemAnnouncement') && $id >= SystemAnnouncement::NOTIFICATION_ID_OFFSET) $broadcastCount += SystemAnnouncement::markRead($id, (int) $userId);
        }
        $ids = array_values(array_filter($ids, static function (int $id): bool {
            return !class_exists('SystemAnnouncement') || $id < SystemAnnouncement::NOTIFICATION_ID_OFFSET;
        }));
        if (!$ids) return $broadcastCount;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([(int) $userId], $ids);
        return $broadcastCount + self::execute(
            "UPDATE notifications
             SET deleted_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE user_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})",
            $params
        );
    }

    private static function eventTypeEnabled(string $type): bool
    {
        if (self::$enabledTypes === null) {
            $configured = json_decode((string) Settings::get('notification_event_types_json', ''), true);
            $defaults = ['payment', 'payment_receipt', 'legal', 'identity', 'profile', 'overdue', 'calendar', 'message', 'bot', 'repair', 'installment', 'due_soon', 'due_today', 'followup', 'system'];
            self::$enabledTypes = is_array($configured)
                ? array_fill_keys(array_values(array_filter($configured, 'is_string')), true)
                : array_fill_keys($defaults, true);
        }
        return isset(self::$enabledTypes[$type]);
    }
}
