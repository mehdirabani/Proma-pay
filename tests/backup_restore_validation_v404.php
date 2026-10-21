<?php
require dirname(__DIR__) . '/helpers/BackupService.php';
$validate = new ReflectionMethod(BackupService::class, 'validateDatabaseDump');
$base = 'CREATE TABLE users (id INT); CREATE TABLE contracts (id INT); CREATE TABLE installments (id INT);';
$validate->invoke(null, $base);
foreach (['CREATE TABLE users (id INT);', $base . ' USE other_database;', $base . ' INSERT INTO other_database.users VALUES (1);', $base . ' LOCK TABLES other_database.users WRITE;', $base . ' GRANT ALL ON *.* TO fake;', $base . ' DELETE FROM users;'] as $sql) {
    $rejected = false;
    try { $validate->invoke(null, $sql); } catch (RuntimeException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Unsafe or incomplete dump accepted');
}
echo "BACKUP_RESTORE_VALIDATION_V404_OK\n";
