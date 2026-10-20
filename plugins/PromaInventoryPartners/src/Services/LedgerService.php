<?php

namespace Proma\Plugins\InventoryPartners\Services;

use Proma\Plugins\InventoryPartners\Domain\Money;

final class LedgerService
{
    /** Post a balanced, immutable journal. Amounts are whole Rials. */
    public static function post($eventKey, $sourceType, $sourceId, $description, $actorId, array $lines, $reversalOfId = null)
    {
        $eventKey = trim((string) $eventKey);
        $description = trim((string) $description);
        if ($eventKey === '' || $description === '' || !$lines) {
            throw new \InvalidArgumentException('سند مالی کامل نیست.');
        }
        $debits = 0;
        $credits = 0;
        $cleanLines = [];
        foreach ($lines as $line) {
            $account = trim((string) ($line['account_code'] ?? ''));
            $debit = Money::integer($line['debit_rial'] ?? 0);
            $credit = Money::integer($line['credit_rial'] ?? 0);
            $partnerId = !empty($line['partner_id']) ? (int) $line['partner_id'] : null;
            if ($account === '' || (($debit > 0) === ($credit > 0)) || ($partnerId !== null && $partnerId <= 0)) {
                throw new \InvalidArgumentException('ردیف دفترکل معتبر نیست.');
            }
            $debits = Money::add($debits, $debit);
            $credits = Money::add($credits, $credit);
            $cleanLines[] = [
                'account_code' => $account,
                'partner_id' => $partnerId,
                'debit_rial' => $debit,
                'credit_rial' => $credit,
                'memo' => substr(trim((string) ($line['memo'] ?? '')), 0, 255),
            ];
        }
        if ($debits <= 0 || $debits !== $credits) {
            throw new \InvalidArgumentException('جمع بدهکار و بستانکار سند باید برابر و بزرگ‌تر از صفر باشد.');
        }
        $hash = hash('sha256', json_encode([
            'source_type' => (string) $sourceType,
            'source_id' => $sourceId ? (int) $sourceId : null,
            'description' => $description,
            'reversal_of_id' => $reversalOfId ? (int) $reversalOfId : null,
            'lines' => $cleanLines,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $started = !\Model::db()->inTransaction();
        if ($started) {
            \Model::begin();
        }
        try {
            \Model::execute(
                'INSERT INTO plugin_inventory_journals (event_key, source_type, source_id, description, request_hash, reversal_of_id, actor_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [$eventKey, substr((string) $sourceType, 0, 50), $sourceId ? (int) $sourceId : null, substr($description, 0, 255), $hash, $reversalOfId ? (int) $reversalOfId : null, $actorId ? (int) $actorId : null]
            );
            $journalId = (int) \Model::lastInsertId();
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_journals WHERE id = ? LIMIT 1', [$journalId]);
            if (!$existing || !hash_equals((string) $existing['request_hash'], $hash)) {
                throw new \RuntimeException('کلید تکراری دفترکل با محتوای متفاوت دریافت شد.');
            }
            $lineCount = (int) (\Model::fetch('SELECT COUNT(*) AS total FROM plugin_inventory_journal_lines WHERE journal_id = ?', [$journalId])['total'] ?? 0);
            if ($lineCount === 0) {
                foreach ($cleanLines as $line) {
                    \Model::execute(
                        'INSERT INTO plugin_inventory_journal_lines (journal_id, account_code, partner_id, debit_rial, credit_rial, memo)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [$journalId, $line['account_code'], $line['partner_id'], Money::db($line['debit_rial']), Money::db($line['credit_rial']), $line['memo'] ?: null]
                    );
                }
            }
            if ($started) {
                \Model::commit();
            }
            return $journalId;
        } catch (\Throwable $error) {
            if ($started) {
                \Model::rollBack();
            }
            throw $error;
        }
    }

    public static function reverse($journalId, $actorId, $reason)
    {
        $journalId = (int) $journalId;
        $reason = trim((string) $reason);
        if ($journalId <= 0 || $reason === '') {
            throw new \InvalidArgumentException('سند و علت معکوس‌سازی الزامی است.');
        }
        $original = \Model::fetch('SELECT * FROM plugin_inventory_journals WHERE id = ? LIMIT 1 FOR UPDATE', [$journalId]);
        if (!$original) {
            throw new \InvalidArgumentException('سند دفترکل پیدا نشد.');
        }
        $lines = \Model::fetchAll('SELECT * FROM plugin_inventory_journal_lines WHERE journal_id = ? ORDER BY id ASC', [$journalId]);
        if (!$lines) {
            throw new \RuntimeException('سند دفترکل ردیفی ندارد و قابل معکوس‌سازی نیست.');
        }
        $reversed = [];
        foreach ($lines as $line) {
            $reversed[] = [
                'account_code' => $line['account_code'],
                'partner_id' => $line['partner_id'],
                'debit_rial' => $line['credit_rial'],
                'credit_rial' => $line['debit_rial'],
                'memo' => 'معکوس سند ' . $journalId,
            ];
        }
        return self::post('reverse:' . $journalId, 'journal_reversal', $journalId, $reason, $actorId, $reversed, $journalId);
    }
}
