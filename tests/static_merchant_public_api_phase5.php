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
$quoteMigration = $read('database/migrations/2026_10_23_checkout_quote_snapshot_phase7.sql');
$pluginIntegration = $read('helpers/MerchantApiPluginIntegration.php');
$installer = $read('database/proma-pay-install.sql');
$frontController = $read('api/v1/index.php');
$bootstrap = $read('bootstrap.php');
$htaccess = $read('.htaccess');

$assert(strpos($gateway, "'pmp_test_") !== false || strpos($gateway, 'pmp_test_') !== false, 'Gateway must authenticate test tokens only.');
$assert(strpos($gateway, 'https_required') !== false && strpos($gateway, 'isSecureTransport') !== false, 'TLS gate is missing.');
$assert(strpos($gateway, 'requireScope') !== false && strpos($repository, 'connection_id') !== false, 'Tenant scope and permission checks are missing.');
$assert(strpos($gateway, "'/policies/financial'") !== false && strpos($gateway, "'policy:read'") !== false, 'Tenant-scoped financial policy read route is missing.');
$assert(strpos($gateway, 'publicFinancialPolicy') !== false && strpos($gateway, 'source_policy_sha256') !== false, 'Public financial policy response must be minimized and versioned.');
$assert(strpos($gateway, 'canonicalRequestHash') !== false && strpos($repository, 'merchant_api_idempotency') !== false, 'Request idempotency is not persisted.');
$assert(strpos($repository, 'merchant_api_rate_limits') !== false, 'Per-connection rate limiting is missing.');
$assert(strpos($repository, 'merchant_api_webhook_outbox') !== false && strpos($repository, 'INSERT IGNORE') !== false, 'Idempotent webhook outbox is missing.');
$assert(strpos($repository, 'issueQuote') !== false && strpos($repository, 'consumeQuote') !== false && strpos($repository, 'quote_uuid') !== false, 'Financial quotes are not persisted and consumed by tenant-scoped checkout orders.');
$assert(strpos($gateway, 'MerchantApiGatewayRepository::issueQuote') !== false && strpos($gateway, "'quote_uuid'") !== false, 'API order creation does not use an issued financial quote snapshot.');
$assert(strpos($repository, "'pending_review'") !== false && strpos($repository, 'Contract::createWithInstallments') === false, 'API order requests must remain pending and must not create live contracts.');
$assert(strpos($gateway, 'external_customer_reference_hash') === false && strpos($repository, 'hash(\'sha256\', $externalCustomerReference)') !== false, 'External customer references must only be retained as a digest.');
$assert(strpos($frontController, 'PROMA_API_REQUEST') !== false && strpos($bootstrap, "!defined('PROMA_API_REQUEST')") !== false, 'API request must bypass the session-backed HTML login.');
$assert(strpos($htaccess, 'RewriteRule ^api/v1/') !== false, 'Versioned API rewrite rules are missing.');
foreach (['merchant_api_rate_limits','merchant_api_idempotency','merchant_api_order_requests','merchant_api_webhook_outbox'] as $table) {
    $assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) !== false, 'API migration is missing table: ' . $table);
    $assert(strpos($installer, 'CREATE TABLE IF NOT EXISTS ' . $table) !== false, 'Fresh installer is missing table: ' . $table);
}
$assert(strpos($quoteMigration, 'CREATE TABLE IF NOT EXISTS merchant_api_quotes') !== false
    && strpos($quoteMigration, 'consumed_order_uuid') !== false
    && strpos($quoteMigration, 'REFERENCES merchant_connections (id,merchant_id,site_id)') !== false
    && strpos($quoteMigration, 'REFERENCES merchant_sites (id,merchant_id)') !== false,
    'Additive quote snapshot migration or its tenant scope is missing.');
$assert(strpos($installer, 'CREATE TABLE IF NOT EXISTS merchant_api_quotes') !== false,
    'Fresh installation is missing the checkout quote snapshot table.');
$assert(strpos($pluginIntegration, 'SystemOutbox::safeEnqueuePluginHook') !== false
    && strpos($pluginIntegration, "'merchant_api.order.pending_review'") !== false
    && strpos($pluginIntegration, "'merchant_api.contract_request.pending_review'") !== false
    && strpos($pluginIntegration, "'merchant_api.order.cancelled'") !== false,
    'Merchant API lifecycle events are not routed through the core plugin outbox.');
$assert(strpos($pluginIntegration, "'financial_posting_permitted' => false") !== false
    && strpos($pluginIntegration, "'external_order_id_sha256'") !== false
    && strpos($pluginIntegration, "'external_customer_reference'") === false,
    'Plugin lifecycle payloads must explicitly prohibit financial posting and minimize identity data.');

echo "STATIC_MERCHANT_PUBLIC_API_PHASE5_OK\n";
