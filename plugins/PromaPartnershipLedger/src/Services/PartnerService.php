<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\Money;

final class PartnerService
{
    public static function create(array $data, $actorId)
    {
        $name = trim((string) ($data['full_name'] ?? ''));
        $code = strtoupper(trim((string) ($data['partner_code'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $identity = trim((string) ($data['identity_no'] ?? ''));
        if ($name === '' || !preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            throw new \InvalidArgumentException('نام شریک و شناسه معتبر الزامی است.');
        }
        $capitalDefault = Money::basisPoints($data['default_capital_share'] ?? '0');
        $profitDefault = Money::basisPoints($data['default_profit_share'] ?? '0');
        $startDate = trim((string) ($data['started_on'] ?? ''));
        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new \InvalidArgumentException('تاریخ شروع همکاری معتبر نیست.');
        }
        \Model::begin();
        try {
            \Model::execute(
                'INSERT INTO plugin_inventory_partners (partner_code, full_name, phone, identity_no, started_on, default_capital_share_bp, default_profit_share_bp, status, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'active\', ?, ?, NOW())',
                [$code, $name, $phone !== '' ? substr($phone, 0, 40) : null, $identity !== '' ? substr($identity, 0, 80) : null, $startDate !== '' ? $startDate : null, $capitalDefault, $profitDefault, trim((string) ($data['notes'] ?? '')) ?: null, $actorId ? (int) $actorId : null]
            );
            $id = (int) \Model::lastInsertId();
            AuditService::record('partner_created', 'partner', $id, $actorId, [], ['code' => $code, 'name' => $name]);
            \Model::commit();
            return $id;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function recordCapital($partnerId, $amountRial, $method, $reference, $actorId, $idempotencyKey, $note = '')
    {
        $partnerId = (int) $partnerId;
        $amount = Money::integer($amountRial);
        $method = trim((string) $method);
        $reference = substr(trim((string) $reference), 0, 120);
        $idempotencyKey = trim((string) $idempotencyKey);
        $note = substr(trim((string) $note), 0, 255);
        if ($partnerId <= 0 || $amount <= 0 || $idempotencyKey === '' || !in_array($method, ['cash', 'bank_transfer', 'card_to_card', 'other'], true)) {
            throw new \InvalidArgumentException('اطلاعات آورده سرمایه معتبر نیست.');
        }
        \Model::begin();
        try {
            $existing = \Model::fetch('SELECT id, partner_id, amount_rial, method, reference_no, note FROM plugin_inventory_capital_contributions WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                $matches = (int) $existing['partner_id'] === $partnerId
                    && Money::integer($existing['amount_rial']) === $amount
                    && (string) $existing['method'] === $method
                    && (string) ($existing['reference_no'] ?? '') === $reference
                    && (string) ($existing['note'] ?? '') === $note;
                if (!$matches) {
                    throw new \InvalidArgumentException('کلید تکرار آورده با اطلاعات متفاوت استفاده شده است.');
                }
                \Model::commit();
                return (int) $existing['id'];
            }
            $partner = \Model::fetch('SELECT id, status FROM plugin_inventory_partners WHERE id = ? LIMIT 1 FOR UPDATE', [$partnerId]);
            if (!$partner || $partner['status'] !== 'active') {
                throw new \InvalidArgumentException('شریک فعال پیدا نشد.');
            }
            $journalId = LedgerService::post('capital:' . $idempotencyKey, 'capital_contribution', $partnerId, 'آورده سرمایه شریک', $actorId, [
                ['account_code' => 'cash', 'debit_rial' => $amount, 'credit_rial' => 0, 'memo' => 'دریافت آورده'],
                ['account_code' => 'partner_capital', 'partner_id' => $partnerId, 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'افزایش سرمایه شریک'],
            ]);
            \Model::execute(
                'INSERT INTO plugin_inventory_capital_contributions (partner_id, amount_rial, received_at, method, reference_no, journal_id, idempotency_key, note, created_by, created_at)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW())',
                [$partnerId, Money::db($amount), $method, $reference !== '' ? $reference : null, $journalId, $idempotencyKey, $note !== '' ? $note : null, $actorId ? (int) $actorId : null]
            );
            $contribution = \Model::fetch('SELECT id, partner_id, amount_rial, journal_id FROM plugin_inventory_capital_contributions WHERE idempotency_key = ? LIMIT 1', [(string) $idempotencyKey]);
            AuditService::record('capital_contribution', 'capital_contribution', $contribution['id'] ?? null, $actorId, [], ['partner_id' => $partnerId, 'amount_rial' => Money::db($amount)]);
            \Model::commit();
            return (int) ($contribution['id'] ?? 0);
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function summary($partnerId)
    {
        $partnerId = (int) $partnerId;
        $partner = \Model::fetch('SELECT * FROM plugin_inventory_partners WHERE id = ? LIMIT 1', [$partnerId]);
        if (!$partner) {
            throw new \InvalidArgumentException('شریک پیدا نشد.');
        }
        $capital = \Model::fetch(
            "SELECT COALESCE(SUM(CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0))), 0) AS balance FROM plugin_inventory_journal_lines WHERE partner_id = ? AND account_code = 'partner_capital'",
            [$partnerId]
        );
        $payables = \Model::fetch(
            "SELECT COALESCE(SUM(CASE WHEN account_code IN ('partner_capital_payable','partner_profit_payable') THEN CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0)) ELSE 0 END), 0) AS balance,
                    COALESCE(SUM(CASE WHEN account_code = 'partner_profit_payable' THEN CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0)) ELSE 0 END), 0) AS profit_available
             FROM plugin_inventory_journal_lines WHERE partner_id = ?",
            [$partnerId]
        );
        $contributions = \Model::fetch('SELECT COALESCE(SUM(amount_rial), 0) AS total FROM plugin_inventory_capital_contributions WHERE partner_id = ?', [$partnerId]);
        $stockCapital = \Model::fetch('SELECT COALESCE(SUM(remaining_capital_rial), 0) AS total FROM plugin_inventory_lot_partner_shares WHERE partner_id = ?', [$partnerId]);
        $soldCapital = \Model::fetch("SELECT COALESCE(SUM(ps.capital_amount_rial - ps.capital_returned_rial), 0) AS total
            FROM plugin_inventory_sale_partner_shares ps JOIN plugin_inventory_sale_lines sl ON sl.id = ps.sale_line_id
            JOIN plugin_inventory_sales s ON s.id = sl.sale_id WHERE ps.partner_id = ? AND s.status = 'posted'", [$partnerId]);
        $profit = \Model::fetch("SELECT COALESCE(SUM(ps.profit_amount_rial), 0) AS total, COALESCE(SUM(ps.profit_released_rial), 0) AS released
            FROM plugin_inventory_sale_partner_shares ps JOIN plugin_inventory_sale_lines sl ON sl.id = ps.sale_line_id
            JOIN plugin_inventory_sales s ON s.id = sl.sale_id WHERE ps.partner_id = ? AND s.status = 'posted'", [$partnerId]);
        $partner['capital_balance_rial'] = (string) ($capital['balance'] ?? 0);
        $partner['capital_contributions_rial'] = (string) ($contributions['total'] ?? 0);
        $partner['capital_at_risk_rial'] = (string) ((int) ($stockCapital['total'] ?? 0) + (int) ($soldCapital['total'] ?? 0));
        $partner['recognized_profit_rial'] = (string) ($profit['total'] ?? 0);
        $partner['collected_profit_rial'] = (string) ($profit['released'] ?? 0);
        $partner['withdrawable_rial'] = (string) ($payables['balance'] ?? 0);
        $partner['withdrawable_profit_rial'] = (string) ($payables['profit_available'] ?? 0);
        return $partner;
    }

    public static function ledger($partnerId, $limit = 100)
    {
        $limit = max(1, min(250, (int) $limit));
        return \Model::fetchAll(
            'SELECT jl.*, j.event_key, j.source_type, j.source_id, j.description, j.created_at
             FROM plugin_inventory_journal_lines jl
             JOIN plugin_inventory_journals j ON j.id = jl.journal_id
             WHERE jl.partner_id = ? ORDER BY j.id DESC, jl.id DESC LIMIT ' . $limit,
            [(int) $partnerId]
        );
    }
}
