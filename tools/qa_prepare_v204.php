<?php

declare(strict_types=1);

if (getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Set PROMA_TEST_ALLOW_DESTRUCTIVE=1 for the isolated QA reset.\n");
    exit(2);
}

$target = 'proma_qa_v204';
$installSql = dirname(__DIR__) . '/database/proma-pay-install.sql';
if (!is_file($installSql)) {
    fwrite(STDERR, "Canonical install SQL is missing.\n");
    exit(3);
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$query = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME IN ('proma-pay','proma_qa_restore','proma_qa_upgrade_v4')");
$preserved = array_column($query->fetchAll(PDO::FETCH_ASSOC), 'SCHEMA_NAME');
if (!in_array('proma-pay', $preserved, true)
    || !in_array('proma_qa_restore', $preserved, true)
    || !in_array('proma_qa_upgrade_v4', $preserved, true)) {
    fwrite(STDERR, "Refusing QA reset because the live DB or both backup clones were not verified.\n");
    exit(4);
}

$pdo->exec("DROP DATABASE IF EXISTS `{$target}`");
$pdo->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$process = proc_open([
    'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe',
    '--protocol=TCP', '-h', '127.0.0.1', '-P', '3306', '-u', 'root',
    '--default-character-set=utf8mb4', $target,
], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start canonical QA schema import.\n");
    exit(5);
}
$source = fopen($installSql, 'rb');
while (!feof($source)) {
    $chunk = fread($source, 1048576);
    if ($chunk === false || ($chunk !== '' && fwrite($pipes[0], $chunk) !== strlen($chunk))) {
        fclose($source);
        fclose($pipes[0]);
        proc_terminate($process);
        proc_close($process);
        fwrite(STDERR, "Canonical QA schema stream failed.\n");
        exit(6);
    }
}
fclose($source);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    fwrite(STDERR, "Canonical QA schema import failed; SQL data diagnostics suppressed.\n");
    exit(7);
}
echo "PROMA_QA_V204_PREPARED_FROM_CANONICAL_INSTALL\n";
