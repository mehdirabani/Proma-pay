<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/scripts/release_artifact_paths.php';
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '');
$baselineVersion = '4.0.1';
if ($version !== '4.0.2') {
    throw new RuntimeException('This builder is pinned to the V4.0.2 loyalty candidate.');
}
if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$baselinePath = ReleaseArtifactPaths::core($root, $baselineVersion);
if (!is_file($baselinePath)) {
    throw new RuntimeException('The exact V' . $baselineVersion . ' Core baseline archive is missing.');
}
$candidateRoot = $root . '/dist/candidates/v' . $version;
$outputDir = $candidateRoot . '/updates';
$updateName = 'PromaPay-Update-v' . $version . '-from-v' . $baselineVersion . '.zip';
$updatePath = $outputDir . '/' . $updateName;
$sidecarPath = $outputDir . '/PromaPay-Update-v' . $version . '-from-v' . $baselineVersion . '-manifest.json';
$replaceExisting = in_array('--replace', $argv, true);
if (!$replaceExisting && (file_exists($updatePath) || file_exists($sidecarPath))) {
    throw new RuntimeException('Candidate artifacts are immutable; an output already exists.');
}
if ($replaceExisting) {
    foreach ([$updatePath, $sidecarPath] as $existingPath) {
        if (is_file($existingPath) && !unlink($existingPath)) {
            throw new RuntimeException('Cannot replace prior candidate output: ' . $existingPath);
        }
    }
}
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Cannot create the candidate output directory.');
}

$paths = [
    'CHANGELOG.md',
    'config/settings.php',
    'config/version.php',
    'controllers/CustomersController.php',
    'controllers/DashboardController.php',
    'controllers/LoyaltyController.php',
    'controllers/MedalsController.php',
    'database/migrations/2026_10_08_customer_loyalty_points.sql',
    'manifest.json',
    'models/CustomerLoyalty.php',
    'models/IdentityDocument.php',
    'models/Payment.php',
    'models/SystemOutbox.php',
    'models/User.php',
    'package.json',
    'service-worker.js',
    'views/customers/index.php',
    'views/customers/show.php',
    'views/customers/tabs/loyalty.php',
    'views/dashboard/customer.php',
    'views/layouts/app.php',
    'views/loyalty/index.php',
];
$paths[] = 'docs/releases/RELEASE_NOTES-v' . $version . '.md';
sort($paths, SORT_STRING);

$baseline = new ZipArchive();
if ($baseline->open($baselinePath) !== true) {
    throw new RuntimeException('Cannot open the baseline Core archive.');
}
try {
    $baselineRaw = $baseline->getFromName('release-manifest.json');
    $baselineManifest = is_string($baselineRaw)
        ? json_decode($baselineRaw, true, 512, JSON_THROW_ON_ERROR)
        : null;
    if (!is_array($baselineManifest)
        || ($baselineManifest['app'] ?? '') !== 'proma-pay'
        || ($baselineManifest['type'] ?? '') !== 'core-installer'
        || (string) ($baselineManifest['version'] ?? '') !== $baselineVersion) {
        throw new RuntimeException('The baseline release manifest is invalid.');
    }
    $baselineHashes = [];
    foreach (($baselineManifest['files'] ?? []) as $record) {
        if (!is_array($record) || empty($record['path']) || empty($record['sha256'])) {
            continue;
        }
        $path = str_replace('\\', '/', (string) $record['path']);
        $bytes = $baseline->getFromName($path);
        $hash = strtolower((string) $record['sha256']);
        if (!is_string($bytes) || !hash_equals($hash, hash('sha256', $bytes))) {
            throw new RuntimeException('Baseline file checksum mismatch: ' . $path);
        }
        $baselineHashes[$path] = $hash;
    }
    if (!$baselineHashes) {
        throw new RuntimeException('The baseline release manifest has no verified file inventory.');
    }
} finally {
    $baseline->close();
}

