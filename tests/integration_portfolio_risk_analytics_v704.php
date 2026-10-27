<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (preg_match('/(?:^|;)dbname=proma_qa_portfolio_[a-f0-9]{10}(?:;|$)/i', $dsn) !== 1 || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use a fresh isolated proma_qa_portfolio_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}

putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_USER=' . ((string) getenv('PROMA_TEST_DB_USER') ?: 'root'));
putenv('PROMA_DB_PASSWORD=' . (string) getenv('PROMA_TEST_DB_PASSWORD'));
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.104';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("SET time_zone = '+03:30'");
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);

$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$uuid = static fn (): string => bin2hex(random_bytes(10));
$tag = strtoupper(substr($uuid(), 0, 12));
$today = date('Y-m-d');
$futureDue = (new DateTimeImmutable($today))->modify('+10 days')->format('Y-m-d');
$pastFive = (new DateTimeImmutable($today))->modify('-5 days')->format('Y-m-d');
$pastTwenty = (new DateTimeImmutable($today))->modify('-20 days')->format('Y-m-d');
$monthStart = date('Y-m-01');
$monthBoundaryDue = $monthStart;
$secondFutureDue = (new DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d');
$admin = 0;
$customerName = 'QA Portfolio ' . $tag;

Model::execute(
    'INSERT INTO users (role,username,full_name,password_hash,status,created_at) VALUES (?,?,?,?,?,NOW())',
    ['customer', 'qa-portfolio-' . strtolower($tag), $customerName, password_hash($uuid(), PASSWORD_DEFAULT), 'active']
);
$customerId = (int) Model::lastInsertId();
$prefix = 'P' . substr($tag, 0, 8);
Model::execute(
    'INSERT INTO contracts (customer_id,contract_number,prefix,serial,principal_amount,interest_type,months,start_date,first_due_date,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
    [$customerId, 'QA-PORTFOLIO-' . $tag, $prefix, random_int(100000, 999999999), 350000, 'simple', 4, $today, $today, 'active']
);
$contractId = (int) Model::lastInsertId();

$installment = static function (int $number, string $due, int $amount, string $status = 'pending') use ($contractId): int {
    Model::execute(
        'INSERT INTO installments (contract_id,installment_number,due_date,base_amount,paid_amount,remaining_amount,status,created_at) VALUES (?,?,?,?,0,?,?,NOW())',
        [$contractId, $number, $due, $amount, $amount, $status]
    );
    return (int) Model::lastInsertId();
};
$currentInstallment = $installment(1, $today, 100000, 'partial');
$futureInstallment = $installment(2, $futureDue, 50000, 'partial');
$overdueSeven = $installment(3, $pastFive, 60000, 'overdue');
$overdueThirty = $installment(4, $pastTwenty, 70000, 'overdue');
$cancelledFuture = $installment(5, $futureDue, 80000, 'cancelled');
$monthBoundaryInstallment = $installment(6, $monthBoundaryDue, 12000, 'pending');
$secondFutureInstallment = $installment(7, $secondFutureDue, 20000, 'partial');

$payment = static function (int $installmentId, int $amount, int $principalApplied, string $date, string $status = 'paid', string $type = 'installment', int $corrected = 0) use ($customerId, $contractId): int {
    Model::execute(
        'INSERT INTO payments (installment_id,contract_id,user_id,amount,method,status,payment_date,principal_applied,normal_penalty_applied,legal_penalty_applied,reward_applied,payment_type,is_corrected,paid_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,?,?,?,NOW())',
        [$installmentId ?: null, $contractId, $customerId, $amount, 'manual', $status, $date, $principalApplied, max(0, $amount - $principalApplied), 0, $type, $corrected, $date . ' 12:00:00']
    );
    return (int) Model::lastInsertId();
};
$payment($currentInstallment, 30000, 25000, $today);
$payment($futureInstallment, 10000, 10000, $today); // early payment stays outside today's due cohort.
$payment($currentInstallment, 77777, 77777, $today, 'corrected', 'installment', 1);
$payment($currentInstallment, 88888, 88888, $today, 'pending');
$payment($currentInstallment, 99999, 99999, $today, 'reversed');
$payment(0, 7000, 7000, $today, 'paid', 'down_payment');
$payment(0, 9000, 9000, $today, 'paid', 'legal_cost');

// A completed group has one payment row per installment allocation. The report
// must sum those source rows once and must not add payment_groups.allocated_amount.
Model::execute(
    'INSERT INTO payment_groups (group_number,contract_id,customer_id,requested_amount,allocated_amount,status,created_at,completed_at) VALUES (?,?,?,?,?,?,NOW(),NOW())',
    ['QA-PORTFOLIO-GROUP-' . $tag, $contractId, $customerId, 10000, 10000, 'completed']
);
$groupId = (int) Model::lastInsertId();
foreach ([[$futureInstallment, 5000], [$secondFutureInstallment, 5000]] as [$groupInstallmentId, $amount]) {
    $groupPaymentId = $payment($groupInstallmentId, $amount, $amount, $today);
    Model::execute('UPDATE payments SET payment_group_id=? WHERE id=?', [$groupId, $groupPaymentId]);
    Model::execute(
        'INSERT INTO payment_allocations (payment_group_id,payment_id,contract_id,installment_id,allocated_amount,principal_applied,created_at) VALUES (?,?,?,?,?,?,NOW())',
        [$groupId, $groupPaymentId, $contractId, $groupInstallmentId, $amount, $amount]
    );
}

$countFinancialRows = static function () use ($contractId): array {
    return [
        'payments' => (int) (Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$contractId])['total'] ?? 0),
        'installments' => (int) (Model::fetch('SELECT COUNT(*) AS total FROM installments WHERE contract_id=?', [$contractId])['total'] ?? 0),
        'profiles' => (int) (Model::fetch('SELECT COUNT(*) AS total FROM customer_credit_profiles WHERE customer_id=?', [(int) Model::fetch('SELECT customer_id FROM contracts WHERE id=?', [$contractId])['customer_id']])['total'] ?? 0),
    ];
};
$before = $countFinancialRows();
$report = PortfolioRiskAnalyticsService::report(['from' => $today, 'to' => $today, 'group' => 'month', 'horizon' => 30, 'contract_id' => $contractId]);
$after = $countFinancialRows();
$assert($before === $after, 'Read-only analytics must not mutate payments, installments, or customer risk snapshots.');
$summary = $report['summary'];
$todayCohortObligation = 100000 + ($today === $monthStart ? 12000 : 0);
$assert((int) $summary['scheduled_period_amount'] === $todayCohortObligation, 'T01: scheduled obligation must use valid current-day installments.');
$assert((int) $summary['period_cash_collection'] === 50000, 'T02-T08: actual installment receipts include each completed grouped allocation once and exclude corrected, reversed, pending, down-payment, and legal-cost rows.');
$assert((int) $summary['due_cohort_obligation'] === $todayCohortObligation && (int) $summary['due_cohort_collected'] === 25000, 'T03-T09: numerator and denominator must use the same due cohort and canonical principal allocations.');
$assert((float) $summary['due_cohort_rate'] === round(25000 * 100 / $todayCohortObligation, 1), 'The cohort rate must use same-cohort principal rather than same-period cash receipts.');
$assert((int) $summary['scheduled_inflow_30_days'] === 50000, 'T06/T15-T17: grouped future allocations reduce scheduled principal and cancelled installments are excluded from forecast.');
$assert((int) $summary['outstanding_principal'] === 267000, 'Outstanding installment principal must come from canonical installment states, including the new valid source rows.');
$monthBoundaryDaysLate = max(0, (int) (new DateTimeImmutable($monthBoundaryDue))->diff(new DateTimeImmutable($today))->days);
$expectedOverduePrincipal = 130000 + ($monthBoundaryDaysLate > 0 ? 12000 : 0);
$expectedOverdueInstallments = 2 + ($monthBoundaryDaysLate > 0 ? 1 : 0);
$assert((int) $summary['overdue_principal'] === $expectedOverduePrincipal && (int) $summary['overdue_installments'] === $expectedOverdueInstallments && (int) $summary['overdue_contracts'] === 1, 'T10-T11: overdue principal and unique-contract counts must not double-count a contract.');
$aging = array_column($report['aging'], null, 'key');
$monthBoundaryBucket = $monthBoundaryDaysLate <= 7 ? '1-7' : ($monthBoundaryDaysLate <= 30 ? '8-30' : ($monthBoundaryDaysLate <= 60 ? '31-60' : ($monthBoundaryDaysLate <= 90 ? '61-90' : '90+')));
$expectedOneSeven = 60000 + ($monthBoundaryBucket === '1-7' ? 12000 : 0);
$expectedEightThirty = 70000 + ($monthBoundaryBucket === '8-30' ? 12000 : 0);
$assert((int) $aging['1-7']['principal'] === $expectedOneSeven && (int) $aging['8-30']['principal'] === $expectedEightThirty, 'T10: each late installment must be placed in the correct aging bucket.');
$assert((int) $aging['1-7']['contracts'] === 1 && (int) $aging['8-30']['contracts'] === 1, 'A contract with installments in two buckets must count once in each bucket.');
$assert((int) ($report['risk_summary']['unavailable']['contracts'] ?? 0) === 1, 'T13-T14: a missing risk snapshot must be unavailable, not silently rated.');
$assert(($report['coverage']['historical_schedule_reconstructed'] ?? true) === false, 'T18: historical schedule reconstruction must be disclosed as unavailable.');

