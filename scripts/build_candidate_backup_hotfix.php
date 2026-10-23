<?php

declare(strict_types=1);

/**
 * Package a manual backup hotfix for V2.2.11 and a matching in-app V3.0.1
 * update package. The manual overlay is the bootstrap step because affected
 * installations cannot currently create the safety backup needed by updater.
 */

$root = dirname(__DIR__);
$patchVersion = '2.2.13';
$releaseVersion = '3.0.1';
$patchCandidateRoot = $root . '/dist/candidates/archive/v2/v' . $patchVersion;
$releaseCandidateRoot = $root . '/dist/candidates/archive/v3/v' . $releaseVersion;
$manualName = 'PromaPay-Manual-Update-v' . $patchVersion . '-from-v2.2.11.zip';
$rebasedName = 'PromaPay-Update-v' . $releaseVersion . '-from-v' . $patchVersion . '.zip';
$manualPath = $patchCandidateRoot . '/manual/' . $manualName;
$previousPatchPath = $root . '/dist/candidates/archive/v2/v2.2.12/manual/PromaPay-Manual-Update-v2.2.12-from-v2.2.11.zip';
$releaseCorePath = $releaseCandidateRoot . '/core/PromaPay-v' . $releaseVersion . '.zip';
$defaultUpdatePath = $releaseCandidateRoot . '/updates/PromaPay-Update-v' . $releaseVersion . '.zip';
$baselineCorePath = $root . '/dist/candidates/archive/v2/v2.2.11/core/PromaPay-v2.2.11.zip';
$rebasedPath = $releaseCandidateRoot . '/updates/' . $rebasedName;
$rebasedManifestPath = $releaseCandidateRoot . '/updates/' . pathinfo($rebasedName, PATHINFO_FILENAME) . '-manifest.json';

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
foreach ([$manualPath, $rebasedPath, $rebasedManifestPath] as $output) {
    if (file_exists($output)) {
        throw new RuntimeException('Candidate artifacts are immutable; existing path: ' . $output);
    }
}
foreach ([$previousPatchPath, $releaseCorePath, $defaultUpdatePath, $baselineCorePath] as $input) {
    if (!is_file($input)) {
        throw new RuntimeException('Required candidate input is missing: ' . $input);
    }
}

$ensureDir = static function (string $path): void {
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create candidate directory: ' . $path);
    }
};
$openZip = static function (string $path, int $flags = 0): ZipArchive {
    $zip = new ZipArchive();
    $result = $zip->open($path, $flags);
    if ($result !== true) {
        throw new RuntimeException('Cannot open ZIP (' . $result . '): ' . $path);
    }
    return $zip;
};
$createZip = static function (string $path) use ($openZip): ZipArchive {
    return $openZip($path, ZipArchive::CREATE | ZipArchive::EXCL);
};

$ensureDir(dirname($manualPath));
$oldPatch = $openZip($previousPatchPath);
$manualEntries = [
    'CHANGELOG.md',
    'config/version.php',
    'helpers/BackupService.php',
    'manifest.json',
    'package.json',
    'service-worker.js',
];
$patchContents = [];
foreach ($manualEntries as $entry) {
    if ($entry === 'helpers/BackupService.php') {
        $content = file_get_contents($root . '/helpers/BackupService.php');
    } else {
        $content = $oldPatch->getFromName($entry);
    }
    if (!is_string($content)) {
        throw new RuntimeException('Manual hotfix source is missing: ' . $entry);
    }
    if ($entry === 'CHANGELOG.md') {
        $content = preg_replace('/^# Changelog\R/', "# Changelog\n\n## V2.2.13 — 2026-10-06 (Candidate)\n\n- اگر ساخت بکاپ در مسیر اصلی هنگام افزودن فایل‌ها یا نهایی‌کردن ZIP شکست بخورد، ساخت در مسیر خصوصی جایگزین دوباره تلاش می‌شود.\n- در صورت شکست همهٔ مسیرها، جزئیات خطای هر پوشه گزارش می‌شود و آپدیت بدون بکاپ ایمنی متوقف می‌ماند.\n\n", $content, 1);
    } else {
        $content = str_replace(['2.2.12', 'v2-2-12', 'v2.2.12'], ['2.2.13', 'v2-2-13', 'v2.2.13'], $content);
    }
    $patchContents[$entry] = $content;
}
$oldPatch->close();

