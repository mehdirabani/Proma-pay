<?php

class BackupService
{
    public const CONFIRM_TEXT = 'بازیابی را تایید می‌کنم';

    public static function ensureSchema()
    {
        Model::execute(
            "CREATE TABLE IF NOT EXISTS backup_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                action VARCHAR(40) NOT NULL,
                file_name VARCHAR(255) NULL,
                status VARCHAR(40) NOT NULL,
                message TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_backup_logs_user (user_id),
                INDEX idx_backup_logs_action (action)
            )"
        );
    }

    public static function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
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
        foreach (glob(self::baseDir() . DIRECTORY_SEPARATOR . 'proma-pay-backup-*.*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $name = basename($path);
            if (!self::findBackup($name)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'type' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                'size' => (int) filesize($path),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ];
        }
        usort($files, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $files;
    }

    public static function log($action, $fileName, $status, $message = '')
    {
        self::ensureSchema();
        Model::execute(
            'INSERT INTO backup_logs (user_id, action, file_name, status, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [Auth::id(), $action, $fileName, $status, $message]
        );
    }

    public static function create($reason = 'manual')
    {
        self::ensureSchema();
        $extension = class_exists('ZipArchive') ? '.zip' : '.sql';
        $fileName = 'proma-pay-backup-' . date('Y-m-d-H-i') . $extension;
        if ($reason !== 'manual') {
            $fileName = str_replace($extension, '-' . preg_replace('/[^a-z0-9_-]/i', '', $reason) . $extension, $fileName);
        }
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $fileName;
        if (!class_exists('ZipArchive')) {
            $sql = "-- Proma Pay database backup\n-- Created at: " . date('Y-m-d H:i:s') . "\n\n" . self::databaseDump();
            if (file_put_contents($path, $sql) === false) {
                throw new RuntimeException('ساخت فایل بکاپ SQL انجام نشد.');
            }
            self::log('backup', $fileName, 'success', 'ZipArchive فعال نیست؛ بکاپ دیتابیس به صورت SQL ساخته شد.');
            return ['name' => $fileName, 'path' => $path, 'type' => 'sql'];
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساخت فایل بکاپ انجام نشد.');
        }
        $zip->addFromString('database.sql', self::databaseDump());
        $zip->addFromString('config-snapshot.json', json_encode(Settings::allKeyed(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->addFromString('backup-meta.json', json_encode([
            'app' => 'Proma Pay',
            'created_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'database' => 'mysql',
            'version' => app_config('version', '1.0.0'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        self::addDirectory($zip, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads', 'uploads');
        self::addDirectory($zip, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage', 'storage', [realpath(self::baseDir())]);
        self::addDirectory($zip, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'contracts', 'storage/contract-templates');
        $zip->close();
        self::log('backup', $fileName, 'success', 'backup created');
        return ['name' => $fileName, 'path' => $path, 'type' => 'zip'];
    }

    public static function restore($upload)
    {
        if (!$upload || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new RuntimeException('فایل بکاپ انتخاب نشده است.');
        }
        if ((int) ($upload['size'] ?? 0) > 64 * 1024 * 1024) {
            throw new RuntimeException('حجم فایل بکاپ بیش از حد مجاز است.');
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
        $before = self::create('before-restore');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'sql') {
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException('فایل SQL بکاپ قابل خواندن نیست.');
            }
            self::runSql($sql);
            self::log('restore', basename($path), 'success', 'stored sql restored; safety backup: ' . $before['name']);
            return $before['name'];
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZipArchive روی PHP فعال نیست.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل zip بکاپ قابل خواندن نیست.');
        }
        self::validateZip($zip);
        $sql = $zip->getFromName('database.sql');
        $meta = json_decode($zip->getFromName('backup-meta.json'), true);
        if (!is_array($meta) || ($meta['app'] ?? '') !== 'Proma Pay') {
            throw new RuntimeException('metadata بکاپ معتبر نیست.');
        }
        self::restoreFiles($zip, $path, 'uploads/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
        self::restoreFiles($zip, $path, 'storage/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage', ['storage/backups/']);
        self::runSql($sql);
        $zip->close();
        self::log('restore', basename($path), 'success', 'stored backup restored; safety backup: ' . $before['name']);
        return $before['name'];
    }

    protected static function restoreSqlBackup($path, $label = '')
    {
        $before = self::create('before-restore');
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('فایل SQL بکاپ قابل خواندن نیست.');
        }
        self::runSql($sql);
        self::log('restore', basename((string) $label) ?: basename($path), 'success', 'sql backup restored; safety backup: ' . $before['name']);
        return $before['name'];
    }

    protected static function restoreZipBackup($path, $label = '')
    {
        $before = self::create('before-restore');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل zip بکاپ قابل خواندن نیست.');
        }
        self::validateZip($zip);
        $sql = $zip->getFromName('database.sql');
        $meta = json_decode($zip->getFromName('backup-meta.json'), true);
        if (!is_array($meta) || ($meta['app'] ?? '') !== 'Proma Pay') {
            throw new RuntimeException('metadata بکاپ معتبر نیست.');
        }
        self::restoreFiles($zip, $path, 'uploads/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
        self::restoreFiles($zip, $path, 'storage/', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage', ['storage/backups/']);
        self::runSql($sql);
        $zip->close();
        self::log('restore', basename((string) $label) ?: basename($path), 'success', 'zip backup restored; safety backup: ' . $before['name']);
        return $before['name'];
    }

    protected static function detectBackupFormat($path, $originalName = '')
    {
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($extension === 'sql') {
            return 'sql';
        }
        if ($extension === 'zip') {
            return 'zip';
        }

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
        if (preg_match('/SET\\s+FOREIGN_KEY_CHECKS|CREATE\\s+TABLE|INSERT\\s+INTO/i', $header)) {
            return 'sql';
        }
        return null;
    }

    public static function findBackup($fileName)
    {
        $safe = basename((string) $fileName);
        if (!preg_match('/^proma-pay-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}(?:-[a-z0-9_-]+)?\.(zip|sql)$/i', $safe)) {
            return null;
        }
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $safe;
        return is_file($path) ? $path : null;
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
        $pdo = Model::db();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            $sql .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . array_values($create)[1] . ";\n";
            $rows = Model::fetchAll('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            foreach ($rows as $row) {
                $columns = array_map(function ($column) {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, array_keys($row));
                $values = array_map(function ($value) use ($pdo) {
                    return $value === null ? 'NULL' : $pdo->quote((string) $value);
                }, array_values($row));
                $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
            }
        }
        return $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    protected static function addDirectory(ZipArchive $zip, $path, $prefix, array $excludedRealPaths = [])
    {
        $zip->addEmptyDir($prefix);
        if (!is_dir($path)) {
            return;
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
            $zip->addFile($real, $local);
        }
    }

    protected static function validateZip(ZipArchive $zip)
    {
        $hasSql = false;
        $hasMeta = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            if ($normalized !== $name || strpos($normalized, '../') !== false || strpos($normalized, '/..') !== false || preg_match('/^[a-z]:/i', $normalized) || substr($normalized, 0, 1) === '/') {
                throw new RuntimeException('ساختار فایل zip امن نیست.');
            }
            $hasSql = $hasSql || $normalized === 'database.sql';
            $hasMeta = $hasMeta || $normalized === 'backup-meta.json';
        }
        if (!$hasSql || !$hasMeta) {
            throw new RuntimeException('database.sql یا backup-meta.json داخل بکاپ وجود ندارد.');
        }
    }

    protected static function restoreFiles(ZipArchive $zip, $zipPath, $prefix, $target, array $skipPrefixes = [])
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        $targetReal = realpath($target);
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
            $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $resolvedDir = realpath($dir);
            if (!$resolvedDir || strpos($resolvedDir, $targetReal) !== 0) {
                throw new RuntimeException('مسیر فایل‌های بکاپ معتبر نیست.');
            }
            copy('zip://' . $zipPath . '#' . $name, $destination);
        }
    }

    protected static function runSql($sql)
    {
        $sql = trim((string) $sql);
        if ($sql === '') {
            throw new RuntimeException('database.sql خالی است.');
        }
        Model::db()->exec($sql);
    }
}
