<?php

declare(strict_types=1);

/**
 * Build a complete Core replacement update without installed-file hash pins.
 * User data, DB credentials, and separately versioned plugins are preserved.
 */
$root = dirname(__DIR__);
require_once $root . '/scripts/release_artifact_paths.php';
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '');
$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new RuntimeException('Candidate build ID must be a short alphanumeric suffix.');
}
$minimumVersion = '2.0.0';
$baselineVersion = $minimumVersion;
$candidateRoot = $root . '/dist/candidates/v' . $version . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$corePath = $candidateRoot . '/core/PromaPay-v' . $version . '.zip';
$baselineCandidates = [
    ReleaseArtifactPaths::core($root, $baselineVersion),
    $root . '/dist/candidates/v' . $baselineVersion . '/core/PromaPay-v' . $baselineVersion . '.zip',
    $root . '/dist/candidates/archive/v' . explode('.', $baselineVersion, 2)[0] . '/v' . $baselineVersion . '/core/PromaPay-v' . $baselineVersion . '.zip',
];
$baselineCandidates = array_merge(
    $baselineCandidates,
    glob($root . '/dist/candidates/archive/v' . explode('.', $baselineVersion, 2)[0] . '/v' . $baselineVersion . '-*/core/PromaPay-v' . $baselineVersion . '.zip') ?: [],
    glob($root . '/dist/candidates/archive/v' . $baselineVersion . '-*/core/PromaPay-v' . $baselineVersion . '.zip') ?: []
);
$baselinePath = '';
foreach ($baselineCandidates as $candidatePath) {
    if (is_file($candidatePath)) {
        $baselinePath = $candidatePath;
        break;
    }
}
$outputDirectory = $candidateRoot . '/updates';
$artifactStem = 'PromaPay-Universal-Core-Update-v' . $version . '-from-v' . $minimumVersion;
$outputPath = $outputDirectory . '/' . $artifactStem . '.zip';
$manifestPath = $outputDirectory . '/' . $artifactStem . '-manifest.json';
$readmePath = $outputDirectory . '/' . $artifactStem . '-README.md';
$checksumPath = $outputDirectory . '/' . $artifactStem . '.sha256';

if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !version_compare($version, $minimumVersion, '>')) {
    throw new RuntimeException('Candidate version must be newer than the supported baseline ' . $minimumVersion . '.');
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
if (!is_file($corePath)) {
    throw new RuntimeException('Required Core archive is missing: ' . $corePath);
}
if ($baselinePath === '') {
    throw new RuntimeException('Required baseline Core archive is missing for V' . $baselineVersion . '.');
}
foreach ([$outputPath, $manifestPath, $readmePath, $checksumPath] as $existingPath) {
    if (file_exists($existingPath)) {
        throw new RuntimeException('Universal candidate artifacts are immutable; existing path: ' . $existingPath);
    }
}
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Cannot create universal update directory.');
}

$readManifest = static function (string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Cannot open Core archive: ' . $path);
    }
    try {
        $raw = $zip->getFromName('release-manifest.json');
        $manifest = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($manifest) || ($manifest['app'] ?? '') !== 'proma-pay' || ($manifest['type'] ?? '') !== 'core-installer') {
            throw new RuntimeException('Core release manifest is invalid: ' . $path);
        }
        return [$manifest, $raw];
    } finally {
        $zip->close();
    }
};

[$coreManifest, $coreManifestRaw] = $readManifest($corePath);
[$baselineManifest, $baselineManifestRaw] = $readManifest($baselinePath);
if ((string) ($coreManifest['version'] ?? '') !== $version || (string) ($baselineManifest['version'] ?? '') !== $baselineVersion) {
    throw new RuntimeException('Core archive version does not match its candidate path.');
}
$releaseGateStatus = (string) ($coreManifest['release_gate_status'] ?? 'NOT_RUN');
if (!in_array($releaseGateStatus, ['NOT_RUN', 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED', 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING', 'RELEASE_GATE_PASSED'], true)) {
    throw new RuntimeException('Candidate Core release gate status is invalid.');
}

