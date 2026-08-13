<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$applicationVersion = (string) ($version['application'] ?? '');
$assert((bool) preg_match('/^\d+\.\d+\.\d+(?:-rc\.\d+)?$/', $applicationVersion), 'Core version is not a supported semantic version.');
$assert(version_compare($applicationVersion, '1.4.1-rc.1', '>='), 'Core version must be V1.4.1-rc.1 or newer.');
$assert(($version['display'] ?? '') === 'V' . $applicationVersion, 'Core display version is not synchronized.');

$accountingManifest = json_decode((string) file_get_contents($root . '/plugins/PromaAccounting/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string) ($accountingManifest['version'] ?? '0.0.0'), '1.2.4-rc.1', '>='), 'Proma Accounting must be V1.2.4-rc.1 or newer.');

$migrationPath = $root . '/database/migrations/2026_07_21_v1_4_1_interaction_state.sql';
$assert(is_file($migrationPath), 'V1.4.1 migration is missing.');
$migration = (string) file_get_contents($migrationPath);
foreach (['notification_delivered_exists', 'contract_duplicate_repairs', 'template_archived_at_exists', 'template_audit_ip_exists', 'medal_behavior_type_exists', 'avatar_path'] as $schemaToken) {
    $assert(strpos($migration, $schemaToken) !== false, 'V1.4.1 migration is missing schema token: ' . $schemaToken);
}

foreach ([
    'helpers/ContractDuplicateRepairService.php',
    'helpers/InstallmentReconciliationService.php',
    'helpers/InstallmentSettlementService.php',
    'helpers/MedalEvaluationService.php',
    'helpers/MedalHistoryService.php',
    'helpers/MedalReconciliationService.php',
    'helpers/PluginReconciliationService.php',
    'helpers/SchemaGuard.php',
    'tests/integration_v141_release_blockers.php',
    'tests/http_v141_role_smoke.php',
] as $requiredFile) {
    $assert(is_file($root . '/' . $requiredFile), 'Required V1.4.1 file is missing: ' . $requiredFile);
}

$installSql = (string) file_get_contents($root . '/database/proma-pay-install.sql');
$installer = (string) file_get_contents($root . '/install.php');
preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-z0-9_]+)`?/i', $installSql, $sqlTables);
preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-z0-9_]+)`?/i', $installer, $installerTables);
$sqlTableNames = array_values(array_unique(array_map('strtolower', $sqlTables[1] ?? [])));
$installerTableNames = array_values(array_unique(array_map('strtolower', $installerTables[1] ?? [])));
sort($sqlTableNames);
sort($installerTableNames);
$assert(count($sqlTableNames) >= 60, 'Fresh-install SQL table inventory is unexpectedly incomplete.');
$assert($sqlTableNames === $installerTableNames, 'install.php and proma-pay-install.sql table inventories differ. SQL-only: ' . implode(',', array_diff($sqlTableNames, $installerTableNames)) . ' Installer-only: ' . implode(',', array_diff($installerTableNames, $sqlTableNames)));

$ddlViolations = [];
foreach (['controllers', 'models', 'helpers'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (in_array($relative, ['helpers/BackupService.php', 'helpers/ScriptUpdateService.php'], true)) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match('/\b(?:CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE)\b/i', $source)) {
            $ddlViolations[] = $relative;
        }
    }
}
$assert($ddlViolations === [], 'Request-time schema mutation remains in: ' . implode(', ', $ddlViolations));

$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$appCss = (string) file_get_contents($root . '/assets/css/app.css');
$assert(strpos($layout, 'proma-profile-trigger') !== false && strpos($layout, 'aria-expanded="false"') !== false, 'Accessible profile menu trigger is missing.');
$assert(strpos($appJs, 'initProfileMenu') !== false, 'Profile menu interaction repair is missing.');
$assert(strpos($appCss, '.onhover-dropdown > .onhover-show-div.active') !== false, 'Active header dropdown visibility rule is missing.');

$releaseBuilder = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($releaseBuilder, 'database/migrations/') !== false && strpos($releaseBuilder, '$baselineFiles') !== false, 'Upgrade migrations are not selected from the verified baseline delta.');

echo "STATIC_V141_OK\n";
