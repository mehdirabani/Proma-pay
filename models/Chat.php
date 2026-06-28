<?php

class Chat extends Model
{
    public static function contactsFor($userId)
    {
        User::ensureProfileColumns();
        $user = User::find($userId);
        if (!$user) {
            return [];
        }
        if ($user['role'] === 'customer') {
            return self::fetchAll(
                "SELECT u.id, u.full_name, u.role, u.department,
                 (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
                 FROM users u
                 WHERE u.role IN ('admin','operator','lawyer') AND u.status = 'active'
                 AND COALESCE(u.department, CASE WHEN u.role = 'admin' THEN 'management' WHEN u.role = 'operator' THEN 'finance' WHEN u.role = 'lawyer' THEN 'legal' ELSE '' END) IN ('management','finance','legal')
                 ORDER BY FIELD(COALESCE(u.department, ''), 'management','finance','legal'), u.full_name",
                [(int) $userId]
            );
        }
        return self::fetchAll(
            "SELECT u.id, u.full_name, u.role, u.department,
             (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
             FROM users u
             WHERE u.id != ? AND u.status = 'active' AND u.role IN ('admin','operator','lawyer')
             ORDER BY u.role, u.full_name",
            [(int) $userId, (int) $userId]
        );
    }

    public static function allowed($senderId, $receiverId)
    {
        $sender = User::find($senderId);
        $receiver = User::find($receiverId);
        if (!$sender || !$receiver) {
            return false;
        }
        if ($sender['role'] === 'customer') {
            $department = $receiver['department'] ?: ($receiver['role'] === 'admin' ? 'management' : ($receiver['role'] === 'operator' ? 'finance' : ($receiver['role'] === 'lawyer' ? 'legal' : '')));
            return in_array($department, ['management', 'finance', 'legal'], true);
        }
        if ($receiver['role'] === 'customer') {
            return false;
        }
        return is_staff_role($sender['role']) && is_staff_role($receiver['role']);
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
