<?php

class Chat extends Model
{
    public const BOT_USERNAME = 'proma_notice_bot';
    public const BOT_NAME = 'اطلاع رسان رسمی پروما';
    public const CHANNEL_NAME = 'کانال رسمی پروما';

    public static function ensureSchema()
    {
        ChatAttachment::ensureSchema();
        self::execute(
            "CREATE TABLE IF NOT EXISTS chat_channels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                type VARCHAR(40) NOT NULL DEFAULT 'public',
                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_chat_channels_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        foreach ([
            'channel_id' => 'BIGINT UNSIGNED NULL',
            'target_unit' => 'VARCHAR(80) NULL',
            'is_system' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE messages ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        try {
            self::execute('ALTER TABLE messages MODIFY receiver_id BIGINT UNSIGNED NULL');
        } catch (Throwable $e) {
            try {
                self::execute('ALTER TABLE messages DROP FOREIGN KEY fk_message_receiver');
                self::execute('ALTER TABLE messages MODIFY receiver_id BIGINT UNSIGNED NULL');
                self::execute('ALTER TABLE messages ADD CONSTRAINT fk_message_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE');
            } catch (Throwable $ignored) {
            }
        }
        try {
            self::execute('ALTER TABLE messages ADD INDEX idx_message_channel (channel_id, id)');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE messages ADD CONSTRAINT fk_message_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
        }

        self::ensureNotificationBot();

        self::execute(
            "INSERT IGNORE INTO chat_channels (title, slug, type, is_pinned, is_system, created_at)
             VALUES (?, 'public-announcements', 'public', 1, 1, NOW())",
            [self::channelName()]
        );
        self::execute("UPDATE chat_channels SET title = ? WHERE slug = 'public-announcements'", [self::channelName()]);
        self::seedSystemWelcome();
    }

    public static function contactsFor($userId)
    {
        self::ensureSchema();
        $user = User::find($userId);
        if (!$user) {
            return [];
        }
        $contacts = self::channelsFor($user);
        $botContact = self::botContact((int) $userId);
        if ($botContact) {
            $contacts[] = $botContact;
        }
        if ($user['role'] === 'customer') {
            return array_merge($contacts, self::customerUnits((int) $userId));
        }
        return array_merge($contacts, self::fetchAll(
            "SELECT u.id, u.full_name, u.role,
             NULL AS kind, NULL AS slug, NULL AS target_unit,
             (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
             FROM users u
             WHERE u.id != ? AND u.status = 'active'
             AND (u.username IS NULL OR u.username != ?)
             AND (u.role != 'customer' OR ? IN ('admin','operator','lawyer'))
             ORDER BY u.role, u.full_name",
            [(int) $userId, (int) $userId, self::BOT_USERNAME, $user['role']]
        ));
    }

    public static function channelsFor(array $user)
    {
        self::ensureSchema();
        return array_map(function ($channel) {
            return [
                'id' => 'channel-' . $channel['id'],
                'channel_id' => (int) $channel['id'],
                'kind' => 'channel',
                'slug' => $channel['slug'],
                'full_name' => $channel['title'],
                'role' => 'channel',
                'department' => 'announcements',
                'target_unit' => self::channelName(),
                'unread_count' => 0,
                'is_pinned' => (int) $channel['is_pinned'],
                'is_verified' => 1,
            ];
        }, self::fetchAll("SELECT * FROM chat_channels WHERE type = 'public' ORDER BY is_pinned DESC, id ASC"));
    }

    public static function channelBySlug($slug)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM chat_channels WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function channelById($id)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM chat_channels WHERE id = ? LIMIT 1', [(int) $id]);
    }

    public static function canViewChannel($userId, array $channel)
    {
        return !empty($userId) && ($channel['type'] ?? '') === 'public';
    }

    public static function canSendToChannel($userId, array $channel)
    {
        $user = User::find((int) $userId);
        return $user && in_array($user['role'] ?? '', ['admin', 'operator', 'lawyer'], true);
    }

    public static function allowed($senderId, $receiverId)
    {
        $sender = User::find($senderId);
        $receiver = User::find($receiverId);
        if (!$sender || !$receiver) {
            return false;
        }
        if (self::isBotUser((int) $senderId) || self::isBotUser((int) $receiverId)) {
            return true;
        }
        if ($sender['role'] === 'customer') {
            return in_array((int) $receiverId, self::customerUnitReceiverIds(), true);
        }
        return !($sender['role'] === 'customer' && $receiver['role'] === 'customer');
    }

    public static function customerUnits($userId = null)
    {
        $unitMap = [
            ['key' => 'management', 'label' => 'واحد مدیریتی', 'department' => 'management'],
            ['key' => 'support', 'label' => 'پشتیبانی', 'department' => 'support'],
            ['key' => 'installments', 'label' => 'واحد اقساط', 'department' => 'installments'],
            ['key' => 'legal', 'label' => 'واحد حقوقی', 'department' => 'legal'],
        ];
        $items = [];
        $usedRepresentativeIds = [];
        foreach ($unitMap as $unit) {
            $rep = self::pickUnitRepresentative($unit['department'], $usedRepresentativeIds);
            if (!$rep) {
                continue;
            }
            $usedRepresentativeIds[] = (int) $rep['id'];
            $items[] = [
                'id' => (int) $rep['id'],
                'full_name' => $unit['label'],
                'role' => $rep['role'],
                'department' => $unit['key'],
                'unread_count' => $userId ? (int) self::fetch(
                    'SELECT COUNT(*) AS total FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0',
                    [(int) $rep['id'], (int) $userId]
                )['total'] : 0,
            ];
        }
        return $items;
    }

    public static function botName()
    {
        return self::BOT_NAME;
    }

    public static function channelName()
    {
        return self::CHANNEL_NAME;
    }

    public static function botContact($userId = null)
    {
        $botId = self::botSenderId();
        if (!$botId) {
            return null;
        }
        return [
            'id' => (int) $botId,
            'kind' => 'bot',
            'slug' => 'proma-notice-bot',
            'full_name' => self::botName(),
            'role' => 'bot',
            'department' => 'announcements',
            'target_unit' => self::botName(),
            'unread_count' => $userId ? (int) (self::fetch(
                'SELECT COUNT(*) AS total FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0',
                [(int) $botId, (int) $userId]
            )['total'] ?? 0) : 0,
            'is_pinned' => 1,
            'is_verified' => 1,
        ];
    }

    public static function isBotUser($userId)
    {
        if ((int) $userId <= 0) {
            return false;
        }
        $user = self::fetch('SELECT username FROM users WHERE id = ? LIMIT 1', [(int) $userId]);
        return $user && (string) ($user['username'] ?? '') === self::BOT_USERNAME;
    }

    protected static function customerUnitReceiverIds()
    {
        return array_values(array_unique(array_map('intval', array_column(self::customerUnits(), 'id'))));
    }

    public static function messages($userId, $contactId, $afterId = 0)
    {
        self::ensureSchema();
        return self::fetchAll(
            'SELECT m.*, CASE WHEN m.is_system = 1 THEN ? ELSE s.full_name END AS sender_name,
             a.id AS attachment_id, a.file_path AS attachment_path, a.file_type AS attachment_type,
             a.status AS attachment_status, a.deleted_at AS attachment_deleted_at
             FROM messages m
             JOIN users s ON s.id = m.sender_id
             LEFT JOIN chat_attachments a ON a.message_id = m.id
             WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
             AND m.id > ?
             ORDER BY m.id ASC',
            [
                self::botName(),
                (int) $userId,
                (int) $contactId,
                (int) $contactId,
                (int) $userId,
                (int) $afterId,
            ]
        );
    }

    public static function channelMessages($channelId, $afterId = 0)
    {
        self::ensureSchema();
        return self::fetchAll(
            'SELECT m.*, CASE WHEN m.is_system = 1 THEN ? ELSE COALESCE(s.full_name, ?) END AS sender_name,
             a.id AS attachment_id, a.file_path AS attachment_path, a.file_type AS attachment_type,
             a.status AS attachment_status, a.deleted_at AS attachment_deleted_at
             FROM messages m
             LEFT JOIN users s ON s.id = m.sender_id
             LEFT JOIN chat_attachments a ON a.message_id = m.id
             WHERE m.channel_id = ? AND m.id > ?
             ORDER BY m.id ASC',
            [self::botName(), self::botName(), (int) $channelId, (int) $afterId]
        );
    }

    public static function send($senderId, $receiverId, $body, $attachmentPath = null)
    {
        self::ensureSchema();
        if (self::isBotUser((int) $receiverId)) {
            throw new RuntimeException('ربات اطلاع‌رسانی فقط برای ارسال اعلان‌های رسمی سامانه است.');
        }
        if (!self::allowed($senderId, $receiverId)) {
            throw new RuntimeException('ارسال پیام به این مخاطب مجاز نیست.');
        }
        self::execute(
            'INSERT INTO messages (sender_id, receiver_id, body, is_read, target_unit, created_at) VALUES (?, ?, ?, 0, ?, NOW())',
            [(int) $senderId, (int) $receiverId, trim($body), self::targetUnitFor($receiverId)]
        );
        $messageId = (int) self::lastInsertId();
        if ($attachmentPath) {
            ChatAttachment::create($messageId, $attachmentPath, 'image');
        }
        Notification::create($receiverId, 'پیام جدید', 'یک پیام جدید دریافت کرده‌اید.', 'message', url('chat', ['contact' => $senderId]));
        return $messageId;
    }

    public static function sendToChannel($senderId, $channelId, $body, $attachmentPath = null, $isSystem = false)
    {
        self::ensureSchema();
        $channel = self::fetch('SELECT * FROM chat_channels WHERE id = ?', [(int) $channelId]);
        if (!$channel || (!$isSystem && !self::canSendToChannel($senderId, $channel))) {
            throw new RuntimeException('ارسال پیام به این کانال مجاز نیست.');
        }
        self::execute(
            'INSERT INTO messages (sender_id, receiver_id, channel_id, body, is_read, target_unit, is_system, created_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, NOW())',
            [(int) $senderId, null, (int) $channelId, trim($body), $channel['title'], $isSystem ? 1 : 0]
        );
        $messageId = (int) self::lastInsertId();
        if ($attachmentPath) {
            ChatAttachment::create($messageId, $attachmentPath, 'image');
        }
        return $messageId;
    }

    public static function systemAnnounce($body)
    {
        self::ensureSchema();
        $channel = self::fetch("SELECT id, title FROM chat_channels WHERE slug = 'public-announcements' LIMIT 1");
        $adminId = self::botSenderId();
        if (!$channel || !$adminId || trim((string) $body) === '') {
            return null;
        }
        return self::insertSystemMessage($channel, $adminId, trim((string) $body));
    }

    public static function botMessage($userId, $body, $url = null)
    {
        self::ensureSchema();
        $senderId = self::botSenderId();
        $user = User::find((int) $userId);
        if (!$senderId || !$user || trim((string) $body) === '') {
            return null;
        }
        self::execute(
            'INSERT INTO messages (sender_id, receiver_id, body, is_read, target_unit, is_system, created_at) VALUES (?, ?, ?, 0, ?, 1, NOW())',
            [$senderId, (int) $userId, trim((string) $body), self::botName()]
        );
        $messageId = (int) self::lastInsertId();
        Notification::create((int) $userId, self::botName(), trim((string) $body), 'bot', $url ?: url('chat', ['contact' => $senderId]));
        return $messageId;
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

    protected static function targetUnitFor($receiverId)
    {
        $receiver = User::find((int) $receiverId);
        if (!$receiver) {
            return null;
        }
        if (!empty($receiver['department'])) {
            return department_label($receiver['department']);
        }
        return role_label($receiver['role'] ?? '');
    }

    protected static function seedSystemWelcome()
    {
        $channel = self::fetch("SELECT id, title FROM chat_channels WHERE slug = 'public-announcements' LIMIT 1");
        $adminId = self::botSenderId();
        if (!$channel || !$adminId) {
            return;
        }
        $exists = self::fetch(
            'SELECT id FROM messages WHERE channel_id = ? AND is_system = 1 LIMIT 1',
            [(int) $channel['id']]
        );
        if ($exists) {
            return;
        }
        self::insertSystemMessage(
            $channel,
            $adminId,
            self::botName() . ' به کانال رسمی پروما خوش آمدید. اعلان‌های مهم سامانه در همین بخش منتشر می‌شود.'
        );
    }

    protected static function botSenderId()
    {
        $botId = self::ensureNotificationBot();
        if ($botId) {
            return $botId;
        }
        $admin = self::fetch("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        if ($admin) {
            return (int) $admin['id'];
        }
        $user = self::fetch("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        return $user ? (int) $user['id'] : 0;
    }

    protected static function pickUnitRepresentative($department, array $usedIds = [])
    {
        $department = trim((string) $department);
        if ($department === '') {
            return null;
        }
        $candidates = self::fetchAll(
            "SELECT id, role, department, is_department_manager
             FROM users
             WHERE status = 'active' AND department = ?
             AND (username IS NULL OR username != ?)
             ORDER BY is_department_manager DESC, id ASC",
            [$department, self::BOT_USERNAME]
        );
        foreach ($candidates as $candidate) {
            if (!in_array((int) $candidate['id'], $usedIds, true)) {
                return $candidate;
            }
        }
        return null;
    }

    protected static function insertSystemMessage(array $channel, $senderId, $body)
    {
        self::execute(
            'INSERT INTO messages (sender_id, receiver_id, channel_id, body, is_read, target_unit, is_system, created_at)
             VALUES (?, NULL, ?, ?, 1, ?, 1, NOW())',
            [
                (int) $senderId,
                (int) $channel['id'],
                $body,
                $channel['title'],
            ]
        );
        return (int) self::lastInsertId();
    }

    protected static function ensureNotificationBot()
    {
        try {
            User::ensureProfileColumns();
            $existing = self::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [self::BOT_USERNAME]);
            if ($existing) {
                self::execute(
                    'UPDATE users SET full_name = ?, role = ?, status = ?, department = ?, is_department_manager = 0, updated_at = NOW() WHERE id = ?',
                    [self::botName(), 'operator', 'active', 'announcements', (int) $existing['id']]
                );
                return (int) $existing['id'];
            }
            $password = bin2hex(random_bytes(24));
            self::execute(
                'INSERT INTO users
                 (role, username, full_name, national_id, mobile, email, password_hash, status, department, is_department_manager, avatar_key, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, NULL, NULL, ?, ?, ?, 0, ?, NOW(), NOW())',
                ['operator', self::BOT_USERNAME, self::botName(), password_hash($password, PASSWORD_DEFAULT), 'active', 'announcements', 'avatar-1']
            );
            return (int) self::lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
