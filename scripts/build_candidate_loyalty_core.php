<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = '4.0.2';
$baselineVersion = '4.0.1';
$baselinePath = $root . '/dist/core/PromaPay-v' . $baselineVersion . '.zip';
$updatePath = $root . '/dist/candidates/v' . $version . '/updates/PromaPay-Update-v' . $version . '-from-v' . $baselineVersion . '.zip';
$outputDir = $root . '/dist/candidates/v' . $version . '/core';
$outputPath = $outputDir . '/PromaPay-v' . $version . '.zip';
$sidecarPath = $outputDir . '/PromaPay-v' . $version . '-manifest.json';

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
if (!is_file($baselinePath) || !is_file($updatePath)) {
    throw new RuntimeException('The official baseline archive or candidate update package is missing.');
}
if ((file_exists($outputPath) || file_exists($sidecarPath)) && !in_array('--replace', $argv, true)) {
    throw new RuntimeException('Candidate artifacts are immutable; an output already exists.');
}
if (in_array('--replace', $argv, true)) {
    foreach ([$outputPath, $sidecarPath] as $existingPath) {
        if (is_file($existingPath) && !unlink($existingPath)) {
            throw new RuntimeException('Cannot replace prior candidate output: ' . $existingPath);
        }
    }
}
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Cannot create the candidate Core output directory.');
}

$baseline = new ZipArchive();
if ($baseline->open($baselinePath) !== true) {
    throw new RuntimeException('Cannot open the official baseline Core archive.');
}
$baselineManifestRaw = $baseline->getFromName('release-manifest.json');
$baselineManifest = is_string($baselineManifestRaw)
    ? json_decode($baselineManifestRaw, true, 512, JSON_THROW_ON_ERROR)
    : null;
if (!is_array($baselineManifest)
    || ($baselineManifest['app'] ?? '') !== 'proma-pay'
    || ($baselineManifest['type'] ?? '') !== 'core-installer'
    || (string) ($baselineManifest['version'] ?? '') !== $baselineVersion) {
    $baseline->close();
    throw new RuntimeException('The official baseline release manifest is invalid.');
}

$baselineRecords = [];
foreach (($baselineManifest['files'] ?? []) as $record) {
    if (!is_array($record) || empty($record['path']) || empty($record['sha256'])) {
        $baseline->close();
        throw new RuntimeException('The baseline release manifest contains an invalid file record.');
    }
    $path = str_replace('\\', '/', (string) $record['path']);
    $bytes = $baseline->getFromName($path);
    if (!is_string($bytes)
        || !hash_equals(strtolower((string) $record['sha256']), hash('sha256', $bytes))
        || (isset($record['size']) && (int) $record['size'] !== strlen($bytes))) {
        $baseline->close();
        throw new RuntimeException('Official baseline checksum or size mismatch: ' . $path);
    }
    $baselineRecords[$path] = true;
    unset($bytes);
}
if (count($baselineRecords) !== count($baselineManifest['files'] ?? [])) {
    $baseline->close();
    throw new RuntimeException('The baseline release manifest has duplicate or missing paths.');
}

$update = new ZipArchive();
if ($update->open($updatePath) !== true) {
    $baseline->close();
    throw new RuntimeException('Cannot open the V' . $version . ' update package.');
}
$updateManifestRaw = $update->getFromName('proma-update.json');
$updateManifest = is_string($updateManifestRaw)
    ? json_decode($updateManifestRaw, true, 512, JSON_THROW_ON_ERROR)
    : null;
if (!is_array($updateManifest)
    || ($updateManifest['app'] ?? '') !== 'proma-pay'
    || (string) ($updateManifest['version'] ?? '') !== $version
    || (string) ($updateManifest['minimum_version'] ?? '') !== $baselineVersion
    || empty($updateManifest['candidate_only'])) {
    $update->close();
    $baseline->close();
    throw new RuntimeException('The customer loyalty update manifest is invalid.');
}

$overlays = [];
foreach (($updateManifest['files'] ?? []) as $record) {
    if (!is_array($record) || empty($record['source']) || empty($record['sha256'])) {
        $update->close();
        $baseline->close();
        throw new RuntimeException('The update package contains an invalid file record.');
    }
    $path = str_replace('\\', '/', (string) $record['source']);
    $bytes = $update->getFromName($path);
    if (!is_string($bytes) || !hash_equals(strtolower((string) $record['sha256']), hash('sha256', $bytes))) {
        $update->close();
        $baseline->close();
        throw new RuntimeException('Update package checksum mismatch: ' . $path);
    }
    $overlays[$path] = $bytes;
}
if (!isset($overlays['database/migrations/2026_10_08_customer_loyalty_points.sql'])) {
    $update->close();
    $baseline->close();
    throw new RuntimeException('The customer loyalty database migration is missing from the update.');
}
if (!isset($baselineRecords['database/proma-pay-install.sql'])) {
    $update->close();
    $baseline->close();
    throw new RuntimeException('The official Core baseline does not contain its fresh-install schema.');
}

$installSql = $baseline->getFromName('database/proma-pay-install.sql');
$loyaltyMigration = $overlays['database/migrations/2026_10_08_customer_loyalty_points.sql'];
if (!is_string($installSql)) {
    $update->close();
    $baseline->close();
    throw new RuntimeException('Cannot read the baseline fresh-install schema.');
}
if (strpos($installSql, 'customer_point_ledger') === false) {
    $installSql = rtrim($installSql) . "\n\n-- Customer loyalty points schema for V{$version}.\n" . $loyaltyMigration . "\n";
}
$overlays['database/proma-pay-install.sql'] = $installSql;

