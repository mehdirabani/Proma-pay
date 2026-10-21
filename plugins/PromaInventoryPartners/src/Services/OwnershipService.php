<?php

namespace Proma\Plugins\InventoryPartners\Services;

use Proma\Plugins\InventoryPartners\Domain\Money;

final class OwnershipService
{
    public static function activePartners()
    {
        return \Model::fetchAll("SELECT id, partner_code, full_name, default_capital_share_bp, default_profit_share_bp FROM plugin_inventory_partners WHERE status = 'active' ORDER BY full_name ASC, id ASC");
    }

    public static function resolve(array $submitted, $key)
    {
        $active = [];
        foreach (self::activePartners() as $partner) {
            $active[(int) $partner['id']] = $partner;
        }
        $map = $submitted[$key] ?? [];
        if (!is_array($map)) {
            throw new \InvalidArgumentException('ترکیب سهم شرکا معتبر نیست.');
        }
        $rows = [];
        foreach ($map as $rawId => $rawPercent) {
            $partnerId = filter_var($rawId, FILTER_VALIDATE_INT);
            if (!$partnerId || !isset($active[(int) $partnerId]) || trim((string) $rawPercent) === '') {
                continue;
            }
            $basisPoints = Money::basisPoints($rawPercent);
            if ($basisPoints === 0) {
                continue;
            }
            $rows[(int) $partnerId] = [
                'partner_id' => (int) $partnerId,
                'weight_bp' => $basisPoints,
                'share_bp' => $basisPoints,
                'name' => (string) $active[(int) $partnerId]['full_name'],
            ];
        }
        if (!$rows) {
            throw new \InvalidArgumentException('سهم حداقل یک شریک فعال را مشخص کنید.');
        }
        $sum = array_sum(array_column($rows, 'weight_bp'));
        if ($sum !== 10000) {
            throw new \InvalidArgumentException('جمع سهم‌ها باید دقیقاً صد درصد باشد.');
        }
        ksort($rows, SORT_NUMERIC);
        return array_values($rows);
    }

    public static function allocationRows($amount, array $shares)
    {
        $allocations = Money::allocate($amount, $shares);
        $rows = [];
        foreach ($shares as $share) {
            $partnerId = (int) $share['partner_id'];
            $rows[$partnerId] = [
                'partner_id' => $partnerId,
                'share_bp' => (int) $share['weight_bp'],
                'amount_rial' => (int) $allocations[$partnerId],
                'name' => (string) ($share['name'] ?? ''),
            ];
        }
        return $rows;
    }
}
