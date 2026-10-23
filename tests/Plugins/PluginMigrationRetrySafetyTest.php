<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/core/PluginManager.php';
$reflection = new ReflectionClass(PluginManager::class);
$pluginManager = $reflection->newInstanceWithoutConstructor();
$splitSql = $reflection->getMethod('splitSql');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "PLUGIN_MIGRATION_RETRY_SAFETY_FAILED: {$message}\n");
        exit(1);
    }
};

foreach ([
    'plugins/PromaRepair/migrations/2026_10_02_repair_expense_approval.sql' => 20,
    'plugins/PromaRepair/migrations/2026_10_03_repair_soft_delete.sql' => 5,
    'plugins/PromaAffiliate/migrations/2026_10_05_affiliate_wallet_management.sql' => 20,
] as $path => $minimumStatementCount) {
    $sql = (string) file_get_contents($root . '/' . $path);
    preg_match_all('/ALTER TABLE\s+[a-z0-9_]+\s+ADD COLUMN\s+/i', $sql, $columns);
    preg_match_all('/COLUMN_NAME\s*=\s*\'[a-z0-9_]+\'/i', $sql, $columnGuards);
    preg_match_all('/ALTER TABLE\s+[a-z0-9_]+\s+ADD\s+(?:UNIQUE\s+)?KEY\s+/i', $sql, $indexes);
    preg_match_all('/INDEX_NAME\s*=\s*\'[a-z0-9_]+\'/i', $sql, $indexGuards);
    $assert(count($columns[0]) === count($columnGuards[0]), $path . ' has an unguarded ADD COLUMN');
    $assert(count($indexes[0]) === count($indexGuards[0]), $path . ' has an unguarded index creation');
    $statements = $splitSql->invoke($pluginManager, $sql);
    $assert(count($statements) > $minimumStatementCount, $path . ' is not split into retryable SQL statements');
}

$registry = (string) file_get_contents($root . '/models/PluginRegistry.php');
$manager = (string) file_get_contents($root . '/core/PluginManager.php');
$executeSqlBranch = <<<'CODE'
        if (preg_match('/^\s*EXECUTE\b/i', $sql)) {
            $statement = $connection->query($sql);
            if ($statement instanceof PDOStatement) {
                try {
                    do {
                        if ($statement->columnCount() > 0) {
                            $statement->fetchAll(PDO::FETCH_ASSOC);
                        }
                    } while ($statement->nextRowset());
                } finally {
                    $statement->closeCursor();
                }
            }
            return;
        }
CODE;
$failureRecord = <<<'CODE'
recordMigration($manifest['id'], $name, hash('sha256', $sql), 'failed'
CODE;
$assert(strpos($registry, "'success'])") !== false, 'failed plugin migrations must remain retryable');
$assert(strpos($manager, $failureRecord) !== false, 'plugin migration failures must be recorded');
$assert(strpos($manager, $executeSqlBranch) !== false, 'SQL-level EXECUTE result sets must be drained and closed before the next migration statement');

echo "PLUGIN_MIGRATION_RETRY_SAFETY_OK\n";
