<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    throw new RuntimeException('Stable release packaging requires a stable semantic Core version. Current: ' . $version);
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$accountingRoot = $root . '/plugins/PromaAccounting';
$accountingManifest = json_decode((string) file_get_contents($accountingRoot . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$accountingVersion = (string) ($accountingManifest['version'] ?? '0.0.0');
if (!preg_match('/^\d+\.\d+\.\d+$/', $accountingVersion)) {
    throw new RuntimeException('Stable release packaging requires a stable Proma Accounting version. Current: ' . $accountingVersion);
}

$localQaBuild = in_array('--local-qa', $argv, true);
$packageOnlyBuild = in_array('--package-only', $argv, true);
echo $localQaBuild
    ? "[GATE] Running local release QA. Production infrastructure is not certified by this build.\n"
    : "[GATE] Running strict production release gate before packaging.\n";
if (!$packageOnlyBuild) {
    $gateCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/release-gate.php');
    if ($localQaBuild) {
        $gateCommand .= ' --local';
    }
    passthru($gateCommand, $gateCode);
    if ($gateCode !== 0) {
        throw new RuntimeException('Release gate failed. No stable archive was produced.');
    }
} else {
    echo "[GATE] Package-only rebuild: release gate has already passed for this source revision.\n";
}

$distCore = $root . '/dist/core';
$distUpdates = $root . '/dist/updates';
$distPlugins = $root . '/dist/plugins';
foreach ([$distCore, $distUpdates, $distPlugins] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create release directory: ' . $directory);
    }
}

// Older builders created a second, identical archive with a dashed version.
// Keep historical canonical releases, but remove only those generated aliases.
foreach ([$distCore . '/proma-pay_v*.zip', $distUpdates . '/proma-update_v*.zip', $distUpdates . '/proma-update_v*-manifest.json'] as $legacyPattern) {
    foreach (glob($legacyPattern) ?: [] as $legacyAlias) {
        if (is_file($legacyAlias) && !unlink($legacyAlias)) {
            throw new RuntimeException('Cannot remove legacy duplicate release alias: ' . $legacyAlias);
        }
    }
}

$normalize = static function (string $path): string {
    return trim(str_replace('\\', '/', $path), '/');
};
$relative = static function (string $path) use ($root, $normalize): string {
    return $normalize(substr($path, strlen($root)));
};
$openZip = static function (string $path): ZipArchive {
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Cannot replace release archive: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create release archive: ' . $path);
    }
    return $zip;
};
$hashList = static function (array $files): array {
    $items = [];
    foreach ($files as $archivePath => $sourcePath) {
        $items[] = [
            'path' => $archivePath,
            'size' => filesize($sourcePath),
            'sha256' => hash_file('sha256', $sourcePath),
        ];
    }
    return $items;
};
$walk = static function (string $directory, callable $accept, string $prefix = '') use ($normalize): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        $relativePath = $normalize(substr($item->getPathname(), strlen($directory)));
        if ($accept($relativePath, $item->getPathname())) {
            $files[$normalize($prefix . '/' . $relativePath)] = $item->getPathname();
        }
    }
    ksort($files, SORT_STRING);
    return $files;
};

$coreExcluded = static function (string $relativePath) use ($normalize): bool {
    $path = strtolower($normalize($relativePath));
    if ($path === '') {
        return false;
    }
    // Browser QA artifacts are never runtime assets. Keep an accidental
    // root-level screenshot/PDF out of both the installer and the delta.
    if (preg_match('#^(?:booklet|contact-modal)-v[\w.-]*\.(?:png|pdf)$#i', $path)) {
        return true;
    }
    if (in_array($path, ['.env', 'config/database.php', 'installed.lock', '1.xlsx'], true)) {
        return true;
    }
    foreach ([
        '.git/', '.github/', '.agents/', '.codex/', '.playwright-cli/',
        'dist/', 'storage/', 'tmp/', 'output/', 'plugins/', 'plugin-packages/', 'node_modules/',
        'docs/', 'electron/', 'html/', 'scripts/', 'tests/', 'tools/',
    ] as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }
    return (bool) preg_match('/\.(?:zip|log|bak|sql\.gz|sqlite|db|tmp)$/i', basename($path));
};

