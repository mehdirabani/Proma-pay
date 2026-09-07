<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
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
        throw new RuntimeException('Release archive is missing or empty: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Release archive cannot be opened: ' . $path);
    }
    return $zip;
};
$assertSafeNames = static function (ZipArchive $zip, string $label) use ($assert): void {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        $assert($name !== '' && $name[0] !== '/' && !preg_match('#(?:^|/)\.\.(?:/|$)#', $name), $label . ' contains an unsafe path: ' . $name);
    }
};

$corePath = $root . '/dist/core/PromaPay-v' . $version . '.zip';
$updatePath = $root . '/dist/updates/PromaPay-Update-v' . $version . '.zip';
$updateManifestPath = $root . '/dist/updates/PromaPay-Update-v' . $version . '-manifest.json';
$pluginPath = '';
$checksumPath = $root . '/dist/core/SHA256SUMS.txt';
$releaseNotesPath = $root . '/dist/core/RELEASE_NOTES-v' . $version . '.md';
$testReportPath = $root . '/dist/core/FULL_TEST_REPORT-v' . $version . '.md';
$uiUxReportPath = $root . '/dist/core/UI_UX_REDESIGN_REPORT-v' . $version . '.md';
$guarantorReportPath = $root . '/dist/core/GUARANTOR_FIX_REPORT-v' . $version . '.md';

