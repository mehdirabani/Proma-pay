<?php

declare(strict_types=1);

/**
 * Builds reviewable Core and differential Update ZIPs without claiming that
 * the full release gate passed. Candidate artifacts are isolated from the
 * immutable dist/core and dist/updates release directories.
 */

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    throw new RuntimeException('Candidate packaging requires a semantic Core version.');
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new RuntimeException('Candidate build ID must be a short alphanumeric suffix.');
}
$candidateRoot = $root . '/dist/candidates/v' . $version . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$releaseGateStatus = trim((string) getenv('PROMA_CANDIDATE_GATE_STATUS')) ?: 'NOT_RUN';
if (!in_array($releaseGateStatus, ['NOT_RUN', 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED', 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING', 'RELEASE_GATE_PASSED'], true)) {
    throw new RuntimeException('Unsupported candidate release gate status.');
}
$sourceStatus = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain=v1 --untracked-files=all 2>NUL'));
$sourceStatusEntries = $sourceStatus === '' ? [] : preg_split('/\r?\n/', $sourceStatus);
$sourceState = [
    'source_tree_clean' => !$sourceStatusEntries,
    'source_status_entry_count' => count($sourceStatusEntries),
    'source_status_sha256' => hash('sha256', $sourceStatus),
];
$coreDirectory = $candidateRoot . '/core';
$updateDirectory = $candidateRoot . '/updates';
foreach ([$coreDirectory, $updateDirectory] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create candidate directory: ' . $directory);
    }
}

$corePath = $coreDirectory . '/PromaPay-v' . $version . '.zip';
$updatePath = $updateDirectory . '/PromaPay-Update-v' . $version . '.zip';
$updateManifestPath = $updateDirectory . '/PromaPay-Update-v' . $version . '-manifest.json';
$checksumPath = $candidateRoot . '/SHA256SUMS.txt';
foreach ([$corePath, $updatePath, $updateManifestPath, $checksumPath] as $path) {
    if (file_exists($path)) {
        throw new RuntimeException('Candidate artifacts are immutable; existing path: ' . $path);
    }
}

$normalize = static fn (string $path): string => trim(str_replace('\\', '/', $path), '/');
$walk = static function (string $directory, callable $accept, string $prefix = '', ?callable $pruneDirectory = null) use ($normalize): array {
    $files = [];
    $directoryIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
    $filteredIterator = new RecursiveCallbackFilterIterator(
        $directoryIterator,
        static function (SplFileInfo $item) use ($directory, $pruneDirectory, $normalize): bool {
            if (!$item->isDir() || $pruneDirectory === null) {
                return true;
            }

            $relativeDirectory = $normalize(substr($item->getPathname(), strlen($directory)));
            return !$pruneDirectory($relativeDirectory);
        }
    );
    $iterator = new RecursiveIteratorIterator($filteredIterator);
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
$hashList = static function (array $files): array {
    $items = [];
    foreach ($files as $archivePath => $sourcePath) {
        $items[] = [
            'path' => $archivePath,
            'size' => filesize($sourcePath),
            'sha256' => strtolower(hash_file('sha256', $sourcePath)),
        ];
    }
    return $items;
};
$openNewZip = static function (string $path): ZipArchive {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Cannot create immutable candidate ZIP: ' . $path);
    }
    return $zip;
};