$coreFiles = $walk($root, static function (string $relativePath) use ($coreExcluded): bool {
    return !$coreExcluded($relativePath);
});
$templateRequiredFiles = [
    'css/font-awesome.css',
    'css/vendors/themify.css',
    'css/vendors/feather-icon.css',
    'css/vendors/slick.css',
    'css/vendors/slick-theme.css',
    'css/vendors/scrollbar.css',
    'css/vendors/quill.snow.css',
    'css/vendors/quill.bubble.css',
    'css/vendors/animate.css',
    'css/vendors/bootstrap.rtl.min.css',
    'css/style.css',
    'css/color-1.css',
    'css/responsive.css',
    'css/vendors/slick/ajax-loader.gif',
    'css/vendors/slick/fonts/slick.woff',
    'fonts/font-awesome/fontawesome-webfont.woff2',
    'fonts/font-awesome/fontawesome-webfont.woff',
    'fonts/themify/themify.woff',
    'fonts/slick/slick.woff',
    'images/ajax-loader.gif',
    'images/favicon.png',
    'images/giftools.gif',
    'images/blog/4.jpg',
    'images/blog/comment.jpg',
    'images/dashboard-2/balance-bg.png',
    'images/dashboard-2/discover.png',
    'images/dashboard-3/bg.jpg',
    'images/dashboard-4/bg-balance.png',
    'images/dashboard-5/profile-bg.png',
    'images/dashboard-6/bg-1.png',
    'images/dashboard-6/bg-2.png',
    'images/dashboard-6/bg-3.png',
    'images/dashboard/widget-bg.png',
    'images/details_close.png',
    'images/details_open.png',
    'images/forms/flags.png',
    'images/forms/user.png',
    'images/landing/footer.jpg',
    'images/landing/home-bg.jpg',
    'images/landing/icon/minus.svg',
    'images/landing/icon/plus.svg',
    'images/login/login_bg.jpg',
    'images/other-images/bg-profile.png',
    'images/other-images/boxbg.jpg',
    'images/other-images/coming-soon-bg.jpg',
    'images/other-images/maintenance-bg.jpg',
    'images/social-app/social-image.png',
    'images/switch/square-gray.png',
    'images/switch/square.svg',
    'js/jquery.min.js',
    'js/bootstrap/bootstrap.bundle.min.js',
    'js/icons/feather-icon/feather.min.js',
    'js/icons/feather-icon/feather-icon.js',
    'js/scrollbar/simplebar.js',
    'js/scrollbar/custom.js',
    'js/config.js',
    'js/sidebar-menu.js',
    'js/sidebar-pin.js',
    'js/clock.js',
    'js/slick/slick.min.js',
    'js/slick/slick.js',
    'js/header-slick.js',
    'js/height-equal.js',
    'js/script.js',
    'js/editors/quill.js',
    'js/login.js',
    'svg/icon-sprite.svg',
];
$templateRequiredFiles = array_fill_keys($templateRequiredFiles, true);
$templateRuntimeFiles = $walk(
    $root . '/html/RTL/assets',
    static function (string $relativePath) use ($templateRequiredFiles): bool {
        return isset($templateRequiredFiles[ltrim($relativePath, '/')]);
    },
    'html/RTL/assets'
);
$coreFiles = array_merge($coreFiles, $templateRuntimeFiles);
ksort($coreFiles, SORT_STRING);