$protectedFiles = ['.env', 'config/database.php', 'installed.lock', 'database/proma-pay-install.sql', 'release-meta.json'];
$normalizePath = static fn (string $path): string => trim(str_replace('\\', '/', $path), '/');
$safeCorePath = static function (string $path) use ($normalizePath, $protectedFiles): ?string {
    $path = $normalizePath($path);
    if ($path === '' || preg_match('#(^|/)\.\.?(/|$)|^[a-z]:|[\x00-\x1f]#i', $path) || strpos($path, ':') !== false) {
        throw new RuntimeException('Unsafe path in Core manifest: ' . $path);
    }
    $lower = strtolower($path);
    if (in_array($lower, $protectedFiles, true)) {
        return null;
    }
    if (strpos($lower, 'plugins/') === 0
        || strpos($lower, 'backups/') === 0
        || strpos($lower, '2- themes/') === 0
        || strpos($lower, 'storage/') === 0
        || strpos($lower, 'uploads/') === 0
        || strpos($lower, 'vendor/') === 0) {
        throw new RuntimeException('Core archive unexpectedly contains a protected target: ' . $path);
    }
    return $path;
};

$baselineMigrationHashes = [];
foreach (($baselineManifest['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && strpos($normalizePath((string) $file['path']), 'database/migrations/') === 0) {
        $baselineMigrationHashes[$normalizePath((string) $file['path'])] = strtolower((string) ($file['sha256'] ?? ''));
    }
}

$coreZip = new ZipArchive();
if ($coreZip->open($corePath) !== true) {
    throw new RuntimeException('Cannot open candidate Core archive.');
}
$files = [];
$migrations = [];
$seen = [];
$totalBytes = 0;
try {
    foreach (($coreManifest['files'] ?? []) as $file) {
        if (!is_array($file) || empty($file['path'])) {
            throw new RuntimeException('Core manifest contains an invalid file record.');
        }
        $path = $safeCorePath((string) $file['path']);
        if ($path === null) {
            continue;
        }
        if (isset($seen[strtolower($path)])) {
            throw new RuntimeException('Core manifest contains a duplicate file: ' . $path);
        }
        $seen[strtolower($path)] = true;
        $bytes = $coreZip->getFromName($path);
        if (!is_string($bytes) || !hash_equals(strtolower((string) ($file['sha256'] ?? '')), hash('sha256', $bytes))) {
            throw new RuntimeException('Core file checksum mismatch: ' . $path);
        }

        if (strpos($path, 'database/migrations/') === 0) {
            $sourceMigration = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (is_file($sourceMigration)) {
                $sourceBytes = file_get_contents($sourceMigration);
                if (!is_string($sourceBytes)) {
                    throw new RuntimeException('Cannot read migration source: ' . $path);
                }
                $bytes = $sourceBytes;
            }
            $baselineHash = $baselineMigrationHashes[$path] ?? null;
            if ($baselineHash === null || !hash_equals($baselineHash, hash('sha256', $bytes))) {
                $migrations[] = $path;
            }
        }

        $totalBytes += strlen($bytes);
        if ($totalBytes > 536870912 || strlen($bytes) > 67108864) {
            throw new RuntimeException('Core update exceeds the updater archive limits.');
        }
        $files[] = [
            'source' => $path,
            'target' => $path,
            'sha256' => hash('sha256', $bytes),
            '_bytes' => $bytes,
        ];
    }
} finally {
    $coreZip->close();
}

if (!$files || count($files) > 20000) {
    throw new RuntimeException('Universal update file inventory is empty or exceeds the updater limit.');
}
sort($migrations, SORT_STRING);
foreach ($migrations as $migration) {
    $bytes = null;
    foreach ($files as $file) {
        if ($file['source'] === $migration) {
            $bytes = $file['_bytes'];
            break;
        }
    }
    if (!is_string($bytes) || preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $bytes)) {
        throw new RuntimeException('Universal migration is missing or contains a prohibited destructive statement: ' . $migration);
    }
}

