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
echo $localQaBuild
    ? "[GATE] Running local release QA. Production infrastructure is not certified by this build.\n"
    : "[GATE] Running strict production release gate before packaging.\n";
$gateCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/release-gate.php');
if ($localQaBuild) {
    $gateCommand .= ' --local';
}
passthru($gateCommand, $gateCode);
if ($gateCode !== 0) {
    throw new RuntimeException('Release gate failed. No stable archive was produced.');
}

$distCore = $root . '/dist/core';
$distPlugins = $root . '/dist/plugins';
foreach ([$distCore, $distPlugins] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create release directory: ' . $directory);
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
    if (in_array($path, ['.env', 'config/database.php', 'installed.lock', '1.xlsx'], true)) {
        return true;
    }
    foreach ([
        '.git/', '.github/', '.agents/', '.codex/',
        'dist/', 'storage/', 'tmp/', 'plugins/', 'plugin-packages/', 'node_modules/',
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
        'source_base' => 'Proma Pay V1.4.2',
    'files' => $hashList($coreFiles),
    'excludes_operational_data' => true,
        'optional_plugins_included' => false,
        'production_availability_certified' => !$localQaBuild,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$coreZip->close();

$baseRef = trim((string) (getenv('PROMA_RELEASE_BASE_REF') ?: 'c2a5165'));
$gitExecutable = trim((string) (getenv('PROMA_GIT_BINARY') ?: 'git'));
$git = static function (array $arguments) use ($root, $gitExecutable): array {
    $command = array_merge([$gitExecutable, '--git-dir=' . $root . '/.git', '--work-tree=' . $root], $arguments);
    $stdoutPath = tempnam(sys_get_temp_dir(), 'proma-git-out-');
    $stderrPath = tempnam(sys_get_temp_dir(), 'proma-git-err-');
    if ($stdoutPath === false || $stderrPath === false) {
        throw new RuntimeException('Temporary Git inventory files could not be created.');
    }
    $process = proc_open($command, [1 => ['file', $stdoutPath, 'w'], 2 => ['file', $stderrPath, 'w']], $pipes, $root, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        @unlink($stdoutPath);
        @unlink($stderrPath);
        throw new RuntimeException('Git release inventory process could not be started.');
    }
    $code = proc_close($process);
    $output = (string) file_get_contents($stdoutPath);
    $error = (string) file_get_contents($stderrPath);
    @unlink($stdoutPath);
    @unlink($stderrPath);
    if ($code !== 0) {
        throw new RuntimeException('Git release inventory failed: ' . trim($output . PHP_EOL . $error));
    }
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: []), static fn (string $line): bool => $line !== ''));
};
$changed = $git(['diff', '--name-only', '--diff-filter=ACMRT', $baseRef, '--']);
$untracked = $git([
    'ls-files', '--others', '--exclude-standard', '--',
    'assets', 'config', 'controllers', 'core', 'database', 'helpers', 'models', 'views',
    '.htaccess', 'bootstrap.php', 'health-static.txt', 'index.php', 'install.php', 'installer.php', 'manifest.json', 'package.json', 'service-worker.js',
]);
$candidates = array_values(array_unique(array_merge($changed, $untracked, [
    'config/version.php',
    'health-static.txt',
    'manifest.json',
    'package.json',
    'service-worker.js',
    'install.php',
    'database/proma-pay-install.sql',
    'database/migrations/2026_07_01_backup_logs.sql',
    'database/migrations/2026_07_10_ecommerce_module.sql',
    'database/migrations/2026_07_13_contract_template_print_engine.sql',
    'database/migrations/2026_07_21_v1_4_1_interaction_state.sql',
    'database/migrations/2026_07_22_release_v143.sql',
    'database/migrations/2026_07_23_release_v144.sql',
])));
sort($candidates, SORT_STRING);

