<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/helpers/functions.php';
require_once $root . '/core/PluginManifest.php';
require_once dirname(__DIR__) . '/src/Services/Money.php';

$manifest = PluginManifest::read(dirname(__DIR__));
if (($manifest['id'] ?? '') !== 'proma-accounting') {
    throw new RuntimeException('Accounting plugin manifest id mismatch.');
}
if (Proma\Plugins\Accounting\Services\Money::percentage(1000000, '1.25') !== 12500) {
    throw new RuntimeException('Accounting money calculation failed.');
}

$sql = (string) file_get_contents(dirname(__DIR__) . '/migrations/2026_07_12_accounting_core.sql');
if (substr_count($sql, 'account_number VARCHAR(50) NOT NULL') < 2) {
    throw new RuntimeException('Account number columns are incomplete.');
}
if (substr_count($sql, 'account_number VARCHAR(50) NOT NULL') > 2) {
    throw new RuntimeException('Duplicate account number column detected.');
}

$provider = (string) file_get_contents(dirname(__DIR__) . '/src/AccountingServiceProvider.php');
if (strpos($provider, 'protected static function safeFetch') === false || strpos($provider, 'closeCursor()') === false) {
    throw new RuntimeException('Accounting provider must close PDO cursors during update/health checks.');
}
if (strpos($provider, '\\Model::fetch("SELECT setting_value FROM plugin_accounting_settings') !== false) {
    throw new RuntimeException('Accounting provider update still uses unsafe Model::fetch for setup status.');
}

$build = (string) file_get_contents(dirname(__DIR__) . '/scripts/build.php');
if (strpos($build, "'/dist/plugins/PromaAccounting'") === false) {
    throw new RuntimeException('Accounting build output must target dist/plugins/PromaAccounting.');
}

echo "PROMA_ACCOUNTING_STATIC_OK\n";
