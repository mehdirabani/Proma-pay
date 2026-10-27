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

        self::assertNoProtectedHistory();
        $backup = BackupService::create('before-data-reset');
        $tables = self::databaseTables();
        $preserveTables = [
            'settings',
            'migrations',
            'migration_batches',
            'backup_logs',
            'audit_logs',
            'users',
            'chat_channels',
            'portal_banners',
        ];
        $cleared = [];

        Model::begin();
        try {
            Model::fetchAll("SELECT id FROM users WHERE role = 'customer' ORDER BY id FOR UPDATE");
            self::assertNoProtectedHistory();
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
        $stmt = Model::db()->query('SHOW TABLES');
        try {
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } finally {
            $stmt->closeCursor();
        }
    }

    protected static function assertNoProtectedHistory()
    {
        $tables = array_fill_keys(self::databaseTables(), true);
        if (isset($tables['audit_logs']) && Model::fetch(
            "SELECT 1 FROM audit_logs AS history
             INNER JOIN users AS customer ON customer.id = history.customer_id
             WHERE customer.role = 'customer' LIMIT 1"
        )) {
            throw new RuntimeException('بازنشانی متوقف شد؛ ردپای حسابرسی مشتری باید حفظ شود.');
        }
        $protectedTables = [
            'contracts', 'installments', 'payments', 'payment_corrections', 'payment_receipts',
            'legal_cases', 'legal_case_logs', 'identity_documents', 'operator_calls',
            'contract_change_logs', 'contract_guarantees', 'contract_guarantor_people', 'contract_items',
            'generated_contract_documents', 'customer_point_ledger', 'customer_merge_logs',
            'financial_ledger_events', 'financial_ledger_lines', 'collection_tasks', 'payment_groups',
            'payment_allocations', 'contract_requests', 'profile_update_requests',
        ];
        foreach ($protectedTables as $table) {
            if (!isset($tables[$table])) {
                continue;
            }
            $safeTable = str_replace('`', '``', $table);
            if (Model::fetch('SELECT 1 FROM `' . $safeTable . '` LIMIT 1')) {
                throw new RuntimeException('بازنشانی متوقف شد؛ سوابق مالی، قراردادی یا حقوقی باید حفظ شوند. از بایگانی مشتری استفاده کنید.');
            }
        }
        if (isset($tables['file_relations']) && Model::fetch(
            "SELECT 1 FROM file_relations WHERE entity_type IN ('customer', 'user', 'contract', 'legal_case', 'identity_document', 'payment_receipt') LIMIT 1"
        )) {
            throw new RuntimeException('بازنشانی متوقف شد؛ سند مرتبط با مشتری یا قرارداد باید حفظ شود.');
        }
        if (isset($tables['files']) && Model::fetch(
            "SELECT 1 FROM files WHERE related_entity_type IN ('customer', 'user', 'contract', 'legal_case', 'identity_document', 'payment_receipt') LIMIT 1"
        )) {
            throw new RuntimeException('بازنشانی متوقف شد؛ سند مرتبط با مشتری یا قرارداد باید حفظ شود.');
        }

        $userReferences = Model::fetchAll(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'users' AND REFERENCED_COLUMN_NAME = 'id'"
        );
        foreach ($userReferences as $reference) {
            $table = str_replace('`', '``', (string) ($reference['TABLE_NAME'] ?? ''));
            $column = str_replace('`', '``', (string) ($reference['COLUMN_NAME'] ?? ''));
            if ($table === '' || $column === '') {
                continue;
            }
            if (Model::fetch(
                "SELECT 1 FROM `{$table}` AS child
                 INNER JOIN users AS customer ON customer.id = child.`{$column}`
                 WHERE customer.role = 'customer' LIMIT 1"
            )) {
                throw new RuntimeException('بازنشانی متوقف شد؛ یک رکورد وابسته به مشتری باید حفظ شود.');
            }
        }
    }

    protected static function clearDataDirectories()
    {
        $root = realpath(dirname(__DIR__));
        $targets = [
            'storage/secure_uploads' => [],
            'storage/private' => [],
            'storage/public' => [],
            'storage/cache' => [],
            'storage/uploads' => ['logos', 'portal-banners'],
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
