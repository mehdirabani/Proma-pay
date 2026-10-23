<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

class BackupService
{
    public static $created = 0;

    public static function create($reason = '')
    {
        self::$created++;
        return ['name' => 'qa-stub-' . $reason . '.zip'];
    }

    public static function log(...$args)
    {
    }
}

function app_version()
{
    return '7.0.4';
}

$root = dirname(__DIR__);
$fixtureId = getmypid();
$relativePath = 'laravel/app/.retired-update-finalize-' . $fixtureId . '.php';
$targetPath = $root . '/' . $relativePath;
$packagePaths = [];
$installedContent = 'customer customization';
$expectedContent = 'baseline retired source';
$coreSource = 'helpers/RetiredCoreFiles.php';
$coreHash = strtolower(hash_file('sha256', $root . '/' . $coreSource));

$createPackage = static function (string $suffix, string $expectedRetiredHash) use ($root, $coreSource, $coreHash, &$packagePaths): string {
    $packageName = 'proma-update-' . date('Y-m-d-H-i-s') . '-retired-finalize-' . $suffix . '-' . getmypid() . '.zip';
    $packagePath = ScriptUpdateService::baseDir() . DIRECTORY_SEPARATOR . $packageName;
    $zip = new ZipArchive();
    if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create QA retired-file finalization package.');
    }
    $zip->addFile($root . '/' . $coreSource, $coreSource);
    $zip->addFromString('proma-update.json', json_encode([
        'app' => 'proma-pay',
        'version' => '7.0.4',
        'files' => [[
            'source' => $coreSource,
            'target' => $coreSource,
            'sha256' => $coreHash,
        ]],
        'retired_files' => [[
            'path' => 'laravel/app/.retired-update-finalize-' . getmypid() . '.php',
            'expected_previous_sha256' => $expectedRetiredHash,
        ]],
    ], JSON_UNESCAPED_SLASHES));
    if (!$zip->close()) {
        throw new RuntimeException('Cannot finish QA retired-file finalization package.');
    }
    $packagePaths[] = $packagePath;
    return $packageName;
};

try {
    mkdir(dirname($targetPath), 0755, true);
    file_put_contents($targetPath, $installedContent);

    $mismatchPackage = $createPackage('mismatch', hash('sha256', $expectedContent));
    try {
        ScriptUpdateService::finalizeRetiredFilesFromInstalledPackage($mismatchPackage);
        throw new RuntimeException('Expected a customized retired file to be rejected.');
    } catch (RuntimeException $error) {
        if (strpos($error->getMessage(), 'نسخه فایل بازنشسته') === false) {
            throw $error;
        }
    }
    if (!is_file($targetPath) || file_get_contents($targetPath) !== $installedContent) {
        throw new RuntimeException('A customized retired file changed after the checksum mismatch.');
    }

    $validPackage = $createPackage('valid', hash('sha256', $installedContent));
    $result = ScriptUpdateService::finalizeRetiredFilesFromInstalledPackage($validPackage);
    if (is_file($targetPath) || count($result['retired_files'] ?? []) !== 1 || BackupService::$created !== 1) {
        throw new RuntimeException('The controlled retired-file finalization did not complete as expected.');
    }

    echo "UPDATE_RETIRED_FILE_FINALIZE_OK\n";
} finally {
    @unlink($targetPath);
    foreach ($packagePaths as $packagePath) {
        @unlink($packagePath);
    }
    foreach ([dirname($targetPath), dirname(dirname($targetPath))] as $directory) {
        if (is_dir($directory) && !is_link($directory)) {
            $items = scandir($directory);
            if (is_array($items) && count($items) === 2) {
                @rmdir($directory);
            }
        }
    }
}
