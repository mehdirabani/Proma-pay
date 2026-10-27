<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/models/PortfolioRiskAnalyticsService.php';
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) throw new RuntimeException('Missing file: ' . $path);
    return $content;
};
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

$version = require $root . '/config/version.php';
$service = $read('models/PortfolioRiskAnalyticsService.php');
$riskEngine = $read('helpers/CreditRiskEngine.php');
$riskController = $read('controllers/RiskController.php');
$dashboardController = $read('controllers/DashboardController.php');
$dashboardView = $read('views/dashboard/admin.php');
$portfolioView = $read('views/risk/portfolio.php');
$detailView = $read('views/risk/portfolio-detail.php');
$layout = $read('views/layouts/app.php');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '7.0.4', '>='), 'Portfolio analytics must remain available in Core 7.0.4 and later.');
$filters = PortfolioRiskAnalyticsService::normalizeFilters([
    'from' => '2026-01-01', 'to' => '2026-02-01', 'group' => 'quarter', 'horizon' => '14',
    'risk' => 'critical', 'aging' => '1-7', 'status' => 'active', 'per_page' => '50',
]);
$assert($filters['from'] === '2026-01-01' && $filters['to'] === '2026-02-01', 'Valid Gregorian report dates must remain stable.');
$assert($filters['group'] === 'month' && $filters['horizon'] === 30 && $filters['risk'] === 'all', 'Report filters must be bounded by server-side allowlists.');
$assert($filters['aging'] === '1-7' && $filters['status'] === 'active' && $filters['per_page'] === 50, 'Supported filters and page sizes must be retained.');
$assert(str_contains($service, 'SET TRANSACTION READ ONLY') && !str_contains($service, 'Model::execute(') && !str_contains($service, 'Model::begin('), 'The portfolio report must execute on a read-only snapshot and never write through Model.');
$assert(str_contains($service, 'InstallmentFinancialStateService::statesForRows') && str_contains($service, 'effective_paid_principal') && str_contains($service, 'remaining_principal'), 'Canonical installment-state outputs must be the source for balances and due-cohort collections.');
$assert(str_contains($service, "p.status = 'paid'") && str_contains($service, 'COALESCE(p.is_corrected, 0) = 0') && str_contains($service, "COALESCE(p.payment_type, 'installment') = 'installment'") && str_contains($service, 'payment_corrections'), 'Period cash must exclude non-paid, corrected, and non-installment payments.');
$assert(str_contains($service, '$dueDate > $asOf') && str_contains($service, 'scheduled_inflow_30_days') && str_contains($service, 'historical_schedule_reconstructed\' => false'), 'Forecast must use future scheduled installments and disclose un-reconstructed schedule history.');
$assert(str_contains($service, "['all', 'high', 'medium', 'low', 'unavailable']") && str_contains($service, 'CreditRiskEngine::summariesForCustomers($ids, false)'), 'Portfolio risk must use bounded, fresh cached scores without recalculating customer profiles.');
$assert(str_contains($riskEngine, 'SUMMARY_FRESHNESS_SECONDS') && str_contains($riskEngine, 'function rulesFingerprint'), 'Risk profile freshness must use the existing score rules and a shared freshness window.');
$assert(str_contains($service, 'LIMIT {$perPage} OFFSET {$offset}') && str_contains($riskController, 'portfolioDetail'), 'Drill-down source rows must be paginated on the server.');
$assert(str_contains($riskController, 'downloadPaginatedXlsx') && str_contains($riskController, 'Source Row Count'), 'Excel output must be paginated and carry source-row count evidence.');
$assert(substr_count($riskController, "\$this->requireRole('admin')") >= 4, 'Portfolio analytics and every drill-down must enforce the admin role.');
$assert(str_contains($dashboardController, 'PortfolioRiskAnalyticsService::report()') && !str_contains($dashboardController, '($receivedMonth / $dueMonth)'), 'The admin dashboard must reuse validated portfolio KPIs instead of dividing unrelated period totals.');
$assert(str_contains($dashboardView, 'نرخ وصول Cohort ماه') && str_contains($dashboardView, 'دادهٔ واجد شرایط') && str_contains($portfolioView, 'دریافت واقعی اقساط') && str_contains($portfolioView, 'ورودی نقدی برنامه‌ریزی‌شده'), 'UI must distinguish actual collection, due-cohort rate, and scheduled forecast.');
$assert(str_contains($portfolioView, 'data-portfolio-chart') && str_contains($portfolioView, 'risk/portfolioDetail') && str_contains($portfolioView, 'period_end') && str_contains($detailView, 'page_contribution_total'), 'Charts must drill down across the full selected bucket to paginated source evidence.');
$assert(str_contains($layout, "'risk/portfolio'") && str_contains($layout, "['risk/portfolio', 'تحلیل ریسک پرتفوی'"), 'The report route must load charts and appear in the admin navigation.');

echo "STATIC_PORTFOLIO_RISK_ANALYTICS_V704_OK\n";
