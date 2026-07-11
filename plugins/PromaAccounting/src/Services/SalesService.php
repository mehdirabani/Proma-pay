<?php

namespace Proma\Plugins\Accounting\Services;

class SalesService
{
    public static function recordFromContract($contractId, $actorId = null)
    {
        $contract = \Contract::find((int) $contractId);
        if (!$contract) {
            return null;
        }
        $sellerId = self::eligibleSeller($actorId);
        $status = $sellerId ? 'active' : 'needs_assignment';
        $data = self::amounts($contract);
        \Model::execute(
            'INSERT IGNORE INTO plugin_accounting_sales
             (contract_id, customer_id, seller_user_id, sales_channel, principal_amount, down_payment_amount, financed_amount, registered_by, registered_at, status, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)',
            [(int) $contract['id'], (int) $contract['customer_id'], $sellerId, 'other', $data['principal'], $data['down_payment'], $data['financed'], $actorId ? (int) $actorId : null, $status, json_encode(['source' => 'contract.created'], JSON_UNESCAPED_UNICODE)]
        );
        $sale = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE contract_id = ? LIMIT 1', [(int) $contractId]);
        if ($sale) {
            self::log($sale, 'sale_created', null, $sale, $actorId);
        }
        if ($sale && $sellerId) {
            CommissionService::refreshForSale($sale, $actorId);
        }
        return $sale;
    }

    public static function syncFromContract($contractId, $actorId = null)
    {
        $contract = \Contract::find((int) $contractId);
        $sale = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE contract_id = ? LIMIT 1', [(int) $contractId]);
        if (!$contract || !$sale) {
            return self::recordFromContract($contractId, $actorId);
        }
        $data = self::amounts($contract);
        $old = $sale;
        \Model::execute(
            'UPDATE plugin_accounting_sales
             SET principal_amount = ?, down_payment_amount = ?, financed_amount = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?',
            [$data['principal'], $data['down_payment'], $data['financed'], $actorId ? (int) $actorId : null, (int) $sale['id']]
        );
        $sale = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE id = ?', [(int) $sale['id']]);
        self::log($sale, 'sale_amount_changed', $old, $sale, $actorId);
        CommissionService::refreshForSale($sale, $actorId);
        return $sale;
    }

    public static function assignSeller($saleId, $sellerId, $actorId)
    {
        $sellerId = self::eligibleSeller($sellerId);
        if (!$sellerId) {
            throw new \InvalidArgumentException('فروشنده انتخاب‌شده معتبر یا فعال نیست.');
        }
        $sale = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE id = ? FOR UPDATE', [(int) $saleId]);
        if (!$sale) {
            throw new \InvalidArgumentException('فروش پیدا نشد.');
        }
        \Model::begin();
        try {
            \Model::execute('UPDATE plugin_accounting_sales SET seller_user_id = ?, status = \'active\', updated_by = ?, updated_at = NOW() WHERE id = ?', [$sellerId, (int) $actorId, (int) $saleId]);
            $updated = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE id = ?', [(int) $saleId]);
            self::log($updated, 'seller_assigned', $sale, $updated, $actorId);
            CommissionService::refreshForSale($updated, $actorId);
            \Model::commit();
            return $updated;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    protected static function amounts(array $contract)
    {
        $principal = Money::integer($contract['principal_amount'] ?? 0);
        $down = Money::integer($contract['down_payment_amount'] ?? 0);
        return ['principal' => Money::decimal($principal), 'down_payment' => Money::decimal($down), 'financed' => Money::decimal(max(0, $principal - $down))];
    }

    protected static function eligibleSeller($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }
        $user = \Model::fetch("SELECT id FROM users WHERE id = ? AND role IN ('admin','operator','lawyer') AND status = 'active' LIMIT 1", [$userId]);
        return $user ? $userId : null;
    }

    protected static function log(array $sale, $action, $old, $new, $actorId)
    {
        \Model::execute(
            'INSERT INTO plugin_accounting_sales_logs (sale_id, contract_id, customer_id, seller_user_id, action, old_snapshot_json, new_snapshot_json, performed_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [(int) $sale['id'], (int) $sale['contract_id'], (int) $sale['customer_id'], !empty($sale['seller_user_id']) ? (int) $sale['seller_user_id'] : null, $action, $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null, $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null, $actorId ? (int) $actorId : null]
        );
    }
}
