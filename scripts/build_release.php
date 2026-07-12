<?php

declare(strict_types=1);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$versionSlug = str_replace('.', '-', $version);
$pluginRoot = $root . '/plugins/PromaAccounting';
$pluginManifest = json_decode((string) file_get_contents($pluginRoot . '/plugin.json'), true);
$pluginVersion = (string) ($pluginManifest['version'] ?? '0.0.0');
$pluginVersionSlug = str_replace('.', '-', $pluginVersion);
$dist = $root . '/dist';
$coreDir = $dist . '/core';
$updateDir = $dist . '/updates';
$pluginDir = $dist . '/plugins';

foreach ([$dist, $coreDir, $updateDir, $pluginDir] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create release directory: ' . $directory);
    }
}

$normalize = static function (string $path): string {
    return trim(str_replace('\\', '/', $path), '/');
};

$relative = static function (string $path) use ($root, $normalize): string {
    return $normalize(substr($path, strlen($root)));
};

$coreExcluded = static function (string $relativePath) use ($normalize): bool {
    $path = strtolower($normalize($relativePath));
    if ($path === '') {
        return false;
    }
    $exact = [
        '.env',
        'config/database.php',
        'installed.lock',
        '1.xlsx',
        'proma-accounting_v1-0-0.zip',
    ];
    if (in_array($path, $exact, true)) {
        return true;
    }
    foreach ([
        '.git/', '.agents/', 'dist/', 'storage/', 'tmp/', 'plugins/', 'plugin-packages/', 'node_modules/',
        'html/docs/', 'html/rtl/dist/', 'html/rtl/starter-kit/', 'html/rtl/template/',
    ] as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }
    if (preg_match('/\.(zip|log|bak|sql\.gz|sqlite|db|tmp)$/i', basename($path))) {
        return true;
    }
    return false;
};

$walk = static function (string $directory, callable $accept) use ($relative): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        $path = $item->getPathname();
        $relativePath = $relative($path);
        if ($accept($relativePath, $path)) {
            $files[$relativePath] = $path;
        }
    }
    ksort($files, SORT_STRING);
    return $files;
};

$openZip = static function (string $path): ZipArchive {
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Cannot replace release archive: ' . $path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create release archive: ' . $path);
    }
    return $zip;
};

$hashList = static function (array $files): array {
    $items = [];
    foreach ($files as $relativePath => $sourcePath) {
        $items[] = [
            'path' => $relativePath,
            'size' => filesize($sourcePath),
            'sha256' => hash_file('sha256', $sourcePath),
        ];
    }
    return $items;
};

$writeChecksum = static function (string $path): void {
    file_put_contents($path . '.sha256', hash_file('sha256', $path) . '  ' . basename($path) . PHP_EOL);
};

