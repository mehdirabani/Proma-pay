<?php

class DashboardController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $role = Auth::role();
        if ($role === 'admin') {
            $this->admin();
            return;
        }
        if ($role === 'operator') {
            $this->operator();
            return;
        }
        if ($role === 'lawyer') {
            $this->lawyer();
            return;
        }
        $this->customer();
    }

    protected function admin()
    {
        $monthStart = date('Y-m-01');
        $nextMonthStart = date('Y-m-01', strtotime('+1 month'));

        $receivedMonth = $this->sumValue(
            "SELECT COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE status = 'paid'
             AND COALESCE(paid_at, created_at) >= ?
             AND COALESCE(paid_at, created_at) < ?",
            [$monthStart, $nextMonthStart]
        );
        $dueMonth = $this->sumValue(
            "SELECT COALESCE(SUM(base_amount),0) AS total
             FROM installments
             WHERE due_date >= ? AND due_date < ?",
            [$monthStart, $nextMonthStart]
        );
        $outstanding = $this->sumValue(
            "SELECT COALESCE(SUM(GREATEST(base_amount - paid_amount, 0)),0) AS total
             FROM installments
             WHERE status != 'paid'"
        );
        $overdueAmount = $this->sumValue(
            "SELECT COALESCE(SUM(GREATEST(base_amount - paid_amount, 0)),0) AS total
             FROM installments
             WHERE status != 'paid' AND due_date < CURDATE()"
        );
        $pendingPayment = Model::fetch(
            "SELECT COUNT(*) AS total, COALESCE(SUM(amount),0) AS amount
             FROM payments
             WHERE status = 'pending'"
        ) ?: ['total' => 0, 'amount' => 0];

        $kpis = [
            'customers' => $this->countValue("SELECT COUNT(*) AS total FROM users WHERE role = 'customer' AND status = 'active'"),
            'contracts' => $this->countValue("SELECT COUNT(*) AS total FROM contracts WHERE status = 'active'"),
            'contracts_total' => $this->countValue('SELECT COUNT(*) AS total FROM contracts'),
            'installments_total' => $this->countValue('SELECT COUNT(*) AS total FROM installments'),
            'overdue' => $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date < CURDATE()"),
            'due_today' => $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date = CURDATE()"),
            'due_week' => $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'received' => $receivedMonth,
            'due_month' => $dueMonth,
            'outstanding' => $outstanding,
            'overdue_amount' => $overdueAmount,
            'pending_payments' => (int) $pendingPayment['total'],
            'pending_payment_amount' => (float) $pendingPayment['amount'],
            'legal_open' => $this->countValue("SELECT COUNT(*) AS total FROM legal_cases WHERE status != 'closed'"),
            'collection_rate' => $dueMonth > 0 ? round(min(100, ($receivedMonth / $dueMonth) * 100), 1) : 0,
            'overdue_share' => $outstanding > 0 ? round(min(100, ($overdueAmount / $outstanding) * 100), 1) : 0,
        ];

        $paymentTrend = $this->paymentTrend();
        $installmentStatus = $this->statusChart(
            Model::fetchAll('SELECT status, COUNT(*) AS total FROM installments GROUP BY status ORDER BY total DESC'),
            [
                'paid' => '#54ba4a',
                'partial' => '#16c7f9',
                'pending' => '#ffaa05',
                'overdue' => '#fc4438',
            ]
        );
        $contractStatus = $this->statusChart(
            Model::fetchAll('SELECT status, COUNT(*) AS total FROM contracts GROUP BY status ORDER BY total DESC'),
            [
                'active' => '#7366ff',
                'closed' => '#54ba4a',
                'referred' => '#ffaa05',
                'inactive' => '#8b8d98',
            ]
        );
        $overdueBuckets = [
            'labels' => ['امروز', '۱ تا ۷ روز', '۸ تا ۳۰ روز', 'بیش از ۳۰ روز'],
            'data' => [
                $kpis['due_today'],
                $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 7"),
                $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) BETWEEN 8 AND 30"),
                $this->countValue("SELECT COUNT(*) AS total FROM installments WHERE status != 'paid' AND due_date < CURDATE() AND DATEDIFF(CURDATE(), due_date) > 30"),
            ],
        ];
        $upcoming = Model::fetchAll(
            "SELECT i.*, c.contract_number, u.full_name AS customer_name, u.mobile
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE i.status != 'paid'
             AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY i.due_date ASC, i.id ASC
             LIMIT 8"
        );
        $recentPayments = Model::fetchAll(
            "SELECT p.*, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.id = p.installment_id
             WHERE p.status = 'paid'
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
             LIMIT 7"
        );
        $riskCustomers = Model::fetchAll(
            "SELECT u.id, u.full_name, u.mobile, COUNT(i.id) AS overdue_count,
             COALESCE(SUM(GREATEST(i.base_amount - i.paid_amount, 0)),0) AS debt,
             MIN(i.due_date) AS oldest_due_date
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE i.status != 'paid' AND i.due_date < CURDATE()
             GROUP BY u.id, u.full_name, u.mobile
             ORDER BY debt DESC, overdue_count DESC
             LIMIT 6"
        );
        $operatorLoad = Model::fetchAll(
            "SELECT op.full_name, COUNT(DISTINCT c.id) AS contracts,
             COUNT(i.id) AS overdue_count
             FROM users op
             LEFT JOIN contracts c ON c.assigned_operator_id = op.id
             LEFT JOIN installments i ON i.contract_id = c.id AND i.status != 'paid' AND i.due_date < CURDATE()
             WHERE op.role = 'operator'
             GROUP BY op.id, op.full_name
             ORDER BY overdue_count DESC, contracts DESC
            LIMIT 5"
        );
        $bestCustomers = $this->customerRanking('best');
        $worstCustomers = $this->customerRanking('worst');

        $this->render('dashboard/admin', [
            'title' => 'داشبورد مدیریت',
            'kpis' => $kpis,
            'chartLabels' => $paymentTrend['labels'],
            'chartData' => $paymentTrend['data'],
            'installmentStatus' => $installmentStatus,
            'contractStatus' => $contractStatus,
            'overdueBuckets' => $overdueBuckets,
            'upcoming' => $upcoming,
            'recentPayments' => $recentPayments,
            'riskCustomers' => $riskCustomers,
            'bestCustomers' => $bestCustomers,
            'worstCustomers' => $worstCustomers,
            'operatorLoad' => $operatorLoad,
            'overdue' => array_slice(Installment::overdue(), 0, 8),
        ]);
    }

    protected function countValue($sql, array $params = [])
    {
        $row = Model::fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    protected function sumValue($sql, array $params = [])
    {
        $row = Model::fetch($sql, $params);
        return (float) ($row['total'] ?? 0);
    }

    protected function paymentTrend()
    {
        $start = new DateTime('first day of this month');
        $start->modify('-5 months');
        $rows = Model::fetchAll(
            "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') AS month_key,
             COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE status = 'paid' AND COALESCE(paid_at, created_at) >= ?
             GROUP BY month_key
             ORDER BY month_key",
            [$start->format('Y-m-01')]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month_key']] = (float) $row['total'];
        }

        $labels = [];
        $data = [];
        for ($i = 0; $i < 6; $i++) {
            $date = (clone $start)->modify('+' . $i . ' months');
            $key = $date->format('Y-m');
            $labels[] = mb_substr(jdate($date->format('Y-m-01')), 0, 7, 'UTF-8');
            $data[] = $totals[$key] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    protected function statusChart(array $rows, array $colors)
    {
        $labels = [];
        $data = [];
        $chartColors = [];
        foreach ($rows as $row) {
            $status = $row['status'];
            $labels[] = status_label($status);
            $data[] = (int) $row['total'];
            $chartColors[] = $colors[$status] ?? '#8b8d98';
        }
        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $chartColors,
        ];
    }

    protected function customerRanking($mode)
    {
        $rows = Model::fetchAll(
            "SELECT u.id, u.full_name, u.mobile,
             (SELECT COUNT(*) FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE c.customer_id = u.id AND i.status = 'paid') AS paid_count,
             (SELECT COUNT(*) FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE c.customer_id = u.id AND i.status != 'paid' AND i.due_date < CURDATE()) AS overdue_count,
             (SELECT COALESCE(AVG(DATEDIFF(CURDATE(), i.due_date)),0) FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE c.customer_id = u.id AND i.status != 'paid' AND i.due_date < CURDATE()) AS avg_delay,
             (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE c.customer_id = u.id AND p.status = 'paid') AS total_paid,
             (SELECT COUNT(*) FROM contracts c WHERE c.customer_id = u.id AND c.status = 'active') AS active_contracts,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.customer_id = u.id AND lc.status != 'closed') AS legal_count
             FROM users u
             WHERE u.role = 'customer' AND u.status = 'active'"
        );
        foreach ($rows as &$row) {
            $score = ((int) $row['paid_count'] * 12)
                + ((float) $row['total_paid'] / 1000000)
                + ((int) $row['active_contracts'] * 5)
                - ((int) $row['overdue_count'] * 15)
                - ((float) $row['avg_delay'])
                - ((int) $row['legal_count'] * 20);
            $row['score'] = max(0, (int) round($score));
        }
        unset($row);
        usort($rows, function ($a, $b) use ($mode) {
            return $mode === 'worst' ? ($a['score'] <=> $b['score']) : ($b['score'] <=> $a['score']);
        });
        return array_slice($rows, 0, 6);
    }

    protected function operator()
    {
        $this->render('dashboard/operator', [
            'title' => 'داشبورد اپراتور',
            'contracts' => Contract::all(['operator_id' => Auth::id()]),
            'calls' => OperatorCall::all(Auth::id()),
        ]);
    }

    protected function lawyer()
    {
        $this->render('dashboard/lawyer', [
            'title' => 'داشبورد وکیل',
            'cases' => LegalCase::all(['lawyer_id' => Auth::id()]),
            'eligible' => LegalCase::eligibleContracts(),
        ]);
    }

    protected function customer()
    {
        $customerId = Auth::id();
        $this->render('dashboard/customer', [
            'title' => 'داشبورد مشتری',
            'contracts' => Contract::all(['customer_id' => $customerId]),
            'installments' => Installment::all(['customer_id' => $customerId]),
            'medals' => Model::fetchAll('SELECT * FROM medals WHERE user_id = ? ORDER BY id DESC', [$customerId]),
        ]);
    }
}
