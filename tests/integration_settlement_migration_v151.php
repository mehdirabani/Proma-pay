<?php

declare(strict_types=1);

$dsn = (string) getenv('PROMA_TEST_DB_DSN');
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$pdo = new PDO(
    $dsn,
    (string) (getenv('PROMA_TEST_DB_USER') ?: 'root'),
    (string) getenv('PROMA_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/2026_07_26_installment_settlement_engine.sql');
if (!preg_match('/CREATE TABLE IF NOT EXISTS settlement_quotes \((.*?)\) ENGINE=InnoDB/s', $migration, $match)) {
    throw new RuntimeException('Settlement quote DDL was not found in the migration.');
}

$probe = 'settlement_quotes_v151_probe';
try {
    $pdo->exec('DROP TABLE IF EXISTS ' . $probe);
    $ddl = 'CREATE TABLE ' . $probe . ' (' . $match[1] . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec($ddl);
    $primaryKeys = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$probe}' AND INDEX_NAME = 'PRIMARY'")->fetchColumn();
    if ((int) $primaryKeys !== 1) {
        throw new RuntimeException('Settlement quote migration did not create exactly one primary key.');
    }

    // Exercise the exact retry path used by an affected V1.5.0 host: the old
    // migration record is failed, then the corrected SQL must finish as success.
    $_SERVER['HTTP_HOST'] = 'qa.local';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.151';
    require dirname(__DIR__) . '/bootstrap.php';
    $modelPdo = new ReflectionProperty(Model::class, 'pdo');
    $modelPdo->setAccessible(true);
    $modelPdo->setValue(null, $pdo);
    $schema = new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema');
    $schema->setAccessible(true);
    $schema->invoke(null);
    Model::execute(
        "INSERT INTO migrations (migration_name, batch, source_type, source_id, status, started_at, finished_at, error_message)
         VALUES (?, 1, 'update', ?, 'failed', NOW(), NOW(), 'SQLSTATE[42000]: 1068 Multiple primary key defined')
         ON DUPLICATE KEY UPDATE status = 'failed', error_message = VALUES(error_message)",
        ['database/migrations/2026_07_26_installment_settlement_engine.sql', 'database/migrations/2026_07_26_installment_settlement_engine.sql']
    );
    $runner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
    $runner->setAccessible(true);
    $runner->invoke(null, 'database/migrations/2026_07_26_installment_settlement_engine.sql', $migration);
    $status = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', ['database/migrations/2026_07_26_installment_settlement_engine.sql']);
    if (($status['status'] ?? '') !== 'success') {
        throw new RuntimeException('Failed settlement migration was not retried successfully.');
    }
    echo "INTEGRATION_SETTLEMENT_MIGRATION_V151_OK\n";
} finally {
    $pdo->exec('DROP TABLE IF EXISTS ' . $probe);
}
