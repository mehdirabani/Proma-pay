<?php

/** Checks every imported value across compatibility migrations, including legacy columns. */
final class RestoreIntegrityService
{
    public static function capture(): array
    {
        $snapshot = [];
        foreach (Model::db()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $columns = Model::db()->query('SHOW COLUMNS FROM ' . self::identifier($table))->fetchAll(PDO::FETCH_COLUMN);
            $snapshot[$table] = ['columns' => $columns, 'rows' => self::rows($table, $columns)];
        }
        return $snapshot;
    }

    public static function assertPreserved(array $snapshot): void
    {
        foreach ($snapshot as $table => $data) {
            $current = self::rows($table, $data['columns']);
            foreach ($data['rows'] as $hash => $count) {
                if (($current[$hash] ?? 0) < $count) {
                    throw new RuntimeException('تطبیق داده‌های بازیابی موفق نبود؛ مقادیر قبلی جدول حفظ نشده‌اند: ' . $table);
                }
            }
        }
    }

    private static function rows(string $table, array $columns): array
    {
        $query = 'SELECT ' . implode(',', array_map([self::class, 'identifier'], $columns)) . ' FROM ' . self::identifier($table);
        $statement = Model::db()->query($query);
        $hashes = [];
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            // Numeric columns may become wider or VARCHAR; retain their exact textual values and NULL.
            $row = array_map(static fn($value) => $value === null ? null : (string) $value, $row);
            $hash = hash('sha256', serialize($row));
            $hashes[$hash] = ($hashes[$hash] ?? 0) + 1;
        }
        $statement->closeCursor();
        return $hashes;
    }

    private static function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