// Match the official Core inventory rules so the candidate is structurally
// equivalent to a release build, while preserving operational data locally.
$coreExcluded = static function (string $relativePath) use ($normalize): bool {
    $path = strtolower($normalize($relativePath));
    if ($path === '') {
        return false;
    }
    if (preg_match('#^(?:booklet|contact-modal)-v[\w.-]*\.(?:png|pdf)$#i', $path)) {
        return true;
    }
    if (in_array($path, ['.env', 'config/database.php', 'installed.lock', '1.xlsx', 'design-qa.md', 'laravel/public/hot', 'laravel/.phpunit.result.cache'], true)
        || preg_match('#^v\d+\.\d+\.\d+-implementation-state\.md$#i', $path)
        || preg_match('#(?:^|/)\.env(?:\.[^/]*)?$#i', $path)) {
        return true;
    }
    // V4.0.2's official Core inventory includes this one release note under
    // docs/. Keep it in the next Core package so the differential updater
    // does not silently remove a baseline file.
    if ($path === 'docs/releases/release_notes-v4.0.2.md') {
        return false;
    }
    foreach ([
        '.git/', '.github/', '.agents/', '.appsec-forge/', '.codex/', '.playwright-cli/', '.project-graph/', 'skill/', 'laravel/',
        'backups/', 'dist/', 'storage/', 'uploads/', 'tmp/', 'output/', 'plugins/', 'plugin-packages/', 'node_modules/',
        'docs/', 'electron/', 'html/', 'scripts/', 'tests/', 'tools/',
        'laravel/.playwright-cli/', 'laravel/bootstrap/cache/', 'laravel/docs/', 'laravel/node_modules/',
        'laravel/releases/', 'laravel/storage/', 'laravel/tests/',
    ] as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }
    return (bool) preg_match('/\.(?:zip|log|bak|sql\.gz|sqlite|db|tmp)$/i', basename($path));
};
$pruneCoreDirectory = static function (string $relativeDirectory) use ($coreExcluded): bool {
    // The Core inventory keeps one historical note beneath docs/releases/;
    // scan those two directories so that the file-level exception can apply.
    $path = strtolower(trim(str_replace('\\', '/', $relativeDirectory), '/'));
    if ($path === 'docs' || $path === 'docs/releases') {
        return false;
    }

    return $coreExcluded(rtrim($relativeDirectory, '/') . '/__candidate_walk_probe__');
};
$coreFiles = $walk(
    $root,
    static fn (string $relativePath): bool => !$coreExcluded($relativePath),
    '',
    $pruneCoreDirectory
);
$runtimeAssetRoots = ['css', 'fonts', 'images', 'js', 'svg'];
$runtimeAssets = $walk(
    $root . '/html/RTL/assets',
    static function (string $relativePath) use ($runtimeAssetRoots): bool {
        $relativePath = strtolower($relativePath);
        $rootName = strtok($relativePath, '/');
        return $relativePath !== ''
            && in_array($rootName, $runtimeAssetRoots, true)
            && !str_ends_with($relativePath, '.map');
    },
    'html/RTL/assets'
);
$coreFiles = array_merge($coreFiles, $runtimeAssets);
ksort($coreFiles, SORT_STRING);

