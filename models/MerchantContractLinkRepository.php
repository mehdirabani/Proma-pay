<?php

declare(strict_types=1);

/** Binds one WooCommerce order to its canonical Proma Pay contract. */
final class MerchantContractLinkRepository
{
    public static function recordWooContract(
        MerchantAccessContext $context,
        int $contractId,
        string $externalOrderId,
        ?int $actorUserId
    ): array {
        $context->requireScope('contract:write');
        if ($contractId < 1 || ($actorUserId !== null && $actorUserId < 1)) {
            throw new InvalidArgumentException('merchant_contract_link_invalid');
        }
        $externalOrderId = trim($externalOrderId);
        if ($externalOrderId === '' || strlen($externalOrderId) > 190
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $externalOrderId)) {
            throw new InvalidArgumentException('merchant_external_order_id_invalid');
        }
        $orderUuid = MerchantAuditLog::newUuid();
        Model::begin();
        try {
            $connection = Model::fetch(
                "SELECT c.id FROM merchant_connections c
                 INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
                 INNER JOIN merchant_accounts m ON m.id = c.merchant_id
                 WHERE c.id = ? AND c.merchant_id = ? AND c.site_id = ?
                   AND c.status = 'active' AND c.emergency_disabled = 0
                   AND s.status = 'active' AND s.verification_status = 'verified'
                   AND m.status = 'active'
                 FOR UPDATE",
                [$context->connectionId(), $context->merchantId(), $context->siteId()]
            );
            if (!$connection) {
                throw new DomainException('merchant_connection_not_available');
            }
            $contract = Model::fetch(
                'SELECT id, status FROM contracts WHERE id = ? FOR UPDATE',
                [$contractId]
            );
            if (!$contract || in_array((string) $contract['status'], ['cancelled', 'deleted'], true)) {
                throw new DomainException('merchant_contract_not_available');
            }
            Model::execute(
                "INSERT INTO merchant_contract_links
                    (merchant_id, site_id, connection_id, contract_id, external_order_id,
                     external_order_uuid, order_status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'contracted', ?)",
                [
                    $context->merchantId(),
                    $context->siteId(),
                    $context->connectionId(),
                    $contractId,
                    $externalOrderId,
                    $orderUuid,
                    $actorUserId,
                ]
            );
            $linkId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                $context->merchantId(),
                $context->siteId(),
                $context->connectionId(),
                $actorUserId,
                'contract.woocommerce_linked',
                'contract_link',
                $orderUuid,
                ['contract_id' => $contractId, 'external_order_id' => $externalOrderId]
            );
            Model::commit();
            return [
                'id' => $linkId,
                'external_order_uuid' => $orderUuid,
                'contract_id' => $contractId,
                'order_status' => 'contracted',
            ];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function isWooContract(int $contractId): bool
    {
        if ($contractId < 1) {
            return false;
        }
        return Model::fetch(
            'SELECT id FROM merchant_contract_links WHERE contract_id = ? LIMIT 1',
            [$contractId]
        ) !== null;
    }
}
