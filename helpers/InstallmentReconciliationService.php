<?php

/** Read-only detection and explicitly approved materialized-state repair. */
final class InstallmentReconciliationService
{
    public const RULE_VERSION = 'financial-reconciliation-v1';

    public static function scan($limit = 500, $contractId = null, $asOf = null)
    {
        $limit = max(1, min(5000, (int) $limit));
        $asOf = self::validDate($asOf ?: date('Y-m-d'));
        $params = [];
        $where = '';
        if ($contractId) {
            $where = ' WHERE i.contract_id = ?';
            $params[] = (int) $contractId;
        }
        $rows = Model::fetchAll(
            "SELECT i.*, c.status AS contract_status, c.customer_id, c.contract_number,
                    (SELECT MIN(lc.legal_referred_at) FROM legal_cases lc WHERE lc.contract_id = i.contract_id AND lc.legal_referred_at IS NOT NULL) AS legal_started_at
             FROM installments i JOIN contracts c ON c.id = i.contract_id
             {$where}
             ORDER BY i.id ASC LIMIT {$limit}",
            $params
        );
        $states = InstallmentFinancialStateService::statesForRows($rows, null, $asOf);
        $issues = [];
        $fingerprints = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $state = $states[$id] ?? InstallmentFinancialStateService::state($row, null, null, $asOf);
            $storedPaid = normalize_money($row['paid_amount'] ?? 0);
            $storedRemaining = normalize_money($row['remaining_amount'] ?? 0);
            $expectedPaid = normalize_money($state['effective_paid_principal'] ?? 0);
            $expectedRemaining = normalize_money($state['remaining_principal'] ?? 0);
            $storedStatus = (string) ($row['status'] ?? '');
            $expectedStatus = (string) ($state['status'] ?? '');
            if ($storedPaid !== $expectedPaid || $storedRemaining !== $expectedRemaining || $storedStatus !== $expectedStatus) {
                $fingerprints[$id] = $fingerprints[$id] ?? self::sourceFingerprint($id);
                $issues[] = $row + [
                    'issue_type' => 'installment_materialization',
                    'rule_id' => 'R03',
                    'rule_version' => self::RULE_VERSION,
                    'severity' => 'high',
                    'classification' => 'verified_mismatch',
                    'coverage_status' => 'covered_by_canonical_state',
                    'expected_toman' => $expectedRemaining,
                    'actual_toman' => $storedRemaining,
                    'difference_toman' => $expectedRemaining - $storedRemaining,
                    'source_fingerprint' => $fingerprints[$id],
                    'expected_state' => $state,
                    'evidence' => [
                        'as_of_date' => $asOf,
                        'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
                        'stored' => ['paid_toman' => $storedPaid, 'remaining_toman' => $storedRemaining, 'status' => $storedStatus],
                        'expected' => ['paid_toman' => $expectedPaid, 'remaining_toman' => $expectedRemaining, 'status' => $expectedStatus],
                    ],
                ];
            }
            if (($state['status'] ?? '') === 'paid' && normalize_money($state['final_payable'] ?? 0) > 0) {
                $fingerprints[$id] = $fingerprints[$id] ?? self::sourceFingerprint($id);
                $issues[] = $row + [
                    'issue_type' => 'paid_with_balance', 'rule_id' => 'R04', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'covered_by_canonical_state',
                    'expected_toman' => normalize_money($state['final_payable'] ?? 0), 'actual_toman' => 0,
                    'difference_toman' => normalize_money($state['final_payable'] ?? 0), 'source_fingerprint' => $fingerprints[$id],
                    'expected_state' => $state,
                    'evidence' => ['as_of_date' => $asOf, 'stored_status' => $storedStatus, 'canonical_status' => (string) ($state['status'] ?? ''), 'canonical_payable_toman' => normalize_money($state['final_payable'] ?? 0)],
                ];
            }
            if (($state['status'] ?? '') !== 'paid' && normalize_money($state['final_payable'] ?? 0) === 0 && $storedStatus !== 'cancelled') {
                $fingerprints[$id] = $fingerprints[$id] ?? self::sourceFingerprint($id);
                $issues[] = $row + [
                    'issue_type' => 'unpaid_with_zero_balance', 'rule_id' => 'R04', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'covered_by_canonical_state',
                    'expected_toman' => 0, 'actual_toman' => normalize_money($row['remaining_amount'] ?? 0),
                    'difference_toman' => -normalize_money($row['remaining_amount'] ?? 0), 'source_fingerprint' => $fingerprints[$id],
                    'expected_state' => $state,
                    'evidence' => ['as_of_date' => $asOf, 'stored_status' => $storedStatus, 'canonical_status' => (string) ($state['status'] ?? ''), 'canonical_payable_toman' => 0],
                ];
            }
        }

