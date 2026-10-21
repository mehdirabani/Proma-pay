<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$app = 'C:/laragon/backup/proma-pay-v404-preupgrade-20261008/app';
$source = $root . '/dist/candidates/v4.0.4/updates/PromaPay-Update-v4.0.4-from-v4.0.2.zip';
$output = $root . '/dist/candidates/v4.0.4/updates/PromaPay-Update-v4.0.4-local-v4.0.2.zip';
if (is_file($output)) throw new RuntimeException('Local candidate is immutable');
$zip = new ZipArchive(); if ($zip->open($source) !== true) throw new RuntimeException('Missing exact 402 package');
$manifest = json_decode($zip->getFromName('proma-update.json'), true, 512, JSON_THROW_ON_ERROR);
$mismatches = [];
foreach ($manifest['files'] as &$file) {
    $path = $file['target'] ?? $file['source'];
    $actual = is_file($app . '/' . $path) ? hash_file('sha256', $app . '/' . $path) : null;
    if ($actual !== ($file['expected_previous_sha256'] ?? null)) {
        $mismatches[] = $path;
        if ($path !== 'database/proma-pay-install.sql') throw new RuntimeException('Unreviewed local drift: ' . $path);
        $file['expected_previous_sha256'] = $actual;
    }
}
unset($file);
if ($mismatches !== ['database/proma-pay-install.sql']) throw new RuntimeException('Local baseline differs from reviewed drift inventory');
$manifest['local_baseline_note'] = 'Exact local V4.0.2 checksum for reviewed installer SQL drift; runtime/database credentials and user data excluded.';
$out = new ZipArchive(); if ($out->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create local update');
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === 'proma-update.json') continue;
    $out->addFromString($name, $zip->getFromIndex($i));
}
$out->addFromString('proma-update.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$out->close(); $zip->close();
echo "LOCAL_402_DRIFT_INVENTORY_OK_INSTALLER_SQL_ONLY\n";
echo 'LOCAL_UPDATE_SHA256=' . hash_file('sha256', $output) . "\n";
