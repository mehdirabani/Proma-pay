<?php

/**
 * Controlled legal-cost boundary. New costs remain pending until an authorized
 * approval; historic log costs are imported by the updater as approved so no
 * existing receivable silently disappears.
 */
final class LegalCaseCostService
{
    private static ?bool $tableAvailable = null;
    private static ?bool $disbursementTableAvailable = null;

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

    public static function disbursementTableAvailable(): bool
    {
        if (self::$disbursementTableAvailable !== null) return self::$disbursementTableAvailable;
        try {
            self::$disbursementTableAvailable = (bool) Model::fetch("SHOW TABLES LIKE 'legal_cost_disbursements'");
        } catch (Throwable $e) {
            self::$disbursementTableAvailable = false;
        }
        return self::$disbursementTableAvailable;
    }

    /**
     * Record cash paid by the company to a third party. This is an expense
     * disbursement only; it never changes customer collection allocations.
     */
    public static function recordThirdPartyPayment(
        int $costId,
        int $amount,
        string $recipient,
        ?string $reference,
        string $paidAt,
        ?string $note,
        string $idempotencyKey,
        int $actorId
    ): array {
        if (!self::disbursementTableAvailable()) throw new RuntimeException('ساختار پرداخت به مرجع ثالث نصب نشده است.');
        $recipient = trim($recipient);
        $reference = trim((string) $reference);
        $note = trim((string) $note);
        $idempotencyKey = strtolower(trim($idempotencyKey));
        if ($costId <= 0 || $amount <= 0 || mb_strlen($recipient, 'UTF-8') < 2 || mb_strlen($recipient, 'UTF-8') > 190) {
            throw new InvalidArgumentException('مبلغ و نام مرجع پرداخت را درست وارد کنید.');
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) throw new InvalidArgumentException('شناسه یکتای درخواست معتبر نیست.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt) || !checkdate((int) substr($paidAt, 5, 2), (int) substr($paidAt, 8, 2), (int) substr($paidAt, 0, 4))) {
            throw new InvalidArgumentException('تاریخ پرداخت معتبر نیست.');
        }
        if (mb_strlen($reference, 'UTF-8') > 190 || mb_strlen($note, 'UTF-8') > 2000) throw new InvalidArgumentException('شرح یا شناسه مرجع بیش از حد مجاز است.');

        Model::begin();
        try {
            $matchesRequest = static function (array $existing) use ($costId, $amount, $recipient, $reference, $paidAt): bool {
                return (int) $existing['legal_case_cost_id'] === $costId
                    && normalize_money($existing['amount_toman']) === $amount
                    && (string) $existing['recipient'] === $recipient
                    && (string) ($existing['payment_reference'] ?? '') === $reference
                    && (string) $existing['paid_at'] === $paidAt;
            };
            $existing = Model::fetch('SELECT * FROM legal_cost_disbursements WHERE idempotency_key = ?', [$idempotencyKey]);
            if ($existing) {
                if (!$matchesRequest($existing)) throw new InvalidArgumentException('شناسه درخواست قبلاً برای پرداخت دیگری استفاده شده است.', 409);
                Model::commit();
                return $existing;
            }
            $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
            if (!$cost || !empty($cost['reversed_at']) || ($cost['approval_status'] ?? '') === 'reversed') {
                throw new InvalidArgumentException('هزینه برای ثبت پرداخت مرجع ثالث معتبر نیست.', 409);
            }
            if (($cost['approval_status'] ?? '') !== 'approved') {
                throw new InvalidArgumentException('پرداخت به مرجع فقط پس از تأیید هزینه توسط مدیریت مجاز است.', 409);
            }
            if (!empty($cost['reimburse_lawyer_id'])) {
                throw new InvalidArgumentException('این هزینه برای بازپرداخت به وکیل ثبت شده و پرداخت دوباره به مرجع از طرف مجموعه مجاز نیست.', 409);
            }
            // Serialize same-cost requests on the cost row before the second
            // idempotency lookup; this avoids gap-lock races on MySQL 5.7.
            $existing = Model::fetch('SELECT * FROM legal_cost_disbursements WHERE idempotency_key = ? FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if (!$matchesRequest($existing)) throw new InvalidArgumentException('شناسه درخواست قبلاً برای پرداخت دیگری استفاده شده است.', 409);
                Model::commit();
                return $existing;
            }
            $sum = Model::fetch('SELECT COALESCE(SUM(amount_toman), 0) AS total FROM legal_cost_disbursements WHERE legal_case_cost_id = ?', [$costId]);
            $paid = normalize_money($sum['total'] ?? 0);
            if ($paid < 0) throw new InvalidArgumentException('گردش پرداخت مرجع نامعتبر است؛ ابتدا سابقه پرداخت و برگشت این هزینه بررسی شود.', 409);
            $remaining = max(0, normalize_money($cost['amount_toman'] ?? 0) - $paid);
            if ($amount > $remaining) throw new InvalidArgumentException('مبلغ پرداختی از مانده هزینهٔ ثبت‌شده بیشتر است.', 409);

            Model::execute(
                'INSERT INTO legal_cost_disbursements (disbursement_uuid, legal_case_cost_id, contract_id, amount_toman, recipient, payment_reference, paid_at, note, idempotency_key, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                ['LCP-' . strtoupper(bin2hex(random_bytes(20))), $costId, (int) $cost['contract_id'], $amount,
                 $recipient, $reference !== '' ? $reference : null, $paidAt, $note !== '' ? $note : null,
                 $idempotencyKey, $actorId > 0 ? $actorId : null]
            );
            $row = Model::fetch('SELECT * FROM legal_cost_disbursements WHERE id = ?', [(int) Model::lastInsertId()]);
            self::audit('legal_cost_third_party_payment_recorded', $cost, [
                'disbursement_id' => (int) ($row['id'] ?? 0), 'amount_toman' => $amount,
                'recipient' => $recipient, 'payment_reference' => $reference,
                'paid_at' => $paidAt, 'customer_receivable_changed' => false,
            ], $actorId);
            Model::commit();
            return $row ?: [];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Bounded ledger view for the current legal-cost page. */
    public static function thirdPartyPaymentsByCost(array $costIds): array
    {
        if (!self::disbursementTableAvailable()) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $costIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $totals = Model::fetchAll(
            "SELECT legal_case_cost_id, COALESCE(SUM(amount_toman), 0) AS total
             FROM legal_cost_disbursements WHERE legal_case_cost_id IN ({$placeholders})
             GROUP BY legal_case_cost_id",
            $ids
        );
        $result = [];
        foreach ($totals as $row) $result[(int) $row['legal_case_cost_id']] = ['total' => normalize_money($row['total'] ?? 0), 'items' => []];
        $items = Model::fetchAll(
            "SELECT d.*, u.full_name AS created_by_name
             FROM legal_cost_disbursements d LEFT JOIN users u ON u.id = d.created_by
             WHERE d.legal_case_cost_id IN ({$placeholders}) ORDER BY d.id DESC LIMIT 1000",
            $ids
        );
        foreach ($items as $row) $result[(int) $row['legal_case_cost_id']]['items'][] = $row;
        return $result;
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
            if ($existing) self::assertNoOutstandingThirdPartyDisbursements((int) $existing['id']);
            if ($existing) Model::execute("UPDATE legal_case_costs SET approval_status = 'reversed', reversed_at = NOW(), reversal_reason = ? WHERE id = ?", ['حذف مبلغ از اقدام حقوقی پیش از تأیید', (int) $existing['id']]);
            return;
        }
        $reimburseLawyerId = array_key_exists('reimburse_lawyer_id', $payload)
            ? (!empty($payload['reimburse_lawyer_id']) ? (int) $payload['reimburse_lawyer_id'] : null)
            : (!empty($existing['reimburse_lawyer_id']) ? (int) $existing['reimburse_lawyer_id'] : null);
        if ($reimburseLawyerId && $existing) self::assertNoCompanyDisbursements((int) $existing['id']);
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
            $reimburseLawyerId,
        ];
        if ($existing && ($existing['approval_status'] ?? '') === 'approved') {
            if (normalize_money($existing['amount_toman'] ?? 0) !== $amount || (string) ($existing['cost_date'] ?? '') !== $values[6]) {
                throw new InvalidArgumentException('هزینه تأییدشده فقط با ثبت برگشت و هزینه جدید قابل اصلاح است.');
            }
            return;
        }
        if ($existing) {
            Model::execute(
                "UPDATE legal_case_costs SET legal_case_id = ?, category = ?, title = ?, amount_toman = ?, cost_date = ?, description = ?, reimburse_lawyer_id = ?, approval_status = 'pending_approval', reversed_at = NULL, reversal_reason = NULL WHERE id = ?",
                [$values[0], $values[3], $values[4], $values[5], $values[6], $values[7], $values[9], (int) $existing['id']]
            );
            return;
        }
        Model::execute(
            "INSERT INTO legal_case_costs (cost_uuid, legal_case_id, contract_id, source_legal_log_id, category, title, amount_toman, cost_date, payment_status, approval_status, paid_by, reimburse_lawyer_id, chargeable_to_customer, description, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending_approval', NULL, ?, 1, ?, ?, NOW())",
            ['LCC-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5))), $values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $values[6], $values[9], $values[7], $values[8]]
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
               AND EXISTS (
                   SELECT 1 FROM legal_cases lc
                   WHERE lc.id = legal_case_costs.legal_case_id
                     AND lc.contract_id = legal_case_costs.contract_id
                     AND lc.legal_referred_at IS NOT NULL
               )
             ORDER BY cost_date ASC, id ASC" . ($forUpdate ? ' FOR UPDATE' : ''),
            [$contractId]
        );
    }