        if ($rows && in_array((string) ($rows[0]['contract_status'] ?? ''), ['completed'], true)) {
            $openPayable = 0;
            $openInstallmentIds = [];
            foreach ($rows as $row) {
                $state = $states[(int) $row['id']] ?? [];
                $payable = normalize_money($state['final_payable'] ?? 0);
                if ($payable > 0) {
                    $openPayable += $payable;
                    $openInstallmentIds[] = (int) $row['id'];
                }
            }
            if ($openPayable > 0) {
                $issues[] = [
                    'id' => (int) $rows[0]['contract_id'],
                    'contract_id' => (int) $rows[0]['contract_id'],
                    'customer_id' => (int) $rows[0]['customer_id'],
                    'contract_number' => (string) $rows[0]['contract_number'],
                    'issue_type' => 'completed_contract_with_balance',
                    'rule_id' => 'R04',
                    'rule_version' => self::RULE_VERSION,
                    'severity' => 'critical',
                    'classification' => 'verified_mismatch',
                    'coverage_status' => 'installments_recalculated_at_snapshot_date',
                    'expected_toman' => 0,
                    'actual_toman' => $openPayable,
                    'difference_toman' => -$openPayable,
                    'evidence' => ['as_of_date' => $asOf, 'contract_status' => 'completed', 'open_payable_toman' => $openPayable, 'installment_ids' => $openInstallmentIds],
                ];
            }
        }

