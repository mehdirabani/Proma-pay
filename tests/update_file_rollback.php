<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

class BackupService
{
    public static $blockerPath;

    public static function create($reason = '')
    {
        // Simulate the target directory becoming unusable after preflight.
        file_put_contents(self::$blockerPath, 'blocker');
        return ['name' => 'qa-stub-backup.zip'];
    }
}

function app_version()
{
    return '1.0.0';
}

$root = dirname(__DIR__);
$fixtureName = '.update_rollback_fixture_' . getmypid() . '.txt';
$relativePath = 'tests/' . $fixtureName;
$targetPath = $root . '/tests/' . $fixtureName;
$newRelativePath = 'tests/.update_rollback_new_' . getmypid() . '.txt';
$newTargetPath = $root . '/' . $newRelativePath;
$blockerRelativePath = 'tests/.update_rollback_blocker_' . getmypid();
$blockerPath = $root . '/' . $blockerRelativePath;
BackupService::$blockerPath = $blockerPath;
$packageName = 'proma-update-' . date('Y-m-d-H-i-s') . '-rollback-qa-' . getmypid() . '.zip';
$packagePath = ScriptUpdateService::baseDir() . DIRECTORY_SEPARATOR . $packageName;
file_put_contents($targetPath, 'original bytes');

$zip = new ZipArchive();
if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create QA update package.');
}
$zip->addFromString('payload.txt', 'replacement bytes');
$zip->addFromString('new-payload.txt', 'new file bytes');
$zip->addFromString('blocked-payload.txt', 'never installed');
$zip->addFromString('proma-update.json', json_encode([
    'app' => 'proma-pay',
    'version' => '2.0.0',
    'files' => [
        [
            'source' => 'payload.txt',
            'target' => $relativePath,
            'sha256' => hash('sha256', 'replacement bytes'),
            'expected_previous_sha256' => hash('sha256', 'original bytes'),
        ],
        [
            'source' => 'new-payload.txt',
            'target' => $newRelativePath,
            'sha256' => hash('sha256', 'new file bytes'),
        ],
        [
            'source' => 'blocked-payload.txt',
            'target' => $blockerRelativePath . '/child.txt',
            'sha256' => hash('sha256', 'never installed'),
        ],
    ],
], JSON_UNESCAPED_SLASHES));
$zip->close();

try {
    try {
        ScriptUpdateService::install($packageName);
        throw new RuntimeException('Expected file installation failure was not raised.');
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'مسیر مقصد بروزرسانی معتبر نیست') === false) {
            throw $e;
        }
    }
    if (file_get_contents($targetPath) !== 'original bytes' || file_exists($newTargetPath)) {
        throw new RuntimeException('Failed installation did not restore both file targets.');
    }
    echo "UPDATE_FILE_ROLLBACK_OK\n";
} finally {
    @unlink($targetPath);
    @unlink($newTargetPath);
    @unlink($blockerPath);
    @unlink($packagePath);
}