$core = $open($corePath);
$assertSafeNames($core, 'Core archive');
$coreManifestRaw = $core->getFromName('release-manifest.json');
$assert(is_string($coreManifestRaw), 'Core release-manifest.json is missing.');
$coreManifest = json_decode($coreManifestRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($coreManifest['version'] ?? '') === $version, 'Core archive version is incorrect.');
$coreFileHashes = [];
foreach (($coreManifest['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && !empty($file['sha256'])) {
        $coreFileHashes[(string) $file['path']] = strtolower((string) $file['sha256']);
    }
}
$assert($core->locateName('install.php') !== false, 'Core archive does not contain install.php.');
$assert(trim((string) $core->getFromName('health-static.txt')) === 'proma-static-ok', 'Core archive does not contain the static health probe.');
foreach (['config/database.php', 'installed.lock', '.env', '1.xlsx'] as $forbidden) {
    $assert($core->locateName($forbidden) === false, 'Core archive contains a forbidden operational file: ' . $forbidden);
}
for ($i = 0; $i < $core->numFiles; $i++) {
    $name = str_replace('\\', '/', (string) $core->getNameIndex($i));
    $assert(strpos($name, 'plugins/PromaAccounting/') !== 0 && strpos($name, 'plugins/PromaZarinpal/') !== 0, 'Core archive contains optional plugin source: ' . $name);
    $assert(strpos($name, 'storage/secure_uploads/') !== 0, 'Core archive contains secure user uploads.');
    if (strpos($name, 'html/') === 0) {
        $assert(strpos($name, 'html/RTL/assets/') === 0, 'Core archive contains a non-runtime HTML asset: ' . $name);
    }
    foreach (['docs/', 'electron/', 'scripts/', 'tests/', 'tools/'] as $developmentPrefix) {
        $assert(strpos($name, $developmentPrefix) !== 0, 'Core archive contains development-only content: ' . $name);
    }
}
foreach ([
    'html/RTL/assets/css/vendors/bootstrap.rtl.min.css',
    'html/RTL/assets/css/style.css',
    'html/RTL/assets/css/responsive.css',
    'html/RTL/assets/js/jquery.min.js',
    'html/RTL/assets/js/bootstrap/bootstrap.bundle.min.js',
    'html/RTL/assets/js/login.js',
    'html/RTL/assets/js/sidebar-menu.js',
    'html/RTL/assets/images/favicon.png',
] as $runtimeAsset) {
    $assert($core->locateName($runtimeAsset) !== false, 'Core archive is missing required UI runtime asset: ' . $runtimeAsset);
}
$core->close();

$update = $open($updatePath);
$assertSafeNames($update, 'Update archive');
$updateManifestRaw = $update->getFromName('proma-update.json');
$assert(is_string($updateManifestRaw), 'Update proma-update.json is missing.');
$updateManifest = json_decode($updateManifestRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($updateManifest['version'] ?? '') === $version, 'Update archive version is incorrect.');
$baseline = $updateManifest['baseline'] ?? [];
$baselineVersion = (string) ($baseline['version'] ?? '');
$assert(preg_match('/^\d+\.\d+\.\d+$/', $baselineVersion) === 1, 'Update baseline version is invalid.');
$assert(($updateManifest['minimum_version'] ?? '') === $baselineVersion, 'Update minimum version must equal its verified baseline.');
$baselinePath = trim((string) getenv('PROMA_UPDATE_BASELINE_ARCHIVE'));
if ($baselinePath === '') {
    $baselinePath = $root . '/dist/core/PromaPay-v' . $baselineVersion . '.zip';
}
$baselineZip = $open($baselinePath);
$baselineRaw = $baselineZip->getFromName('release-manifest.json');
$baselineZip->close();
$assert(is_string($baselineRaw) && hash_equals((string) ($baseline['release_manifest_sha256'] ?? ''), hash('sha256', $baselineRaw)), 'Update baseline manifest checksum is invalid.');
$baselineManifest = json_decode($baselineRaw, true, 512, JSON_THROW_ON_ERROR);
$baselineHashes = [];
foreach (($baselineManifest['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && !empty($file['sha256'])) {
        $baselineHashes[(string) $file['path']] = strtolower((string) $file['sha256']);
    }
}
$assert($update->locateName('UPDATE_README.md') !== false, 'Differential update instruction file is missing.');
$assert(count($updateManifest['files'] ?? []) < count($baselineHashes), 'Update archive is not differential; it contains the full installer inventory.');
foreach (($updateManifest['files'] ?? []) as $file) {
    $source = (string) ($file['source'] ?? '');
    $assert($source !== '' && strpos($source, 'plugins/') !== 0 && $source !== 'config/database.php' && strpos($source, 'storage/') !== 0, 'Update manifest contains a forbidden target: ' . $source);
    $content = $update->getFromName($source);
    $assert(is_string($content), 'Update file is absent from the archive: ' . $source);
    $assert(hash_equals((string) ($file['sha256'] ?? ''), hash('sha256', $content)), 'Update checksum mismatch: ' . $source);
    $assert(($coreFileHashes[$source] ?? '') === hash('sha256', $content), 'Update file is not identical to the target core package: ' . $source);
    $assert(($file['expected_previous_sha256'] ?? null) === ($baselineHashes[$source] ?? null), 'Update baseline hash mismatch: ' . $source);
    $assert(($baselineHashes[$source] ?? '') !== hash('sha256', $content), 'Update includes an unchanged baseline file: ' . $source);
}
$update->close();

$assert(is_file($updateManifestPath), 'Update sidecar manifest is missing.');
$sidecar = json_decode((string) file_get_contents($updateManifestPath), true, 512, JSON_THROW_ON_ERROR);
$assert(($sidecar['version'] ?? '') === $version, 'Update sidecar version is incorrect.');

$checksumLines = is_file($checksumPath) ? file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$pluginArchives = [];
foreach ($checksumLines as $line) {
    if (preg_match('/\\s{2}(PromaAccounting-v(\\d+\\.\\d+\\.\\d+)\\.zip)$/', trim((string) $line), $match)) {
        $pluginArchives[] = ['name' => $match[1], 'version' => $match[2]];
    }
}
$assert(count($pluginArchives) === 1, 'SHA256SUMS.txt must identify exactly one Accounting archive.');
$accountingVersion = $pluginArchives[0]['version'];
$pluginPath = $root . '/dist/plugins/' . $pluginArchives[0]['name'];

$plugin = $open($pluginPath);
$assertSafeNames($plugin, 'Proma Accounting archive');
$pluginJsonRaw = $plugin->getFromName('PromaAccounting/plugin.json');
$assert(is_string($pluginJsonRaw), 'Proma Accounting archive root structure is invalid.');
$pluginJson = json_decode($pluginJsonRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($pluginJson['id'] ?? '') === 'proma-accounting', 'Proma Accounting plugin id is invalid.');
$assert(($pluginJson['version'] ?? '') === $accountingVersion, 'Proma Accounting archive version is invalid.');
$plugin->close();

$assert(is_array($checksumLines) && count($checksumLines) === 3, 'SHA256SUMS.txt must contain exactly the Core, update and Accounting archives.');
$targets = [basename($corePath) => $corePath, basename($updatePath) => $updatePath, basename($pluginPath) => $pluginPath];
foreach ($checksumLines as $line) {
    $parts = preg_split('/\s{2}/', trim((string) $line), 2);
    $assert(is_array($parts) && count($parts) === 2, 'Invalid checksum line: ' . $line);
    [$expected, $name] = $parts;
    $assert(isset($targets[$name]), 'Unexpected checksum target: ' . $name);
    $assert(hash_equals($expected, hash_file('sha256', $targets[$name])), 'Archive checksum mismatch: ' . $name);
    unset($targets[$name]);
}
$assert($targets === [], 'One or more release archives are absent from SHA256SUMS.txt.');
$assert(is_file($releaseNotesPath) && filesize($releaseNotesPath) > 300, 'Release notes are missing or incomplete.');
$assert(is_file($testReportPath) && filesize($testReportPath) > 1000, 'Final test report is missing or incomplete.');
$assert(is_file($uiUxReportPath) && filesize($uiUxReportPath) > 500, 'UI/UX report is missing or incomplete.');
$assert(is_file($guarantorReportPath) && filesize($guarantorReportPath) > 500, 'Guarantor fix report is missing or incomplete.');

foreach ([
    $root . '/dist/core/proma-pay_v*.zip',
    $root . '/dist/updates/proma-update_v*.zip',
    $root . '/dist/updates/proma-update_v*-manifest.json',
] as $legacyPattern) {
    $assert((glob($legacyPattern) ?: []) === [], 'Legacy duplicate release alias remains: ' . $legacyPattern);
}

$extractRoot = sys_get_temp_dir() . '/proma-release-verify-' . bin2hex(random_bytes(6));
if (!mkdir($extractRoot, 0700, true) && !is_dir($extractRoot)) {
    throw new RuntimeException('Cannot create temporary extraction directory.');
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
    foreach (['core' => $corePath, 'update' => $updatePath, 'plugin' => $pluginPath] as $label => $archivePath) {
        $target = $extractRoot . '/' . $label;
        mkdir($target, 0700, true);
        $archive = $open($archivePath);
        $assert($archive->extractTo($target), 'Could not extract ' . $label . ' archive.');
        $archive->close();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $lintOutput, $lintCode);
            $assert($lintCode === 0, 'Extracted PHP lint failed: ' . $file->getPathname() . ' ' . implode(' ', $lintOutput));
            $lintOutput = [];
        }
    }
} finally {
    $removeTree($extractRoot);
}

echo 'RELEASE_ARCHIVES_V' . str_replace('.', '', $version) . "_OK\n";