$coreFiles = $walk($root, static function (string $relativePath) use ($coreExcluded): bool {
    return !$coreExcluded($relativePath);
});
$corePath = $coreDir . '/proma-pay_v' . $versionSlug . '.zip';
$coreZip = $openZip($corePath);
foreach ($coreFiles as $relativePath => $sourcePath) {
    $coreZip->addFile($sourcePath, $relativePath);
}
foreach (['plugins', 'storage/cache', 'storage/logs', 'storage/uploads', 'storage/backups', 'storage/releases', 'storage/updates'] as $emptyDirectory) {
    $coreZip->addEmptyDir($emptyDirectory);
}
$coreReleaseManifest = [
    'app' => 'proma-pay',
    'type' => 'core-installer',
    'version' => $version,
    'built_at' => gmdate('c'),
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $hashList($coreFiles),
    'excludes_operational_data' => true,
    'optional_plugins_included' => false,
];
$coreZip->addFromString('release-manifest.json', json_encode($coreReleaseManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$coreZip->close();
$writeChecksum($corePath);

$updateRelativeFiles = [
    '.gitignore',
    'assets/css/app.css',
    'assets/css/components/forms.css',
    'assets/css/components/contract-print.css',
    'assets/css/components/contract-settings.css',
    'assets/css/components/layout.css',
    'assets/js/app.js',
    'assets/js/contract-template-editor.js',
    'config/settings.php',
    'config/version.php',
    'controllers/PluginsController.php',
    'controllers/SettingsController.php',
    'controllers/ContractsController.php',
    'core/ContractPermission.php',
    'core/PluginManager.php',
    'core/PluginStatus.php',
    'helpers/functions.php',
    'manifest.json',
    'models/PluginRegistry.php',
    'models/ContractDocument.php',
    'models/ContractPrintProfile.php',
    'models/ContractTemplateRenderer.php',
    'models/ContractTemplateService.php',
    'models/Settings.php',
    'package.json',
    'scripts/build_release.php',
    'service-worker.js',
    'views/contracts/print.php',
    'views/contracts/show.php',
    'views/layouts/app.php',
    'views/layouts/auth.php',
    'views/layouts/public.php',
    'views/plugins/index.php',
    'views/settings/index.php',
    'views/settings/contracts.php',
    'views/settings/contract-preview.php',
    'database/migrations/2026_07_13_contract_template_print_engine.sql',
    'CHANGELOG.md',
    'docs/plugins/PLUGIN_UI_DESIGN_SYSTEM.md',
    'docs/plugins/PLUGIN_REINSTALLATION.md',
    'docs/debug/V1_2_9_UI_LAYOUT_AUDIT.md',
    'docs/contracts/CONTRACT_TEMPLATE_ENGINE.md',
    'docs/contracts/CONTRACT_TEMPLATE_EDITOR.md',
    'docs/contracts/CONTRACT_TEMPLATE_VERSIONING.md',
    'docs/contracts/CONTRACT_PRINT_PROFILE.md',
    'docs/contracts/CONTRACT_PRINT_COMPACT_MODE.md',
    'docs/contracts/CONTRACT_DOCUMENT_REBUILD.md',
    'docs/README.md',
    'docs/codex/01_PROJECT_MAP.md',
    'docs/debug/CONTRACT_SETTINGS_AND_PRINT_ENGINE_AUDIT.md',
    'docs/security/CONTRACT_TEMPLATE_SANITIZATION.md',
    'docs/ui/CONTRACT_SETTINGS_UI.md',
    'docs/releases/V1.3.0.md',
    'docs/reports/CONTRACT_SETTINGS_AND_PRINT_ENGINE_REPORT.md',
    'tests/README.md',
    'tests/static_v128.php',
    'tests/static_v129.php',
    'tests/static_v130.php',
];
$updateFiles = [];
foreach ($updateRelativeFiles as $relativePath) {
    $sourcePath = $root . '/' . $relativePath;
    if (!is_file($sourcePath)) {
        throw new RuntimeException('Required update file is missing: ' . $relativePath);
    }
    $updateFiles[$relativePath] = $sourcePath;
}
$updateManifestFiles = [];
foreach ($updateFiles as $relativePath => $sourcePath) {
    $updateManifestFiles[] = [
        'source' => $relativePath,
        'target' => $relativePath,
        'sha256' => hash_file('sha256', $sourcePath),
    ];
}
$updateManifest = [
    'app' => 'proma-pay',
    'name' => 'بروزرسانی پایدار Proma Pay V' . $version,
    'version' => $version,
    'minimum_version' => '1.2.9',
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $updateManifestFiles,
    'migrations' => ['database/migrations/2026_07_13_contract_template_print_engine.sql'],
    'preserves' => ['config/database.php', 'plugins/', 'storage/', 'uploads/'],
];
$updatePath = $updateDir . '/proma-update_v' . $versionSlug . '.zip';
$updateZip = $openZip($updatePath);
foreach ($updateFiles as $relativePath => $sourcePath) {
    $updateZip->addFile($sourcePath, $relativePath);
}
$updateJson = json_encode($updateManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$updateZip->addFromString('proma-update.json', $updateJson);
$updateZip->close();
file_put_contents($updateDir . '/proma-update_v' . $versionSlug . '-manifest.json', $updateJson . PHP_EOL);
$writeChecksum($updatePath);

$pluginFiles = [];
$pluginIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($pluginIterator as $item) {
    if (!$item->isFile() || $item->isLink()) {
        continue;
    }
    $relativePath = $normalize(substr($item->getPathname(), strlen($pluginRoot)));
    if (preg_match('/\.(zip|log|tmp)$/i', basename($relativePath))) {
        continue;
    }
    $pluginFiles[$relativePath] = $item->getPathname();
}
ksort($pluginFiles, SORT_STRING);
$pluginPath = $pluginDir . '/proma-accounting_v' . $pluginVersionSlug . '.zip';
$pluginZip = $openZip($pluginPath);
foreach ($pluginFiles as $relativePath => $sourcePath) {
    $pluginZip->addFile($sourcePath, 'PromaAccounting/' . $relativePath);
}
$pluginBuildManifest = [
    'plugin_id' => (string) ($pluginManifest['id'] ?? 'proma-accounting'),
    'version' => $pluginVersion,
    'requires_core' => (string) ($pluginManifest['requires_core'] ?? ''),
    'built_at' => gmdate('c'),
    'files' => $hashList(array_combine(array_map(static function ($path) {
        return 'PromaAccounting/' . $path;
    }, array_keys($pluginFiles)), array_values($pluginFiles))),
];
$pluginZip->addFromString('PromaAccounting/build-manifest.json', json_encode($pluginBuildManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$pluginZip->close();
$writeChecksum($pluginPath);

foreach ([$corePath, $updatePath, $pluginPath] as $archivePath) {
    if (!is_file($archivePath) || filesize($archivePath) < 100) {
        throw new RuntimeException('Release archive validation failed: ' . $archivePath);
    }
}

echo json_encode([
    'version' => $version,
    'core' => $corePath,
    'update' => $updatePath,
    'update_manifest' => $updateDir . '/proma-update_v' . $versionSlug . '-manifest.json',
    'plugin' => $pluginPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