$manualZip = $createZip($manualPath);
foreach ($patchContents as $entry => $content) {
    if (!$manualZip->addFromString($entry, $content)) {
        $manualZip->close();
        @unlink($manualPath);
        throw new RuntimeException('Cannot add manual hotfix file: ' . $entry);
    }
}
if (!$manualZip->close()) {
    @unlink($manualPath);
    throw new RuntimeException('Cannot finalize manual hotfix ZIP.');
}

$manualHashes = [];
foreach ($patchContents as $entry => $content) {
    $manualHashes[$entry] = hash('sha256', $content);
}
$manualSha = hash_file('sha256', $manualPath);
$manualReadme = "# Proma Pay V2.2.13 — Manual backup hotfix\n\n"
    . "این وصله برای سامانهٔ V2.2.11 است که ساخت بکاپ آن، هم بکاپ دستی و هم مرحلهٔ ایمنی آپدیت را متوقف می‌کند.\n\n"
    . "1. در DirectAdmin از فایل‌ها و پایگاه‌داده یک نسخهٔ مستقل بگیرید.\n"
    . "2. ZIP را کنار `index.php` باز کنید و جایگزینی فایل‌های هم‌نام را تأیید کنید.\n"
    . "3. پس از مشاهدهٔ V2.2.13، آپدیت را با بستهٔ `PromaPay-Update-v3.0.1-from-v2.2.13.zip` اجرا کنید؛ یا بستهٔ کامل Core + plugins را دستی نصب کنید.\n"
    . "4. سپس از تنظیمات، ZIP بکاپ قبلی را بازیابی کنید.\n\n"
    . "این بسته migration ندارد. وضعیت انتشار Candidate است و نصب روی میزبان واقعی تأیید نشده است.\n\n"
    . "SHA-256: `{$manualSha}`\n";
file_put_contents(dirname($manualPath) . '/README-FA.txt', $manualReadme);
file_put_contents(dirname($manualPath) . '/SHA256SUMS.txt', $manualSha . '  ' . $manualName . "\n");
file_put_contents(dirname(dirname($manualPath)) . '/README.md', $manualReadme);
file_put_contents(dirname(dirname($manualPath)) . '/SHA256SUMS.txt', $manualSha . '  manual/' . $manualName . "\n");

