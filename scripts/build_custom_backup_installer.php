<?php

declare(strict_types=1);

/**
 * Build a private, user-specific V3.0.1 custom installer from a validated
 * Proma Pay backup. It embeds the database and approved data files, then asks
 * only for database connection details at install time.
 */

$root = dirname(__DIR__);
$backupPath = getenv('PROMA_CUSTOM_BACKUP_PATH') ?: 'C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-06-08-05.zip';
$baseBundlePath = $root . '/dist/candidates/archive/v3/v3.0.1/core/PromaPay-Full-v3.0.1.zip';
$outputRoot = $root . '/dist/candidates/archive/v3/v3.0.1.custom';
$outputPath = $outputRoot . '/PromaPay-Full-v3.0.1.custom.zip';
$fragmentPath = $root . '/scripts/custom_restore_install_fragment.php';
$allowedPrefixes = ['uploads/', 'storage/uploads/', 'storage/secure_uploads/', 'storage/private/', 'storage/contract-templates/'];

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}
foreach ([$backupPath, $baseBundlePath, $fragmentPath] as $input) {
    if (!is_file($input)) {
        throw new RuntimeException('Required custom build input is missing: ' . $input);
    }
}
if (file_exists($outputRoot)) {
    throw new RuntimeException('Custom candidate output already exists: ' . $outputRoot);
}
if (!mkdir($outputRoot, 0775, true) && !is_dir($outputRoot)) {
    throw new RuntimeException('Cannot create custom candidate output directory.');
}

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
$safeArchivePath = static function (string $path): string {
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || strpos($path, "\0") !== false || substr($path, 0, 1) === '/' || preg_match('/^[a-z]:/i', $path)) {
        throw new RuntimeException('Backup contains an unsafe path.');
    }
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            throw new RuntimeException('Backup contains an unsafe path.');
        }
    }
    return $path;
};

$tempPayloadPath = tempnam(sys_get_temp_dir(), 'proma-custom-payload-');
if ($tempPayloadPath === false) {
    throw new RuntimeException('Cannot create a temporary custom restore archive.');
}
@unlink($tempPayloadPath);
$outputCreated = false;

