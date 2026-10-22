<?php

require_once __DIR__ . '/FilesystemPathGuard.php';
require_once __DIR__ . '/RetiredCoreFiles.php';

class ScriptUpdateService
{
    public const CONFIRM_TEXT = 'بروزرسانی را تایید می‌کنم';
    protected const MAX_PACKAGE_SIZE = 167772160;
    protected const MAX_PACKAGE_ENTRIES = 20000;
    protected const MAX_PACKAGE_ENTRY_SIZE = 67108864;
    protected const MAX_PACKAGE_UNCOMPRESSED_SIZE = 536870912;
    protected const MAX_MANIFEST_SIZE = 4194304;

    public static function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /** Effective file size limit after accounting for PHP's request and file limits. */
    public static function uploadLimits()
    {
        $uploadLimit = self::iniSizeBytes(ini_get('upload_max_filesize'));
        $postLimit = self::iniSizeBytes(ini_get('post_max_size'));
        $limits = [self::MAX_PACKAGE_SIZE];
        if ($uploadLimit !== null) {
            $limits[] = $uploadLimit;
        }
        if ($postLimit !== null) {
            // Leave room for multipart boundaries, CSRF data, and request fields.
            $limits[] = max(0, $postLimit - 1024 * 1024);
        }

        return [
            'max_bytes' => max(0, min($limits)),
            'application_limit' => self::formatBytes(self::MAX_PACKAGE_SIZE),
            'upload_limit' => $uploadLimit === null ? 'بدون محدودیت' : self::formatBytes($uploadLimit),
            'post_limit' => $postLimit === null ? 'بدون محدودیت' : self::formatBytes($postLimit),
            'post_limit_bytes' => $postLimit,
        ];
    }

    public static function postLimitExceeded($contentLength)
    {
        $limits = self::uploadLimits();
        $contentLength = trim((string) $contentLength);
        return $limits['post_limit_bytes'] !== null
            && preg_match('/^\d+$/', $contentLength)
            && (int) $contentLength > $limits['post_limit_bytes'];
    }

