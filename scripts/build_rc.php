<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$channel = strtolower((string) ($versionInfo['release_channel'] ?? ''));
if ($channel !== 'rc' || !preg_match('/^\d+\.\d+\.\d+-rc\.\d+$/', $version)) {
    throw new RuntimeException('RC packaging requires an rc channel and X.Y.Z-rc.N version. Current: ' . $version);
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$accountingRoot = $root . '/plugins/PromaAccounting';
$accounting = json_decode((string) file_get_contents($accountingRoot . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$accountingVersion = (string) ($accounting['version'] ?? '0.0.0');
if (!preg_match('/^\d+\.\d+\.\d+-rc\.\d+$/', $accountingVersion)) {
    throw new RuntimeException('Proma Accounting RC version is invalid: ' . $accountingVersion);
}

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/availability-gate.php'), $availabilityCode);
if ($availabilityCode !== 2) {
    throw new RuntimeException('RC packaging expected the production availability gate to remain BLOCKED.');
}
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/release-gate.php'), $localGateCode);
if ($localGateCode !== 0) {
    throw new RuntimeException('Local release gate failed. No RC archive was produced.');
}

$distCore = $root . '/dist/core';
$distUpdates = $root . '/dist/updates';
$distPlugins = $root . '/dist/plugins';
foreach ([$distCore, $distUpdates, $distPlugins] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create RC directory: ' . $directory);
    }
}

$normalize = static fn (string $path): string => trim(str_replace('\\', '/', $path), '/');
$openZip = static function (string $path): ZipArchive {
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Cannot replace archive: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create archive: ' . $path);
    }
    return $zip;
};
$walk = static function (string $directory, callable $accept, string $prefix = '') use ($normalize): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        $relative = $normalize(substr($item->getPathname(), strlen($directory)));
        if ($accept($relative, $item->getPathname())) {
            $files[$normalize($prefix . '/' . $relative)] = $item->getPathname();
        }
    }
    ksort($files, SORT_STRING);
    return $files;
};
$fileManifest = static function (array $files): array {
    $result = [];
    foreach ($files as $path => $source) {
        $result[] = ['path' => $path, 'size' => filesize($source), 'sha256' => hash_file('sha256', $source)];
    }
    return $result;
};

$excluded = static function (string $relative) use ($normalize): bool {
    $path = strtolower($normalize($relative));
    if (in_array($path, ['.env', 'config/database.php', 'installed.lock', '1.xlsx'], true)) {
        return true;
    }
    if (strpos($path, 'html/rtl/assets/') === 0) {
        return !preg_match('#^html/rtl/assets/(?:css|fonts|images|js|json|svg)/#', $path);
    }
    foreach (['.git/', '.github/', '.agents/', '.codex/', 'dist/', 'storage/', 'tmp/', 'plugins/', 'plugin-packages/', 'node_modules/', 'docs/accounting-next/', 'docs/accounting-stability/', 'html/docs/', 'html/rtl/dist/', 'html/rtl/starter-kit/', 'html/rtl/template/'] as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }
    return (bool) preg_match('/\.(?:zip|log|bak|sql\.gz|sqlite|db|tmp)$/i', basename($path));
};

