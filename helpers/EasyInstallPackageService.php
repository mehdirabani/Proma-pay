<?php

class EasyInstallPackageService
{
    public static function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'releases';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function packages()
    {
        $packages = [];
        $paths = array_merge(
            glob(self::baseDir() . DIRECTORY_SEPARATOR . 'proma-pay_v*.zip') ?: [],
            glob(self::baseDir() . DIRECTORY_SEPARATOR . 'proma-pay-easy-install-*.zip') ?: []
        );
        foreach (array_unique($paths) as $path) {
            if (!is_file($path)) {
                continue;
            }
            $packages[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => filesize($path),
                'created_at' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }
        usort($packages, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $packages;
    }

    public static function create()
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZipArchive برای ساخت بسته نصبی فعال نیست.');
        }
        $version = preg_replace('/[^0-9.]+/', '', (string) app_config('version', '1.0.0')) ?: '1.0.0';
        $fileName = 'proma-pay_v' . str_replace('.', '-', $version) . '.zip';
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $fileName;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساخت فایل zip انجام نشد.');
        }

        $root = realpath(dirname(__DIR__));
        $databaseSql = self::databaseSqlForPackage();
        $included = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $real = $item->getRealPath();
            if (!$real || !self::isAllowedPath($root, $real, $item->isDir())) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($real, strlen($root) + 1));
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
                continue;
            }
            if ($zip->addFile($real, $relative)) {
                $included++;
            }
        }

        if ($databaseSql !== '') {
            $zip->addFromString('database/proma-pay-install.sql', $databaseSql);
        }
        $zip->addFromString('storage/secure_uploads/.htaccess', "Require all denied\nDeny from all\n");
        $zip->addFromString('storage/uploads/.gitkeep', '');
        $zip->addFromString('release-meta.json', json_encode([
            'name' => 'Proma Pay Easy Install Package',
            'created_at' => date('Y-m-d H:i:s'),
            'files_count' => $included,
            'database_dump_included' => $databaseSql !== '',
            'install_url' => 'installer.php',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->close();

        BackupService::log('easy_install_package', $fileName, 'success', 'easy install package created');
        return ['name' => $fileName, 'path' => $path, 'files_count' => $included];
    }

    public static function find($fileName)
    {
        $safe = basename(rawurldecode((string) $fileName));
        if (!preg_match('/^(proma-pay_v\d+(?:-\d+){2}|proma-pay-easy-install-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})\.zip$/', $safe)) {
            return null;
        }
        $path = self::baseDir() . DIRECTORY_SEPARATOR . $safe;
        return is_file($path) ? $path : null;
    }

    public static function delete($fileName)
    {
        $path = self::find($fileName);
        if (!$path) {
            throw new RuntimeException('بسته نصبی پیدا نشد.');
        }
        $name = basename($path);
        if (!unlink($path)) {
            throw new RuntimeException('حذف بسته نصبی انجام نشد.');
        }
        BackupService::log('easy_install_delete', $name, 'success', 'easy install package deleted');
        return $name;
    }

    protected static function isAllowedPath($root, $realPath, $isDir)
    {
        $relative = str_replace('\\', '/', substr($realPath, strlen($root) + 1));
        if ($relative === '') {
            return false;
        }
        $lower = strtolower($relative);
        $top = strtok($lower, '/');
        $allowedRootDirs = ['assets', 'config', 'controllers', 'core', 'cron', 'database', 'helpers', 'html', 'models', 'storage', 'views'];
        $allowedRootFiles = [
            '.htaccess',
            '.gitignore',
            'bootstrap.php',
            'changelog.md',
            'index.php',
            'install.php',
            'installer.php',
            'install.md',
            'license.txt',
            'manifest.json',
            'offline.html',
            'readme.md',
            'requirements.txt',
            'reset-service-worker.html',
            'service-worker.js',
        ];
        if (strpos($lower, '/') === false && !$isDir) {
            return in_array($lower, $allowedRootFiles, true);
        }
        if ($top && !in_array($top, $allowedRootDirs, true)) {
            return false;
        }
        $blockedExact = [
            'config/database.php',
            'database/proma-pay-install.sql',
            '.env',
            'storage/logs/install.log',
            'storage/logs/installer.log',
        ];
        if (in_array($lower, $blockedExact, true)) {
            return false;
        }
        $blockedPrefixes = [
            '.git/',
            '.idea/',
            '.vscode/',
            'docs/',
            'electron/',
            'node_modules/',
            'vendor/',
            'html/rtl/dist/',
            'html/rtl/starter-kit/',
            'html/rtl/template/',
            'html/rtl/assets/pug/',
            'html/rtl/assets/scss/',
            'html/rtl/assets/video/',
            'html/rtl/assets/audio/',
            'storage/backups/',
            'storage/releases/',
            'storage/secure_uploads/',
            'storage/uploads/',
            'uploads/',
        ];
        foreach ($blockedPrefixes as $prefix) {
            if (strpos($lower, $prefix) === 0 || ($isDir && rtrim($lower, '/') === rtrim($prefix, '/'))) {
                return false;
            }
        }
        if (!$isDir && preg_match('/\.(zip|sql|log|map|tmp)$/i', basename($relative))) {
            return false;
        }
        return true;
    }

    protected static function databaseSqlForPackage()
    {
        try {
            $backup = BackupService::create('easy-install-source');
            $sql = self::extractDatabaseSql($backup['path'] ?? '');
            if ($sql !== '') {
                return $sql;
            }
        } catch (Throwable $e) {
        }

        foreach (BackupService::files() as $file) {
            $path = BackupService::findBackup($file['name']);
            $sql = self::extractDatabaseSql($path);
            if ($sql !== '') {
                return $sql;
            }
        }
        return '';
    }

    protected static function extractDatabaseSql($path)
    {
        if (!$path || !is_file($path)) {
            return '';
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'sql') {
            $sql = file_get_contents($path);
            return trim((string) $sql) === '' ? '' : $sql;
        }
        if ($extension !== 'zip' || !class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $sql = (string) $zip->getFromName('database.sql');
        $zip->close();
        return trim($sql) === '' ? '' : $sql;
    }
}
