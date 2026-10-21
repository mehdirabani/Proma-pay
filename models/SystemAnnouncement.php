<?php

/** Broadcast notices with a compact recipient/read-state table. */
final class SystemAnnouncement extends Model
{
    public const NOTIFICATION_ID_OFFSET = 4000000000000000;

    public static function create(string $title, string $body, array $roles, ?string $localRoute, int $actorId, ?string $expiresAt = null): int
    {
        $title = trim(strip_tags($title));
        $body = trim(strip_tags($body));
        $roles = array_values(array_unique(array_filter(array_map(static function ($role) {
            $role = strtolower(trim((string) $role));
            return preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/', $role) ? $role : null;
        }, $roles))));
        if (mb_strlen($title, 'UTF-8') < 3 || mb_strlen($title, 'UTF-8') > 190 || mb_strlen($body, 'UTF-8') < 3 || mb_strlen($body, 'UTF-8') > 10000 || $actorId <= 0) {
            throw new InvalidArgumentException('عنوان، متن اعلان یا شناسهٔ فرستنده معتبر نیست.');
        }
        $route = self::normalizeLocalRoute($localRoute);
        self::begin();
        try {
            self::execute(
                'INSERT INTO system_announcements (title, body, audience_roles_json, local_url, published_by, published_at, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())',
                [$title, $body, json_encode($roles, JSON_UNESCAPED_UNICODE), $route, $actorId, $expiresAt]
            );
            $id = (int) self::lastInsertId();
            $params = [];
            $where = "status = 'active' AND (username IS NULL OR username != 'proma_notice_bot')";
            if ($roles) {
                $where .= ' AND role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
                $params = $roles;
            }
            $users = self::fetchAll('SELECT id FROM users WHERE ' . $where . ' ORDER BY id', $params);
            foreach ($users as $user) {
                self::execute('INSERT INTO system_announcement_recipients (announcement_id, user_id, is_read, created_at) VALUES (?, ?, 0, NOW())', [$id, (int) $user['id']]);
            }
            if (class_exists('AuditLog')) AuditLog::record('notification', 'broadcast_published', 'system_announcement', $id, ['actor_user_id' => $actorId, 'new_values' => ['audience_roles' => $roles, 'recipient_count' => count($users), 'expires_at' => $expiresAt]]);
            self::commit();
            if (class_exists('WebPushService')) WebPushService::enqueueAnnouncement($id);
            return $id;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = self::fetchAll(
            'SELECT a.id AS announcement_id, a.title, a.body, a.local_url, a.published_at AS created_at, r.is_read, r.read_at
             FROM system_announcement_recipients r JOIN system_announcements a ON a.id = r.announcement_id
             WHERE r.user_id = ? AND (a.expires_at IS NULL OR a.expires_at > NOW())
             ORDER BY a.id DESC LIMIT ' . $limit,
            [$userId]
        );
        foreach ($rows as &$row) {
            $row['id'] = self::NOTIFICATION_ID_OFFSET + (int) $row['announcement_id'];
            $row['type'] = 'announcement';
            $row['url'] = !empty($row['local_url']) ? url((string) $row['local_url']) : null;
            $row['relative_time'] = relative_time($row['created_at'] ?? '');
        }
        unset($row);
        return $rows;
    }

    public static function unreadCount(int $userId): int
    {
        $row = self::fetch('SELECT COUNT(*) AS total FROM system_announcement_recipients r JOIN system_announcements a ON a.id=r.announcement_id WHERE r.user_id=? AND r.is_read=0 AND (a.expires_at IS NULL OR a.expires_at>NOW())', [$userId]);
        return (int) ($row['total'] ?? 0);
    }

    public static function latestId(int $userId): int
    {
        $row = self::fetch('SELECT COALESCE(MAX(announcement_id),0) AS latest_id FROM system_announcement_recipients WHERE user_id=?', [$userId]);
        return (int) ($row['latest_id'] ?? 0);
    }

    public static function findForUser(int $syntheticId, int $userId): ?array
    {
        $announcementId = $syntheticId - self::NOTIFICATION_ID_OFFSET;
        if ($announcementId <= 0) return null;
        $row = self::fetch(
            'SELECT a.id AS announcement_id,a.title,a.body,a.local_url,a.published_at AS created_at,r.is_read,r.read_at
             FROM system_announcement_recipients r JOIN system_announcements a ON a.id=r.announcement_id
             WHERE a.id=? AND r.user_id=? AND (a.expires_at IS NULL OR a.expires_at>NOW()) LIMIT 1',
            [$announcementId, $userId]
        );
        if (!$row) return null;
        $row['id'] = $syntheticId; $row['type'] = 'announcement';
        $row['url'] = !empty($row['local_url']) ? url((string) $row['local_url']) : null;
        return $row;
    }

    public static function markRead(int $syntheticId, int $userId): int
    {
        $announcementId = $syntheticId - self::NOTIFICATION_ID_OFFSET;
        if ($announcementId <= 0) return 0;
        return self::execute('UPDATE system_announcement_recipients SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE announcement_id=? AND user_id=?', [$announcementId, $userId]);
    }

    public static function markAllRead(int $userId): int
    {
        return self::execute('UPDATE system_announcement_recipients SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND is_read=0', [$userId]);
    }

    public static function recent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return self::fetchAll('SELECT a.*,u.full_name AS publisher_name,(SELECT COUNT(*) FROM system_announcement_recipients r WHERE r.announcement_id=a.id) AS recipient_count FROM system_announcements a LEFT JOIN users u ON u.id=a.published_by ORDER BY a.id DESC LIMIT ' . $limit);
    }

    private static function normalizeLocalRoute(?string $route): ?string
    {
        $route = trim((string) $route);
        if ($route === '') return null;
        $route = ltrim($route, '/');
        if (strlen($route) > 255 || !preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', $route)) {
            throw new InvalidArgumentException('پیوند اعلان باید یک مسیر داخلی سامانه باشد.');
        }
        return $route;
    }
}