$runtimeRoots = ['assets/', 'config/', 'controllers/', 'core/', 'database/', 'helpers/', 'models/', 'views/'];
$runtimeRootFiles = ['.htaccess', 'bootstrap.php', 'health-static.txt', 'index.php', 'install.php', 'installer.php', 'manifest.json', 'package.json', 'service-worker.js'];
$updateFiles = [];
foreach ($candidates as $candidate) {
    $candidate = $normalize($candidate);
    $allowed = in_array($candidate, $runtimeRootFiles, true);
    foreach ($runtimeRoots as $prefix) {
        if (strpos($candidate, $prefix) === 0) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed || strpos($candidate, 'plugins/') === 0 || in_array(strtolower($candidate), ['config/database.php'], true)) {
        continue;
    }
    $source = $root . '/' . $candidate;
    if (is_file($source)) {
        $updateFiles[$candidate] = $source;
    }
}
foreach ([
    'config/version.php',
    'health-static.txt',
    'install.php',
    'database/proma-pay-install.sql',
    'database/migrations/2026_07_01_backup_logs.sql',
    'database/migrations/2026_07_10_ecommerce_module.sql',
    'database/migrations/2026_07_13_contract_template_print_engine.sql',
    'database/migrations/2026_07_21_v1_4_1_interaction_state.sql',
    'database/migrations/2026_07_22_release_v143.sql',
    'database/migrations/2026_07_23_release_v144.sql',
] as $requiredUpdateFile) {
    if (!isset($updateFiles[$requiredUpdateFile])) {
        throw new RuntimeException('Required update file is absent from release inventory: ' . $requiredUpdateFile);
    }
}

$migrations = array_values(array_filter(array_keys($updateFiles), static fn (string $path): bool => strpos($path, 'database/migrations/') === 0));
$updateManifestFiles = [];
foreach ($updateFiles as $archivePath => $sourcePath) {
    $updateManifestFiles[] = ['source' => $archivePath, 'target' => $archivePath, 'sha256' => hash_file('sha256', $sourcePath)];
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی پایدار Proma Pay V' . $version,
    'version' => $version,
    'minimum_version' => '1.4.2',
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $updateManifestFiles,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
    'production_availability_certified' => !$localQaBuild,
];
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$updatePath = $distCore . '/PromaPay-Update-v' . $version . '.zip';
$updateZip = $openZip($updatePath);
foreach ($updateFiles as $archivePath => $sourcePath) {
    $updateZip->addFile($sourcePath, $archivePath);
}
$updateZip->addFromString('proma-update.json', $updateJson);
$updateZip->close();
$updateManifestPath = $distCore . '/PromaPay-Update-v' . $version . '-manifest.json';
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

$releaseNotesSource = $root . '/docs/releases/V' . $version . '.md';
$testReportSource = $root . '/docs/qa/V' . str_replace('.', '_', $version) . '_TEST_RESULTS.md';
if (!is_file($releaseNotesSource) || !is_file($testReportSource)) {
    throw new RuntimeException('Release notes and final QA report must exist before packaging.');
}
$releaseNotesPath = $distCore . '/RELEASE_NOTES-v' . $version . '.md';
$testReportPath = $distCore . '/TEST_REPORT-v' . $version . '.md';
copy($releaseNotesSource, $releaseNotesPath);
copy($testReportSource, $testReportPath);

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

$versionSlug = str_replace('.', '-', $version);
$coreAliasPath = $distCore . '/proma-pay_v' . $versionSlug . '.zip';
$updateAliasPath = $distCore . '/proma-update_v' . $versionSlug . '.zip';
$updateManifestAliasPath = $distCore . '/proma-update_v' . $versionSlug . '-manifest.json';
foreach ([[$corePath, $coreAliasPath], [$updatePath, $updateAliasPath], [$updateManifestPath, $updateManifestAliasPath]] as [$source, $target]) {
    if (is_file($target) && !unlink($target)) {
        throw new RuntimeException('Cannot replace release alias: ' . $target);
    }
    if (!copy($source, $target)) {
        throw new RuntimeException('Cannot create release alias: ' . $target);
    }
}

echo json_encode([
    'version' => $version,
    'core' => $corePath,
    'update' => $updatePath,
    'update_manifest' => $updateManifestPath,
    'plugin' => $pluginPath,
    'checksums' => $checksumPath,
    'release_notes' => $releaseNotesPath,
    'test_report' => $testReportPath,
    'core_alias' => $coreAliasPath,
    'update_alias' => $updateAliasPath,
    'update_manifest_alias' => $updateManifestAliasPath,
    'update_files' => count($updateFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
