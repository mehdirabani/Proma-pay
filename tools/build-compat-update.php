<?php

declare(strict_types=1);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '');
$minimum = $argv[1] ?? '1.5.7';

if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !preg_match('/^\d+\.\d+\.\d+$/', $minimum)) {
    fwrite(STDERR, "Usage: php tools/build-compat-update.php 1.5.7\n");
    exit(1);
}

$corePath = $root . '/dist/core/PromaPay-v' . $version . '.zip';
if (!is_file($corePath)) {
    fwrite(STDERR, "Core archive does not exist: {$corePath}\n");
    exit(1);
}

$core = new ZipArchive();
if ($core->open($corePath) !== true) {
    fwrite(STDERR, "Cannot open core archive: {$corePath}\n");
    exit(1);
}

$releaseManifestRaw = $core->getFromName('release-manifest.json');
$releaseManifest = is_string($releaseManifestRaw) ? json_decode($releaseManifestRaw, true) : null;
if (!is_array($releaseManifest) || ($releaseManifest['type'] ?? '') !== 'core-installer') {
    fwrite(STDERR, "Core archive has no valid release-manifest.json\n");
    $core->close();
    exit(1);
}

$files = [];
foreach (($releaseManifest['files'] ?? []) as $file) {
    if (!is_array($file) || empty($file['path']) || empty($file['sha256'])) {
        continue;
    }
    $path = trim(str_replace('\\', '/', (string) $file['path']), '/');
    if ($path === '' || str_contains($path, '../') || str_starts_with($path, '/')) {
        continue;
    }
    $files[] = [
        'source' => $path,
        'target' => $path,
        'sha256' => (string) $file['sha256'],
        'expected_previous_sha256' => null,
    ];
}

if (!$files) {
    fwrite(STDERR, "No files found in core release manifest.\n");
    $core->close();
    exit(1);
}

$distUpdates = $root . '/dist/updates';
if (!is_dir($distUpdates) && !mkdir($distUpdates, 0775, true) && !is_dir($distUpdates)) {
    fwrite(STDERR, "Cannot create update directory: {$distUpdates}\n");
    $core->close();
    exit(1);
}

$suffix = 'from-v' . $minimum . '-full';
$updatePath = $distUpdates . '/PromaPay-Update-v' . $version . '-' . $suffix . '.zip';
$manifestPath = $distUpdates . '/PromaPay-Update-v' . $version . '-' . $suffix . '-manifest.json';

@unlink($updatePath);
@unlink($manifestPath);

$manifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی تجمیعی پایدار Proma Pay V' . $version . ' از V' . $minimum,
    'version' => $version,
    'minimum_version' => $minimum,
    'baseline' => [
        'version' => $minimum,
        'archive' => 'compat-full',
        'release_manifest_sha256' => null,
    ],
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $files,
    'migrations' => [],
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
    'production_availability_certified' => true,
];

$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($manifestJson)) {
    fwrite(STDERR, "Cannot encode update manifest.\n");
    $core->close();
    exit(1);
}

$update = new ZipArchive();
if ($update->open($updatePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create update archive: {$updatePath}\n");
    $core->close();
    exit(1);
}

foreach ($files as $file) {
    $source = (string) $file['source'];
    $content = $core->getFromName($source);
    if ($content === false) {
        fwrite(STDERR, "Missing core file in archive: {$source}\n");
        $update->close();
        $core->close();
        @unlink($updatePath);
        exit(1);
    }
    $update->addFromString($source, $content);
}

$update->addFromString('proma-update.json', $manifestJson);
$update->addFromString(
    'UPDATE_README.md',
    "# بروزرسانی تجمیعی Proma Pay V{$version}\n\n"
    . "این بسته برای زمانی ساخته شده که سامانه مقصد روی V{$minimum} یا بالاتر است و baseline تفاضلی دقیق در دسترس نیست.\n"
    . "بسته فایل‌های runtime هسته را جایگزین می‌کند، اما config/database.php، plugins، storage و uploads را حفظ می‌کند.\n\n"
    . "- نسخه مقصد: {$version}\n"
    . "- حداقل نسخه قابل نصب: {$minimum}\n"
    . "- فایل‌های runtime: " . count($files) . "\n"
);

$update->close();
$core->close();
file_put_contents($manifestPath, $manifestJson . PHP_EOL);

echo json_encode([
    'version' => $version,
    'minimum_version' => $minimum,
    'update' => $updatePath,
    'manifest' => $manifestPath,
    'files' => count($files),
    'size' => filesize($updatePath),
    'sha256' => hash_file('sha256', $updatePath),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

