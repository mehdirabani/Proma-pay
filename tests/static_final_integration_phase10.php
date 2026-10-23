<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);
    if (!is_string($content)) throw new RuntimeException('Unable to read release evidence: ' . $relative);
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$apiIntegration = $read('tests/integration_merchant_public_api_phase5.php');
$worker = $read('tests/support/merchant_api_order_worker.php');
$wooStatic = $read('wordpress-plugins/proma-pay-woocommerce/tests/static.php');
$installer = $read('wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-installer.php');
$releaseGate = $read('tools/release-gate.php');
$releaseNotes = $read('docs/releases/PROMAPAY-WOOCOMMERCE-0.8.0-RC1.md');
$assert(strpos($apiIntegration, 'proc_open') !== false
    && strpos($apiIntegration, 'Idempotency-Key') !== false
    && strpos($apiIntegration, '203.0.113.5') !== false,
    'Concurrent API idempotency test is missing from the disposable integration suite.');
$assert(strpos($apiIntegration, 'connection/emergency-disable') !== false
    && strpos($apiIntegration, 'A second merchant read an order') !== false
    && strpos($worker, 'proma_qa_v204') !== false,
    'API disconnect, tenant-isolation, or destructive-test guard is missing.');
$assert(strpos($wooStatic, 'promapaywc_partial_refund_unsupported') !== false
    && strpos($wooStatic, 'depositOrder->is_paid()') !== false
    && strpos($wooStatic, "['bacs', 'cheque', 'cod']") !== false,
    'WooCommerce return/refund and online-payment safeguards are missing.');
$assert(strpos($installer, 'dbDelta') !== false
    && !preg_match('/\b(?:DROP|TRUNCATE)\s+TABLE\b/i', $installer)
    && strpos($installer, 'maybe_upgrade') !== false,
    'Plugin upgrade path is not additive.');
$assert(strpos($releaseGate, 'tests/woocommerce_release_package_phase10.php') !== false
    && strpos($releaseGate, 'tests/static_final_integration_phase10.php') !== false
    && strpos($releaseGate, 'tests/integration_merchant_public_api_phase5.php') !== false,
    'Final release evidence is not wired into the release gate.');
$assert(strpos($releaseNotes, 'Production Ready نیست') !== false
    && strpos($releaseNotes, 'بانکی زنده') !== false,
    'Prerelease notes omit production and live-bank limitations.');

echo "STATIC_FINAL_INTEGRATION_PHASE10_OK\n";
