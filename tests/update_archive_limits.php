<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

$archivePath = tempnam(sys_get_temp_dir(), 'proma-zip-limit-');
if ($archivePath === false) {
    throw new RuntimeException('Cannot create archive fixture.');
}
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot open archive fixture.');
}
$zip->addFromString('proma-update.json', str_repeat(' ', 4194305));
$zip->addFromString('small.txt', str_repeat('x', 33));
$zip->close();

$readManifest = new ReflectionMethod(ScriptUpdateService::class, 'readManifest');

$zipContent = new ReflectionMethod(ScriptUpdateService::class, 'zipContent');


try {
    try {
        $readManifest->invoke(null, $archivePath, true);
        throw new RuntimeException('Oversize manifest was accepted.');
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'بیش از حد مجاز') === false) {
            throw $e;
        }
    }
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Cannot reopen archive fixture.');
    }
    try {
        try {
            $zipContent->invoke(null, $zip, 'small.txt', 32);
            throw new RuntimeException('Oversize entry was accepted.');
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), 'بیش از حد مجاز') === false) {
                throw $e;
            }
        }
    } finally {
        $zip->close();
    }
    echo "UPDATE_ARCHIVE_LIMITS_OK\n";
} finally {
    @unlink($archivePath);
}
