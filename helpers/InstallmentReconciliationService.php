<?php

/** Dry-run first reconciliation of materialized installment state and ledger rows. */
final class InstallmentReconciliationService
{
    public static function scan($limit = 500, $contractId = null)
    {
        $limit = max(1, min(5000, (int) $limit));
        // Reconciliation must read stored materialisation, not UI previews:
        // `Installment::all()` intentionally overlays the canonical state for
        // display and would conceal exactly the mismatch this tool repairs.
        $params = [];
        $where = '';
        if ($contractId) {
            $where = ' WHERE i.contract_id = ?';
            $params[] = (int) $contractId;
        }
        $rows = Model::fetchAll(
            "SELECT i.*, c.status AS contract_status,
                    (SELECT MIN(lc.legal_referred_at) FROM legal_cases lc WHERE lc.contract_id = i.contract_id AND lc.legal_referred_at IS NOT NULL) AS legal_started_at
             FROM installments i JOIN contracts c ON c.id = i.contract_id
             {$where}
             ORDER BY i.id ASC LIMIT {$limit}",
            $params
        );
        $states = InstallmentFinancialStateService::statesForRows($rows);
        $issues = [];
        foreach ($rows as $row) {
            $state = $states[(int) $row['id']] ?? InstallmentFinancialStateService::state($row);
            $storedPaid = normalize_money($row['paid_amount'] ?? 0);
            $storedRemaining = normalize_money($row['remaining_amount'] ?? 0);
            if ($storedPaid !== normalize_money($state['effective_paid_principal'])
                || $storedRemaining !== normalize_money($state['remaining_principal'])
                || ($row['status'] ?? '') !== ($state['status'] ?? '')) {
                $issues[] = $row + ['issue_type' => 'installment_materialization', 'expected_state' => $state];
            }
            if (($state['status'] ?? '') === 'paid' && normalize_money($state['final_payable'] ?? 0) > 0) {
                $issues[] = $row + ['issue_type' => 'paid_with_balance', 'expected_state' => $state];
            }
            if (($state['status'] ?? '') !== 'paid' && normalize_money($state['final_payable'] ?? 0) === 0 && ($row['status'] ?? '') !== 'cancelled') {
                $issues[] = $row + ['issue_type' => 'unpaid_with_zero_balance', 'expected_state' => $state];
            }
        }
        return array_merge($issues, self::allocationIssues($contractId, $limit));
    }

    public static function allocationIssues($contractId = null, $limit = 500)
    {
        $where = $contractId ? 'WHERE pg.contract_id = ?' : '';
        $params = $contractId ? [(int) $contractId] : [];
        $groups = Model::fetchAll(
            "SELECT pg.id, pg.contract_id, pg.requested_amount, pg.allocated_amount, pg.status,
                    COALESCE(SUM(CASE WHEN COALESCE(pa.is_reversal,0)=0 THEN pa.allocated_amount ELSE 0 END),0) AS allocation_total,
                    COUNT(pa.id) AS allocation_count
             FROM payment_groups pg LEFT JOIN payment_allocations pa ON pa.payment_group_id = pg.id
             {$where} GROUP BY pg.id ORDER BY pg.id ASC LIMIT " . max(1, min(5000, (int) $limit)),
            $params
        );
        $issues = [];
        foreach ($groups as $group) {
            $requested = normalize_money($group['requested_amount'] ?? 0);
            $actual = normalize_money($group['allocation_total'] ?? 0);
            if (($group['status'] ?? '') === 'completed' && ($actual !== $requested || (int) $group['allocation_count'] === 0)) {
                $issues[] = $group + ['issue_type' => 'group_allocation_total_mismatch'];
            }
        }
        $orphans = Model::fetchAll(
            'SELECT pa.* FROM payment_allocations pa LEFT JOIN payments p ON p.id = pa.payment_id WHERE p.id IS NULL LIMIT ' . max(1, min(5000, (int) $limit))
        );
        foreach ($orphans as $orphan) $issues[] = $orphan + ['issue_type' => 'allocation_without_payment'];
        $payments = Model::fetchAll(
            'SELECT p.* FROM payments p LEFT JOIN payment_allocations pa ON pa.payment_id = p.id AND COALESCE(pa.is_reversal,0)=0 WHERE p.payment_group_id IS NOT NULL AND p.status = \'paid\' AND COALESCE(p.is_corrected,0)=0 AND pa.id IS NULL LIMIT ' . max(1, min(5000, (int) $limit))
        );
        foreach ($payments as $payment) $issues[] = $payment + ['issue_type' => 'payment_without_allocation'];
        return $issues;
    }

    /** Authorized callers may pass the reviewed dry-run records to materialize only state discrepancies. */
    public static function apply(array $issues)
    {
        $updated = 0;
        $contractIds = [];
        Model::begin();
        try {
            foreach ($issues as $issue) {
                if (($issue['issue_type'] ?? '') !== 'installment_materialization' || empty($issue['id'])) continue;
                $state = $issue['expected_state'] ?? InstallmentFinancialStateService::stateForInstallment((int) $issue['id']);
                $updated += Model::execute('UPDATE installments SET paid_amount = ?, remaining_amount = ?, status = ?, effective_settlement_at = ? WHERE id = ? AND status <> ?', [normalize_money($state['effective_paid_principal'] ?? 0), normalize_money($state['remaining_principal'] ?? 0), $state['status'] ?? 'pending', $state['effective_settlement_date'] ?? null, (int) $issue['id'], 'cancelled']);
                $contractIds[] = (int) ($issue['contract_id'] ?? 0);
            }
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
        Contract::syncCompletionStatusesForContracts($contractIds);
        return $updated;
    }
}
