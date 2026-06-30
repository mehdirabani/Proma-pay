<?php

class Chat extends Model
{
    public static function contactsFor($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }
        if ($user['role'] === 'customer') {
            return self::fetchAll(
                "SELECT u.id,
                 CASE u.role
                   WHEN 'admin' THEN 'واحد مدیریت'
                   WHEN 'operator' THEN 'مدیریت مالی و پیگیری اقساط'
                   WHEN 'lawyer' THEN 'بخش حقوقی و شکایت‌ها'
                   ELSE u.full_name
                 END AS full_name,
                 u.role,
                 (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
                 FROM users u
                 JOIN (
                   SELECT role, MIN(id) AS id FROM users
                   WHERE role IN ('admin','operator','lawyer') AND status = 'active'
                   GROUP BY role
                 ) unit ON unit.id = u.id
                 ORDER BY FIELD(u.role, 'admin', 'operator', 'lawyer')",
                [(int) $userId]
            );
        }
        return self::fetchAll(
            "SELECT u.id, u.full_name, u.role,
             (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
             FROM users u
             WHERE u.id != ? AND u.status = 'active'
             AND (u.role != 'customer' OR ? IN ('admin','operator','lawyer'))
             ORDER BY u.role, u.full_name",
            [(int) $userId, (int) $userId, $user['role']]
        );
    }

    public static function allowed($senderId, $receiverId)
    {
        $sender = User::find($senderId);
        $receiver = User::find($receiverId);
        if (!$sender || !$receiver) {
            return false;
        }
        return !($sender['role'] === 'customer' && $receiver['role'] === 'customer');
    }

    public static function messages($userId, $contactId, $afterId = 0)
    {
        return self::fetchAll(
            'SELECT m.*, s.full_name AS sender_name
             FROM messages m JOIN users s ON s.id = m.sender_id
             WHERE ((sender_id = :user_a AND receiver_id = :contact_a) OR (sender_id = :contact_b AND receiver_id = :user_b))
             AND m.id > :after_id
             ORDER BY m.id ASC',
            [
                'user_a' => (int) $userId,
                'contact_a' => (int) $contactId,
                'contact_b' => (int) $contactId,
                'user_b' => (int) $userId,
                'after_id' => (int) $afterId,
            ]
        );
    }

    public static function send($senderId, $receiverId, $body)
    {
        if (!self::allowed($senderId, $receiverId)) {
            throw new RuntimeException('ارسال پیام به این مخاطب مجاز نیست.');
        }
        self::execute(
            'INSERT INTO messages (sender_id, receiver_id, body, is_read, created_at) VALUES (?, ?, ?, 0, NOW())',
            [(int) $senderId, (int) $receiverId, trim($body)]
        );
        Notification::create($receiverId, 'پیام جدید', 'یک پیام جدید دریافت کرده‌اید.', 'message', url('chat', ['contact' => $senderId]));
        return (int) self::lastInsertId();
    }

    public static function markRead($userId, $contactId)
    {
        self::execute(
            'UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND is_read = 0',
            [(int) $userId, (int) $contactId]
        );
    }

    public static function unreadCount($userId)
    {
        return (int) self::fetch('SELECT COUNT(*) AS total FROM messages WHERE receiver_id = ? AND is_read = 0', [(int) $userId])['total'];
    }
}
