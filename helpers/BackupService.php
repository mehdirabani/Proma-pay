<?php

require_once __DIR__ . '/FilesystemPathGuard.php';

class BackupService
{
    protected static $restoreJournal = null;
    protected const RESTORE_MAX_UPLOAD_BYTES = 536870912; // 512 MiB hard ceiling; PHP limits remain authoritative.
    protected const RESTORE_MAX_SQL_BYTES = 8589934592; // 8 GiB streamed SQL ceiling.
    protected const RESTORE_MAX_SQL_STATEMENT_BYTES = 67108864; // 64 MiB per statement.
    protected const RESTORE_MAX_EXPANDED_BYTES = 8589934592; // 8 GiB guard against ZIP expansion bombs.
    public const CONFIRM_TEXT = 'بازیابی را تایید می‌کنم';

    public static function ensureSchema()
    {
        SchemaGuard::requireColumns('backup_logs', ['user_id', 'action', 'file_name', 'status', 'message', 'created_at']);
    }

    public static function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Keep a second, private location for safety backups when storage/backups
     * is not writable. The system temp directory is used only as a last resort.
     */
    protected static function fallbackDirectories($create = false)
    {
        $root = dirname(__DIR__);
        $storageFallback = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.backup-fallback';
        $directories = [];

        if ($create) {
            if (self::ensurePrivateDirectory($storageFallback)) {
                $directories[] = $storageFallback;
            }
        } elseif (is_dir($storageFallback) && !is_link($storageFallback)) {
            $directories[] = $storageFallback;
        }

        $tempRoot = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($tempRoot !== '') {
            $tempFallback = $tempRoot . DIRECTORY_SEPARATOR . 'proma-pay-backup-' . substr(hash('sha256', (string) realpath($root)), 0, 16);
            $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
            $resolvedTempRoot = realpath($tempRoot);
            $tempIsPublic = $documentRoot && $resolvedTempRoot
                && ($resolvedTempRoot === $documentRoot
                    || strpos($resolvedTempRoot, rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0);
            if (!$tempIsPublic) {
                if ($create) {
                    if (self::ensurePrivateDirectory($tempFallback)) {
                        $directories[] = $tempFallback;
                    }
                } elseif (is_dir($tempFallback) && !is_link($tempFallback)) {
                    $directories[] = $tempFallback;
                }
            }
        }

        return $directories;
    }

    protected static function ensurePrivateDirectory($directory)
    {
        if (is_link($directory)) {
            return false;
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return false;
        }
        @chmod($directory, 0700);
        return is_dir($directory) && is_writable($directory);
    }

    protected static function backupDirectories($createFallback = false)
    {
        return array_values(array_unique(array_merge([self::baseDir()], self::fallbackDirectories($createFallback))));
    }

    protected static function backupFileName($reason, $extension)
    {
        $suffix = preg_replace('/[^a-z0-9_-]/i', '', (string) $reason);
        try {
            $unique = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            $unique = str_replace('.', '', uniqid('', true));
        }
        return 'proma-pay-backup-' . date('Y-m-d-H-i') . '-' . date('s') . '-'
            . ($suffix !== '' ? $suffix . '-' : '') . $unique . $extension;
    }

    protected static function zipOpenError(ZipArchive $zip, $result)
    {
        $parts = ['کد ' . (string) $result];
        if (method_exists($zip, 'getStatusString')) {
            try {
                $status = trim((string) $zip->getStatusString());
                if ($status !== '' && strtolower($status) !== 'no error') {
                    $parts[] = $status;
                }
            } catch (Throwable $e) {
                // Keep the native open result when the extension cannot report a string.
            }
        }
        return implode('؛ ', $parts);
    }

    public static function logs($limit = 20)
    {
        self::ensureSchema();
        return Model::fetchAll('SELECT * FROM backup_logs ORDER BY id DESC LIMIT ' . max(1, (int) $limit));
    }

    public static function deleteLogs(array $ids)
    {
        self::ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (!$ids || count($ids) > 200) {
            throw new InvalidArgumentException('حداقل یک و حداکثر ۲۰۰ لاگ معتبر انتخاب کنید.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Model::execute('DELETE FROM backup_logs WHERE id IN (' . $placeholders . ')', $ids);
    }

    public static function clearLogs()
    {
        self::ensureSchema();
        return Model::execute('DELETE FROM backup_logs');
    }

    public static function files()
    {
        $files = [];
        foreach (self::backupDirectories() as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . 'proma-pay-backup-*.*') ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $name = basename($path);
                if (!self::findBackup($name)) {
                    continue;
                }
                $files[$name] = [
                    'name' => $name,
                    'type' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                    'size' => (int) filesize($path),
                    'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
                ];
            }
        }
        $files = array_values($files);
        usort($files, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $files;
    }

    public static function log($action, $fileName, $status, $message = '')
    {
        try {
            self::ensureSchema();
            Model::execute(
                'INSERT INTO backup_logs (user_id, action, file_name, status, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [Auth::id(), $action, $fileName, $status, $message]
            );
            return true;
        } catch (Throwable $e) {
            // Backup/update logs are operational telemetry. A log-table failure must
            // not turn an already-completed backup, restore, delete, or update into
            // a reported failure. Keep a minimal server-side diagnostic instead.
            $safeAction = preg_replace('/[^a-z0-9_-]/i', '', (string) $action);
            error_log('[PromaPay][backup_log] Could not record action ' . ($safeAction !== '' ? $safeAction : 'unknown') . ' (' . get_class($e) . ').');
            return false;
        }
    }

    /**
     * Return the effective request/file limit for restore uploads. DirectAdmin
     * and PHP can impose different limits; leave room for multipart form data.
     */
    public static function restoreUploadLimits()
    {
        return self::resolveRestoreUploadLimits(
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            self::RESTORE_MAX_UPLOAD_BYTES
        );
    }

    public static function restoreRequestPayloadExceeded()
    {
        return self::requestPayloadExceedsPostLimit(
            $_SERVER['CONTENT_LENGTH'] ?? 0,
            ini_get('post_max_size'),
            $_POST,
            $_FILES
        );
    }

    protected static function requestPayloadExceedsPostLimit($contentLength, $postMax, array $postData, array $fileData)
    {
        $contentLength = (int) $contentLength;
        $postBytes = self::iniSizeBytes($postMax);
        return $contentLength > 0 && $postBytes !== null && $contentLength > $postBytes
            && empty($postData) && empty($fileData);
    }

    protected static function resolveRestoreUploadLimits($uploadMax, $postMax, $hardLimit)
    {
        $uploadBytes = self::iniSizeBytes($uploadMax);
        $postBytes = self::iniSizeBytes($postMax);
        // The multipart envelope also contains CSRF and confirmation fields.
        $postFileBytes = $postBytes === null ? null : max(0, $postBytes - 262144);
        $limits = array_values(array_filter([$uploadBytes, $postFileBytes, (int) $hardLimit], static function ($value) {
            return $value > 0;
        }));

        return [
            'max_bytes' => $limits ? min($limits) : (int) $hardLimit,
            'upload_limit' => trim((string) $uploadMax) !== '' ? trim((string) $uploadMax) : 'نامشخص',
            'post_limit' => trim((string) $postMax) !== '' ? trim((string) $postMax) : 'نامشخص',
        ];
    }

    protected static function iniSizeBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0' || $value === '-1') return null;
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?)\s*b?$/i', $value, $matches)) return null;
        $power = ['' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4][strtolower($matches[2])];
        $bytes = (float) $matches[1] * (1024 ** $power);
        return $bytes >= PHP_INT_MAX ? PHP_INT_MAX : (int) floor($bytes);
    }

