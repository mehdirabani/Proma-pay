<?php

declare(strict_types=1);

/**
 * Destructive schema-upgrade test. It is intentionally limited to a disposable
 * database whose name is exactly proma_qa_v204 and needs an explicit opt-in.
 */
$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '' || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1' || !preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/', $dsn)) {
    fwrite(STDERR, "Refusing schema mutation: use the disposable proma_qa_v204 database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
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
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$migrationName = 'database/migrations/2026_10_01_group_payment_allocation_scope.sql';
$schemaSetup = new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema');
$schemaSetup->setAccessible(true);
$schemaSetup->invoke(null);
$column = $pdo->query("SHOW COLUMNS FROM payment_groups LIKE 'allocation_scope'")->fetch();
if ($column) {
    $pdo->exec('ALTER TABLE payment_groups DROP COLUMN allocation_scope');
}
$statement = $pdo->prepare('DELETE FROM migrations WHERE migration_name = ?');
$statement->execute([$migrationName]);

$runner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$runner->setAccessible(true);
$runner->invoke(null, $migrationName, (string) file_get_contents(dirname(__DIR__) . '/' . $migrationName));

$column = $pdo->query("SHOW COLUMNS FROM payment_groups LIKE 'allocation_scope'")->fetch();
$status = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
if (!$column || ($status['status'] ?? '') !== 'success') {
    throw new RuntimeException('Controlled updater did not install and record allocation_scope.');
}

echo "INTEGRATION_PAYMENT_SCOPE_MIGRATION_V204_OK\n";
