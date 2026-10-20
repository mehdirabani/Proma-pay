<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '' || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1'
    || !preg_match('/(?:^|;)dbname=proma_qa_update_[a-f0-9]{10}(?:;|$)/i', $dsn)) {
    fwrite(STDERR, "Use an isolated proma_qa_update_<10-hex> database with PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive is required for the update rollback integration test.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.205';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);

$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$markerColumn = 'qa_rollback_' . $suffix;
$markerSetting = 'qa_rollback_' . $suffix;
$backupName = 'proma-pay-backup-' . date('Y-m-d-H-i-s') . '-before-update-qa' . $suffix . '.zip';
$backupPath = BackupService::baseDir() . DIRECTORY_SEPARATOR . $backupName;
$sqlPath = tempnam(sys_get_temp_dir(), 'proma-update-snapshot-test-');
if ($sqlPath === false) throw new RuntimeException('Could not create the isolated SQL snapshot file.');

try {
    $dumpInfo = (new ReflectionMethod(BackupService::class, 'databaseDumpToFile'))->invoke(null, $sqlPath);
    $sqlBytes = filesize($sqlPath);
    $sqlHash = hash_file('sha256', $sqlPath);
    if (!is_array($dumpInfo) || $sqlBytes < 1 || !is_string($sqlHash)) throw new RuntimeException('Could not create the isolated database snapshot.');

    $checksumJson = json_encode([
        'files' => ['database.sql' => ['size' => $sqlBytes, 'sha256' => $sqlHash]],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $checksumHash = hash('sha256', $checksumJson);
    $manifestJson = json_encode([
        'format' => 'proma-pay-backup',
        'backup_format_version' => 1,
        'app' => 'Proma Pay',
        'files_manifest' => ['path' => 'checksums.json', 'entry_count' => 1, 'sha256' => $checksumHash],
        'checksum' => ['algorithm' => 'sha256', 'file' => 'checksums.json', 'value' => $checksumHash],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $zip = new ZipArchive();
    if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Could not create the isolated pre-update database snapshot.');
    }
    if (!$zip->addFile($sqlPath, 'database.sql')) throw new RuntimeException('Could not add the streamed SQL snapshot to the archive.');
    $zip->addFromString('checksums.json', $checksumJson);
    $zip->addFromString('manifest.json', $manifestJson);
    if (!$zip->close()) throw new RuntimeException('Could not finalize the isolated pre-update snapshot.');

    $pdo->exec('ALTER TABLE users ADD COLUMN `' . $markerColumn . '` INT NULL');
    $setting = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, 0)');
    $setting->execute([$markerSetting, 'temporary post-update state']);

    BackupService::restoreUpdateDatabaseSnapshot($backupName);

    $column = $pdo->query("SHOW COLUMNS FROM users LIKE '" . $markerColumn . "'")->fetch();
    $settingCheck = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
    $settingCheck->execute([$markerSetting]);
    $settingsRemain = (int) $settingCheck->fetchColumn();
    foreach (['users', 'contracts', 'installments', 'payment_groups'] as $table) {
        if (!$pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn()) {
            throw new RuntimeException('Database snapshot restore missed table ' . $table . '.');
        }
    }
    if ($column || $settingsRemain !== 0) {
        throw new RuntimeException('Pre-update database snapshot did not remove the post-update schema/data.');
    }

    echo "INTEGRATION_UPDATE_DATABASE_ROLLBACK_V5_OK\n";
} finally {
    if (is_file($backupPath)) @unlink($backupPath);
    if (is_file($sqlPath)) @unlink($sqlPath);
}
