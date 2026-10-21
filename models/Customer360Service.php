<?php

/** Bounded read models used by the administrative Customer 360 workspace. */
final class Customer360Service
{
    public static function summary(int $customerId): array
    {
        $row = \Model::fetch(
            "SELECT
                COUNT(DISTINCT c.id) AS contract_count,
                COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.id END) AS active_contract_count,
                COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','cancelled') AND i.due_date < CURDATE() THEN 1 ELSE 0 END),0) AS overdue_installment_count,
                COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','cancelled') THEN GREATEST(COALESCE(i.base_amount,0) - COALESCE(i.paid_amount,0),0) ELSE 0 END),0) AS installment_balance
             FROM contracts c
             LEFT JOIN installments i ON i.contract_id = c.id
             WHERE c.customer_id = ?",
            [$customerId]
        ) ?: [];

        return [
            'contract_count' => (int) ($row['contract_count'] ?? 0),
            'active_contract_count' => (int) ($row['active_contract_count'] ?? 0),
            'overdue_installment_count' => (int) ($row['overdue_installment_count'] ?? 0),
            'installment_balance' => (int) ($row['installment_balance'] ?? 0),
        ];
    }

    public static function referrals(int $customerId): array
    {
        try {
            return [
                'member' => \Model::fetch('SELECT referral_code, reward_balance, purchase_credit, lifetime_rewards, status FROM proma_affiliate_members WHERE user_id = ? LIMIT 1', [$customerId]),
                'referred_by' => \Model::fetch(
                    'SELECT r.id, r.status, r.reward_amount, r.created_at, u.id AS user_id, u.full_name, u.mobile
                     FROM proma_affiliate_referrals r JOIN users u ON u.id = r.referrer_user_id
                     WHERE r.referred_user_id = ? LIMIT 1',
                    [$customerId]
                ),
                'referrals' => \Model::fetchAll(
                    'SELECT r.id, r.status, r.reward_amount, r.created_at, u.id AS user_id, u.full_name, u.mobile
                     FROM proma_affiliate_referrals r JOIN users u ON u.id = r.referred_user_id
                     WHERE r.referrer_user_id = ? ORDER BY r.id DESC LIMIT 50',
                    [$customerId]
                ),
            ];
        } catch (\Throwable $ignored) {
            return ['member' => null, 'referred_by' => null, 'referrals' => []];
        }
    }

    public static function timeline(int $customerId, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $items = [];
        $sources = [
            [
                "SELECT 'contract' AS kind, c.id AS source_id, c.created_at, CONCAT('قرارداد ', c.contract_number) AS title, CONCAT('وضعیت: ', c.status) AS description, CONCAT('contracts/show/', c.id) AS route
                 FROM contracts c WHERE c.customer_id = ? ORDER BY c.id DESC LIMIT 30",
                [$customerId],
            ],
            [
                "SELECT 'payment' AS kind, p.id AS source_id, COALESCE(p.paid_at,p.created_at) AS created_at, CONCAT('پرداخت ', FORMAT(p.amount,0), ' تومان') AS title, CONCAT('قرارداد ', c.contract_number) AS description, 'payments' AS route
                 FROM payments p JOIN contracts c ON c.id = p.contract_id
                 WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0 ORDER BY p.id DESC LIMIT 30",
                [$customerId],
            ],
            [
                "SELECT 'call' AS kind, oc.id AS source_id, oc.created_at, 'تماس اپراتور' AS title, CONCAT(COALESCE(oc.call_result,''), CASE WHEN COALESCE(oc.notes,'') = '' THEN '' ELSE CONCAT(' · ', oc.notes) END) AS description, 'overdue' AS route
                 FROM operator_calls oc WHERE oc.customer_id = ? ORDER BY oc.id DESC LIMIT 30",
                [$customerId],
            ],
            [
                "SELECT 'legal' AS kind, lc.id AS source_id, lc.updated_at AS created_at, CONCAT('پرونده حقوقی قرارداد ', c.contract_number) AS title, CONCAT(COALESCE(lc.stage,''), ' · ', COALESCE(lc.status,'')) AS description, CONCAT('legal/show/', lc.id) AS route
                 FROM legal_cases lc JOIN contracts c ON c.id = lc.contract_id
                 WHERE lc.customer_id = ? ORDER BY lc.updated_at DESC, lc.id DESC LIMIT 30",
                [$customerId],
            ],
        ];

        foreach ($sources as [$sql, $params]) {
            try {
                foreach (\Model::fetchAll($sql, $params) as $item) $items[] = $item;
            } catch (\Throwable $ignored) { }
        }
        try {
            foreach (\Model::fetchAll(
                'SELECT id AS source_id, event_type AS kind, created_at,
                        JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.title")) AS title,
                        JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.description")) AS description,
                        JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.route")) AS route
                 FROM customer_automation_events WHERE customer_id = ? ORDER BY id DESC LIMIT 30',
                [$customerId]
            ) as $item) $items[] = $item;
        } catch (\Throwable $ignored) { }

        usort($items, static function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''))
                ?: ((int) ($b['source_id'] ?? 0) <=> (int) ($a['source_id'] ?? 0));
        });
        return array_slice($items, 0, $limit);
    }
}