$coreFiles = $walk($root, static fn (string $relative): bool => !$excluded($relative));
$corePath = $distCore . '/PromaPay-v' . $version . '.zip';
$coreZip = $openZip($corePath);
foreach ($coreFiles as $path => $source) {
    $coreZip->addFile($source, $path);
}
foreach (['plugins', 'storage/cache', 'storage/logs', 'storage/uploads', 'storage/backups', 'storage/releases', 'storage/updates'] as $directory) {
    $coreZip->addEmptyDir($directory);
}
$coreZip->addFromString('release-manifest.json', json_encode([
    'app' => 'proma-pay',
    'type' => 'core-installer-rc',
    'version' => $version,
    'channel' => 'rc',
    'stable_gate' => 'BLOCKED',
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'built_at' => gmdate('c'),
    'files' => $fileManifest($coreFiles),
    'excludes_operational_data' => true,
    'optional_plugins_included' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$coreZip->close();

$git = static function (string $arguments) use ($root): array {
    exec('git -C ' . escapeshellarg($root) . ' ' . $arguments . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Git inventory failed: ' . implode(' ', $output));
    }
    return array_values(array_filter(array_map('trim', $output)));
};
$baseRef = trim((string) (getenv('PROMA_RELEASE_BASE_REF') ?: 'a07e6fb'));
$candidates = array_values(array_unique(array_merge(
    $git('diff --name-only --diff-filter=ACMRT ' . escapeshellarg($baseRef) . ' --'),
    $git('ls-files --others --exclude-standard'),
    ['config/version.php', 'manifest.json', 'package.json', 'service-worker.js']
)));
sort($candidates, SORT_STRING);
$runtimeRoots = ['assets/', 'config/', 'controllers/', 'core/', 'database/', 'helpers/', 'html/RTL/assets/', 'models/', 'views/'];
$runtimeRootFiles = ['.htaccess', 'bootstrap.php', 'index.php', 'install.php', 'installer.php', 'manifest.json', 'package.json', 'service-worker.js'];
$updateFiles = [];
foreach ($candidates as $candidate) {
    $candidate = $normalize($candidate);
    $allowed = in_array($candidate, $runtimeRootFiles, true);
    foreach ($runtimeRoots as $prefix) {
        $allowed = $allowed || strpos($candidate, $prefix) === 0;
    }
    if (!$allowed || strpos($candidate, 'plugins/') === 0 || strpos($candidate, 'storage/') === 0 || $candidate === 'config/database.php') {
        continue;
    }
    $source = $root . '/' . $candidate;
    if (is_file($source)) {
        $updateFiles[$candidate] = $source;
    }
}
ksort($updateFiles, SORT_STRING);
$updateManifestFiles = [];
foreach ($updateFiles as $path => $source) {
    $updateManifestFiles[] = ['source' => $path, 'target' => $path, 'sha256' => hash_file('sha256', $source)];
}
$migrations = array_values(array_filter(array_keys($updateFiles), static fn (string $path): bool => strpos($path, 'database/migrations/') === 0));
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی آزمایشی Proma Pay V' . $version,
    'version' => $version,
    'channel' => 'rc',
    'stable_gate' => 'BLOCKED',
    'minimum_version' => '1.4.1',
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $updateManifestFiles,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
];
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$updatePath = $distUpdates . '/PromaPay-Update-v' . $version . '.zip';
$updateZip = $openZip($updatePath);
foreach ($updateFiles as $path => $source) {
    $updateZip->addFile($source, $path);
}
$updateZip->addFromString('proma-update.json', $updateJson);
$updateZip->close();
$updateManifestPath = $distUpdates . '/PromaPay-Update-v' . $version . '-manifest.json';
file_put_contents($updateManifestPath, $updateJson . PHP_EOL, LOCK_EX);

$pluginFiles = $walk($accountingRoot, static fn (string $relative): bool => !preg_match('/\.(?:zip|log|tmp)$/i', basename($relative)), 'PromaAccounting');
$pluginPath = $distPlugins . '/PromaAccounting-v' . $accountingVersion . '.zip';
$pluginZip = $openZip($pluginPath);
foreach ($pluginFiles as $path => $source) {
    $pluginZip->addFile($source, $path);
}
$pluginZip->addFromString('PromaAccounting/build-manifest.json', json_encode([
    'plugin_id' => (string) ($accounting['id'] ?? 'proma-accounting'),
    'version' => $accountingVersion,
    'channel' => 'rc',
    'requires_core' => (string) ($accounting['requires_core'] ?? ''),
    'built_at' => gmdate('c'),
    'files' => $fileManifest($pluginFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$pluginZip->close();

$reportSource = $root . '/docs/incidents/TIMEOUT_INCIDENT_REPORT-v' . $version . '.md';
$notesSource = $root . '/docs/releases/V' . $version . '.md';
if (!is_file($reportSource) || !is_file($notesSource)) {
    throw new RuntimeException('RC report or release notes are missing.');
}
$reportPath = $distCore . '/TIMEOUT_INCIDENT_REPORT-v' . $version . '.md';
$notesPath = $distCore . '/RELEASE_NOTES-v' . $version . '.md';
copy($reportSource, $reportPath);
copy($notesSource, $notesPath);

$archives = [$corePath, $updatePath, $pluginPath];
$checksumLines = [];
foreach ($archives as $archive) {
    if (!is_file($archive) || filesize($archive) < 100) {
        throw new RuntimeException('Archive is missing or empty: ' . $archive);
    }
    $checksumLines[] = hash_file('sha256', $archive) . '  ' . basename($archive);
}
$checksumPath = $distCore . '/SHA256SUMS-v' . $version . '.txt';
file_put_contents($checksumPath, implode(PHP_EOL, $checksumLines) . PHP_EOL, LOCK_EX);

echo json_encode([
    'version' => $version,
    'channel' => 'rc',
    'stable_gate' => 'BLOCKED',
    'core' => $corePath,
    'update' => $updatePath,
    'update_manifest' => $updateManifestPath,
    'plugin' => $pluginPath,
    'checksums' => $checksumPath,
    'incident_report' => $reportPath,
    'release_notes' => $notesPath,
    'update_files' => count($updateFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
