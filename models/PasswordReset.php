<?php

class PasswordReset extends Model
{
    protected static $schemaReady = false;

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
        User::setPassword((int) $row['user_id'], $password);
        self::execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $id]);
        return true;
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