// An update is a delta from one known, verified installer archive—not a Git
// diff and not a second full installer. Resolve the exact preceding stable
// core package before building the target archive.
$baselineCorePath = trim((string) getenv('PROMA_UPDATE_BASELINE_ARCHIVE'));
if ($baselineCorePath === '') {
    $candidates = [];
    foreach (glob($distCore . '/PromaPay-v*.zip') ?: [] as $candidate) {
        if (preg_match('/PromaPay-v(\d+\.\d+\.\d+)\.zip$/', $candidate, $matches)
            && version_compare($matches[1], $version, '<')) {
            $candidates[$matches[1]] = $candidate;
        }
    }
    if ($candidates) {
        uksort($candidates, 'version_compare');
        $baselineCorePath = end($candidates);
    }
}
if (!is_file($baselineCorePath)) {
    throw new RuntimeException('A verified preceding PromaPay core archive is required to build a differential update. Set PROMA_UPDATE_BASELINE_ARCHIVE when needed.');
}
$baselineZip = new ZipArchive();
if ($baselineZip->open($baselineCorePath) !== true) {
    throw new RuntimeException('Cannot read the selected update baseline archive: ' . $baselineCorePath);
}
$baselineRaw = $baselineZip->getFromName('release-manifest.json');
$baselineZip->close();
$baselineManifest = is_string($baselineRaw) ? json_decode($baselineRaw, true) : null;
if (!is_array($baselineManifest) || ($baselineManifest['app'] ?? '') !== 'proma-pay' || ($baselineManifest['type'] ?? '') !== 'core-installer') {
    throw new RuntimeException('The selected update baseline does not contain a valid Proma Pay core release manifest.');
}
$baselineVersion = (string) ($baselineManifest['version'] ?? '');
if (!preg_match('/^\d+\.\d+\.\d+$/', $baselineVersion) || !version_compare($baselineVersion, $version, '<')) {
    throw new RuntimeException('The update baseline must be a stable version older than the target release.');
}
$baselineFiles = [];
foreach (($baselineManifest['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && !empty($file['sha256'])) {
        $baselineFiles[$normalize((string) $file['path'])] = strtolower((string) $file['sha256']);
    }
}
if (!$baselineFiles) {
    throw new RuntimeException('The selected update baseline has no file checksum inventory.');
}
$corePath = $distCore . '/PromaPay-v' . $version . '.zip';
$coreZip = $openZip($corePath);
foreach ($coreFiles as $archivePath => $sourcePath) {
    $coreZip->addFile($sourcePath, $archivePath);
}
foreach (['plugins', 'storage/cache', 'storage/logs', 'storage/uploads', 'storage/backups', 'storage/releases', 'storage/updates'] as $emptyDirectory) {
    $coreZip->addEmptyDir($emptyDirectory);
}
$coreZip->addFromString('release-manifest.json', json_encode([
    'app' => 'proma-pay',
    'type' => 'core-installer',
    'version' => $version,
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'built_at' => gmdate('c'),
    'source_base' => 'Proma Pay V' . $baselineVersion,
    'files' => $hashList($coreFiles),
    'excludes_operational_data' => true,
        'optional_plugins_included' => false,
        'production_availability_certified' => !$localQaBuild,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$coreZip->close();

$updateFiles = [];
foreach ($coreFiles as $archivePath => $sourcePath) {
    $hash = strtolower(hash_file('sha256', $sourcePath));
    if (($baselineFiles[$archivePath] ?? null) !== $hash) {
        $updateFiles[$archivePath] = $sourcePath;
    }
}
ksort($updateFiles, SORT_STRING);

$migrations = array_values(array_filter(array_keys($updateFiles), static fn (string $path): bool => strpos($path, 'database/migrations/') === 0));
$updateManifestFiles = [];
foreach ($updateFiles as $archivePath => $sourcePath) {
    $updateManifestFiles[] = [
        'source' => $archivePath,
        'target' => $archivePath,
        'sha256' => hash_file('sha256', $sourcePath),
        'expected_previous_sha256' => $baselineFiles[$archivePath] ?? null,
    ];
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی پایدار Proma Pay V' . $version,
    'version' => $version,
    'minimum_version' => $baselineVersion,
    'baseline' => [
        'version' => $baselineVersion,
        'archive' => basename($baselineCorePath),
        'release_manifest_sha256' => hash('sha256', $baselineRaw),
    ],
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $updateManifestFiles,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
    'production_availability_certified' => !$localQaBuild,
];
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$updatePath = $distUpdates . '/PromaPay-Update-v' . $version . '.zip';
$updateZip = $openZip($updatePath);
foreach ($updateFiles as $archivePath => $sourcePath) {
    $updateZip->addFile($sourcePath, $archivePath);
}
$updateZip->addFromString('proma-update.json', $updateJson);
$updateZip->addFromString('UPDATE_README.md', "# بروزرسانی Proma Pay V{$version}\n\nاین بسته تفاضلی فقط برای ارتقا از نسخه V{$baselineVersion} ساخته شده است. ابتدا نسخهٔ پشتیبان خودکار سامانه را بررسی کنید؛ سپس بسته را از مدیریت بروزرسانی بارگذاری و نصب کنید.\n\n- فایل‌های تغییرکرده: " . count($updateFiles) . "\n- migrationهای جدید: " . count($migrations) . "\n- داده‌های کاربران، storage، افزونه‌ها و config/database.php در این بسته قرار ندارند.\n");
$updateZip->close();
$updateManifestPath = $distUpdates . '/PromaPay-Update-v' . $version . '-manifest.json';
file_put_contents($updateManifestPath, $updateJson . PHP_EOL);

$pluginArchiveName = 'PromaAccounting';
$pluginVersion = $accountingVersion;
$pluginFiles = $walk($accountingRoot, static function (string $relativePath): bool {
    return !preg_match('/\.(?:zip|log|tmp)$/i', basename($relativePath));
}, $pluginArchiveName);
$pluginPath = $distPlugins . '/' . $pluginArchiveName . '-v' . $pluginVersion . '.zip';
$pluginZip = $openZip($pluginPath);
foreach ($pluginFiles as $archivePath => $sourcePath) {
    $pluginZip->addFile($sourcePath, $archivePath);
}
$pluginZip->addFromString($pluginArchiveName . '/build-manifest.json', json_encode([
    'plugin_id' => (string) ($accountingManifest['id'] ?? 'proma-accounting'),
    'version' => $pluginVersion,
    'requires_core' => (string) ($accountingManifest['requires_core'] ?? ''),
    'built_at' => gmdate('c'),
    'files' => $hashList($pluginFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$pluginZip->close();

// The plugin archive name is a contract with the update screen. Validate the
// version that was actually written into the archive so an edit made while a
// release is being assembled cannot leave behind a mismatched package.
$builtPluginZip = new ZipArchive();
if ($builtPluginZip->open($pluginPath) !== true) {
    throw new RuntimeException('Cannot verify the generated Proma Accounting archive.');
}
$builtPluginRaw = $builtPluginZip->getFromName($pluginArchiveName . '/plugin.json');
$builtPluginZip->close();
$builtPluginManifest = is_string($builtPluginRaw) ? json_decode($builtPluginRaw, true) : null;
if (!is_array($builtPluginManifest) || (string) ($builtPluginManifest['version'] ?? '') !== $pluginVersion) {
    @unlink($pluginPath);
    throw new RuntimeException('Proma Accounting changed during packaging; rerun the release build.');
}

$releaseNotesSource = $root . '/docs/releases/V' . $version . '.md';
$testReportSource = $root . '/docs/qa/V' . str_replace('.', '_', $version) . '_TEST_RESULTS.md';
$uiUxReportSource = $root . '/docs/releases/UI_UX_REDESIGN_REPORT-v' . $version . '.md';
$guarantorReportSource = $root . '/docs/releases/GUARANTOR_FIX_REPORT-v' . $version . '.md';
foreach ([$releaseNotesSource, $testReportSource, $uiUxReportSource, $guarantorReportSource] as $reportSource) {
    if (!is_file($reportSource)) {
        throw new RuntimeException('Release notes and final QA reports must exist before packaging: ' . $reportSource);
    }
}
$releaseNotesPath = $distCore . '/RELEASE_NOTES-v' . $version . '.md';
$testReportPath = $distCore . '/FULL_TEST_REPORT-v' . $version . '.md';
$uiUxReportPath = $distCore . '/UI_UX_REDESIGN_REPORT-v' . $version . '.md';
$guarantorReportPath = $distCore . '/GUARANTOR_FIX_REPORT-v' . $version . '.md';
copy($releaseNotesSource, $releaseNotesPath);
copy($testReportSource, $testReportPath);
copy($uiUxReportSource, $uiUxReportPath);
copy($guarantorReportSource, $guarantorReportPath);

$archives = [$corePath, $updatePath, $pluginPath];
foreach ($archives as $archivePath) {
    if (!is_file($archivePath) || filesize($archivePath) < 100) {
        throw new RuntimeException('Release archive validation failed: ' . $archivePath);
    }
}
$checksumLines = [];
foreach ($archives as $archivePath) {
    $checksumLines[] = hash_file('sha256', $archivePath) . '  ' . basename($archivePath);
}
$checksumPath = $distCore . '/SHA256SUMS.txt';
file_put_contents($checksumPath, implode(PHP_EOL, $checksumLines) . PHP_EOL);

echo json_encode([
    'version' => $version,
    'core' => $corePath,
    'update' => $updatePath,
    'update_manifest' => $updateManifestPath,
    'plugin' => $pluginPath,
    'checksums' => $checksumPath,
    'release_notes' => $releaseNotesPath,
    'test_report' => $testReportPath,
    'ui_ux_report' => $uiUxReportPath,
    'guarantor_report' => $guarantorReportPath,
    'update_files' => count($updateFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
