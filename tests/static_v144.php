<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? ''), '1.4.4', '>='), 'Core version must be V1.4.4 or newer.');
$assert(($version['release_channel'] ?? '') === 'stable', 'V1.4.4 release channel must be stable.');

foreach ([
    'database/migrations/2026_07_23_release_v144.sql',
    'docs/releases/V1.4.4.md',
    'docs/qa/V1_4_4_TEST_RESULTS.md',
] as $requiredFile) {
    $assert(is_file($root . '/' . $requiredFile), 'Required V1.4.4 file is missing: ' . $requiredFile);
}

$auth = (string) file_get_contents($root . '/core/Auth.php');
$router = (string) file_get_contents($root . '/core/Router.php');
$functions = (string) file_get_contents($root . '/helpers/functions.php');
$assert(strpos($auth, 'ensureSessionWritable') !== false && strpos($auth, 'commitSessionWrite') !== false, 'Short-lived session write support is missing.');
$assert(strpos($router, 'Auth::releaseSessionLock()') !== false, 'Authenticated requests do not release the session lock early.');
$assert(strpos($functions, 'Auth::sessionWasReleased()') !== false, 'Flash writes are not compatible with released sessions.');

$telemetry = (string) file_get_contents($root . '/core/RequestTelemetry.php');
$assert(strpos($telemetry, 'PROMA_REQUEST_SAMPLE_RATE') !== false, 'Normal request telemetry is not sampled.');
$assert(strpos($telemetry, 'LOCK_EX | LOCK_NB') !== false, 'Telemetry file locking can still block request workers.');

$notification = (string) file_get_contents($root . '/models/Notification.php');
$feedStart = strpos($notification, 'public static function feed');
$feedEnd = strpos($notification, 'public static function markAllRead', $feedStart);
$feedSource = substr($notification, $feedStart, $feedEnd - $feedStart);
$assert(strpos($feedSource, 'UPDATE notifications') === false, 'Notification polling must remain read-only.');
$assert(substr_count($feedSource, 'self::') <= 2, 'Notification feed performs too many model operations.');

$appJs = (string) file_get_contents($root . '/assets/js/app.js');
foreach (['fetchWithDeadline', 'proma-poller-lease:', "leaseKey: 'notifications:'", 'navigator.onLine === false'] as $token) {
    $assert(strpos($appJs, $token) !== false, 'Adaptive cross-tab polling is missing token: ' . $token);
}

$controller = (string) file_get_contents($root . '/controllers/ContractsController.php');
$contractIndex = (string) file_get_contents($root . '/views/contracts/index.php');
$contractShow = (string) file_get_contents($root . '/views/contracts/show.php');
$assert(strpos($controller, 'public function retire') !== false, 'Neutral contract retirement endpoint is missing.');
foreach ([$contractIndex, $contractShow] as $view) {
    $assert(strpos($view, "url('contracts/retire/") !== false, 'Contract removal form does not use the low-false-positive endpoint.');
    $assert(strpos($view, "url('contracts/delete/") === false, 'Legacy sensitive delete endpoint remains in generated forms.');
}

$profileView = (string) file_get_contents($root . '/views/profile-reviews/index.php');
$healthView = (string) file_get_contents($root . '/views/system-health/index.php');
$healthController = (string) file_get_contents($root . '/controllers/SystemHealthController.php');
foreach ([$profileView, $healthView] as $view) {
    $assert(strpos($view, 'class="page-header') === false, 'A content page reuses the fixed template page-header class.');
    $assert(strpos($view, 'proma-section-header') !== false, 'Shared content header is missing.');
}
$assert(strpos($healthController, 'public function report') !== false && strpos($healthView, 'گزارش امن') !== false, 'Safe host diagnostic export is missing.');

$layoutCss = (string) file_get_contents($root . '/assets/css/components/layout.css');
$responsiveCss = (string) file_get_contents($root . '/assets/css/components/responsive.css');
$formsCss = (string) file_get_contents($root . '/assets/css/components/forms.css');
$assert(strpos($layoutCss, '.proma-section-header') !== false, 'Shared section header styling is missing.');
$assert(strpos($responsiveCss, '--proma-visual-height') !== false, 'Mobile modals do not follow the visual viewport.');
$assert(strpos($responsiveCss, 'grid-template-columns: minmax(0, 1fr) !important') !== false, 'Mobile modal fields do not collapse to one column.');
$assert(
    preg_match('/\.modal-body\.form-grid,\s*\.modal-body\.grid\s*\{[^}]*align-content:\s*start;[^}]*grid-auto-rows:\s*max-content;/s', $formsCss) === 1,
    'Modal form rows can shrink and overlap on short mobile viewports.'
);

$updater = (string) file_get_contents($root . '/helpers/ScriptUpdateService.php');
$backupService = (string) file_get_contents($root . '/helpers/BackupService.php');
$settingsView = (string) file_get_contents($root . '/views/settings/index.php');
$v143Migration = (string) file_get_contents($root . '/database/migrations/2026_07_22_release_v143.sql');
$assert(strpos($updater, 'packageCompatibility($manifest)') !== false, 'Update compatibility is not checked before installation.');
$assert(strpos($updater, 'نصب مجدد آن مجاز نیست') !== false, 'Same-version update rejection is missing.');
$assert(strpos($settingsView, "empty(\$package['is_installable'])") !== false, 'Non-upgrade packages can still expose an install action.');
$assert(!preg_match('/\bDELETE\s+FROM\b/i', $v143Migration), 'V1.4.3 migration still removes production audit history.');
foreach (['backups', 'cache', 'logs', 'releases', 'sessions', 'updates'] as $volatileDirectory) {
    $assert(strpos($backupService, "'" . $volatileDirectory . "'") !== false, 'Safety backups do not exclude volatile storage/' . $volatileDirectory . ' data.');
}
$assert(strpos($backupService, 'if (!$zip->close())') !== false, 'Backup ZIP finalization errors are ignored.');

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_23_release_v144.sql');
$assert(strpos($migration, 'idx_notification_active_user') !== false, 'Notification polling index is missing.');
$assert(strpos($migration, 'idx_message_reverse_pair') !== false, 'Reverse chat polling index is missing.');
$assert(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $migration), 'V1.4.4 migration contains destructive table operations.');

echo "STATIC_V144_OK\n";
