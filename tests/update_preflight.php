<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/ScriptUpdateService.php';

$root = dirname(__DIR__);
$archivePath = tempnam(sys_get_temp_dir(), 'proma-preflight-');
if ($archivePath === false) {
    throw new RuntimeException('Cannot create preflight fixture.');
}
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot open preflight fixture.');
}
$version = (string) file_get_contents($root . '/config/version.php');
$readme = (string) file_get_contents($root . '/README.md');
$zip->addFromString('config/version.php', $version);
$zip->addFromString('README.md', $readme);
$largePayload = str_repeat('x', 3 * 1024 * 1024);
$zip->addFromString('payload/large.bin', $largePayload);
$zip->addFromString('database/migrations/qa.sql', 'SELECT 1;');
$zip->addFromString('database/migrations/bad.sql', 'DROP TABLE users;');
$zip->close();

$preflight = new ReflectionMethod(ScriptUpdateService::class, 'preflightPackage');

$safePath = new ReflectionMethod(ScriptUpdateService::class, 'safePackagePath');

$snapshotTarget = new ReflectionMethod(ScriptUpdateService::class, 'snapshotUpdateTarget');

$restoreTargets = new ReflectionMethod(ScriptUpdateService::class, 'restoreUpdateTargets');

$discardSnapshots = new ReflectionMethod(ScriptUpdateService::class, 'discardUpdateSnapshots');
$writeFromZip = new ReflectionMethod(ScriptUpdateService::class, 'writeRootFileFromZip');

$files = [
    ['source' => 'config/version.php', 'target' => 'config/version.php', 'sha256' => hash('sha256', $version), 'expected_previous_sha256' => hash('sha256', $version)],
    ['source' => 'README.md', 'target' => 'README.md', 'sha256' => hash('sha256', $readme), 'expected_previous_sha256' => hash('sha256', $readme)],
    ['source' => 'payload/large.bin', 'target' => 'tests/.update-streaming-probe-' . getmypid(), 'sha256' => hash('sha256', $largePayload)],
];
$expectFailure = static function (array $candidateFiles, array $migrations, string $label) use ($preflight, $archivePath): void {
    try {
        $preflight->invoke(null, $archivePath, $candidateFiles, $migrations);
    } catch (RuntimeException $e) {
        return;
    }
    throw new RuntimeException('Preflight accepted ' . $label);
};

try {
    $preflight->invoke(null, $archivePath, $files, ['database/migrations/qa.sql']);
    $streamingTarget = 'tests/.update-streaming-probe-' . getmypid();
    $streamingTargetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $streamingTarget);
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Cannot reopen preflight fixture for streaming write.');
    }
    try {
        $expectedHash = hash('sha256', $largePayload);
        $writtenHash = $writeFromZip->invoke(null, $zip, 'payload/large.bin', $streamingTarget, $expectedHash);
        if ($writtenHash !== $expectedHash || hash_file('sha256', $streamingTargetPath) !== $expectedHash) {
            throw new RuntimeException('Streaming updater write did not preserve the ZIP member.');
        }
        try {
            $writeFromZip->invoke(null, $zip, 'payload/large.bin', $streamingTarget, str_repeat('0', 64));
            throw new RuntimeException('Streaming updater accepted a wrong SHA-256.');
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), 'accepted a wrong SHA-256') !== false) throw $e;
        }
        if (hash_file('sha256', $streamingTargetPath) !== $expectedHash) {
            throw new RuntimeException('Failed streaming verification replaced the original target.');
        }
    } finally {
        $zip->close();
        @unlink($streamingTargetPath);
    }
    $wrongPrevious = $files;
    $wrongPrevious[1]['expected_previous_sha256'] = str_repeat('0', 64);
    $expectFailure($wrongPrevious, [], 'an incompatible later file');
    $duplicateTarget = $files;
    $duplicateTarget[1]['target'] = 'config/version.php';
    $expectFailure($duplicateTarget, [], 'a duplicate target');
    $expectFailure($files, ['database/migrations/missing.sql'], 'a missing migration');
    $expectFailure($files, ['database/migrations/bad.sql'], 'a destructive migration');
    foreach (['..', 'x/../y', 'x//y', './x', 'x:stream', '/absolute', 'trailing/', 'CON.php', 'x.', 'x ', 'x?y'] as $invalidPath) {
        try {
            $safePath->invoke(null, $invalidPath);
            throw new RuntimeException('Unsafe package path accepted: ' . $invalidPath);
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), 'Unsafe package path accepted:') === 0) {
                throw $e;
            }
        }
    }
    $existing = tempnam(sys_get_temp_dir(), 'proma-existing-');
    $new = tempnam(sys_get_temp_dir(), 'proma-new-');
    if ($existing === false || $new === false) {
        throw new RuntimeException('Cannot create rollback fixtures.');
    }
    unlink($new);
    file_put_contents($existing, 'before');
    $originals = [$snapshotTarget->invoke(null, $existing), $snapshotTarget->invoke(null, $new)];
    try {
        file_put_contents($existing, 'after');
        file_put_contents($new, 'created');
        $errors = $restoreTargets->invoke(null, $originals);
        if ($errors || file_get_contents($existing) !== 'before' || file_exists($new)) {
            throw new RuntimeException('Update file rollback did not restore original state.');
        }
    } finally {
        $discardSnapshots->invoke(null, $originals);
        @unlink($existing);
        @unlink($new);
    }
    if (hash_file('sha256', $root . '/config/version.php') !== hash('sha256', $version)
        || hash_file('sha256', $root . '/README.md') !== hash('sha256', $readme)) {
        throw new RuntimeException('Preflight changed an application file.');
    }
    echo "UPDATE_PREFLIGHT_OK\n";
} finally {
    unlink($archivePath);
}
