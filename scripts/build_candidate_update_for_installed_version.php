<?php

declare(strict_types=1);

// Build one exact-baseline update candidate from an existing Core installer.
$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$baselineVersion = (string) ($argv[1] ?? '');
if (!preg_match('/^\d+\.\d+\.\d+$/', $baselineVersion) || !version_compare($baselineVersion, $version, '<')) {
    throw new InvalidArgumentException('Pass an older semantic Core version, for example 2.0.4.');
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new InvalidArgumentException('Candidate build ID must be a short alphanumeric suffix.');
}
$candidateRoot = $root . '/dist/candidates/v' . $version . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$explicitBaseline = trim((string) getenv('PROMA_BASELINE_ARCHIVE'));
$baselinePath = null;
if ($explicitBaseline !== '') {
    $baselinePath = realpath($explicitBaseline);
    if ($baselinePath === false || !is_file($baselinePath)) {
        throw new RuntimeException('The explicitly selected baseline Core archive does not exist: ' . $explicitBaseline);
    }
} else {
    $baselineCandidates = [
        $root . '/dist/core/PromaPay-v' . $baselineVersion . '.zip',
        $root . '/dist/candidates/v' . $baselineVersion . '/core/PromaPay-v' . $baselineVersion . '.zip',
    ];
    foreach ($baselineCandidates as $candidatePath) {
        if (is_file($candidatePath)) {
            $baselinePath = $candidatePath;
            break;
        }
    }
}
if ($baselinePath === null) {
    throw new RuntimeException('No installer exists for baseline V' . $baselineVersion . '.');
}
$corePath = $candidateRoot . '/core/PromaPay-v' . $version . '.zip';
$outputDirectory = $candidateRoot . '/updates';
$suffix = '-from-v' . $baselineVersion;
$updatePath = $outputDirectory . '/PromaPay-Update-v' . $version . $suffix . '.zip';
$manifestPath = $outputDirectory . '/PromaPay-Update-v' . $version . $suffix . '-manifest.json';
foreach ([$updatePath, $manifestPath] as $path) {
    if (file_exists($path)) {
        throw new RuntimeException('Candidate artifacts are immutable; existing path: ' . $path);
    }
}
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Cannot create candidate update directory.');
}

$baselineZip = new ZipArchive();
$coreZip = new ZipArchive();
if ($baselineZip->open($baselinePath) !== true || $coreZip->open($corePath) !== true) {
    throw new RuntimeException('Cannot open baseline or current Core installer.');
}
try {
    $baselineRaw = $baselineZip->getFromName('release-manifest.json');
    $coreRaw = $coreZip->getFromName('release-manifest.json');
    $baselineManifest = is_string($baselineRaw) ? json_decode($baselineRaw, true, 512, JSON_THROW_ON_ERROR) : null;
    $coreManifest = is_string($coreRaw) ? json_decode($coreRaw, true, 512, JSON_THROW_ON_ERROR) : null;
    if (!is_array($baselineManifest) || ($baselineManifest['app'] ?? '') !== 'proma-pay'
        || ($baselineManifest['type'] ?? '') !== 'core-installer'
        || (string) ($baselineManifest['version'] ?? '') !== $baselineVersion) {
        throw new RuntimeException('Baseline installer manifest is invalid.');
    }
    if (!is_array($coreManifest) || (string) ($coreManifest['version'] ?? '') !== $version) {
        throw new RuntimeException('Current Core candidate manifest is invalid.');
    }
    $releaseGateStatus = (string) ($coreManifest['release_gate_status'] ?? 'NOT_RUN');
    if (!in_array($releaseGateStatus, ['NOT_RUN', 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED', 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING', 'RELEASE_GATE_PASSED'], true)) {
        throw new RuntimeException('Current Core candidate release gate status is invalid.');
    }

    $baselineHashes = [];
    foreach (($baselineManifest['files'] ?? []) as $file) {
        if (!is_array($file) || empty($file['path']) || empty($file['sha256'])) {
            continue;
        }
        $path = str_replace('\\', '/', (string) $file['path']);
        $bytes = $baselineZip->getFromName($path);
        if (!is_string($bytes) || !hash_equals(strtolower((string) $file['sha256']), hash('sha256', $bytes))) {
            throw new RuntimeException('Baseline file checksum mismatch: ' . $path);
        }
        $baselineHashes[$path] = strtolower((string) $file['sha256']);
    }
    if (!$baselineHashes) {
        throw new RuntimeException('Baseline has no file inventory.');
    }

    $currentHashes = [];
    $updateFiles = [];
    foreach (($coreManifest['files'] ?? []) as $file) {
        $path = str_replace('\\', '/', (string) ($file['path'] ?? ''));
        $expected = strtolower((string) ($file['sha256'] ?? ''));
        if ($path === '' || $expected === '') {
            throw new RuntimeException('Current Core manifest contains an invalid file record.');
        }
        $bytes = $coreZip->getFromName($path);
        if (!is_string($bytes) || !hash_equals($expected, hash('sha256', $bytes))) {
            throw new RuntimeException('Current Core file checksum mismatch: ' . $path);
        }
        $currentHashes[$path] = $expected;
        if (($baselineHashes[$path] ?? null) !== $expected) {
            $updateFiles[$path] = $bytes;
        }
    }
    $removed = array_values(array_diff(array_keys($baselineHashes), array_keys($currentHashes)));
    if ($removed) {
        throw new RuntimeException('Updater cannot remove baseline runtime files: ' . implode(', ', $removed));
    }
    if (!$updateFiles) {
        throw new RuntimeException('This baseline has no changed files.');
    }
} finally {
    $baselineZip->close();
    $coreZip->close();
}

