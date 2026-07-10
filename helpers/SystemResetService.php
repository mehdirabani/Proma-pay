<?php

class SystemResetService
{
    public const CONFIRM_TEXT = 'حذف داده‌ها را تایید می‌کنم';

    public static function resetOperationalData($preserveUserId)
    {
        $preserveUserId = (int) $preserveUserId;
        if ($preserveUserId <= 0) {
            throw new RuntimeException('کاربر مدیر معتبر نیست.');
        }

        $backup = BackupService::create('before-data-reset');
        $tables = self::databaseTables();
        $preserveTables = [
            'settings',
            'migrations',
            'migration_batches',
            'backup_logs',
            'users',
            'chat_channels',
        ];
        $cleared = [];

        Model::begin();
        try {
            Model::db()->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                if (in_array($table, $preserveTables, true)) {
                    continue;
                }
                $safeTable = str_replace('`', '``', $table);
                Model::db()->exec('DELETE FROM `' . $safeTable . '`');
                $cleared[] = $table;
            }
            if (in_array('users', $tables, true)) {
                Model::execute("DELETE FROM users WHERE role = 'customer'");
                Model::execute("UPDATE users SET status = 'active' WHERE id = ?", [$preserveUserId]);
            }
            if (in_array('settings', $tables, true)) {
                Settings::set('contract_next_serial', '1');
            }
            Model::db()->exec('SET FOREIGN_KEY_CHECKS=1');
            Model::commit();
        } catch (Throwable $e) {
            try {
                Model::db()->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $ignored) {
            }
            Model::rollBack();
            throw $e;
        }

        self::clearDataDirectories();
        BackupService::log('data_reset', $backup['name'], 'success', 'cleared tables: ' . implode(', ', $cleared));
        return [
            'backup' => $backup['name'],
            'cleared_tables' => $cleared,
        ];
    }

    protected static function databaseTables()
    {
        return Model::db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    protected static function clearDataDirectories()
    {
        $root = realpath(dirname(__DIR__));
        $targets = [
            'storage/secure_uploads' => [],
            'storage/private' => [],
            'storage/public' => [],
            'storage/cache' => [],
            'storage/uploads' => ['logos'],
        ];
        foreach ($targets as $relative => $keepNames) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_dir($path)) {
                continue;
            }
            self::clearDirectory($path, $root, $keepNames);
        }
    }

    protected static function clearDirectory($dir, $root, array $keepNames = [])
    {
        $real = realpath($dir);
        if (!$root || !$real || strpos($real, $root) !== 0) {
            throw new RuntimeException('مسیر پاکسازی فایل‌ها معتبر نیست.');
        }
        $items = scandir($real);
        if (!$items) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $keepNames, true)) {
                continue;
            }
            $path = $real . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                self::clearDirectory($path, $root);
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
    }
}