        return array_merge($issues, self::allocationIssues($contractId, $limit));
    }

    /**
     * Fingerprint only the inputs used to derive the cached installment state.
     * It is recomputed under the contract lock before any approved write.
     */
    public static function sourceFingerprint(int $installmentId): string
    {
        $installment = Model::fetch(
            'SELECT i.id, i.contract_id, i.installment_number, i.base_amount, i.paid_amount, i.remaining_amount, i.status,
                    i.due_date, i.last_payment_date, i.effective_settlement_at, i.manual_penalty_adjustment,
                    i.penalty_discount_amount, i.manual_reward_adjustment, c.status AS contract_status
             FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE i.id = ? LIMIT 1',
            [$installmentId]
        );
        if (!$installment) return hash('sha256', 'missing:' . $installmentId);
        $payments = Model::fetchAll(
            'SELECT id, installment_id, contract_id, amount, status, payment_type, is_corrected, correction_reason,
                    payment_date, paid_at, principal_applied, normal_penalty_applied, legal_penalty_applied,
                    reward_applied, calculated_reward, remaining_after_payment
             FROM payments WHERE installment_id = ? ORDER BY id',
            [$installmentId]
        );
        $allocations = Model::fetchAll(
            'SELECT id, payment_id, payment_group_id, contract_id, installment_id, allocated_amount, principal_applied,
                    normal_penalty_applied, legal_penalty_applied, reward_applied, reversal_of_allocation_id, is_reversal
             FROM payment_allocations WHERE installment_id = ? ORDER BY id',
            [$installmentId]
        );
        $legalFacts = Model::fetchAll(
            "SELECT id, status, legal_referred_at FROM legal_cases WHERE contract_id = ? ORDER BY id",
            [(int) $installment['contract_id']]
        );
        $allSettings = Settings::allKeyed();
        $settings = [];
        foreach (['monthly_penalty_rate', 'legal_monthly_penalty_rate', 'late_penalty_grace_days', 'monthly_reward_rate'] as $key) {
            $settings[$key] = (string) ($allSettings[$key] ?? '');
        }
        $snapshot = [
            'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
            'installment' => $installment,
            'payments' => $payments,
            'allocations' => $allocations,
            'legal_facts' => $legalFacts,
            'settings' => $settings,
        ];
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: 'invalid');
    }

    public static function allocationIssues($contractId = null, $limit = 500)
    {
        $limit = max(1, min(5000, (int) $limit));
        $where = $contractId ? 'WHERE pg.contract_id = ?' : '';
        $params = $contractId ? [(int) $contractId] : [];
        $groups = Model::fetchAll(
            "SELECT pg.id, pg.id AS payment_group_id, pg.contract_id, pg.customer_id, pg.requested_amount, pg.allocated_amount, pg.status,
                    COALESCE(SUM(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected,0)=0 THEN p.amount ELSE 0 END),0) AS active_paid_total,
                    COUNT(DISTINCT p.id) AS child_payment_count,
                    COUNT(DISTINCT CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected,0)=0 THEN p.id END) AS active_payment_count,
                    SUM(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected,0)=0 AND p.contract_id <> pg.contract_id THEN 1 ELSE 0 END) AS cross_contract_payment_count
             FROM payment_groups pg LEFT JOIN payments p ON p.payment_group_id = pg.id
             {$where} GROUP BY pg.id ORDER BY pg.id ASC LIMIT {$limit}",
            $params
        );
        $issues = [];
        foreach ($groups as $group) {
            if (($group['status'] ?? '') !== 'completed') continue;
            if ((int) ($group['active_payment_count'] ?? 0) === 0 && (int) ($group['child_payment_count'] ?? 0) > 0) continue;
            $expected = normalize_money($group['requested_amount'] ?? 0);
            $actual = normalize_money($group['active_paid_total'] ?? 0);
            $storedAllocated = normalize_money($group['allocated_amount'] ?? 0);
            $crossContractCount = (int) ($group['cross_contract_payment_count'] ?? 0);
            if ($expected !== $actual || $storedAllocated !== $actual) {
                $displayExpected = $expected !== $actual ? $expected : $actual;
                $displayActual = $expected !== $actual ? $actual : $storedAllocated;
                $issues[] = $group + [
                    'issue_type' => 'group_payment_total_mismatch', 'rule_id' => 'R01', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'payment_group_records',
                    'expected_toman' => $displayExpected, 'actual_toman' => $displayActual, 'difference_toman' => $displayExpected - $displayActual,
                    'evidence' => ['requested_toman' => $expected, 'stored_allocated_toman' => $storedAllocated, 'active_paid_children_toman' => $actual, 'active_payment_count' => (int) $group['active_payment_count'], 'cross_contract_child_count' => $crossContractCount],
                ];
            }
            if ($crossContractCount > 0) {
                $issues[] = $group + [
                    'issue_type' => 'group_cross_contract_payment', 'rule_id' => 'R07', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'payment_group_contract_scope',
                    'evidence' => ['payment_group_contract_id' => (int) $group['contract_id'], 'cross_contract_child_count' => $crossContractCount],
                ];
            }
        }

        $orphanSql = 'SELECT pa.*, p.contract_id AS payment_contract_id, i.contract_id AS installment_contract_id, c.customer_id AS contract_customer_id
                      FROM payment_allocations pa
                      LEFT JOIN payments p ON p.id = pa.payment_id
                      LEFT JOIN installments i ON i.id = pa.installment_id
                      LEFT JOIN contracts c ON c.id = pa.contract_id
                      WHERE (p.id IS NULL OR i.id IS NULL OR c.id IS NULL
                         OR p.contract_id <> pa.contract_id OR i.contract_id <> pa.contract_id)
                      ' . ($contractId ? 'AND pa.contract_id = ? ' : '') . 'ORDER BY pa.id LIMIT ' . $limit;
        foreach (Model::fetchAll($orphanSql, $contractId ? [(int) $contractId] : []) as $row) {
            $orphanType = empty($row['payment_contract_id']) || empty($row['installment_contract_id']) || empty($row['contract_customer_id'])
                ? 'allocation_orphan_reference' : 'cross_contract_allocation';
            $issues[] = $row + [
                'issue_type' => $orphanType, 'rule_id' => 'R07', 'rule_version' => self::RULE_VERSION,
                'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'referential_integrity',
                'evidence' => ['allocation_contract_id' => (int) ($row['contract_id'] ?? 0), 'payment_contract_id' => (int) ($row['payment_contract_id'] ?? 0), 'installment_contract_id' => (int) ($row['installment_contract_id'] ?? 0)],
            ];
        }

        $paymentAllocations = Model::fetchAll(
            "SELECT p.id, p.contract_id, p.installment_id, p.payment_group_id, p.amount, p.status, p.payment_type,
                    COALESCE(SUM(pa.allocated_amount),0) AS net_allocated,
                    COUNT(pa.id) AS allocation_count
             FROM payments p LEFT JOIN payment_allocations pa ON pa.payment_id = p.id
             WHERE p.status = 'paid' AND COALESCE(p.is_corrected,0)=0
               AND (p.payment_group_id IS NOT NULL OR EXISTS (SELECT 1 FROM payment_allocations exists_pa WHERE exists_pa.payment_id=p.id))
               AND COALESCE(p.payment_type,'installment') = 'installment'
               " . ($contractId ? 'AND p.contract_id = ? ' : '') . "
             GROUP BY p.id ORDER BY p.id LIMIT {$limit}",
            $contractId ? [(int) $contractId] : []
        );
        foreach ($paymentAllocations as $payment) {
            $expected = normalize_money($payment['amount'] ?? 0);
            $actual = normalize_money($payment['net_allocated'] ?? 0);
            $count = (int) ($payment['allocation_count'] ?? 0);
            if (($count === 0) || $expected !== $actual) {
                $issues[] = $payment + [
                    'issue_type' => 'payment_allocation_total_mismatch', 'rule_id' => 'R01', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'grouped_installment_payment',
                    'payment_id' => (int) $payment['id'], 'expected_toman' => $expected, 'actual_toman' => $actual,
                    'difference_toman' => $expected - $actual,
                    'evidence' => ['payment_toman' => $expected, 'net_allocation_toman' => $actual, 'allocation_count' => $count],
                ];
            }
        }

        $legalPaymentAllocationRows = Model::fetchAll(
            "SELECT p.id, p.contract_id, p.amount, p.status, p.payment_type,
                    COALESCE(SUM(a.allocated_amount_toman),0) AS net_allocated,
                    COUNT(a.payment_id) AS allocation_count
             FROM payments p LEFT JOIN legal_cost_payment_allocations a ON a.payment_id = p.id
             WHERE p.status = 'paid' AND COALESCE(p.is_corrected,0)=0 AND COALESCE(p.payment_type,'') = 'legal_cost'
               " . ($contractId ? 'AND p.contract_id = ? ' : '') . "
             GROUP BY p.id ORDER BY p.id LIMIT {$limit}",
            $contractId ? [(int) $contractId] : []
        );
        foreach ($legalPaymentAllocationRows as $payment) {
            $expected = normalize_money($payment['amount'] ?? 0);
            $actual = normalize_money($payment['net_allocated'] ?? 0);
            if ((int) ($payment['allocation_count'] ?? 0) === 0 || $expected !== $actual) {
                $issues[] = $payment + [
                    'issue_type' => 'legal_payment_allocation_total_mismatch', 'rule_id' => 'R06', 'rule_version' => self::RULE_VERSION,
                    'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'legal_cost_payment_allocations',
                    'payment_id' => (int) $payment['id'], 'expected_toman' => $expected, 'actual_toman' => $actual,
                    'difference_toman' => $expected - $actual,
                    'evidence' => ['payment_toman' => $expected, 'legal_cost_allocation_toman' => $actual],
                ];
            }
        }

        $paymentsWithoutAllocations = Model::fetchAll(
            "SELECT p.* FROM payments p
             LEFT JOIN payment_allocations pa ON pa.payment_id = p.id
             WHERE p.payment_group_id IS NOT NULL AND p.status = 'paid' AND COALESCE(p.is_corrected,0)=0
               AND COALESCE(p.payment_type,'installment') = 'installment' AND pa.id IS NULL
               " . ($contractId ? 'AND p.contract_id = ? ' : '') . "ORDER BY p.id LIMIT {$limit}",
            $contractId ? [(int) $contractId] : []
        );
        foreach ($paymentsWithoutAllocations as $payment) {
            $issues[] = $payment + [
                'issue_type' => 'payment_without_allocation', 'rule_id' => 'R01', 'rule_version' => self::RULE_VERSION,
                'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'grouped_installment_payment',
                'payment_id' => (int) $payment['id'], 'expected_toman' => normalize_money($payment['amount'] ?? 0), 'actual_toman' => 0,
                'difference_toman' => normalize_money($payment['amount'] ?? 0), 'evidence' => ['payment_toman' => normalize_money($payment['amount'] ?? 0), 'allocation_count' => 0],
            ];
        }
        $paymentsWithoutInstallments = Model::fetchAll(
            "SELECT p.* FROM payments p
             WHERE p.status = 'paid' AND COALESCE(p.is_corrected,0)=0
               AND COALESCE(p.payment_type,'installment') = 'installment' AND p.installment_id IS NULL
               " . ($contractId ? 'AND p.contract_id = ? ' : '') . "
             ORDER BY p.id LIMIT {$limit}",
            $contractId ? [(int) $contractId] : []
        );
        foreach ($paymentsWithoutInstallments as $payment) {
            $issues[] = $payment + [
                'issue_type' => 'payment_missing_installment_reference', 'rule_id' => 'R07', 'rule_version' => self::RULE_VERSION,
                'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'paid_installment_payment_without_installment_id',
                'payment_id' => (int) $payment['id'], 'evidence' => ['payment_id' => (int) $payment['id'], 'contract_id' => (int) $payment['contract_id'], 'installment_id' => null],
            ];
        }

        $gatewayReviewGroups = Model::fetchAll(
            "SELECT id, id AS payment_group_id, contract_id, customer_id, requested_amount, gateway_verified_amount, status, created_at
               FROM payment_groups WHERE status='review_required' " . ($contractId ? 'AND contract_id=? ' : '') . "ORDER BY id LIMIT {$limit}",
            $contractId ? [(int) $contractId] : []
        );
        foreach ($gatewayReviewGroups as $group) {
            $issues[] = $group + [
                'issue_type' => 'gateway_payment_review_required', 'rule_id' => 'R01', 'rule_version' => self::RULE_VERSION,
                'severity' => 'review', 'classification' => 'insufficient_evidence', 'coverage_status' => 'gateway_review_required_not_receipt',
                'payment_group_id' => (int) $group['id'], 'expected_toman' => null, 'actual_toman' => null, 'difference_toman' => null,
                'evidence' => ['payment_group_id' => (int) $group['id'], 'status' => 'review_required', 'verified_amount_received' => $group['gateway_verified_amount'] !== null],
            ];
        }
        return $issues;
    }

    /** Old unreviewed snapshots are never accepted as write instructions. */
    public static function apply(array $issues)
    {
        throw new LogicException('بازسازی وضعیت قسط فقط از پیشنهاد تأییدشده در مرکز تطبیق مجاز است.');
    }

    /**
     * Apply only the canonical materialized installment state after an
     * independently approved reconciliation proposal. The caller owns the
     * transaction so the proposal, audit log, and this update commit together.
     */
    public static function applyApprovedMaterializationRefresh(int $installmentId, string $expectedFingerprint, string $approvedAsOf, int $actorId): array
    {
        self::assertApprovedRefreshActor($actorId);
        if (!Model::db()->inTransaction()) throw new LogicException('بازسازی وضعیت باید در تراکنش پیشنهاد تأییدشده انجام شود.');
        if ($installmentId <= 0 || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $approvedAsOf)) throw new InvalidArgumentException('شناسه قسط یا تاریخ مبنای پیشنهاد معتبر نیست.', 422);
        $parsed = DateTime::createFromFormat('!Y-m-d', $approvedAsOf);
        if (!$parsed || $parsed->format('Y-m-d') !== $approvedAsOf) throw new InvalidArgumentException('تاریخ مبنای پیشنهاد معتبر نیست.', 422);

        $locked = self::lockMaterializationSource($installmentId);
        $before = [
            'paid_toman' => normalize_money($locked['paid_amount'] ?? 0),
            'remaining_toman' => normalize_money($locked['remaining_amount'] ?? 0),
            'status' => (string) ($locked['status'] ?? ''),
            'contract_status' => (string) ($locked['contract_status'] ?? ''),
        ];
        if (!hash_equals($expectedFingerprint, self::sourceFingerprint($installmentId))) {
            return ['status' => 'stale', 'changed' => false, 'before' => $before];
        }
        if ($approvedAsOf !== date('Y-m-d')) {
            return ['status' => 'stale', 'changed' => false, 'before' => $before, 'reason' => 'approved_date_expired'];
        }
        if (($locked['status'] ?? '') === 'cancelled' || ($locked['contract_status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قسط یا قرارداد لغوشده از مسیر بازسازی وضعیت قابل تغییر نیست.', 409);
        }

        $state = InstallmentFinancialStateService::state($locked, null, null, $approvedAsOf);
        $after = [
            'paid_toman' => normalize_money($state['effective_paid_principal'] ?? 0),
            'remaining_toman' => normalize_money($state['remaining_principal'] ?? 0),
            'status' => (string) ($state['status'] ?? ''),
            'effective_settlement_at' => $state['effective_settlement_date'] ?? null,
            'as_of_date' => $approvedAsOf,
            'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
        ];
        $lastPaid = Model::fetch(
            "SELECT MAX(COALESCE(payment_date,DATE(paid_at),DATE(created_at))) AS last_payment_date
               FROM payments WHERE installment_id=? AND status='paid' AND COALESCE(is_corrected,0)=0
                 AND COALESCE(payment_type,'installment')='installment'",
            [$installmentId]
        );
        $after['last_payment_date'] = $lastPaid['last_payment_date'] ?? null;
        $changed = $before['paid_toman'] !== $after['paid_toman'] || $before['remaining_toman'] !== $after['remaining_toman'] || $before['status'] !== $after['status'];
        if ($changed) {
            $updatedRows = Model::execute(
                'UPDATE installments SET paid_amount=?,remaining_amount=?,status=?,effective_settlement_at=?,last_payment_date=?,updated_at=NOW() WHERE id=? AND status<>?',
                [$after['paid_toman'], $after['remaining_toman'], $after['status'], $after['effective_settlement_at'], $after['last_payment_date'], $installmentId, 'cancelled']
            );
            if ($updatedRows !== 1) throw new RuntimeException('وضعیت قسط در حین بازسازی تغییر کرد؛ عملیات برگشت داده شد.', 409);
        }

        Contract::syncCompletionStatusesForContracts([(int) $locked['contract_id']]);
        $freshContract = Model::fetch('SELECT status FROM contracts WHERE id=? LIMIT 1', [(int) $locked['contract_id']]);
        $after['contract_status'] = (string) ($freshContract['status'] ?? $before['contract_status']);
        $remaining = array_filter(self::scan(5000, (int) $locked['contract_id'], $approvedAsOf), static function ($issue) use ($installmentId) {
            return (int) ($issue['id'] ?? $issue['installment_id'] ?? 0) === $installmentId
                && in_array((string) ($issue['issue_type'] ?? ''), ['installment_materialization', 'paid_with_balance', 'unpaid_with_zero_balance'], true);
        });
        if ($remaining) throw new RuntimeException('تطبیق مجدد وضعیت قسط پس از بازسازی موفق نشد؛ تراکنش برگشت داده شد.', 409);
        return ['status' => 'applied', 'changed' => $changed, 'before' => $before, 'after' => $after];
    }

    /** Lock the exact source rows used by the canonical state calculation. */
    public static function lockMaterializationSource(int $installmentId): array
    {
        if (!Model::db()->inTransaction()) throw new LogicException('قفل‌کردن منبع وضعیت قسط نیازمند تراکنش است.');
        $reference = Model::fetch('SELECT contract_id FROM installments WHERE id=? LIMIT 1', [$installmentId]);
        if (!$reference) throw new InvalidArgumentException('قسط مرتبط دیگر وجود ندارد.', 404);
        // Payment writers lock the contract first. Keep the same order to
        // serialize this materialized-state repair with new receipts.
        $contract = Model::fetch('SELECT id,customer_id,contract_number,status AS contract_status FROM contracts WHERE id=? LIMIT 1 FOR UPDATE', [(int) $reference['contract_id']]);
        if (!$contract) throw new InvalidArgumentException('قرارداد مرتبط دیگر وجود ندارد.', 404);
        $row = Model::fetch('SELECT * FROM installments WHERE id=? LIMIT 1 FOR UPDATE', [$installmentId]);
        if (!$row) throw new InvalidArgumentException('قسط مرتبط دیگر وجود ندارد.', 404);
        $legalCases = Model::fetchAll('SELECT id,status,legal_referred_at FROM legal_cases WHERE contract_id=? ORDER BY id FOR UPDATE', [(int) $contract['id']]);
        Model::fetchAll('SELECT id FROM payments WHERE installment_id=? ORDER BY id FOR UPDATE', [$installmentId]);
        Model::fetchAll('SELECT id FROM payment_allocations WHERE installment_id=? ORDER BY id FOR UPDATE', [$installmentId]);
        Model::fetchAll('SELECT id FROM legal_cost_payment_allocations WHERE contract_id=? ORDER BY id FOR UPDATE', [(int) $contract['id']]);
        Model::fetchAll("SELECT setting_key FROM settings WHERE setting_key IN ('monthly_penalty_rate','legal_monthly_penalty_rate','late_penalty_grace_days','monthly_reward_rate') ORDER BY setting_key FOR UPDATE");
        $legalDates = array_values(array_filter(array_map(static function ($legalCase) { return $legalCase['legal_referred_at'] ?? null; }, $legalCases)));
        sort($legalDates, SORT_STRING);
        $inconsistent = count(array_filter($legalCases, static function ($legalCase) {
            return empty($legalCase['legal_referred_at']) && in_array((string) $legalCase['status'], ['referred', 'external_submission_confirmed'], true);
        }));
        return array_merge($row, $contract, ['legal_started_at' => $legalDates[0] ?? null, 'inconsistent_legal_referral_count' => $inconsistent]);
    }

    private static function assertApprovedRefreshActor(int $actorId): void
    {
        if ($actorId <= 0 || (int) Auth::id() !== $actorId || Auth::role() !== 'admin') throw new InvalidArgumentException('اجرای بازسازی وضعیت فقط برای مدیر مجاز است.', 403);
        $user = Model::fetch('SELECT id,role,archived_at FROM users WHERE id=? LIMIT 1', [$actorId]);
        if (!$user || ($user['role'] ?? '') !== 'admin' || !empty($user['archived_at'])) throw new InvalidArgumentException('حساب مدیر دیگر برای این عملیات مجاز نیست.', 403);
    }

    private static function validDate($value): string
    {
        $date = substr(trim((string) $value), 0, 10);
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) return date('Y-m-d');
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : date('Y-m-d');
    }
}