ksort($updateFiles, SORT_STRING);
$updateEntries = [];
$migrations = [];
foreach ($updateFiles as $path => $bytes) {
    $updateEntries[] = [
        'source' => $path,
        'target' => $path,
        'sha256' => hash('sha256', $bytes),
        'expected_previous_sha256' => $baselineHashes[$path] ?? null,
    ];
    if (strpos($path, 'database/migrations/') === 0) {
        $migrations[] = $path;
    }
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بستهٔ کاندید بروزرسانی Proma Pay V' . $version . ' از V' . $baselineVersion,
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
    'production_availability_certified' => false,
    'files' => $updateEntries,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
];
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$zip = new ZipArchive();
if ($zip->open($updatePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Cannot create candidate update archive.');
}
foreach ($updateFiles as $path => $bytes) {
    $zip->addFromString($path, $bytes);
}
$zip->addFromString('proma-update.json', $updateJson);
$statusDescription = $releaseGateStatus === 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING'
    ? 'گیت ایستا/واحد و آزمون‌های یکپارچهٔ منتخب روی MySQL 8.4 ایزوله گذشتند؛ گیت کامل پایگاه‌داده و HTTP هنوز اجرا نشده است.'
    : ($releaseGateStatus === 'RELEASE_GATE_PASSED'
        ? 'گیت QA کامل گذشت؛ گواهی دسترس‌پذیری تولید جداگانه بررسی می‌شود.'
        : 'این بسته کاندید بررسی محلی است و نصب عملیاتی آن تأیید نشده است.');
$zip->addFromString('UPDATE_README.md', "# بستهٔ کاندید بروزرسانی Proma Pay V{$version} از V{$baselineVersion}\n\n{$statusDescription}\n\n- فایل‌های تغییرکرده: " . count($updateEntries) . "\n- Migrationها: " . count($migrations) . "\n- وضعیت گیت: {$releaseGateStatus}\n- نصب عملیاتی تأیید نشده است.\n");
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize candidate update archive.');
}
if (file_put_contents($manifestPath, $updateJson . PHP_EOL) === false) {
    throw new RuntimeException('Cannot write exact-baseline update manifest.');
}

$verify = new ZipArchive();
if ($verify->open($updatePath) !== true) {
    throw new RuntimeException('Cannot reopen candidate update archive.');
}
foreach ($updateEntries as $file) {
    $bytes = $verify->getFromName($file['source']);
    if (!is_string($bytes) || !hash_equals($file['sha256'], hash('sha256', $bytes))) {
        $verify->close();
        throw new RuntimeException('Candidate update checksum mismatch: ' . $file['source']);
    }
}
$storedManifest = $verify->getFromName('proma-update.json');
$verify->close();
if (!is_string($storedManifest) || !hash_equals(hash('sha256', $updateJson), hash('sha256', $storedManifest))) {
    throw new RuntimeException('Embedded candidate update manifest mismatch.');
}

$checksumPath = $candidateRoot . '/SHA256SUMS.txt';
$lines = is_file($checksumPath) ? preg_split('/\r?\n/', trim((string) file_get_contents($checksumPath))) : [];
$lines[] = strtolower(hash_file('sha256', $updatePath)) . '  ' . basename($updatePath);
if (file_put_contents($checksumPath, implode(PHP_EOL, array_filter($lines, static fn ($line): bool => $line !== '')) . PHP_EOL) === false) {
    throw new RuntimeException('Cannot update candidate checksum manifest.');
}
$readmePath = $candidateRoot . '/README.md';
$readme = is_file($readmePath) ? (string) file_get_contents($readmePath) : '';
$readme .= '- Update from V' . $baselineVersion . ': ' . basename($updatePath) . "\n";
if (file_put_contents($readmePath, $readme) === false) {
    throw new RuntimeException('Cannot update candidate README.');
}

echo json_encode([
    'version' => $version,
    'baseline' => $baselineVersion,
    'update' => $updatePath,
    'sha256' => strtolower(hash_file('sha256', $updatePath)),
    'files' => count($updateEntries),
    'migrations' => count($migrations),
    'release_gate_status' => $releaseGateStatus,
    'status' => 'EXACT_BASELINE_UPDATE_PACKAGED',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

