<?php

declare(strict_types=1);

$archive = 'C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-06-08-05.zip';
$target = $argv[1] ?? 'proma_qa_restore';
if (!in_array($target, ['proma_qa_restore', 'proma_qa_upgrade_v4'], true)) {
    fwrite(STDERR, "Target must be an approved disposable QA database.\n");
    exit(2);
}
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$exists = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
$exists->execute([$target]);
if ((int) $exists->fetchColumn() !== 0) {
    fwrite(STDERR, "REFUSED: QA target database already exists.\n");
    exit(3);
}

$zip = new ZipArchive();
if ($zip->open($archive) !== true || ($entry = $zip->getStream('database.sql')) === false) {
    fwrite(STDERR, "REFUSED: backup SQL entry unavailable.\n");
    exit(4);
}
$pdo->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$command = [
    'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe',
    '--protocol=TCP', '-h', '127.0.0.1', '-P', '3306', '-u', 'root',
    '--default-character-set=utf8mb4', $target,
];
$process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    fclose($entry);
    fwrite(STDERR, "Could not start isolated import.\n");
    exit(5);
}
while (!feof($entry)) {
    $chunk = fread($entry, 1048576);
    if ($chunk === false || ($chunk !== '' && fwrite($pipes[0], $chunk) !== strlen($chunk))) {
        fclose($entry);
        fclose($pipes[0]);
        proc_terminate($process);
        proc_close($process);
        fwrite(STDERR, "Backup stream failed.\n");
        exit(6);
    }
}
fclose($entry);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$zip->close();
if ($exitCode !== 0) {
    fwrite(STDERR, "Import failed (mysql exit {$exitCode}); SQL diagnostics suppressed.\n");
    exit(7);
}
echo "BACKUP_IMPORTED_TO_ISOLATED_QA_DB\n";
