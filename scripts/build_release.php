<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$version = (string) ($versionInfo['application'] ?? '0.0.0');
$candidateMigration = $root . '/database/migrations/2026_07_18_contract_lifecycle_schema_repair_v137.sql';
if (is_file($candidateMigration) && version_compare($version, '1.3.7', '<')) {
    throw new RuntimeException('V1.3.7 release candidate is present. Update config/version.php only after the staging release gate passes; no archive may be labeled V' . $version . '.');
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required.\n");
    exit(1);
}
$versionSlug = str_replace('.', '-', $version);
$pluginPackages = [
    ['directory' => 'PromaAccounting', 'archive' => 'PromaAccounting'],
    ['directory' => 'PromaZarinpal', 'archive' => 'PromaZarinpal'],
];
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
    '.htaccess',
    'assets/css/app.css',
    'assets/css/design-system/tokens.css',
    'assets/css/components/forms.css',
    'assets/css/components/contract-print.css',
    'assets/css/components/contract-settings.css',
    'assets/css/components/layout.css',
    'assets/css/components/responsive.css',
    'assets/css/error-pages.css',
    'assets/js/app.js',
    'assets/js/contract-template-editor.js',
    'assets/vendor/chart.umd.min.js',
    'config/settings.php',
    'bootstrap.php',
    'index.php',
    'installer.php',
    'install.php',
    'config/version.php',
    'controllers/PluginsController.php',
    'controllers/BackupController.php',
    'controllers/SettingsController.php',
    'controllers/ContractsController.php',
    'controllers/HealthController.php',
    'core/Auth.php',
    'core/ContractPermission.php',
    'core/Controller.php',
    'core/Csrf.php',
    'core/ErrorHandler.php',
    'core/HttpException.php',
    'core/PluginManager.php',
    'core/PluginStatus.php',
    'core/Router.php',
    'helpers/FinanceHelper.php',
    'helpers/functions.php',
    'helpers/MoneyMath.php',
    'helpers/BackupService.php',
    'helpers/ScriptUpdateService.php',
    'manifest.json',
    'models/PluginRegistry.php',
    'models/Contract.php',
    'models/ContractDocument.php',
    'models/ContractPrintProfile.php',
    'models/ContractTemplateRenderer.php',
    'models/ContractTemplateService.php',
    'models/PaymentReceipt.php',
    'models/Installment.php',
    'models/PaymentRequest.php',
    'models/SystemOutbox.php',
    'models/Settings.php',
    'package.json',
    'scripts/build_release.php',
    'service-worker.js',
    'views/contracts/print.php',
    'views/errors/system.php',
    'views/contracts/show.php',
    'views/layouts/app.php',
    'views/layouts/auth.php',
    'views/layouts/public.php',
    'views/plugins/index.php',
    'views/settings/index.php',
    'views/settings/contracts.php',
    'views/settings/contract-preview.php',
    'database/migrations/2026_07_13_contract_template_print_engine.sql',
    'database/migrations/2026_07_13_contract_print_compact_version_retention.sql',
    'database/migrations/2026_07_13_payment_reliability_v135.sql',
    'database/migrations/2026_07_18_runtime_schema_gate_v136.sql',
    'database/migrations/2026_07_18_contract_lifecycle_schema_repair_v137.sql',
    'controllers/PaymentsController.php',
    'core/PaymentGatewayProviderInterface.php',
    'core/PaymentGatewayRegistry.php',
    'helpers/PaymentGroupService.php',
    'helpers/ZibalGatewayProvider.php',
    'models/Payment.php',
    'views/installments/index.php',
    'CHANGELOG.md',
    'docs/plugins/PLUGIN_UI_DESIGN_SYSTEM.md',
    'docs/plugins/PLUGIN_REINSTALLATION.md',
    'docs/plugins/PROMA_ACCOUNTING_UI_GUIDE.md',
    'docs/debug/V1_2_9_UI_LAYOUT_AUDIT.md',
    'docs/contracts/CONTRACT_TEMPLATE_ENGINE.md',
    'docs/contracts/CONTRACT_TEMPLATE_EDITOR.md',
    'docs/contracts/CONTRACT_TEMPLATE_VERSIONING.md',
    'docs/contracts/CONTRACT_PRINT_PROFILE.md',
    'docs/contracts/CONTRACT_PRINT_COMPACT_MODE.md',
    'docs/contracts/COMPACT_A4_PRINT_PROFILE.md',
    'docs/contracts/CONTRACT_PRINT_TYPOGRAPHY.md',
    'docs/contracts/IMPORTANT_CLAUSE_MARKUP.md',
    'docs/contracts/TEMPLATE_VERSION_DELETION.md',
    'docs/contracts/TEMPLATE_VERSION_RETENTION.md',
    'docs/contracts/CONTRACT_DOCUMENT_REBUILD.md',
    'docs/README.md',
    'docs/codex/01_PROJECT_MAP.md',
    'docs/debug/CONTRACT_SETTINGS_AND_PRINT_ENGINE_AUDIT.md',
    'docs/security/CONTRACT_TEMPLATE_SANITIZATION.md',
    'docs/ui/CONTRACT_SETTINGS_UI.md',
    'docs/releases/V1.3.0.md',
    'docs/releases/V1.3.1.md',
    'docs/releases/V1.3.2.md',
    'docs/releases/V1.3.3.md',
    'docs/releases/PROMA_ACCOUNTING_V1.2.0.md',
    'docs/reports/CONTRACT_SETTINGS_AND_PRINT_ENGINE_REPORT.md',
    'docs/reports/V1_3_1_CONTRACT_PRINT_AND_TEMPLATE_VERSION_REPORT.md',
    'docs/reports/PROMA_ACCOUNTING_MODERN_UI_REPORT.md',
    'docs/reports/PROMA_PAY_V1_3_5_PAYMENT_RELIABILITY_REPORT.md',
    'docs/reports/PROMA_PAY_V1_3_6_RELIABILITY_AND_FAILURE_EXPERIENCE_REPORT.md',
    'docs/debug/CONTRACT_CANCELLATION_AUDIT_V1_3_6.md',
    'docs/debug/CONTRACT_DELETE_AUDIT_V1_3_6.md',
    'docs/incidents/V1_3_6_TIMEOUT_INVESTIGATION.md',
    'docs/incidents/INTERMITTENT_TIMEOUT_INVESTIGATION.md',
    'docs/qa/V1_3_7_TEST_RESULTS.md',
    'docs/qa/V1_3_7_RELEASE_CHECKLIST.md',
    'docs/qa/V1_3_7_PLUGIN_TESTS.md',
    'docs/qa/V1_3_7_INSTALL_AND_UPGRADE.md',
    'docs/debug/PROMA_ACCOUNTING_MODERN_UI_AUDIT.md',
    'docs/screenshots/PROMA_ACCOUNTING_V1_1_0_BEFORE.png',
    'docs/screenshots/PROMA_ACCOUNTING_V1_2_0_AFTER.png',
    'docs/debug/V1_3_0_CONTRACT_PRINT_AND_TEMPLATE_VERSION_AUDIT.md',
    'docs/debug/PROMA_ZARINPAL_PLUGIN_AUDIT.md',
    'docs/plugins/PROMA_ZARINPAL.md',
    'docs/plugins/ZARINPAL_CONFIGURATION.md',
    'docs/plugins/ZARINPAL_INSTALLATION.md',
    'docs/plugins/ZARINPAL_SANDBOX.md',
    'docs/plugins/ZARINPAL_SECURITY.md',
    'docs/plugins/ZARINPAL_TROUBLESHOOTING.md',
    'docs/workflows/ZARINPAL_CALLBACK.md',
    'docs/workflows/ZARINPAL_MULTI_PAYMENT.md',
    'docs/workflows/ZARINPAL_RECONCILIATION.md',
    'docs/workflows/ZARINPAL_SINGLE_PAYMENT.md',
    'docs/releases/V1.3.4.md',
    'docs/releases/V1.3.5.md',
    'docs/releases/V1.3.6.md',
    'docs/releases/V1.3.7.md',
    'docs/reports/PROMA_ZARINPAL_PLUGIN_IMPLEMENTATION_REPORT.md',
    'tests/README.md',
    'tests/static_v128.php',
    'tests/static_v129.php',
    'tests/static_v130.php',
    'tests/static_v131.php',
    'tests/static_v132.php',
    'tests/static_v133.php',
    'tests/static_v134.php',
    'tests/static_v135.php',
    'tests/static_v136.php',
    'tests/static_v137.php',
    'tests/financial_precision_v136.php',
    'tests/error_response_v136.php',
    'tools/release-gate.php',
    'static-errors/500.html',
    'static-errors/503.html',
    'static-errors/400.html',
    'static-errors/403.html',
    'static-errors/404.html',
    'static-errors/429.html',
    'static-errors/502.html',
    'static-errors/504.html',
    'tests/integration_zarinpal_v134.php',
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
    'minimum_version' => '1.3.0',
    'plugin_api' => (string) ($versionInfo['plugin_api'] ?? '1.0'),
    'files' => $updateManifestFiles,
    'migrations' => [
        'database/migrations/2026_07_13_contract_template_print_engine.sql',
        'database/migrations/2026_07_13_contract_print_compact_version_retention.sql',
        'database/migrations/2026_07_13_payment_reliability_v135.sql',
        'database/migrations/2026_07_18_runtime_schema_gate_v136.sql',
        'database/migrations/2026_07_18_contract_lifecycle_schema_repair_v137.sql',
    ],
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

