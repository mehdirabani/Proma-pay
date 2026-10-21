<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sessionRoot = sys_get_temp_dir() . '/proma-session-v144-' . bin2hex(random_bytes(5));
if (!mkdir($sessionRoot, 0777, true) && !is_dir($sessionRoot)) {
    throw new RuntimeException('Temporary session directory could not be created.');
}
putenv('PROMA_SESSION_SAVE_PATH=' . $sessionRoot);
$_GET['route'] = 'auth/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require $root . '/bootstrap.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$_SESSION['user_id'] = 144;
$_SESSION['role'] = 'admin';
$token = Csrf::token();
$assert(Auth::releaseSessionLock(), 'Active session lock was not released.');
$assert(session_status() === PHP_SESSION_NONE, 'Session must be closed after release.');
$assert(Auth::id() === 144 && Auth::role() === 'admin', 'Released session snapshot is not readable.');

set_flash('success', 'session-v144');
$assert(session_status() === PHP_SESSION_NONE, 'Flash write must close the session immediately.');
$assert(Auth::ensureSessionWritable(), 'Released session could not be reopened.');
$assert(($_SESSION['_flash']['success'] ?? '') === 'session-v144', 'Flash value was not persisted after lock release.');
$assert(hash_equals($token, (string) ($_SESSION['_csrf_token'] ?? '')), 'CSRF token changed while reopening the session.');
Auth::commitSessionWrite();

foreach (glob($sessionRoot . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($sessionRoot);

echo "SESSION_V144_OK\n";
