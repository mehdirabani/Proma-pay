<?php

declare(strict_types=1);

/** Publishes a validated connection policy without changing earlier contracts. */
final class MerchantPolicyRepository
{
    public static function publishForConnection(
        MerchantAccessContext $context,
        string $policyVersionUuid,
        int $actorUserId
    ): bool {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId
            || !MerchantMembershipRepository::allows($actorUserId, $context->merchantId(), 'policy.manage')) {
            throw new DomainException('merchant_policy_manage_denied');
        }
        $policyVersionUuid = strtolower(trim($policyVersionUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $policyVersionUuid)) {
            throw new InvalidArgumentException('merchant_policy_uuid_invalid');
        }

        Model::begin();
        try {
            $policy = Model::fetch(
                "SELECT p.id, p.connection_id, p.policy_type, p.policy_json, c.merchant_id, c.site_id
                 FROM merchant_policy_versions p
                 INNER JOIN merchant_connections c ON c.id = p.connection_id
                 WHERE p.policy_version_uuid = ?
                   AND p.connection_id = ? AND c.merchant_id = ? AND c.site_id = ?
                   AND p.status = 'draft'
                 FOR UPDATE",
                [$policyVersionUuid, $context->connectionId(), $context->merchantId(), $context->siteId()]
            );
            if (!$policy) {
                Model::commit();
                return false;
            }

            if ($policy['policy_type'] === 'guarantee') {
                try {
                    $connectionPolicy = json_decode((string) $policy['policy_json'], true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new DomainException('merchant_guarantee_policy_invalid', 0, $e);
                }
                if (!is_array($connectionPolicy)) {
                    throw new DomainException('merchant_guarantee_policy_invalid');
                }
                MerchantGuaranteePolicy::resolve(
                    $connectionPolicy,
                    MerchantCentralGuaranteePolicyRepository::currentPublished()
                );
            }

            $head = Model::fetch(
                'SELECT policy_version_id FROM merchant_policy_heads
                 WHERE connection_id = ? AND policy_type = ? FOR UPDATE',
                [$context->connectionId(), $policy['policy_type']]
            );
            if ($head) {
                Model::execute(
                    "UPDATE merchant_policy_versions SET status = 'retired'
                     WHERE id = ? AND status = 'published'",
                    [(int) $head['policy_version_id']]
                );
            }
            Model::execute(
                "UPDATE merchant_policy_versions SET status = 'published', published_by = ?, published_at = NOW()
                 WHERE id = ? AND status = 'draft'",
                [$actorUserId, (int) $policy['id']]
            );
            Model::execute(
                'INSERT INTO merchant_policy_heads (connection_id, policy_type, policy_version_id, selected_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE policy_version_id = VALUES(policy_version_id),
                                         selected_by = VALUES(selected_by), selected_at = NOW()',
                [$context->connectionId(), $policy['policy_type'], (int) $policy['id'], $actorUserId]
            );
            MerchantAuditLog::append(
                $context->merchantId(),
                $context->siteId(),
                $context->connectionId(),
                $actorUserId,
                'policy.published',
                'policy',
                $policyVersionUuid,
                ['policy_type' => $policy['policy_type'], 'policy_version_id' => (int) $policy['id']]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }
}
