<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if (!is_string($contents)) {
        throw new RuntimeException('Required Phase 2 file is missing: ' . $path);
    }
    return $contents;
};

$migration = $read('database/migrations/2026_10_23_multi_merchant_core_phase2.sql');
$freshSchema = $read('database/proma-pay-install.sql');
$tableNames = [
    'merchant_accounts',
    'merchant_sites',
    'merchant_connections',
    'merchant_memberships',
    'merchant_api_credentials',
    'merchant_policy_versions',
    'merchant_policy_heads',
    'merchant_audit_events',
];
foreach ($tableNames as $tableName) {
    $create = '/CREATE TABLE IF NOT EXISTS ' . preg_quote($tableName, '/') . '\s*\(/i';
    $assert((bool) preg_match($create, $migration), 'Migration omits table ' . $tableName . '.');
    $assert((bool) preg_match($create, $freshSchema), 'Fresh-install schema omits table ' . $tableName . '.');
}
$assert(
    !preg_match('/^\s*(?:ALTER|DROP|TRUNCATE|UPDATE|DELETE|INSERT)\s+/im', $migration),
    'Additive migration contains a destructive or data-mutating statement.'
);
$assert(strpos($migration, 'FOREIGN KEY (site_id,merchant_id)') !== false, 'Connection/site ownership is not enforced by a composite foreign key.');
$assert(strpos($migration, 'UNIQUE KEY uq_merchant_site_origin (merchant_id,site_origin)') !== false, 'Site origin uniqueness is not merchant-scoped.');
$assert(strpos($migration, 'UNIQUE KEY uq_merchant_policy_version (connection_id,policy_type,version_no)') !== false, 'Policy version uniqueness is not connection-scoped.');
$assert(strpos($migration, 'FOREIGN KEY (policy_version_id,connection_id,policy_type)') !== false, 'Policy head can point to a different connection/type.');
$assert(strpos($migration, "status ENUM('inactive','active','suspended','revoked') NOT NULL DEFAULT 'inactive'") !== false, 'Connections do not default to inactive.');
$assert(strpos($migration, "verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified'") !== false, 'Sites do not default to unverified.');
$assert(strpos($migration, 'token_hash BINARY(32) NOT NULL') !== false, 'Credential table does not store a fixed-width digest.');
$assert(!preg_match('/\b(?:raw_token|token_value|secret_value)\b/i', $migration), 'Credential table stores a raw secret field.');
$assert(strpos($migration, "status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft'") !== false, 'New policies do not default to draft.');
$assert(strpos($migration, 'emergency_disabled TINYINT(1) NOT NULL DEFAULT 0') !== false, 'Connection kill switch is missing.');
$assert(strpos($read('install.php'), "database/proma-pay-install.sql") !== false, 'Fresh installer no longer uses the canonical schema.');

$connectionRepository = $read('models/MerchantConnectionRepository.php');
foreach (['c.merchant_id = ?', 'c.site_id = ?', 'c.emergency_disabled = 0', "s.verification_status = 'verified'", "m.status = 'active'"] as $guard) {
    $assert(strpos($connectionRepository, $guard) !== false, 'Scoped API read guard missing: ' . $guard);
}
$credentialRepository = $read('models/MerchantApiCredentialRepository.php');
foreach ([
    'k.token_hash = ?',
    "k.status = 'active'",
    "c.status = 'active'",
    'c.emergency_disabled = 0',
    "s.verification_status = 'verified'",
    "m.status = 'active'",
    'merchant_live_credentials_require_phase5_approval',
] as $guard) {
    $assert(strpos($credentialRepository, $guard) !== false, 'Credential/authentication guard missing: ' . $guard);
}
$auditMetadata = $read('helpers/MerchantAuditMetadata.php');
$assert(strpos($auditMetadata, 'token_hash') !== false, 'Audit metadata does not block credential fields.');
$assert(strpos($auditMetadata, 'pmp_(?:test|live)_') !== false, 'Audit metadata does not reject raw Proma Pay credentials in values.');
$directory = $read('models/MerchantDirectory.php');
$assert(strpos($directory, "preg_match_all('/./us', \$text)") !== false, 'Merchant text length is not counted as Unicode characters.');

$gate = $read('tools/release-gate.php');
foreach (['tests/merchant_core_phase2.php', 'tests/static_multi_merchant_core_phase2.php'] as $testName) {
    $assert(strpos($gate, "'" . $testName . "'") !== false, 'Release gate omits ' . $testName . '.');
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "STATIC_MULTI_MERCHANT_CORE_PHASE2_OK\n";
