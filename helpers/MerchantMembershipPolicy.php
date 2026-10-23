<?php

declare(strict_types=1);

/** Fixed server-side permission map for merchant-panel memberships. */
final class MerchantMembershipPolicy
{
    private const PERMISSIONS = [
        'owner' => [
            'merchant.read', 'merchant.manage',
            'site.read', 'site.manage',
            'connection.read', 'connection.manage',
            'policy.read', 'policy.manage',
            'reports.read', 'settlement.read',
        ],
        'admin' => [
            'merchant.read', 'merchant.manage',
            'site.read', 'site.manage',
            'connection.read', 'connection.manage',
            'policy.read', 'policy.manage',
            'reports.read', 'settlement.read',
        ],
        'operator' => [
            'merchant.read', 'site.read', 'connection.read', 'policy.read', 'reports.read',
        ],
        'finance' => [
            'merchant.read', 'site.read', 'connection.read', 'policy.read', 'reports.read', 'settlement.read',
        ],
        'viewer' => [
            'merchant.read', 'site.read', 'connection.read', 'policy.read', 'reports.read',
        ],
    ];

    public static function allows(string $role, string $permission): bool
    {
        if ($permission === '' || strpos($permission, '*') !== false) {
            return false;
        }
        return in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    public static function roles(): array
    {
        return array_keys(self::PERMISSIONS);
    }
}
