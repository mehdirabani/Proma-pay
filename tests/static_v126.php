<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/core/PluginManifest.php';
require_once $root . '/core/PluginHooks.php';
require_once $root . '/plugins/PromaAccounting/src/Services/Money.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? ''), '1.2.6', '>='), 'Application version must be at least V1.2.6 after the release gate.');
$assert((bool) preg_match('/^\d+\.\d+\.\d+$/', (string) ($version['application'] ?? '')), 'Central version is not semantic.');

$manifest = PluginManifest::read($root . '/plugins/PromaAccounting');
$assert(($manifest['id'] ?? '') === 'proma-accounting', 'Accounting manifest id mismatch.');
$assert(($manifest['requires_php'] ?? '') === '>=8.1', 'Accounting PHP requirement mismatch.');
$assert(in_array('migrations/2026_07_12_accounting_core.sql', $manifest['migrations'], true), 'Accounting migration missing from manifest.');

$assert(Proma\Plugins\Accounting\Services\Money::percentage(1000000, '1.25') === 12500, 'Exact percentage calculation failed.');
$assert(Proma\Plugins\Accounting\Services\Money::integer('۱۲۳٬۴۵۶ تومان') === 123456, 'Persian money parsing failed.');

$payload = PluginHooks::dispatch('test', ['password' => 'hidden', 'nested' => ['api_key' => 'hidden', 'ok' => 1]]);
$assert(($payload['password'] ?? '') === '[redacted]', 'Hook payload password was not redacted.');
$assert(($payload['nested']['api_key'] ?? '') === '[redacted]', 'Nested hook secret was not redacted.');
$assert(($payload['nested']['ok'] ?? 0) === 1, 'Hook payload was changed unexpectedly.');

$first = avatar_suggestion_for('سارا رضایی', 'customer');
$second = avatar_suggestion_for('سارا رضایی', 'customer');
$assert(($first['key'] ?? '') === ($second['key'] ?? ''), 'Avatar suggestion is not stable.');
$assert(is_file($root . '/' . avatar_catalog()[$first['key']]['file']), 'Suggested avatar asset is missing.');

$requiredMigrations = [
    '2026_07_12_plugin_framework.sql',
    '2026_07_12_contract_payment_document_enhancements.sql',
    '2026_07_12_medal_avatar_system.sql',
];
foreach ($requiredMigrations as $migration) {
    $assert(is_file($root . '/database/migrations/' . $migration), 'Required core migration missing: ' . $migration);
}

echo "STATIC_V126_OK\n";
