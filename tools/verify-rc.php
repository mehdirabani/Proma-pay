<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '');
$pluginManifest = json_decode((string) file_get_contents($root . '/plugins/PromaAccounting/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$pluginVersion = (string) ($pluginManifest['version'] ?? '');
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$open = static function (string $path): ZipArchive {
    if (!is_file($path) || filesize($path) < 100) {
        throw new RuntimeException('Archive is missing or empty: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Archive cannot be opened: ' . $path);
    }
    return $zip;
};
$safeNames = static function (ZipArchive $zip, string $label) use ($assert): void {
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        $assert($name !== '' && $name[0] !== '/' && !preg_match('#(?:^|/)\.\.(?:/|$)#', $name), $label . ' contains unsafe path: ' . $name);
    }
};

$corePath = $root . '/dist/core/PromaPay-v' . $version . '.zip';
$updatePath = $root . '/dist/core/PromaPay-Update-v' . $version . '.zip';
$sidecarPath = $root . '/dist/core/PromaPay-Update-v' . $version . '-manifest.json';
$pluginPath = $root . '/dist/plugins/PromaAccounting-v' . $pluginVersion . '.zip';
$checksumsPath = $root . '/dist/core/SHA256SUMS-v' . $version . '.txt';

$core = $open($corePath);
$safeNames($core, 'Core RC');
$coreManifest = json_decode((string) $core->getFromName('release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($coreManifest['version'] ?? '') === $version, 'Core RC version mismatch.');
$assert(($coreManifest['channel'] ?? '') === 'rc' && ($coreManifest['stable_gate'] ?? '') === 'BLOCKED', 'Core archive does not identify the blocked RC channel.');
$assert($core->locateName('install.php') !== false, 'Core installer is missing install.php.');
foreach (['config/database.php', 'installed.lock', '.env', '1.xlsx'] as $forbidden) {
    $assert($core->locateName($forbidden) === false, 'Core archive contains operational file: ' . $forbidden);
}
for ($index = 0; $index < $core->numFiles; $index++) {
    $name = str_replace('\\', '/', (string) $core->getNameIndex($index));
    $assert(strpos($name, 'plugins/PromaAccounting/') !== 0, 'Core archive contains Accounting source.');
    $assert(strpos($name, 'plugins/PromaZarinpal/') !== 0, 'Core archive contains Zarinpal source.');
    $assert(strpos($name, 'storage/secure_uploads/') !== 0, 'Core archive contains private uploads.');
    $assert(strpos($name, 'docs/accounting-next/') !== 0 && strpos($name, 'docs/accounting-stability/') !== 0, 'Core archive contains unrelated worktree documents.');
}
$core->close();

$update = $open($updatePath);
$safeNames($update, 'Update RC');
$updateManifest = json_decode((string) $update->getFromName('proma-update.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($updateManifest['version'] ?? '') === $version, 'Update RC version mismatch.');
$assert(($updateManifest['minimum_version'] ?? '') === '1.4.1', 'Update minimum version is incorrect.');
$assert(($updateManifest['channel'] ?? '') === 'rc' && ($updateManifest['stable_gate'] ?? '') === 'BLOCKED', 'Update archive does not identify the blocked RC channel.');
foreach (['core/RequestTelemetry.php', 'core/CronLock.php', 'controllers/SystemHealthController.php', 'views/system-health/index.php', 'config/version.php'] as $required) {
    $assert($update->locateName($required) !== false, 'Required RC update file is missing: ' . $required);
}
foreach (($updateManifest['files'] ?? []) as $file) {
    $source = (string) ($file['source'] ?? '');
    $assert($source !== '' && strpos($source, 'plugins/') !== 0 && strpos($source, 'storage/') !== 0 && $source !== 'config/database.php', 'Update contains forbidden target: ' . $source);
    $content = $update->getFromName($source);
    $assert(is_string($content), 'Update manifest file is absent: ' . $source);
    $assert(hash_equals((string) ($file['sha256'] ?? ''), hash('sha256', $content)), 'Update file checksum mismatch: ' . $source);
}
$update->close();

$sidecar = json_decode((string) file_get_contents($sidecarPath), true, 512, JSON_THROW_ON_ERROR);
$assert(($sidecar['version'] ?? '') === $version && ($sidecar['channel'] ?? '') === 'rc', 'Update sidecar is invalid.');

$plugin = $open($pluginPath);
$safeNames($plugin, 'Accounting RC');
$insidePlugin = json_decode((string) $plugin->getFromName('PromaAccounting/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($insidePlugin['id'] ?? '') === 'proma-accounting', 'Accounting plugin id is invalid.');
$assert(($insidePlugin['version'] ?? '') === $pluginVersion, 'Accounting plugin version mismatch.');
$migration = 'PromaAccounting/migrations/2026_07_21_accounting_dashboard_performance.sql';
$assert($plugin->locateName($migration) !== false, 'Accounting performance migration is missing.');
$plugin->close();

$targets = [basename($corePath) => $corePath, basename($updatePath) => $updatePath, basename($pluginPath) => $pluginPath];
$lines = file($checksumsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$assert(count($lines) === count($targets), 'Checksum inventory size is invalid.');
foreach ($lines as $line) {
    $parts = preg_split('/\s{2}/', trim((string) $line), 2);
    $assert(is_array($parts) && count($parts) === 2 && isset($targets[$parts[1]]), 'Checksum target is invalid.');
    $assert(hash_equals($parts[0], hash_file('sha256', $targets[$parts[1]])), 'Archive checksum mismatch: ' . $parts[1]);
    unset($targets[$parts[1]]);
}
$assert($targets === [], 'Checksum inventory is incomplete.');

$temporary = sys_get_temp_dir() . '/proma-rc-verify-' . bin2hex(random_bytes(6));
if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
    throw new RuntimeException('Cannot create extraction directory.');
}
$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
};
try {
    foreach (['core' => $corePath, 'update' => $updatePath, 'plugin' => $pluginPath] as $label => $path) {
        $target = $temporary . '/' . $label;
        mkdir($target, 0700, true);
        $archive = $open($path);
        $assert($archive->extractTo($target), 'Could not extract ' . $label . '.');
        $archive->close();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
            $assert($code === 0, 'Extracted PHP lint failed: ' . $file->getPathname() . ' ' . implode(' ', $output));
            $output = [];
        }
    }
} finally {
    $removeTree($temporary);
}

echo "RC_ARCHIVES_VERIFIED\n";
