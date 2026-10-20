<?php
require dirname(__DIR__) . '/helpers/BackupService.php';

function backupManifestInvoke($method, array $arguments)
{
    $reflection = new ReflectionMethod(BackupService::class, $method);

    return $reflection->invokeArgs(null, $arguments);
}

$path = tempnam(sys_get_temp_dir(), 'proma-backup-manifest-');
if ($path === false) throw new RuntimeException('Could not create temporary backup fixture.');
$legacyPath = $path . '.legacy.zip';
$sql = "CREATE TABLE users (id INT);\nCREATE TABLE contracts (id INT);\nCREATE TABLE installments (id INT);\n";
$metadata = [
    'application_version' => '4.0.5',
    'schema_version' => 'sha256:test-schema',
    'created_at' => '2026-10-08T12:00:00+03:30',
    'database_engine' => 'MariaDB',
    'database_version' => '10.11.18-MariaDB',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'php_version' => PHP_VERSION,
    'required_modules' => ['json', 'mbstring', 'pdo_mysql'],
];

try {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create ZIP fixture.');
    $zip->addFromString('database.sql', $sql);
    $zip->addFromString('config-snapshot.json', '{}');
    if (!$zip->close()) throw new RuntimeException('Could not close ZIP fixture.');

    backupManifestInvoke('appendArchiveManifest', [$path, $metadata]);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Could not reopen manifested archive.');
    if (backupManifestInvoke('validateZip', [$zip]) !== null) {
        // validateZip is intentionally a guard with no return value.
    }
    if (backupManifestInvoke('validateArchiveManifest', [$zip]) !== true) throw new RuntimeException('New backup manifest was not recognized.');
    $zip->close();

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Could not edit manifested archive fixture.');
    $zip->deleteName('database.sql');
    $zip->addFromString('database.sql', $sql . "-- corrupted after manifest creation\n");
    $zip->close();
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Could not reopen corrupted archive.');
    $rejected = false;
    try { backupManifestInvoke('validateArchiveManifest', [$zip]); } catch (RuntimeException $e) { $rejected = strpos($e->getMessage(), 'checksum') !== false; }
    $zip->close();
    if (!$rejected) throw new RuntimeException('Modified backup content was not rejected by checksum validation.');

    $zip = new ZipArchive();
    if ($zip->open($legacyPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create legacy ZIP fixture.');
    $zip->addFromString('database.sql', $sql);
    $zip->close();
    $zip = new ZipArchive();
    if ($zip->open($legacyPath) !== true) throw new RuntimeException('Could not reopen legacy ZIP fixture.');
    if (backupManifestInvoke('validateArchiveManifest', [$zip]) !== false) throw new RuntimeException('Legacy V4 archive should remain readable without a manifest.');
    $zip->close();

    echo "BACKUP_MANIFEST_INTEGRITY_OK\n";
} finally {
    @unlink($path);
    @unlink($legacyPath);
}
