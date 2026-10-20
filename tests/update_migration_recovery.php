<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

class BackupService
{
    public static function create($reason = '')
    {
        return ['name' => 'qa-stub-backup.zip'];
    }

    public static function restoreUpdateDatabaseSnapshot($fileName)
    {
        throw new RuntimeException('QA_DATABASE_UNAVAILABLE');
    }
}

class Model
{
    public static function execute($sql, $params = [])
    {
        throw new RuntimeException('QA_DATABASE_UNAVAILABLE');
    }
}

function app_version()
{
    return '1.0.0';
}

$root = dirname(__DIR__);
$relativePath = 'tests/.update_migration_fixture_' . getmypid() . '.txt';
$targetPath = $root . '/' . $relativePath;
$packageName = 'proma-update-' . date('Y-m-d-H-i-s') . '-migration-qa-' . getmypid() . '.zip';
$packagePath = ScriptUpdateService::baseDir() . DIRECTORY_SEPARATOR . $packageName;
$snapshotPath = null;
file_put_contents($targetPath, 'original bytes');

$zip = new ZipArchive();
if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create QA update package.');
}
$zip->addFromString('payload.txt', 'replacement bytes');
$zip->addFromString('database/migrations/qa.sql', 'SELECT 1;');
$zip->addFromString('proma-update.json', json_encode([
    'app' => 'proma-pay',
    'version' => '2.0.0',
    'files' => [[
        'source' => 'payload.txt',
        'target' => $relativePath,
        'sha256' => hash('sha256', 'replacement bytes'),
        'expected_previous_sha256' => hash('sha256', 'original bytes'),
    ]],
    'migrations' => ['database/migrations/qa.sql'],
], JSON_UNESCAPED_SLASHES));
$zip->close();

try {
    try {
        ScriptUpdateService::install($packageName);
        throw new RuntimeException('Expected migration failure was not raised.');
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'QA_DATABASE_UNAVAILABLE') === false
            || strpos($e->getMessage(), 'qa-stub-backup.zip') === false
            || !preg_match('/نسخه‌های موقت فایل‌ها: (.+?)\. علت:/u', $e->getMessage(), $match)) {
            throw $e;
        }
        $snapshotPath = $match[1];
    }
    if (file_get_contents($targetPath) !== 'replacement bytes'
        || !is_file($snapshotPath)
        || file_get_contents($snapshotPath) !== 'original bytes') {
        throw new RuntimeException('Migration recovery state was not preserved.');
    }
    echo "UPDATE_MIGRATION_RECOVERY_OK\n";
} finally {
    @unlink($targetPath);
    @unlink($packagePath);
    if ($snapshotPath !== null) {
        @unlink($snapshotPath);
    }
}
