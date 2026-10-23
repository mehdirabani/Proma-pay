<?php

declare(strict_types=1);

/** API reads are always bound to the authenticated merchant/site/connection tuple. */
final class MerchantConnectionRepository
{
    public static function findActive(MerchantAccessContext $context, string $requiredScope): ?array
    {
        $context->requireScope($requiredScope);
        return Model::fetch(
            "SELECT c.id, c.connection_uuid, c.connection_code, c.display_name,
                    c.api_version, c.merchant_id, c.site_id, c.rate_limit_per_minute,
                    s.site_origin, m.merchant_uuid
             FROM merchant_connections c
             INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
             INNER JOIN merchant_accounts m ON m.id = c.merchant_id
             WHERE c.id = ?
               AND c.merchant_id = ?
               AND c.site_id = ?
               AND c.status = 'active'
               AND c.emergency_disabled = 0
               AND s.status = 'active'
               AND s.verification_status = 'verified'
               AND m.status = 'active'
             LIMIT 1",
            [$context->connectionId(), $context->merchantId(), $context->siteId()]
        );
    }

    public static function publishedPolicy(
        MerchantAccessContext $context,
        string $policyType,
        string $requiredScope
    ): ?array {
        $context->requireScope($requiredScope);
        if (!in_array($policyType, MerchantPolicyDocument::types(), true)) {
            throw new InvalidArgumentException('merchant_policy_type_invalid');
        }

        $row = Model::fetch(
            "SELECT p.id, p.policy_version_uuid, p.policy_type, p.version_no,
                    p.policy_json, p.content_sha256, p.published_at
             FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p
               ON p.id = h.policy_version_id
              AND p.connection_id = h.connection_id
              AND p.policy_type = h.policy_type
             INNER JOIN merchant_connections c ON c.id = h.connection_id
             INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
             INNER JOIN merchant_accounts m ON m.id = c.merchant_id
             WHERE h.connection_id = ?
               AND c.id = ?
               AND c.merchant_id = ?
               AND c.site_id = ?
               AND h.policy_type = ?
               AND p.status = 'published'
               AND c.status = 'active'
               AND c.emergency_disabled = 0
               AND s.status = 'active'
               AND s.verification_status = 'verified'
               AND m.status = 'active'
             LIMIT 1",
            [
                $context->connectionId(),
                $context->connectionId(),
                $context->merchantId(),
                $context->siteId(),
                $policyType,
            ]
        );
        if (!$row) {
            return null;
        }

        if (!hash_equals((string) $row['content_sha256'], hash('sha256', (string) $row['policy_json']))) {
            throw new RuntimeException('merchant_policy_snapshot_corrupt');
        }
        return $row;
    }
}