// The exact preceding installer supplies the checksum baseline for a true
// differential update. Validate every indexed baseline file before using it.
$baselineCandidates = [];
if ($explicitBaseline = trim((string) getenv('PROMA_BASELINE_ARCHIVE'))) {
    $resolvedBaseline = realpath($explicitBaseline);
    if ($resolvedBaseline === false || !is_file($resolvedBaseline)) {
        throw new RuntimeException('The explicitly selected baseline Core archive does not exist: ' . $explicitBaseline);
    }
    $baselineCandidates['0.0.0'] = $resolvedBaseline;
} else {
    $baselinePriorities = [];
    foreach (glob($root . '/dist/core/PromaPay-v*.zip') ?: [] as $candidate) {
        if (preg_match('/PromaPay-v(\d+\.\d+\.\d+)\.zip$/', $candidate, $matches)
            && version_compare($matches[1], $version, '<')) {
            $baselineCandidates[$matches[1]] = $candidate;
            $baselinePriorities[$matches[1]] = 0;
        }
    }
    foreach (glob($root . '/dist/core/archive/v*/v*/PromaPay-v*.zip') ?: [] as $candidate) {
        if (preg_match('/PromaPay-v(\d+\.\d+\.\d+)\.zip$/', $candidate, $matches)
            && version_compare($matches[1], $version, '<')
            && !isset($baselineCandidates[$matches[1]])) {
            $baselineCandidates[$matches[1]] = $candidate;
            $baselinePriorities[$matches[1]] = -1;
        }
    }
    // Prefer candidate installers over stable Core packages. Within each
    // candidate group, exact version folders outrank build-specific snapshots.
    $candidateArchivePatterns = [
        $root . '/dist/candidates/v*/core/PromaPay-v*.zip',
        $root . '/dist/candidates/archive/v*/core/PromaPay-v*.zip',
        $root . '/dist/candidates/archive/v*/v*/core/PromaPay-v*.zip',
    ];
    foreach ($candidateArchivePatterns as $pattern) {
        foreach (glob($pattern) ?: [] as $candidate) {
            if (preg_match('/PromaPay-v(\d+\.\d+\.\d+)\.zip$/', $candidate, $matches)
                && version_compare($matches[1], $version, '<')) {
                $isArchived = strpos(str_replace('\\', '/', $candidate), '/dist/candidates/archive/') !== false;
                $candidateFolder = basename(dirname(dirname($candidate)));
                $isExactVersionFolder = $candidateFolder === 'v' . $matches[1];
                $priority = $isArchived
                    ? ($isExactVersionFolder ? 2 : 1)
                    : ($isExactVersionFolder ? 4 : 3);
                if ($priority > ($baselinePriorities[$matches[1]] ?? -1)) {
                    $baselineCandidates[$matches[1]] = $candidate;
                    $baselinePriorities[$matches[1]] = $priority;
                }
            }
        }
    }
}
if (!$baselineCandidates) {
    throw new RuntimeException('No preceding Core installer exists for the differential update.');
}
uksort($baselineCandidates, 'version_compare');
$baselinePath = end($baselineCandidates);
$baselineZip = new ZipArchive();
if ($baselineZip->open($baselinePath) !== true) {
    throw new RuntimeException('Cannot open update baseline: ' . $baselinePath);
}
try {
    $baselineRaw = $baselineZip->getFromName('release-manifest.json');
    $baselineManifest = is_string($baselineRaw) ? json_decode($baselineRaw, true, 512, JSON_THROW_ON_ERROR) : null;
    if (!is_array($baselineManifest)
        || ($baselineManifest['app'] ?? '') !== 'proma-pay'
        || ($baselineManifest['type'] ?? '') !== 'core-installer') {
        throw new RuntimeException('Update baseline has no valid Core release manifest.');
    }
    $baselineVersion = (string) ($baselineManifest['version'] ?? '');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $baselineVersion) || !version_compare($baselineVersion, $version, '<')) {
        throw new RuntimeException('Update baseline version is invalid or not older than the candidate.');
    }
    $baselineFiles = [];
    $preservedBaselineContents = [];
$omittedNonRuntimeAssetRoots = ['ajax', 'audio', 'json', 'pdf', 'pug', 'scss', 'video'];
$isOmittedPackagingPath = static function (string $path) use ($normalize, $omittedNonRuntimeAssetRoots): bool {
    $path = strtolower($normalize($path));
    if (strpos($path, 'laravel/') === 0 || strpos($path, 'backups/') === 0 || strpos($path, '.appsec-forge/') === 0) {
        return true;
    }
    foreach ($omittedNonRuntimeAssetRoots as $assetRoot) {
        if (strpos($path, 'html/rtl/assets/' . $assetRoot . '/') === 0) {
            return true;
        }
    }
    return false;
};
    foreach (($baselineManifest['files'] ?? []) as $file) {
        if (!is_array($file) || empty($file['path']) || empty($file['sha256'])) {
            continue;
        }
        $path = $normalize((string) $file['path']);
        $content = $baselineZip->getFromName($path);
        if (!is_string($content) || !hash_equals(strtolower((string) $file['sha256']), hash('sha256', $content))) {
            throw new RuntimeException('Update baseline content does not match its checksum inventory: ' . $path);
        }
        $baselineFiles[$path] = strtolower((string) $file['sha256']);
        // The in-app updater cannot delete files. Keep baseline entries that
        // are absent from the current source in the Core candidate so a fresh
        // install and an in-place update produce the same file inventory.
        if (!isset($coreFiles[$path]) && !$isOmittedPackagingPath($path)) {
            $preservedBaselineContents[$path] = $content;
        }
    }
} finally {
    $baselineZip->close();
}
if (!$baselineFiles) {
    throw new RuntimeException('Update baseline has an empty checksum inventory.');
}

