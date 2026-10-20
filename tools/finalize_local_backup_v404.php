<?php
declare(strict_types=1);
$app = 'C:/laragon/www/proma';
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.49'; require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== 'proma_pay_restore_20261007' || (require $app . '/config/version.php')['application'] !== '4.0.4') throw new RuntimeException('Unexpected live baseline');
$oldArchive = dirname(__DIR__) . '/dist/candidates/archive/v4.0.4-before-mysqldump-comment-compat-20261008/core/PromaPay-v4.0.4.zip';
$old = new ZipArchive(); if ($old->open($oldArchive) !== true) throw new RuntimeException('Missing previous verified 404 core');
$oldBytes = $old->getFromName('helpers/BackupService.php'); $old->close();
$path = $app . '/helpers/BackupService.php';
if (!hash_equals(hash('sha256', $oldBytes), hash_file('sha256', $path))) throw new RuntimeException('Unexpected live BackupService drift');
$new = new ZipArchive(); if ($new->open(dirname(__DIR__) . '/dist/candidates/v4.0.4/core/PromaPay-v4.0.4.zip') !== true) throw new RuntimeException('Missing final 404 core');
$bytes = $new->getFromName('helpers/BackupService.php'); $new->close();
if (!hash_equals(hash_file('sha256', dirname(__DIR__) . '/helpers/BackupService.php'), hash('sha256', $bytes))) throw new RuntimeException('Final source/archive mismatch');
RequestMaintenanceLock::exclusive();
try {
    $backup = BackupService::create('before-v404-sql-compat');
    $previous = $app . '/storage/.runtime-lock/BackupService.before-v404-sql-compat.php';
    if (file_exists($previous) || !copy($path, $previous)) throw new RuntimeException('Cannot preserve previous helper');
    $temporary = $app . '/storage/.runtime-lock/BackupService.v404-new.php';
    if (file_put_contents($temporary, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete helper write');
    if (!rename($temporary, $path)) throw new RuntimeException('Cannot replace final helper');
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) throw new RuntimeException('Final helper verification failed');
    file_put_contents(dirname(__DIR__) . '/storage/qa-v404-final-local-backup-result.json', json_encode(['version' => '4.0.4', 'matches_final_core' => true, 'safety_backup' => $backup['name']], JSON_PRETTY_PRINT));
    echo "LOCAL_V404_FINAL_SQL_COMPAT_HELPER_APPLIED_AND_VERIFIED\n";
} finally { RequestMaintenanceLock::shared(); }
