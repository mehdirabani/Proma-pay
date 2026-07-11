<?php

namespace Proma\Plugins\Accounting\Services;

class CommissionService
{
    public static function refreshForContract($contractId, $actorId = null)
    {
        $sale = \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE contract_id = ? LIMIT 1', [(int) $contractId]);
        return $sale ? self::refreshForSale($sale, $actorId) : null;
    }

    public static function refreshForSale(array $sale, $actorId = null)
    {
        if (empty($sale['seller_user_id'])) {
            return null;
        }
        $rule = self::ruleFor((int) $sale['seller_user_id']);
        if (!$rule) {
            return null;
        }
        $basisType = $rule['calculation_basis'] ?: 'financed_amount';
        $basis = Money::integer($sale[$basisType] ?? $sale['financed_amount'] ?? 0);
        $amount = $rule['commission_type'] === 'percentage'
            ? Money::percentage($basis, $rule['commission_value'])
            : Money::integer($rule['commission_value']);
        $minimum = $rule['minimum_amount'] !== null ? Money::integer($rule['minimum_amount']) : null;
        $maximum = $rule['maximum_amount'] !== null ? Money::integer($rule['maximum_amount']) : null;
        if ($minimum !== null) {
            $amount = max($amount, $minimum);
        }
        if ($maximum !== null && $maximum > 0) {
            $amount = min($amount, $maximum);
        }
        $existing = \Model::fetch('SELECT * FROM plugin_accounting_commissions WHERE sale_id = ? LIMIT 1', [(int) $sale['id']]);
        if (!$existing) {
            \Model::execute(
                'INSERT INTO plugin_accounting_commissions
                 (contract_id, sale_id, seller_user_id, commission_rule_id, basis_type, basis_amount, commission_type, commission_value, calculated_amount, status, calculated_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [(int) $sale['contract_id'], (int) $sale['id'], (int) $sale['seller_user_id'], (int) $rule['id'], $basisType, Money::decimal($basis), $rule['commission_type'], $rule['commission_value'], Money::decimal($amount), !empty($rule['requires_approval']) ? 'pending' : 'payable']
            );
            return (int) \Model::lastInsertId();
        }
        if (in_array($existing['status'], ['pending', 'payable'], true)) {
            \Model::execute(
                'UPDATE plugin_accounting_commissions SET seller_user_id = ?, commission_rule_id = ?, basis_type = ?, basis_amount = ?, commission_type = ?, commission_value = ?, calculated_amount = ?, updated_at = NOW() WHERE id = ?',
                [(int) $sale['seller_user_id'], (int) $rule['id'], $basisType, Money::decimal($basis), $rule['commission_type'], $rule['commission_value'], Money::decimal($amount), (int) $existing['id']]
            );
        }
        return (int) $existing['id'];
    }

    public static function reverseForContract($contractId, $actorId, $reason)
    {
        $rows = \Model::fetchAll('SELECT * FROM plugin_accounting_commissions WHERE contract_id = ? AND status NOT IN (\'reversed\', \'cancelled\') FOR UPDATE', [(int) $contractId]);
        foreach ($rows as $row) {
            if (!empty($row['posted_ledger_entry_id'])) {
                LedgerService::reverse((int) $row['posted_ledger_entry_id'], $actorId, $reason);
            }
            \Model::execute('UPDATE plugin_accounting_commissions SET status = \'reversed\', reversed_at = NOW(), reversed_by = ?, reversal_reason = ?, updated_at = NOW() WHERE id = ?', [(int) $actorId, trim((string) $reason), (int) $row['id']]);
        }
    }

    protected static function ruleFor($sellerId)
    {
        return \Model::fetch(
            "SELECT * FROM plugin_accounting_commission_rules
             WHERE is_active = 1 AND (user_id = ? OR user_id IS NULL)
             AND (effective_from IS NULL OR effective_from <= CURDATE())
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, priority DESC, id ASC LIMIT 1",
            [(int) $sellerId, (int) $sellerId]
        );
    }
}
