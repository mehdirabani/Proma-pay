<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = (string) ((require $root . '/config/version.php')['application'] ?? '0.0.0');
$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new RuntimeException('Candidate build ID must be a short alphanumeric suffix.');
}
$candidateRoot = $root . '/dist/candidates/v' . $version . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$deliveryRoot = $root . '/dist/releases';
$bundlePath = $deliveryRoot . '/PromaPay-v' . $version . '-Candidate-Delivery' . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '') . '.zip';
$checksumPath = $bundlePath . '.sha256';

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
if (!is_dir($deliveryRoot) && !mkdir($deliveryRoot, 0775, true) && !is_dir($deliveryRoot)) {
    throw new RuntimeException('Cannot create the candidate delivery directory.');
}
if (file_exists($bundlePath) || file_exists($checksumPath)) {
    throw new RuntimeException('Candidate delivery artifacts are immutable; existing path: ' . $bundlePath);
}

$files = [];
$addInput = static function (string $source, string $relative) use (&$files, $version): void {
    if (!is_file($source) || is_link($source)) {
        throw new RuntimeException('Candidate delivery input is missing or unsafe: ' . $source);
    }
    $files['PromaPay-v' . $version . '/' . $relative] = $source;
};

$addInput($candidateRoot . '/README.md', 'README.md');
$addInput($candidateRoot . '/SHA256SUMS.txt', 'SHA256SUMS.txt');
foreach (glob($candidateRoot . '/core/*.zip') ?: [] as $path) {
    $addInput($path, 'core/' . basename($path));
}
foreach (glob($candidateRoot . '/updates/*') ?: [] as $path) {
    if (is_file($path)) $addInput($path, 'updates/' . basename($path));
}
foreach (glob($candidateRoot . '/plugins/*') ?: [] as $path) {
    if (is_file($path)) $addInput($path, 'plugins/' . basename($path));
}
$addInput($root . '/docs/audit/FULL-BUG-AUDIT.md', 'audit/FULL-BUG-AUDIT.md');
$addInput($root . '/docs/audit/FINAL-AUDIT-V' . $version . '.md', 'audit/FINAL-AUDIT-V' . $version . '.md');
$releaseNotes = $root . '/docs/releases/V' . $version . '.md';
if (is_file($releaseNotes)) {
    $addInput($releaseNotes, 'release-notes/V' . $version . '.md');
}
ksort($files, SORT_STRING);

$corePath = $candidateRoot . '/core/PromaPay-v' . $version . '.zip';
$core = new ZipArchive();
if ($core->open($corePath) !== true) {
    throw new RuntimeException('Cannot read the candidate Core manifest.');
}
$releaseRaw = $core->getFromName('release-manifest.json');
$core->close();
$releaseManifest = is_string($releaseRaw) ? json_decode($releaseRaw, true, 512, JSON_THROW_ON_ERROR) : null;
if (!is_array($releaseManifest) || ($releaseManifest['version'] ?? '') !== $version
    || ($releaseManifest['candidate_only'] ?? false) !== true) {
    throw new RuntimeException('Core release manifest does not match this candidate.');
}
$releaseGateStatus = (string) ($releaseManifest['release_gate_status'] ?? 'UNKNOWN');
$updateManifestPath = $candidateRoot . '/updates/PromaPay-Update-v' . $version . '-manifest.json';
$updateManifest = json_decode((string) file_get_contents($updateManifestPath), true, 512, JSON_THROW_ON_ERROR);
$baselineVersion = (string) ($updateManifest['minimum_version'] ?? '');
if (!preg_match('/^\d+\.\d+\.\d+$/', $baselineVersion)) {
    throw new RuntimeException('Candidate Update baseline is invalid.');
}

