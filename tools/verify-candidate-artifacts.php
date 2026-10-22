<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/scripts/release_artifact_paths.php';
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new RuntimeException('Candidate build ID must be a short alphanumeric suffix.');
}
$candidateRoot = $root . '/dist/candidates/v' . $version . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$corePath = $candidateRoot . '/core/PromaPay-v' . $version . '.zip';
$fullPath = trim((string) getenv('PROMA_CANDIDATE_FULL_ARCHIVE')) ?: $candidateRoot . '/core/PromaPay-Full-v' . $version . '.zip';
$updatePath = $candidateRoot . '/updates/PromaPay-Update-v' . $version . '.zip';
$sidecarPath = $candidateRoot . '/updates/PromaPay-Update-v' . $version . '-manifest.json';
$checksumPath = $candidateRoot . '/SHA256SUMS.txt';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$open = static function (string $path) use ($assert): ZipArchive {
    $assert(class_exists('ZipArchive'), 'PHP ZipArchive extension is required.');
    $assert(is_file($path) && filesize($path) > 100, 'Candidate archive is missing or empty: ' . $path);
    $zip = new ZipArchive();
    $assert($zip->open($path) === true, 'Candidate archive cannot be opened: ' . $path);
    return $zip;
};
$assertSafeNames = static function (ZipArchive $zip, string $label) use ($assert): void {
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        $assert($name !== '' && $name[0] !== '/' && !preg_match('#(?:^|/)\.\.(?:/|$)#', $name), $label . ' has an unsafe path: ' . $name);
    }
};