    protected static function formatRestoreBytes($bytes)
    {
        $bytes = max(0, (float) $bytes);
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') return number_format($bytes, $unit === 'B' ? 0 : 1) . ' ' . $unit;
            $bytes /= 1024;
        }
        return number_format($bytes, 1) . ' GB';
    }

    protected static function maximumSqlDumpBytes($memoryLimit = null)
    {
        // SQL validation and import are stream-based; this is a disk/ZIP safety
        // ceiling, independent of the PHP memory_limit.
        return self::RESTORE_MAX_SQL_BYTES;
    }

    protected static function maximumSqlStatementBytes($memoryLimit = null)
    {
        $memoryBytes = self::iniSizeBytes($memoryLimit === null ? ini_get('memory_limit') : $memoryLimit);
        if ($memoryBytes === null) return self::RESTORE_MAX_SQL_STATEMENT_BYTES;
        // Leave headroom for the fetched row, quoted values, parser buffer, and
        // PDO while keeping the statement cap useful under small PHP limits.
        return max(1048576, min(self::RESTORE_MAX_SQL_STATEMENT_BYTES, intdiv($memoryBytes, 8)));
    }

    public static function restoreSqlDumpLimitBytes()
    {
        return self::maximumSqlDumpBytes();
    }

    protected static function restoreUploadLimitMessage($actualBytes, $maximumBytes)
    {
        $limits = self::restoreUploadLimits();
        return 'حجم فایل بکاپ (' . self::formatRestoreBytes($actualBytes) . ') از سقف قابل‌قبول این فرم ('
            . self::formatRestoreBytes($maximumBytes) . ') بیشتر است. محدودیت PHP: upload_max_filesize='
            . $limits['upload_limit'] . '، post_max_size=' . $limits['post_limit'] . '.';
    }

    protected static function restoreSqlSizeMessage($actualBytes, $maximumBytes)
    {
        return 'فایل SQL بازشده (' . self::formatRestoreBytes($actualBytes) . ') از سقف پردازش جریان‌محور ('
            . self::formatRestoreBytes($maximumBytes) . ') بزرگ‌تر است.';
    }

    public static function create($reason = 'manual')
    {
        self::ensureSchema();
        $extension = class_exists('ZipArchive') ? '.zip' : '.sql';
        $fileName = self::backupFileName($reason === 'manual' ? '' : $reason, $extension);
        $primaryDirectory = self::baseDir();
        $databaseSqlPath = @tempnam(sys_get_temp_dir(), 'proma-pay-dump-');
        if ($databaseSqlPath === false) throw new RuntimeException('ساخت فایل موقت امن برای dump پایگاه‌داده انجام نشد.');
        try {
        if (!class_exists('ZipArchive')) {
            self::databaseDumpToFile($databaseSqlPath, "-- Proma Pay database backup\n-- Created at: " . date('Y-m-d H:i:s') . "\n\n");
            $directories = [$primaryDirectory];
            for ($i = 0; $i < count($directories); $i++) {
                $directory = $directories[$i];
                $path = $directory . DIRECTORY_SEPARATOR . $fileName;
                if (is_writable($directory) && @copy($databaseSqlPath, $path)) {
                    self::log('backup', $fileName, 'success', 'ZipArchive unavailable; database-only SQL backup created.');
                    return ['name' => $fileName, 'path' => $path, 'type' => 'sql'];
                }
                @unlink($path);
                if ($i === 0) {
                    $directories = array_merge($directories, self::fallbackDirectories(true));
                }
            }
            throw new RuntimeException('ساخت فایل بکاپ SQL در storage/backups و مسیرهای امن جایگزین انجام نشد؛ مجوز نوشتن و فضای دیسک را بررسی کنید.');
        }

        $dumpInfo = self::databaseDumpToFile($databaseSqlPath);
        $databaseMetadata = self::databaseMetadata();
        $schemaVersion = $dumpInfo['schema_version'];
        $backupCreatedAt = date(DATE_ATOM);
        $configSnapshot = json_encode(Settings::allKeyed(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $backupMeta = json_encode([
            'app' => 'Proma Pay',
            'created_at' => $backupCreatedAt,
            'php_version' => PHP_VERSION,
            'database' => $databaseMetadata['engine'],
            'schema_version' => $schemaVersion,
            'version' => app_config('version', '1.0.0'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($configSnapshot === false || $backupMeta === false) {
            throw new RuntimeException('اطلاعات تنظیمات برای ثبت در بکاپ قابل تبدیل نیست.');
        }

        $path = null;
        $backupErrors = [];
        foreach (self::backupDirectories(true) as $directory) {
            if (!is_dir($directory) || !is_writable($directory)) {
                $backupErrors[] = basename($directory) . ': پوشه قابل نوشتن نیست';
                continue;
            }

            $candidatePath = $directory . DIRECTORY_SEPARATOR . $fileName;
            $candidateZip = new ZipArchive();
            $openResult = @$candidateZip->open($candidatePath, ZipArchive::CREATE | ZipArchive::EXCL);
            if ($openResult !== true) {
                $backupErrors[] = basename($directory) . ': ' . self::zipOpenError($candidateZip, $openResult);
                continue;
            }

            $closed = false;
            try {
                if (!$candidateZip->addFile($databaseSqlPath, 'database.sql')
                    || !$candidateZip->addFromString('config-snapshot.json', $configSnapshot)
                    || !$candidateZip->addFromString('backup-meta.json', $backupMeta)) {
                    throw new RuntimeException('افزودن اطلاعات پایگاه‌داده یا تنظیمات به فایل zip ناموفق بود.');
                }
                if (!self::addDirectory($candidateZip, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads', 'uploads')) {
                    throw new RuntimeException('افزودن فایل‌های uploads به بکاپ ناموفق بود.');
                }
                $storageRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
                $volatileStoragePaths = [];
                foreach (['backups', 'cache', 'logs', 'releases', 'sessions', 'updates', '.backup-fallback', '.runtime-lock', '.restore-work'] as $volatileDirectory) {
                    $realPath = realpath($storageRoot . DIRECTORY_SEPARATOR . $volatileDirectory);
                    if ($realPath) {
                        $volatileStoragePaths[] = $realPath;
                    }
                }
                if (!self::addDirectory($candidateZip, $storageRoot, 'storage', $volatileStoragePaths)
                    || !self::addDirectory($candidateZip, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'contracts', 'storage/contract-templates')) {
                    throw new RuntimeException('افزودن فایل‌های storage یا قالب قرارداد به بکاپ ناموفق بود.');
                }
                if (!$candidateZip->close()) {
                    $closed = true;
                    throw new RuntimeException('تکمیل فایل zip ناموفق بود؛ فضای دیسک یا محدودیت سهمیه را بررسی کنید.');
                }
                $closed = true;
                self::appendArchiveManifest($candidatePath, [
                    'application_version' => (string) app_config('version', '1.0.0'),
                    'schema_version' => $schemaVersion,
                    'created_at' => $backupCreatedAt,
                    'database_engine' => $databaseMetadata['engine'],
                    'database_version' => $databaseMetadata['version'],
                    'charset' => $databaseMetadata['charset'],
                    'collation' => $databaseMetadata['collation'],
                    'php_version' => PHP_VERSION,
                    'required_modules' => ['json', 'mbstring', 'pdo_mysql'],
                ]);
                $path = $candidatePath;
                break;
            } catch (Throwable $e) {
                if (!$closed) {
                    @$candidateZip->close();
                }
                @unlink($candidatePath);
                $backupErrors[] = basename($directory) . ': ' . $e->getMessage();
            }
        }
        if ($path === null) {
            throw new RuntimeException(
                'ساخت بکاپ در storage/backups و مسیرهای خصوصی جایگزین انجام نشد؛ مجوز PHP، مالکیت پوشه و فضای دیسک را بررسی کنید. جزئیات: '
                . implode(' | ', $backupErrors)
            );
        }
        self::log('backup', $fileName, 'success', 'backup created');
        return ['name' => $fileName, 'path' => $path, 'type' => 'zip'];
        } finally {
            @unlink($databaseSqlPath);
        }
    }

    public static function restore($upload)
    {
        if (!$upload) {
            throw new RuntimeException('فایل بکاپ انتخاب نشده است.');
        }
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $limits = self::restoreUploadLimits();
                throw new RuntimeException('حجم فایل از محدودیت بارگذاری PHP بیشتر است. سقف این فرم '
                    . self::formatRestoreBytes($limits['max_bytes']) . ' است؛ upload_max_filesize='
                    . $limits['upload_limit'] . ' و post_max_size=' . $limits['post_limit'] . '.');
            }
            if ($uploadError === UPLOAD_ERR_PARTIAL) {
                throw new RuntimeException('فایل بکاپ فقط بخشی بارگذاری شد؛ اتصال را بررسی و بارگذاری را دوباره انجام دهید.');
            }
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('فایل بکاپ انتخاب نشده است.');
            }
            throw new RuntimeException('بارگذاری فایل بکاپ کامل نشد. دوباره فایل را انتخاب کنید.');
        }
        if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
        }
        $measuredSize = @filesize($upload['tmp_name']);
        if ($measuredSize === false) throw new RuntimeException('اندازه فایل بارگذاری‌شده قابل خواندن نیست.');
        $uploadSize = (int) $measuredSize;
        $uploadMaxBytes = self::restoreUploadLimits()['max_bytes'];
        if ($uploadSize > $uploadMaxBytes) {
            throw new RuntimeException(self::restoreUploadLimitMessage($uploadSize, $uploadMaxBytes));
        }
        $format = self::detectBackupFormat($upload['tmp_name'], $upload['name'] ?? '');
        if ($format === 'sql') {
            return self::restoreSqlBackup($upload['tmp_name'], $upload['name'] ?? '');
        }
        if ($format === 'zip') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('برای بازیابی فایل zip افزونه ZipArchive باید روی PHP فعال باشد. اگر فقط SQL دارید، همان فایل .sql را بارگذاری کنید.');
            }
            return self::restoreZipBackup($upload['tmp_name'], $upload['name'] ?? '');
        }
        throw new RuntimeException('فرمت بکاپ شناخته نشد. فایل .sql یا .zip معتبر بارگذاری کنید.');
    }

    public static function restoreStored($fileName)
    {
        $path = self::findBackup($fileName);
        if (!$path) {
            throw new RuntimeException('فایل بکاپ پیدا نشد.');
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'sql') {
            return self::restoreSqlBackup($path, basename($path));
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZipArchive روی PHP فعال نیست.');
        }
        return self::restoreZipBackup($path, basename($path));
    }

    /** Restore only the database from the updater's own pre-update safety backup. */
    public static function restoreUpdateDatabaseSnapshot($fileName)
    {
        $name = basename((string) $fileName);
        if (!preg_match('/^proma-pay-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-before-update-[a-z0-9]+\.(?:zip|sql)$/i', $name)) {
            throw new RuntimeException('بکاپ بازیابی دیتابیس برای rollback بروزرسانی معتبر نیست.');
        }
        $path = self::findBackup($name);
        if (!$path) throw new RuntimeException('بکاپ ایمنی پیش از بروزرسانی پیدا نشد: ' . $name);

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'sql') {
            $size = @filesize($path);
            if ($size === false || $size > self::RESTORE_MAX_SQL_BYTES) {
                throw new RuntimeException('اندازه SQL بکاپ ایمنی برای rollback امن نیست.');
            }
            self::validateDatabaseDumpFile($path);
            self::replaceDatabaseFromFile($path);
        } else {
            if (!class_exists('ZipArchive')) throw new RuntimeException('افزونه ZipArchive برای rollback بروزرسانی فعال نیست.');
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) throw new RuntimeException('ZIP بکاپ ایمنی برای rollback قابل خواندن نیست.');
            try {
                self::validateZip($zip);
                self::validateArchiveManifest($zip);
                $database = self::databaseDumpFromZip($zip);
                try {
                    self::validateDatabaseDumpFile($database['path']);
                    self::replaceDatabaseFromFile($database['path']);
                } finally { @unlink($database['path']); }
            } finally {
                $zip->close();
            }
        }
        SchemaGuard::clearCache();
        self::log('update_rollback', $name, 'success', 'database restored from pre-update safety backup');
        return true;
    }

    protected static function restoreSqlBackup($path, $label = '')
    {
        $size = @filesize($path);
        $maximum = self::RESTORE_MAX_SQL_BYTES;
        if ($size === false) throw new RuntimeException('اندازه فایل SQL بکاپ قابل خواندن نیست.');
        if ($size > $maximum) throw new RuntimeException(self::restoreSqlSizeMessage($size, $maximum));
        self::validateDatabaseDumpFile($path);
        return self::restoreWithRecoveryFile($path, basename((string) $label) ?: basename($path));
    }

    protected static function databaseMetadata()
    {
        try {
            $row = Model::db()->query(
                'SELECT VERSION() AS version, @@character_set_database AS charset, @@collation_database AS collation'
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $version = (string) ($row['version'] ?? '');
            return [
                'engine' => stripos($version, 'mariadb') !== false ? 'MariaDB' : 'MySQL',
                'version' => $version !== '' ? $version : 'unknown',
                'charset' => (string) ($row['charset'] ?? 'unknown'),
                'collation' => (string) ($row['collation'] ?? 'unknown'),
            ];
        } catch (Throwable $e) {
            error_log('[PromaPay][backup_manifest] Database metadata unavailable (' . get_class($e) . ').');
            return ['engine' => 'unknown', 'version' => 'unknown', 'charset' => 'unknown', 'collation' => 'unknown'];
        }
    }

    protected static function schemaVersionFromDump($sql)
    {
        $definitions = [];
        foreach (self::splitSqlStatements($sql) as $statement) {
            if (preg_match('/^\s*CREATE\s+TABLE\b/i', $statement)) {
                $definitions[] = trim($statement);
            }
        }
        if (!$definitions) return 'untracked';
        sort($definitions, SORT_STRING);
        return 'sha256:' . hash('sha256', implode("\n", $definitions));
    }

    /** Add a versioned integrity manifest after the archive entries are closed. */
    protected static function appendArchiveManifest($path, array $metadata)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('ZIP بکاپ برای ثبت manifest دوباره باز نشد.');
        }
        $closed = false;
        try {
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($name === '' || substr($name, -1) === '/') continue;
                if (in_array($name, ['manifest.json', 'checksums.json'], true)) {
                    throw new RuntimeException('فایل رزروشدهٔ manifest از قبل در ZIP وجود دارد.');
                }
                $entry = self::hashZipEntry($zip, $name);
                $files[$name] = $entry;
                if (count($files) > 100000) throw new RuntimeException('تعداد فایل‌های بکاپ بیش از حد مجاز است.');
            }
            ksort($files, SORT_STRING);
            $checksumsJson = json_encode(['files' => $files], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($checksumsJson) > 16777216) throw new RuntimeException('فهرست checksumهای بکاپ بیش از حد مجاز است.');
            $checksumsHash = hash('sha256', $checksumsJson);
            $manifest = array_merge([
                'format' => 'proma-pay-backup',
                'backup_format_version' => 1,
                'app' => 'Proma Pay',
                'files_manifest' => [
                    'path' => 'checksums.json',
                    'entry_count' => count($files),
                    'sha256' => $checksumsHash,
                ],
                'checksum' => ['algorithm' => 'sha256', 'file' => 'checksums.json', 'value' => $checksumsHash],
            ], $metadata);
            $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (!$zip->addFromString('checksums.json', $checksumsJson)
                || !$zip->addFromString('manifest.json', $manifestJson)) {
                throw new RuntimeException('ثبت manifest و checksum در ZIP بکاپ انجام نشد.');
            }
            if (!$zip->close()) throw new RuntimeException('بستن ZIP پس از افزودن manifest انجام نشد.');
            $closed = true;
        } finally {
            if (!$closed) @$zip->close();
        }
    }

    protected static function validateArchiveManifest(ZipArchive $zip)
    {
        $manifestStat = $zip->statName('manifest.json');
        $checksumsStat = $zip->statName('checksums.json');
        if (!$manifestStat && !$checksumsStat) return false; // Legacy V4 archive.
        if (!$manifestStat || !$checksumsStat) throw new RuntimeException('manifest و checksums باید هر دو داخل بکاپ نسخه‌دار باشند.');
        if ((int) $manifestStat['size'] > 1048576 || (int) $checksumsStat['size'] > 16777216) {
            throw new RuntimeException('فایل‌های manifest بکاپ بیش از حد مجاز هستند.');
        }
        $manifestJson = $zip->getFromName('manifest.json');
        $checksumsJson = $zip->getFromName('checksums.json');
        if (!is_string($manifestJson) || !is_string($checksumsJson)) throw new RuntimeException('خواندن manifest بکاپ ناموفق بود.');
        try {
            $manifest = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
            $checksums = json_decode($checksumsJson, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('ساختار JSON مربوط به manifest یا checksum معتبر نیست.', 0, $e);
        }
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'proma-pay-backup'
            || (int) ($manifest['backup_format_version'] ?? 0) !== 1 || ($manifest['app'] ?? '') !== 'Proma Pay') {
            throw new RuntimeException('نسخه یا هویت manifest بکاپ پشتیبانی نمی‌شود.');
        }
        $fileManifest = $manifest['files_manifest'] ?? null;
        $checksum = $manifest['checksum'] ?? null;
        if (!is_array($fileManifest) || ($fileManifest['path'] ?? '') !== 'checksums.json'
            || !is_array($checksum) || ($checksum['algorithm'] ?? '') !== 'sha256'
            || ($checksum['file'] ?? '') !== 'checksums.json' || !isset($checksum['value'])
            || !hash_equals((string) $checksum['value'], hash('sha256', $checksumsJson))
            || !hash_equals((string) ($fileManifest['sha256'] ?? ''), hash('sha256', $checksumsJson))) {
            throw new RuntimeException('checksum فایل manifest با checksums بکاپ هم‌خوانی ندارد.');
        }
        $files = $checksums['files'] ?? null;
        if (!is_array($files) || count($files) > 100000 || count($files) !== (int) ($fileManifest['entry_count'] ?? -1)) {
            throw new RuntimeException('فهرست فایل‌های checksum در بکاپ کامل یا معتبر نیست.');
        }
        $actualNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || substr($name, -1) === '/' || in_array($name, ['manifest.json', 'checksums.json'], true)) continue;
            $actualNames[] = $name;
        }
        sort($actualNames, SORT_STRING);
        $expectedNames = array_keys($files);
        sort($expectedNames, SORT_STRING);
        if ($actualNames !== $expectedNames) throw new RuntimeException('فهرست فایل‌های ZIP با manifest بکاپ متفاوت است.');
        foreach ($files as $name => $expected) {
            if (!is_string($name) || !is_array($expected) || !isset($expected['size'], $expected['sha256'])
                || !preg_match('/\A[a-f0-9]{64}\z/i', (string) $expected['sha256'])) {
                throw new RuntimeException('یک رکورد checksum بکاپ ساختار معتبری ندارد.');
            }
            $actual = self::hashZipEntry($zip, $name);
            if ($actual['size'] !== (int) $expected['size']
                || !hash_equals(strtolower((string) $expected['sha256']), $actual['sha256'])) {
                throw new RuntimeException('checksum یا اندازهٔ فایل داخل ZIP با manifest هم‌خوانی ندارد: ' . basename($name));
            }
        }
        return true;
    }

    protected static function hashZipEntry(ZipArchive $zip, $name)
    {
        $stream = $zip->getStream((string) $name);
        if (!$stream) throw new RuntimeException('خواندن فایل از ZIP برای checksum انجام نشد: ' . basename((string) $name));
        $context = hash_init('sha256');
        $size = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) throw new RuntimeException('خواندن فایل از ZIP هنگام محاسبه checksum ناقص ماند.');
                if ($chunk === '') break;
                $size += strlen($chunk);
                hash_update($context, $chunk);
            }
        } finally {
            fclose($stream);
        }
        return ['size' => $size, 'sha256' => hash_final($context)];
    }

    protected static function restoreZipBackup($path, $label = '')
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل zip بکاپ قابل خواندن نیست.');
        }
        try {
            self::validateZip($zip);
            self::validateArchiveManifest($zip);
            $database = self::databaseDumpFromZip($zip);
            try {
            self::validateDatabaseDumpFile($database['path']);
            return self::restoreWithRecoveryFile($database['path'], basename((string) $label) ?: basename($path), function () use ($zip, $path, $database) {
                foreach (self::backupRootPrefixes($zip, $database['entry']) as $prefix) {
                    self::restoreConfigSnapshot($zip, $prefix . 'config-snapshot.json');
                    self::restoreFiles($zip, $path, $prefix . 'uploads/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
                    $skips = [];
                    foreach (['backups', 'cache', 'logs', 'sessions', 'updates', 'releases', '.backup-fallback', '.runtime-lock', '.restore-work'] as $directory) $skips[] = $prefix . 'storage/' . $directory . '/';
                    self::restoreFiles($zip, $path, $prefix . 'storage/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage', $skips);
                }
            });
            } finally { @unlink($database['path']); }
        } finally {
            $zip->close();
        }
    }

    protected static function restoreWithRecovery($sql, $label, ?callable $restoreFiles = null)
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!$stream) throw new RuntimeException('ساخت جریان موقت برای بازیابی SQL انجام نشد.');
        try {
            self::writeStreamFully($stream, (string) $sql);
            rewind($stream);
            return self::restoreWithRecoveryStream($stream, $label, $restoreFiles);
        } finally { fclose($stream); }
    }

    protected static function restoreWithRecoveryFile($sqlPath, $label, ?callable $restoreFiles = null)
    {
        $stream = @fopen($sqlPath, 'rb');
        if (!$stream) throw new RuntimeException('بازکردن فایل SQL برای بازیابی انجام نشد.');
        try { return self::restoreWithRecoveryStream($stream, $label, $restoreFiles); }
        finally { fclose($stream); }
    }

    protected static function restoreWithRecoveryStream($sqlStream, $label, ?callable $restoreFiles = null)
    {
        if (Model::db()->inTransaction()) throw new RuntimeException('بازیابی داخل تراکنش فعال مجاز نیست.');
        RequestMaintenanceLock::exclusive();
        $before = null;
        $journalDirectory = null;
        $safeToResume = true;
        try {
            @set_time_limit(0);
            $before = self::create('before-restore');
            $journalDirectory = dirname(__DIR__) . '/storage/.restore-work/' . bin2hex(random_bytes(12));
            if (!self::ensurePrivateDirectory($journalDirectory)) throw new RuntimeException('ساخت محل ایمنی فایل‌های بازیابی انجام نشد.');
            self::$restoreJournal = ['directory' => $journalDirectory, 'files' => []];
            RequestMaintenanceLock::markPending($before['name'], $journalDirectory);
            $safeToResume = false;
            self::replaceDatabaseFromStream($sqlStream);
            $imported = RestoreIntegrityService::capture();
            RestoreSchemaService::repair(static function ($migrationSql) { self::runSql($migrationSql); });
            RestoreIntegrityService::assertPreserved($imported);
            if ($restoreFiles) $restoreFiles();
            $safeToResume = true;
            self::log('restore', $label, 'success', 'restored and schema repaired; safety backup: ' . $before['name']);
            return $before['name'];
        } catch (Throwable $error) {
            if ($before && self::$restoreJournal !== null) {
                try {
                    $safetySql = self::readSafetySql($before);
                    try {
                        // MySQL DDL commits implicitly; replay the safety dump.
                        self::replaceDatabaseFromFile($safetySql['path']);
                    } finally { if ($safetySql['temporary']) @unlink($safetySql['path']); }
                    foreach (array_reverse(self::$restoreJournal['files'], true) as $destination => $saved) {
                        if ($saved === null) {
                            if (is_file($destination) && !unlink($destination)) throw new RuntimeException('حذف فایل جدید بازیابی انجام نشد.');
                        } elseif (!copy($saved, $destination)) throw new RuntimeException('بازگردانی فایل ایمنی انجام نشد.');
                    }
                    SchemaGuard::clearCache();
                    $safeToResume = true;
                } catch (Throwable $recoveryError) {
                    throw new RuntimeException('بازیابی و بازگشت خودکار کامل نشد. بکاپ ایمنی را نگه دارید: ' . $before['name'] . '. علت: ' . $recoveryError->getMessage(), 0, $error);
                }
                throw new RuntimeException('بازیابی انجام نشد؛ اطلاعات و فایل‌های قبلی از بکاپ ایمنی بازگردانده شدند. بکاپ ایمنی: ' . $before['name'] . '. علت: ' . $error->getMessage(), 0, $error);
            }
            throw $error;
        } finally {
            self::$restoreJournal = null;
            if ($safeToResume && $journalDirectory && is_dir($journalDirectory)) {
                foreach (glob($journalDirectory . '/*') ?: [] as $file) if (is_file($file)) @unlink($file);
                @rmdir($journalDirectory);
            }
            SchemaGuard::clearCache();
            if ($safeToResume) RequestMaintenanceLock::clearPending();
            RequestMaintenanceLock::shared();
        }
    }

    protected static function readSafetySql(array $backup)
    {
        if (($backup['type'] ?? '') === 'sql') {
            self::validateDatabaseDumpFile($backup['path']);
            return ['path' => $backup['path'], 'temporary' => false];
        }
        $zip = new ZipArchive();
        if ($zip->open($backup['path']) !== true) throw new RuntimeException('بکاپ ایمنی قابل خواندن نیست.');
        try {
            self::validateZip($zip);
            self::validateArchiveManifest($zip);
            $database = self::databaseDumpFromZip($zip);
            try {
                self::validateDatabaseDumpFile($database['path']);
                return ['path' => $database['path'], 'temporary' => true];
            } catch (Throwable $e) {
                @unlink($database['path']);
                throw $e;
            }
        }
        finally { $zip->close(); }
    }

    protected static function restoreConfigSnapshot(ZipArchive $zip, $entry)
    {
        $stat = $zip->statName($entry);
        if (!$stat) return;
        if ($stat['size'] > 2 * 1024 * 1024) throw new RuntimeException('snapshot تنظیمات بیش از حد مجاز است.');
        $snapshot = json_decode($zip->getFromName($entry), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) throw new RuntimeException('snapshot تنظیمات معتبر نیست.');
        // Older backups include defaults that never had a row in settings.
        // Preserve those values while giving explicit database rows precedence.
        foreach ($snapshot as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) throw new RuntimeException('نوع پارامتر snapshot پشتیبانی نمی‌شود.');
            Model::execute('INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)', [$key, (string) $value, preg_match('/password|secret|token|api_key|merchant/i', $key) ? 1 : 0]);
        }
    }

    protected static function replaceDatabase($sql)
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!$stream) throw new RuntimeException('ساخت جریان موقت برای جایگزینی دیتابیس انجام نشد.');
        try {
            self::writeStreamFully($stream, (string) $sql);
            rewind($stream);
            self::replaceDatabaseFromStream($stream);
        } finally { fclose($stream); }
    }

    protected static function replaceDatabaseFromFile($path)
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) throw new RuntimeException('بازکردن dump برای جایگزینی دیتابیس انجام نشد.');
        try { self::replaceDatabaseFromStream($stream); }
        finally { fclose($stream); }
    }

    protected static function replaceDatabaseFromStream($stream)
    {
        $included = [];
        if (fseek($stream, 0) !== 0) throw new RuntimeException('بازگشت به ابتدای dump برای بازیابی انجام نشد.');
        // Parse statement boundaries so SQL-looking customer text cannot declare a table.
        foreach (self::streamSqlStatements($stream) as $statement) {
            if (preg_match('/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-z0-9_$-]+)`?\s*\(/i', $statement, $match)) $included[$match[1]] = true;
        }
        if (fseek($stream, 0) !== 0) throw new RuntimeException('بازگشت به ابتدای dump برای import انجام نشد.');
        $pdo = Model::db();
        $fk = (int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Newer tables absent from the historical snapshot must not retain
            // ledger rows referencing IDs that have just been restored.
            foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!isset($included[$table])) $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
            }
            self::runSqlStream($stream);
        } finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=' . $fk); }
    }

    protected static function detectBackupFormat($path, $originalName = '')
    {
        $header = '';
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $header = (string) fread($handle, 2048);
            fclose($handle);
        }
        if ($header === '') {
            return null;
        }
        if (substr($header, 0, 2) === 'PK') {
            return 'zip';
        }
        if (preg_match('/SET\\s+FOREIGN_KEY_CHECKS|CREATE\\s+TABLE|INSERT\\s+INTO/i', preg_replace('/^\\xEF\\xBB\\xBF/', '', $header))) {
            return 'sql';
        }
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($extension === 'sql') {
            return 'sql';
        }
        if ($extension === 'zip' && class_exists('ZipArchive')) {
            return 'zip';
        }
        return null;
    }

    public static function findBackup($fileName)
    {
        $safe = basename((string) $fileName);
        if (!preg_match('/^proma-pay-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}(?:-[a-z0-9_-]+)?\.(zip|sql)$/i', $safe)) {
            return null;
        }
        foreach (self::backupDirectories() as $directory) {
            $path = $directory . DIRECTORY_SEPARATOR . $safe;
            if (is_file($path) && !is_link($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function delete($fileName)
    {
        $path = self::findBackup($fileName);
        if (!$path) {
            throw new RuntimeException('فایل بکاپ پیدا نشد.');
        }
        $name = basename($path);
        if (!unlink($path)) {
            throw new RuntimeException('حذف فایل بکاپ انجام نشد.');
        }
        self::log('backup_delete', $name, 'success', 'backup file deleted');
        return $name;
    }

    protected static function databaseDump()
    {
        $path = @tempnam(sys_get_temp_dir(), 'proma-pay-dump-test-');
        if ($path === false) throw new RuntimeException('ساخت فایل موقت dump پایگاه‌داده انجام نشد.');
        try {
            self::databaseDumpToFile($path);
            $sql = @file_get_contents($path);
            if (!is_string($sql)) throw new RuntimeException('خواندن dump پایگاه‌داده انجام نشد.');
            return $sql;
        } finally {
            @unlink($path);
        }
    }

    /** Stream a consistent database snapshot to disk without buffering table rows. */
    protected static function databaseDumpToFile($path, $header = '')
    {
        $pdo = Model::db();
        if ($pdo->inTransaction()) throw new RuntimeException('ساخت بکاپ داخل تراکنش فعال مجاز نیست.');
        $nonTransactional = Model::fetch("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'");
        $lockedTables = false;
        $output = @fopen($path, 'wb');
        if (!$output) throw new RuntimeException('بازکردن فایل موقت dump برای نوشتن انجام نشد.');
        $bufferedAttribute = defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')
            ? constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')
            : (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') ? constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') : null);
        $originalBuffered = null;
        $bufferedChanged = false;
        $definitions = [];
        $bytes = 0;
        try {
        if ($header !== '') self::writeStreamFully($output, (string) $header);
        if ($bufferedAttribute !== null) {
            try {
                $originalBuffered = $pdo->getAttribute($bufferedAttribute);
                $pdo->setAttribute($bufferedAttribute, false);
                $bufferedChanged = true;
            } catch (Throwable $e) {
                throw new RuntimeException('فعال‌کردن خواندن ردیف‌های پایگاه‌داده به‌صورت جریان‌محور انجام نشد.', 0, $e);
            }
        }
        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        try {
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        } finally {
            $tablesStmt->closeCursor();
        }
        if ((int) ($nonTransactional['total'] ?? 0) > 0 && $tables) {
            // Legacy dumps contain MyISAM tables: MVCC cannot snapshot them.
            // Freeze all tables together until their rows have been dumped.
            $pdo->exec('LOCK TABLES ' . implode(', ', array_map(static function ($table) { return '`' . str_replace('`', '``', $table) . '` READ'; }, $tables)));
            $lockedTables = true;
        } else {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }
        self::writeStreamFully($output, "SET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', (string) $table) . '`';
            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
            try {
                $create = $createStmt->fetch(PDO::FETCH_ASSOC);
            } finally {
                $createStmt->closeCursor();
            }
            $createSql = is_array($create) ? ($create['Create Table'] ?? (array_values($create)[1] ?? null)) : null;
            if (!is_string($createSql) || $createSql === '') throw new RuntimeException('خواندن تعریف جدول برای بکاپ انجام نشد: ' . (string) $table);
            $definitions[] = trim($createSql);
            self::writeStreamFully($output, "\nDROP TABLE IF EXISTS {$quotedTable};\n" . $createSql . ";\n");
            $rowsStmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            try {
            while (($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns = array_map(function ($column) {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, array_keys($row));
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) return 'NULL';
                    $quoted = $pdo->quote((string) $value);
                    if (!is_string($quoted)) throw new RuntimeException('مقدار یک ردیف برای بکاپ قابل quote نیست.');
                    return $quoted;
                }, array_values($row));
                $insert = 'INSERT INTO ' . $quotedTable . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
                if (strlen($insert) > self::maximumSqlStatementBytes()) {
                    throw new RuntimeException('یک ردیف پایگاه‌داده از سقف امن دستور SQL عبور کرد: ' . (string) $table);
                }
                self::writeStreamFully($output, $insert);
            }
            } finally {
                $rowsStmt->closeCursor();
            }
        }
        self::writeStreamFully($output, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        if (!fflush($output)) throw new RuntimeException('تکمیل نوشتن dump پایگاه‌داده انجام نشد.');
        $stat = fstat($output);
        $bytes = (int) ($stat['size'] ?? 0);
        sort($definitions, SORT_STRING);
        $schemaVersion = $definitions ? 'sha256:' . hash('sha256', implode("\n", $definitions)) : 'untracked';
        return ['bytes' => $bytes, 'schema_version' => $schemaVersion];
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($lockedTables) $pdo->exec('UNLOCK TABLES');
            if ($bufferedChanged) {
                try { $pdo->setAttribute($bufferedAttribute, $originalBuffered); }
                catch (Throwable $e) { error_log('[PromaPay][backup] Could not restore PDO buffered-query setting (' . get_class($e) . ').'); }
            }
            fclose($output);
        }
    }

    protected static function writeStreamFully($stream, $data)
    {
        $offset = 0;
        $length = strlen((string) $data);
        while ($offset < $length) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('نوشتن کامل فایل dump پایگاه‌داده انجام نشد؛ فضای دیسک و مجوزها را بررسی کنید.');
            $offset += $written;
        }
    }

    protected static function addDirectory(ZipArchive $zip, $path, $prefix, array $excludedRealPaths = [])
    {
        if (!$zip->addEmptyDir($prefix) && $zip->locateName(rtrim($prefix, '/') . '/') === false) {
            return false;
        }
        if (!is_dir($path)) {
            return true;
        }
        $base = realpath($path);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $real = $file->getRealPath();
            foreach ($excludedRealPaths as $excluded) {
                if ($excluded && strpos($real, $excluded) === 0) {
                    continue 2;
                }
            }
            if (!$file->isFile()) {
                continue;
            }
            $local = $prefix . '/' . str_replace('\\', '/', substr($real, strlen($base) + 1));
            if (!$zip->addFile($real, $local)) {
                return false;
            }
        }
        return true;
    }

    protected static function validateZip(ZipArchive $zip)
    {
        $hasSql = false;
        $names = [];
        $expandedBytes = 0;
        if ($zip->numFiles > 100000) throw new RuntimeException('تعداد فایل‌های ZIP بکاپ بیش از حد مجاز است.');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            if ($normalized !== $name || preg_match('#(^|/)\\.\\.(?:/|$)#', $normalized) || preg_match('/^[a-z]:/i', $normalized) || substr($normalized, 0, 1) === '/') {
                throw new RuntimeException('ساختار فایل zip امن نیست.');
            }
            if (isset($names[$name])) throw new RuntimeException('ZIP بکاپ شامل نام فایل تکراری است.');
            $names[$name] = true;
            $stat = $zip->statIndex($i);
            if (!$stat || (int) ($stat['size'] ?? -1) < 0) throw new RuntimeException('اندازه یک فایل داخل ZIP قابل خواندن نیست.');
            $entryBytes = (int) $stat['size'];
            if ($entryBytes > self::RESTORE_MAX_EXPANDED_BYTES - $expandedBytes) {
                throw new RuntimeException('حجم بازشده ZIP از سقف محافظت در برابر فایل فشرده مخرب یا بیش‌ازحد بزرگ عبور می‌کند.');
            }
            $expandedBytes += $entryBytes;
            if (strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) === 'sql') {
                if ((int) $stat['size'] > self::RESTORE_MAX_SQL_BYTES) throw new RuntimeException(self::restoreSqlSizeMessage((int) $stat['size'], self::RESTORE_MAX_SQL_BYTES));
            }
            $hasSql = $hasSql || strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) === 'sql';
        }
        if (!$hasSql) {
            throw new RuntimeException('فایل SQL داخل بکاپ پیدا نشد.');
        }
    }

    protected static function databaseDumpFromZip(ZipArchive $zip)
    {
        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
                continue;
            }
            $base = strtolower(basename($name));
            $priority = $base === 'database.sql' ? 0 : (in_array($base, ['backup.sql', 'dump.sql'], true) ? 1 : 2);
            $candidates[] = ['entry' => $name, 'priority' => $priority];
        }
        usort($candidates, static function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($candidates as $candidate) {
            $stat = $zip->statName($candidate['entry']);
            if (!$stat || !isset($stat['size'])) continue;
            if ((int) $stat['size'] > self::RESTORE_MAX_SQL_BYTES) {
                throw new RuntimeException(self::restoreSqlSizeMessage((int) $stat['size'], self::RESTORE_MAX_SQL_BYTES));
            }
            $temporary = @tempnam(sys_get_temp_dir(), 'proma-restore-sql-');
            if ($temporary === false) throw new RuntimeException('ساخت فایل موقت امن برای استخراج SQL انجام نشد.');
            try {
                self::copyZipEntryToFile($zip, $candidate['entry'], $temporary, self::RESTORE_MAX_SQL_BYTES);
                if (filesize($temporary) > 0 && self::isPromaPayDatabaseDumpFile($temporary)) {
                    return ['entry' => $candidate['entry'], 'path' => $temporary];
                }
            } catch (Throwable $e) {
                @unlink($temporary);
                throw $e;
            }
            @unlink($temporary);
        }
        throw new RuntimeException('فایل ZIP بکاپ معتبر پروما پی ندارد؛ فایل SQL اصلی پیدا یا شناسایی نشد.');
    }

    protected static function copyZipEntryToFile(ZipArchive $zip, $entry, $path, $maximumBytes)
    {
        $input = $zip->getStream((string) $entry);
        if (!$input) throw new RuntimeException('بازکردن فایل SQL از ZIP برای خواندن انجام نشد.');
        $output = @fopen($path, 'wb');
        if (!$output) { fclose($input); throw new RuntimeException('بازکردن فایل موقت SQL برای نوشتن انجام نشد.'); }
        $total = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1048576);
                if ($chunk === false || ($chunk === '' && !feof($input))) throw new RuntimeException('استخراج SQL از ZIP ناقص ماند.');
                if ($chunk === '') break;
                if (strlen($chunk) > $maximumBytes - $total) throw new RuntimeException('اندازه SQL استخراج‌شده از سقف مجاز عبور کرد.');
                $total += strlen($chunk);
                self::writeStreamFully($output, $chunk);
            }
            if (!fflush($output)) throw new RuntimeException('تکمیل استخراج SQL از ZIP انجام نشد.');
        } finally {
            fclose($input);
            fclose($output);
        }
        return $total;
    }

    protected static function backupRootPrefixes(ZipArchive $zip, $sqlEntry)
    {
        $prefixes = [''];
        $directory = trim(str_replace('\\', '/', dirname((string) $sqlEntry)), '. /');
        if ($directory === '') {
            return $prefixes;
        }
        $nestedPrefix = $directory . '/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (strpos($name, $nestedPrefix . 'uploads/') === 0 || strpos($name, $nestedPrefix . 'storage/') === 0) {
                $prefixes[] = $nestedPrefix;
                break;
            }
        }
        return $prefixes;
    }

    protected static function validateDatabaseDump($sql)
    {
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('محتوای SQL به‌عنوان بکاپ پایگاه داده پروما پی شناسایی نشد.');
        }
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!$stream) throw new RuntimeException('ساخت جریان موقت برای اعتبارسنجی SQL انجام نشد.');
        try {
            self::writeStreamFully($stream, $sql);
            rewind($stream);
            self::validateDatabaseDumpStream($stream);
        } finally { fclose($stream); }
    }

    protected static function validateDatabaseDumpFile($path)
    {
        $size = @filesize($path);
        if ($size === false || $size < 1) throw new RuntimeException('فایل SQL بکاپ خالی یا غیرقابل‌خواندن است.');
        if ($size > self::RESTORE_MAX_SQL_BYTES) throw new RuntimeException(self::restoreSqlSizeMessage($size, self::RESTORE_MAX_SQL_BYTES));
        $stream = @fopen($path, 'rb');
        if (!$stream) throw new RuntimeException('بازکردن فایل SQL برای اعتبارسنجی انجام نشد.');
        try { self::validateDatabaseDumpStream($stream); }
        finally { fclose($stream); }
    }

    protected static function validateDatabaseDumpStream($stream)
    {
        $required = ['users' => false, 'contracts' => false, 'installments' => false];
        $signature = false;
        $statements = 0;
        foreach (self::streamSqlStatements($stream) as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            $statements++;
            if (!preg_match('/^\s*(?:SET\b|CREATE\s+TABLE\b|DROP\s+TABLE\b|ALTER\s+TABLE\b|INSERT\s+INTO\b|LOCK\s+TABLES\b|UNLOCK\s+TABLES\b)/i', $statement)) {
                throw new RuntimeException('نوع دستور SQL در بکاپ پشتیبانی نمی‌شود.');
            }
            if (preg_match('/^\s*(?:USE\b|(?:CREATE|DROP|ALTER)\s+DATABASE\b|(?:GRANT|REVOKE|CALL|DELIMITER)\b|SET\s+(?:@@)?GLOBAL\b)/i', $statement)
                || preg_match('/^\s*(?:INSERT\s+INTO|LOCK\s+TABLES|(?:CREATE|DROP|ALTER)\s+TABLE(?:\s+IF\s+(?:NOT\s+)?EXISTS)?)\s+`?[a-z0-9_$-]+`?\s*\./i', $statement)) {
                throw new RuntimeException('بکاپ شامل دستور خارج از پایگاه داده جاری است.');
            }
            if (preg_match('/^\s*(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO)\s+(?:`?[a-z0-9_$-]+`?\.)?`?(users|contracts|installments)`?\s*(?:\(|$)/i', $statement, $match)) {
                $signature = true;
                if (preg_match('/^\s*CREATE\s+TABLE/i', $statement)) $required[strtolower($match[1])] = true;
            }
        }
        if (!$signature || $statements === 0) throw new RuntimeException('محتوای SQL به‌عنوان بکاپ پایگاه داده پروما پی شناسایی نشد.');
        foreach ($required as $table => $found) {
            if (!$found) throw new RuntimeException('بکاپ کامل نیست؛ ساختار جدول اصلی موجود نیست: ' . $table);
        }
    }

    protected static function isPromaPayDatabaseDumpFile($path)
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return false;
        try {
            foreach (self::streamSqlStatements($stream) as $statement) {
                if (preg_match('/^\s*(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO)\s+(?:`?[a-z0-9_$-]+`?\.)?`?(?:users|contracts|installments)`?\s*(?:\(|$)/i', $statement)) return true;
            }
            return false;
        } catch (Throwable $e) { return false; }
        finally { fclose($stream); }
    }

    protected static function isPromaPayDatabaseDump($sql)
    {
        return (bool) preg_match(
            '/\\b(?:CREATE\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?|INSERT\\s+INTO)\\s+(?:`?[a-z0-9_$-]+`?\\.)?`?(?:users|contracts|installments)`?\\s*(?:\\(|$)/i',
            preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $sql)
        );
    }

    protected static function restoreFiles(ZipArchive $zip, $zipPath, $prefix, $target, array $skipPrefixes = [])
    {
        $targetReal = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if (strpos($name, $prefix) !== 0 || substr($name, -1) === '/') {
                continue;
            }
            foreach ($skipPrefixes as $skipPrefix) {
                if (strpos($name, $skipPrefix) === 0) {
                    continue 2;
                }
            }
            $relative = substr($name, strlen($prefix));
            if ($relative === '' || preg_match('#(^|/)\.{1,2}(?:/|$)|[\x00-\x1f]|^[a-z]:#i', $relative) || $relative[0] === '/') {
                throw new RuntimeException('مسیر نسبی فایل بکاپ معتبر نیست.');
            }
            // Empty/absent legacy uploads must not require creating a directory
            // in a read-only application root. Create roots only for actual files.
            if ($targetReal === null) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new RuntimeException('ساخت پوشه بازیابی انجام نشد: ' . basename($target) . '؛ مجوز نوشتن و فضای دیسک را بررسی کنید.');
                }
                clearstatcache(true, $target);
                $targetReal = realpath($target);
                if ($targetReal === false) throw new RuntimeException('خواندن مسیر پوشه بازیابی انجام نشد: ' . basename($target));
            }
            $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($destination);
            FilesystemPathGuard::requireParent($targetReal, $dir, $relative);
            if (is_link($destination)) throw new RuntimeException('فایل مقصد یک پیوند است و قابل بازنویسی نیست: ' . $relative);
            if (self::$restoreJournal !== null && !array_key_exists($destination, self::$restoreJournal['files'])) {
                $saved = null;
                if (is_file($destination)) {
                    $saved = self::$restoreJournal['directory'] . '/' . count(self::$restoreJournal['files']);
                    if (!copy($destination, $saved)) throw new RuntimeException('ذخیره فایل ایمنی پیش از بازیابی انجام نشد.');
                }
                self::$restoreJournal['files'][$destination] = $saved;
                if (file_put_contents(self::$restoreJournal['directory'] . '/journal.json', json_encode(self::$restoreJournal['files'], JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
                    throw new RuntimeException('ثبت journal فایل‌های بازیابی انجام نشد.');
                }
            }
            $stream = $zip->getStream($name);
            if (!$stream) throw new RuntimeException('خواندن فایل «' . $relative . '» از ZIP بکاپ انجام نشد.');
            $destinationStream = @fopen($destination, 'wb');
            if (!$destinationStream) {
                fclose($stream);
                throw new RuntimeException('بازیابی فایل «' . $relative . '» از بکاپ انجام نشد؛ مجوز نوشتن و فضای دیسک را بررسی کنید.');
            }
            $hash = hash_init('sha256');
            $size = 0;
            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1048576);
                    if ($chunk === false || ($chunk === '' && !feof($stream))) {
                        throw new RuntimeException('خواندن فایل «' . $relative . '» از ZIP بکاپ ناقص ماند.');
                    }
                    if ($chunk === '') break;
                    hash_update($hash, $chunk);
                    $size += strlen($chunk);
                    $offset = 0;
                    $length = strlen($chunk);
                    while ($offset < $length) {
                        $written = fwrite($destinationStream, substr($chunk, $offset));
                        if ($written === false || $written === 0) {
                            throw new RuntimeException('بازیابی فایل «' . $relative . '» از بکاپ انجام نشد؛ مجوز نوشتن و فضای دیسک را بررسی کنید.');
                        }
                        $offset += $written;
                    }
                }
                if (!fflush($destinationStream)) {
                    throw new RuntimeException('تکمیل نوشتن فایل بازیابی انجام نشد: ' . $relative);
                }
            } finally {
                fclose($stream);
                fclose($destinationStream);
            }
            $expectedHash = hash_final($hash);
            $actualHash = @hash_file('sha256', $destination);
            $archiveStat = $zip->statName($name);
            if ($actualHash === false || !hash_equals($expectedHash, $actualHash) || (int) filesize($destination) !== $size
                || !$archiveStat || (int) ($archiveStat['size'] ?? -1) !== $size) {
                throw new RuntimeException('محتوای فایل بازیابی با بکاپ یکسان نیست: ' . $relative);
            }
        }
    }

    protected static function runSql($sql)
    {
        $sql = trim((string) $sql);
        if ($sql === '') {
            throw new RuntimeException('database.sql خالی است.');
        }
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (!$stream) throw new RuntimeException('ساخت جریان موقت برای اجرای SQL انجام نشد.');
        try {
            self::writeStreamFully($stream, $sql);
            rewind($stream);
            self::runSqlStream($stream);
        } finally { fclose($stream); }
    }

    protected static function runSqlStream($stream)
    {
        if (fseek($stream, 0) !== 0) throw new RuntimeException('بازگشت به ابتدای فایل SQL برای اجرا انجام نشد.');
        $pdo = Model::db();
        $session = $pdo->query('SELECT @@SESSION.foreign_key_checks AS fk, @@SESSION.sql_mode AS mode')->fetch(PDO::FETCH_ASSOC);
        $pdo->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        // mysqldump often places this setting in a versioned comment, which
        // the parser strips. Import table definitions in any dump order.
        $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');
        try {
        foreach (self::streamSqlStatements($stream) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            // Group dump INSERTs into transactions instead of flushing every
            // individual row to disk. DDL still commits implicitly.
            if (preg_match('/^INSERT\s+INTO\b/i', $statement) && !$pdo->inTransaction()) $pdo->beginTransaction();
            $result = $pdo->query($statement);
            if (!$result instanceof PDOStatement) {
                continue;
            }
            try {
                do {
                    if ($result->columnCount() > 0) {
                        $result->fetchAll(PDO::FETCH_ASSOC);
                    }
                    try {
                        $hasMoreRows = $result->nextRowset();
                    } catch (Throwable $e) {
                        $hasMoreRows = false;
                    }
                } while ($hasMoreRows);
            } finally {
                $result->closeCursor();
            }
        }
        if ($pdo->inTransaction()) $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = ' . (int) $session['fk']);
            $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($session['mode']));
            SchemaGuard::clearCache();
        }
    }

    protected static function splitSqlStatements($sql)
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', (string) $sql);
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            $afterNext = $i + 2 < $length ? $sql[$i + 2] : '';

            if ($quote !== null) {
                $buffer .= $char;
                if ($quote !== '`' && $char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === '-' && $next === '-' && ($afterNext === '' || preg_match('/\s/', $afterNext))) {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $i += 2;
                while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                $buffer .= ' ';
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    yield $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            yield $tail;
        }
    }

    /** Parse SQL from a stream with bounded memory and MySQL dump comment/quote rules. */
    protected static function streamSqlStatements($stream, $maximumStatementBytes = null)
    {
        $maximumStatementBytes = max(1024, (int) ($maximumStatementBytes ?? self::maximumSqlStatementBytes()));
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $firstChunk = true;
        $carry = '';
        $append = static function ($text) use (&$buffer, $maximumStatementBytes) {
            $length = strlen($text);
            if ($length > $maximumStatementBytes - strlen($buffer)) {
                throw new RuntimeException('یک دستور SQL از سقف امن ' . self::formatRestoreBytes($maximumStatementBytes) . ' عبور کرد.');
            }
            $buffer .= $text;
        };

        while (true) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) throw new RuntimeException('خواندن فایل SQL جریان‌محور ناموفق بود.');
            $eof = feof($stream);
            if ($chunk === '' && !$eof) throw new RuntimeException('خواندن فایل SQL متوقف شد، اما انتهای فایل نرسیده است.');
            if ($firstChunk) {
                if (substr($chunk, 0, 3) === "\xEF\xBB\xBF") $chunk = substr($chunk, 3);
                $firstChunk = false;
            }
            $data = $carry . $chunk;
            $length = strlen($data);
            $limit = $eof ? $length : max(0, $length - 2);
            $i = 0;
            while ($i < $limit) {
                if ($lineComment) {
                    $newline = strpos($data, "\n", $i);
                    if ($newline === false || $newline >= $limit) { $i = $limit; continue; }
                    $append("\n");
                    $i = $newline + 1;
                    $lineComment = false;
                    continue;
                }
                if ($blockComment) {
                    $close = strpos($data, '*/', $i);
                    if ($close === false) { $i = $limit; continue; }
                    if ($close + 1 >= $limit) { $i = $close; break; }
                    $append(' ');
                    $i = $close + 2;
                    $blockComment = false;
                    continue;
                }
                if ($quote !== null) {
                    $specials = $quote === '`' ? $quote : ($quote . '\\');
                    $span = strcspn($data, $specials, $i, $limit - $i);
                    if ($span > 0) { $append(substr($data, $i, $span)); $i += $span; if ($i >= $limit) continue; }
                    $char = $data[$i];
                    $next = $i + 1 < $length ? $data[$i + 1] : '';
                    if ($quote !== '`' && $char === '\\' && $next !== '') {
                        $append($char . $next);
                        $i += 2;
                        continue;
                    }
                    if ($char === $quote) {
                        if ($next === $quote) { $append($char . $next); $i += 2; continue; }
                        $append($char);
                        $quote = null;
                        $i++;
                        continue;
                    }
                    $append($char);
                    $i++;
                    continue;
                }

                $span = strcspn($data, "'\"`;#/-", $i, $limit - $i);
                if ($span > 0) { $append(substr($data, $i, $span)); $i += $span; if ($i >= $limit) continue; }
                $char = $data[$i];
                $next = $i + 1 < $length ? $data[$i + 1] : '';
                $afterNext = $i + 2 < $length ? $data[$i + 2] : '';
                if ($char === "'" || $char === '"' || $char === '`') {
                    $quote = $char;
                    $append($char);
                    $i++;
                    continue;
                }
                if ($char === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') yield $statement;
                    $buffer = '';
                    $i++;
                    continue;
                }
                if ($char === '#') { $lineComment = true; $i++; continue; }
                if ($char === '-' && $next === '-' && ($afterNext === '' || preg_match('/\s/', $afterNext))) {
                    $lineComment = true;
                    $i += 2;
                    continue;
                }
                if ($char === '/' && $next === '*') { $blockComment = true; $i += 2; continue; }
                $append($char);
                $i++;
            }
            $carry = substr($data, $i);
            if ($eof) break;
        }

        if ($quote !== null || $blockComment) throw new RuntimeException('فایل SQL شامل رشته یا comment بسته‌نشده است.');
        $tail = trim($buffer . $carry);
        if ($tail !== '') {
            if (strlen($tail) > $maximumStatementBytes) throw new RuntimeException('دستور SQL پایانی از سقف مجاز عبور کرد.');
            yield $tail;
        }
    }
}