$pluginPaths = [];
foreach ($pluginPackages as $pluginPackage) {
    $pluginDirectory = (string) $pluginPackage['directory'];
    $pluginArchiveName = (string) $pluginPackage['archive'];
    $pluginRoot = $root . '/plugins/' . $pluginDirectory;
    $manifestPath = $pluginRoot . '/plugin.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('Plugin manifest is missing: ' . $manifestPath);
    }
    $pluginManifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $pluginVersion = (string) ($pluginManifest['version'] ?? '0.0.0');
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
    $pluginPath = $pluginDir . '/' . $pluginArchiveName . '-' . $pluginVersion . '.zip';
    $pluginZip = $openZip($pluginPath);
    foreach ($pluginFiles as $relativePath => $sourcePath) {
        $pluginZip->addFile($sourcePath, $pluginDirectory . '/' . $relativePath);
    }
    $manifestFileMap = [];
    foreach ($pluginFiles as $relativePath => $sourcePath) {
        $manifestFileMap[$pluginDirectory . '/' . $relativePath] = $sourcePath;
    }
    $pluginBuildManifest = [
        'plugin_id' => (string) ($pluginManifest['id'] ?? ''),
        'version' => $pluginVersion,
        'requires_core' => (string) ($pluginManifest['requires_core'] ?? ''),
        'built_at' => gmdate('c'),
        'files' => $hashList($manifestFileMap),
    ];
    $pluginZip->addFromString(
        $pluginDirectory . '/build-manifest.json',
        json_encode($pluginBuildManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $pluginZip->close();
    $writeChecksum($pluginPath);
    $pluginPaths[(string) ($pluginManifest['id'] ?? $pluginDirectory)] = $pluginPath;
}

foreach (array_merge([$corePath, $updatePath], array_values($pluginPaths)) as $archivePath) {
    if (!is_file($archivePath) || filesize($archivePath) < 100) {
        throw new RuntimeException('Release archive validation failed: ' . $archivePath);
    }
}

echo json_encode([
    'version' => $version,
    'core' => $corePath,
    'update' => $updatePath,
    'update_manifest' => $updateDir . '/proma-update_v' . $versionSlug . '-manifest.json',
    'plugins' => $pluginPaths,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
