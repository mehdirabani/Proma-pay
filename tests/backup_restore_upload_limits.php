<?php
require dirname(__DIR__) . '/helpers/BackupService.php';

function backupRestoreAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function backupRestoreInvoke($method, array $arguments)
{
    $reflection = new ReflectionMethod(BackupService::class, $method);

    return $reflection->invokeArgs(null, $arguments);
}

backupRestoreAssert(backupRestoreInvoke('iniSizeBytes', ['160M']) === 167772160, '160M must be parsed as 160 MiB.');
backupRestoreAssert(backupRestoreInvoke('iniSizeBytes', ['1.5G']) === 1610612736, 'Fractional GiB values must be parsed.');
backupRestoreAssert(backupRestoreInvoke('iniSizeBytes', ['-1']) === null, 'Unlimited PHP limits must be represented as uncapped.');

$limits = backupRestoreInvoke('resolveRestoreUploadLimits', ['160M', '170M', 536870912]);
backupRestoreAssert($limits['max_bytes'] === 167772160, 'The upload limit must honor upload_max_filesize when it is the tightest cap.');
$limits = backupRestoreInvoke('resolveRestoreUploadLimits', ['200M', '100M', 536870912]);
backupRestoreAssert($limits['max_bytes'] === 104595456, 'The request limit must reserve room for multipart form data.');
$limits = backupRestoreInvoke('resolveRestoreUploadLimits', ['0', '-1', 536870912]);
backupRestoreAssert($limits['max_bytes'] === 536870912, 'Uncapped PHP settings must still respect the application hard ceiling.');

backupRestoreAssert(backupRestoreInvoke('maximumSqlDumpBytes', ['256M']) === 8589934592, 'A streamed SQL dump must use the disk safety ceiling, not the PHP memory limit.');
backupRestoreAssert(backupRestoreInvoke('maximumSqlDumpBytes', ['64M']) === 8589934592, 'The streamed SQL ceiling must remain independent of PHP memory_limit.');
backupRestoreAssert(backupRestoreInvoke('maximumSqlStatementBytes', ['128M']) === 16777216, 'A 128 MiB PHP memory limit must reserve room around each SQL statement.');
backupRestoreAssert(backupRestoreInvoke('requestPayloadExceedsPostLimit', [178257921, '170M', [], []]) === true, 'An oversized empty PHP request must be recognized as post_max_size truncation.');
backupRestoreAssert(backupRestoreInvoke('requestPayloadExceedsPostLimit', [178257920, '170M', [], []]) === false, 'A request at the configured post_max_size must not be reported as truncated.');
backupRestoreAssert(backupRestoreInvoke('requestPayloadExceedsPostLimit', [178257921, '170M', ['csrf' => 'present'], []]) === false, 'A parsed request must not be mistaken for post_max_size truncation.');

echo "BACKUP_RESTORE_UPLOAD_LIMITS_OK\n";
