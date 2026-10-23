<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read API gateway source: ' . $path);
    }
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$gateway = $read('helpers/MerchantApiGateway.php');
$repository = $read('models/MerchantApiGatewayRepository.php');
$migration = $read('database/migrations/2026_10_23_public_api_gateway_phase5.sql');
$installer = $read('database/proma-pay-install.sql');
$frontController = $read('api/v1/index.php');
$bootstrap = $read('bootstrap.php');
$htaccess = $read('.htaccess');

$assert(strpos($gateway, "'pmp_test_") !== false || strpos($gateway, 'pmp_test_') !== false, 'Gateway must authenticate test tokens only.');
$assert(strpos($gateway, 'https_required') !== false && strpos($gateway, 'isSecureTransport') !== false, 'TLS gate is missing.');
$assert(strpos($gateway, 'requireScope') !== false && strpos($repository, 'connection_id') !== false, 'Tenant scope and permission checks are missing.');
$assert(strpos($gateway, 'canonicalRequestHash') !== false && strpos($repository, 'merchant_api_idempotency') !== false, 'Request idempotency is not persisted.');
$assert(strpos($repository, 'merchant_api_rate_limits') !== false, 'Per-connection rate limiting is missing.');
$assert(strpos($repository, 'merchant_api_webhook_outbox') !== false && strpos($repository, 'INSERT IGNORE') !== false, 'Idempotent webhook outbox is missing.');
$assert(strpos($repository, "'pending_review'") !== false && strpos($repository, 'Contract::createWithInstallments') === false, 'API order requests must remain pending and must not create live contracts.');
$assert(strpos($gateway, 'external_customer_reference_hash') === false && strpos($repository, 'hash(\'sha256\', $externalCustomerReference)') !== false, 'External customer references must only be retained as a digest.');
$assert(strpos($frontController, 'PROMA_API_REQUEST') !== false && strpos($bootstrap, "!defined('PROMA_API_REQUEST')") !== false, 'API request must bypass the session-backed HTML login.');
$assert(strpos($htaccess, 'RewriteRule ^api/v1/') !== false, 'Versioned API rewrite rules are missing.');
foreach (['merchant_api_rate_limits','merchant_api_idempotency','merchant_api_order_requests','merchant_api_webhook_outbox'] as $table) {
    $assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) !== false, 'API migration is missing table: ' . $table);
    $assert(strpos($installer, 'CREATE TABLE IF NOT EXISTS ' . $table) !== false, 'Fresh installer is missing table: ' . $table);
}

echo "STATIC_MERCHANT_PUBLIC_API_PHASE5_OK\n";
