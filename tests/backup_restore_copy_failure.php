<?php

// Regression: a failed file write while restoring a ZIP must stop restoration
// instead of silently continuing to the database import.
if (!class_exists('ZipArchive')) {
    fwrite(STDOUT, "SKIP: ZipArchive is unavailable.\n");
    exit(0);
}

require dirname(__DIR__) . '/helpers/BackupService.php';

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proma-backup-copy-regression-' . bin2hex(random_bytes(8));
$archivePath = $base . '.zip';
$target = $base . '-target';
$successTarget = $base . '-success-target';

$removeTree = static function ($path) use (&$removeTree) {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
        return;
    }
    if (file_exists($path) || is_link($path)) {
        @unlink($path);
    }
};

try {
    if (!mkdir($target, 0700, true)) {
        throw new RuntimeException('Could not create isolated test directory.');
    }
    if (!mkdir($successTarget, 0700, true)) {
        throw new RuntimeException('Could not create isolated successful restore directory.');
    }
    if (!mkdir($target . DIRECTORY_SEPARATOR . 'blocked.txt', 0700)) {
        throw new RuntimeException('Could not create the simulated file-write conflict.');
    }

    $writer = new ZipArchive();
    if ($writer->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create isolated test archive.');
    }
    if (!$writer->addFromString('backup/uploads/.gitkeep', '')
        || !$writer->addFromString('backup/uploads/binary.bin', "synthetic\0upload\xFF")
        || !$writer->addFromString('backup/uploads/blocked.txt', 'synthetic upload')) {
        $writer->close();
        throw new RuntimeException('Could not write isolated test entry.');
    }
    $writer->close();

    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Could not reopen isolated test archive.');
    }

    $restoreFiles = new ReflectionMethod(BackupService::class, 'restoreFiles');

    $restoreFiles->invoke(null, $zip, $archivePath, 'backup/uploads/', $successTarget);
    if (!is_file($successTarget . DIRECTORY_SEPARATOR . '.gitkeep')
        || filesize($successTarget . DIRECTORY_SEPARATOR . '.gitkeep') !== 0
        || file_get_contents($successTarget . DIRECTORY_SEPARATOR . 'binary.bin') !== "synthetic\0upload\xFF") {
        throw new RuntimeException('Streaming restore did not preserve empty and binary ZIP entries.');
    }
    $copyFailureCaught = false;
    try {
        $restoreFiles->invoke(null, $zip, $archivePath, 'backup/uploads/', $target);
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'بازیابی فایل') === false) {
            throw $e;
        }
        $copyFailureCaught = true;
    } finally {
        $zip->close();
    }

    if (!$copyFailureCaught) {
        throw new RuntimeException('A failed ZIP file copy was not reported.');
    }
    echo "ZIP restore streaming and copy-failure regression passed.\n";
} finally {
    $removeTree($target);
    $removeTree($successTarget);
    $removeTree($archivePath);
}