$cashDetail = PortfolioRiskAnalyticsService::details('cash', ['from' => $today, 'to' => $today, 'contract_id' => $contractId]);
$cashDetailTotal = array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $cashDetail['rows']));
$assert($cashDetailTotal === (int) $summary['period_cash_collection'] && (int) $cashDetail['page_contribution_total'] === (int) $summary['period_cash_collection'], 'T19-T20: source payment rows and page total must sum to the actual-cash KPI.');
$cohortDetail = PortfolioRiskAnalyticsService::details('cohort', ['from' => $today, 'to' => $today, 'contract_id' => $contractId]);
$cohortPrincipal = array_sum(array_map(static fn (array $row): int => (int) $row['paid_principal'], $cohortDetail['rows']));
$assert($cohortPrincipal === (int) $summary['due_cohort_collected'], 'Due-cohort drill-down principal must equal its KPI numerator.');
$forecastDetail = PortfolioRiskAnalyticsService::details('forecast', ['horizon' => 30, 'contract_id' => $contractId]);
$forecastPageTotal = (int) ($forecastDetail['page_contribution_total'] ?? 0);
$assert($forecastPageTotal === (int) $summary['scheduled_inflow_30_days'], 'Forecast detail must equal the selected forecast bucket and source amount.');
$cancelledForecastDetail = PortfolioRiskAnalyticsService::details('forecast', ['horizon' => 30, 'period' => $futureDue, 'contract_id' => $contractId]);
$assert(count($cancelledForecastDetail['rows']) === 1 && (int) $cancelledForecastDetail['rows'][0]['id'] === $futureInstallment, 'T16/T20: cancelled installments must be absent from forecast drill-down as they are from report KPIs.');
$weeklyReport = PortfolioRiskAnalyticsService::report(['from' => $monthStart, 'to' => $today, 'group' => 'week', 'horizon' => 30, 'contract_id' => $contractId]);
$expectedCurrentMonthWindow = 112000
    + (substr($pastFive, 0, 7) === substr($today, 0, 7) ? 60000 : 0)
    + (substr($pastTwenty, 0, 7) === substr($today, 0, 7) ? 70000 : 0);
