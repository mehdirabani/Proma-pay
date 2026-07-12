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
        $calculation = CommissionCalculationService::forSale($sale);
        if (!self::timingReady($sale, $calculation['calculation_timing'])) {
            return null;
        }
        $requiresApproval = !empty($calculation['requires_approval']) || $calculation['calculation_timing'] === 'manual_approval';
        $existing = \Model::fetch("SELECT * FROM plugin_accounting_commissions WHERE sale_id = ? AND status NOT IN ('reversed', 'cancelled') ORDER BY id DESC LIMIT 1", [(int) $sale['id']]);
        if ($existing && !in_array($existing['status'], ['pending', 'payable'], true)) {
            $basisChanged = Money::integer($existing['basis_amount'] ?? 0) !== $calculation['basis_amount'];
            $ruleChanged = (string) ($existing['commission_value'] ?? '') !== (string) $calculation['commission_value'];
            if ($basisChanged || $ruleChanged) {
                self::reverseRow($existing, $actorId, 'تعدیل کمیسیون به علت تغییر مبلغ یا قانون قرارداد');
                $existing = null;
            }
        }
        if (!$existing) {
            \Model::execute(
                'INSERT INTO plugin_accounting_commissions
                 (contract_id, sale_id, seller_user_id, commission_rule_id, basis_type, basis_amount, commission_type, commission_value, raw_commission, minimum_adjustment, maximum_adjustment, rounding_adjustment, rule_source, calculation_snapshot_json, calculated_amount, status, calculated_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [(int) $sale['contract_id'], (int) $sale['id'], (int) $sale['seller_user_id'], $calculation['rule_id'], $calculation['basis_type'], Money::decimal($calculation['basis_amount']), $calculation['commission_type'], $calculation['commission_value'], Money::decimal($calculation['raw_commission']), Money::signedDecimal($calculation['minimum_adjustment']), Money::signedDecimal($calculation['maximum_adjustment']), Money::signedDecimal($calculation['rounding_adjustment']), $calculation['rule_source'], json_encode($calculation, JSON_UNESCAPED_UNICODE), Money::decimal($calculation['final_commission']), $requiresApproval ? 'pending' : 'payable']
            );
            $commissionId = (int) \Model::lastInsertId();
            self::postIfConfigured($commissionId, $sale, $calculation['final_commission'], $actorId, $calculation);
            return $commissionId;
        }
        if (in_array($existing['status'], ['pending', 'payable'], true)) {
            \Model::execute(
                'UPDATE plugin_accounting_commissions SET seller_user_id = ?, commission_rule_id = ?, basis_type = ?, basis_amount = ?, commission_type = ?, commission_value = ?, raw_commission = ?, minimum_adjustment = ?, maximum_adjustment = ?, rounding_adjustment = ?, rule_source = ?, calculation_snapshot_json = ?, calculated_amount = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [(int) $sale['seller_user_id'], $calculation['rule_id'], $calculation['basis_type'], Money::decimal($calculation['basis_amount']), $calculation['commission_type'], $calculation['commission_value'], Money::decimal($calculation['raw_commission']), Money::signedDecimal($calculation['minimum_adjustment']), Money::signedDecimal($calculation['maximum_adjustment']), Money::signedDecimal($calculation['rounding_adjustment']), $calculation['rule_source'], json_encode($calculation, JSON_UNESCAPED_UNICODE), Money::decimal($calculation['final_commission']), $requiresApproval ? 'pending' : 'payable', (int) $existing['id']]
            );
            self::postIfConfigured((int) $existing['id'], $sale, $calculation['final_commission'], $actorId, $calculation);
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

    public static function approve($commissionId, $actorId)
    {
        \Model::begin();
        try {
            $row = \Model::fetch('SELECT * FROM plugin_accounting_commissions WHERE id = ? FOR UPDATE', [(int) $commissionId]);
            if (!$row || ($row['status'] ?? '') !== 'pending') {
                throw new \InvalidArgumentException('فقط کمیسیون در انتظار تأیید قابل تأیید است.');
            }
            \Model::execute("UPDATE plugin_accounting_commissions SET status = 'approved', approved_amount = calculated_amount, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?", [(int) $actorId, (int) $commissionId]);
            $settings = AccountingRepository::settings();
            if (($settings['automatic_ledger_posting'] ?? '0') === '1') {
                self::postApprovedRow(array_merge($row, ['status' => 'approved', 'approved_amount' => $row['calculated_amount']]), $actorId);
            }
            \AuditLog::record('accounting', 'commission_approved', 'plugin_accounting_commission', (int) $commissionId, ['actor_user_id' => $actorId]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public static function post($commissionId, $actorId)
    {
        \Model::begin();
        try {
            $row = \Model::fetch('SELECT * FROM plugin_accounting_commissions WHERE id = ? FOR UPDATE', [(int) $commissionId]);
            if (!$row || !in_array($row['status'] ?? '', ['payable', 'approved'], true)) {
                throw new \InvalidArgumentException('این کمیسیون آماده ثبت در حساب نیست.');
            }
            self::postApprovedRow($row, $actorId);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public static function reverse($commissionId, $actorId, $reason)
    {
        \Model::begin();
        try {
            $row = \Model::fetch('SELECT * FROM plugin_accounting_commissions WHERE id = ? FOR UPDATE', [(int) $commissionId]);
            if (!$row || in_array($row['status'] ?? '', ['reversed', 'cancelled'], true)) {
                throw new \InvalidArgumentException('کمیسیون قابل معکوس‌سازی نیست.');
            }
            self::reverseRow($row, $actorId, $reason);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
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

    protected static function postIfConfigured($commissionId, array $sale, $amount, $actorId, array $calculation)
    {
        $settings = AccountingRepository::settings();
        if (Money::integer($amount) <= 0 || ($settings['automatic_ledger_posting'] ?? '0') !== '1' || !empty($calculation['requires_approval']) || $calculation['calculation_timing'] === 'manual_approval') {
            return;
        }
        $row = \Model::fetch('SELECT * FROM plugin_accounting_commissions WHERE id = ? LIMIT 1', [(int) $commissionId]);
        if ($row) {
            self::postApprovedRow($row, $actorId, $sale, $amount);
        }
    }

    protected static function postApprovedRow(array $row, $actorId, array $sale = null, $amount = null)
    {
        if (!empty($row['posted_ledger_entry_id']) || ($row['status'] ?? '') === 'posted') {
            return (int) ($row['posted_ledger_entry_id'] ?? 0);
        }
        $sale = $sale ?: \Model::fetch('SELECT * FROM plugin_accounting_sales WHERE id = ? LIMIT 1', [(int) ($row['sale_id'] ?? 0)]);
        if (!$sale) {
            throw new \RuntimeException('فروش مرتبط با کمیسیون پیدا نشد.');
        }
        $amount = $amount === null ? ($row['approved_amount'] ?? $row['calculated_amount'] ?? 0) : $amount;
        $entryId = LedgerService::post(
            (int) $row['seller_user_id'],
            'commission',
            'increase',
            $amount,
            'کمیسیون قرارداد ' . ($row['contract_id'] ?? ''),
            $actorId,
            'commission',
            (int) $row['id'],
            ['sale_id' => (int) $sale['id']],
            'commission:' . (int) $row['id'],
            self::categoryId('commission'),
            (int) $row['contract_id'],
            (int) $row['id']
        );
        \Model::execute("UPDATE plugin_accounting_commissions SET status = 'posted', posted_ledger_entry_id = ?, approved_amount = COALESCE(approved_amount, calculated_amount), updated_at = NOW() WHERE id = ?", [$entryId, (int) $row['id']]);
        return $entryId;
    }

    protected static function timingReady(array $sale, $timing)
    {
        $contractId = (int) ($sale['contract_id'] ?? 0);
        if ($timing === 'after_down_payment') {
            $required = Money::integer($sale['down_payment_amount'] ?? 0);
            if ($required <= 0) {
                return true;
            }
            $paid = \Model::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0", [$contractId]);
            return Money::integer($paid['total'] ?? 0) >= $required;
        }
        if ($timing === 'after_first_installment') {
            return (bool) \Model::fetch("SELECT id FROM installments WHERE contract_id = ? AND status <> 'cancelled' AND (status = 'paid' OR paid_amount > 0) LIMIT 1", [$contractId]);
        }
        if ($timing === 'after_full_settlement') {
            $stats = \Model::fetch("SELECT COUNT(*) AS total, SUM(CASE WHEN status NOT IN ('paid','cancelled') OR GREATEST(COALESCE(remaining_amount, base_amount - paid_amount), 0) > 0 THEN 1 ELSE 0 END) AS open_count FROM installments WHERE contract_id = ?", [$contractId]) ?: [];
            return (int) ($stats['total'] ?? 0) > 0 && (int) ($stats['open_count'] ?? 0) === 0;
        }
        return true;
    }

    protected static function categoryId($type)
    {
        $row = \Model::fetch('SELECT id FROM accounting_categories WHERE category_type = ? AND is_active = 1 ORDER BY is_system DESC, id LIMIT 1', [(string) $type]);
        return $row ? (int) $row['id'] : null;
    }
}
