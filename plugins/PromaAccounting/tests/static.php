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

echo "PROMA_ACCOUNTING_STATIC_OK\n";
