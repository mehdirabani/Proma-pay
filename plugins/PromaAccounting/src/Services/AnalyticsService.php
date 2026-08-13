<?php

namespace Proma\Plugins\Accounting\Services;

class AnalyticsService
{
    public static function summary($userId, $startDate, $endDate)
    {
        $userId = (int) $userId;
        $startDate = self::date($startDate, '-31 days');
        $endDate = self::date($endDate, 'now');
        $account = \Model::fetch('SELECT current_balance FROM accounting_user_accounts WHERE user_id = ? LIMIT 1', [$userId]);
        $row = \Model::fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'income' THEN le.amount ELSE 0 END),0) AS income,
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'expense' THEN le.amount ELSE 0 END),0) AS expense,
                COALESCE(SUM(CASE WHEN le.entry_type = 'commission' THEN le.amount ELSE 0 END),0) AS commission,
                COALESCE(SUM(CASE WHEN le.entry_type = 'fixed_salary' THEN le.amount ELSE 0 END),0) AS salary,
                COALESCE(SUM(CASE WHEN le.entry_type = 'bonus' THEN le.amount ELSE 0 END),0) AS bonus,
                COALESCE(SUM(CASE WHEN le.entry_type = 'deduction' THEN le.amount ELSE 0 END),0) AS deduction
             FROM accounting_ledger_entries le
             LEFT JOIN accounting_analytics_categories ac ON ac.entry_type = le.entry_type AND ac.is_active = 1
             WHERE le.user_id = ? AND le.entry_date BETWEEN ? AND ?",
            [$userId, $startDate, $endDate]
        ) ?: [];
        $row['balance'] = $account['current_balance'] ?? 0;
        $row['net_result'] = Money::integer($row['income'] ?? 0) - Money::integer($row['expense'] ?? 0);
        $row['recent'] = \Model::fetchAll('SELECT id, entry_type, direction, amount, balance_before, balance_after, description, entry_date, created_at FROM accounting_ledger_entries WHERE user_id = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date DESC, id DESC LIMIT 10', [$userId, $startDate, $endDate]);
        return $row;
    }

    public static function chart($userId, $startDate, $endDate)
    {
        $startDate = self::date($startDate, '-31 days');
        $endDate = self::date($endDate, 'now');
        $rows = \Model::fetchAll(
            "SELECT le.entry_date AS bucket,
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'income' THEN le.amount ELSE 0 END),0) AS income,
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'expense' THEN le.amount ELSE 0 END),0) AS expense,
                COALESCE(SUM(CASE WHEN le.entry_type = 'commission' THEN le.amount ELSE 0 END),0) AS commission,
                COALESCE(SUM(CASE WHEN le.entry_type = 'fixed_salary' THEN le.amount ELSE 0 END),0) AS salary,
                COALESCE(SUM(CASE WHEN le.entry_type = 'bonus' THEN le.amount ELSE 0 END),0) AS bonus,
                COALESCE(SUM(CASE WHEN le.entry_type = 'deduction' THEN le.amount ELSE 0 END),0) AS deduction
             FROM accounting_ledger_entries le
             LEFT JOIN accounting_analytics_categories ac ON ac.entry_type = le.entry_type AND ac.is_active = 1
             WHERE le.user_id = ? AND le.entry_date BETWEEN ? AND ?
             GROUP BY le.entry_date ORDER BY le.entry_date ASC",
            [(int) $userId, $startDate, $endDate]
        );
        return ['labels' => array_column($rows, 'bucket'), 'rows' => $rows];
    }

    public static function monthlyChart($userId, $startDate, $endDate)
    {
        $startDate = self::date($startDate, '-11 months');
        $endDate = self::date($endDate, 'now');
        $rows = \Model::fetchAll(
            "SELECT DATE_FORMAT(le.entry_date, '%Y-%m') AS bucket,
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'income' THEN le.amount ELSE 0 END),0) AS income,
                COALESCE(SUM(CASE WHEN ac.analytics_direction = 'expense' THEN le.amount ELSE 0 END),0) AS expense
             FROM accounting_ledger_entries le
             LEFT JOIN accounting_analytics_categories ac ON ac.entry_type = le.entry_type AND ac.is_active = 1
             WHERE le.user_id = ? AND le.entry_date BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(le.entry_date, '%Y-%m') ORDER BY bucket ASC",
            [(int) $userId, $startDate, $endDate]
        );
        foreach ($rows as &$row) { $row['net'] = Money::integer($row['income'] ?? 0) - Money::integer($row['expense'] ?? 0); }
        unset($row);
        return ['labels' => array_column($rows, 'bucket'), 'rows' => $rows, 'income' => array_column($rows, 'income'), 'expense' => array_column($rows, 'expense'), 'net' => array_column($rows, 'net')];
    }

    public static function classifications()
    {
        return \Model::fetchAll('SELECT * FROM accounting_analytics_categories WHERE is_active = 1 ORDER BY sort_order, id');
    }

    protected static function date($value, $fallback)
    {
        $value = trim((string) $value);
        if (function_exists('parse_jalali_date') && $value !== '') {
            $parsed = parse_jalali_date($value);
            if ($parsed) { return $parsed; }
        }
        return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d', strtotime($fallback));
    }
}
