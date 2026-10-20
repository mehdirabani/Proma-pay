<?php

final class SchemaGuard
{
    protected static $tables = [];
    protected static $columns = [];

    public static function tableExists($table)
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
        if ($table === '') return false;
        if (!array_key_exists($table, self::$tables)) {
            $row = Model::fetch(
                'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            self::$tables[$table] = (int) ($row['total'] ?? 0) > 0;
        }
        return self::$tables[$table];
    }

    public static function columnsExist($table, array $columns)
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
        if ($table === '' || !self::tableExists($table)) return false;
        if (!isset(self::$columns[$table])) {
            $rows = Model::fetchAll(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            self::$columns[$table] = array_fill_keys(array_column($rows, 'COLUMN_NAME'), true);
        }
        foreach ($columns as $column) {
            if (!isset(self::$columns[$table][(string) $column])) return false;
        }
        return true;
    }

    public static function requireTables(array $tables)
    {
        foreach ($tables as $table) {
            $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
            if ($table === '') {
                throw new RuntimeException('نام جدول برای بررسی ساختار معتبر نیست.');
            }
            if (!self::tableExists($table)) {
                throw new RuntimeException('ساختار پایگاه داده کامل نیست. migration نسخه جاری را اجرا کنید. جدول مفقود: ' . $table);
            }
        }
        return true;
    }

    public static function requireColumns($table, array $columns)
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table);
        if ($table === '') {
            throw new RuntimeException('نام جدول برای بررسی ساختار معتبر نیست.');
        }
        self::columnsExist($table, []);
        $missing = [];
        foreach ($columns as $column) {
            if (!isset(self::$columns[$table][(string) $column])) {
                $missing[] = (string) $column;
            }
        }
        if ($missing) {
            throw new RuntimeException('ساختار پایگاه داده کامل نیست. migration نسخه جاری را اجرا کنید. فیلدهای مفقود: ' . implode(', ', $missing));
        }
        return true;
    }

    public static function clearCache()
    {
        self::$tables = [];
        self::$columns = [];
    }
}
