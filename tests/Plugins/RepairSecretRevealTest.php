<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string { return 'repair-secret-test-csrf'; }
}

require dirname(__DIR__, 2) . '/core/Auth.php';

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root . '/plugins/PromaRepair/src/Controllers/RepairController.php');
$detailStart = strpos($controller, 'public function detail(');
$revealStart = strpos($controller, 'public function revealPassword(');
$deleteStart = strpos($controller, 'public function deleteDevicePassword(');
if ($detailStart === false || $revealStart === false || $deleteStart === false || $detailStart >= $revealStart || $revealStart >= $deleteStart) {
    throw new RuntimeException('Repair detail/reveal controller actions could not be located.');
}
$detail = substr($controller, $detailStart, $revealStart - $detailStart);
$reveal = substr($controller, $revealStart, $deleteStart - $revealStart);
$expectedSafeRedirect = "\\redirect('plugin/repair/detail/' . \$id, \$secret ? ['open' => 'repair-device-password-modal'] : [])";
foreach ([
    [str_contains($detail, '\\Auth::ensureSessionWritable()'), 'Detail must reopen a writable session before consuming the reveal.'],
    [str_contains($detail, 'unset($_SESSION[\'repair_secret_reveals\']'), 'Detail must remove the secret before rendering.'],
    [str_contains($detail, '\\Auth::commitSessionWrite()'), 'Detail must commit the one-time consume.'],
    [str_contains($reveal, '\\Auth::ensureSessionWritable()') && str_contains($reveal, '\\Auth::commitSessionWrite()'), 'Reveal must write and commit under a writable session lock.'],
    [str_contains($reveal, 'device_password_viewed'), 'Device password viewing must keep its audit event.'],
    [str_contains($reveal, $expectedSafeRedirect) && !str_contains($reveal, '\\redirect(\'plugin/repair/detail/\' . $id, [\'secret\' =>'), 'The modal may be opened after redirect, but the revealed secret must never appear in the redirect URL.'],
] as [$condition, $message]) {
    if (!$condition) throw new RuntimeException($message);
}

$sessionDirectory = sys_get_temp_dir() . '/proma-repair-session-' . bin2hex(random_bytes(6));
if (!mkdir($sessionDirectory, 0700, true)) throw new RuntimeException('Could not create isolated session test directory.');
ini_set('session.save_path', $sessionDirectory);
$sessionId = 'repair' . bin2hex(random_bytes(12));
session_id($sessionId);
if (!session_start()) throw new RuntimeException('Could not start the first isolated session request.');
if (!Auth::releaseSessionLock() || session_status() === PHP_SESSION_ACTIVE) throw new RuntimeException('Core route must release the active session lock.');
if (!Auth::ensureSessionWritable()) throw new RuntimeException('Plugin route must reopen the session for a controlled write.');
$_SESSION['repair_secret_reveals'][37] = ['type' => 'pattern', 'value' => 'test-secret'];
if (!Auth::commitSessionWrite()) throw new RuntimeException('Plugin route must commit the secret reveal.');

session_id($sessionId);
if (!session_start()) throw new RuntimeException('Could not start the redirected detail request.');
$secret = $_SESSION['repair_secret_reveals'][37] ?? null;
unset($_SESSION['repair_secret_reveals'][37]);
if (!Auth::commitSessionWrite() || ($secret['value'] ?? null) !== 'test-secret') throw new RuntimeException('The redirected detail request must consume exactly the committed secret.');

session_id($sessionId);
if (!session_start()) throw new RuntimeException('Could not verify the consumed session.');
$stillPresent = isset($_SESSION['repair_secret_reveals'][37]);
session_write_close();
if ($stillPresent) throw new RuntimeException('A consumed reveal secret must not remain in the next request.');

$sessionFile = $sessionDirectory . '/sess_' . $sessionId;
if (is_file($sessionFile)) unlink($sessionFile);
rmdir($sessionDirectory);
echo "REPAIR_SECRET_REVEAL_SESSION_OK\n";
