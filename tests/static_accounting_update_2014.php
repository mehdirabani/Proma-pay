<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$model = (string) file_get_contents($root . '/core/Model.php');
foreach (['public static function fetch(', 'public static function fetchAll(', 'public static function execute('] as $method) {
    $position = strpos($model, $method);
    $assert($position !== false, 'Model method missing: ' . $method);
    $body = substr($model, $position, 420);
    $assert(strpos($body, 'finally') !== false, 'Model helper lacks finally block: ' . $method);
    $assert(strpos($body, 'closeCursor()') !== false, 'Model helper does not close cursor: ' . $method);
}

$backup = (string) file_get_contents($root . '/helpers/BackupService.php');
$reset = (string) file_get_contents($root . '/helpers/SystemResetService.php');
$scriptUpdate = (string) file_get_contents($root . '/helpers/ScriptUpdateService.php');
$assert(substr_count($backup, 'closeCursor()') >= 2, 'BackupService direct PDO queries must close cursors.');
$assert(strpos($reset, 'closeCursor()') !== false, 'SystemResetService direct PDO query must close cursor.');
$assert(strpos($scriptUpdate, 'finally') !== false && strpos($scriptUpdate, 'closeCursor()') !== false, 'ScriptUpdateService must close migration statement cursors in finally.');

$pluginsController = (string) file_get_contents($root . '/controllers/PluginsController.php');
$pluginManager = (string) file_get_contents($root . '/core/PluginManager.php');
$pluginRegistry = (string) file_get_contents($root . '/models/PluginRegistry.php');
$pluginStatus = (string) file_get_contents($root . '/core/PluginStatus.php');
$pluginsView = (string) file_get_contents($root . '/views/plugins/index.php');
$formsCss = (string) file_get_contents($root . '/assets/css/components/forms.css');

$assert(strpos($pluginsController, 'flashPluginError') !== false, 'Plugin controller safe error helper is missing.');
$assert(strpos($pluginsController, 'ErrorHandler::log') !== false, 'Plugin controller must log technical update failures.');
$assert(strpos($pluginsController, '$e->getMessage()') === false, 'Plugin controller still exposes raw exception messages.');
$assert(strpos($pluginManager, "ErrorHandler::log('plugin_health:") !== false, 'Plugin health failures must be logged.');
$assert(strpos($pluginManager, "return ['ok' => false, 'message' => \$e->getMessage()]") === false, 'Plugin health still returns raw exception messages.');
$assert(strpos($pluginsView, 'آخرین خطای افزونه در گزارش امن سامانه ثبت شده است.') !== false, 'Plugin manager still renders raw last_error.');
$assert(strpos($formsCss, '.proma-plugin-warning__content') !== false, 'Plugin warning spacing component CSS is missing.');
$assert(strpos($formsCss, 'overflow-wrap: anywhere') !== false, 'Plugin warning must safely wrap mixed RTL/LTR text.');

$assert(strpos($pluginStatus, "const INSTALLING = 'installing'") !== false, 'Plugin lifecycle status INSTALLING is missing.');
$assert(strpos($pluginStatus, "const UPDATING = 'updating'") !== false, 'Plugin lifecycle status UPDATING is missing.');
$assert(strpos($pluginStatus, "const INSTALLATION_FAILED = 'installation_failed'") !== false, 'Plugin lifecycle status INSTALLATION_FAILED is missing.');
$assert(strpos($pluginStatus, "const MIGRATION_FAILED = 'migration_failed'") !== false, 'Plugin lifecycle status MIGRATION_FAILED is missing.');
$assert(strpos($pluginStatus, "const REPAIR_REQUIRED = 'repair_required'") !== false, 'Plugin lifecycle status REPAIR_REQUIRED is missing.');
$assert(strpos($pluginRegistry, '$status = PluginStatus::INSTALLED') !== false, 'PluginRegistry::upsert must accept an explicit lifecycle status.');
$assert(strpos($pluginRegistry, '$preferredStatus') !== false, 'Filesystem reconciliation must preserve lifecycle statuses.');
$assert(strpos($pluginManager, 'PluginStatus::INSTALLING') !== false, 'Plugin install must enter INSTALLING before migrations/provider install.');
$assert(strpos($pluginManager, 'PluginStatus::INSTALLATION_FAILED') !== false, 'Plugin install failure must not remain installed.');
$assert(strpos($pluginManager, 'PluginStatus::UPDATING') !== false, 'Plugin update must enter UPDATING before migrations/provider update.');
$assert(strpos($pluginManager, 'PluginStatus::MIGRATION_FAILED') !== false, 'Migration failures must be visible as MIGRATION_FAILED.');
$assert(strpos($pluginManager, 'assertMigrationsCurrent($manifest)') !== false, 'Activation/update must verify migrations before successful status.');
$assert(substr_count($pluginManager, 'assertProviderHealth($provider, $manifest)') >= 3, 'Install/activate/update must verify provider health before success.');
$assert(strpos($pluginManager, 'restoreFailedUpdate(') !== false, 'Plugin update failure must attempt rollback before requiring repair.');
$assert(strpos($pluginManager, 'PluginStatus::REPAIR_REQUIRED') !== false, 'Unrecoverable update failure must mark repair_required.');

echo "PROMA_ACCOUNTING_UPDATE_2014_STATIC_OK\n";
