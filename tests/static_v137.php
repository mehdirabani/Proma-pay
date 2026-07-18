<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$requiredFiles = [
    'controllers/HealthController.php',
    'assets/css/design-system/tokens.css',
    'database/migrations/2026_07_18_contract_lifecycle_schema_repair_v137.sql',
    'static-errors/400.html',
    'static-errors/403.html',
    'static-errors/404.html',
    'static-errors/429.html',
    'static-errors/502.html',
    'static-errors/504.html',
];
foreach ($requiredFiles as $file) {
    $assert(is_file($root . '/' . $file), 'Missing V1.3.7 file: ' . $file);
}

$installer = (string) file_get_contents($root . '/installer.php');
$assert(strpos($installer, "header('Location: install.php'") !== false, 'installer.php must delegate to install.php.');
$assert(strpos($installer, 'session_start') === false, 'installer.php must not create an installation session.');
$assert(stripos($installer, 'ZipArchive') === false && stripos($installer, 'installer_import_dump') === false, 'installer.php must not contain easy-install behavior.');

$router = (string) file_get_contents($root . '/core/Router.php');
$healthPosition = strpos($router, "health/live");
$pluginPosition = strpos($router, 'PluginManager::boot()');
$assert($healthPosition !== false && $pluginPosition !== false && $healthPosition < $pluginPosition, 'Health routes must bypass plugin boot.');

$bootstrap = (string) file_get_contents($root . '/bootstrap.php');
$assert(strpos($bootstrap, "['health/live', 'health/ready']") !== false, 'Health routes must bypass session startup.');

$health = (string) file_get_contents($root . '/controllers/HealthController.php');
$assert(strpos($health, 'databaseReady') !== false && strpos($health, 'storageReady') !== false, 'Readiness dependencies are incomplete.');
$assert(strpos($health, 'http_response_code($ready ? 200 : 503)') !== false, 'Readiness must return 503 when unavailable.');

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_18_contract_lifecycle_schema_repair_v137.sql');
foreach (['cancelled_at', 'previous_status', 'cancellation_metadata_json', 'is_corrected', 'payment_corrections', 'contract_deletion_archives'] as $needle) {
    $assert(strpos($migration, $needle) !== false, 'Lifecycle repair migration is missing ' . $needle . '.');
}
$assert(!preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $migration), 'Lifecycle repair migration contains a destructive command.');

$updater = (string) file_get_contents($root . '/helpers/ScriptUpdateService.php');
$assert(strpos($updater, "['failed', mb_substr") !== false && strpos($updater, "finished_at = NOW()") !== false, 'Failed update migrations are not recoverable.');

$contract = (string) file_get_contents($root . '/models/Contract.php');
$deleteStart = strpos($contract, 'public static function deleteContractSafely');
$cancelStart = strpos($contract, 'public static function cancel(');
$deleteMethod = substr($contract, $deleteStart, $cancelStart - $deleteStart);
$assert(strpos($deleteMethod, 'Payment::correctForContract') === false, 'Permanent deletion must not correct or remove payments.');
$assert(strpos($deleteMethod, 'eligible_for_permanent_delete') !== false, 'Permanent deletion must enforce eligibility server-side.');
$assert(strpos($deleteMethod, 'hash_equals') !== false, 'Permanent deletion must require typed contract confirmation.');
$assert(strpos($contract, 'contract_cancel_outbox') !== false && strpos($contract, 'contract_delete_outbox') !== false, 'Post-commit outbox failures are not isolated.');

$contractsController = (string) file_get_contents($root . '/controllers/ContractsController.php');
$assert(strpos($contractsController, 'confirm_delete_mistake') !== false && strpos($contractsController, 'confirm_contract_number') !== false, 'Delete controller confirmation is incomplete.');

foreach (['views/contracts/index.php', 'views/contracts/show.php'] as $view) {
    $source = (string) file_get_contents($root . '/' . $view);
    $assert(strpos($source, 'confirm_contract_number') !== false, 'Typed deletion confirmation is missing from ' . $view . '.');
    $deleteStart = strpos($source, 'delete-contract');
    $deleteEnd = strpos($source, '</form>', $deleteStart);
    $deleteModal = substr($source, $deleteStart, $deleteEnd - $deleteStart);
    $assert(strpos($deleteModal, 'correct_contract_payments') === false, 'Delete UI must not offer payment correction as a deletion bypass.');
}

$tokens = (string) file_get_contents($root . '/assets/css/design-system/tokens.css');
foreach (['#6a1b9a', '#4a148c', '#8e24aa', '#b388ff', '#f5f5f5', '#212121'] as $color) {
    $assert(stripos($tokens, $color) !== false, 'Design token is missing: ' . $color . '.');
}
$assert(strpos($tokens, '.form-group') !== false && strpos($tokens, '[required]') !== false, 'Legacy form groups are not covered by the unified required-field design.');

$builder = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($builder, 'V1.3.7 release candidate is present') !== false, 'Release builder can mislabel the V1.3.7 candidate as an older archive.');

$errorView = (string) file_get_contents($root . '/views/errors/system.php');
$assert(strpos($errorView, 'صفحه اصلی') === false, 'Error page still contains a duplicate home action.');
$assert(strpos($errorView, 'تلاش دوباره') !== false && strpos($errorView, 'بررسی وضعیت عملیات') !== false, 'Error actions are not context-aware.');

echo "STATIC_V137_OK\n";
