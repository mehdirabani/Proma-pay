<?php

declare(strict_types=1);

/** Tenant-aware merchant membership checks. */
final class MerchantMembershipRepository
{
    public static function allows(int $userId, int $merchantId, string $permission): bool
    {
        if ($userId < 1 || $merchantId < 1 || $permission === '') {
            return false;
        }

        $membership = Model::fetch(
            "SELECT mm.membership_role
             FROM merchant_memberships mm
             INNER JOIN merchant_accounts m ON m.id = mm.merchant_id
             WHERE mm.user_id = ?
               AND mm.merchant_id = ?
               AND mm.status = 'active'
               AND m.status = 'active'
             LIMIT 1",
            [$userId, $merchantId]
        );
        if (!$membership) {
            return false;
        }

        return MerchantMembershipPolicy::allows((string) $membership['membership_role'], $permission);
    }
}
