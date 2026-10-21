<?php
declare(strict_types=1);

// This harness refuses every database except the disposable one it owns.
$mode = $argv[1] ?? 'inspect';
$app = $argv[2] ?? 'C:/laragon/www/proma-qa-v404-20261008';
$backupPath = 'C:/laragon/www/proma/storage/backups/proma-pay-backup-2026-10-06-08-05.zip';
$config = require 'C:/laragon/www/proma/config/database.php';
$qaName = 'proma_qa_v404_20261008';
$pdo = new PDO('mysql:host=' . $config['host'] . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if ($mode === 'init') {
    if ($pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$qaName'")->fetchColumn()) throw new RuntimeException('QA database already exists; refusing overwrite.');
    $pdo->exec("CREATE DATABASE `$qaName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $zip = new ZipArchive();
    if ($zip->open(dirname(__DIR__) . '/dist/candidates/v4.0.3/core/PromaPay-v4.0.3.zip') !== true) throw new RuntimeException('Missing verified base archive');
    $zip->extractTo($app);
    $zip->close();
    $config['database'] = $qaName;
    file_put_contents($app . '/config/database.php', "<?php\nreturn " . var_export($config, true) . ";\n");
    file_put_contents($app . '/installed.lock', 'qa=1');
}
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.44';
require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== $qaName) throw new RuntimeException('Wrong QA database');
$run = new ReflectionMethod(BackupService::class, 'runSql');
$dump = new ReflectionMethod(BackupService::class, 'databaseDumpFromZip');
$zip = new ZipArchive();
if ($zip->open($backupPath) !== true) throw new RuntimeException('Missing real historical backup');
$database = $dump->invoke(null, $zip);
$zip->close();
if ($mode === 'init') {
    $run->invoke(null, $database['sql']);
    echo "QA_V404_CREATED_FROM_REAL_LEGACY_BACKUP\n";
} elseif ($mode === 'inspect') {
    foreach (['payment_requests', 'payment_groups', 'system_outbox', 'users'] as $table) {
        $columns = Model::fetchAll('SHOW COLUMNS FROM `' . $table . '`');
        echo $table . ': ' . implode(',', array_column($columns, 'Field')) . "\n";
    }
} elseif ($mode === 'restore') {
    $before = [];
    foreach (['users','contracts','payments','installments'] as $table) $before[$table] = (int) Model::db()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    $fingerprint = static function () { $hash = hash_init('sha256'); foreach (Model::fetchAll('SELECT id,password_hash FROM users ORDER BY id') as $row) hash_update($hash, json_encode($row)); return hash_final($hash); };
    $hash = $fingerprint();
    $restore = new ReflectionMethod(BackupService::class, 'restoreZipBackup');
    $safety = $restore->invoke(null, $backupPath, basename($backupPath));
    foreach ($before as $table => $count) if ((int) Model::db()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== $count) throw new RuntimeException('Legacy row count changed: ' . $table);
    if (!hash_equals($hash, $fingerprint())) throw new RuntimeException('Legacy credential fingerprints changed');
    echo "REAL_LEGACY_RESTORE_V404_OK_CORE_ROWS_AND_CREDENTIALS_PRESERVED\n";
    echo 'SAFETY_BACKUP=' . $safety . "\n";
} elseif ($mode === 'standard-sql') {
    $expectedConfig = $config; $expectedConfig['database'] = 'proma_qa_exact_v402_to_v404_20261008';
    $expected = new PDO('mysql:host=' . $expectedConfig['host'] . ';dbname=' . $expectedConfig['database'] . ';charset=utf8mb4', $expectedConfig['username'], $expectedConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $fingerprint = static function (PDO $connection) {
        $hash = hash_init('sha256');
        foreach ($connection->query('SELECT id,password_hash,username,role,status,mobile FROM users ORDER BY id')->fetchAll() as $row) hash_update($hash, json_encode($row));
        foreach (['contracts','payments','installments'] as $table) foreach ($connection->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll() as $row) hash_update($hash, json_encode($row));
        return hash_final($hash);
    };
    $hash = $fingerprint($expected);
    $target = $app . '/storage/backups/proma-pay-backup-2026-10-08-09-40-standard.sql';
    copy('C:/laragon/backup/proma-pay-v402-preupgrade-20261008-072958/qa-v402-before-password-mode-test.sql', $target);
    BackupService::restoreStored(basename($target));
    if (!hash_equals($hash, $fingerprint(Model::db()))) throw new RuntimeException('Standard SQL restore changed financial/credential fingerprints');
    echo "STANDARD_MYSQLDUMP_SQL_RESTORE_V404_OK_BUSINESS_AND_CREDENTIALS_PRESERVED\n";
} elseif ($mode === 'failure') {
    $fingerprint = static function () {
        $hash = hash_init('sha256');
        foreach (['users','contracts','payments','installments'] as $table) foreach (Model::fetchAll('SELECT * FROM `' . $table . '` ORDER BY id') as $row) hash_update($hash, json_encode($row));
        return hash_final($hash);
    };
    $before = $fingerprint();
    $testPath = $app . '/storage/backups/qa-v404-invalid.sql';
    file_put_contents($testPath, $database['sql'] . "\nCREATE TABLE qa_failed_import_extra (id INT PRIMARY KEY) ENGINE=InnoDB;\nINSERT INTO qa_missing_v404_table VALUES (1);\n");
    $failed = false;
    try { (new ReflectionMethod(BackupService::class, 'restoreSqlBackup'))->invoke(null, $testPath, 'qa-invalid.sql'); }
    catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'بازگردانده شدند') && str_contains($e->getMessage(), 'qa_missing_v404_table'); }
    if (!$failed || !hash_equals($before, $fingerprint())) throw new RuntimeException('SQL failure recovery did not preserve core fingerprints');
    if (SchemaGuard::tableExists('qa_failed_import_extra')) throw new RuntimeException('Failed restore retained a table absent from the safety snapshot');
    unlink($testPath);
    $marker = $app . '/uploads/qa-v404-journal.txt';
    $newFile = $app . '/uploads/qa-v404-new.txt';
    $blocked = $app . '/uploads/qa-v404-blocked.txt';
    file_put_contents($marker, 'ORIGINAL');
    mkdir($blocked, 0700);
    $testPath = $app . '/storage/backups/qa-v404-file-failure.zip';
    $testZip = new ZipArchive();
    $testZip->open($testPath, ZipArchive::CREATE | ZipArchive::EXCL);
    $testZip->addFromString('database.sql', $database['sql']);
    $testZip->addFromString('uploads/qa-v404-journal.txt', 'CHANGED');
    $testZip->addFromString('uploads/qa-v404-new.txt', 'NEW');
    $testZip->addFromString('uploads/qa-v404-blocked.txt', 'FAIL');
    $testZip->close();
    $failed = false;
    try { (new ReflectionMethod(BackupService::class, 'restoreZipBackup'))->invoke(null, $testPath, 'qa-file-failure.zip'); }
    catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'بازگردانده شدند') && str_contains($e->getMessage(), 'بازیابی فایل'); }
    if (!$failed || !hash_equals($before, $fingerprint()) || file_get_contents($marker) !== 'ORIGINAL' || file_exists($newFile)) throw new RuntimeException('File failure recovery did not preserve previous state');
    unlink($marker); rmdir($blocked); unlink($testPath);
    if (is_file($app . '/storage/.runtime-lock/restore-pending.json')) throw new RuntimeException('Maintenance marker remained after safe recovery');
    echo "RESTORE_SQL_AND_FILE_FAILURE_RECOVERY_V404_OK\n";
}
