<?php
declare(strict_types=1);
$app='C:/laragon/www/proma-qa-backup-v405-20261008';
$_SERVER['REQUEST_METHOD']='CLI';$_SERVER['REMOTE_ADDR']='198.51.100.49';require $app.'/bootstrap.php';
if(Model::db()->query('SELECT DATABASE()')->fetchColumn()!=='proma_qa_backup_v405_20261008') throw new RuntimeException('Owned recovery QA database required');
$before=RestoreIntegrityService::capture();
$migration=$app.'/database/migrations/2026_10_08_customer_password_mode.sql';$original=file_get_contents($migration);
$rejected=false;
try {
    file_put_contents($migration,$original."\nUPDATE users SET full_name='QA deliberately changed imported value' WHERE id=(SELECT target_id FROM (SELECT MIN(id) AS target_id FROM users) AS owned_test);\n");
    try { (new ReflectionMethod(BackupService::class,'restoreZipBackup'))->invoke(null,'C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-08-09-42-v405-converted-0ddc3955.zip','qa-integrity-failure.zip'); }
    catch(RuntimeException $error) { $rejected=str_contains($error->getMessage(),'تطبیق داده')&&str_contains($error->getMessage(),'بازگردانده شدند'); }
} finally { file_put_contents($migration,$original); }
if(!$rejected) throw new RuntimeException('Migration data loss was not rejected and recovered');
RestoreIntegrityService::assertPreserved($before);
if(is_file($app.'/storage/.runtime-lock/restore-pending.json')) throw new RuntimeException('Recovered app remains in maintenance');
echo "MIGRATION_VALUE_LOSS_V405_REJECTED_AND_ALL_PREEXISTING_VALUES_RECOVERED\n";
