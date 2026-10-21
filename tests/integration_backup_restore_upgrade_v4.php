<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_upgrade_v4(?:_[a-f0-9]{10})?(?:;|$)/i', $dsn)
    || getenv('PROMA_TEST_ALLOW_BACKUP_UPGRADE') !== '1') {
    fwrite(STDERR, "Use only the disposable proma_qa_upgrade_v4 database with PROMA_TEST_ALLOW_BACKUP_UPGRADE=1.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.204';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');

$property->setValue(null, $pdo);

$count = static function (string $table) use ($pdo): int {
    if (!preg_match('/^[a-z_]+$/', $table)) {
        throw new InvalidArgumentException('Invalid QA table name.');
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
};
$before = [];
foreach (['users', 'contracts', 'payments', 'installments', 'system_outbox'] as $table) {
    $before[$table] = $count($table);
}

$migrationName = 'database/migrations/2026_10_06_v4_restored_backup_schema_repair.sql';
$runner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');

$runner->invoke(null, $migrationName, (string) file_get_contents(dirname(__DIR__) . '/' . $migrationName));

$after = [];
foreach (array_keys($before) as $table) {
    $after[$table] = $count($table);
    if ($after[$table] !== $before[$table]) {
        throw new RuntimeException('V4 restore repair changed row counts in ' . $table . '.');
    }
}

$assertColumns = static function (string $table, array $columns) use ($pdo): void {
    $query = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $query->execute([$table]);
    $present = array_fill_keys(array_column($query->fetchAll(), 'COLUMN_NAME'), true);
    foreach ($columns as $column) {
        if (!isset($present[$column])) {
            throw new RuntimeException('V4 restore repair missed ' . $table . '.' . $column . '.');
        }
    }
};
$assertColumns('payment_requests', ['response_code', 'error_message', 'updated_at']);
$assertColumns('system_outbox', ['event_type', 'max_attempts', 'next_attempt_at']);

$responseType = $pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'response_code'")->fetchColumn();
if ($responseType !== 'varchar') {
    throw new RuntimeException('V4 restore repair did not widen legacy payment response codes.');
}
$emptyEventTypes = $count('system_outbox') - (int) $pdo->query("SELECT COUNT(*) FROM system_outbox WHERE event_type IS NOT NULL AND event_type <> ''")->fetchColumn();
if ($emptyEventTypes !== 0) {
    throw new RuntimeException('V4 restore repair left outbox events without a type.');
}
$migration = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
if (($migration['status'] ?? '') !== 'success') {
    throw new RuntimeException('V4 restore migration was not recorded as successful.');
}

echo 'BACKUP_RESTORE_UPGRADE_V4_OK users=' . $after['users']
    . ' contracts=' . $after['contracts']
    . ' payments=' . $after['payments']
    . ' installments=' . $after['installments'] . "\n";
