<?php

class OperatorCall extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        SchemaGuard::requireColumns('operator_calls', [
            'operator_id', 'customer_id', 'contract_id', 'installment_id', 'call_result', 'notes',
            'next_followup_date', 'promise_payment_date', 'action_type', 'contact_target', 'result_code',
            'promise_amount_toman', 'promise_event_type', 'related_promise_call_id', 'next_action_type',
            'baseline_overdue_amount_toman', 'baseline_snapshot_status', 'created_at',
        ]);
        self::$schemaReady = true;
    }

    public static function all($operatorId = null)
    {
        self::ensureSchema();
        $params = [];
        $where = '';
        if ($operatorId) {
            $where = 'WHERE oc.operator_id = ?';
            $params[] = (int) $operatorId;
        }
        return self::fetchAll(
            "SELECT oc.*, u.full_name AS customer_name, c.contract_number
             FROM operator_calls oc
             JOIN users u ON u.id = oc.customer_id
             JOIN contracts c ON c.id = oc.contract_id
             {$where}
             ORDER BY oc.id DESC",
            $params
        );
    }

    public static function forCustomer(int $customerId, int $limit = 50): array
    {
        self::ensureSchema();
        if ($customerId <= 0) return [];
        $limit = max(1, min(100, $limit));
        return self::fetchAll(
            'SELECT oc.*, u.full_name AS customer_name, c.contract_number, op.full_name AS operator_name
             FROM operator_calls oc
             JOIN users u ON u.id = oc.customer_id
             JOIN contracts c ON c.id = oc.contract_id
             LEFT JOIN users op ON op.id = oc.operator_id
             WHERE oc.customer_id = ?
             ORDER BY oc.id DESC
             LIMIT ' . $limit,
            [$customerId]
        );
    }

    /** Load a small, bounded recent-call history for each visible contract. */
    public static function recentForContracts(array $contractIds, int $limitPerContract = 5): array
    {
        self::ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $contractIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) return [];

        $ids = array_slice($ids, 0, 100);
        $limitPerContract = max(2, min(5, $limitPerContract));
        $queries = [];
        foreach ($ids as $contractId) {
            $queries[] = '(SELECT oc.*, op.full_name AS operator_name
                FROM operator_calls oc
                LEFT JOIN users op ON op.id = oc.operator_id
                WHERE oc.contract_id = ?
                ORDER BY oc.id DESC
                LIMIT ' . $limitPerContract . ')';
        }

        return self::fetchAll(implode(' UNION ALL ', $queries) . ' ORDER BY contract_id ASC, id DESC', $ids);
    }

    public static function createCall($operatorId, $contractId, $result, $notes, $nextFollowupDate = null, $promisePaymentDate = null, $installmentId = null)
    {
        self::ensureSchema();
        $contract = Contract::find($contractId);
        if (!$contract) {
            return false;
        }
        self::execute(
            'INSERT INTO operator_calls (operator_id, customer_id, contract_id, installment_id, call_result, notes, next_followup_date, promise_payment_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [(int) $operatorId, $contract['customer_id'], (int) $contractId, $installmentId ?: null, trim((string) $result), trim((string) $notes), $nextFollowupDate ?: null, $promisePaymentDate ?: null]
        );
        return true;
    }

    /** Persist a classified follow-up into the existing immutable call ledger. */
    public static function insertStructured(array $data): int
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO operator_calls
             (operator_id,customer_id,contract_id,installment_id,call_result,notes,next_followup_date,promise_payment_date,
              action_type,contact_target,result_code,promise_amount_toman,promise_event_type,related_promise_call_id,
              next_action_type,baseline_overdue_amount_toman,baseline_snapshot_status,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                (int) $data['operator_id'], (int) $data['customer_id'], (int) $data['contract_id'],
                !empty($data['installment_id']) ? (int) $data['installment_id'] : null,
                (string) $data['call_result'], (string) ($data['notes'] ?? ''),
                $data['next_followup_date'] ?? null, $data['promise_payment_date'] ?? null,
                (string) $data['action_type'], (string) $data['contact_target'], $data['result_code'] !== null ? (string) $data['result_code'] : null,
                $data['promise_amount_toman'] ?? null, $data['promise_event_type'] ?? null,
                $data['related_promise_call_id'] ?? null, $data['next_action_type'] ?? null,
                $data['baseline_overdue_amount_toman'] ?? null, $data['baseline_snapshot_status'] !== null ? (string) $data['baseline_snapshot_status'] : null,
            ]
        );
        return (int) Model::db()->lastInsertId();
    }
}
