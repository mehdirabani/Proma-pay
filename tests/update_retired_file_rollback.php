<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

class BackupService
{
    public static function create($reason = '')
    {
        return ['name' => 'qa-stub-before-update.zip'];
    }

    public static function restoreUpdateDatabaseSnapshot($fileName)
    {
        return true;
    }

    public static function log(...$args)
    {
    }
}

class MigrationFailureDatabase
{
    public function query($sql)
    {
        throw new RuntimeException('QA_FORCED_MIGRATION_FAILURE');
    }
}

class Model
{
    public static function execute($sql, $params = [])
    {
    }

    public static function fetch($sql, $params = [])
    {
        return null;
    }

    public static function begin()
    {
    }

    public static function commit()
    {
    }

    public static function rollBack()
    {
    }

    public static function db()
    {
        return new MigrationFailureDatabase();
    }
}

class Auth
{
    public static function id()
    {
        return 1;
    }
}

function app_version()
{
    return '1.0.0';
}

$root = dirname(__DIR__);
$fixtureId = getmypid();
$relativePath = 'laravel/app/.retired-update-rollback-' . $fixtureId . '.php';
$targetPath = $root . '/' . $relativePath;
$packageName = 'proma-update-' . date('Y-m-d-H-i-s') . '-retired-qa-' . $fixtureId . '.zip';
$packagePath = ScriptUpdateService::baseDir() . DIRECTORY_SEPARATOR . $packageName;
$originalContent = 'original retired bytes';

try {
    mkdir(dirname($targetPath), 0755, true);
    file_put_contents($targetPath, $originalContent);

    $zip = new ZipArchive();
    if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create QA retired-file update package.');
    }
    $zip->addFromString('database/migrations/qa-retired-rollback.sql', 'SELECT 1;');
    $zip->addFromString('proma-update.json', json_encode([
        'app' => 'proma-pay',
        'version' => '2.0.0',
        'migrations' => ['database/migrations/qa-retired-rollback.sql'],
        'retired_files' => [[
            'path' => $relativePath,
            'expected_previous_sha256' => hash('sha256', $originalContent),
        ]],
    ], JSON_UNESCAPED_SLASHES));
    $zip->close();

    try {
        ScriptUpdateService::install($packageName);
        throw new RuntimeException('Expected migration failure was not raised.');
    } catch (RuntimeException $error) {
        if (strpos($error->getMessage(), 'QA_FORCED_MIGRATION_FAILURE') === false) {
            throw $error;
        }
    }

    if (!is_file($targetPath) || file_get_contents($targetPath) !== $originalContent) {
        throw new RuntimeException('Retired file was not restored after migration rollback.');
    }
    echo "UPDATE_RETIRED_FILE_ROLLBACK_OK\n";
} finally {
    @unlink($targetPath);
    @unlink($packagePath);
    foreach ([dirname($targetPath), dirname(dirname($targetPath))] as $directory) {
        if (is_dir($directory) && !is_link($directory)) {
            $items = scandir($directory);
            if (is_array($items) && count($items) === 2) {
                @rmdir($directory);
            }
        }
    }
}
