<?php

final class InstallmentReconciliationService
{
    public static function scan($limit = 500)
    {
        $limit = max(1, min(5000, (int) $limit));
        $rows = Model::fetchAll(
            "SELECT i.id, i.contract_id, i.status, i.base_amount, i.paid_amount, i.remaining_amount, i.due_date,
                    COALESCE(SUM(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
                                      AND COALESCE(p.payment_type, 'installment') = 'installment' THEN p.amount ELSE 0 END), 0) AS effective_paid
             FROM installments i
             LEFT JOIN payments p ON p.installment_id = i.id
             WHERE i.status <> 'cancelled'
             GROUP BY i.id
             ORDER BY i.id ASC
             LIMIT {$limit}"
        );
        $issues = [];
        foreach ($rows as $row) {
            $base = normalize_money($row['base_amount'] ?? 0);
            $effectivePaid = min($base, normalize_money($row['effective_paid'] ?? 0));
            $expectedRemaining = max(0, $base - $effectivePaid);
            $expectedStatus = FinanceHelper::status($base, $effectivePaid, (string) $row['due_date']);
            $storedRemaining = normalize_money($row['remaining_amount'] ?? 0);
            if ($storedRemaining !== $expectedRemaining || ($row['status'] ?? '') !== $expectedStatus || normalize_money($row['paid_amount'] ?? 0) !== $effectivePaid) {
                $issues[] = $row + [
                    'expected_paid_amount' => $effectivePaid,
                    'expected_remaining_amount' => $expectedRemaining,
                    'expected_status' => $expectedStatus,
                ];
            }
        }
        return $issues;
    }

    public static function apply(array $issues)
    {
        $updated = 0;
        Model::begin();
        try {
            foreach ($issues as $issue) {
                $updated += Model::execute(
                    'UPDATE installments SET paid_amount = ?, remaining_amount = ?, status = ?, updated_at = NOW() WHERE id = ? AND status <> ?',
                    [
                        normalize_money($issue['expected_paid_amount'] ?? 0),
                        normalize_money($issue['expected_remaining_amount'] ?? 0),
                        (string) ($issue['expected_status'] ?? 'pending'),
                        (int) ($issue['id'] ?? 0),
                        'cancelled',
                    ]
                );
            }
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
        Contract::syncCompletionStatuses();
        return $updated;
    }
}
