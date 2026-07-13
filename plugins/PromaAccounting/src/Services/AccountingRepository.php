<?php

namespace Proma\Plugins\Accounting\Services;

class AccountingRepository
{
    public static function dashboard()
    {
        return [
            'users' => (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM users WHERE role IN ('admin','operator','lawyer') AND status = 'active'")['total'] ?? 0)),
            'sales' => (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM plugin_accounting_sales WHERE status NOT IN ('cancelled','deleted')")['total'] ?? 0)),
            'commission' => (string) ((\Model::fetch("SELECT COALESCE(SUM(calculated_amount), 0) AS total FROM plugin_accounting_commissions WHERE status IN ('pending','payable','approved','posted')")['total'] ?? '0')),
            'pending_commissions' => (string) ((\Model::fetch("SELECT COALESCE(SUM(calculated_amount), 0) AS total FROM plugin_accounting_commissions WHERE status = 'pending'")['total'] ?? '0')),
            'payable_commissions' => (string) ((\Model::fetch("SELECT COALESCE(SUM(calculated_amount), 0) AS total FROM plugin_accounting_commissions WHERE status IN ('payable','approved','posted')")['total'] ?? '0')),
            'positive_balances' => (string) ((\Model::fetch("SELECT COALESCE(SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END), 0) AS total FROM accounting_user_accounts")['total'] ?? '0')),
            'negative_balances' => (string) ((\Model::fetch("SELECT COALESCE(SUM(CASE WHEN current_balance < 0 THEN current_balance ELSE 0 END), 0) AS total FROM accounting_user_accounts")['total'] ?? '0')),
            'ledger' => (string) ((\Model::fetch("SELECT COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE -amount END), 0) AS total FROM accounting_ledger_entries")['total'] ?? '0')),
            'payments_this_month' => (string) ((\Model::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM accounting_ledger_entries WHERE direction = 'decrease' AND entry_type IN ('payment','payment_to_user') AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['total'] ?? '0')),
            'receipts_this_month' => (string) ((\Model::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM accounting_ledger_entries WHERE direction = 'increase' AND entry_type IN ('receipt','receipt_from_user') AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['total'] ?? '0')),
        ];
    }

    public static function dashboardSeries()
    {
        $rows = \Model::fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period,
                    COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE 0 END), 0) AS receipts,
                    COALESCE(SUM(CASE WHEN direction = 'decrease' THEN amount ELSE 0 END), 0) AS payments
             FROM accounting_ledger_entries
             WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY period"
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['period']] = $row;
        }
        $labels = [];
        $receipts = [];
        $payments = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $cursor->modify('-' . $offset . ' months');
            $key = $month->format('Y-m');
            $labels[] = \jdate($month->format('Y-m-01'));
            $receipts[] = (int) ($indexed[$key]['receipts'] ?? 0);
            $payments[] = (int) ($indexed[$key]['payments'] ?? 0);
        }
        return ['labels' => $labels, 'receipts' => $receipts, 'payments' => $payments];
    }

    public static function recentLedger($limit = 8)
    {
        $limit = max(1, min(20, (int) $limit));
        return \Model::fetchAll(
            "SELECT l.*, u.full_name, u.role, u.mobile
             FROM accounting_ledger_entries l
             JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT {$limit}"
        );
    }

    public static function topSellers($limit = 5)
    {
        $limit = max(1, min(12, (int) $limit));
        return \Model::fetchAll(
            "SELECT u.id, u.full_name, u.role, u.mobile, COUNT(s.id) AS sales_count,
                    COALESCE(SUM(s.financed_amount), 0) AS financed_total
             FROM plugin_accounting_sales s
             JOIN users u ON u.id = s.seller_user_id
             WHERE s.status NOT IN ('cancelled', 'deleted')
             GROUP BY u.id, u.full_name, u.role, u.mobile
             ORDER BY financed_total DESC, sales_count DESC LIMIT {$limit}"
        );
    }

    public static function accounts($search = '', $page = 1, $perPage = 50, $filters = [])
    {
        $params = [];
        $where = ["u.role IN ('admin','operator','lawyer')", "u.status = 'active'"];
        if (trim((string) $search) !== '') {
            $needle = '%' . trim((string) $search) . '%';
            $where[] = '(u.full_name LIKE ? OR u.mobile LIKE ? OR u.username LIKE ?)';
            $params = [$needle, $needle, $needle];
        }
        if (($filters['balance'] ?? '') === 'positive') {
            $where[] = 'COALESCE(a.current_balance, 0) > 0';
        } elseif (($filters['balance'] ?? '') === 'negative') {
            $where[] = 'COALESCE(a.current_balance, 0) < 0';
        } elseif (($filters['balance'] ?? '') === 'zero') {
            $where[] = 'COALESCE(a.current_balance, 0) = 0';
        }
        if (in_array($filters['role'] ?? '', ['admin', 'operator', 'lawyer'], true)) {
            $where[] = 'u.role = ?';
            $params[] = $filters['role'];
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $where[] = "COALESCE(a.status, 'open') = ?";
            $params[] = $filters['status'];
        }
        $perPage = max(10, min(100, (int) $perPage));
        $page = max(1, (int) $page);
        $whereSql = implode(' AND ', $where);
        $total = (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM users u LEFT JOIN accounting_user_accounts a ON a.user_id = u.id WHERE {$whereSql}", $params)['total'] ?? 0));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $sorts = [
            'name' => 'u.full_name ASC',
            'balance_desc' => 'COALESCE(a.current_balance, 0) DESC, u.full_name ASC',
            'balance_asc' => 'COALESCE(a.current_balance, 0) ASC, u.full_name ASC',
            'recent' => 'last_transaction_at DESC, u.full_name ASC',
        ];
        $orderBy = $sorts[$filters['sort'] ?? 'name'] ?? $sorts['name'];
        $items = \Model::fetchAll(
            "SELECT u.id, u.full_name, u.username, u.role, u.mobile, a.account_number, a.current_balance AS balance, a.opening_balance, a.status AS account_status,
                    (SELECT COALESCE(SUM(l.amount), 0) FROM accounting_ledger_entries l WHERE l.user_id = u.id AND l.direction = 'increase') AS total_increase,
                    (SELECT COALESCE(SUM(l.amount), 0) FROM accounting_ledger_entries l WHERE l.user_id = u.id AND l.direction = 'decrease') AS total_decrease,
                    (SELECT MAX(l.created_at) FROM accounting_ledger_entries l WHERE l.user_id = u.id) AS last_transaction_at,
                    (SELECT COALESCE(SUM(cm.calculated_amount), 0) FROM plugin_accounting_commissions cm WHERE cm.seller_user_id = u.id AND cm.status IN ('approved','payable','posted','paid')) AS total_commission
             FROM users u
             LEFT JOIN accounting_user_accounts a ON a.user_id = u.id
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public static function ledger($userId, $page = 1, $perPage = 100)
    {
        $perPage = max(10, min(200, (int) $perPage));
        $page = max(1, (int) $page);
        $total = (int) ((\Model::fetch('SELECT COUNT(*) AS total FROM accounting_ledger_entries WHERE user_id = ?', [(int) $userId])['total'] ?? 0));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $items = \Model::fetchAll(
            "SELECT l.*, u.full_name, a.account_number, c.title AS category_title
             FROM accounting_ledger_entries l
             JOIN users u ON u.id = l.user_id
             JOIN accounting_user_accounts a ON a.id = l.account_id
             LEFT JOIN accounting_categories c ON c.id = l.category_id
             WHERE l.user_id = ?
             ORDER BY l.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [(int) $userId]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public static function accountSummary($userId)
    {
        return \Model::fetch(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE 0 END), 0) AS total_increase,
                    COALESCE(SUM(CASE WHEN direction = 'decrease' THEN amount ELSE 0 END), 0) AS total_decrease,
                    COALESCE(SUM(CASE WHEN entry_type IN ('commission') AND direction = 'increase' THEN amount ELSE 0 END), 0) AS total_commission,
                    COALESCE(SUM(CASE WHEN entry_type IN ('payment','payment_to_user') AND direction = 'decrease' THEN amount ELSE 0 END), 0) AS paid_to_user,
                    COALESCE(SUM(CASE WHEN entry_type IN ('receipt','receipt_from_user') AND direction = 'increase' THEN amount ELSE 0 END), 0) AS received_from_user,
                    MAX(created_at) AS last_transaction_at
             FROM accounting_ledger_entries WHERE user_id = ?",
            [(int) $userId]
        ) ?: [];
    }

    public static function sales($page = 1, $perPage = 50, array $filters = [])
    {
        $perPage = max(10, min(100, (int) $perPage));
        $page = max(1, (int) $page);
        $params = [];
        $where = ['1 = 1'];
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(c.contract_number LIKE ? OR cu.full_name LIKE ? OR su.full_name LIKE ?)';
            $params = [$needle, $needle, $needle];
        }
        if (in_array($filters['status'] ?? '', ['active', 'cancelled', 'deleted', 'pending'], true)) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM plugin_accounting_sales s JOIN contracts c ON c.id = s.contract_id JOIN users cu ON cu.id = s.customer_id LEFT JOIN users su ON su.id = s.seller_user_id WHERE {$whereSql}", $params)['total'] ?? 0));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        return ['items' => \Model::fetchAll(
            "SELECT s.*, c.contract_number, cu.full_name AS customer_name, su.full_name AS seller_name
             FROM plugin_accounting_sales s
             JOIN contracts c ON c.id = s.contract_id
             JOIN users cu ON cu.id = s.customer_id
             LEFT JOIN users su ON su.id = s.seller_user_id
             WHERE {$whereSql}
             ORDER BY s.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        ), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public static function commissions($page = 1, $perPage = 50, array $filters = [])
    {
        $perPage = max(10, min(100, (int) $perPage));
        $page = max(1, (int) $page);
        $params = [];
        $where = ['1 = 1'];
        if (in_array($filters['status'] ?? '', ['pending', 'approved', 'payable', 'posted', 'paid', 'reversed', 'cancelled'], true)) {
            $where[] = 'cm.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['seller_user_id'])) {
            $where[] = 'cm.seller_user_id = ?';
            $params[] = (int) $filters['seller_user_id'];
        }
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ?)';
            $params[] = $needle;
            $params[] = $needle;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) ((\Model::fetch("SELECT COUNT(*) AS total FROM plugin_accounting_commissions cm JOIN contracts c ON c.id = cm.contract_id LEFT JOIN users u ON u.id = cm.seller_user_id WHERE {$whereSql}", $params)['total'] ?? 0));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        return ['items' => \Model::fetchAll(
            "SELECT cm.*, c.contract_number, u.full_name AS seller_name
             FROM plugin_accounting_commissions cm
             JOIN contracts c ON c.id = cm.contract_id
             LEFT JOIN users u ON u.id = cm.seller_user_id
             WHERE {$whereSql}
             ORDER BY cm.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        ), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public static function rules()
    {
        return \Model::fetchAll('SELECT r.*, u.full_name AS user_name FROM plugin_accounting_commission_rules r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.priority DESC, r.id DESC');
    }

    public static function settings()
    {
        $rows = \Model::fetchAll('SELECT setting_key, setting_value FROM plugin_accounting_settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $settings;
    }

    public static function categories()
    {
        return \Model::fetchAll('SELECT * FROM accounting_categories WHERE is_active = 1 ORDER BY sort_order, id');
    }

    public static function staff($search = '')
    {
        $needle = '%' . trim((string) $search) . '%';
        return \Model::fetchAll("SELECT id, full_name, role, mobile FROM users WHERE role IN ('admin','operator','lawyer') AND status = 'active' AND (full_name LIKE ? OR mobile LIKE ? OR username LIKE ?) ORDER BY full_name LIMIT 100", [$needle, $needle, $needle]);
    }
}
