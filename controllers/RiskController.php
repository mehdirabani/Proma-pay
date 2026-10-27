<?php

class RiskController extends Controller
{
    public function portfolio()
    {
        $this->requireRole('admin');
        $report = PortfolioRiskAnalyticsService::report($_GET);
        $this->render('risk/portfolio', [
            'title' => 'تحلیل ریسک پرتفوی و جریان نقدی',
            'report' => $report,
            'filters' => $report['filters'],
        ]);
    }

    public function portfolioDetail()
    {
        $this->requireRole('admin');
        $metric = (string) ($_GET['metric'] ?? '');
        try {
            $snapshot = PortfolioRiskAnalyticsService::readOnlySnapshot(static function () use ($metric): array {
                $detail = PortfolioRiskAnalyticsService::details($metric, $_GET);
                return ['detail' => $detail, 'report' => PortfolioRiskAnalyticsService::report($detail['filters'])];
            });
            $detail = $snapshot['detail'];
            $report = $snapshot['report'];
        } catch (InvalidArgumentException $e) {
            ErrorHandler::abort(404);
            return;
        }
        $labels = [
            'balance' => 'اصل ماندهٔ اقساط',
            'due' => 'اقساط سررسیدشده',
            'cash' => 'دریافت واقعی اقساط',
            'cohort' => 'تطبیق Cohort سررسید',
            'aging' => 'مطالبات معوق',
            'forecast' => 'ورودی نقدی برنامه‌ریزی‌شده',
        ];
        $metricTotal = $this->portfolioMetricTotal($metric, $report, $detail['filters']);
        $this->render('risk/portfolio-detail', [
            'title' => 'جزئیات ' . ($labels[$metric] ?? 'گزارش پرتفوی'),
            'detail' => $detail,
            'report' => $report,
            'metricLabel' => $labels[$metric] ?? 'گزارش پرتفوی',
            'metricTotal' => $metricTotal,
        ]);
    }

