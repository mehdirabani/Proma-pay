<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginRoot = realpath($root . '/plugins/wordpress-plugins/proma-pay-woocommerce');
if ($pluginRoot === false) {
    throw new RuntimeException('WooCommerce plugin source directory is missing.');
}
$mainFile = $pluginRoot . '/proma-pay-woocommerce.php';
$mainSource = file_get_contents($mainFile);
if (!is_string($mainSource)
    || !preg_match('/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+-rc\.[0-9]+)\s*$/m', $mainSource, $versionMatch)) {
    throw new RuntimeException('Only a versioned WooCommerce release candidate can be packaged.');
}
$version = $versionMatch[1];
$relativeFiles = ['proma-pay-woocommerce.php', 'readme.txt'];
foreach (['includes', 'assets'] as $directory) {
    $base = $pluginRoot . DIRECTORY_SEPARATOR . $directory;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile() || $entry->isLink()) {
            continue;
        }
        $extension = strtolower($entry->getExtension());
        if (!in_array($extension, ['php','js','css','txt','json'], true)) {
            continue;
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($pluginRoot) + 1));
        if (str_starts_with($relative, '../') || str_contains('/' . $relative . '/', '/../')) {
            throw new RuntimeException('Unsafe plugin package path.');
        }
        $relativeFiles[] = $relative;
    }
}
$relativeFiles = array_values(array_unique($relativeFiles));
sort($relativeFiles, SORT_STRING);
$files = [];
foreach ($relativeFiles as $relative) {
    $path = $pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $real = realpath($path);
    $prefix = rtrim($pluginRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($real === false || !str_starts_with($real, $prefix) || !is_file($real)) {
        throw new RuntimeException('Plugin package file is missing or outside its source tree: ' . $relative);
    }
    $files[$relative] = hash_file('sha256', $real);
}
$manifest = [
    'product' => 'Proma Pay for WooCommerce',
    'version' => $version,
    'channel' => 'prerelease',
    'requires_php' => '8.3',
    'tested_wordpress' => '7.1.3',
    'tested_woocommerce' => '11.2.1',
    'files_sha256' => $files,
    'financial_mode' => 'test_credentials_only',
];
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$outputDirectory = $root . '/dist/plugins/pre-release/proma-pay-woocommerce';
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create plugin release output directory.');
}
$outputPath = $outputDirectory . '/PromaPay-WooCommerce-v' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create WooCommerce plugin package.');
}
foreach ($relativeFiles as $relative) {
    $zip->addFile($pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative), 'proma-pay-woocommerce/' . $relative);
}
$zip->addFromString('proma-pay-woocommerce/PROMAPAY-RELEASE-MANIFEST.json', $manifestJson);
$zip->close();

echo json_encode([
    'package' => str_replace('\\', '/', substr($outputPath, strlen($root) + 1)),
    'version' => $version,
    'files' => count($relativeFiles) + 1,
    'sha256' => hash_file('sha256', $outputPath),
    'size_bytes' => filesize($outputPath),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