$core = $open($corePath);
$assertSafeNames($core, 'Core archive');
$coreRaw = $core->getFromName('release-manifest.json');
$assert(is_string($coreRaw), 'Core release manifest is missing.');
$coreManifest = json_decode($coreRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($coreManifest['version'] ?? '') === $version, 'Core candidate version does not match config/version.php.');
$assert(($coreManifest['candidate_only'] ?? false) === true, 'Core candidate-only marker is missing.');
$assert(in_array(($coreManifest['release_gate_status'] ?? ''), ['NOT_RUN', 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED', 'LOCAL_TARGETED_INTEGRATION_PASSED_FULL_GATE_PENDING', 'RELEASE_GATE_PASSED'], true), 'Core release gate status is invalid.');
$assert(($coreManifest['production_availability_certified'] ?? true) === false, 'Core candidate must not claim production certification.');
$coreHashes = [];
$corePayloadBytes = 0;
foreach (($coreManifest['files'] ?? []) as $file) {
    $path = (string) ($file['path'] ?? '');
    $assert($path !== '' && !isset($coreHashes[$path]), 'Core manifest has an empty or duplicate path.');
    $content = $core->getFromName($path);
    $assert(is_string($content), 'Core manifest file is absent from ZIP: ' . $path);
    $assert(hash_equals(strtolower((string) ($file['sha256'] ?? '')), hash('sha256', $content)), 'Core checksum mismatch: ' . $path);
    $corePayloadBytes += strlen($content);
    $coreHashes[$path] = hash('sha256', $content);
}
$assert($core->locateName('install.php') !== false, 'Core archive is missing install.php.');
$assert(trim((string) $core->getFromName('health-static.txt')) === 'proma-static-ok', 'Core static health marker is missing.');
foreach ([
    'html/RTL/assets/css/vendors/bootstrap.rtl.min.css',
    'html/RTL/assets/css/style.css',
    'html/RTL/assets/css/responsive.css',
    'html/RTL/assets/js/jquery.min.js',
    'html/RTL/assets/js/bootstrap/bootstrap.bundle.min.js',
    'html/RTL/assets/js/login.js',
    'html/RTL/assets/js/sidebar-menu.js',
    'html/RTL/assets/images/favicon.png',
] as $requiredAsset) {
    $assert($core->locateName($requiredAsset) !== false, 'Core archive is missing a runtime asset: ' . $requiredAsset);
}
foreach (['.env', 'config/database.php', 'installed.lock', '1.xlsx'] as $forbidden) {
    $assert($core->locateName($forbidden) === false, 'Core archive includes operational data: ' . $forbidden);
}
for ($index = 0; $index < $core->numFiles; $index++) {
    $name = str_replace('\\', '/', (string) $core->getNameIndex($index));
    $assert(!preg_match('#(?:^|/)\.env(?:\.[^/]*)?$#i', $name), 'Core archive includes an environment file: ' . $name);
    foreach (['laravel/', 'laravel/storage/', 'laravel/tests/', 'laravel/docs/', 'laravel/node_modules/', 'laravel/releases/', 'laravel/bootstrap/cache/', 'laravel/.playwright-cli/'] as $laravelExcludedPrefix) {
        $assert(strpos($name, $laravelExcludedPrefix) !== 0, 'Core archive includes Laravel operational or development data: ' . $name);
    }
    foreach (['docs/', 'electron/', 'scripts/', 'tests/', 'tools/', 'skill/', '.project-graph/', 'output/'] as $excludedPrefix) {
        // The official V4.0.2 baseline includes this one legacy release note.
        // The differential updater preserves it; all other docs remain excluded.
        $legacyBaselineReleaseNote = strtolower($name) === 'docs/releases/release_notes-v4.0.2.md';
        $assert($legacyBaselineReleaseNote || strpos($name, $excludedPrefix) !== 0, 'Core archive includes development content: ' . $name);
    }
    $assert(strpos($name, 'plugins/PromaAccounting/') !== 0 && strpos($name, 'plugins/PromaZarinpal/') !== 0, 'Core archive includes optional plugin source: ' . $name);
    $assert(strpos($name, 'storage/secure_uploads/') !== 0, 'Core archive includes private user uploads.');
}
$core->close();

$full = $open($fullPath);
$assertSafeNames($full, 'Full bundle');
$fullRaw = $full->getFromName('release-manifest.json');
$assert(is_string($fullRaw), 'Full bundle release manifest is missing.');
$fullManifest = json_decode($fullRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($fullManifest['version'] ?? '') === $version, 'Full bundle version is incorrect.');
$assert(($fullManifest['type'] ?? '') === 'full-candidate-installer', 'Full bundle type is incorrect.');
$assert(($fullManifest['optional_plugins_included'] ?? false) === true, 'Full bundle is missing optional plugin marker.');
$assert(($fullManifest['candidate_only'] ?? false) === true, 'Full bundle candidate-only marker is missing.');
foreach (($fullManifest['files'] ?? []) as $file) {
    $path = (string) ($file['path'] ?? '');
    $content = $full->getFromName($path);
    $assert($path !== '' && is_string($content), 'Full bundle file is missing: ' . $path);
    $assert(hash_equals(strtolower((string) ($file['sha256'] ?? '')), hash('sha256', $content)), 'Full bundle checksum mismatch: ' . $path);
}
foreach (['PromaRepair', 'PromaAccounting', 'PromaAffiliate', 'PromaInventoryPartners', 'PromaSignConnect', 'PromaZarinpal'] as $pluginName) {
    $pluginPrefix = 'plugins/' . $pluginName . '/';
    $assert($full->locateName($pluginPrefix . 'plugin.json') !== false, 'Full bundle is missing plugin: ' . $pluginName);
}
$full->close();

$assert(is_file($fullPath), 'Candidate Full bundle is missing.');
$assert(is_file($updatePath), 'Candidate Update bundle is missing.');

$update = $open($updatePath);
$assertSafeNames($update, 'Update archive');
$updateRaw = $update->getFromName('proma-update.json');
$assert(is_string($updateRaw), 'Update manifest is missing.');
$updateManifest = json_decode($updateRaw, true, 512, JSON_THROW_ON_ERROR);
$assert(($updateManifest['version'] ?? '') === $version, 'Update candidate version is incorrect.');
$assert(($updateManifest['candidate_only'] ?? false) === true, 'Update candidate-only marker is missing.');
$assert(($updateManifest['release_gate_status'] ?? '') === ($coreManifest['release_gate_status'] ?? ''), 'Update and Core release gate statuses differ.');
$assert(($updateManifest['production_availability_certified'] ?? true) === false, 'Update candidate must not claim production certification.');
$assert($update->locateName('UPDATE_README.md') !== false, 'Update instructions are missing.');
$baselineVersion = (string) ($updateManifest['baseline']['version'] ?? '');
$assert(($updateManifest['minimum_version'] ?? '') === $baselineVersion, 'Update minimum version differs from its baseline.');
$baselineCandidates = [
    ReleaseArtifactPaths::core($root, $baselineVersion),
    $root . '/dist/candidates/v' . $baselineVersion . '/core/PromaPay-v' . $baselineVersion . '.zip',
    $root . '/dist/candidates/archive/v' . explode('.', $baselineVersion, 2)[0] . '/v' . $baselineVersion . '/core/PromaPay-v' . $baselineVersion . '.zip',
];
$baselineCandidates = array_merge(
    $baselineCandidates,
    glob($root . '/dist/candidates/v' . $baselineVersion . '-*/core/PromaPay-v' . $baselineVersion . '.zip') ?: [],
    glob($root . '/dist/candidates/archive/v' . explode('.', $baselineVersion, 2)[0] . '/v' . $baselineVersion . '-*/core/PromaPay-v' . $baselineVersion . '.zip') ?: [],
    glob($root . '/dist/candidates/archive/v' . $baselineVersion . '-*/core/PromaPay-v' . $baselineVersion . '.zip') ?: []
);
$expectedBaselineManifestHash = strtolower((string) ($updateManifest['baseline']['release_manifest_sha256'] ?? ''));
$baselinePath = '';
$baselineRaw = false;
foreach (array_unique($baselineCandidates) as $candidatePath) {
    if (!is_file($candidatePath)) {
        continue;
    }
    $candidate = $open($candidatePath);
    $candidateRaw = $candidate->getFromName('release-manifest.json');
    $candidate->close();
    if (is_string($candidateRaw) && $expectedBaselineManifestHash !== ''
        && hash_equals($expectedBaselineManifestHash, hash('sha256', $candidateRaw))) {
        $baselinePath = $candidatePath;
        $baselineRaw = $candidateRaw;
        break;
    }
}
$assert($baselinePath !== '', 'Exact baseline Core archive is missing for V' . $baselineVersion . '. Expected release manifest SHA-256: ' . $expectedBaselineManifestHash);
$baselineManifest = json_decode($baselineRaw, true, 512, JSON_THROW_ON_ERROR);
$baselineHashes = [];
foreach (($baselineManifest['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && !empty($file['sha256'])) {
        $baselineHashes[(string) $file['path']] = strtolower((string) $file['sha256']);
    }
}
$updatePayloadBytes = 0;
foreach (($updateManifest['files'] ?? []) as $file) {
    $path = (string) ($file['source'] ?? '');
    $assert($path !== '' && isset($coreHashes[$path]), 'Update points to a file outside its Core candidate: ' . $path);
    $assert(strpos($path, 'plugins/') !== 0 && strpos($path, 'storage/') !== 0 && $path !== 'config/database.php', 'Update contains a protected target: ' . $path);
    $content = $update->getFromName($path);
    $assert(is_string($content), 'Update file is missing: ' . $path);
    $updatePayloadBytes += strlen($content);
    $hash = hash('sha256', $content);
    $assert(hash_equals((string) ($file['sha256'] ?? ''), $hash), 'Update checksum mismatch: ' . $path);
    $assert(($coreHashes[$path] ?? '') === $hash, 'Update content differs from the complete Core candidate: ' . $path);
    $assert(($file['expected_previous_sha256'] ?? null) === ($baselineHashes[$path] ?? null), 'Update baseline checksum mismatch: ' . $path);
    $assert(($baselineHashes[$path] ?? '') !== $hash, 'Update includes an unchanged file: ' . $path);
}
$assert(count($updateManifest['files'] ?? []) < count($coreHashes) && $updatePayloadBytes < $corePayloadBytes, 'Update candidate is not differential.');
$update->close();

$sidecar = json_decode((string) file_get_contents($sidecarPath), true, 512, JSON_THROW_ON_ERROR);
$assert($sidecar === $updateManifest, 'Update sidecar does not match the embedded manifest.');
$checksumLines = file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$expectedChecksums = [
    basename($corePath) => hash_file('sha256', $corePath),
    basename($fullPath) => hash_file('sha256', $fullPath),
    basename($updatePath) => hash_file('sha256', $updatePath),
];
foreach (glob($candidateRoot . '/plugins/*.zip') ?: [] as $pluginArchive) {
    $expectedChecksums[basename($pluginArchive)] = hash_file('sha256', $pluginArchive);
}
foreach (glob($candidateRoot . '/updates/*-from-v*.zip') ?: [] as $baselineUpdate) {
    $baselineArchive = $open($baselineUpdate);
    $baselineManifestRaw = $baselineArchive->getFromName('proma-update.json');
    $assert(is_string($baselineManifestRaw), 'Baseline update manifest is missing: ' . basename($baselineUpdate));
    $baselineManifest = json_decode($baselineManifestRaw, true, 512, JSON_THROW_ON_ERROR);
    $assert(($baselineManifest['version'] ?? '') === $version, 'Baseline update version is incorrect: ' . basename($baselineUpdate));
    $assert(($baselineManifest['candidate_only'] ?? false) === true, 'Baseline update candidate marker is missing: ' . basename($baselineUpdate));
    $assert(($baselineManifest['release_gate_status'] ?? '') === ($coreManifest['release_gate_status'] ?? ''), 'Baseline update release gate status differs from Core: ' . basename($baselineUpdate));
    $assert(($baselineManifest['production_availability_certified'] ?? true) === false, 'Baseline update incorrectly claims production certification: ' . basename($baselineUpdate));
    foreach (($baselineManifest['files'] ?? []) as $file) {
        $source = (string) ($file['source'] ?? '');
        $content = $baselineArchive->getFromName($source);
        $assert($source !== '' && is_string($content), 'Baseline update file is missing: ' . $source);
        $assert(hash_equals(strtolower((string) ($file['sha256'] ?? '')), hash('sha256', $content)), 'Baseline update checksum mismatch: ' . $source);
    }
    $baselineArchive->close();
    $expectedChecksums[basename($baselineUpdate)] = hash_file('sha256', $baselineUpdate);
}
foreach ($checksumLines as $line) {
    $parts = preg_split('/\s{2}/', trim((string) $line), 2);
    $assert(is_array($parts) && count($parts) === 2, 'Invalid candidate checksum line.');
    [$expected, $name] = $parts;
    $assert(isset($expectedChecksums[$name]) && hash_equals($expectedChecksums[$name], $expected), 'Candidate archive checksum mismatch: ' . $name);
    unset($expectedChecksums[$name]);
}
$assert($expectedChecksums === [], 'Candidate checksums do not cover both archives.');

// Lint PHP files from the actual packaged copies in an isolated temporary tree.
$extractRoot = sys_get_temp_dir() . '/proma-candidate-verify-' . bin2hex(random_bytes(6));
$assert(mkdir($extractRoot, 0700, true), 'Cannot create candidate verification directory.');
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
    foreach (['core' => $corePath, 'update' => $updatePath] as $label => $archivePath) {
        $target = $extractRoot . '/' . $label;
        mkdir($target, 0700, true);
        $archive = $open($archivePath);
        $assert($archive->extractTo($target), 'Cannot extract candidate ' . $label . ' archive.');
        $archive->close();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1));
            if (preg_match('#(?:^|/)(?:vendor|node_modules)/#', $relative)) {
                continue;
            }
            try {
                $source = file_get_contents($file->getPathname());
                $assert(is_string($source), 'Cannot read extracted PHP file: ' . $file->getPathname());
                token_get_all($source, TOKEN_PARSE);
            } catch (ParseError $error) {
                $assert(false, 'Extracted PHP lint failed: ' . $file->getPathname() . ' ' . $error->getMessage());
            }
        }
    }
} finally {
    $removeTree($extractRoot);
}

echo 'CANDIDATE_ARTIFACTS_V' . str_replace('.', '', $version) . '_OK_' . ($coreManifest['release_gate_status'] ?? 'UNKNOWN') . "\n";
