<?php

declare(strict_types=1);

// Build candidate-only plugin archives and a combined full application bundle.
$root = dirname(__DIR__);
$coreVersion = (string) ((require $root . '/config/version.php')['application'] ?? '0.0.0');
$candidateBuildId = trim((string) getenv('PROMA_CANDIDATE_BUILD_ID'));
if ($candidateBuildId !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,22}$/i', $candidateBuildId)) {
    throw new RuntimeException('Candidate build ID must be a short alphanumeric suffix.');
}
$candidateRoot = $root . '/dist/candidates/v' . $coreVersion . ($candidateBuildId !== '' ? '-' . strtolower($candidateBuildId) : '');
$corePath = $candidateRoot . '/core/PromaPay-v' . $coreVersion . '.zip';
$defaultPluginOutput = $candidateRoot . '/plugins';
$pluginOutput = $defaultPluginOutput;
$fullPath = $candidateRoot . '/core/PromaPay-Full-v' . $coreVersion . '.zip';
$pluginsOnly = in_array('--plugins-only', $argv, true);
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--core-archive=')) {
        $corePath = trim(substr($argument, strlen('--core-archive=')), '"');
    }
    if (str_starts_with($argument, '--plugin-output=')) {
        $pluginOutput = trim(substr($argument, strlen('--plugin-output=')), '"');
    }
    if (str_starts_with($argument, '--full-output=')) {
        $fullPath = trim(substr($argument, strlen('--full-output=')), '"');
    }
}
if ($pluginOutput === '') {
    throw new RuntimeException('Plugin output directory cannot be empty.');
}
if ($pluginOutput !== $defaultPluginOutput) {
    if (str_starts_with($pluginOutput, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $pluginOutput)
        || preg_match('#(?:^|[\\\\/])\\.\\.(?:[\\\\/]|$)#', $pluginOutput)) {
        throw new RuntimeException('Custom plugin output must be a relative directory inside the project.');
    }
    $pluginOutput = $root . '/' . str_replace('\\\\', '/', $pluginOutput);
}
$writesCandidateMetadata = strcasecmp(rtrim(str_replace('\\\\', '/', $pluginOutput), '/'), rtrim(str_replace('\\\\', '/', $defaultPluginOutput), '/')) === 0;
$pluginNames = ['PromaRepair', 'PromaAccounting', 'PromaAffiliate', 'PromaInventoryPartners', 'PromaSignConnect', 'PromaZarinpal'];

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
if (!is_file($corePath)) {
    throw new RuntimeException('Build the candidate Core and Update archives first.');
}
$coreMetadataZip = new ZipArchive();
if ($coreMetadataZip->open($corePath) !== true) {
    throw new RuntimeException('Cannot read candidate Core release metadata.');
}
$coreMetadataRaw = $coreMetadataZip->getFromName('release-manifest.json');
$coreMetadataZip->close();
$coreMetadata = is_string($coreMetadataRaw) ? json_decode($coreMetadataRaw, true, 512, JSON_THROW_ON_ERROR) : null;
$releaseGateStatus = (string) ($coreMetadata['release_gate_status'] ?? '');
if (!in_array($releaseGateStatus, ['NOT_RUN', 'LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED', 'RELEASE_GATE_PASSED'], true)) {
    throw new RuntimeException('Candidate Core release gate status is invalid.');
}
if (!is_dir($pluginOutput) && !mkdir($pluginOutput, 0775, true) && !is_dir($pluginOutput)) {
    throw new RuntimeException('Cannot create candidate plugin directory.');
}

