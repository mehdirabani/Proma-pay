<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$slug = str_replace('.', '-', $version);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required.\n");
    exit(1);
}

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$open = static function (string $path): ZipArchive {
    if (!is_file($path) || filesize($path) < 100) {
        throw new RuntimeException('Release archive is missing or empty: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Release archive cannot be opened: ' . $path);
    }
    return $zip;
};

$corePath = $root . '/dist/core/proma-pay_v' . $slug . '.zip';
$updatePath = $root . '/dist/updates/proma-update_v' . $slug . '.zip';
$sidecarManifestPath = $root . '/dist/updates/proma-update_v' . $slug . '-manifest.json';
$checksumPath = $root . '/dist/checksums/SHA256SUMS.txt';

$core = $open($corePath);
$coreManifestRaw = $core->getFromName('release-manifest.json');
$assert(is_string($coreManifestRaw), 'Core release manifest is missing.');
$coreManifest = json_decode($coreManifestRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($coreManifest['version'] ?? null) === $version, 'Core archive version is incorrect.');
$assert($core->locateName('install.php') !== false, 'Core archive does not contain install.php.');
$assert($core->locateName('config/database.php') === false, 'Core archive contains database credentials.');
$assert($core->locateName('installed.lock') === false, 'Core archive contains an installation lock.');
for ($i = 0; $i < $core->numFiles; $i++) {
    $name = (string) $core->getNameIndex($i);
    $assert(strpos($name, 'plugins/PromaAccounting/') !== 0 && strpos($name, 'plugins/PromaZarinpal/') !== 0, 'Core archive contains an optional plugin: ' . $name);
}
$core->close();

$update = $open($updatePath);
$manifestRaw = $update->getFromName('proma-update.json');
$assert(is_string($manifestRaw), 'Update manifest is missing from the archive.');
$manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['version'] ?? null) === $version, 'Update archive version is incorrect.');
$assert(in_array('database/migrations/2026_07_20_profile_auth_contract_payment_v140.sql', $manifest['migrations'] ?? [], true), 'V1.4.0 migration is absent from the update manifest.');
foreach (($manifest['files'] ?? []) as $file) {
    $source = (string) ($file['source'] ?? '');
    $content = $update->getFromName($source);
    $assert(is_string($content), 'Update file is absent from the archive: ' . $source);
    $assert(hash_equals((string) ($file['sha256'] ?? ''), hash('sha256', $content)), 'Update file checksum mismatch: ' . $source);
}
$update->close();

$assert(is_file($sidecarManifestPath), 'Update sidecar manifest is missing.');
$sidecar = json_decode((string) file_get_contents($sidecarManifestPath), true, 512, JSON_THROW_ON_ERROR);
$assert(($sidecar['version'] ?? null) === $version, 'Sidecar manifest version is incorrect.');

$checksumLines = is_file($checksumPath) ? file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$assert(is_array($checksumLines) && count($checksumLines) >= 4, 'Aggregate checksum file is incomplete.');
foreach ($checksumLines as $line) {
    $parts = preg_split('/\s{2}/', trim((string) $line), 2);
    $assert(is_array($parts) && count($parts) === 2, 'Invalid checksum line: ' . $line);
    [$expected, $name] = $parts;
    $matches = glob($root . '/dist/{core,updates,plugins}/' . $name, GLOB_BRACE) ?: [];
    $assert(count($matches) === 1, 'Checksum target is missing or ambiguous: ' . $name);
    $assert(hash_equals($expected, hash_file('sha256', $matches[0])), 'Archive checksum mismatch: ' . $name);
}

echo "RELEASE_ARCHIVES_V140_OK\n";