    protected static function iniSizeBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1' || $value === '0') {
            return null;
        }
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?)b?$/i', $value, $matches)) {
            return null;
        }
        $power = ['' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4][strtolower($matches[2])];
        return (int) floor((float) $matches[1] * (1024 ** $power));
    }

    protected static function formatBytes($bytes)
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public static function packages()
    {
        self::relaxRuntimeLimits();
        $packages = [];
        foreach (glob(self::baseDir() . DIRECTORY_SEPARATOR . 'proma-update-*.zip') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $info = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
                'is_valid' => false,
                'title' => '',
                'version' => '',
                'files_count' => 0,
                'migrations_count' => 0,
                'is_installable' => false,
                'version_state' => 'invalid',
                'version_message' => '',
                'error' => '',
            ];
            try {
                $manifest = self::readManifest($path, false);
                $compatibility = self::packageCompatibility($manifest);
                $info['is_valid'] = true;
                $info['title'] = (string) ($manifest['name'] ?? $manifest['title'] ?? 'بسته بروزرسانی');
                $info['version'] = (string) ($manifest['version'] ?? '');
                $info['files_count'] = count(self::manifestFiles($manifest));
                $info['retired_files_count'] = count(self::manifestRetiredFiles($manifest));
                $info['migrations_count'] = count(self::manifestMigrations($manifest));
                $info['is_installable'] = $compatibility['is_installable'];
                $info['version_state'] = $compatibility['state'];
                $info['version_message'] = $compatibility['message'];
            } catch (Throwable $e) {
                $info['error'] = $e->getMessage();
            }
            $packages[] = $info;
        }
        usort($packages, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $packages;
    }

    public static function upload($upload)
    {
        self::relaxRuntimeLimits();
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZipArchive روی PHP فعال نیست.');
        }
        if (!is_array($upload)) {
            throw new RuntimeException('فایل بروزرسانی به سرور نرسیده است؛ صفحه را تازه‌سازی و دوباره تلاش کنید.');
        }
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $limits = self::uploadLimits();
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'حجم فایل از سقف upload_max_filesize هاست (' . $limits['upload_limit'] . ') بیشتر است. سقف موثر این فرم ' . self::formatBytes($limits['max_bytes']) . ' است؛ محدودیت PHP را در تنظیمات هاست افزایش دهید یا بسته کوچک‌تر آپلود کنید.',
                UPLOAD_ERR_FORM_SIZE => 'حجم فایل از سقف مجاز فرم بیشتر است.',
                UPLOAD_ERR_PARTIAL => 'فایل فقط بخشی از مسیر آپلود شد؛ اتصال را بررسی و دوباره تلاش کنید.',
                UPLOAD_ERR_NO_FILE => 'ابتدا فایل ZIP بروزرسانی را انتخاب کنید.',
                UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت آپلود PHP در هاست موجود نیست؛ تنظیمات PHP را بررسی کنید.',
                UPLOAD_ERR_CANT_WRITE => 'هاست نتوانست فایل موقت آپلود را روی دیسک بنویسد؛ فضای دیسک و مجوزها را بررسی کنید.',
                UPLOAD_ERR_EXTENSION => 'یکی از افزونه‌های PHP جلوی آپلود فایل را گرفت؛ لاگ PHP هاست را بررسی کنید.',
            ];
            throw new RuntimeException($messages[$uploadError] ?? 'بارگذاری فایل بروزرسانی کامل نشد (کد ' . $uploadError . ').');
        }
        if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new RuntimeException('فایل موقت آپلود معتبر نیست؛ دوباره فایل را انتخاب کنید.');
        }
        if ((int) ($upload['size'] ?? 0) > self::MAX_PACKAGE_SIZE) {
            throw new RuntimeException('حجم بسته بروزرسانی بیش از حد مجاز است.');
        }
        if (strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('فقط فایل zip بروزرسانی پذیرفته می‌شود.');
        }

        $original = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo((string) $upload['name'], PATHINFO_FILENAME));
        $original = trim($original, '-_.') ?: 'package';
        if (preg_match('/^proma-update-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-[a-z0-9._-]+$/i', $original)) {
            $name = $original . '.zip';
        } else {
            $name = 'proma-update-' . date('Y-m-d-H-i-s') . '-' . $original . '.zip';
        }
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            $name = pathinfo($name, PATHINFO_FILENAME) . '-copy-' . date('YmdHis') . '.zip';
            $path = self::baseDir() . DIRECTORY_SEPARATOR . $name;
        }
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new RuntimeException('ذخیره بسته بروزرسانی انجام نشد.');
        }

        try {
            self::quickZipCheck($path);
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }

        try {
            BackupService::log('update_upload', $name, 'success', 'update package uploaded; full validation deferred to install');
        } catch (Throwable $e) {
            // A nonessential audit-log failure must not hide a package that was saved successfully.
            error_log('[PromaPay][update_upload] Package saved but backup log failed: ' . $e->getMessage());
        }
        return $name;
    }

    public static function install($fileName)
    {
        $lock = fopen(self::baseDir() . DIRECTORY_SEPARATOR . '.install.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('قفل نصب بروزرسانی قابل ایجاد نیست.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('نصب بروزرسانی دیگری در حال اجرا است.');
        }
        $maintenance = false;
        try {
            if (class_exists('RequestMaintenanceLock')) {
                RequestMaintenanceLock::exclusive();
                $maintenance = true;
            }
            return self::installLocked($fileName);
        } finally {
            if ($maintenance) RequestMaintenanceLock::shared();
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected static function installLocked($fileName)
    {
        self::relaxRuntimeLimits();
        $path = self::findPackage($fileName);
        if (!$path) {
            throw new RuntimeException('بسته بروزرسانی پیدا نشد.');
        }
        $manifest = self::readManifest($path, true);
        $compatibility = self::packageCompatibility($manifest);
        if (!$compatibility['is_installable']) {
            throw new RuntimeException($compatibility['message']);
        }
        $files = self::manifestFiles($manifest);
        $migrations = self::manifestMigrations($manifest);
        $retiredFiles = self::manifestRetiredFiles($manifest);
        if (!$files && !$migrations && !$retiredFiles) {
            throw new RuntimeException('manifest بسته هیچ فایل یا migration برای نصب معرفی نکرده است.');
        }

        self::preflightPackage($path, $files, $migrations, $retiredFiles);
        $safety = BackupService::create('before-update');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('بسته بروزرسانی قابل خواندن نیست.');
        }

        $installed = [];
        $removed = [];
        $originals = [];
        $keepSnapshots = false;
        $migrationStarted = false;
        try {
            foreach ($files as $fileSpec) {
                $source = self::safePackagePath($fileSpec['source']);
                $target = self::safeTargetPath($fileSpec['target']);
                $expectedPrevious = strtolower(trim((string) ($fileSpec['expected_previous_sha256'] ?? '')));
                $targetPath = self::checkedUpdateTargetPath($target);
                if ($expectedPrevious !== '' && (!is_file($targetPath) || !hash_equals($expectedPrevious, hash_file('sha256', $targetPath)))) {
                    throw new RuntimeException('نسخه فایل فعلی با مبنای این بروزرسانی یکسان نیست: ' . $target . '. ابتدا بسته سازگار با نسخه نصب‌شده را انتخاب کنید.');
                }
                $originals[] = self::snapshotUpdateTarget($targetPath, $target);
                $actualHash = self::writeRootFileFromZip(
                    $zip,
                    $source,
                    $target,
                    strtolower(trim((string) ($fileSpec['sha256'] ?? '')))
                );
                if ($actualHash === false) {
                    throw new RuntimeException('فایل ' . $source . ' داخل بسته پیدا نشد.');
                }
                $installed[] = $target;
            }

            foreach ($retiredFiles as $relativePath) {
                $target = self::safeRetiredTargetPath($relativePath['path']);
                $targetPath = self::checkedUpdateTargetPath($target);
                $expectedPrevious = $relativePath['expected_previous_sha256'];
                if (is_file($targetPath) && !hash_equals($expectedPrevious, strtolower(hash_file('sha256', $targetPath)))) {
                    throw new RuntimeException('نسخه فایل بازنشسته با مبنای این بروزرسانی یکسان نیست: ' . $target . '. فایل تغییرکرده را دستی بررسی کنید.');
                }
                $originals[] = self::snapshotUpdateTarget($targetPath, $target);
                if (file_exists($targetPath)) {
                    if (!@unlink($targetPath)) {
                        throw new RuntimeException('حذف فایل بازنشسته انجام نشد: ' . $target);
                    }
                    self::pruneRetiredDirectories($target);
                }
                $removed[] = $target;
            }

            foreach ($migrations as $migration) {
                $migrationPath = self::safePackagePath($migration);
                $sql = self::zipContent($zip, $migrationPath);
                if ($sql === false) {
                    throw new RuntimeException('migration معرفی‌شده در بسته پیدا نشد: ' . $migrationPath);
                }
                $migrationStarted = true;
                self::runUpdateMigration($migrationPath, $sql);
            }
        } catch (Throwable $e) {
            if ($migrationStarted) {
                try {
                    BackupService::restoreUpdateDatabaseSnapshot($safety['name']);
                } catch (Throwable $rollbackError) {
                    $keepSnapshots = true;
                    $snapshotPaths = array_filter(array_column($originals, 'snapshot'));
                    throw new RuntimeException(
                        'migration بروزرسانی ناموفق بود و rollback خودکار دیتابیس انجام نشد. بکاپ پیش از ارتقا: '
                        . $safety['name'] . '. نسخه‌های موقت فایل‌ها: ' . implode(', ', $snapshotPaths)
                        . '. علت: ' . $e->getMessage() . '؛ خطای rollback دیتابیس: ' . $rollbackError->getMessage(),
                        0,
                        $e
                    );
                }

                $rollbackErrors = self::restoreUpdateTargets($originals);
                if ($rollbackErrors) {
                    $keepSnapshots = true;
                    $snapshotPaths = array_filter(array_column($originals, 'snapshot'));
                    throw new RuntimeException(
                        'دیتابیس از بکاپ پیش از ارتقا برگشت، اما بازگردانی فایل‌های نسخه قبلی کامل نشد. بکاپ: '
                        . $safety['name'] . '. مسیرهای ناموفق: ' . implode(', ', $rollbackErrors)
                        . '. نسخه‌های موقت فایل‌ها: ' . implode(', ', $snapshotPaths) . '. علت: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }

                throw new RuntimeException(
                    'migration بروزرسانی ناموفق بود؛ دیتابیس و فایل‌ها از وضعیت پیش از ارتقا برگشتند. بکاپ ایمنی: '
                    . $safety['name'] . '. علت: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
            $rollbackErrors = self::restoreUpdateTargets($originals);
            if ($rollbackErrors) {
                $keepSnapshots = true;
                $snapshotPaths = array_filter(array_column($originals, 'snapshot'));
                throw new RuntimeException($e->getMessage() . ' بازگردانی فایل‌ها نیز ناموفق بود: ' . implode(', ', $rollbackErrors) . '. نسخه‌های موقت: ' . implode(', ', $snapshotPaths), 0, $e);
            }
            throw $e;
        } finally {
            $zip->close();
            if (!$keepSnapshots) {
                self::discardUpdateSnapshots($originals);
            }
        }

        BackupService::log(
            'update_install',
            basename($path),
            'success',
            'installed files: ' . count($installed) . '; retired files: ' . count($removed) . '; migrations: ' . count($migrations) . '; safety backup: ' . $safety['name']
        );
        return [
            'backup' => $safety['name'],
            'installed_files' => $installed,
            'retired_files' => $removed,
            'migrations_count' => count($migrations),
        ];
    }

    /** Reject an incomplete or incompatible package before any backup or file replacement. */
    protected static function preflightPackage($path, array $files, array $migrations, array $retiredFiles = [])
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('بسته بروزرسانی قابل خواندن نیست.');
        }
        try {
            self::validateZipEntries($zip);
            $seenTargets = [];
            foreach ($files as $fileSpec) {
                $source = self::safePackagePath($fileSpec['source']);
                $target = self::safeTargetPath($fileSpec['target']);
                if (isset($seenTargets[strtolower($target)])) {
                    throw new RuntimeException('مسیر مقصد در بسته بروزرسانی تکراری است: ' . $target);
                }
                $seenTargets[strtolower($target)] = true;
                $targetPath = self::checkedUpdateTargetPath($target);
                $entry = self::zipEntryInfo($zip, $source);
                if ($entry === false) {
                    throw new RuntimeException('فایل ' . $source . ' داخل بسته پیدا نشد.');
                }
                $expectedHash = strtolower(trim((string) ($fileSpec['sha256'] ?? '')));
                if ($expectedHash !== '' && !hash_equals($expectedHash, $entry['sha256'])) {
                    throw new RuntimeException('امضای sha256 فایل ' . $source . ' معتبر نیست.');
                }
                $previousHash = strtolower(trim((string) ($fileSpec['expected_previous_sha256'] ?? '')));
                if ($previousHash !== '') {
                    if (!is_file($targetPath) || !hash_equals($previousHash, hash_file('sha256', $targetPath))) {
                        throw new RuntimeException('نسخه فایل فعلی با مبنای این بروزرسانی یکسان نیست: ' . $target . '. ابتدا بسته سازگار با نسخه نصب‌شده را انتخاب کنید.');
                    }
                }
            }
            foreach ($retiredFiles as $relativePath) {
                $target = self::safeRetiredTargetPath($relativePath['path']);
                if (isset($seenTargets[strtolower($target)])) {
                    throw new RuntimeException('مسیر مقصد در بسته بروزرسانی تکراری است: ' . $target);
                }
                $seenTargets[strtolower($target)] = true;
                $targetPath = self::checkedUpdateTargetPath($target);
                if (file_exists($targetPath) && (!is_file($targetPath) || is_link($targetPath))) {
                    throw new RuntimeException('مسیر فایل بازنشسته عادی نیست: ' . $target);
                }
                $expectedPrevious = $relativePath['expected_previous_sha256'];
                if (is_file($targetPath) && !hash_equals($expectedPrevious, strtolower(hash_file('sha256', $targetPath)))) {
                    throw new RuntimeException('نسخه فایل بازنشسته با مبنای این بروزرسانی یکسان نیست: ' . $target . '. فایل تغییرکرده را دستی بررسی کنید.');
                }
            }
            foreach ($migrations as $migration) {
                $migrationPath = self::safePackagePath($migration);
                $sql = self::zipContent($zip, $migrationPath);
                if ($sql === false) {
                    throw new RuntimeException('migration معرفی‌شده در بسته پیدا نشد: ' . $migrationPath);
                }
                if (preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $sql)) {
                    throw new RuntimeException('migration بروزرسانی شامل دستور مخرب است: ' . $migrationPath);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** Keep byte-for-byte file copies so a failed update can restore code files. */
    protected static function snapshotUpdateTarget($targetPath, $relativePath)
    {
        if (!file_exists($targetPath)) {
            return ['path' => $targetPath, 'relative_path' => $relativePath, 'snapshot' => null];
        }
        if (!is_file($targetPath) || is_link($targetPath)) {
            throw new RuntimeException('مسیر مقصد بروزرسانی فایل عادی نیست.');
        }
        $snapshot = tempnam(sys_get_temp_dir(), 'proma-update-');
        if ($snapshot === false || !copy($targetPath, $snapshot)) {
            if ($snapshot !== false) {
                @unlink($snapshot);
            }
            throw new RuntimeException('تهیه نسخه موقت از فایل فعلی انجام نشد.');
        }
        return ['path' => $targetPath, 'relative_path' => $relativePath, 'snapshot' => $snapshot];
    }

    /** Reject symlinked parents before reading an old target or creating directories. */
    protected static function checkedUpdateTargetPath($relativePath)
    {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('ریشه برنامه قابل شناسایی نیست.');
        }
        $parts = explode('/', self::safeTargetPath($relativePath));
        $path = $root;
        foreach ($parts as $index => $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
            if (is_link($path) || ($index < count($parts) - 1 && file_exists($path) && !is_dir($path))) {
                throw new RuntimeException('مسیر مقصد بروزرسانی معتبر نیست: ' . $relativePath);
            }
        }
        return $path;
    }

    protected static function restoreUpdateTargets(array $originals)
    {
        $errors = [];
        foreach (array_reverse($originals) as $original) {
            $path = $original['path'];
            $snapshot = $original['snapshot'];
            if ($snapshot === null) {
                if (file_exists($path) && !@unlink($path)) {
                    $errors[] = $path;
                }
            } else {
                try {
                    $relativePath = (string) ($original['relative_path'] ?? '');
                    $path = self::checkedUpdateTargetPath($relativePath);
                    $root = realpath(dirname(__DIR__));
                    $directory = dirname($path);
                    if ($root === false) {
                        throw new RuntimeException('Application root is unavailable during update rollback.');
                    }
                    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new RuntimeException('Cannot recreate the parent directory during update rollback.');
                    }
                    FilesystemPathGuard::requireParent($root, $directory, $relativePath);
                    if (!@copy($snapshot, $path)) {
                        throw new RuntimeException('Cannot restore the saved file.');
                    }
                } catch (Throwable $error) {
                    $errors[] = $path . ' (' . $error->getMessage() . ')';
                }
            }
        }
        return $errors;
    }

    protected static function discardUpdateSnapshots(array $originals)
    {
        foreach ($originals as $original) {
            if ($original['snapshot'] !== null) {
                @unlink($original['snapshot']);
            }
        }
    }

    protected static function pruneRetiredDirectories($relativePath)
    {
        $boundary = RetiredCoreFiles::pruneBoundary($relativePath);
        if ($boundary === null) {
            return;
        }
        $directory = dirname(str_replace('\\', '/', (string) $relativePath));
        while ($directory !== '.' && $directory !== '') {
            $probe = self::checkedUpdateTargetPath($directory . '/.proma-retired-path-check');
            $directoryPath = dirname($probe);
            if (!is_dir($directoryPath) || is_link($directoryPath) || !@rmdir($directoryPath)) {
                return;
            }
            if (strtolower($directory) === strtolower($boundary)) {
                return;
            }
            $directory = dirname($directory);
        }
    }

    public static function delete($fileName)
    {
        $path = self::findPackage($fileName);
        if (!$path) {
            throw new RuntimeException('بسته بروزرسانی پیدا نشد.');
        }
        $name = basename($path);
        if (!unlink($path)) {
            throw new RuntimeException('حذف بسته بروزرسانی انجام نشد.');
        }
        BackupService::log('update_delete', $name, 'success', 'update package deleted');
        return $name;
    }

    public static function findPackage($fileName)
    {
        $safe = basename((string) $fileName);
        if (!preg_match('/^proma-update-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-[a-z0-9._-]+\.zip$/i', $safe)) {
            return null;
        }
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $safe;
        return is_file($path) ? $path : null;
    }

    protected static function readManifest($path, $validateEntries = true)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('بسته بروزرسانی zip معتبر نیست.');
        }
        try {
            self::validateZipEntries($zip, $validateEntries);
            $manifestContent = false;
            $manifestName = '';
            foreach (['proma-update.json', 'update-manifest.json', 'manifest.json'] as $candidate) {
                $manifestContent = self::zipContent($zip, $candidate, self::MAX_MANIFEST_SIZE);
                if ($manifestContent !== false) {
                    $manifestName = $candidate;
                    break;
                }
            }
            if ($manifestContent === false) {
                return self::buildManifestFromFullPackage($zip, $path);
            }
            $manifest = json_decode($manifestContent, true);
            if (!is_array($manifest)) {
                throw new RuntimeException('manifest بروزرسانی JSON معتبر نیست.');
            }
            if (isset($manifest['app']) && stripos((string) $manifest['app'], 'proma') === false) {
                throw new RuntimeException('این بسته برای Proma Pay معتبر نیست.');
            }
            if (!self::manifestFiles($manifest) && !self::manifestMigrations($manifest) && !self::manifestRetiredFiles($manifest)) {
                if ($manifestName === 'manifest.json') {
                    return self::buildManifestFromFullPackage($zip, $path);
                }
                throw new RuntimeException('manifest بروزرسانی هیچ فایل یا migration معتبری معرفی نکرده است.');
            }
            foreach (self::manifestFiles($manifest) as $file) {
                self::safePackagePath($file['source']);
                self::safeTargetPath($file['target']);
            }
            foreach (self::manifestMigrations($manifest) as $migration) {
                self::safePackagePath($migration);
            }
            foreach (self::manifestRetiredFiles($manifest) as $retiredFile) {
                self::safeRetiredTargetPath($retiredFile['path']);
            }
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    protected static function quickZipCheck($path)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل zip بروزرسانی قابل خواندن نیست.');
        }
        $zip->close();
    }

    protected static function zipContent(ZipArchive $zip, $path, $maxBytes = self::MAX_PACKAGE_ENTRY_SIZE)
    {
        $stream = self::openZipEntryStream($zip, $path);
        if ($stream === false) return false;
        $entry = self::readZipEntryStream($stream, $path, $maxBytes, null, true);
        return $entry['content'];
    }

    /** Return a ZIP entry's size and digest without retaining its contents in memory. */
    protected static function zipEntryInfo(ZipArchive $zip, $path, $maxBytes = self::MAX_PACKAGE_ENTRY_SIZE)
    {
        $stream = self::openZipEntryStream($zip, $path);
        if ($stream === false) return false;
        return self::readZipEntryStream($stream, $path, $maxBytes);
    }

    /** Open ZIP members by normalized path, including legacy ./ prefixes. */
    protected static function openZipEntryStream(ZipArchive $zip, $path)
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');
        foreach ([$path, './' . $path] as $candidate) {
            $stream = @$zip->getStream($candidate);
            if (is_resource($stream)) return $stream;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $normalized = preg_replace('#^\./+#', '', trim($entryName, '/'));
            if ($normalized !== $path) continue;
            $stream = @$zip->getStream($entryName);
            if (is_resource($stream)) return $stream;
        }
        return false;
    }

    /** Read a ZIP member in bounded chunks, optionally hashing or copying it. */
    protected static function readZipEntryStream($source, $path, $maxBytes, $destination = null, $collect = false)
    {
        $maxBytes = max(0, (int) $maxBytes);
        $hash = hash_init('sha256');
        $bytes = 0;
        $content = '';
        try {
            while (!feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('خواندن فایل داخل بسته بروزرسانی انجام نشد: ' . $path);
                }
                if ($chunk === '') {
                    if (feof($source)) break;
                    throw new RuntimeException('خواندن فایل داخل بسته بروزرسانی متوقف شد: ' . $path);
                }
                $chunkBytes = strlen($chunk);
                if ($bytes + $chunkBytes > $maxBytes) {
                    throw new RuntimeException('فایل داخل بسته بروزرسانی بیش از حد مجاز است: ' . $path);
                }
                $bytes += $chunkBytes;
                hash_update($hash, $chunk);

                if (is_resource($destination)) {
                    $offset = 0;
                    while ($offset < $chunkBytes) {
                        $written = fwrite($destination, substr($chunk, $offset));
                        if ($written === false || $written === 0) {
                            throw new RuntimeException('نوشتن فایل موقت بروزرسانی انجام نشد: ' . $path);
                        }
                        $offset += $written;
                    }
                } elseif ($collect) {
                    $content .= $chunk;
                }
            }
        } finally {
            fclose($source);
        }

        $result = ['bytes' => $bytes, 'sha256' => hash_final($hash)];
        if ($collect) $result['content'] = $content;
        return $result;
    }

    protected static function relaxRuntimeLimits()
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '256M');
    }

    protected static function buildManifestFromFullPackage(ZipArchive $zip, $path)
    {
        $files = [];
        $migrations = [];
        $hasAppBootstrap = false;
        $hasIndex = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $name = preg_replace('#^\./+#', '', $name);
            $name = trim($name, '/');
            if ($name === '' || substr($name, -1) === '/') {
                continue;
            }
            if ($name === 'bootstrap.php') {
                $hasAppBootstrap = true;
            }
            if ($name === 'index.php') {
                $hasIndex = true;
            }
            if (!self::isFullPackageEntryAllowed($name)) {
                continue;
            }
            if (preg_match('#^database/migrations/.+\.sql$#i', $name)) {
                $migrations[] = $name;
                continue;
            }
            $files[] = ['source' => $name, 'target' => $name, 'sha256' => null];
        }
        if (!$hasAppBootstrap || !$hasIndex || (!$files && !$migrations)) {
            throw new RuntimeException('manifest بروزرسانی داخل بسته پیدا نشد. اگر فایل نصب کامل بارگذاری می‌کنید، باید ساختار کامل Proma Pay داخل zip باشد.');
        }
        return [
            'app' => 'proma-pay',
            'name' => 'بروزرسانی کامل از ' . basename((string) $path),
            'version' => self::versionFromFileName(basename((string) $path)),
            'files' => $files,
            'migrations' => $migrations,
        ];
    }

    protected static function isFullPackageEntryAllowed($path)
    {
        $lower = strtolower($path);
        if (in_array($lower, ['config/database.php', 'installed.lock', '.env', '1.xlsx', 'database/proma-pay-install.sql', 'release-meta.json'], true)) {
            return false;
        }
        foreach (['storage/', 'uploads/', '.git/', 'vendor/', 'node_modules/'] as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return false;
            }
        }
        if (preg_match('/\.(zip|log|map|tmp)$/i', basename($path))) {
            return false;
        }
        if (preg_match('/\.sql$/i', basename($path)) && !preg_match('#^database/migrations/.+\.sql$#i', $path)) {
            return false;
        }
        return true;
    }

    protected static function versionFromFileName($name)
    {
        if (preg_match('/proma-pay_v([0-9]+(?:-[0-9]+){2})/i', (string) $name, $matches)) {
            return str_replace('-', '.', $matches[1]);
        }
        return '';
    }

    protected static function packageCompatibility(array $manifest)
    {
        $current = self::normalizeVersion(app_version());
        $target = self::normalizeVersion($manifest['version'] ?? '');
        $minimum = self::normalizeVersion($manifest['minimum_version'] ?? '');

        if ($target === '') {
            return [
                'is_installable' => false,
                'state' => 'unknown',
                'message' => 'نسخه بسته بروزرسانی در manifest مشخص نشده است.',
            ];
        }
        if ($minimum !== '' && version_compare($current, $minimum, '<')) {
            return [
                'is_installable' => false,
                'state' => 'incompatible',
                'message' => 'این بسته حداقل به نسخه ' . $minimum . ' نیاز دارد. نسخه نصب‌شده ' . $current . ' است.',
            ];
        }

        $comparison = version_compare($target, $current);
        if ($comparison === 0) {
            return [
                'is_installable' => false,
                'state' => 'current',
                'message' => 'نسخه ' . $target . ' هم‌اکنون روی سامانه نصب است و نصب مجدد آن مجاز نیست.',
            ];
        }
        if ($comparison < 0) {
            return [
                'is_installable' => false,
                'state' => 'older',
                'message' => 'نسخه بسته (' . $target . ') از نسخه نصب‌شده (' . $current . ') قدیمی‌تر است.',
            ];
        }

        return [
            'is_installable' => true,
            'state' => 'upgrade',
            'message' => 'آماده ارتقا از نسخه ' . $current . ' به ' . $target . ' است.',
        ];
    }

    protected static function normalizeVersion($version)
    {
        $version = trim((string) $version);
        $version = ltrim($version, "vV \t\n\r\0\x0B");
        return preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
    }

    protected static function manifestFiles(array $manifest)
    {
        $files = [];
        foreach (($manifest['files'] ?? []) as $file) {
            if (is_string($file)) {
                $files[] = ['source' => $file, 'target' => $file, 'sha256' => null];
                continue;
            }
            if (is_array($file)) {
                $source = $file['source'] ?? $file['path'] ?? $file['file'] ?? '';
                $target = $file['target'] ?? $file['path'] ?? $source;
                if ($source !== '' && $target !== '') {
                    $files[] = [
                        'source' => (string) $source,
                        'target' => (string) $target,
                        'sha256' => $file['sha256'] ?? null,
                        'expected_previous_sha256' => $file['expected_previous_sha256'] ?? null,
                    ];
                }
            }
        }
        return $files;
    }

    protected static function manifestMigrations(array $manifest)
    {
        $migrations = [];
        foreach (($manifest['migrations'] ?? []) as $migration) {
            if (is_string($migration) && trim($migration) !== '') {
                $migrations[] = $migration;
            } elseif (is_array($migration) && !empty($migration['path'])) {
                $migrations[] = (string) $migration['path'];
            }
        }
        return $migrations;
    }

    protected static function manifestRetiredFiles(array $manifest)
    {
        $entries = $manifest['retired_files'] ?? [];
        if (!is_array($entries) || !array_is_list($entries) || count($entries) > self::MAX_PACKAGE_ENTRIES) {
            throw new RuntimeException('فهرست فایل‌های بازنشسته در manifest معتبر نیست.');
        }
        $retired = [];
        $seen = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['path'], $entry['expected_previous_sha256'])
                || !is_string($entry['path']) || trim($entry['path']) === '') {
                throw new RuntimeException('مسیر فایل بازنشسته در manifest معتبر نیست.');
            }
            $path = self::safeRetiredTargetPath($entry['path']);
            $expectedPrevious = strtolower(trim((string) $entry['expected_previous_sha256']));
            if (!preg_match('/\A[a-f0-9]{64}\z/', $expectedPrevious)) {
                throw new RuntimeException('امضای نسخه قبلی فایل بازنشسته معتبر نیست: ' . $path);
            }
            $key = strtolower($path);
            if (isset($seen[$key])) {
                throw new RuntimeException('مسیر فایل بازنشسته در manifest تکراری است: ' . $path);
            }
            $seen[$key] = true;
            $retired[] = [
                'path' => $path,
                'expected_previous_sha256' => $expectedPrevious,
            ];
        }
        return $retired;
    }

    protected static function safeRetiredTargetPath($path)
    {
        $path = self::safeTargetPath($path);
        $basename = basename($path);
        if (strlen($path) > 1024
            || (preg_match('/^\.env(?:\..*)?$/i', $basename) && strtolower($basename) !== '.env.example')
            || !RetiredCoreFiles::allows($path)) {
            throw new RuntimeException('بروزرسانی اجازه حذف فایل بازنشسته ' . $path . ' را ندارد.');
        }
        return $path;
    }

    protected static function validateZipEntries(ZipArchive $zip, $validatePaths = true)
    {
        if ($zip->numFiles > self::MAX_PACKAGE_ENTRIES) {
            throw new RuntimeException('تعداد فایل‌های بسته بروزرسانی بیش از حد مجاز است.');
        }
        $totalBytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat) || !isset($stat['size']) || $stat['size'] < 0 || $stat['size'] > self::MAX_PACKAGE_ENTRY_SIZE) {
                throw new RuntimeException('اندازه فایل داخل بسته بروزرسانی معتبر نیست.');
            }
            $totalBytes += (int) $stat['size'];
            if ($totalBytes > self::MAX_PACKAGE_UNCOMPRESSED_SIZE) {
                throw new RuntimeException('حجم بازشده بسته بروزرسانی بیش از حد مجاز است.');
            }
            if (!$validatePaths) {
                continue;
            }
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $trimmed = trim($name, '/');
            if ($trimmed === '' || strpos($trimmed, '../') !== false || strpos($trimmed, '/..') !== false || preg_match('/^[a-z]:/i', $trimmed) || substr($name, 0, 1) === '/') {
                throw new RuntimeException('مسیر داخل بسته بروزرسانی امن نیست.');
            }
        }
    }

    protected static function safePackagePath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if ($path === '' || substr($path, 0, 1) === '/' || substr($path, -1) === '/' || preg_match('/[\x00-\x1F:<>"|?*]/', $path)) {
            throw new RuntimeException('مسیر داخل بسته بروزرسانی امن نیست.');
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..'
                || rtrim($component, " .") !== $component
                || preg_match('/^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\..*)?$/i', $component)) {
                throw new RuntimeException('مسیر داخل بسته بروزرسانی امن نیست.');
            }
        }
        return $path;
    }

    protected static function safeTargetPath($path)
    {
        $path = self::safePackagePath($path);
        $blocked = ['config/database.php', 'installed.lock', '.env'];
        if (in_array(strtolower($path), $blocked, true)) {
            throw new RuntimeException('بروزرسانی اجازه تغییر فایل حساس ' . $path . ' را ندارد.');
        }
        foreach (['storage/', 'uploads/', '.git/', 'vendor/'] as $prefix) {
            if (strpos(strtolower($path), $prefix) === 0) {
                throw new RuntimeException('بروزرسانی اجازه نوشتن در مسیر ' . $prefix . ' را ندارد.');
            }
        }
        return $path;
    }

    protected static function writeRootFile($relativePath, $content)
    {
        $target = self::checkedUpdateTargetPath($relativePath);
        $root = realpath(dirname(__DIR__));
        $dir = dirname($target);
        if (!$root) throw new RuntimeException('پوشه اصلی بروزرسانی قابل خواندن نیست.');
        FilesystemPathGuard::requireParent($root, $dir, $relativePath);
        if (is_link($target)) {
            throw new RuntimeException('مسیر مقصد بروزرسانی معتبر نیست.');
        }
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException('نوشتن فایل بروزرسانی انجام نشد: ' . $relativePath);
        }
    }

    /** Stream and verify a package file before atomically replacing its target. */
    protected static function writeRootFileFromZip(ZipArchive $zip, $sourcePath, $relativePath, $expectedHash = '')
    {
        $target = self::checkedUpdateTargetPath($relativePath);
        $root = realpath(dirname(__DIR__));
        $directory = dirname($target);
        if (!$root) throw new RuntimeException('پوشه اصلی بروزرسانی قابل خواندن نیست.');
        FilesystemPathGuard::requireParent($root, $directory, $relativePath);
        if (is_link($target)) throw new RuntimeException('مسیر مقصد بروزرسانی معتبر نیست.');

        $temporary = tempnam($directory, '.proma-update-');
        if ($temporary === false) throw new RuntimeException('ساخت فایل موقت برای بروزرسانی انجام نشد: ' . $relativePath);
        $output = null;
        try {
            $source = self::openZipEntryStream($zip, $sourcePath);
            if ($source === false) return false;
            $output = fopen($temporary, 'wb');
            if ($output === false) {
                fclose($source);
                throw new RuntimeException('فایل موقت بروزرسانی باز نشد: ' . $relativePath);
            }
            $entry = self::readZipEntryStream($source, $sourcePath, self::MAX_PACKAGE_ENTRY_SIZE, $output);
            if ($expectedHash !== '' && !hash_equals($expectedHash, $entry['sha256'])) {
                throw new RuntimeException('امضای sha256 فایل ' . $sourcePath . ' معتبر نیست.');
            }
            if (!fflush($output)) throw new RuntimeException('نوشتن فایل موقت بروزرسانی کامل نشد: ' . $relativePath);
            fclose($output);
            $output = null;
            @chmod($temporary, 0644);
            if (!@rename($temporary, $target)) {
                throw new RuntimeException('جایگزینی فایل بروزرسانی انجام نشد: ' . $relativePath);
            }
            return $entry['sha256'];
        } finally {
            if (is_resource($output)) fclose($output);
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    protected static function runUpdateMigration($migrationName, $sql)
    {
        self::ensureMigrationSchema();
        $migration = Model::fetch('SELECT id, status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
        if ($migration && ($migration['status'] ?? '') === 'success') {
            return;
        }
        if (preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', (string) $sql)) {
            throw new RuntimeException('migration بروزرسانی شامل دستور مخرب است: ' . $migrationName);
        }
        Model::begin();
        try {
            if ($migration) {
                Model::execute(
                    'UPDATE migrations SET status = ?, started_at = NOW(), finished_at = NULL, error_message = NULL, executed_by = ? WHERE id = ?',
                    ['running', Auth::id(), (int) $migration['id']]
                );
            } else {
                Model::execute(
                    'INSERT INTO migrations (migration_name, batch, source_type, source_id, status, started_at, executed_by) VALUES (?, ?, ?, ?, ?, NOW(), ?)',
                    [$migrationName, 1, 'update', $migrationName, 'running', Auth::id()]
                );
            }
            self::executeSqlBatch((string) $sql);
            Model::execute(
                'UPDATE migrations SET status = ?, finished_at = NOW() WHERE migration_name = ?',
                ['success', $migrationName]
            );
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            try {
                Model::execute(
                    'UPDATE migrations SET status = ?, finished_at = NOW(), error_message = ? WHERE migration_name = ?',
                    ['failed', mb_substr($e->getMessage(), 0, 2000, 'UTF-8'), $migrationName]
                );
            } catch (Throwable $statusError) {
                ErrorHandler::log('update_migration_status', $statusError, 500);
            }
            throw $e;
        }
    }

    protected static function executeSqlBatch($sql)
    {
        foreach (self::splitSql($sql) as $statement) {
            self::executeSqlStatement($statement);
        }
    }

    protected static function executeSqlStatement($statement)
    {
        $statement = trim((string) $statement);
        if ($statement === '') {
            return;
        }
        $stmt = Model::db()->query($statement);
        if ($stmt instanceof PDOStatement) {
            try {
                self::drainStatement($stmt);
            } finally {
                $stmt->closeCursor();
            }
        }
    }

    protected static function drainStatement(PDOStatement $stmt)
    {
        do {
            if ($stmt->columnCount() > 0) {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            try {
                $hasMore = $stmt->nextRowset();
            } catch (Throwable $e) {
                $hasMore = false;
            }
        } while ($hasMore);
    }

    protected static function splitSql($sql)
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', (string) $sql);
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]))) {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($quote === null && $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $i += 2;
                while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                if ($quote === null) {
                    $quote = $char;
                } elseif ($quote === $char) {
                    if ($char !== '`' && $next === $char) {
                        $buffer .= $char . $next;
                        $i++;
                        continue;
                    }
                    $escaped = $i > 0 && $sql[$i - 1] === '\\';
                    if (!$escaped) {
                        $quote = null;
                    }
                }
            }

            if ($quote === null && $char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $statements;
    }

    protected static function ensureMigrationSchema()
    {
        Model::execute(
            "CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL DEFAULT 1,
                source_type VARCHAR(40) NOT NULL DEFAULT 'core',
                source_id VARCHAR(191) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'success',
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                executed_by BIGINT UNSIGNED NULL,
                error_message TEXT NULL,
                UNIQUE KEY uniq_migrations_migration_name (migration_name),
                KEY idx_migrations_status (status),
                KEY idx_migrations_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
