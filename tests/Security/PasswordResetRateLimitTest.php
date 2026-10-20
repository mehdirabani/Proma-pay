<?php

declare(strict_types=1);

class Model
{
    public static $rows = [];
    public static $now = 1_800_000_000;

    public static function fetch($sql, array $params = [])
    {
        if (strpos($sql, 'FROM auth_login_attempts') === false) {
            return null;
        }
        $key = $params[0] . ':' . $params[1];
        $row = self::$rows[$key] ?? null;
        if (!$row) {
            return null;
        }
        if (strpos($sql, 'SELECT failed_count') !== false) {
            return $row;
        }
            return ['is_locked' => $row['locked_until'] !== null && strtotime($row['locked_until']) > self::$now ? 1 : 0];
    }

    public static function execute($sql, array $params = [])
    {
        if (strpos($sql, 'INSERT INTO auth_login_attempts') === false) {
            return 0;
        }
        [$scopeType, $scopeHash] = $params;
        $key = $scopeType . ':' . $scopeHash;
        $row = self::$rows[$key] ?? [
            'failed_count' => 0,
            'last_failed_at' => self::$now,
            'locked_until' => null,
        ];
        if ($row['locked_until'] !== null && strtotime($row['locked_until']) > self::$now) {
            return 1;
        }
        if ($row['last_failed_at'] < self::$now - 900) {
            $row['failed_count'] = 1;
        } else {
            $row['failed_count']++;
        }
        $row['last_failed_at'] = self::$now;
        $row['locked_until'] = $row['failed_count'] >= 20 ? date('Y-m-d H:i:s', self::$now + 900) : null;
        self::$rows[$key] = $row;
        return 1;
    }
}

require_once dirname(__DIR__, 2) . '/models/PasswordReset.php';

for ($attempt = 1; $attempt < 20; $attempt++) {
    if (PasswordReset::recordRequestAttempt('192.0.2.10')) {
        throw new RuntimeException('The IP reset throttle blocked before the configured threshold.');
    }
}
if (!PasswordReset::recordRequestAttempt('192.0.2.10')) {
    throw new RuntimeException('The IP reset throttle did not block at its configured threshold.');
}
if (!PasswordReset::recordRequestAttempt('192.0.2.10')) {
    throw new RuntimeException('The IP reset throttle did not retain its lock.');
}
if (PasswordReset::recordRequestAttempt('192.0.2.11')) {
    throw new RuntimeException('A different IP should have an independent throttle scope.');
}

echo "PASSWORD_RESET_RATE_LIMIT_OK\n";
