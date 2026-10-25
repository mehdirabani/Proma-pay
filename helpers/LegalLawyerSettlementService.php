<?php

/**
 * Payable ledger for lawyers who have paid approved legal expenses on behalf
 * of the company. Entries here never modify the customer's cost collection.
 */
final class LegalLawyerSettlementService
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready !== null) return self::$ready;
        try {
            self::$ready = (bool) Model::fetch("SHOW TABLES LIKE 'legal_lawyer_payment_batches'")
                && (bool) Model::fetch("SHOW TABLES LIKE 'legal_lawyer_payment_allocations'")
                && (bool) Model::fetch("SHOW TABLES LIKE 'legal_lawyer_adjustments'")
                && (bool) Model::fetch("SHOW COLUMNS FROM legal_case_costs LIKE 'reimburse_lawyer_id'");
        } catch (Throwable $e) {
            self::$ready = false;
        }
        return self::$ready;
    }

    /** @return array<int, array<string, mixed>> */
    public static function lawyerSummaries(): array
    {
        if (!self::ready()) return [];
        return Model::fetchAll(
            "SELECT u.id AS lawyer_id, u.full_name AS lawyer_name, u.status AS lawyer_status,
                    COALESCE(cl.claimed_toman, 0) AS claimed_toman,
                    COALESCE(pm.paid_toman, 0) AS paid_toman,
                    GREATEST(0, COALESCE(cl.claimed_toman, 0) - COALESCE(pm.paid_toman, 0)) AS pending_reimbursement_toman,
                    COALESCE(ad.credit_toman, 0) AS credit_toman,
                    COALESCE(ad.debit_toman, 0) AS debit_toman,
                    COALESCE(ad.adjustment_toman, 0) AS adjustment_toman,
                    GREATEST(0, COALESCE(cl.claimed_toman, 0) - COALESCE(pm.paid_toman, 0)) + COALESCE(ad.adjustment_toman, 0) AS balance_toman
             FROM users u
             LEFT JOIN (
                 SELECT reimburse_lawyer_id AS lawyer_id, SUM(amount_toman) AS claimed_toman
                 FROM legal_case_costs
                 WHERE reimburse_lawyer_id IS NOT NULL AND approval_status = 'approved'
                   AND payment_status NOT IN ('reimbursed', 'cancelled') AND reversed_at IS NULL
                 GROUP BY reimburse_lawyer_id
             ) cl ON cl.lawyer_id = u.id
             LEFT JOIN (
                 SELECT b.lawyer_id, SUM(a.allocated_amount_toman) AS paid_toman
                 FROM legal_lawyer_payment_batches b
                 JOIN legal_lawyer_payment_allocations a ON a.payment_batch_id = b.id
                 GROUP BY b.lawyer_id
             ) pm ON pm.lawyer_id = u.id
             LEFT JOIN (
                 SELECT e.lawyer_id,
                        SUM(CASE WHEN e.entry_type = 'credit' THEN e.signed_amount_toman WHEN e.entry_type = 'reversal' AND original.entry_type = 'credit' THEN e.signed_amount_toman ELSE 0 END) AS credit_toman,
                        SUM(CASE WHEN e.entry_type = 'debit' THEN -e.signed_amount_toman WHEN e.entry_type = 'reversal' AND original.entry_type = 'debit' THEN -e.signed_amount_toman ELSE 0 END) AS debit_toman,
                        SUM(e.signed_amount_toman) AS adjustment_toman
                 FROM legal_lawyer_adjustments e
                 LEFT JOIN legal_lawyer_adjustments original ON original.id = e.reversal_of_id
                 GROUP BY e.lawyer_id
             ) ad ON ad.lawyer_id = u.id
             WHERE u.role = 'lawyer'
             ORDER BY u.status = 'active' DESC, u.full_name ASC"
        );
    }

    public static function lawyerSummary(int $lawyerId): ?array
    {
        foreach (self::lawyerSummaries() as $summary) {
            if ((int) $summary['lawyer_id'] === $lawyerId) return $summary;
        }
        return null;
    }

    /** Approved, unreversed claim amounts that have not yet been settled. */
    public static function outstandingCosts(int $lawyerId): array
    {
        if (!self::ready() || $lawyerId <= 0) return [];
        return Model::fetchAll(
            "SELECT c.id, c.legal_case_id, c.contract_id, c.title, c.category, c.amount_toman,
                    c.cost_date, c.description, co.contract_number, cu.full_name AS customer_name,
                    COALESCE(paid.paid_toman, 0) AS lawyer_paid_toman,
                    GREATEST(0, c.amount_toman - COALESCE(paid.paid_toman, 0)) AS outstanding_toman
             FROM legal_case_costs c
             JOIN contracts co ON co.id = c.contract_id
             LEFT JOIN users cu ON cu.id = co.customer_id
             LEFT JOIN (
                 SELECT a.legal_case_cost_id, SUM(a.allocated_amount_toman) AS paid_toman
                 FROM legal_lawyer_payment_allocations a
                 JOIN legal_lawyer_payment_batches b ON b.id = a.payment_batch_id
                 GROUP BY a.legal_case_cost_id
             ) paid ON paid.legal_case_cost_id = c.id
             WHERE c.reimburse_lawyer_id = ? AND c.approval_status = 'approved'
               AND c.payment_status NOT IN ('reimbursed', 'cancelled')
               AND c.reversed_at IS NULL AND c.amount_toman > COALESCE(paid.paid_toman, 0)
             ORDER BY c.cost_date ASC, c.id ASC LIMIT 500",
            [$lawyerId]
        );
    }

    public static function paymentBatches(int $lawyerId): array
    {
        if (!self::ready() || $lawyerId <= 0) return [];
        $batches = Model::fetchAll(
            "SELECT b.*, actor.full_name AS created_by_name,
                    original.paid_at AS original_paid_at,
                    (SELECT COUNT(*) FROM legal_lawyer_payment_allocations a WHERE a.payment_batch_id = b.id) AS allocation_count,
                    EXISTS(SELECT 1 FROM legal_lawyer_payment_batches r WHERE r.reversal_of_batch_id = b.id) AS has_reversal
             FROM legal_lawyer_payment_batches b
             LEFT JOIN users actor ON actor.id = b.created_by
             LEFT JOIN legal_lawyer_payment_batches original ON original.id = b.reversal_of_batch_id
             WHERE b.lawyer_id = ? ORDER BY b.id DESC LIMIT 100",
            [$lawyerId]
        );
        if (!$batches) return [];
        $ids = array_map(static fn (array $batch): int => (int) $batch['id'], $batches);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $allocations = Model::fetchAll(
            "SELECT a.payment_batch_id, a.legal_case_cost_id, a.allocated_amount_toman, c.title, c.category, co.contract_number
             FROM legal_lawyer_payment_allocations a
             JOIN legal_case_costs c ON c.id = a.legal_case_cost_id
             LEFT JOIN contracts co ON co.id = c.contract_id
             WHERE a.payment_batch_id IN ({$placeholders}) ORDER BY a.payment_batch_id DESC, a.id ASC",
            $ids
        );
        $byBatch = [];
        foreach ($allocations as $allocation) $byBatch[(int) $allocation['payment_batch_id']][] = $allocation;
        foreach ($batches as &$batch) $batch['allocations'] = $byBatch[(int) $batch['id']] ?? [];
        unset($batch);
        return $batches;
    }

    public static function adjustments(int $lawyerId): array
    {
        if (!self::ready() || $lawyerId <= 0) return [];
        return Model::fetchAll(
            "SELECT e.*, actor.full_name AS created_by_name, original.entry_type AS reversed_entry_type,
                    EXISTS(SELECT 1 FROM legal_lawyer_adjustments r WHERE r.reversal_of_id = e.id) AS has_reversal
             FROM legal_lawyer_adjustments e
             LEFT JOIN users actor ON actor.id = e.created_by
             LEFT JOIN legal_lawyer_adjustments original ON original.id = e.reversal_of_id
             WHERE e.lawyer_id = ? ORDER BY e.id DESC LIMIT 100",
            [$lawyerId]
        );
    }

    /**
     * Register one real-world transfer to a lawyer, allocating it across any
     * number of approved cost claims in one transaction.
     *
     * @param array<int, array{cost_id:int,amount_toman:int}> $allocations
     */
    public static function recordPayment(
        int $lawyerId,
        array $allocations,
        string $paidAt,
        string $reference,
        string $note,
        string $idempotencyKey,
        int $actorId
    ): array {
        if (!self::ready()) throw new RuntimeException('ساختار تسویه حساب وکلا نصب نشده است.');
        $reference = trim($reference);
        $note = trim($note);
        $idempotencyKey = strtolower(trim($idempotencyKey));
        if ($lawyerId <= 0 || count($allocations) < 1 || count($allocations) > 500) {
            throw new InvalidArgumentException('وکیل و حداقل یک هزینه برای پرداخت انتخاب کنید.');
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) throw new InvalidArgumentException('شناسه یکتای درخواست معتبر نیست.');
        self::validateDate($paidAt);
        if (mb_strlen($reference, 'UTF-8') > 190 || mb_strlen($note, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('شناسه پیگیری یا شرح پرداخت بیش از حد مجاز است.');
        }

        $normalized = [];
        foreach ($allocations as $allocation) {
            $costId = (int) ($allocation['cost_id'] ?? 0);
            $amount = normalize_money($allocation['amount_toman'] ?? 0);
            if ($costId <= 0 || $amount <= 0 || isset($normalized[$costId])) {
                throw new InvalidArgumentException('فهرست هزینه‌های انتخاب‌شده معتبر نیست.');
            }
            $normalized[$costId] = $amount;
        }
        ksort($normalized, SORT_NUMERIC);
        $total = array_sum($normalized);
        if ($total <= 0) throw new InvalidArgumentException('مبلغ پرداخت باید بیشتر از صفر باشد.');
        $fingerprint = hash('sha256', json_encode([
            'lawyer_id' => $lawyerId, 'allocations' => $normalized,
            'paid_at' => $paidAt, 'reference' => $reference, 'note' => $note,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        Model::begin();
        try {
            $existing = Model::fetch('SELECT * FROM legal_lawyer_payment_batches WHERE idempotency_key = ? FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if ((int) $existing['lawyer_id'] !== $lawyerId || !hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    throw new InvalidArgumentException('شناسه درخواست قبلاً برای تسویه دیگری استفاده شده است.', 409);
                }
                Model::commit();
                return $existing;
            }
            $lawyer = Model::fetch("SELECT id FROM users WHERE id = ? AND role = 'lawyer' FOR UPDATE", [$lawyerId]);
            if (!$lawyer) throw new InvalidArgumentException('حساب وکیل انتخاب‌شده معتبر نیست.', 404);
            $existing = Model::fetch('SELECT * FROM legal_lawyer_payment_batches WHERE idempotency_key = ? FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if ((int) $existing['lawyer_id'] !== $lawyerId || !hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    throw new InvalidArgumentException('شناسه درخواست قبلاً برای تسویه دیگری استفاده شده است.', 409);
                }
                Model::commit();
                return $existing;
            }

            foreach ($normalized as $costId => $amount) {
                $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
                if (!$cost || (int) ($cost['reimburse_lawyer_id'] ?? 0) !== $lawyerId
                    || ($cost['approval_status'] ?? '') !== 'approved'
                    || in_array((string) ($cost['payment_status'] ?? ''), ['reimbursed', 'cancelled'], true)
                    || !empty($cost['reversed_at'])) {
                    throw new InvalidArgumentException('یکی از هزینه‌ها برای تسویه این وکیل تأییدشده یا معتبر نیست.', 409);
                }
                if (LegalCaseCostService::disbursementTableAvailable()) {
                    $companyPayments = Model::fetch('SELECT COALESCE(SUM(amount_toman), 0) AS total FROM legal_cost_disbursements WHERE legal_case_cost_id = ?', [$costId]);
                    if (normalize_money($companyPayments['total'] ?? 0) > 0) {
                        throw new InvalidArgumentException('برای یکی از هزینه‌ها قبلاً پرداخت مجموعه به مرجع ثبت شده است.', 409);
                    }
                }
                $paid = Model::fetch(
                    'SELECT COALESCE(SUM(a.allocated_amount_toman), 0) AS total
                     FROM legal_lawyer_payment_allocations a WHERE a.legal_case_cost_id = ?',
                    [$costId]
                );
                $outstanding = max(0, normalize_money($cost['amount_toman'] ?? 0) - (int) ($paid['total'] ?? 0));
                if ($amount > $outstanding) throw new InvalidArgumentException('مبلغ پرداخت از مانده همان هزینه بیشتر است.', 409);
            }

            Model::execute(
                'INSERT INTO legal_lawyer_payment_batches
                 (batch_uuid, lawyer_id, amount_toman, paid_at, payment_reference, note, idempotency_key, request_fingerprint, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                ['LLP-' . strtoupper(bin2hex(random_bytes(20))), $lawyerId, $total, $paidAt,
                 $reference !== '' ? $reference : null, $note !== '' ? $note : null,
                 $idempotencyKey, $fingerprint, $actorId > 0 ? $actorId : null]
            );
            $batchId = (int) Model::lastInsertId();
            foreach ($normalized as $costId => $amount) {
                Model::execute(
                    'INSERT INTO legal_lawyer_payment_allocations (payment_batch_id, legal_case_cost_id, allocated_amount_toman, created_at) VALUES (?, ?, ?, NOW())',
                    [$batchId, $costId, $amount]
                );
            }
            self::audit('legal_lawyer_payment_recorded', $lawyerId, $batchId, [
                'amount_toman' => $total, 'cost_allocations' => $normalized,
                'paid_at' => $paidAt, 'payment_reference' => $reference,
                'customer_receivables_changed' => false,
            ], $actorId);
            $row = Model::fetch('SELECT * FROM legal_lawyer_payment_batches WHERE id = ?', [$batchId]);
            Model::commit();
            return $row ?: [];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reversePayment(int $batchId, string $reason, int $actorId): void
    {
        if (!self::ready()) throw new RuntimeException('ساختار تسویه حساب وکلا نصب نشده است.');
        $reason = trim($reason);
        if ($batchId <= 0 || mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت برگشت پرداخت را کامل وارد کنید.');
        Model::begin();
        try {
            $batch = Model::fetch('SELECT * FROM legal_lawyer_payment_batches WHERE id = ? AND is_reversal = 0 FOR UPDATE', [$batchId]);
            if (!$batch) throw new InvalidArgumentException('پرداخت وکیل برای برگشت پیدا نشد.', 404);
            if (Model::fetch('SELECT id FROM legal_lawyer_payment_batches WHERE reversal_of_batch_id = ? LIMIT 1 FOR UPDATE', [$batchId])) {
                throw new InvalidArgumentException('این پرداخت وکیل قبلاً برگشت خورده است.', 409);
            }
            $allocations = Model::fetchAll('SELECT * FROM legal_lawyer_payment_allocations WHERE payment_batch_id = ? ORDER BY id ASC FOR UPDATE', [$batchId]);
            if (!$allocations) throw new InvalidArgumentException('تخصیص‌های پرداخت برای برگشت پیدا نشد.', 409);
            $key = 'reversal:' . $batchId . ':' . bin2hex(random_bytes(8));
            Model::execute(
                'INSERT INTO legal_lawyer_payment_batches
                 (batch_uuid, lawyer_id, amount_toman, paid_at, payment_reference, note, idempotency_key, request_fingerprint, is_reversal, reversal_of_batch_id, reversal_reason, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())',
                ['LLP-R-' . strtoupper(bin2hex(random_bytes(20))), (int) $batch['lawyer_id'], -abs(normalize_money($batch['amount_toman'])),
                 date('Y-m-d'), $batch['payment_reference'] ?? null, null, $key,
                 hash('sha256', 'reverse:' . $batchId . ':' . $reason), $batchId, $reason, $actorId > 0 ? $actorId : null]
            );
            $reversalId = (int) Model::lastInsertId();
            foreach ($allocations as $allocation) {
                Model::execute(
                    'INSERT INTO legal_lawyer_payment_allocations (payment_batch_id, legal_case_cost_id, allocated_amount_toman, reversal_of_allocation_id, created_at) VALUES (?, ?, ?, ?, NOW())',
                    [$reversalId, (int) $allocation['legal_case_cost_id'], -abs(normalize_money($allocation['allocated_amount_toman'])), (int) $allocation['id']]
                );
            }
            self::audit('legal_lawyer_payment_reversed', (int) $batch['lawyer_id'], $batchId, [
                'reversal_batch_id' => $reversalId, 'reason' => $reason,
                'amount_toman' => -abs(normalize_money($batch['amount_toman'])),
            ], $actorId);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function addAdjustment(int $lawyerId, string $type, int $amount, string $effectiveAt, string $description, string $idempotencyKey, int $actorId): void
    {
        if (!self::ready()) throw new RuntimeException('ساختار تسویه حساب وکلا نصب نشده است.');
        $type = trim($type);
        $description = trim($description);
        $idempotencyKey = strtolower(trim($idempotencyKey));
        $amount = normalize_money($amount);
        if ($lawyerId <= 0 || !in_array($type, ['credit', 'debit'], true) || $amount <= 0) {
            throw new InvalidArgumentException('نوع رویداد، مبلغ و وکیل را بررسی کنید.');
        }
        self::validateDate($effectiveAt);
        if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) throw new InvalidArgumentException('شناسه یکتای درخواست معتبر نیست.');
        if (mb_strlen($description, 'UTF-8') < 3 || mb_strlen($description, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('شرح بستانکاری یا بدهکاری را کامل کنید.');
        }
        $signed = $type === 'credit' ? $amount : -$amount;
        $fingerprint = hash('sha256', json_encode([
            'lawyer_id' => $lawyerId, 'type' => $type, 'amount' => $amount,
            'effective_at' => $effectiveAt, 'description' => $description,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Model::begin();
        try {
            $existing = Model::fetch('SELECT * FROM legal_lawyer_adjustments WHERE idempotency_key = ? FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if ((int) $existing['lawyer_id'] !== $lawyerId || !hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    throw new InvalidArgumentException('شناسه درخواست قبلاً برای رویداد دیگری استفاده شده است.', 409);
                }
                Model::commit();
                return;
            }
            $lawyer = Model::fetch("SELECT id FROM users WHERE id = ? AND role = 'lawyer' FOR UPDATE", [$lawyerId]);
            if (!$lawyer) throw new InvalidArgumentException('حساب وکیل انتخاب‌شده معتبر نیست.', 404);
            $existing = Model::fetch('SELECT * FROM legal_lawyer_adjustments WHERE idempotency_key = ? FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if ((int) $existing['lawyer_id'] !== $lawyerId || !hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    throw new InvalidArgumentException('شناسه درخواست قبلاً برای رویداد دیگری استفاده شده است.', 409);
                }
                Model::commit();
                return;
            }
            Model::execute(
                'INSERT INTO legal_lawyer_adjustments (entry_uuid, lawyer_id, entry_type, signed_amount_toman, idempotency_key, request_fingerprint, effective_at, description, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                ['LLA-' . strtoupper(bin2hex(random_bytes(20))), $lawyerId, $type, $signed, $idempotencyKey, $fingerprint, $effectiveAt, $description, $actorId > 0 ? $actorId : null]
            );
            $entryId = (int) Model::lastInsertId();
            self::audit('legal_lawyer_adjustment_recorded', $lawyerId, $entryId, [
                'entry_type' => $type, 'signed_amount_toman' => $signed,
                'effective_at' => $effectiveAt, 'description' => $description,
            ], $actorId);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reverseAdjustment(int $entryId, string $reason, int $actorId): void
    {
        if (!self::ready()) throw new RuntimeException('ساختار تسویه حساب وکلا نصب نشده است.');
        $reason = trim($reason);
        if ($entryId <= 0 || mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت برگشت رویداد را کامل وارد کنید.');
        Model::begin();
        try {
            $entry = Model::fetch("SELECT * FROM legal_lawyer_adjustments WHERE id = ? AND reversal_of_id IS NULL AND entry_type IN ('credit', 'debit') FOR UPDATE", [$entryId]);
            if (!$entry) throw new InvalidArgumentException('رویداد بستانکاری یا بدهکاری برای برگشت پیدا نشد.', 404);
            if (Model::fetch('SELECT id FROM legal_lawyer_adjustments WHERE reversal_of_id = ? LIMIT 1 FOR UPDATE', [$entryId])) {
                throw new InvalidArgumentException('این رویداد قبلاً برگشت خورده است.', 409);
            }
            Model::execute(
                'INSERT INTO legal_lawyer_adjustments (entry_uuid, lawyer_id, entry_type, signed_amount_toman, idempotency_key, request_fingerprint, effective_at, description, reversal_of_id, created_by, created_at)
                 VALUES (?, ?, \'reversal\', ?, ?, ?, ?, ?, ?, ?, NOW())',
                ['LLA-R-' . strtoupper(bin2hex(random_bytes(20))), (int) $entry['lawyer_id'], -((int) $entry['signed_amount_toman']),
                 hash('sha256', 'adjustment-reversal:' . $entryId), hash('sha256', 'adjustment-reversal-fingerprint:' . $entryId . ':' . $reason),
                 date('Y-m-d'), $reason, $entryId, $actorId > 0 ? $actorId : null]
            );
            self::audit('legal_lawyer_adjustment_reversed', (int) $entry['lawyer_id'], $entryId, [
                'reversal_of_id' => $entryId, 'reason' => $reason,
                'signed_amount_toman' => -((int) $entry['signed_amount_toman']),
            ], $actorId);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Refuse expense reversal until its net reimbursement has been reversed. */
    public static function assertCostReversible(int $costId): void
    {
        if (!self::ready()) return;
        $paid = Model::fetch('SELECT COALESCE(SUM(allocated_amount_toman), 0) AS total FROM legal_lawyer_payment_allocations WHERE legal_case_cost_id = ?', [$costId]);
        if ((int) ($paid['total'] ?? 0) > 0) {
            throw new InvalidArgumentException('ابتدا پرداخت‌های تسویه‌شده با وکیل را با رویداد برگشت تطبیق دهید.');
        }
    }

    /** Classify an already approved cost as payable back to its case lawyer. */
    public static function markCostForReimbursement(int $costId, int $actorId): void
    {
        if (!self::ready()) throw new RuntimeException('ساختار تسویه حساب وکلا نصب نشده است.');
        Model::begin();
        try {
            $cost = Model::fetch('SELECT * FROM legal_case_costs WHERE id = ? FOR UPDATE', [$costId]);
            if (!$cost || ($cost['approval_status'] ?? '') !== 'approved' || !empty($cost['reversed_at']) || !empty($cost['reimburse_lawyer_id'])) {
                throw new InvalidArgumentException('هزینه برای افزودن به صورت‌حساب وکیل معتبر نیست.', 409);
            }
            if (in_array((string) ($cost['payment_status'] ?? ''), ['reimbursed', 'cancelled'], true)) {
                throw new InvalidArgumentException('وضعیت پرداخت هزینه اجازهٔ بازپرداخت دوباره به وکیل را نمی‌دهد.', 409);
            }
            $companyPayments = LegalCaseCostService::disbursementTableAvailable()
                ? Model::fetch('SELECT COALESCE(SUM(amount_toman), 0) AS total FROM legal_cost_disbursements WHERE legal_case_cost_id = ?', [$costId])
                : ['total' => 0];
            if (normalize_money($companyPayments['total'] ?? 0) > 0) {
                throw new InvalidArgumentException('برای این هزینه پرداخت مجموعه به مرجع ثبت شده است و نمی‌توان همان مبلغ را دوباره به وکیل پرداخت کرد.', 409);
            }
            $case = Model::fetch(
                "SELECT lc.lawyer_id, u.role, u.status FROM legal_cases lc
                 JOIN users u ON u.id = lc.lawyer_id
                 WHERE lc.id = ? AND lc.contract_id = ? FOR UPDATE",
                [(int) ($cost['legal_case_id'] ?? 0), (int) $cost['contract_id']]
            );
            if (!$case || (int) ($case['lawyer_id'] ?? 0) <= 0 || ($case['role'] ?? '') !== 'lawyer') {
                throw new InvalidArgumentException('برای این هزینه وکیل پرونده مشخص نشده است.');
            }
            Model::execute('UPDATE legal_case_costs SET reimburse_lawyer_id = ? WHERE id = ?', [(int) $case['lawyer_id'], $costId]);
            self::audit('legal_lawyer_cost_claimed', (int) $case['lawyer_id'], $costId, [
                'amount_toman' => normalize_money($cost['amount_toman'] ?? 0),
                'contract_id' => (int) $cost['contract_id'],
            ], $actorId);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function validateDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('تاریخ مالی معتبر نیست.');
        }
    }

    private static function audit(string $action, int $lawyerId, int $sourceId, array $details, int $actorId): void
    {
        if (!class_exists('AuditLog')) return;
        try {
            AuditLog::record('legal_lawyer_settlement', $action, 'legal_lawyer_ledger', $sourceId, [
                'actor_user_id' => $actorId, 'lawyer_id' => $lawyerId, 'details' => $details,
            ]);
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('legal_lawyer_settlement.audit', $e);
        }
    }
}
