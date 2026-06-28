<?php

class OperatorCall extends Model
{
    public static function all($operatorId = null)
    {
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

    public static function createCall($operatorId, $contractId, $result, $notes, $nextFollowupDate)
    {
        $contract = Contract::find($contractId);
        if (!$contract) {
            return false;
        }
        self::execute(
            'INSERT INTO operator_calls (operator_id, customer_id, contract_id, call_result, notes, next_followup_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [(int) $operatorId, $contract['customer_id'], (int) $contractId, $result, $notes, $nextFollowupDate ?: null]
        );
        return true;
    }
}
