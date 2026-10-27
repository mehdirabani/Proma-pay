<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException('Missing file: ' . $path);
    }
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$htaccess = $read('.htaccess');
$auth = $read('core/Auth.php');
$dashboard = $read('controllers/DashboardController.php');
$version = require $root . '/config/version.php';
$settings = require $root . '/config/settings.php';
$manifest = json_decode($read('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$worker = $read('service-worker.js');

$assert((bool) preg_match('/RewriteRule\s+\^storage\/\(\?!uploads\(\?:\/\|\$\)\)/i', $htaccess),
    'HTTP access to storage outside the public upload tree is not denied.');
$assert(strpos($auth, "SELECT role, status, archived_at, password_hash FROM users WHERE id = ? LIMIT 1") !== false,
    'Protected requests do not reload current account status, archive state, role, and password state.');
$assert(strpos($auth, "(\$user['status'] ?? '') !== 'active'") !== false
    && strpos($auth, "(\$user['role'] ?? '') !== (string) (\$_SESSION['role'] ?? '')") !== false
    && strpos($auth, "hash_equals(hash('sha256', \$passwordHash), \$sessionFingerprint)") !== false,
    'Protected requests do not reject inactive accounts, stale session roles, or sessions predating a password change.');
$assert(strpos($auth, 'self::logout();') !== false, 'Invalid sessions are not destroyed.');
$assert(strpos($auth, "\$_SESSION['auth_password_fingerprint'] = hash('sha256'") !== false,
    'Successful login does not bind the session to the current password hash.');
$assert(strpos($htaccess, 'RewriteRule ^(?:config|core|controllers|models|helpers|services|views|database|cron|scripts|tools|tests|docs)') !== false
    && strpos($htaccess, 'RewriteRule ^bootstrap\\.php$') !== false,
    'HTTP access to server-side source and operational directories is not denied.');
$assert(strpos($htaccess, 'RewriteRule (^|/)\\.(?!well-known') !== false
    && strpos($htaccess, 'pnpm(-lock|-workspace)?\\.yaml') !== false,
    'HTTP access to VCS metadata, environment files, or package lockfiles is not denied.');
$cron = $read('cron/calendar_reminders.php');
$assert(strpos($cron, "if (PHP_SAPI !== 'cli')") !== false
    && strpos($cron, "if (PHP_SAPI !== 'cli')") < strpos($cron, "require __DIR__ . '/../bootstrap.php';"),
    'Calendar reminder job is not restricted to CLI before application bootstrap.');
$assert(strpos($dashboard, 'customerRanking(') === false,
    'Unused customer ranking query path remains in the dashboard controller.');
$applicationVersion = (string) ($version['application'] ?? '');
$assert((bool) preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/', (string) ($settings['asset_version'] ?? ''))
    && ($manifest['version'] ?? '') === 'v' . $applicationVersion
    && strpos($worker, 'proma-pay-v' . str_replace('.', '-', $applicationVersion) . '-') !== false,
    'Asset cache-bust version is invalid or application metadata is inconsistent.');

echo "STATIC_PHP_AUDIT_HARDENING_OK\n";
