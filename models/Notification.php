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
        return self::fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit, [(int) $userId]);
    }

    public static function markAllRead($userId)
    {
        self::execute('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?', [(int) $userId]);
    }
}