try {
    $backup = $openZip($backupPath, ZipArchive::CHECKCONS);
    $metaRaw = $backup->getFromName('backup-meta.json');
    $meta = is_string($metaRaw) ? json_decode($metaRaw, true) : null;
    if (!is_array($meta) || ($meta['app'] ?? '') !== 'Proma Pay' || ($meta['version'] ?? '') !== '2.2.10') {
        throw new RuntimeException('The supplied backup is not the expected Proma Pay V2.2.10 backup.');
    }
    $databaseSql = $backup->getFromName('database.sql');
    if (!is_string($databaseSql) || strlen($databaseSql) > 64 * 1024 * 1024) {
        throw new RuntimeException('The supplied backup does not contain an acceptable database dump.');
    }
    foreach (['users', 'contracts', 'installments'] as $table) {
        if (!preg_match('/^CREATE TABLE `' . preg_quote($table, '/') . '`\s*\(/mi', $databaseSql)) {
            throw new RuntimeException('The supplied database dump is missing a required Proma Pay table.');
        }
    }

    $payload = $createZip($tempPayloadPath);
    if (!$payload->addFromString('database.sql', $databaseSql)) {
        $payload->close();
        throw new RuntimeException('Cannot package the supplied database dump.');
    }
    $fileManifest = [];
    for ($i = 0; $i < $backup->numFiles; $i++) {
        $name = (string) $backup->getNameIndex($i);
        if ($name === '' || substr($name, -1) === '/') {
            continue;
        }
        $name = $safeArchivePath($name);
        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($name, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            continue;
        }
        $opsys = 0;
        $attributes = 0;
        if ($backup->getExternalAttributesIndex($i, $opsys, $attributes)) {
            $fileType = ($attributes >> 16) & 0170000;
            if ($fileType === 0120000) {
                throw new RuntimeException('The backup contains a symbolic link in a restored data folder.');
            }
        }
        $content = $backup->getFromIndex($i);
        if (!is_string($content)) {
            throw new RuntimeException('Cannot read a user data file from the backup.');
        }
        if (!$payload->addFromString($name, $content)) {
            $payload->close();
            throw new RuntimeException('Cannot package a user data file from the backup.');
        }
        $fileManifest[] = ['path' => $name, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
    }
    $backup->close();
    usort($fileManifest, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
    $restoreManifest = [
        'app' => 'Proma Pay',
        'source_backup' => basename($backupPath),
        'source_backup_sha256' => hash_file('sha256', $backupPath),
        'source_version' => '2.2.10',
        'target_version' => '3.0.1.custom',
        'created_at_utc' => gmdate('c'),
        'database_size' => strlen($databaseSql),
        'database_sha256' => hash('sha256', $databaseSql),
        'files' => $fileManifest,
    ];
    if (!$payload->addFromString('restore-manifest.json', json_encode($restoreManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
        $payload->close();
        throw new RuntimeException('Cannot add the custom restore manifest.');
    }
    if (!$payload->close()) {
        throw new RuntimeException('Cannot finalize the embedded restore payload.');
    }

    $base = $openZip($baseBundlePath, ZipArchive::CHECKCONS);
    $installSource = $base->getFromName('install.php');
    $fragment = file_get_contents($fragmentPath);
    if (is_string($fragment)) {
        $fragment = preg_replace('/^\s*<\?php\s*/', '', $fragment, 1);
    }
    $marker = '$step = (int) ($_GET[\'step\'] ?? 1);';
    if (!is_string($installSource) || !is_string($fragment) || substr_count($installSource, $marker) !== 1) {
        $base->close();
        throw new RuntimeException('Cannot safely inject the custom database restore into install.php.');
    }
    $installedGuard = "if (is_file(__DIR__ . '/installed.lock')) {";
    $customInstalledGuard = <<<'PHP'
$customRestoreBootstrapAvailable = is_file(__DIR__ . '/storage/custom-install/restore-payload.zip');
if (is_file(__DIR__ . '/installed.lock') && !$customRestoreBootstrapAvailable) {
PHP;
    if (substr_count($installSource, $installedGuard) !== 1) {
        $base->close();
        throw new RuntimeException('Cannot safely enable the one-time custom restore on an existing installation.');
    }
    $customInstallerName = 'custom-install-' . bin2hex(random_bytes(16)) . '.php';
    $customInstallSource = str_replace($installedGuard, $customInstalledGuard, $installSource);
    $customInstallSource = str_replace($marker, rtrim($fragment) . "\n\n" . $marker, $customInstallSource);
    $backupService = file_get_contents($root . '/helpers/BackupService.php');
    $versionSource = $base->getFromName('config/version.php');
    $manifestSource = $base->getFromName('manifest.json');
    $changelogSource = $base->getFromName('CHANGELOG.md');
    if (!is_string($backupService) || !is_string($versionSource) || !is_string($manifestSource) || !is_string($changelogSource)) {
        $base->close();
        throw new RuntimeException('A required Core file is missing from the V3.0.1 bundle.');
    }
    $versionSource = preg_replace("/'display'\s*=>\s*'V3\.0\.1'/", "'display' => 'V3.0.1.custom'", $versionSource, 1, $versionReplaced);
    if ($versionReplaced !== 1) {
        $base->close();
        throw new RuntimeException('Cannot set the custom display version.');
    }
    $manifestSource = str_replace('"version": "v3.0.1"', '"version": "v3.0.1.custom"', $manifestSource);
    $changelogSource = preg_replace('/^# Changelog\R/', "# Changelog\n\n## V3.0.1.custom — خصوصی\n\n- نصب سفارشی، پس از اتصال به دیتابیس خالی، بکاپ کاربر را به‌صورت خودکار بازیابی می‌کند.\n- تنظیمات و کاربران از پایگاه‌دادهٔ بکاپ وارد می‌شوند؛ فایل‌های کاربری و قالب‌های قرارداد نیز بازیابی می‌شوند.\n\n", $changelogSource, 1);

    $modifiedFiles = [
        $customInstallerName => $customInstallSource,
        'helpers/BackupService.php' => $backupService,
        'config/version.php' => $versionSource,
        'manifest.json' => $manifestSource,
        'CHANGELOG.md' => $changelogSource,
        'storage/custom-install/restore-payload.zip' => file_get_contents($tempPayloadPath),
    ];
    if (!is_string($modifiedFiles['storage/custom-install/restore-payload.zip'])) {
        $base->close();
        throw new RuntimeException('Cannot read the embedded restore payload.');
    }
    $releaseRaw = $base->getFromName('release-manifest.json');
    $releaseManifest = is_string($releaseRaw) ? json_decode($releaseRaw, true) : null;
    if (!is_array($releaseManifest) || !is_array($releaseManifest['files'] ?? null)) {
        $base->close();
        throw new RuntimeException('The V3.0.1 bundle release manifest is missing or invalid.');
    }
    $indexedFiles = [];
    foreach ($releaseManifest['files'] as $file) {
        if (is_array($file) && !empty($file['path'])) {
            $indexedFiles[(string) $file['path']] = $file;
        }
    }
    foreach ($modifiedFiles as $path => $content) {
        $record = ['path' => $path, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
        $indexedFiles[$path] = $record;
    }
    ksort($indexedFiles, SORT_STRING);
    $releaseManifest['files'] = array_values($indexedFiles);
    $releaseManifest['custom_build'] = true;
    $releaseManifest['backup_source_version'] = '2.2.10';
    $releaseManifest['display_version'] = '3.0.1.custom';
    $releaseManifestContent = json_encode($releaseManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $customZip = $createZip($outputPath);
    $outputCreated = true;
    for ($i = 0; $i < $base->numFiles; $i++) {
        $name = (string) $base->getNameIndex($i);
        if ($name === 'release-manifest.json' || isset($modifiedFiles[$name])) {
            continue;
        }
        if (substr($name, -1) === '/') {
            if (!$customZip->addEmptyDir(rtrim($name, '/')) && $customZip->locateName($name) === false) {
                throw new RuntimeException('Cannot copy package directory entry: ' . $name);
            }
            continue;
        }
        $content = $base->getFromIndex($i);
        if (!is_string($content) || !$customZip->addFromString($name, $content)) {
            throw new RuntimeException('Cannot copy package file: ' . $name);
        }
    }
    $base->close();
    foreach ($modifiedFiles as $name => $content) {
        if (!$customZip->addFromString($name, $content)) {
            throw new RuntimeException('Cannot add custom package file: ' . $name);
        }
    }
    if (!$customZip->addFromString('release-manifest.json', $releaseManifestContent)) {
        throw new RuntimeException('Cannot add the custom release manifest.');
    }
    if (!$customZip->close()) {
        throw new RuntimeException('Cannot finalize the custom full-install package.');
    }

    $packageSha = hash_file('sha256', $outputPath);
    $readme = "# Proma Pay V3.0.1.custom — بستهٔ نصب خصوصی\n\n"
        . "این بسته برای بکاپ خصوصی V2.2.10 شما ساخته شده است. پایگاه‌داده، تنظیمات ذخیره‌شده، کاربران و فایل‌های کاربری از بکاپ وارد می‌شوند. `config/database.php` و `installed.lock` در بسته نیستند.\n\n"
        . "1. فایل‌ها را در پوشهٔ ریشهٔ سامانه استخراج و جایگزینی را تأیید کنید. نصب تازه و نصب موجود پشتیبانی می‌شود.\n"
        . "2. یک دیتابیس خالی بسازید و فایل `{$customInstallerName}` را در مرورگر باز کنید.\n"
        . "3. فقط میزبان، نام دیتابیس، کاربر و گذرواژه را وارد کنید. نصب‌کننده داده‌های بکاپ را وارد کرده و فایل اتصال دیتابیس و قفل نصب را می‌سازد.\n"
        . "4. با همان حساب‌های قبلی وارد سامانه شوید.\n\n"
        . "نصب‌کننده اگر دیتابیس خالی نباشد، برای جلوگیری از بازنویسی داده‌ها متوقف می‌شود. اگر نصب نیمه‌کاره ماند، دیتابیس مقصد را خالی و دوباره اجرا کنید. این Candidate روی هاست واقعی آزمایش نشده است.\n\n"
        . "SHA-256: `{$packageSha}`\n";
    file_put_contents($outputRoot . '/README-FA.txt', $readme);
    file_put_contents($outputRoot . '/SHA256SUMS.txt', $packageSha . '  ' . basename($outputPath) . "\n");
    echo json_encode([
        'package' => $outputPath,
        'package_sha256' => $packageSha,
        'package_bytes' => filesize($outputPath),
        'embedded_database_bytes' => strlen($databaseSql),
        'embedded_user_files' => count($fileManifest),
        'source_backup_sha256' => hash_file('sha256', $backupPath),
        'status' => 'PRIVATE_CUSTOM_CANDIDATE_NOT_HOST_TESTED',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    if ($outputCreated && is_file($outputPath)) {
        @unlink($outputPath);
    }
    throw $e;
} finally {
    @unlink($tempPayloadPath);
}