$manifestFiles = [];
foreach ($files as $file) {
    unset($file['_bytes']);
    $manifestFiles[] = $file;
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی کامل هسته Proma Pay V' . $version . ' (همه نسخه‌های پشتیبانی‌شده)',
    'version' => $version,
    'minimum_version' => $minimumVersion,
    'update_mode' => 'replace_all_core_files',
    'supported_from' => $minimumVersion,
    'baseline' => [
        'version' => $baselineVersion,
        'archive' => basename($baselinePath),
        'release_manifest_sha256' => hash('sha256', $baselineManifestRaw),
    ],
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'candidate_only' => true,
    'release_gate_status' => $releaseGateStatus,
    'production_availability_certified' => false,
    'files' => $manifestFiles,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', '.env', 'installed.lock', 'plugins/', 'storage/', 'uploads/'],
    'notes' => [
        'Replaces the complete Core inventory and does not pin installed file hashes.',
        'Optional plugins keep their existing files and must use the plugin update flow.',
        'Supports versions from ' . $minimumVersion . ' up to, but not including, ' . $version . '.',
    ],
];
$manifestJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (strlen($manifestJson) > 4194304) {
    throw new RuntimeException('Universal update manifest exceeds the updater limit.');
}

$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Cannot create universal update archive.');
}
foreach ($files as $file) {
    $zip->addFromString($file['source'], $file['_bytes']);
}
$zip->addFromString('proma-update.json', $manifestJson);
$statusDescription = match ($releaseGateStatus) {
    'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED' => 'Static and unit checks passed; database and HTTP integration checks remain blocked because an isolated QA environment is unavailable.',
    'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING' => 'Static/unit and selected isolated integration checks passed; the full release gate remains pending.',
    'RELEASE_GATE_PASSED' => 'The full QA release gate passed; production availability is certified separately.',
    default => 'The release gate has not run.',
};
$readme = "# Proma Pay Universal Core Update V{$version}\n\n"
    . "This is a complete Core replacement update for installed versions {$minimumVersion} through versions older than {$version}.\n\n"
    . "It preserves database credentials, the installed lock, plugins, uploads, and storage. Optional plugins use their own update and migration flow.\n\n"
    . "This is a local candidate artifact. Release Gate: {$releaseGateStatus}. {$statusDescription} Production installation is not certified.\n\n"
    . '- Core files: ' . count($files) . "\n"
    . '- Migrations: ' . count($migrations) . "\n";
$zip->addFromString('UPDATE_README.md', $readme);
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize universal update archive.');
}

$verify = new ZipArchive();
if ($verify->open($outputPath) !== true) {
    throw new RuntimeException('Cannot reopen universal update archive.');
}
try {
    $storedManifest = $verify->getFromName('proma-update.json');
    if (!is_string($storedManifest) || !hash_equals(hash('sha256', $manifestJson), hash('sha256', $storedManifest))) {
        throw new RuntimeException('Embedded universal update manifest mismatch.');
    }
    foreach ($manifestFiles as $file) {
        $bytes = $verify->getFromName($file['source']);
        if (!is_string($bytes) || !hash_equals($file['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Universal update payload checksum mismatch: ' . $file['source']);
        }
        if (array_key_exists('expected_previous_sha256', $file)) {
            throw new RuntimeException('Universal update unexpectedly contains an installed-file hash pin.');
        }
    }
} finally {
    $verify->close();
}

file_put_contents($manifestPath, $manifestJson . PHP_EOL);
file_put_contents($readmePath, $readme);
$sha256 = strtolower(hash_file('sha256', $outputPath));
file_put_contents($checksumPath, $sha256 . '  ' . basename($outputPath) . PHP_EOL);

echo json_encode([
    'version' => $version,
    'supported_from' => $minimumVersion,
    'update' => $outputPath,
    'sha256' => $sha256,
    'core_files' => count($files),
    'migrations' => $migrations,
    'uncompressed_bytes' => $totalBytes,
    'compressed_bytes' => filesize($outputPath),
    'status' => 'CANDIDATE_UNIVERSAL_CORE_' . $releaseGateStatus,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
