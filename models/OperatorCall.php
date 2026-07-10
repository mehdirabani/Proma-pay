<?php

class OperatorCall extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE operator_calls ADD COLUMN installment_id BIGINT UNSIGNED NULL AFTER contract_id');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE operator_calls ADD COLUMN promise_payment_date DATE NULL AFTER next_followup_date');
        } catch (Throwable $e) {
        }
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
}
