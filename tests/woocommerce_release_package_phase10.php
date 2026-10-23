<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginRoot = $root . '/wordpress-plugins/proma-pay-woocommerce';
$main = (string) file_get_contents($pluginRoot . '/proma-pay-woocommerce.php');
if (!preg_match('/^\s*\*\s*Version:\s*(0\.8\.0-rc\.1)\s*$/m', $main, $versionMatch)) {
    throw new RuntimeException('Expected the documented WooCommerce release-candidate version.');
}
$version = $versionMatch[1];
$packagePath = $root . '/dist/plugins/PromaPay-WooCommerce-v' . $version . '.zip';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assert(class_exists(ZipArchive::class) && is_file($packagePath), 'Installable WooCommerce release archive is missing.');
$zip = new ZipArchive();
$assert($zip->open($packagePath) === true, 'Installable plugin archive is not a readable ZIP.');
$names = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = (string) $zip->getNameIndex($index);
    $normalized = str_replace('\\', '/', $name);
    $assert($name === $normalized && !str_starts_with($name, '/') && !preg_match('#(?:\A|/)\.\.(?:/|\z)#', $name), 'Plugin archive contains an unsafe file path.');
    $assert(str_starts_with($name, 'proma-pay-woocommerce/'), 'Plugin archive has an unexpected top-level path.');
    $assert(!str_contains($name, '/tests/'), 'Test code was packaged for installation.');
    $assert(!preg_match('/(?:^|\/)(?:\.env(?:\.|$)|[^\/]+\.(?:pem|key|p12|pfx|log))$/i', $name), 'Potential credential or runtime file was packaged.');
    $assert(!isset($names[$name]), 'Plugin archive has duplicate paths.');
    $names[$name] = true;
}
$manifestRaw = $zip->getFromName('proma-pay-woocommerce/PROMAPAY-RELEASE-MANIFEST.json');
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true, 64, JSON_THROW_ON_ERROR) : null;
$assert(is_array($manifest)
    && ($manifest['version'] ?? '') === $version
    && ($manifest['channel'] ?? '') === 'prerelease'
    && ($manifest['financial_mode'] ?? '') === 'test_credentials_only'
    && is_array($manifest['files_sha256'] ?? null),
    'Release manifest is missing its test-only release metadata.');
foreach ($manifest['files_sha256'] as $relative => $expectedHash) {
    $archivePath = 'proma-pay-woocommerce/' . $relative;
    $content = $zip->getFromName($archivePath);
    $source = $pluginRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $assert(is_string($content) && is_file($source)
        && hash_equals((string) $expectedHash, hash('sha256', $content))
        && hash_equals((string) $expectedHash, hash_file('sha256', $source)),
        'Release archive content does not match its manifest/source: ' . $relative);
}
$assert(isset($names['proma-pay-woocommerce/proma-pay-woocommerce.php'])
    && isset($names['proma-pay-woocommerce/readme.txt'])
    && isset($names['proma-pay-woocommerce/assets/js/checkout-blocks.js']),
    'Core installer, readme, or Checkout Blocks asset is missing from the plugin ZIP.');
$zip->close();

echo "WOOCOMMERCE_RELEASE_PACKAGE_PHASE10_OK\n";
