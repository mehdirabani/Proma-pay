<?php

class ScriptUpdateService
{
    public const CONFIRM_TEXT = 'بروزرسانی را تایید می‌کنم';
    protected const MAX_PACKAGE_SIZE = 167772160;

    public static function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
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
                'error' => '',
            ];
            try {
                $manifest = self::readManifest($path, false);
                $info['is_valid'] = true;
                $info['title'] = (string) ($manifest['name'] ?? $manifest['title'] ?? 'بسته بروزرسانی');
                $info['version'] = (string) ($manifest['version'] ?? '');
                $info['files_count'] = count(self::manifestFiles($manifest));
                $info['migrations_count'] = count(self::manifestMigrations($manifest));
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
        if (!$upload || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new RuntimeException('فایل بروزرسانی انتخاب نشده است.');
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('بارگذاری فایل بروزرسانی کامل نشد.');
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

        BackupService::log('update_upload', $name, 'success', 'update package uploaded; full validation deferred to install');
        return $name;
    }

    public static function install($fileName)
    {
        self::relaxRuntimeLimits();
        $path = self::findPackage($fileName);
        if (!$path) {
            throw new RuntimeException('بسته بروزرسانی پیدا نشد.');
        }
        $manifest = self::readManifest($path, true);
        $files = self::manifestFiles($manifest);
        $migrations = self::manifestMigrations($manifest);
        if (!$files && !$migrations) {
            throw new RuntimeException('manifest بسته هیچ فایل یا migration برای نصب معرفی نکرده است.');
        }

        $safety = BackupService::create('before-update');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('بسته بروزرسانی قابل خواندن نیست.');
        }

        $installed = [];
        foreach ($files as $fileSpec) {
            $source = self::safePackagePath($fileSpec['source']);
            $target = self::safeTargetPath($fileSpec['target']);
            $content = self::zipContent($zip, $source);
            if ($content === false) {
                throw new RuntimeException('فایل ' . $source . ' داخل بسته پیدا نشد.');
            }
            if (!empty($fileSpec['sha256']) && !hash_equals(strtolower((string) $fileSpec['sha256']), hash('sha256', $content))) {
                throw new RuntimeException('امضای sha256 فایل ' . $source . ' معتبر نیست.');
            }
            self::writeRootFile($target, $content);
            $installed[] = $target;
        }

        foreach ($migrations as $migration) {
            $migrationPath = self::safePackagePath($migration);
            $sql = self::zipContent($zip, $migrationPath);
            if ($sql === false) {
                throw new RuntimeException('migration معرفی‌شده در بسته پیدا نشد: ' . $migrationPath);
            }
            self::runUpdateMigration($migrationPath, $sql);
        }
        $zip->close();

        BackupService::log(
            'update_install',
            basename($path),
            'success',
            'installed files: ' . count($installed) . '; migrations: ' . count($migrations) . '; safety backup: ' . $safety['name']
        );
        return [
            'backup' => $safety['name'],
            'installed_files' => $installed,
            'migrations_count' => count($migrations),
        ];
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
        if ($validateEntries) {
            self::validateZipEntries($zip);
        }
        $manifestContent = false;
        $manifestName = '';
        foreach (['proma-update.json', 'update-manifest.json', 'manifest.json'] as $candidate) {
            $manifestContent = self::zipContent($zip, $candidate);
            if ($manifestContent !== false) {
                $manifestName = $candidate;
                break;
            }
        }
        if ($manifestContent === false) {
            $manifest = self::buildManifestFromFullPackage($zip, $path);
            $zip->close();
            return $manifest;
        }
        $manifest = json_decode($manifestContent, true);
        if (!is_array($manifest)) {
            $zip->close();
            throw new RuntimeException('manifest بروزرسانی JSON معتبر نیست.');
        }
        if (isset($manifest['app']) && stripos((string) $manifest['app'], 'proma') === false) {
            $zip->close();
            throw new RuntimeException('این بسته برای Proma Pay معتبر نیست.');
        }
        if (!self::manifestFiles($manifest) && !self::manifestMigrations($manifest)) {
            if ($manifestName === 'manifest.json') {
                $manifest = self::buildManifestFromFullPackage($zip, $path);
                $zip->close();
                return $manifest;
            }
            $zip->close();
            throw new RuntimeException('manifest بروزرسانی هیچ فایل یا migration معتبری معرفی نکرده است.');
        }
        $zip->close();
        foreach (self::manifestFiles($manifest) as $file) {
            self::safePackagePath($file['source']);
            self::safeTargetPath($file['target']);
        }
        foreach (self::manifestMigrations($manifest) as $migration) {
            self::safePackagePath($migration);
        }
        return $manifest;
    }

    protected static function quickZipCheck($path)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل zip بروزرسانی قابل خواندن نیست.');
        }
        $zip->close();
    }

    protected static function zipContent(ZipArchive $zip, $path)
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');
        foreach ([$path, './' . $path] as $candidate) {
            $content = $zip->getFromName($candidate);
            if ($content !== false) {
                return $content;
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $entry = preg_replace('#^\./+#', '', trim($entry, '/'));
            if ($entry === $path) {
                return $zip->getFromIndex($i);
            }
        }
        return false;
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

    protected static function validateZipEntries(ZipArchive $zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $trimmed = trim($name, '/');
            if ($trimmed === '' || strpos($trimmed, '../') !== false || strpos($trimmed, '/..') !== false || preg_match('/^[a-z]:/i', $trimmed) || substr($name, 0, 1) === '/') {
                throw new RuntimeException('مسیر داخل بسته بروزرسانی امن نیست.');
            }
        }
    }

    protected static function safePackagePath($path)
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '' || strpos($path, '../') !== false || strpos($path, '/..') !== false || preg_match('/^[a-z]:/i', $path) || substr($path, -1) === '/') {
            throw new RuntimeException('مسیر داخل بسته بروزرسانی امن نیست.');
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
        $root = realpath(dirname(__DIR__));
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $realDir = realpath($dir);
        if (!$root || !$realDir || strpos($realDir, $root) !== 0) {
            throw new RuntimeException('مسیر مقصد بروزرسانی معتبر نیست.');
        }
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException('نوشتن فایل بروزرسانی انجام نشد: ' . $relativePath);
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