$baselineZip = $openZip($baselineCorePath);
$baselineRelease = json_decode((string) $baselineZip->getFromName('release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$baselineHashes = [];
foreach (($baselineRelease['files'] ?? []) as $file) {
    if (is_array($file) && !empty($file['path']) && !empty($file['sha256'])) {
        $baselineHashes[(string) $file['path']] = strtolower((string) $file['sha256']);
    }
}
$baselineZip->close();
foreach ($manualHashes as $entry => $hash) {
    $baselineHashes[$entry] = strtolower($hash);
}

$coreZip = $openZip($releaseCorePath);
$coreRelease = json_decode((string) $coreZip->getFromName('release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$changed = [];
foreach (($coreRelease['files'] ?? []) as $file) {
    if (!is_array($file) || empty($file['path']) || empty($file['sha256'])) {
        continue;
    }
    $path = (string) $file['path'];
    $currentHash = strtolower((string) $file['sha256']);
    $previousHash = $baselineHashes[$path] ?? null;
    if ($previousHash !== null && hash_equals($previousHash, $currentHash)) {
        continue;
    }
    $content = $coreZip->getFromName($path);
    if (!is_string($content) || !hash_equals($currentHash, hash('sha256', $content))) {
        throw new RuntimeException('Core package file failed manifest checksum: ' . $path);
    }
    $changed[$path] = [
        'content' => $content,
        'source' => $path,
        'target' => $path,
        'sha256' => $currentHash,
        'expected_previous_sha256' => $previousHash,
    ];
}
$coreZip->close();
if (!$changed) {
    throw new RuntimeException('No changed Core files found for the rebased update.');
}
ksort($changed, SORT_STRING);

$defaultZip = $openZip($defaultUpdatePath);
$defaultManifest = json_decode((string) $defaultZip->getFromName('proma-update.json'), true, 512, JSON_THROW_ON_ERROR);
$defaultZip->close();
$updateFiles = [];
$migrations = [];
foreach ($changed as $file) {
    $updateFiles[] = [
        'source' => $file['source'],
        'target' => $file['target'],
        'sha256' => $file['sha256'],
        'expected_previous_sha256' => $file['expected_previous_sha256'],
    ];
    if (strpos($file['target'], 'database/migrations/') === 0) {
        $migrations[] = $file['target'];
    }
}
$defaultManifest['version'] = $releaseVersion;
$defaultManifest['minimum_version'] = $patchVersion;
$defaultManifest['baseline'] = [
    'version' => $patchVersion,
    'archive' => $manualName,
    'release_manifest_sha256' => $manualSha,
];
$defaultManifest['files'] = $updateFiles;
$defaultManifest['migrations'] = $migrations;
$defaultManifest['candidate_only'] = true;
$defaultManifest['production_availability_certified'] = false;
$updateManifestJson = json_encode($defaultManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$ensureDir(dirname($rebasedPath));
$rebasedZip = $createZip($rebasedPath);
foreach ($changed as $path => $file) {
    if (!$rebasedZip->addFromString($path, $file['content'])) {
        $rebasedZip->close();
        @unlink($rebasedPath);
        throw new RuntimeException('Cannot add update file: ' . $path);
    }
}
if (!$rebasedZip->addFromString('proma-update.json', $updateManifestJson)
    || !$rebasedZip->addFromString('UPDATE_README.md', "Candidate V{$releaseVersion} differential update for V{$patchVersion} after applying {$manualName}. The in-app update creates its safety backup before replacing files.\n")) {
    $rebasedZip->close();
    @unlink($rebasedPath);
    throw new RuntimeException('Cannot add rebased update metadata.');
}
if (!$rebasedZip->close()) {
    @unlink($rebasedPath);
    throw new RuntimeException('Cannot finalize rebased update ZIP.');
}
file_put_contents($rebasedManifestPath, $updateManifestJson . PHP_EOL);

$rebasedSha = hash_file('sha256', $rebasedPath);
$releaseRoot = $releaseCandidateRoot;
$releaseReadmePath = $releaseRoot . '/README.md';
$releaseReadme = (string) file_get_contents($releaseReadmePath);
$releaseReadme .= "\n- Update from V{$patchVersion} after applying the V2.2.13 backup hotfix: {$rebasedName}\n";
file_put_contents($releaseReadmePath, $releaseReadme);
$checksumsPath = $releaseRoot . '/SHA256SUMS.txt';
file_put_contents($checksumsPath, $rebasedSha . '  updates/' . $rebasedName . "\n", FILE_APPEND);
file_put_contents($checksumsPath, hash_file('sha256', $rebasedManifestPath) . '  updates/' . basename($rebasedManifestPath) . "\n", FILE_APPEND);

echo json_encode([
    'manual_hotfix' => $manualPath,
    'manual_sha256' => $manualSha,
    'rebased_update' => $rebasedPath,
    'rebased_update_sha256' => $rebasedSha,
    'update_files' => count($changed),
    'status' => 'CANDIDATE_NOT_LIVE_HOST_VERIFIED',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
