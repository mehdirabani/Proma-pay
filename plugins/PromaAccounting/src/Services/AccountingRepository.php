<?php

namespace Proma\Plugins\Accounting\Services;

class AccountingRepository
{
    public static function dashboard()
    {
        return [
            'users' => (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM users WHERE role IN ('admin','operator','lawyer') AND status = 'active'")['total'] ?? 0)),
            'sales' => (int) ((\Model::fetch('SELECT COUNT(*) AS total FROM plugin_accounting_sales WHERE status != \'cancelled\'')['total'] ?? 0)),
            'commission' => (string) ((\Model::fetch("SELECT COALESCE(SUM(calculated_amount), 0) AS total FROM plugin_accounting_commissions WHERE status IN ('pending','payable','approved','posted')")['total'] ?? '0')),
            'ledger' => (string) ((\Model::fetch("SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS total FROM plugin_accounting_ledger_entries")['total'] ?? '0')),
        ];
    }

    public static function accounts($search = '', $limit = 50)
    {
        $params = [];
        $where = "u.role IN ('admin','operator','lawyer')";
        if (trim((string) $search) !== '') {
            $needle = '%' . trim((string) $search) . '%';
            $where .= ' AND (u.full_name LIKE ? OR u.mobile LIKE ? OR u.username LIKE ?)';
            $params = [$needle, $needle, $needle];
        }
        $limit = max(1, min(100, (int) $limit));
        return \Model::fetchAll(
            "SELECT u.id, u.full_name, u.role, u.mobile, a.account_number,
                    COALESCE(SUM(CASE WHEN l.direction = 'credit' THEN l.amount ELSE -l.amount END), 0) AS balance
             FROM users u
             LEFT JOIN plugin_accounting_accounts a ON a.user_id = u.id
             LEFT JOIN plugin_accounting_ledger_entries l ON l.user_id = u.id
             WHERE {$where}
             GROUP BY u.id, u.full_name, u.role, u.mobile, a.account_number
             ORDER BY u.full_name ASC
             LIMIT {$limit}",
            $params
        );
    }

    public static function ledger($userId, $limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        return \Model::fetchAll(
            "SELECT l.*, u.full_name, a.account_number
             FROM plugin_accounting_ledger_entries l
             JOIN users u ON u.id = l.user_id
             JOIN plugin_accounting_accounts a ON a.id = l.account_id
             WHERE l.user_id = ?
             ORDER BY l.id DESC
             LIMIT {$limit}",
            [(int) $userId]
        );
    }

    public static function sales($limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        return \Model::fetchAll(
            "SELECT s.*, c.contract_number, cu.full_name AS customer_name, su.full_name AS seller_name
             FROM plugin_accounting_sales s
             JOIN contracts c ON c.id = s.contract_id
             JOIN users cu ON cu.id = s.customer_id
             LEFT JOIN users su ON su.id = s.seller_user_id
             ORDER BY s.id DESC LIMIT {$limit}"
        );
    }

    public static function commissions($limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        return \Model::fetchAll(
            "SELECT cm.*, c.contract_number, u.full_name AS seller_name
             FROM plugin_accounting_commissions cm
             JOIN contracts c ON c.id = cm.contract_id
             LEFT JOIN users u ON u.id = cm.seller_user_id
             ORDER BY cm.id DESC LIMIT {$limit}"
        );
    }
}
