<?php

declare(strict_types=1);

if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive extension is required.');
}

$pluginRoot = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($pluginRoot . '/plugin.json'), true);
$version = (string) ($manifest['version'] ?? '0.0.0');
$projectRoot = dirname($pluginRoot, 2);
$output = $projectRoot . '/dist/plugins/PromaAccounting-v' . $version . '.zip';

if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0775, true) && !is_dir(dirname($output))) {
    throw new RuntimeException('Cannot create plugin output directory.');
}
if (is_file($output) && !unlink($output)) {
    throw new RuntimeException('Cannot replace existing plugin package.');
}

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create plugin ZIP.');
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $item) {
    if (!$item->isFile() || $item->isLink()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($pluginRoot) + 1));
    if (preg_match('/\.(zip|log|bak|tmp)$/i', $relative) || strpos($relative, 'tests/fixtures/') === 0) {
        continue;
    }
    $zip->addFile($item->getPathname(), 'PromaAccounting/' . $relative);
}
$zip->close();
file_put_contents($output . '.sha256', hash_file('sha256', $output) . '  ' . basename($output) . PHP_EOL);

echo json_encode(['plugin' => $manifest['id'] ?? '', 'version' => $version, 'path' => $output, 'sha256' => hash_file('sha256', $output)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
