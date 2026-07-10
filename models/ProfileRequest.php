<?php

class ProfileRequest extends Model
{
    public static function ensureSchema()
    {
        User::ensureProfileColumns();
        self::execute(
            "CREATE TABLE IF NOT EXISTS profile_update_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                payload_json LONGTEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                reviewed_by BIGINT UNSIGNED NULL,
                review_notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                KEY idx_profile_request_status (status),
                KEY idx_profile_request_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function createRequest($userId, array $payload)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO profile_update_requests (user_id, payload_json, status, created_at) VALUES (?, ?, ?, NOW())',
            [(int) $userId, json_encode($payload, JSON_UNESCAPED_UNICODE), 'pending']
        );
    }

    public static function pending()
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT pr.*, u.full_name, u.role, u.mobile
             FROM profile_update_requests pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.status = 'pending'
             ORDER BY pr.id DESC"
        );
    }

    public static function latestForUser($userId)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM profile_update_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $userId]);
    }

    public static function approve($id, $reviewerId)
    {
        self::ensureSchema();
        $request = self::fetch("SELECT * FROM profile_update_requests WHERE id = ? AND status = 'pending'", [(int) $id]);
        if (!$request) {
            return false;
        }
        $payload = json_decode($request['payload_json'], true) ?: [];
        User::applyProfileData((int) $request['user_id'], $payload);
        self::execute('UPDATE profile_update_requests SET status = ?, reviewed_by = ?, updated_at = NOW() WHERE id = ?', ['approved', (int) $reviewerId, (int) $id]);
        Notification::create((int) $request['user_id'], 'ویرایش پروفایل تایید شد', 'درخواست ویرایش پروفایل شما تایید شد.', 'profile', url('profile'));
        return true;
    }

    public static function reject($id, $reviewerId, $notes = '')
    {
        self::ensureSchema();
        $request = self::fetch("SELECT * FROM profile_update_requests WHERE id = ? AND status = 'pending'", [(int) $id]);
        if (!$request) {
            return false;
        }
        self::execute('UPDATE profile_update_requests SET status = ?, reviewed_by = ?, review_notes = ?, updated_at = NOW() WHERE id = ?', ['rejected', (int) $reviewerId, $notes, (int) $id]);
        Notification::create((int) $request['user_id'], 'ویرایش پروفایل رد شد', 'درخواست ویرایش پروفایل شما تایید نشد.', 'profile', url('profile'));
        return true;
    }
}
