<?php

declare(strict_types=1);

/**
 * Publishes minimized merchant API lifecycle events through the core outbox.
 * Pending requests are deliberately not translated into financial or contract
 * events; those require an internally verified customer and active contract.
 */
final class MerchantApiPluginIntegration
{
    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD';

    public static function buildPayload(string $event, MerchantAccessContext $context, array $order, array $extra = []): array
    {
        $rules = [
            'merchant_api.order.pending_review' => ['pending_review', 'not_requested'],
            'merchant_api.contract_request.pending_review' => ['pending_review', 'pending_review'],
            'merchant_api.order.cancelled' => ['cancelled', null],
        ];
        if (!isset($rules[$event])) {
            throw new DomainException('merchant_api_plugin_event_not_supported');
        }

        $orderUuid = strtolower(trim((string) ($order['order_uuid'] ?? '')));
        $externalOrderId = trim((string) ($order['external_order_id'] ?? ''));
        $status = trim((string) ($order['status'] ?? ''));
        $contractStatus = trim((string) ($order['contract_request_status'] ?? ''));
        [$requiredStatus, $requiredContractStatus] = $rules[$event];
        if (!preg_match(self::UUID_PATTERN, $orderUuid)
            || $externalOrderId === ''
            || $status !== $requiredStatus
            || ($requiredContractStatus !== null && $contractStatus !== $requiredContractStatus)) {
            throw new DomainException('merchant_api_plugin_event_payload_invalid');
        }

        foreach (['merchant_id' => $context->merchantId(), 'site_id' => $context->siteId(), 'connection_id' => $context->connectionId()] as $key => $expected) {
            if (isset($order[$key]) && (int) $order[$key] !== $expected) {
                throw new DomainException('merchant_api_plugin_event_scope_mismatch');
            }
        }

        $payload = [
            'schema_version' => 1,
            'source_channel' => 'woocommerce',
            'order_uuid' => $orderUuid,
            'merchant_id' => $context->merchantId(),
            'site_id' => $context->siteId(),
            'connection_id' => $context->connectionId(),
            'external_order_id_sha256' => hash('sha256', $externalOrderId),
            'order_status' => $status,
            'contract_request_status' => $contractStatus,
            'financial_posting_permitted' => false,
        ];

        if ($event === 'merchant_api.contract_request.pending_review') {
            $contractRequestUuid = strtolower(trim((string) ($extra['contract_request_uuid'] ?? '')));
            if (!preg_match(self::UUID_PATTERN, $contractRequestUuid)) {
                throw new DomainException('merchant_api_plugin_event_payload_invalid');
            }
            $payload['contract_request_uuid'] = $contractRequestUuid;
        }
        if ($event === 'merchant_api.order.cancelled' && isset($extra['reason_code'])) {
            $reasonCode = trim((string) $extra['reason_code']);
            if (!preg_match('/\A[a-z][a-z0-9_]{1,63}\z/D', $reasonCode)) {
                throw new DomainException('merchant_api_plugin_event_payload_invalid');
            }
            $payload['reason_code'] = $reasonCode;
        }

        return $payload;
    }

    public static function enqueue(string $event, MerchantAccessContext $context, array $order, array $extra = []): ?int
    {
        if (!class_exists('SystemOutbox')) {
            return null;
        }
        $payload = self::buildPayload($event, $context, $order, $extra);
        $eventId = \SystemOutbox::safeEnqueuePluginHook($event, $payload, 'merchant_api_order_request');
        return $eventId ? (int) $eventId : null;
    }
}
