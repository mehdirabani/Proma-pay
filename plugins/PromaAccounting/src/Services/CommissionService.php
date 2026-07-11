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
        $basis = self::basisFor($sale, $basisType);
        $amount = $rule['commission_type'] === 'percentage'
            ? Money::percentage($basis, $rule['commission_value'])
            : Money::integer($rule['commission_value']);
        $minimum = $rule['minimum_amount'] !== null && $rule['minimum_amount'] !== '' ? Money::integer($rule['minimum_amount']) : null;
        $maximum = $rule['maximum_amount'] !== null && $rule['maximum_amount'] !== '' ? Money::integer($rule['maximum_amount']) : null;
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
                [(int) $sale['contract_id'], (int) $sale['id'], (int) $sale['seller_user_id'], !empty($rule['id']) ? (int) $rule['id'] : null, $basisType, Money::decimal($basis), $rule['commission_type'], $rule['commission_value'], Money::decimal($amount), !empty($rule['requires_approval']) ? 'pending' : 'payable']
            );
            $commissionId = (int) \Model::lastInsertId();
            self::postIfConfigured($commissionId, $sale, $amount, $actorId, $rule);
            return $commissionId;
        }
        if (in_array($existing['status'], ['pending', 'payable'], true)) {
            \Model::execute(
                'UPDATE plugin_accounting_commissions SET seller_user_id = ?, commission_rule_id = ?, basis_type = ?, basis_amount = ?, commission_type = ?, commission_value = ?, calculated_amount = ?, updated_at = NOW() WHERE id = ?',
                [(int) $sale['seller_user_id'], !empty($rule['id']) ? (int) $rule['id'] : null, $basisType, Money::decimal($basis), $rule['commission_type'], $rule['commission_value'], Money::decimal($amount), (int) $existing['id']]
            );
            self::postIfConfigured((int) $existing['id'], $sale, $amount, $actorId, $rule);
        }
        return (int) $existing['id'];
    }

    public static function reverseForSale($saleId, $actorId, $reason)
    {
        $rows = \Model::fetchAll('SELECT * FROM plugin_accounting_commissions WHERE sale_id = ? AND status NOT IN (\'reversed\', \'cancelled\') FOR UPDATE', [(int) $saleId]);
        foreach ($rows as $row) {
            self::reverseRow($row, $actorId, $reason);
        }
    }

    public static function reverseForContract($contractId, $actorId, $reason)
    {
        $started = false;
        if (!\Model::db()->inTransaction()) {
            \Model::begin();
            $started = true;
        }
        try {
            $rows = \Model::fetchAll('SELECT * FROM plugin_accounting_commissions WHERE contract_id = ? AND status NOT IN (\'reversed\', \'cancelled\') FOR UPDATE', [(int) $contractId]);
            foreach ($rows as $row) {
                self::reverseRow($row, $actorId, $reason);
            }
            if ($started) {
                \Model::commit();
            }
        } catch (\Throwable $e) {
            if ($started) {
                \Model::rollBack();
            }
            throw $e;
        }
    }

    protected static function reverseRow(array $row, $actorId, $reason)
    {
        if (!empty($row['posted_ledger_entry_id'])) {
            LedgerService::reverse((int) $row['posted_ledger_entry_id'], $actorId, $reason);
        }
        \Model::execute('UPDATE plugin_accounting_commissions SET status = \'reversed\', reversed_at = NOW(), reversed_by = ?, reversal_reason = ?, updated_at = NOW() WHERE id = ?', [(int) $actorId, trim((string) $reason), (int) $row['id']]);
    }

    protected static function postIfConfigured($commissionId, array $sale, $amount, $actorId, array $rule)
    {
        $settings = self::settings();
        if (Money::integer($amount) <= 0 || ($settings['automatic_ledger_posting'] ?? '0') !== '1' || !empty($rule['requires_approval'])) {
            return;
        }
        $entryId = LedgerService::post(
            (int) $sale['seller_user_id'],
            'commission',
            'increase',
            $amount,
            'کمیسیون قرارداد ' . ($sale['contract_id'] ?? ''),
            $actorId,
            'commission',
            (int) $commissionId,
            ['sale_id' => (int) $sale['id']],
            'commission:' . (int) $commissionId,
            self::categoryId('commission'),
            (int) $sale['contract_id'],
            (int) $commissionId
        );
        \Model::execute('UPDATE plugin_accounting_commissions SET status = \'posted\', posted_ledger_entry_id = ?, approved_amount = calculated_amount, updated_at = NOW() WHERE id = ?', [$entryId, (int) $commissionId]);
    }

    protected static function basisFor(array $sale, $basisType)
    {
        if ($basisType === 'collected_amount') {
            $row = \Model::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0", [(int) $sale['contract_id']]);
            return Money::integer($row['total'] ?? 0);
        }
        return Money::integer($sale[$basisType] ?? $sale['financed_amount'] ?? 0);
    }

    protected static function ruleFor($sellerId)
    {
        $rule = \Model::fetch(
            "SELECT * FROM plugin_accounting_commission_rules
             WHERE is_active = 1 AND (user_id = ? OR user_id IS NULL)
             AND (effective_from IS NULL OR effective_from <= CURDATE())
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, priority DESC, id ASC LIMIT 1",
            [(int) $sellerId, (int) $sellerId]
        );
        if ($rule) {
            return $rule;
        }
        $settings = self::settings();
        return [
            'id' => null,
            'commission_type' => in_array($settings['default_commission_type'] ?? '', ['fixed', 'percentage'], true) ? $settings['default_commission_type'] : 'percentage',
            'commission_value' => $settings['default_commission_value'] ?? '0',
            'calculation_basis' => $settings['default_calculation_basis'] ?? 'financed_amount',
            'minimum_amount' => $settings['minimum_commission'] ?? '0',
            'maximum_amount' => $settings['maximum_commission'] ?? '0',
            'requires_approval' => ($settings['require_commission_approval'] ?? '0') === '1' ? 1 : 0,
        ];
    }

    protected static function settings()
    {
        $rows = \Model::fetchAll('SELECT setting_key, setting_value FROM plugin_accounting_settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $settings;
    }

    protected static function categoryId($type)
    {
        $row = \Model::fetch('SELECT id FROM accounting_categories WHERE category_type = ? AND is_active = 1 ORDER BY is_system DESC, id LIMIT 1', [(string) $type]);
        return $row ? (int) $row['id'] : null;
    }
}
