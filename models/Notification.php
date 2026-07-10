<?php

class Notification extends Model
{
    public static function create($userId, $title, $body, $type, $url = null)
    {
        if (!$userId) {
            return;
        }
        self::execute(
            'INSERT INTO notifications (user_id, title, body, type, url, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())',
            [(int) $userId, $title, $body, $type, $url]
        );
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
        return (int) self::fetch('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $userId])['total'];
    }

    public static function latest($userId, $limit = 6)
    {
        if (!$userId) {
            return [];
        }
        $rows = self::fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, (int) $limit), [(int) $userId]);
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
             WHERE user_id = ?
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
        $row = self::fetch('SELECT MAX(id) AS latest_id FROM notifications WHERE user_id = ?', [(int) $userId]);
        return (int) ($row['latest_id'] ?? 0);
    }

    public static function feed($userId, $limit = 8)
    {
        return [
            'unread_count' => self::unreadCount($userId),
            'latest_id' => self::latestId($userId),
            'items' => self::latest($userId, $limit),
        ];
    }

    public static function markAllRead($userId)
    {
        self::execute('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?', [(int) $userId]);
    }
}
