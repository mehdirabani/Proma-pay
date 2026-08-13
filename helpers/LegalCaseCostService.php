<?php

/**
 * Controlled legal-cost boundary. New costs remain pending until an authorized
 * approval; historic log costs are imported by the updater as approved so no
 * existing receivable silently disappears.
 */
final class LegalCaseCostService
{
    private static ?bool $tableAvailable = null;

    public static function tableAvailable(): bool
    {
        if (self::$tableAvailable !== null) return self::$tableAvailable;
        try {
            self::$tableAvailable = (bool) Model::fetch("SHOW TABLES LIKE 'legal_case_costs'");
        } catch (Throwable $e) {
            self::$tableAvailable = false;
        }
        return self::$tableAvailable;
    }

    /** Create or synchronize the provisional cost associated with a legal log. */
    public static function syncFromLegalLog(int $logId, array $payload, string $title, ?int $actorId = null): void
    {
        if (!self::tableAvailable()) return;
        $amount = normalize_money($payload['cost_amount'] ?? 0);
        $existing = Model::fetch('SELECT * FROM legal_case_costs WHERE source_legal_log_id = ?', [$logId]);
        if ($amount <= 0) {
            if ($existing && in_array((string) ($existing['approval_status'] ?? ''), ['approved', 'reversed'], true)) {
                throw new InvalidArgumentException('هزینه تأییدشده فقط با گردش برگشت قابل اصلاح است.');
            }
            if ($existing) Model::execute("UPDATE legal_case_costs SET approval_status = 'reversed', reversed_at = NOW(), reversal_reason = ? WHERE id = ?", ['حذف مبلغ از اقدام حقوقی پیش از تأیید', (int) $existing['id']]);
            return;
        }
        $values = [
            (int) ($payload['legal_case_id'] ?? 0) ?: null,
            (int) ($payload['contract_id'] ?? 0),
            $logId,
            trim((string) ($payload['cost_type'] ?? 'سایر')) ?: 'سایر',
            mb_substr(trim($title) ?: 'هزینه حقوقی', 0, 190),
            $amount,
            (string) ($payload['action_date'] ?? date('Y-m-d')),
            trim((string) ($payload['description'] ?? '')) ?: null,
            $actorId ?: null,
        ];
        if ($existing && ($existing['approval_status'] ?? '') === 'approved') {
            if (normalize_money($existing['amount_toman'] ?? 0) !== $amount || (string) ($existing['cost_date'] ?? '') !== $values[6]) {
                throw new InvalidArgumentException('هزینه تأییدشده فقط با ثبت برگشت و هزینه جدید قابل اصلاح است.');
            }
            return;
        }
        if ($existing) {
            Model::execute(
                "UPDATE legal_case_costs SET legal_case_id = ?, category = ?, title = ?, amount_toman = ?, cost_date = ?, description = ?, approval_status = 'pending_approval', reversed_at = NULL, reversal_reason = NULL WHERE id = ?",
                [$values[0], $values[3], $values[4], $values[5], $values[6], $values[7], (int) $existing['id']]
            );
            return;
        }
        Model::execute(
            "INSERT INTO legal_case_costs (cost_uuid, legal_case_id, contract_id, source_legal_log_id, category, title, amount_toman, cost_date, payment_status, approval_status, paid_by, chargeable_to_customer, description, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending_approval', NULL, 1, ?, ?, NOW())",
            ['LCC-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5))), $values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $values[6], $values[7], $values[8]]
        );
    }

    public static function assertLogSyncAllowed(int $logId, array $payload): void
    {
        if (!self::tableAvailable()) return;
        $existing = Model::fetch('SELECT * FROM legal_case_costs WHERE source_legal_log_id = ?', [$logId]);
        if (!$existing || ($existing['approval_status'] ?? '') !== 'approved') return;
        $amount = normalize_money($payload['cost_amount'] ?? 0);
        $date = (string) ($payload['action_date'] ?? '');
        if ($amount <= 0 || normalize_money($existing['amount_toman'] ?? 0) !== $amount || (string) ($existing['cost_date'] ?? '') !== $date) {
            throw new InvalidArgumentException('هزینه تأییدشده فقط با ثبت برگشت و هزینه جدید قابل اصلاح است.');
        }
    }

    public static function forContract(int $contractId): array
    {
        if (!self::tableAvailable()) return [];
        return Model::fetchAll(
            'SELECT c.*, u.full_name AS created_by_name, a.full_name AS approved_by_name
             FROM legal_case_costs c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN users a ON a.id = c.approved_by
             WHERE c.contract_id = ? ORDER BY c.cost_date DESC, c.id DESC',
            [$contractId]
        );
    }

    /** Bounded payable cost rows, used only by a contract-wide settlement. */
    public static function outstandingChargeableForContract(int $contractId, bool $forUpdate = false): array
    {
        if (!self::tableAvailable()) return [];
        return Model::fetchAll(
            "SELECT * FROM legal_case_costs
             WHERE contract_id = ?
               AND approval_status = 'approved'
               AND chargeable_to_customer = 1
               AND payment_status NOT IN ('reimbursed', 'cancelled')
               AND reversed_at IS NULL
               AND amount_toman > COALESCE(paid_amount_toman, 0)
             ORDER BY cost_date ASC, id ASC" . ($forUpdate ? ' FOR UPDATE' : ''),
            [$contractId]
        );
    }

    public static function recordPaymentAllocation(int $costId, int $groupId, int $paymentId, int $contractId, int $amount, int $actorId): void
    {
        if (!self::tableAvailable() || $amount <= 0) return;
        $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? AND contract_id = ? FOR UPDATE', [$costId, $contractId]);
        if (!$cost || ($cost['approval_status'] ?? '') !== 'approved' || (int) ($cost['chargeable_to_customer'] ?? 0) !== 1 || !empty($cost['reversed_at'])) {
            throw new InvalidArgumentException('هزینه حقوقی انتخاب‌شده دیگر قابل دریافت نیست.', 409);
        }
        $available = max(0, normalize_money($cost['amount_toman'] ?? 0) - normalize_money($cost['paid_amount_toman'] ?? 0));
        if ($amount > $available) throw new InvalidArgumentException('مبلغ تخصیص هزینه حقوقی معتبر نیست.', 409);
        Model::execute(
            'INSERT INTO legal_cost_payment_allocations (payment_group_id, payment_id, legal_case_cost_id, contract_id, allocated_amount_toman, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$groupId, $paymentId, $costId, $contractId, $amount]
        );
        Model::execute(
            "UPDATE legal_case_costs SET paid_amount_toman = paid_amount_toman + ?, payment_status = CASE WHEN paid_amount_toman + ? >= amount_toman THEN 'paid' ELSE 'partial' END, paid_by = ? WHERE id = ?",
            [$amount, $amount, $actorId ?: null, $costId]
        );
        self::audit('legal_cost_payment_allocated', $cost, ['payment_id' => $paymentId, 'amount_toman' => $amount], $actorId);
    }

    public static function reversePaymentAllocations(int $paymentId, int $actorId, string $reason): void
    {
        if (!self::tableAvailable()) return;
        $rows = Model::fetchAll('SELECT * FROM legal_cost_payment_allocations WHERE payment_id = ? AND is_reversal = 0 FOR UPDATE', [$paymentId]);
        foreach ($rows as $row) {
            $exists = Model::fetch('SELECT id FROM legal_cost_payment_allocations WHERE reversal_of_allocation_id = ? LIMIT 1', [(int) $row['id']]);
            if ($exists) continue;
            $amount = normalize_money($row['allocated_amount_toman'] ?? 0);
            Model::execute(
                'INSERT INTO legal_cost_payment_allocations (payment_group_id, payment_id, legal_case_cost_id, contract_id, allocated_amount_toman, reversal_of_allocation_id, is_reversal, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
                [(int) $row['payment_group_id'], $paymentId, (int) $row['legal_case_cost_id'], (int) $row['contract_id'], -$amount, (int) $row['id']]
            );
            Model::execute("UPDATE legal_case_costs SET paid_amount_toman = GREATEST(0, paid_amount_toman - ?), payment_status = CASE WHEN paid_amount_toman - ? <= 0 THEN 'pending' ELSE 'partial' END WHERE id = ?", [$amount, $amount, (int) $row['legal_case_cost_id']]);
        }
        if ($rows) self::audit('legal_cost_payment_reversed', ['id' => 0, 'contract_id' => (int) $rows[0]['contract_id']], ['payment_id' => $paymentId, 'reason' => $reason], $actorId);
    }

    public static function approve(int $costId, int $actorId): void
    {
        if (!self::tableAvailable()) throw new RuntimeException('ساختار هزینه حقوقی هنوز بروزرسانی نشده است.');
        Model::begin();
        try {
            $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
            if (!$cost || ($cost['approval_status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('این هزینه در وضعیت قابل تأیید نیست.');
            Model::execute("UPDATE legal_case_costs SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?", [$actorId, $costId]);
            Model::commit();
            self::audit('legal_cost_approved', $cost, ['approval_status' => 'approved'], $actorId);
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reverse(int $costId, string $reason, int $actorId): void
    {
        if (!self::tableAvailable()) throw new RuntimeException('ساختار هزینه حقوقی هنوز بروزرسانی نشده است.');
        $reason = trim($reason);
        if (mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت برگشت هزینه را کامل وارد کنید.');
        Model::begin();
        try {
            $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
            if (!$cost || ($cost['approval_status'] ?? '') === 'reversed') throw new InvalidArgumentException('هزینه برای برگشت معتبر نیست.');
            Model::execute("UPDATE legal_case_costs SET approval_status = 'reversed', reversed_at = NOW(), reversal_reason = ? WHERE id = ?", [$reason, $costId]);
            Model::commit();
            self::audit('legal_cost_reversed', $cost, ['approval_status' => 'reversed', 'reason' => $reason], $actorId);
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function audit(string $action, array $before, array $after, int $actorId): void
    {
        if (!class_exists('AuditLog')) return;
        AuditLog::record('legal_cost', $action, 'legal_case_cost', (int) $before['id'], [
            'actor_user_id' => $actorId,
            'contract_id' => (int) $before['contract_id'],
            'old_values' => $before,
            'new_values' => $after,
        ]);
    }
}
