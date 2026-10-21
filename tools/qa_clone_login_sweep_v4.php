<?php

declare(strict_types=1);

if (getenv('PROMA_TEST_ALLOW_ALL_USER_LOGIN') !== '1') {
    fwrite(STDERR, "Set PROMA_TEST_ALLOW_ALL_USER_LOGIN=1 for this isolated backup clone.\n");
    exit(2);
}

$source = 'proma_qa_upgrade_v4';
$target = 'proma_qa_login_sweep_v4';
$mysqlDirectory = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
$mysqlClient = $mysqlDirectory . '/mysql.exe';
$mysqlDump = $mysqlDirectory . '/mysqldump.exe';
if (!is_file($mysqlClient) || !is_file($mysqlDump)) {
    fwrite(STDERR, "The configured Laragon MySQL client is unavailable.\n");
    exit(3);
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    foreach (['proma-pay', 'proma_qa_restore', 'proma_qa_upgrade_v4', 'proma_qa_v204'] as $preserved) {
        $check->execute([$preserved]);
        if ((int) $check->fetchColumn() !== 1) {
            throw new RuntimeException('A preserved source or live database could not be verified.');
        }
    }
    $check->execute([$source]);
    if ((int) $check->fetchColumn() !== 1) {
        throw new RuntimeException('The migrated backup source database is missing.');
    }
    $check->execute([$target]);
    if ((int) $check->fetchColumn() !== 0) {
        throw new RuntimeException('The dedicated login-sweep database already exists; refusing to overwrite it.');
    }

    $pdo->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $temporaryDump = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proma-qa-login-copy-' . bin2hex(random_bytes(8)) . '.sql';
    try {
        $dumpProcess = proc_open([
            $mysqlDump,
            '--protocol=TCP', '-h', '127.0.0.1', '-P', '3306', '-u', 'root',
            '--default-character-set=utf8mb4', '--single-transaction', '--skip-lock-tables',
            '--routines', '--triggers', '--events', '--no-tablespaces', $source,
        ], [0 => ['pipe', 'r'], 1 => ['file', $temporaryDump, 'wb'], 2 => ['pipe', 'w']], $dumpPipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        if (!is_resource($dumpProcess)) {
            throw new RuntimeException('Could not start the isolated backup clone.');
        }
        fclose($dumpPipes[0]);
        stream_get_contents($dumpPipes[2]);
        fclose($dumpPipes[2]);
        if (proc_close($dumpProcess) !== 0 || !is_file($temporaryDump) || filesize($temporaryDump) < 1) {
            throw new RuntimeException('The isolated backup snapshot could not be created.');
        }

        $restoreProcess = proc_open([
            $mysqlClient,
            '--protocol=TCP', '-h', '127.0.0.1', '-P', '3306', '-u', 'root',
            '--default-character-set=utf8mb4', '--binary-mode=1', $target,
        ], [0 => ['file', $temporaryDump, 'rb'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $restorePipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        if (!is_resource($restoreProcess)) {
            throw new RuntimeException('Could not start the isolated backup restore.');
        }
        stream_get_contents($restorePipes[1]);
        fclose($restorePipes[1]);
        stream_get_contents($restorePipes[2]);
        fclose($restorePipes[2]);
        if (proc_close($restoreProcess) !== 0) {
            throw new RuntimeException('The isolated backup clone could not be restored.');
        }
    } finally {
        if (is_file($temporaryDump)) {
            unlink($temporaryDump);
        }
    }

    $tableQuery = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    $tableQuery->execute([$source]);
    $sourceTables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
    $tableQuery->execute([$target]);
    $targetTables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
    if ($sourceTables !== $targetTables) {
        throw new RuntimeException('The restored clone has a different table inventory.');
    }
    $quoteIdentifier = static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`';
    foreach ($sourceTables as $table) {
        $sourceCount = (int) $pdo->query('SELECT COUNT(*) FROM ' . $quoteIdentifier($source) . '.' . $quoteIdentifier((string) $table))->fetchColumn();
        $targetCount = (int) $pdo->query('SELECT COUNT(*) FROM ' . $quoteIdentifier($target) . '.' . $quoteIdentifier((string) $table))->fetchColumn();
        if ($sourceCount !== $targetCount) {
            throw new RuntimeException('A restored table row count did not match its source.');
        }
    }
    $users = (int) $pdo->query('SELECT COUNT(*) FROM `' . $target . '`.users')->fetchColumn();
    echo 'PROMA_QA_LOGIN_CLONE_READY tables=' . count($sourceTables) . ' users=' . $users . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'PROMA_QA_LOGIN_CLONE_FAILED ' . get_class($error) . "\n");
    exit(1);
}