$contents = [];
$entries = [];
$migrations = [];
foreach ($paths as $path) {
    $absolute = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($absolute)) {
        throw new RuntimeException('Candidate file is missing: ' . $path);
    }
    $bytes = file_get_contents($absolute);
    if (!is_string($bytes)) {
        throw new RuntimeException('Cannot read candidate file: ' . $path);
    }
    $contents[$path] = $bytes;
    $entries[] = [
        'source' => $path,
        'target' => $path,
        'sha256' => hash('sha256', $bytes),
        'expected_previous_sha256' => $baselineHashes[$path] ?? null,
    ];
    if (strpos($path, 'database/migrations/') === 0) {
        $migrations[] = $path;
    }
}

$manifest = [
    'app' => 'proma-pay',
    'name' => 'بسته کاندید باشگاه امتیاز Proma Pay V' . $version . ' از V' . $baselineVersion,
    'version' => $version,
    'minimum_version' => $baselineVersion,
    'baseline' => [
        'version' => $baselineVersion,
        'archive' => basename($baselinePath),
        'release_manifest_sha256' => hash('sha256', $baselineRaw),
    ],
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'candidate_only' => true,
    'release_gate_status' => 'NOT_RUN',
    'production_availability_certified' => false,
    'files' => $entries,
    'migrations' => $migrations,
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
];
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$readme = "# Candidate update V{$version} from V{$baselineVersion}\n\n"
    . "This local-review update contains the customer activity points and tier-medal feature. "
    . "Release Gate: NOT RUN. Production installation is not certified.\n\n"
    . '- Changed files: ' . count($entries) . "\n"
    . '- Migrations: ' . count($migrations) . "\n"
    . "- Only install on the exact V{$baselineVersion} baseline represented by the embedded file hashes.\n"
    . "- Customer data, uploads, plug-ins, and database connection settings are preserved.\n";

$zip = new ZipArchive();
if ($zip->open($updatePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Cannot create the candidate update archive.');
}
foreach ($contents as $path => $bytes) {
    if (!$zip->addFromString($path, $bytes)) {
        $zip->close();
        throw new RuntimeException('Cannot add candidate file: ' . $path);
    }
}
$zip->addFromString('proma-update.json', $manifestJson);
$zip->addFromString('UPDATE_README.md', $readme);
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize the candidate update archive.');
}
file_put_contents($sidecarPath, $manifestJson . PHP_EOL);

$verify = new ZipArchive();
if ($verify->open($updatePath) !== true) {
    throw new RuntimeException('Cannot reopen the candidate update archive.');
}
try {
    foreach ($entries as $entry) {
        $bytes = $verify->getFromName($entry['source']);
        if (!is_string($bytes) || !hash_equals($entry['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Candidate checksum mismatch: ' . $entry['source']);
        }
    }
    $embedded = $verify->getFromName('proma-update.json');
    if (!is_string($embedded) || !hash_equals(hash('sha256', $manifestJson), hash('sha256', $embedded))) {
        throw new RuntimeException('Embedded candidate manifest mismatch.');
    }
} finally {
    $verify->close();
}

$sumPath = $candidateRoot . '/SHA256SUMS.txt';
$sumLine = strtolower(hash_file('sha256', $updatePath)) . '  ' . $updateName;
$existingLines = is_file($sumPath) ? preg_split('/\r?\n/', trim((string) file_get_contents($sumPath))) : [];
$existingLines = array_values(array_filter($existingLines, static fn ($line) => trim((string) $line) !== '' && !str_ends_with(trim((string) $line), '  ' . $updateName)));
$existingLines[] = $sumLine;
file_put_contents($sumPath, implode(PHP_EOL, $existingLines) . PHP_EOL);

echo json_encode([
    'version' => $version,
    'baseline' => $baselineVersion,
    'update' => $updatePath,
    'sha256' => strtolower(hash_file('sha256', $updatePath)),
    'files' => count($entries),
    'migrations' => count($migrations),
    'status' => 'CANDIDATE_PACKAGED_QA_NOT_RUN',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
