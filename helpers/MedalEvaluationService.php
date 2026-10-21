<?php

final class MedalEvaluationService
{
    public static function metricsFor($userId)
    {
        $userId = (int) $userId;
        return [
            'contract_count' => self::count("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status != 'cancelled'", [$userId]),
            'payment_count' => self::count("SELECT COUNT(*) AS total FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0", [$userId]),
            'on_time_count' => self::count("SELECT COUNT(DISTINCT i.id) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id JOIN payments p ON p.installment_id = i.id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND DATE(COALESCE(p.paid_at, p.created_at)) <= i.due_date", [$userId]),
            'early_payment_count' => self::count("SELECT COUNT(DISTINCT i.id) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id JOIN payments p ON p.installment_id = i.id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND DATE(COALESCE(p.paid_at, p.created_at)) < i.due_date", [$userId]),
            'completed_contract_count' => self::count("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status = 'completed'", [$userId]),
            'overdue_count' => self::count("SELECT COUNT(*) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE c.customer_id = ? AND c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date < CURDATE() AND GREATEST(COALESCE(i.remaining_amount, i.base_amount - i.paid_amount), 0) > 0", [$userId]),
            'early_settlement_count' => self::count("SELECT COUNT(*) AS total FROM contracts c WHERE c.customer_id = ? AND c.status = 'completed' AND c.updated_at IS NOT NULL AND DATE(c.updated_at) < (SELECT MAX(i.due_date) FROM installments i WHERE i.contract_id = c.id)", [$userId]),
        ];
    }

    public static function matches(array $definition, array $metrics)
    {
        $metric = (string) ($definition['criteria_type'] ?? '');
        if ($metric === '' || !array_key_exists($metric, $metrics)) {
            return false;
        }
        $criteria = json_decode((string) ($definition['criteria_json'] ?? ''), true) ?: [];
        $value = (int) $metrics[$metric];
        return (!isset($criteria['minimum']) || $value >= (int) $criteria['minimum'])
            && (!isset($criteria['maximum']) || $value <= (int) $criteria['maximum']);
    }

    protected static function count($sql, array $params)
    {
        return (int) (Model::fetch($sql, $params)['total'] ?? 0);
    }
}
