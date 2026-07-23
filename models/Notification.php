<?php

class Notification extends Model
{
    public static function create($userId, $title, $body, $type, $url = null, $dedupeKey = null)
    {
        if (!$userId) {
            return;
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
        return (int) self::lastInsertId();
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
        return (int) self::fetch('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0 AND archived_at IS NULL AND deleted_at IS NULL', [(int) $userId])['total'];
    }

    public static function latest($userId, $limit = 6)
    {
        if (!$userId) {
            return [];
        }
        $rows = self::fetchAll('SELECT * FROM notifications WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . max(1, (int) $limit), [(int) $userId]);
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
        return (int) ($row['latest_id'] ?? 0);
    }

    public static function feed($userId, $limit = 8)
    {
        if (!$userId) {
            return ['unread_count' => 0, 'latest_id' => 0, 'items' => []];
        }
        $summary = self::fetch(
            'SELECT COUNT(CASE WHEN is_read = 0 THEN 1 END) AS unread_count, COALESCE(MAX(id), 0) AS latest_id
             FROM notifications
             WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL',
            [(int) $userId]
        ) ?: [];
        return [
            'unread_count' => (int) ($summary['unread_count'] ?? 0),
            'latest_id' => (int) ($summary['latest_id'] ?? 0),
            'items' => self::latest($userId, $limit),
        ];
    }

    public static function markAllRead($userId)
    {
        self::execute('UPDATE notifications SET is_read = 1, seen_at = COALESCE(seen_at, NOW()), read_at = COALESCE(read_at, NOW()) WHERE user_id = ? AND archived_at IS NULL AND deleted_at IS NULL', [(int) $userId]);
    }

    public static function markRead($id, $userId, $actioned = false)
    {
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
        return self::fetch(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1',
            [(int) $id, (int) $userId]
        );
    }

    public static function archive($id, $userId)
    {
        return self::execute(
            'UPDATE notifications SET archived_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [(int) $id, (int) $userId]
        );
    }

    public static function deleteForUser($id, $userId)
    {
        return self::execute(
            'UPDATE notifications SET deleted_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ? AND deleted_at IS NULL',
            [(int) $id, (int) $userId]
        );
    }
}