$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    $update->close();
    $baseline->close();
    throw new RuntimeException('Cannot create the full Core candidate archive.');
}
$records = [];
$seen = [];
$addedOverlays = [];
$baselineCount = 0;
for ($i = 0; $i < $baseline->numFiles; $i++) {
    $path = $baseline->getNameIndex($i);
    if (!is_string($path) || $path === 'release-manifest.json') {
        continue;
    }
    if (str_ends_with($path, '/')) {
        if (!$zip->addEmptyDir(rtrim($path, '/'))) {
            throw new RuntimeException('Cannot copy baseline directory: ' . $path);
        }
        continue;
    }
    if (isset($seen[$path])) {
        throw new RuntimeException('Duplicate path in baseline archive: ' . $path);
    }
    $bytes = array_key_exists($path, $overlays) ? $overlays[$path] : $baseline->getFromIndex($i);
    if (!is_string($bytes)) {
        throw new RuntimeException('Cannot read baseline archive entry: ' . $path);
    }
    if (!$zip->addFromString($path, $bytes)) {
        throw new RuntimeException('Cannot add full Core archive entry: ' . $path);
    }
    $seen[$path] = true;
    $addedOverlays[$path] = true;
    $records[] = ['path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    $baselineCount++;
    unset($bytes);
}

foreach ($overlays as $path => $bytes) {
    if (isset($addedOverlays[$path])) {
        continue;
    }
    if (isset($seen[$path])) {
        throw new RuntimeException('Unexpected duplicate overlay path: ' . $path);
    }
    if (!$zip->addFromString($path, $bytes)) {
        throw new RuntimeException('Cannot add new feature file to Core archive: ' . $path);
    }
    $seen[$path] = true;
    $records[] = ['path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
}
usort($records, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
$versionInfo = require $root . '/config/version.php';
$manifest = [
    'app' => 'proma-pay',
    'type' => 'core-installer',
    'version' => $version,
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.1'),
    'candidate_only' => true,
    'release_gate_status' => 'NOT_RUN',
    'production_availability_certified' => false,
    'built_at' => gmdate('c'),
    'source_base' => 'Proma Pay V' . $baselineVersion . ' official Core + Customer Loyalty V' . $version,
    'files' => $records,
];
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (!$zip->addFromString('release-manifest.json', $manifestJson)) {
    throw new RuntimeException('Cannot add the new Core release manifest.');
}
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize the full Core candidate archive.');
}
$update->close();
$baseline->close();
file_put_contents($sidecarPath, $manifestJson . PHP_EOL);

$verify = new ZipArchive();
if ($verify->open($outputPath) !== true) {
    throw new RuntimeException('Cannot reopen the full Core candidate archive.');
}
try {
    $embeddedRaw = $verify->getFromName('release-manifest.json');
    if (!is_string($embeddedRaw) || !hash_equals(hash('sha256', $manifestJson), hash('sha256', $embeddedRaw))) {
        throw new RuntimeException('Embedded Core release manifest mismatch.');
    }
    foreach ($records as $record) {
        $bytes = $verify->getFromName($record['path']);
        if (!is_string($bytes)
            || strlen($bytes) !== $record['size']
            || !hash_equals($record['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Full Core archive checksum mismatch: ' . $record['path']);
        }
        unset($bytes);
    }
    foreach (['install.php', 'database/proma-pay-install.sql', 'database/migrations/2026_10_08_customer_loyalty_points.sql', 'config/version.php'] as $required) {
        if ($verify->locateName($required) === false) {
            throw new RuntimeException('Required installation file is missing: ' . $required);
        }
    }
    $schema = $verify->getFromName('database/proma-pay-install.sql');
    if (!is_string($schema) || strpos($schema, 'customer_point_ledger') === false) {
        throw new RuntimeException('Fresh-install SQL does not contain the loyalty schema.');
    }
    $versionSource = $verify->getFromName('config/version.php');
    if (!is_string($versionSource) || !str_contains($versionSource, "'application' => '4.0.2'")) {
        throw new RuntimeException('Core archive does not identify itself as V4.0.2.');
    }
} finally {
    $verify->close();
}

$sumPath = dirname($outputDir) . '/SHA256SUMS.txt';
$sumLines = is_file($sumPath) ? preg_split('/\r?\n/', trim((string) file_get_contents($sumPath))) : [];
$sumLines = array_values(array_filter($sumLines, static fn ($line): bool => trim((string) $line) !== '' && !str_ends_with(trim((string) $line), '  PromaPay-v' . $version . '.zip')));
$sumLines[] = strtolower(hash_file('sha256', $outputPath)) . '  PromaPay-v' . $version . '.zip';
file_put_contents($sumPath, implode(PHP_EOL, $sumLines) . PHP_EOL);

echo json_encode([
    'version' => $version,
    'core_installer' => $outputPath,
    'sha256' => strtolower(hash_file('sha256', $outputPath)),
    'verified_files' => count($records),
    'baseline_files' => $baselineCount,
    'status' => 'FULL_CANDIDATE_PACKAGED_GATE_NOT_RUN',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
