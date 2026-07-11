<?php

namespace Proma\Plugins\Accounting\Services;

class LedgerService
{
    public static function accountFor($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !\Model::fetch("SELECT id FROM users WHERE id = ? AND role IN ('admin','operator','lawyer') AND status = 'active' LIMIT 1", [$userId])) {
            throw new \InvalidArgumentException('کاربر حسابداری معتبر نیست.');
        }
        $account = \Model::fetch('SELECT * FROM accounting_user_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        if ($account) {
            return $account;
        }
        try {
            \Model::execute(
                'INSERT INTO accounting_user_accounts (user_id, account_number, status, opening_balance, current_balance, created_at) VALUES (?, ?, \'active\', 0, 0, NOW())',
                [$userId, 'ACC-' . str_pad((string) $userId, 8, '0', STR_PAD_LEFT)]
            );
        } catch (\Throwable $e) {
            // A concurrent request may have created the unique account already.
        }
        $account = \Model::fetch('SELECT * FROM accounting_user_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$account) {
            throw new \RuntimeException('حساب کاربر ساخته نشد.');
        }
        return $account;
    }

    public static function balance($userId)
    {
        $account = \Model::fetch('SELECT current_balance FROM accounting_user_accounts WHERE user_id = ? LIMIT 1', [(int) $userId]);
        return Money::decimal($account['current_balance'] ?? 0);
    }

    public static function post($userId, $entryType, $direction, $amount, $description, $actorId, $referenceType = null, $referenceId = null, array $metadata = [], $idempotencyKey = null, $categoryId = null, $contractId = null, $commissionId = null)
    {
        $amount = Money::integer($amount);
        $direction = trim((string) $direction);
        if ($amount <= 0 || !in_array($direction, ['increase', 'decrease'], true)) {
            throw new \InvalidArgumentException('مبلغ و جهت سند دفترکل معتبر نیست.');
        }
        $description = trim((string) $description);
        if ($description === '') {
            throw new \InvalidArgumentException('شرح سند دفترکل الزامی است.');
        }
        $started = false;
        if (!\Model::db()->inTransaction()) {
            \Model::begin();
            $started = true;
        }
        try {
            if ($idempotencyKey !== null) {
                $existing = \Model::fetch('SELECT id FROM accounting_ledger_entries WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [(string) $idempotencyKey]);
                if ($existing) {
                    if ($started) {
                        \Model::commit();
                    }
                    return (int) $existing['id'];
                }
            }
            $account = self::accountFor($userId);
            $account = \Model::fetch('SELECT * FROM accounting_user_accounts WHERE id = ? FOR UPDATE', [(int) $account['id']]);
            $before = Money::integer($account['current_balance'] ?? 0);
            $after = $direction === 'increase' ? $before + $amount : $before - $amount;
            $entryNumber = 'LED-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            \Model::execute(
                'INSERT INTO accounting_ledger_entries
                 (account_id, user_id, entry_number, entry_type, category_id, direction, amount, balance_before, balance_after, reference_type, reference_id, contract_id, commission_id, description, entry_date, entry_time, created_by, created_at, metadata_json, idempotency_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, NOW(), ?, ?)',
                [(int) $account['id'], (int) $userId, $entryNumber, trim((string) $entryType), $categoryId ? (int) $categoryId : null, $direction, Money::decimal($amount), Money::decimal($before), Money::decimal($after), $referenceType, $referenceId ? (int) $referenceId : null, $contractId ? (int) $contractId : null, $commissionId ? (int) $commissionId : null, $description, $actorId ? (int) $actorId : null, json_encode($metadata, JSON_UNESCAPED_UNICODE), $idempotencyKey]
            );
            $entryId = (int) \Model::lastInsertId();
            \Model::execute('UPDATE accounting_user_accounts SET current_balance = ?, updated_at = NOW() WHERE id = ?', [Money::decimal($after), (int) $account['id']]);
            if (class_exists('AuditLog')) {
                \AuditLog::record('accounting', 'ledger_posted', 'accounting_ledger_entry', $entryId, [
                    'actor_user_id' => $actorId,
                    'new_values' => ['user_id' => (int) $userId, 'direction' => $direction, 'amount' => Money::decimal($amount), 'entry_type' => $entryType, 'balance_before' => Money::decimal($before), 'balance_after' => Money::decimal($after)],
                ]);
            }
            if ($started) {
                \Model::commit();
            }
            return $entryId;
        } catch (\Throwable $e) {
            if ($started) {
                \Model::rollBack();
            }
            throw $e;
        }
    }

    public static function reverse($entryId, $actorId, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('علت معکوس‌کردن سند الزامی است.');
        }
        $started = false;
        if (!\Model::db()->inTransaction()) {
            \Model::begin();
            $started = true;
        }
        try {
            $entry = \Model::fetch('SELECT * FROM accounting_ledger_entries WHERE id = ? FOR UPDATE', [(int) $entryId]);
            if (!$entry) {
                throw new \InvalidArgumentException('سند دفترکل پیدا نشد.');
            }
            if (\Model::fetch('SELECT id FROM accounting_ledger_entries WHERE reversed_entry_id = ? LIMIT 1 FOR UPDATE', [(int) $entryId])) {
                throw new \InvalidArgumentException('این سند قبلاً معکوس شده است.');
            }
            $opposite = $entry['direction'] === 'increase' ? 'decrease' : 'increase';
            $newId = self::post(
                (int) $entry['user_id'],
                'reversal',
                $opposite,
                $entry['amount'],
                $reason,
                $actorId,
                'ledger_entry',
                (int) $entry['id'],
                ['original_entry_id' => (int) $entry['id']],
                'reverse:' . (int) $entry['id'],
                $entry['category_id'] ?? null,
                $entry['contract_id'] ?? null,
                $entry['commission_id'] ?? null
            );
            \Model::execute('UPDATE accounting_ledger_entries SET reversed_entry_id = ?, reversed_by = ?, reversed_at = NOW(), reversal_reason = ? WHERE id = ?', [$newId, $actorId ? (int) $actorId : null, $reason, (int) $entry['id']]);
            if (class_exists('AuditLog')) {
                \AuditLog::record('accounting', 'ledger_reversed', 'accounting_ledger_entry', $newId, ['actor_user_id' => $actorId, 'old_values' => ['entry_id' => (int) $entry['id']], 'new_values' => ['reason' => $reason]]);
            }
            if ($started) {
                \Model::commit();
            }
            return $newId;
        } catch (\Throwable $e) {
            if ($started) {
                \Model::rollBack();
            }
            throw $e;
        }
    }
}
