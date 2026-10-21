<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\Money;

final class SettlementService
{
    public static function request($partnerId, $amountRial, $reason, $actorId, $idempotencyKey)
    {
        $partnerId = (int) $partnerId;
        $amount = Money::integer($amountRial);
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($partnerId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            throw new \InvalidArgumentException('شریک، مبلغ و شناسه درخواست تسویه الزامی است.');
        }
        \Model::begin();
        try {
            $existing = \Model::fetch('SELECT id, partner_id, amount_rial FROM plugin_inventory_settlement_requests WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if ((int) $existing['partner_id'] !== $partnerId || Money::integer($existing['amount_rial']) !== $amount) {
                    throw new \InvalidArgumentException('کلید تکرار تسویه با اطلاعات متفاوت استفاده شده است.');
                }
                \Model::commit();
                return (int) $existing['id'];
            }
            $partner = \Model::fetch("SELECT id, status FROM plugin_inventory_partners WHERE id = ? LIMIT 1 FOR UPDATE", [$partnerId]);
            if (!$partner || $partner['status'] !== 'active') {
                throw new \InvalidArgumentException('شریک فعال پیدا نشد.');
            }
            $balance = self::payableBalance($partnerId);
            $reserved = \Model::fetch("SELECT COALESCE(SUM(amount_rial), 0) AS total FROM plugin_inventory_settlement_requests
                WHERE partner_id = ? AND status IN ('pending_approval','approved')", [$partnerId]);
            $available = $balance - Money::integer($reserved['total'] ?? 0);
            if ($amount > $available) {
                throw new \InvalidArgumentException('مبلغ درخواست از وجه قابل برداشت پس از کسر درخواست‌های باز بیشتر است.');
            }
            $requestNo = self::number('SET');
            \Model::execute(
                'INSERT INTO plugin_inventory_settlement_requests (request_no, partner_id, amount_rial, status, reason, requested_by, idempotency_key, created_at)
                 VALUES (?, ?, ?, \'pending_approval\', ?, ?, ?, NOW())',
                [$requestNo, $partnerId, Money::db($amount), substr(trim((string) $reason), 0, 255) ?: null, $actorId ? (int) $actorId : null, $idempotencyKey]
            );
            $id = (int) \Model::lastInsertId();
            AuditService::record('settlement_requested', 'settlement', $id, $actorId, [], ['partner_id' => $partnerId, 'amount_rial' => Money::db($amount)]);
            \Model::commit();
            return $id;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function approve($settlementId, $actorId)
    {
        return self::changeDecision($settlementId, $actorId, 'approved');
    }

    public static function reject($settlementId, $actorId, $reason)
    {
        return self::changeDecision($settlementId, $actorId, 'rejected', $reason);
    }

    private static function changeDecision($settlementId, $actorId, $nextStatus, $reason = '')
    {
        \Model::begin();
        try {
            $request = \Model::fetch('SELECT * FROM plugin_inventory_settlement_requests WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $settlementId]);
            if (!$request || $request['status'] !== 'pending_approval') {
                throw new \InvalidArgumentException('درخواست تسویه در وضعیت قابل تصمیم‌گیری نیست.');
            }
            \Model::fetch('SELECT id FROM plugin_inventory_partners WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $request['partner_id']]);
            if ($nextStatus === 'approved') {
                $balance = self::payableBalance((int) $request['partner_id']);
                $reserved = \Model::fetch("SELECT COALESCE(SUM(amount_rial), 0) AS total FROM plugin_inventory_settlement_requests
                    WHERE partner_id = ? AND status IN ('pending_approval','approved') AND id <> ?", [(int) $request['partner_id'], (int) $request['id']]);
                if (Money::integer($request['amount_rial']) > $balance - Money::integer($reserved['total'] ?? 0)) {
                    throw new \InvalidArgumentException('مانده قابل تسویه پس از درخواست‌های دیگر برای تأیید کافی نیست.');
                }
                \Model::execute("UPDATE plugin_inventory_settlement_requests SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?", [$actorId ? (int) $actorId : null, (int) $request['id']]);
            } else {
                $reason = trim((string) $reason);
                if ($reason === '') { throw new \InvalidArgumentException('علت رد درخواست تسویه الزامی است.'); }
                \Model::execute("UPDATE plugin_inventory_settlement_requests SET status = 'rejected', reason = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?", [substr($reason, 0, 255), $actorId ? (int) $actorId : null, (int) $request['id']]);
            }
            AuditService::record('settlement_' . $nextStatus, 'settlement', (int) $request['id'], $actorId, ['status' => 'pending_approval'], ['status' => $nextStatus, 'reason' => trim((string) $reason)]);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function pay($settlementId, $method, $reference, $actorId)
    {
        $method = trim((string) $method);
        $reference = trim((string) $reference);
        if (!in_array($method, ['cash', 'bank_transfer', 'card_to_card', 'other'], true) || $reference === '') {
            throw new \InvalidArgumentException('روش پرداخت و شناسه تراکنش یا رسید الزامی است.');
        }
        \Model::begin();
        try {
            $request = \Model::fetch('SELECT * FROM plugin_inventory_settlement_requests WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $settlementId]);
            if (!$request || $request['status'] !== 'approved') {
                throw new \InvalidArgumentException('فقط تسویه تأییدشده قابل پرداخت است.');
            }
            \Model::fetch('SELECT id FROM plugin_inventory_partners WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $request['partner_id']]);
            $amount = Money::integer($request['amount_rial']);
            $capital = self::accountBalance((int) $request['partner_id'], 'partner_capital_payable');
            $profit = self::accountBalance((int) $request['partner_id'], 'partner_profit_payable');
            $reserved = \Model::fetch("SELECT COALESCE(SUM(amount_rial), 0) AS total FROM plugin_inventory_settlement_requests
                WHERE partner_id = ? AND status IN ('pending_approval','approved') AND id <> ?", [(int) $request['partner_id'], (int) $request['id']]);
            if ($amount > $capital + $profit - Money::integer($reserved['total'] ?? 0)) {
                throw new \InvalidArgumentException('وجه قابل پرداخت پس از حفظ رزرو درخواست‌های باز دیگر کافی نیست.');
            }
            $capitalPaid = min($capital, $amount);
            $profitPaid = $amount - $capitalPaid;
            $lines = [];
            if ($capitalPaid > 0) {
                $lines[] = ['account_code' => 'partner_capital_payable', 'partner_id' => (int) $request['partner_id'], 'debit_rial' => $capitalPaid, 'credit_rial' => 0, 'memo' => 'پرداخت اصل سرمایه'];
            }
            if ($profitPaid > 0) {
                $lines[] = ['account_code' => 'partner_profit_payable', 'partner_id' => (int) $request['partner_id'], 'debit_rial' => $profitPaid, 'credit_rial' => 0, 'memo' => 'پرداخت سود وصول‌شده'];
            }
            $lines[] = ['account_code' => 'cash', 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'پرداخت تسویه'];
            $journalId = LedgerService::post('settlement:' . (int) $request['id'], 'inventory_settlement', (int) $request['id'], 'پرداخت تسویه ' . $request['request_no'], $actorId, $lines);
            \Model::execute("UPDATE plugin_inventory_settlement_requests SET status = 'paid', payout_method = ?, payment_reference = ?, paid_by = ?, paid_at = NOW(), journal_id = ?, updated_at = NOW() WHERE id = ?", [
                $method, substr($reference, 0, 120), $actorId ? (int) $actorId : null, $journalId, (int) $request['id'],
            ]);
            AuditService::record('settlement_paid', 'settlement', (int) $request['id'], $actorId, ['status' => 'approved'], ['amount_rial' => Money::db($amount), 'method' => $method, 'reference' => substr($reference, 0, 120)]);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function reverse($settlementId, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { throw new \InvalidArgumentException('علت برگشت تسویه الزامی است.'); }
        \Model::begin();
        try {
            $request = \Model::fetch('SELECT * FROM plugin_inventory_settlement_requests WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $settlementId]);
            if (!$request || $request['status'] !== 'paid' || empty($request['journal_id'])) {
                throw new \InvalidArgumentException('فقط پرداخت تسویه‌شده با سند دفترکل قابل برگشت است.');
            }
            LedgerService::reverse((int) $request['journal_id'], $actorId, $reason);
            \Model::execute("UPDATE plugin_inventory_settlement_requests SET status = 'returned', updated_at = NOW() WHERE id = ?", [(int) $request['id']]);
            AuditService::record('settlement_returned', 'settlement', (int) $request['id'], $actorId, ['status' => 'paid'], ['status' => 'returned', 'reason' => substr($reason, 0, 255)]);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function payableBalance($partnerId)
    {
        return self::accountBalance((int) $partnerId, 'partner_capital_payable')
            + self::accountBalance((int) $partnerId, 'partner_profit_payable');
    }

    private static function accountBalance($partnerId, $accountCode)
    {
        $row = \Model::fetch('SELECT COALESCE(SUM(CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0))), 0) AS balance FROM plugin_inventory_journal_lines WHERE partner_id = ? AND account_code = ?', [(int) $partnerId, (string) $accountCode]);
        return (int) ($row['balance'] ?? 0);
    }

    private static function number($prefix)
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
