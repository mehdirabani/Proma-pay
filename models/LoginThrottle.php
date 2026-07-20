<?php

class LoginThrottle extends Model
{
    const WINDOW_MINUTES = 15;
    const LOCK_MINUTES = 15;

    public static function inspect($identifier, $ipAddress)
    {
        try {
            $scopes = self::scopes($identifier, $ipAddress);
            $rows = self::rows($scopes);
            $lockedUntil = null;
            $failures = 0;
            foreach ($rows as $row) {
                $failures = max($failures, (int) ($row['failed_count'] ?? 0));
                if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
                    if ($lockedUntil === null || strtotime((string) $row['locked_until']) > strtotime($lockedUntil)) {
                        $lockedUntil = (string) $row['locked_until'];
                    }
                }
            }
            return ['blocked' => $lockedUntil !== null, 'locked_until' => $lockedUntil, 'failures' => $failures];
        } catch (Throwable $e) {
            self::logFailure('inspect', $e);
            return ['blocked' => false, 'locked_until' => null, 'failures' => 0];
        }
    }

    public static function recordFailure($identifier, $ipAddress)
    {
        try {
            $scopes = self::scopes($identifier, $ipAddress);
            foreach ($scopes as $scope) {
                self::execute(
                    "INSERT INTO auth_login_attempts
                     (scope_type, scope_hash, failed_count, first_failed_at, last_failed_at, created_at, updated_at)
                     VALUES (?, ?, 1, NOW(), NOW(), NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                       failed_count = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE), 1, failed_count + 1),
                       first_failed_at = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE), NOW(), first_failed_at),
                       last_failed_at = NOW(),
                       locked_until = IF(locked_until IS NOT NULL AND locked_until > NOW(), locked_until, NULL),
                       updated_at = NOW()",
                    [$scope['type'], $scope['hash']]
                );
                $row = self::fetch(
                    'SELECT id, failed_count, locked_until FROM auth_login_attempts WHERE scope_type = ? AND scope_hash = ? LIMIT 1',
                    [$scope['type'], $scope['hash']]
                );
                if ($row && (int) $row['failed_count'] >= self::threshold($scope['type']) && (empty($row['locked_until']) || strtotime((string) $row['locked_until']) <= time())) {
                    self::execute(
                        'UPDATE auth_login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL ' . self::LOCK_MINUTES . ' MINUTE), updated_at = NOW() WHERE id = ?',
                        [(int) $row['id']]
                    );
                }
            }
            return self::inspect($identifier, $ipAddress);
        } catch (Throwable $e) {
            self::logFailure('record', $e);
            return ['blocked' => false, 'locked_until' => null, 'failures' => 0];
        }
    }

    public static function clearSuccessful($identifier, $ipAddress)
    {
        try {
            foreach (self::scopes($identifier, $ipAddress) as $scope) {
                if ($scope['type'] === 'ip') {
                    continue;
                }
                self::execute('DELETE FROM auth_login_attempts WHERE scope_type = ? AND scope_hash = ?', [$scope['type'], $scope['hash']]);
            }
        } catch (Throwable $e) {
            self::logFailure('clear', $e);
        }
    }

    public static function progressiveDelay($failures)
    {
        $failures = max(0, min(6, (int) $failures));
        if ($failures <= 0) {
            return;
        }
        usleep(min(800000, 100000 * (2 ** ($failures - 1))));
    }

    protected static function scopes($identifier, $ipAddress)
    {
        $identifier = mb_strtolower(trim(to_english_digits((string) $identifier)), 'UTF-8');
        $ipAddress = trim((string) $ipAddress) ?: 'unknown';
        return [
            ['type' => 'identifier', 'hash' => hash('sha256', 'identifier|' . $identifier)],
            ['type' => 'ip', 'hash' => hash('sha256', 'ip|' . $ipAddress)],
            ['type' => 'combined', 'hash' => hash('sha256', 'combined|' . $identifier . '|' . $ipAddress)],
        ];
    }

    protected static function rows(array $scopes)
    {
        $conditions = [];
        $params = [];
        foreach ($scopes as $scope) {
            $conditions[] = '(scope_type = ? AND scope_hash = ?)';
            array_push($params, $scope['type'], $scope['hash']);
        }
        return self::fetchAll('SELECT * FROM auth_login_attempts WHERE ' . implode(' OR ', $conditions), $params);
    }

    protected static function threshold($scopeType)
    {
        return $scopeType === 'ip' ? 20 : 5;
    }

    protected static function logFailure($action, Throwable $exception)
    {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log('login_throttle.' . $action, $exception, 500);
        }
    }
}
