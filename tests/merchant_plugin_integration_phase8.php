<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/MerchantApiCredentialToken.php';
require_once dirname(__DIR__) . '/helpers/MerchantAccessContext.php';
require_once dirname(__DIR__) . '/helpers/MerchantApiPluginIntegration.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $expected): void {
    try {
        $callback();
    } catch (DomainException $error) {
        if ($error->getMessage() === $expected) return;
        throw $error;
    }
    throw new RuntimeException('Expected DomainException: ' . $expected);
};

$context = new MerchantAccessContext(17, 29, 43, ['order:create']);
$order = [
    'order_uuid' => 'a9b0d857-3b5d-43a2-9f0b-00d39eea9c01',
    'external_order_id' => 'WC-private-5821',
    'status' => 'pending_review',
    'contract_request_status' => 'not_requested',
];
$event = MerchantApiPluginIntegration::buildPayload('merchant_api.order.pending_review', $context, $order);
$json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$assert(($event['schema_version'] ?? null) === 1
    && ($event['source_channel'] ?? '') === 'woocommerce'
    && ($event['merchant_id'] ?? 0) === 17
    && ($event['site_id'] ?? 0) === 29
    && ($event['connection_id'] ?? 0) === 43
    && ($event['external_order_id_sha256'] ?? '') === hash('sha256', 'WC-private-5821')
    && ($event['financial_posting_permitted'] ?? true) === false,
    'The lifecycle event must keep exact tenant identity and disallow financial posting.');
$assert(strpos($json, 'WC-private-5821') === false
    && strpos($json, 'external_customer_reference') === false
    && strpos($json, 'cash_price') === false
    && strpos($json, 'down_payment') === false,
    'Lifecycle events must not expose raw external IDs, customer references, or amounts.');

$contractEvent = MerchantApiPluginIntegration::buildPayload(
    'merchant_api.contract_request.pending_review',
    $context,
    array_replace($order, ['contract_request_status' => 'pending_review']),
    ['contract_request_uuid' => '9e1619a8-f32a-463d-bf78-9f7c84cf0a44']
);
$assert(isset($contractEvent['contract_request_uuid']), 'Contract request event is missing its opaque internal request UUID.');
$cancelEvent = MerchantApiPluginIntegration::buildPayload(
    'merchant_api.order.cancelled',
    $context,
    array_replace($order, ['status' => 'cancelled']),
    ['reason_code' => 'payment_failed']
);
$assert(($cancelEvent['order_status'] ?? '') === 'cancelled'
    && ($cancelEvent['financial_posting_permitted'] ?? true) === false,
    'Cancelled order event did not preserve the safe state contract.');

$throws(
    static fn() => MerchantApiPluginIntegration::buildPayload('contract.created', $context, $order),
    'merchant_api_plugin_event_not_supported'
);
$throws(
    static fn() => MerchantApiPluginIntegration::buildPayload('merchant_api.order.pending_review', $context, $order + ['merchant_id' => 18]),
    'merchant_api_plugin_event_scope_mismatch'
);
$throws(
    static fn() => MerchantApiPluginIntegration::buildPayload('merchant_api.contract_request.pending_review', $context, array_replace($order, ['contract_request_status' => 'pending_review'])),
    'merchant_api_plugin_event_payload_invalid'
);

echo "MERCHANT_PLUGIN_INTEGRATION_PHASE8_OK\n";