    /** Customer-visible receivables grouped by contract for direct payment from the installment portal. */
    public static function outstandingChargeableForCustomer(int $customerId): array
    {
        if (!self::tableAvailable() || $customerId <= 0) return [];
        return Model::fetchAll(
            "SELECT costs.*, contracts.contract_number
             FROM legal_case_costs costs
             JOIN contracts ON contracts.id = costs.contract_id
             WHERE contracts.customer_id = ?
               AND contracts.status != 'cancelled'
               AND costs.approval_status = 'approved'
               AND costs.chargeable_to_customer = 1
               AND costs.payment_status NOT IN ('reimbursed', 'cancelled')
               AND costs.reversed_at IS NULL
               AND costs.amount_toman > COALESCE(costs.paid_amount_toman, 0)
               AND EXISTS (
                   SELECT 1 FROM legal_cases lc
                   WHERE lc.id = costs.legal_case_id
                     AND lc.contract_id = costs.contract_id
                     AND lc.legal_referred_at IS NOT NULL
               )
             ORDER BY costs.contract_id ASC, costs.cost_date ASC, costs.id ASC",
            [$customerId]
        );
    }

    public static function recordPaymentAllocation(int $costId, int $groupId, int $paymentId, int $contractId, int $amount, int $actorId): void
    {
        if (!self::tableAvailable() || $amount <= 0) return;
        $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? AND contract_id = ? FOR UPDATE', [$costId, $contractId]);
        $courtReferral = $cost && !empty($cost['legal_case_id'])
            ? Model::fetch(
                'SELECT id FROM legal_cases WHERE id = ? AND contract_id = ? AND legal_referred_at IS NOT NULL FOR UPDATE',
                [(int) $cost['legal_case_id'], (int) $contractId]
            )
            : null;
        if (!$cost || !$courtReferral || ($cost['approval_status'] ?? '') !== 'approved' || (int) ($cost['chargeable_to_customer'] ?? 0) !== 1 || !empty($cost['reversed_at'])) {
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

    public static function reverseThirdPartyPayment(int $disbursementId, string $reason, int $actorId): void
    {
        if (!self::disbursementTableAvailable()) throw new RuntimeException('ساختار پرداخت به مرجع ثالث نصب نشده است.');
        $reason = trim($reason);
        if ($disbursementId <= 0 || mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت برگشت پرداخت را کامل وارد کنید.');
        Model::begin();
        try {
            $payment = Model::fetch('SELECT * FROM legal_cost_disbursements WHERE id = ? AND is_reversal = 0 FOR UPDATE', [$disbursementId]);
            if (!$payment) throw new InvalidArgumentException('پرداخت مرجع ثالث برای برگشت پیدا نشد.', 404);
            $alreadyReversed = Model::fetch('SELECT id FROM legal_cost_disbursements WHERE reversal_of_id = ? LIMIT 1 FOR UPDATE', [$disbursementId]);
            if ($alreadyReversed) throw new InvalidArgumentException('این پرداخت قبلاً برگشت خورده است.', 409);
            Model::execute(
                'INSERT INTO legal_cost_disbursements (disbursement_uuid, legal_case_cost_id, contract_id, amount_toman, recipient, payment_reference, paid_at, note, idempotency_key, is_reversal, reversal_of_id, reversal_reason, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())',
                ['LCP-R-' . strtoupper(bin2hex(random_bytes(20))), (int) $payment['legal_case_cost_id'], (int) $payment['contract_id'], -abs(normalize_money($payment['amount_toman'])),
                 (string) $payment['recipient'], $payment['payment_reference'] ?? null, date('Y-m-d'), null,
                 'reversal:' . $disbursementId . ':' . bin2hex(random_bytes(12)), $disbursementId, $reason, $actorId > 0 ? $actorId : null]
            );
            self::audit('legal_cost_third_party_payment_reversed', $payment, [
                'reversal_of_id' => $disbursementId, 'amount_toman' => -abs(normalize_money($payment['amount_toman'])),
                'reason' => $reason, 'customer_receivable_changed' => false,
            ], $actorId);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function approve(int $costId, int $actorId): void
    {
        if (!self::tableAvailable()) throw new RuntimeException('ساختار هزینه حقوقی هنوز بروزرسانی نشده است.');
        Model::begin();
        try {
            $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
            if (!$cost || ($cost['approval_status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('این هزینه در وضعیت قابل تأیید نیست.');
            Model::execute("UPDATE legal_case_costs SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?", [$actorId, $costId]);
            FinancialLedgerService::approveLegalCost($cost, $actorId);
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
            self::assertNoOutstandingThirdPartyDisbursements($costId);
            FinancialLedgerService::reverseLegalCost($cost, $actorId, $reason);
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
        try {
            AuditLog::record('legal_cost', $action, 'legal_case_cost', (int) $before['id'], [
                'actor_user_id' => $actorId,
                'contract_id' => (int) $before['contract_id'],
                'old_values' => $before,
                'new_values' => $after,
            ]);
        } catch (Throwable $e) {
            // Audit storage trouble must not roll back an otherwise valid
            // financial allocation or disbursement.
            if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('legal_cost.audit', $e);
        }
    }

    private static function assertNoOutstandingThirdPartyDisbursements(int $costId): void
    {
        LegalLawyerSettlementService::assertCostReversible($costId);
        if (!self::disbursementTableAvailable()) return;
        $row = Model::fetch('SELECT COALESCE(SUM(amount_toman), 0) AS total FROM legal_cost_disbursements WHERE legal_case_cost_id = ?', [$costId]);
        if (normalize_money($row['total'] ?? 0) > 0) {
            throw new InvalidArgumentException('ابتدا پرداخت‌های ثبت‌شده به مرجع ثالث را با رویداد برگشت تطبیق دهید.');
        }
    }

    private static function assertNoCompanyDisbursements(int $costId): void
    {
        if (!self::disbursementTableAvailable()) return;
        $row = Model::fetch('SELECT COALESCE(SUM(amount_toman), 0) AS total FROM legal_cost_disbursements WHERE legal_case_cost_id = ?', [$costId]);
        if (normalize_money($row['total'] ?? 0) > 0) {
            throw new InvalidArgumentException('برای این هزینه قبلاً پرداخت مجموعه به مرجع ثبت شده است؛ پرداخت دوباره به وکیل مجاز نیست.');
        }
    }
}
