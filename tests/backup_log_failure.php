<?php

// Regression: a backup-log schema/database failure must not change the outcome
// of the backup, restore, or update operation that called it.
class SchemaGuard
{
    public static function requireColumns($table, array $columns)
    {
        throw new RuntimeException('synthetic schema failure');
    }
}

require dirname(__DIR__) . '/helpers/BackupService.php';

if (BackupService::log('restore', 'test.zip', 'success', 'synthetic') !== false) {
    fwrite(STDERR, "Expected a failed best-effort log to return false.\n");
    exit(1);
}

echo "Best-effort backup logging regression passed.\n";