$plugins = [];
$pluginSourceFiles = [];
foreach ($pluginNames as $pluginName) {
    $sourceRoot = $root . '/plugins/' . $pluginName;
    $pluginJson = $sourceRoot . '/plugin.json';
    if (!is_file($pluginJson)) {
        throw new RuntimeException('Missing plugin manifest: ' . $pluginJson);
    }
    $manifest = json_decode((string) file_get_contents($pluginJson), true, 512, JSON_THROW_ON_ERROR);
    $pluginVersion = (string) ($manifest['version'] ?? '0.0.0');
    $pluginId = (string) ($manifest['id'] ?? '');
    if ($pluginId === '' || !preg_match('/^\d+\.\d+\.\d+$/', $pluginVersion)) {
        throw new RuntimeException('Invalid plugin identity or version: ' . $pluginName);
    }

    $sourceFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceRoot) + 1));
        $normalized = strtolower($relative);
        if (strpos($normalized, '.appsec-forge/') === 0 || strpos($normalized, 'tests/') === 0 || strpos($normalized, 'scripts/') === 0
            || strpos($normalized, '.git/') === 0 || preg_match('/\.(?:zip|log|bak|tmp)$/i', $relative)) {
            continue;
        }
        $sourceFiles[$relative] = $item->getPathname();
    }
    ksort($sourceFiles, SORT_STRING);
    $archivePath = $pluginOutput . '/' . $pluginName . '-v' . $pluginVersion . '.zip';
    $sidecarPath = $pluginOutput . '/' . $pluginName . '-v' . $pluginVersion . '-manifest.json';
    if (file_exists($archivePath) || file_exists($sidecarPath)) {
        throw new RuntimeException('Candidate artifacts are immutable; existing path: ' . $archivePath);
    }
    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Cannot create candidate plugin ZIP: ' . $archivePath);
    }
    $inventory = [];
    foreach ($sourceFiles as $relative => $sourcePath) {
        $archiveName = $pluginName . '/' . $relative;
        $zip->addFile($sourcePath, $archiveName);
        $inventory[] = [
            'path' => $archiveName,
            'size' => filesize($sourcePath),
            'sha256' => strtolower(hash_file('sha256', $sourcePath)),
        ];
        $pluginSourceFiles[$archiveName] = $sourcePath;
    }
    if (!$zip->close()) {
        throw new RuntimeException('Cannot finalize plugin ZIP: ' . $archivePath);
    }
    $pluginPackageManifest = [
        'app' => 'proma-pay',
        'type' => 'optional-plugin-candidate',
        'plugin_id' => $pluginId,
        'plugin_name' => $pluginName,
        'plugin_version' => $pluginVersion,
        'core_version' => $coreVersion,
        'built_at' => gmdate('c'),
        'candidate_only' => true,
        'release_gate_status' => $releaseGateStatus,
        'production_availability_certified' => false,
        'files' => $inventory,
    ];
    file_put_contents($sidecarPath, json_encode($pluginPackageManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    $plugins[$pluginName] = [
        'id' => $pluginId,
        'version' => $pluginVersion,
        'archive' => $archivePath,
        'manifest' => $pluginPackageManifest,
        'sha256' => strtolower(hash_file('sha256', $archivePath)),
    ];
}

if (!$pluginsOnly && file_exists($fullPath)) {
    throw new RuntimeException('Candidate artifacts are immutable; existing path: ' . $fullPath);
}
$fullFiles = [];
if (!$pluginsOnly) {
    $coreZip = new ZipArchive();
    if ($coreZip->open($corePath) !== true) {
        throw new RuntimeException('Cannot open candidate Core ZIP.');
    }
    $coreManifestRaw = $coreZip->getFromName('release-manifest.json');
    $coreManifest = is_string($coreManifestRaw) ? json_decode($coreManifestRaw, true, 512, JSON_THROW_ON_ERROR) : null;
    if (!is_array($coreManifest) || (string) ($coreManifest['version'] ?? '') !== $coreVersion) {
        $coreZip->close();
        throw new RuntimeException('Candidate Core manifest does not match this version.');
    }
    $fullZip = new ZipArchive();
    if ($fullZip->open($fullPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        $coreZip->close();
        throw new RuntimeException('Cannot create full candidate ZIP.');
    }
    for ($index = 0; $index < $coreZip->numFiles; $index++) {
        $name = $coreZip->getNameIndex($index);
        if (!is_string($name) || $name === 'release-manifest.json') {
            continue;
        }
        $content = $coreZip->getFromIndex($index);
        if (!is_string($content) || !$fullZip->addFromString($name, $content)) {
            $coreZip->close();
            $fullZip->close();
            throw new RuntimeException('Could not copy Core file into full package: ' . (string) $name);
        }
    }
    $coreZip->close();
    foreach ($pluginSourceFiles as $archiveName => $sourcePath) {
        $fullZip->addFile($sourcePath, 'plugins/' . $archiveName);
    }

    $fullManifest = $coreManifest;
    $fullManifest['type'] = 'full-candidate-installer';
    $fullManifest['optional_plugins_included'] = true;
    $fullManifest['plugins'] = [];
    $fullFiles = $coreManifest['files'] ?? [];
    foreach ($plugins as $pluginName => $plugin) {
        $fullManifest['plugins'][$pluginName] = ['id' => $plugin['id'], 'version' => $plugin['version']];
        foreach ($plugin['manifest']['files'] as $file) {
            $file['path'] = 'plugins/' . $file['path'];
            $fullFiles[] = $file;
        }
    }
    $fullManifest['files'] = $fullFiles;
    $fullZip->addFromString('release-manifest.json', json_encode($fullManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $fullZip->addFromString('CANDIDATE_RELEASE_STATUS.txt', "Candidate bundle only. Release Gate: {$releaseGateStatus}. Production availability certified: no.\r\n");
    if (!$fullZip->close()) {
        throw new RuntimeException('Cannot finalize full candidate ZIP.');
    }
}

// Reopen archives and verify all manifest-listed file hashes.
$archivePaths = [];
foreach ($plugins as $pluginName => $plugin) {
    $zip = new ZipArchive();
    if ($zip->open($plugin['archive']) !== true) {
        throw new RuntimeException('Cannot reopen plugin ZIP: ' . $plugin['archive']);
    }
    foreach ($plugin['manifest']['files'] as $file) {
        $bytes = $zip->getFromName((string) $file['path']);
        if (!is_string($bytes) || !hash_equals((string) $file['sha256'], hash('sha256', $bytes))) {
            $zip->close();
            throw new RuntimeException('Plugin ZIP checksum mismatch: ' . $file['path']);
        }
    }
    $zip->close();
    $archivePaths[] = $plugin['archive'];
}
if (!$pluginsOnly) {
    $fullCheck = new ZipArchive();
    if ($fullCheck->open($fullPath) !== true) {
        throw new RuntimeException('Cannot reopen full candidate ZIP.');
    }
    foreach ($fullFiles as $file) {
        $bytes = $fullCheck->getFromName((string) $file['path']);
        if (!is_string($bytes) || !hash_equals((string) $file['sha256'], hash('sha256', $bytes))) {
            $fullCheck->close();
            throw new RuntimeException('Full ZIP checksum mismatch: ' . $file['path']);
        }
    }
    $fullCheck->close();
    $archivePaths[] = $fullPath;
}

if ($writesCandidateMetadata) {
$sumPath = $candidateRoot . '/SHA256SUMS.txt';
$sumMap = [];
if (is_file($sumPath)) {
    foreach (preg_split('/\r?\n/', trim((string) file_get_contents($sumPath))) as $line) {
        if (preg_match('/^([a-f0-9]{64})\s{2}(.+)$/i', trim((string) $line), $match)) {
            $sumMap[$match[2]] = strtolower($match[1]);
        }
    }
}
foreach ($archivePaths as $archivePath) {
    $sumMap[basename($archivePath)] = strtolower(hash_file('sha256', $archivePath));
}
foreach (glob($candidateRoot . '/updates/*.zip') ?: [] as $updateArchive) {
    $sumMap[basename($updateArchive)] = strtolower(hash_file('sha256', $updateArchive));
}
ksort($sumMap, SORT_STRING);
$sumLines = [];
foreach ($sumMap as $name => $hash) {
    $sumLines[] = $hash . '  ' . $name;
}
file_put_contents($sumPath, implode(PHP_EOL, $sumLines) . PHP_EOL);

$readmePath = $candidateRoot . '/README.md';
$readme = is_file($readmePath) ? (string) file_get_contents($readmePath) : '';
$readme = preg_replace('/\n## Optional plugin packages\n[\s\S]*$/', '', $readme) ?? $readme;
$readme .= "\n\n## Optional plugin packages\n\n";
foreach ($plugins as $pluginName => $plugin) {
    $readme .= '- ' . $pluginName . ' v' . $plugin['version'] . ': ' . basename($plugin['archive']) . "\n";
}
$readme .= "\nPlugin dependency: install and activate PromaAccounting before installing PromaAffiliate.\n";
$defaultUpdateManifestPath = $candidateRoot . '/updates/PromaPay-Update-v' . $coreVersion . '-manifest.json';
$defaultUpdateManifest = is_file($defaultUpdateManifestPath)
    ? json_decode((string) file_get_contents($defaultUpdateManifestPath), true)
    : null;
$defaultBaseline = is_array($defaultUpdateManifest) ? (string) ($defaultUpdateManifest['minimum_version'] ?? '') : '';
if ($defaultBaseline !== '') {
    $readme .= '- Update from V' . $defaultBaseline . ': PromaPay-Update-v' . $coreVersion . ".zip\n";
}
foreach (glob($candidateRoot . '/updates/PromaPay-Update-v' . $coreVersion . '-from-v*.zip') ?: [] as $baselineUpdate) {
    $baselineName = basename($baselineUpdate);
    preg_match('/-from-v(\d+\.\d+\.\d+)\.zip$/', $baselineName, $baselineMatch);
    $readme .= '- Update from V' . ($baselineMatch[1] ?? 'unknown') . ': ' . $baselineName . "\n";
}
foreach (glob($candidateRoot . '/updates/PromaPay-Universal-Core-Update-v' . $coreVersion . '-from-v*.zip') ?: [] as $universalUpdate) {
    $universalName = basename($universalUpdate);
    preg_match('/-from-v(\d+\.\d+\.\d+)\.zip$/', $universalName, $universalMatch);
    $readme .= '- Universal Core update from V' . ($universalMatch[1] ?? 'unknown') . ': ' . $universalName . "\n";
}
$readme .= ($pluginsOnly ? '' : '- Full Core + plugin bundle: ' . basename($fullPath) . "\n") . '- Release Gate: ' . $releaseGateStatus . ".\n";
file_put_contents($readmePath, $readme);
}

$outputPlugins = [];
foreach ($plugins as $pluginName => $plugin) {
    $outputPlugins[$pluginName] = [
        'version' => $plugin['version'],
        'archive' => $plugin['archive'],
        'sha256' => $plugin['sha256'],
        'files' => count($plugin['manifest']['files']),
    ];
}
echo json_encode([
    'core_version' => $coreVersion,
    'plugin_output' => $pluginOutput,
    'full_bundle' => $pluginsOnly ? null : $fullPath,
    'full_sha256' => $pluginsOnly ? null : strtolower(hash_file('sha256', $fullPath)),
    'plugins' => $outputPlugins,
    'status' => ($pluginsOnly ? 'CANDIDATE_PLUGINS_PACKAGED_' : 'CANDIDATE_PACKAGED_') . $releaseGateStatus,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

