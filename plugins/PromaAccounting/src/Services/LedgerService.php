<?php

namespace Proma\Plugins\Accounting\Services;

class LedgerService
{
    public static function accountFor($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !\Model::fetch("SELECT id FROM users WHERE id = ? AND role IN ('admin','operator','lawyer') LIMIT 1", [$userId])) {
            throw new \InvalidArgumentException('کاربر حسابداری معتبر نیست.');
        }
        $account = \Model::fetch('SELECT * FROM plugin_accounting_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        if ($account) {
            return $account;
        }
        try {
            \Model::execute('INSERT INTO plugin_accounting_accounts (user_id, account_number, created_at) VALUES (?, ?, NOW())', [$userId, 'ACC-' . str_pad((string) $userId, 8, '0', STR_PAD_LEFT)]);
        } catch (\Throwable $e) {
            // A concurrent request may have created the unique account already.
        }
        $account = \Model::fetch('SELECT * FROM plugin_accounting_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$account) {
            throw new \RuntimeException('حساب کاربر ساخته نشد.');
        }
        return $account;
    }

    public static function balance($userId)
    {
        $row = \Model::fetch("SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS balance FROM plugin_accounting_ledger_entries WHERE user_id = ?", [(int) $userId]);
        return (string) ($row['balance'] ?? '0.00');
    }

    public static function post($userId, $entryType, $direction, $amount, $description, $actorId, $referenceType = null, $referenceId = null, array $metadata = [], $idempotencyKey = null)
    {
        $amount = Money::integer($amount);
        $direction = trim((string) $direction);
        if ($amount <= 0 || !in_array($direction, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('مبلغ و جهت سند دفترکل معتبر نیست.');
        }
        $description = trim((string) $description);
        if ($description === '') {
            throw new \InvalidArgumentException('شرح سند دفترکل الزامی است.');
        }
        if ($idempotencyKey !== null) {
            $existing = \Model::fetch('SELECT id FROM plugin_accounting_ledger_entries WHERE idempotency_key = ? LIMIT 1', [(string) $idempotencyKey]);
            if ($existing) {
                return (int) $existing['id'];
            }
        }
        $account = self::accountFor($userId);
        $started = false;
        if (!\Model::db()->inTransaction()) {
            \Model::begin();
            $started = true;
        }
        try {
            \Model::execute(
                'INSERT INTO plugin_accounting_ledger_entries
                 (account_id, user_id, entry_type, direction, amount, reference_type, reference_id, description, performed_by, metadata_json, idempotency_key, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $account['id'], (int) $userId, trim((string) $entryType), $direction, Money::decimal($amount), $referenceType, $referenceId ? (int) $referenceId : null, $description, $actorId ? (int) $actorId : null, json_encode($metadata, JSON_UNESCAPED_UNICODE), $idempotencyKey]
            );
            $entryId = (int) \Model::lastInsertId();
            if (class_exists('AuditLog')) {
                \AuditLog::record('accounting', 'ledger_posted', 'plugin_accounting_ledger_entry', $entryId, [
                    'actor_user_id' => $actorId,
                    'new_values' => ['user_id' => (int) $userId, 'direction' => $direction, 'amount' => Money::decimal($amount), 'entry_type' => $entryType],
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
            $entry = \Model::fetch('SELECT * FROM plugin_accounting_ledger_entries WHERE id = ? FOR UPDATE', [(int) $entryId]);
            if (!$entry) {
                throw new \InvalidArgumentException('سند دفترکل پیدا نشد.');
            }
            if (\Model::fetch('SELECT id FROM plugin_accounting_ledger_entries WHERE reversal_of_id = ? LIMIT 1', [(int) $entryId])) {
                throw new \InvalidArgumentException('این سند قبلاً معکوس شده است.');
            }
            $opposite = $entry['direction'] === 'credit' ? 'debit' : 'credit';
            $account = self::accountFor((int) $entry['user_id']);
            \Model::execute(
                'INSERT INTO plugin_accounting_ledger_entries
                 (account_id, user_id, entry_type, direction, amount, reference_type, reference_id, reversal_of_id, description, performed_by, metadata_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $account['id'], (int) $entry['user_id'], 'reversal', $opposite, $entry['amount'], 'ledger_entry', (int) $entry['id'], (int) $entry['id'], $reason, $actorId ? (int) $actorId : null, json_encode(['original_entry_id' => (int) $entry['id']], JSON_UNESCAPED_UNICODE)]
            );
            $newId = (int) \Model::lastInsertId();
            if (class_exists('AuditLog')) {
                \AuditLog::record('accounting', 'ledger_reversed', 'plugin_accounting_ledger_entry', $newId, ['actor_user_id' => $actorId, 'old_values' => ['entry_id' => (int) $entry['id']], 'new_values' => ['reason' => $reason]]);
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
