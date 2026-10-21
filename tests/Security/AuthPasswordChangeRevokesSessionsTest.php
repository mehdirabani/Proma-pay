<?php

declare(strict_types=1);

final class AuthTestModel
{
    public static $user = [];

    public static function fetch($sql, array $params = [])
    {
        return self::$user;
    }

    public static function execute($sql, array $params = [])
    {
        return true;
    }
}

final class AuthTestUser
{
    public static $syncCalled = false;

    public static function customerPasswordFromMobile($mobile)
    {
        $digits = preg_replace('/\D+/', '', to_english_digits((string) $mobile));
        return strlen($digits) >= 4 ? substr($digits, -4) : '';
    }

    public static function syncCustomerLoginDefaults($id, array $user = [])
    {
        self::$syncCalled = true;
        return true;
    }
}

final class AuthTestPluginManager
{
    public static function roleAvailable($role)
    {
        return true;
    }

    public static function roleCanLogin($role, array $user = [])
    {
        return true;
    }
}

final class AuthTestErrorHandler
{
    public static function abort($status)
    {
        throw new RuntimeException('HTTP_ABORT', (int) $status);
    }
}

class_alias(AuthTestModel::class, 'Model');
class_alias(AuthTestUser::class, 'User');
class_alias(AuthTestPluginManager::class, 'PluginManager');
class_alias(AuthTestErrorHandler::class, 'ErrorHandler');
if (!function_exists('to_english_digits')) {
    function to_english_digits($value)
    {
        return (string) $value;
    }
}
require_once dirname(__DIR__, 2) . '/core/Auth.php';

$sessionDirectory = sys_get_temp_dir() . '/proma-auth-password-' . bin2hex(random_bytes(6));
if (!mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('Could not create isolated session directory.');
}
session_save_path($sessionDirectory);

$startSession = static function (array $session): void {
    session_id('promaqa' . bin2hex(random_bytes(12)));
    session_start();
    $_SESSION = $session;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    AuthTestModel::$user = ['role' => 'admin', 'status' => 'active', 'password_hash' => 'current-hash'];
    $currentFingerprint = hash('sha256', 'current-hash');
    $startSession([
        'user_id' => 7,
        'role' => 'admin',
        'auth_password_fingerprint' => $currentFingerprint,
    ]);
    Auth::requireLogin();
    $assert(Auth::check(), 'A session with the current password fingerprint should remain valid.');
    session_write_close();

    $startSession([
        'user_id' => 7,
        'role' => 'admin',
        'auth_password_fingerprint' => hash('sha256', 'previous-hash'),
    ]);
    $denied = false;
    try {
        Auth::requireLogin();
    } catch (RuntimeException $exception) {
        $denied = $exception->getCode() === 403;
    }
    $assert($denied && !Auth::check(), 'Changing the password must revoke an existing session.');

    $startSession(['user_id' => 7, 'role' => 'admin']);
    $legacyDenied = false;
    try {
        Auth::requireLogin();
    } catch (RuntimeException $exception) {
        $legacyDenied = $exception->getCode() === 403;
    }
    $assert($legacyDenied && !Auth::check(), 'Sessions without a password fingerprint must re-authenticate.');

    AuthTestModel::$user = [
        'id' => 8,
        'role' => 'customer',
        'status' => 'active',
        'mobile' => '09123456789',
        'password_hash' => password_hash('old-personal-password', PASSWORD_DEFAULT),
    ];
    $legacyPasswordHash = AuthTestModel::$user['password_hash'];
    $assert(Auth::unifiedLogin('1234567890', '6789'), 'Customer login must accept the registered mobile last four digits.');
    $assert(AuthTestUser::$syncCalled, 'Successful customer login must synchronize the username default.');
    $assert(hash_equals($legacyPasswordHash, AuthTestModel::$user['password_hash']), 'Customer login must not rewrite an existing password hash.');
    session_write_close();

    AuthTestUser::$syncCalled = false;
    $assert(!Auth::unifiedLogin('1234567890', 'old-personal-password'), 'A non-suffix customer password must not bypass the mobile PIN rule.');
    $assert(!AuthTestUser::$syncCalled, 'Invalid customer login must not modify the stored password hash.');
    $assert(!Auth::customerLogin('1234567890', '0000'), 'The legacy customer login entry point must reject an incorrect PIN.');

    echo "AUTH_PASSWORD_CHANGE_SESSION_REVOCATION_OK\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    foreach (glob($sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $sessionFile) {
        @unlink($sessionFile);
    }
    @rmdir($sessionDirectory);
}