$qaDescription = match ($releaseGateStatus) {
    'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED' => 'آزمون‌های ایستا و واحد گذشتند؛ آزمون دیتابیس، HTTP و availability تولید به‌دلیل نبود محیط QA ایزوله اجرا نشدند.',
    'RELEASE_GATE_PASSED' => 'گیت QA کامل گذشت؛ دسترس‌پذیری تولید جداگانه تأیید نشده است.',
    default => 'گیت QA و آزمون‌های ایستا، واحد، دیتابیس و HTTP برای این ساخت اجرا نشده‌اند.',
};
$startHere = "Proma Pay V{$version} — Candidate delivery\r\n\r\n"
    . "وضعیت: {$releaseGateStatus}. این بسته Stable یا مجاز برای استقرار تولید نیست. {$qaDescription}\r\n\r\n"
    . "- نصب پاک: core/PromaPay-v{$version}.zip\r\n"
    . "- ارتقا از V{$baselineVersion}: updates/PromaPay-Update-v{$version}.zip\r\n"
    . "- Core همراه با همهٔ افزونه‌ها: core/PromaPay-Full-v{$version}.zip\r\n"
    . "- ZIP مستقل افزونه‌ها و manifestها: plugins/\r\n"
    . "- پیش‌نیاز افیلیت: پیش از نصب PromaAffiliate، PromaAccounting را نصب و فعال کنید.\r\n"
    . "- گزارش ممیزی و checksumها: audit/ و SHA256SUMS.txt\r\n";
$startName = 'PromaPay-v' . $version . '/START-HERE.fa.txt';
$files[$startName] = null;
$inventory = [];
foreach ($files as $archiveName => $sourcePath) {
    $content = $sourcePath === null ? $startHere : (string) file_get_contents($sourcePath);
    if (!is_string($content)) {
        throw new RuntimeException('Cannot read a candidate delivery input: ' . $archiveName);
    }
    $inventory[] = [
        'path' => $archiveName,
        'size' => strlen($content),
        'sha256' => hash('sha256', $content),
    ];
}
$deliveryManifest = [
    'app' => 'proma-pay',
    'version' => $version,
    'type' => 'candidate-delivery-bundle',
    'candidate_only' => true,
    'release_gate_status' => $releaseGateStatus,
    'production_availability_certified' => false,
    'built_at' => gmdate('c'),
    'files' => $inventory,
];
$manifestName = 'PromaPay-v' . $version . '/delivery-manifest.json';

$zip = new ZipArchive();
if ($zip->open($bundlePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Cannot create candidate delivery bundle.');
}
foreach ($files as $archiveName => $sourcePath) {
    $added = $sourcePath === null
        ? $zip->addFromString($archiveName, $startHere)
        : $zip->addFile($sourcePath, $archiveName);
    if (!$added) {
        $zip->close();
        throw new RuntimeException('Cannot add file to candidate delivery bundle: ' . $archiveName);
    }
    if ($sourcePath !== null && str_ends_with(strtolower($sourcePath), '.zip')) {
        $zip->setCompressionName($archiveName, ZipArchive::CM_STORE);
    }
}
$zip->addFromString($manifestName, json_encode($deliveryManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize candidate delivery bundle.');
}

$check = new ZipArchive();
if ($check->open($bundlePath) !== true) {
    throw new RuntimeException('Cannot reopen candidate delivery bundle.');
}
try {
    foreach ($inventory as $item) {
        $content = $check->getFromName((string) $item['path']);
        if (!is_string($content) || !hash_equals((string) $item['sha256'], hash('sha256', $content))) {
            throw new RuntimeException('Candidate delivery checksum mismatch: ' . (string) $item['path']);
        }
    }
    $storedManifest = $check->getFromName($manifestName);
    if (!is_string($storedManifest) || json_decode($storedManifest, true, 512, JSON_THROW_ON_ERROR) !== $deliveryManifest) {
        throw new RuntimeException('Candidate delivery manifest verification failed.');
    }
} finally {
    $check->close();
}

$bundleHash = strtolower(hash_file('sha256', $bundlePath));
if (file_put_contents($checksumPath, $bundleHash . '  ' . basename($bundlePath) . PHP_EOL) === false) {
    throw new RuntimeException('Cannot write candidate delivery checksum.');
}
echo json_encode([
    'bundle' => $bundlePath,
    'sha256' => $bundleHash,
    'size_bytes' => filesize($bundlePath),
    'files' => count($inventory),
    'release_gate_status' => $releaseGateStatus,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