    public function portfolioExport()
    {
        $this->requireRole('admin');
        $metric = (string) ($_GET['metric'] ?? '');
        if (!in_array($metric, ['balance', 'due', 'cash', 'cohort', 'aging', 'forecast'], true)) {
            ErrorHandler::abort(404);
            return;
        }
        $filters = PortfolioRiskAnalyticsService::normalizeFilters($_GET);
        PortfolioRiskAnalyticsService::readOnlySnapshot(function () use ($metric, $filters): void {
            $detail = PortfolioRiskAnalyticsService::details($metric, $filters);
            $report = PortfolioRiskAnalyticsService::report($detail['filters']);
            $metricTotal = $this->portfolioMetricTotal($metric, $report, $detail['filters']);
            $filterSnapshot = json_encode([
                'from' => $detail['filters']['from'], 'to' => $detail['filters']['to'],
                'group' => $detail['filters']['group'], 'horizon_days' => $detail['filters']['horizon'],
                'risk' => $detail['filters']['risk'], 'aging' => $detail['filters']['aging'],
                'status' => $detail['filters']['status'], 'search' => $detail['filters']['q'],
                'contract_id' => $detail['filters']['contract_id'], 'customer_id' => $detail['filters']['customer_id'],
                'operator_id' => $detail['filters']['operator_id'], 'period' => $detail['filters']['period'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $coverage = (string) ($report['coverage']['schedule_basis'] ?? 'current_schedule');
            $extractedAt = date('Y-m-d H:i:s');
            $metricLabels = [
                'balance' => 'اصل ماندهٔ اقساط', 'due' => 'اقساط سررسیدشده',
                'cash' => 'دریافت واقعی اقساط', 'cohort' => 'وصول اصل Cohort',
                'aging' => 'مطالبات معوق', 'forecast' => 'ورودی قراردادی برنامه‌ریزی‌شده',
            ];
            SpreadsheetHelper::downloadPaginatedXlsx(
                'portfolio-' . $metric . '-' . date('Ymd-His') . '.xlsx',
                'Portfolio Evidence',
                ['Metric ID', 'Metric Version', 'Metric Name', 'Period Start', 'Period End', 'As Of Date', 'Extracted At', 'Filters', 'Metric Total (Toman)', 'Source Row Count', 'Currency', 'Source Scope', 'Contract ID', 'Contract Number', 'Customer', 'Installment ID', 'Installment Number', 'Payment ID', 'Payment Date', 'Due Date', 'Record Status', 'Payment Type', 'Contribution (Toman)', 'Scheduled Principal (Toman)', 'Collected Principal (Toman)', 'Remaining Principal (Toman)', 'Penalty (Toman)', 'Delay Days', 'Coverage'],
                static function (int $page, int $perPage) use ($metric, $filters): array {
                    $pageFilters = array_merge($filters, ['page' => $page, 'per_page' => min(200, $perPage)]);
                    $result = PortfolioRiskAnalyticsService::details($metric, $pageFilters);
                    return ['items' => $result['rows'], 'total' => $result['total'], 'pages' => $result['pages']];
                },
                static function (array $row) use ($metric, $metricLabels, $filters, $detail, $metricTotal, $filterSnapshot, $coverage, $extractedAt): array {
                    $isCash = $metric === 'cash';
                    $penalty = normalize_money($row['normal_penalty'] ?? 0) + normalize_money($row['legal_penalty'] ?? 0);
                    return [
                        $metric, PortfolioRiskAnalyticsService::METRIC_VERSION, $metricLabels[$metric] ?? $metric,
                        $filters['from'], $filters['to'], $detail['as_of'], $extractedAt, $filterSnapshot,
                        $metricTotal, (int) $detail['total'], 'Toman', $isCash ? 'payments: paid, uncorrected installment payments' : 'current installments + canonical installment state',
                        (int) ($row['contract_id'] ?? 0), (string) ($row['contract_number'] ?? ''), (string) ($row['customer_name'] ?? ''),
                        (int) ($row['id'] ?? $row['installment_id'] ?? 0), (int) ($row['installment_number'] ?? 0),
                        (int) ($row['payment_id'] ?? 0), (string) ($row['payment_date'] ?? ''), (string) ($row['due_date'] ?? ''),
                        (string) ($row['status'] ?? $row['calculation_status'] ?? ''), (string) ($row['payment_type'] ?? 'installment'),
                        normalize_money($row['contribution'] ?? $row['amount'] ?? 0), normalize_money($row['base_amount'] ?? 0),
                        normalize_money($row['paid_principal'] ?? 0), normalize_money($row['remaining_principal'] ?? 0),
                        $penalty, (int) ($row['overdue_days'] ?? 0), $coverage,
                    ];
                },
                50
            );
        });
    }

    private function portfolioMetricTotal(string $metric, array $report, array $filters): int
    {
        if ($metric === 'balance') return (int) ($report['summary']['outstanding_principal'] ?? 0);
        if ($metric === 'due') return (int) ($report['summary']['scheduled_period_amount'] ?? 0);
        if ($metric === 'cash') return (int) ($report['summary']['period_cash_collection'] ?? 0);
        if ($metric === 'cohort') return (int) ($report['summary']['due_cohort_collected'] ?? 0);
        if ($metric === 'aging') {
            $total = 0;
            foreach (($report['aging'] ?? []) as $bucket) {
                if (($filters['aging'] ?? 'all') === 'all' || ($filters['aging'] ?? '') === ($bucket['key'] ?? '')) $total += (int) ($bucket['total'] ?? 0);
            }
            return $total;
        }
        if ($metric === 'forecast') {
            $total = 0;
            foreach (($report['forecast'] ?? []) as $bucket) {
                if (empty($filters['period']) || $filters['period'] === ($bucket['period_key'] ?? '')) $total += (int) ($bucket['amount'] ?? 0);
            }
            return $total;
        }
        throw new InvalidArgumentException('شاخص خروجی معتبر نیست.');
    }

    public function index()
    {
        $this->requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50], true)) $perPage = 25;
        $needle = '%' . to_english_digits($query) . '%';
        $where = "role='customer' AND status='active'
             AND (? = '' OR full_name LIKE ? OR mobile LIKE ? OR national_id LIKE ?)";
        $params = [$query, $needle, $needle, $needle];
        $total = (int) (Model::fetch("SELECT COUNT(*) AS total FROM users WHERE {$where}", $params)['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $customers = Model::fetchAll(
            "SELECT id, full_name, mobile, status FROM users WHERE {$where}
             ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        try { $profiles = CreditRiskEngine::summariesForCustomers(array_column($customers, 'id')); }
        catch (Throwable $e) { $profiles = []; if (class_exists('ErrorHandler')) ErrorHandler::log('risk_profiles_list', $e, 500); }
        foreach ($customers as &$customer) $customer['credit_profile'] = $profiles[(int) $customer['id']] ?? null;
        unset($customer);
        $summary = ['low' => 0, 'medium' => 0, 'high' => 0, 'pending' => 0, 'average' => 0];
        $scores = [];
        foreach ($customers as $customer) {
            $profile = $customer['credit_profile'] ?? null;
            if (!$profile) {
                $summary['pending']++;
                continue;
            }
            $level = (string) ($profile['risk_level'] ?? 'high');
            if (!array_key_exists($level, $summary)) $level = 'high';
            $summary[$level]++;
            $scores[] = (int) ($profile['score'] ?? 0);
        }
        $summary['average'] = $scores ? (int) round(array_sum($scores) / count($scores)) : 0;
        $this->render('risk/index', [
            'title' => 'پروفایل ریسک اعتباری', 'customers' => $customers, 'query' => $query,
            'summary' => $summary, 'pagination' => ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage],
        ]);
    }

    public function summary($customerId)
    {
        $this->requireRole('admin');
        try {
            $profile = CreditRiskEngine::summaryForCustomer((int) $customerId, 300);
            $this->json(['ok' => true, 'summary' => [
                'score' => (int) ($profile['score'] ?? 0),
                'risk_level' => (string) ($profile['risk_level'] ?? 'high'),
                'recommendation' => $profile['recommendation'] ?? [],
                'calculated_at' => (string) ($profile['calculated_at'] ?? date('Y-m-d H:i:s')),
            ]]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'محاسبهٔ رتبهٔ اعتباری انجام نشد.'], 422);
        }
    }

    public function profile($customerId)
    {
        $this->requireRole('admin');
        try {
            $profile = CreditRiskEngine::profile((int) $customerId);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('risk');
        }
        $this->render('risk/profile', ['title' => 'Proma Score مشتری', 'profile' => $profile]);
    }
}