$temporaryBaselineFiles = [];
register_shutdown_function(static function () use (&$temporaryBaselineFiles): void {
    foreach ($temporaryBaselineFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});
foreach ($preservedBaselineContents as $archivePath => $content) {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'proma-v7-baseline-');
    if ($temporaryPath === false || file_put_contents($temporaryPath, $content) !== strlen($content)) {
        if (is_string($temporaryPath) && is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
        throw new RuntimeException('Cannot preserve a baseline file that the updater cannot remove: ' . $archivePath);
    }
    $temporaryBaselineFiles[] = $temporaryPath;
    $coreFiles[$archivePath] = $temporaryPath;
}
ksort($coreFiles, SORT_STRING);

$currentPaths = array_fill_keys(array_keys($coreFiles), true);
$removedPaths = array_values(array_filter(
    array_diff(array_keys($baselineFiles), array_keys($currentPaths)),
    static fn (string $path): bool => !$isOmittedPackagingPath($path)
));
if ($removedPaths) {
    throw new RuntimeException('The updater cannot remove files; candidate has runtime deletions: ' . implode(', ', $removedPaths));
}

$builtAt = gmdate('c');
$sourceRevision = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>NUL'));
$sourceRevision = $sourceRevision !== '' ? $sourceRevision : 'unknown';
$manifestFiles = $hashList($coreFiles);
$coreManifest = [
    'app' => 'proma-pay',
    'type' => 'core-installer',
    'version' => $version,
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'built_at' => $builtAt,
    'source_revision' => $sourceRevision,
    ...$sourceState,
    'source_base' => 'Proma Pay V' . $baselineVersion,
    'candidate_only' => true,
    'release_gate_status' => $releaseGateStatus,
    'production_availability_certified' => false,
    'files' => $manifestFiles,
    'preserved_baseline_files' => array_keys($preservedBaselineContents),
    'omitted_non_runtime_asset_roots' => $omittedNonRuntimeAssetRoots,
    'omitted_separate_laravel_app' => true,
    'excludes_operational_data' => true,
    'optional_plugins_included' => false,
];

$coreZip = $openNewZip($corePath);
foreach ($coreFiles as $archivePath => $sourcePath) {
    $coreZip->addFile($sourcePath, $archivePath);
}
foreach (['plugins', 'storage/cache', 'storage/logs', 'storage/uploads', 'storage/backups', 'storage/releases', 'storage/updates'] as $emptyDirectory) {
    $coreZip->addEmptyDir($emptyDirectory);
}
$coreZip->addFromString('release-manifest.json', json_encode($coreManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if (!$coreZip->close()) {
    throw new RuntimeException('Cannot finalize candidate Core archive.');
}

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
        'sha256' => strtolower(hash_file('sha256', $sourcePath)),
        'expected_previous_sha256' => $baselineFiles[$archivePath] ?? null,
    ];
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بستهٔ کاندید بروزرسانی Proma Pay V' . $version,
    'version' => $version,
    'minimum_version' => $baselineVersion,
    'baseline' => [
        'version' => $baselineVersion,
        'archive' => basename($baselinePath),
        'release_manifest_sha256' => hash('sha256', $baselineRaw),
    ],
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'candidate_only' => true,
    'release_gate_status' => $releaseGateStatus,
    ...$sourceState,
    'production_availability_certified' => false,
    'files' => $updateManifestFiles,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
];
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$updateZip = $openNewZip($updatePath);
foreach ($updateFiles as $archivePath => $sourcePath) {
    $updateZip->addFile($sourcePath, $archivePath);
}
$updateZip->addFromString('proma-update.json', $updateJson);
$statusDescription = $releaseGateStatus === 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED'
    ? 'آزمون‌های ایستا و واحد گذشتند؛ آزمون‌های پایگاه‌داده و HTTP به‌دلیل نبود محیط QA ایزوله اجرا نشدند.'
    : ($releaseGateStatus === 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING'
        ? 'گیت ایستا/واحد و آزمون‌های یکپارچهٔ منتخب روی MySQL 8.4 ایزوله گذشتند؛ گیت کامل پایگاه‌داده و HTTP هنوز اجرا نشده است.'
        : ($releaseGateStatus === 'RELEASE_GATE_PASSED' ? 'گیت QA کامل گذشت؛ گواهی دسترس‌پذیری تولید جداگانه بررسی می‌شود.' : 'گیت QA هنوز اجرا نشده است.'));
$updateZip->addFromString('UPDATE_README.md', "# بستهٔ کاندید بروزرسانی Proma Pay V{$version}\n\n{$statusDescription} این بسته هنوز برای نصب عملیاتی تأیید نشده است. مبنای تفاضلی: V{$baselineVersion}.\n\n- فایل‌های تغییرکرده: " . count($updateFiles) . "\n- migrationهای جدید: " . count($migrations) . "\n- config/database.php، افزونه‌ها و داده‌های storage در این بسته نیستند.\n");
if (!$updateZip->close()) {
    throw new RuntimeException('Cannot finalize candidate Update archive.');
}
if (file_put_contents($updateManifestPath, $updateJson . PHP_EOL) === false) {
    throw new RuntimeException('Cannot write candidate Update sidecar manifest.');
}

$candidateReadme = "# Proma Pay V{$version} — Candidate artifacts\n\n{$statusDescription} این بسته‌ها برای نصب عملیاتی یا توزیع عمومی تأیید نشده‌اند.\n\n- Baseline آپدیت پیش‌فرض: V{$baselineVersion}\n- Core runtime files: " . count($coreFiles) . "\n- Update files: " . count($updateFiles) . "\n- New migrations: " . count($migrations) . "\n- QA gate: {$releaseGateStatus}\n- Production certified: no\n- Source revision: {$sourceRevision}\n- Source tree clean: " . ($sourceState['source_tree_clean'] ? 'yes' : 'no') . "\n- Source status entries: " . $sourceState['source_status_entry_count'] . "\n- Source status SHA-256: " . $sourceState['source_status_sha256'] . "\n";
if (file_put_contents($candidateRoot . '/README.md', $candidateReadme) === false) {
    throw new RuntimeException('Cannot write candidate status report.');
}
$checksumLines = [
    hash_file('sha256', $corePath) . '  ' . basename($corePath),
    hash_file('sha256', $updatePath) . '  ' . basename($updatePath),
];
if (file_put_contents($checksumPath, implode(PHP_EOL, $checksumLines) . PHP_EOL) === false) {
    throw new RuntimeException('Cannot write candidate checksums.');
}

// Reopen both archives and verify every indexed target before declaring the
// packaging operation complete. This validates packaging, not application QA.
$coreCheck = new ZipArchive();
if ($coreCheck->open($corePath) !== true) {
    throw new RuntimeException('Candidate Core archive cannot be reopened.');
}
try {
    $storedManifest = json_decode((string) $coreCheck->getFromName('release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($storedManifest['candidate_only'] ?? false) !== true || ($storedManifest['release_gate_status'] ?? '') !== $releaseGateStatus) {
        throw new RuntimeException('Candidate Core manifest status is invalid.');
    }
    foreach ($storedManifest['files'] as $file) {
        $content = $coreCheck->getFromName((string) $file['path']);
        if (!is_string($content) || !hash_equals(strtolower((string) $file['sha256']), hash('sha256', $content))) {
            throw new RuntimeException('Candidate Core checksum mismatch: ' . (string) $file['path']);
        }
    }
} finally {
    $coreCheck->close();
}
$updateCheck = new ZipArchive();
if ($updateCheck->open($updatePath) !== true) {
    throw new RuntimeException('Candidate Update archive cannot be reopened.');
}
try {
    $storedUpdate = json_decode((string) $updateCheck->getFromName('proma-update.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($storedUpdate['candidate_only'] ?? false) !== true || ($storedUpdate['release_gate_status'] ?? '') !== $releaseGateStatus) {
        throw new RuntimeException('Candidate Update manifest status is invalid.');
    }
    foreach ($storedUpdate['files'] as $file) {
        $content = $updateCheck->getFromName((string) $file['source']);
        if (!is_string($content) || !hash_equals(strtolower((string) $file['sha256']), hash('sha256', $content))) {
            throw new RuntimeException('Candidate Update checksum mismatch: ' . (string) $file['source']);
        }
    }
} finally {
    $updateCheck->close();
}

echo json_encode([
    'version' => $version,
    'status' => 'CANDIDATE_PACKAGED_' . $releaseGateStatus,
    'baseline' => $baselineVersion,
    'core' => $corePath,
    'core_sha256' => hash_file('sha256', $corePath),
    'core_files' => count($coreFiles),
    'update' => $updatePath,
    'update_sha256' => hash_file('sha256', $updatePath),
    'update_files' => count($updateFiles),
    'migrations' => $migrations,
    'production_availability_certified' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
