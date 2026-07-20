<?php

class ProfileRequest extends Model
{
    public static function fieldLabels()
    {
        return [
            'full_name' => 'نام و نام خانوادگی',
            'mobile' => 'شماره موبایل',
            'secondary_phone' => 'تلفن دوم',
            'email' => 'ایمیل',
            'address' => 'آدرس',
        ];
    }

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
        $user = User::find((int) $userId);
        if (!$user) {
            throw new InvalidArgumentException('حساب کاربری پیدا نشد.');
        }
        $changes = [];
        foreach (self::fieldLabels() as $field => $label) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$field]);
            if ($field === 'mobile' || $field === 'secondary_phone') {
                $value = preg_replace('/\D+/', '', to_english_digits($value));
            }
            if ($value !== trim((string) ($user[$field] ?? ''))) {
                $changes[$field] = $value;
            }
        }
        if (!$changes) {
            return false;
        }
        $encoded = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pending = self::fetch("SELECT id FROM profile_update_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [(int) $userId]);
        if ($pending) {
            self::execute(
                'UPDATE profile_update_requests SET payload_json = ?, reviewed_by = NULL, review_notes = NULL, created_at = NOW(), updated_at = NOW() WHERE id = ?',
                [$encoded, (int) $pending['id']]
            );
            return (int) $pending['id'];
        }
        self::execute(
            'INSERT INTO profile_update_requests (user_id, payload_json, status, created_at) VALUES (?, ?, ?, NOW())',
            [(int) $userId, $encoded, 'pending']
        );
        return (int) self::lastInsertId();
    }

    public static function pending()
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT pr.*, u.full_name, u.role, u.mobile,
                    u.full_name AS current_full_name, u.mobile AS current_mobile,
                    u.secondary_phone AS current_secondary_phone, u.email AS current_email,
                    u.address AS current_address
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
        $payload = array_intersect_key($payload, self::fieldLabels());
        self::begin();
        try {
            User::applyProfileData((int) $request['user_id'], $payload);
            self::execute('UPDATE profile_update_requests SET status = ?, reviewed_by = ?, updated_at = NOW() WHERE id = ?', ['approved', (int) $reviewerId, (int) $id]);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        try {
            Notification::create((int) $request['user_id'], 'اصلاح مشخصات تایید شد', 'درخواست اصلاح مشخصات شما تایید و روی حساب اعمال شد.', 'profile', url('profile'));
        } catch (Throwable $ignored) {
        }
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
        try {
            Notification::create((int) $request['user_id'], 'اصلاح مشخصات رد شد', 'درخواست اصلاح مشخصات شما تایید نشد.', 'profile', url('profile'));
        } catch (Throwable $ignored) {
        }
        return true;
    }
}
