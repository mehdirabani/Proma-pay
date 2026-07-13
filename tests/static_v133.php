<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/PluginManager.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(($version['application'] ?? '') === '1.3.3', 'Application version must be V1.3.3.');

$managerSource = (string) file_get_contents($root . '/core/PluginManager.php');
$registrySource = (string) file_get_contents($root . '/models/PluginRegistry.php');
$pluginController = (string) file_get_contents($root . '/controllers/PluginsController.php');
$backupController = (string) file_get_contents($root . '/controllers/BackupController.php');
$backupService = (string) file_get_contents($root . '/helpers/BackupService.php');
$pluginView = (string) file_get_contents($root . '/views/plugins/index.php');
$settingsView = (string) file_get_contents($root . '/views/settings/index.php');

foreach (['stageUpdate', 'stageExtractedUpdate', 'deleteFromHost', 'assertManagedPluginPath'] as $method) {
    $assert(strpos($managerSource, 'function ' . $method) !== false, 'Plugin manager method missing: ' . $method);
}
$assert(strpos($registrySource, 'markUpdateAvailable') !== false, 'Candidate update registry state is missing.');
$assert(strpos($registrySource, "PluginStatus::UPDATE_AVAILABLE") !== false, 'Update-available state is not preserved.');
$assert(strpos($pluginController, 'function updatePackage') !== false && strpos($pluginController, 'function deleteFromHost') !== false, 'Plugin maintenance endpoints are missing.');
$assert(strpos($backupController, 'function deleteLogs') !== false, 'Backup log deletion endpoint is missing.');
$assert(strpos($backupService, 'function deleteLogs') !== false && strpos($backupService, 'function clearLogs') !== false, 'Backup log deletion service is incomplete.');
foreach (['plugins-delete-host-form', 'stage-plugin-update-', 'apply-plugin-update-', 'plugins_delete_from_host'] as $needle) {
    $assert(strpos($pluginView, $needle) !== false, 'Plugin maintenance UI is missing: ' . $needle);
}
$assert(strpos($pluginView, 'onsubmit="return confirm') === false, 'Browser confirm remains in plugin maintenance UI.');
foreach (['backup-log-delete-form', 'backup_logs_delete', 'data-check-item="backup_log_ids"'] as $needle) {
    $assert(strpos($settingsView, $needle) !== false, 'Backup log UI is missing: ' . $needle);
}

$cacheRoot = $root . '/storage/cache';
if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0755, true) && !is_dir($cacheRoot)) {
    throw new RuntimeException('Cannot create test cache directory.');
}
$allowed = $cacheRoot . '/plugin-delete-safety-' . bin2hex(random_bytes(4));
mkdir($allowed, 0755, true);
file_put_contents($allowed . '/sample.txt', 'safe');
$reflection = new ReflectionMethod(PluginManager::class, 'removePluginFiles');
$reflection->setAccessible(true);
$reflection->invoke(PluginManager::instance(), $allowed);
$assert(!file_exists($allowed), 'Managed cache directory was not removed.');

$outside = $root . '/tmp/plugin-delete-refused-' . bin2hex(random_bytes(4));
if (!is_dir(dirname($outside))) {
    mkdir(dirname($outside), 0755, true);
}
mkdir($outside, 0755, true);
file_put_contents($outside . '/keep.txt', 'keep');
$refused = false;
try {
    $reflection->invoke(PluginManager::instance(), $outside);
} catch (RuntimeException $e) {
    $refused = true;
}
$assert($refused && is_file($outside . '/keep.txt'), 'Out-of-scope deletion was not refused.');
unlink($outside . '/keep.txt');
rmdir($outside);

echo "STATIC_V133_OK\n";