$assert((int) $weeklyReport['summary']['current_month_due'] === $expectedCurrentMonthWindow && (int) $weeklyReport['summary']['current_month_cohort_obligation'] === $expectedCurrentMonthWindow, 'Current-month totals must count source due dates across a week/month boundary: ' . json_encode([$weeklyReport['summary']['current_month_due'], $weeklyReport['summary']['current_month_cohort_obligation'], $expectedCurrentMonthWindow, $pastFive, $pastTwenty, $today]));
$assert((int) $weeklyReport['summary']['current_month_cash'] === 50000, 'Current-month actual cash must be independent of chart week bucket boundaries.');
$boundaryWeekKey = (new DateTimeImmutable($monthStart))->modify('monday this week')->format('Y-m-d');
$weeklyPoints = array_column($weeklyReport['trend'], null, 'period_key');
$boundaryWeek = $weeklyPoints[$boundaryWeekKey] ?? null;
$assert(is_array($boundaryWeek) && ($boundaryWeek['period_end'] ?? '') !== '', 'Weekly chart points must expose their inclusive end date for source drill-down.');
$detailFrom = max($monthStart, $boundaryWeekKey);
$detailTo = min($today, (string) $boundaryWeek['period_end']);
$boundaryWeekDetail = PortfolioRiskAnalyticsService::details('due', ['from' => $detailFrom, 'to' => $detailTo, 'contract_id' => $contractId]);
$boundaryWeekSourceTotal = array_sum(array_map(static fn (array $row): int => (int) $row['base_amount'], $boundaryWeekDetail['rows']));
$assert($boundaryWeekSourceTotal === (int) $boundaryWeek['due_amount'], 'T19/T20: a weekly chart point must drill down to the same full-period due source total.');
$assert((int) $summary['scheduled_period_amount'] !== 0 ? ($summary['due_cohort_rate'] !== round($summary['period_cash_collection'] * 100 / $summary['scheduled_period_amount'], 1)) : true, 'Period receipts must not be presented as due-cohort receipts.');

echo "INTEGRATION_PORTFOLIO_RISK_ANALYTICS_V704_OK\n";
