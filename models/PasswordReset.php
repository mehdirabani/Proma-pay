<?php

class PasswordReset extends Model
{
    protected static $schemaReady = false;
    const REQUEST_WINDOW_MINUTES = 15;
    const REQUEST_IP_THRESHOLD = 20;
    const USER_COOLDOWN_SECONDS = 60;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }

        SchemaGuard::requireColumns('password_resets', ['user_id', 'mobile', 'code_hash', 'attempts', 'expires_at', 'used_at', 'ip_address', 'user_agent', 'created_at']);

        self::$schemaReady = true;
    }

    public static function createForUser(array $user, $code)
    {
        self::ensureSchema();
        self::execute('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
        self::execute(
            'INSERT INTO password_resets (user_id, mobile, code_hash, expires_at, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, ?, NOW())',
            [
                (int) $user['id'],
                to_english_digits($user['mobile'] ?? ''),
                password_hash((string) $code, PASSWORD_DEFAULT),
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8') ?: null,
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8') ?: null,
            ]
        );
        return (int) self::lastInsertId();
    }

    /**
     * Count reset requests by source IP without retaining attacker-controlled identifiers.
     * The shared auth attempt table is already part of the installed schema.
     */
    public static function recordRequestAttempt($ipAddress)
    {
        $ipAddress = mb_substr(trim((string) $ipAddress) ?: 'unknown', 0, 45, 'UTF-8');
        $scopeType = 'reset_ip';
        $scopeHash = hash('sha256', 'password-reset-ip|' . $ipAddress);

        try {
            $existing = self::fetch(
                'SELECT failed_count, last_failed_at, (locked_until > NOW()) AS is_locked FROM auth_login_attempts WHERE scope_type = ? AND scope_hash = ? LIMIT 1',
                [$scopeType, $scopeHash]
            );
            if ($existing && (int) ($existing['is_locked'] ?? 0) === 1) {
                return true;
            }

            self::execute(
                'INSERT INTO auth_login_attempts
                 (scope_type, scope_hash, failed_count, first_failed_at, last_failed_at, locked_until, created_at, updated_at)
                 VALUES (?, ?, 1, NOW(), NOW(), NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                   locked_until = IF(locked_until IS NOT NULL AND locked_until > NOW(), locked_until,
                     IF(last_failed_at < DATE_SUB(NOW(), INTERVAL ' . self::REQUEST_WINDOW_MINUTES . ' MINUTE),
                       IF(1 >= ' . self::REQUEST_IP_THRESHOLD . ', DATE_ADD(NOW(), INTERVAL ' . self::REQUEST_WINDOW_MINUTES . ' MINUTE), NULL),
                       IF(failed_count + 1 >= ' . self::REQUEST_IP_THRESHOLD . ', DATE_ADD(NOW(), INTERVAL ' . self::REQUEST_WINDOW_MINUTES . ' MINUTE), NULL))),
                   failed_count = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL ' . self::REQUEST_WINDOW_MINUTES . ' MINUTE), 1, failed_count + 1),
                   first_failed_at = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL ' . self::REQUEST_WINDOW_MINUTES . ' MINUTE), NOW(), first_failed_at),
                   last_failed_at = NOW(),
                   updated_at = NOW()',
                [$scopeType, $scopeHash]
            );
            $current = self::fetch(
                'SELECT (locked_until > NOW()) AS is_locked FROM auth_login_attempts WHERE scope_type = ? AND scope_hash = ? LIMIT 1',
                [$scopeType, $scopeHash]
            );
            return $current && (int) ($current['is_locked'] ?? 0) === 1;
        } catch (Throwable $exception) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log('password_reset.rate_limit', $exception, 500);
            }
            // A limiter failure must not make the SMS endpoint unlimited.
            return true;
        }
    }

    public static function userOnCooldown($userId)
    {
        self::ensureSchema();
        return (bool) self::fetch(
            'SELECT id FROM password_resets
             WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . self::USER_COOLDOWN_SECONDS . ' SECOND)
             ORDER BY id DESC LIMIT 1',
            [(int) $userId]
        );
    }

    public static function cancel($id)
    {
        self::ensureSchema();
        self::execute('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [(int) $id]);
    }

    public static function verifyCode($id, $code)
    {
        self::ensureSchema();
        $row = self::fetch('SELECT * FROM password_resets WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$row || !self::isUsable($row)) {
            return ['ok' => false, 'message' => 'کد بازیابی معتبر نیست یا منقضی شده است.'];
        }
        if ((int) $row['attempts'] >= 5) {
            return ['ok' => false, 'message' => 'تعداد تلاش‌ها بیش از حد مجاز است. دوباره درخواست کد کنید.'];
        }
        if (!password_verify((string) $code, $row['code_hash'])) {
            self::execute('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?', [(int) $id]);
            return ['ok' => false, 'message' => 'کد وارد شده درست نیست.'];
        }
        return ['ok' => true, 'reset' => $row];
    }

    public static function complete($id, $password)
    {
        self::ensureSchema();
        $row = self::fetch('SELECT * FROM password_resets WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$row || !self::isUsable($row)) {
            return false;
        }
        $user = User::find((int) $row['user_id']);
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'customer') {
            $password = User::customerPasswordFromMobile($user['mobile'] ?? '');
            if ($password === '') {
                return false;
            }
        }
        User::setPassword((int) $row['user_id'], $password);
        self::execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $id]);
        return true;
    }

    public static function userForReset($id)
    {
        self::ensureSchema();
        $row = self::fetch('SELECT user_id, expires_at, used_at FROM password_resets WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$row || !self::isUsable($row)) {
            return null;
        }
        return User::find((int) $row['user_id']);
    }

    public static function cleanup()
    {
        self::ensureSchema();
        self::execute('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR used_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    protected static function isUsable(array $row)
    {
        return empty($row['used_at']) && strtotime((string) $row['expires_at']) >= time();
    }
}
