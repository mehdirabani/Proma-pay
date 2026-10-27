<?php

/**
 * Read-only portfolio reporting over the canonical installment state.
 * Historical schedule versions are not reconstructed: past due cohorts use
 * the schedule currently stored for each contract and disclose that coverage.
 */
final class PortfolioRiskAnalyticsService
{
    public const METRIC_VERSION = 'portfolio-risk-v1';
    private const BATCH_SIZE = 250;
    private const RISK_BATCH_SIZE = 400;
    private const MAX_RANGE_DAYS = 730;
    private static ?string $riskRulesFingerprint = null;

    /** Keep each report and its drill-down on one consistent, read-only DB snapshot. */
    public static function readOnlySnapshot(callable $callback)
    {
        $pdo = Model::db();
        if ($pdo->inTransaction()) return $callback();
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function normalizeFilters(array $input = []): array
    {
        $today = date('Y-m-d');
        $defaultFrom = (new DateTimeImmutable('first day of this month'))->modify('-5 months')->format('Y-m-d');
        $from = self::isoDate($input['from'] ?? '') ?: $defaultFrom;
        $to = self::isoDate($input['to'] ?? '') ?: $today;
        if ($to > $today) $to = $today;
        if ($from > $to) $from = $defaultFrom;
        $fromDate = new DateTimeImmutable($from);
        $toDate = new DateTimeImmutable($to);
        if ((int) $fromDate->diff($toDate)->format('%a') > self::MAX_RANGE_DAYS) {
            $from = $toDate->modify('-' . self::MAX_RANGE_DAYS . ' days')->format('Y-m-d');
        }

        $group = (string) ($input['group'] ?? 'month');
        if (!in_array($group, ['day', 'week', 'month'], true)) $group = 'month';
        $horizon = (int) ($input['horizon'] ?? 30);
        if (!in_array($horizon, [7, 30, 90, 180, 365], true)) $horizon = 30;
        $risk = (string) ($input['risk'] ?? 'all');
        if (!in_array($risk, ['all', 'high', 'medium', 'low', 'unavailable'], true)) $risk = 'all';
        $aging = (string) ($input['aging'] ?? 'all');
        if (!in_array($aging, ['all', '1-7', '8-30', '31-60', '61-90', '90+'], true)) $aging = 'all';
        $status = (string) ($input['status'] ?? 'all');
        if (!in_array($status, ['all', 'active', 'referred', 'processing', 'pending', 'completed', 'closed'], true)) $status = 'all';
        $page = max(1, min(100000, (int) ($input['page'] ?? 1)));
        $perPage = (int) ($input['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50], true)) $perPage = 25;
        $operatorId = isset($input['operator_id']) && preg_match('/\A\d+\z/', (string) $input['operator_id'])
            ? max(0, (int) $input['operator_id']) : null;

        return [
            'from' => $from, 'to' => $to, 'group' => $group, 'horizon' => $horizon,
            'risk' => $risk, 'aging' => $aging, 'status' => $status,
            'q' => mb_substr(trim(to_english_digits((string) ($input['q'] ?? ''))), 0, 100, 'UTF-8'),
            'operator_id' => $operatorId,
            'contract_id' => isset($input['contract_id']) && preg_match('/\A\d+\z/', (string) $input['contract_id'])
                ? max(0, (int) $input['contract_id']) : null,
            'customer_id' => isset($input['customer_id']) && preg_match('/\A\d+\z/', (string) $input['customer_id'])
                ? max(0, (int) $input['customer_id']) : null,
            'period' => self::isoDate($input['period'] ?? ''),
            'page' => $page, 'per_page' => $perPage,
        ];
    }

    public static function report(array $input = []): array
    {
        return self::readOnlySnapshot(static function () use ($input): array {
            return self::buildReport($input);
        });
    }

    private static function buildReport(array $input = []): array
    {
        $filters = self::normalizeFilters($input);
        $asOf = date('Y-m-d');
        $horizonEnd = (new DateTimeImmutable($asOf))->modify('+' . $filters['horizon'] . ' days')->format('Y-m-d');
        $thirtyDayEnd = (new DateTimeImmutable($asOf))->modify('+30 days')->format('Y-m-d');
        $trend = self::emptyTrend($filters['from'], $filters['to'], $filters['group']);
        $aging = self::emptyAging();
        $forecast = self::emptyForecast($asOf, $horizonEnd, $filters['horizon']);
        $monthStart = max(date('Y-m-01'), $filters['from']);
        $monthEnd = min($asOf, $filters['to']);
        $contracts = [];
        $totals = [
            'scheduled_period_amount' => 0, 'scheduled_period_count' => 0,
            'cohort_obligation' => 0, 'cohort_collected' => 0, 'cohort_incomplete' => false,
            'outstanding_principal' => 0, 'overdue_principal' => 0, 'overdue_penalties' => 0,
            'overdue_installments' => 0, 'overdue_contracts' => [], 'active_balance_contracts' => 0,
            'active_legal_contracts' => 0,
            'due_today' => 0, 'due_week' => 0, 'forecast_30' => 0,
            'current_month_due' => 0, 'current_month_cohort_obligation' => 0,
            'current_month_cohort_collected' => 0, 'current_month_cohort_incomplete' => false,
            'calculation_review_rows' => 0, 'calculated_rows' => 0,
        ];
        $settings = Settings::allKeyed();
        $cursor = 0;

        do {
            $rows = self::installmentBatch($cursor, self::BATCH_SIZE, $filters);
            if (!$rows) break;
            $cursor = (int) end($rows)['id'];
            $states = InstallmentFinancialStateService::statesForRows($rows, $settings, $asOf);
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $contractId = (int) ($row['contract_id'] ?? 0);
                $customerId = (int) ($row['customer_id'] ?? 0);
                $dueDate = (string) ($row['due_date'] ?? '');
                $base = max(0, normalize_money($row['base_amount'] ?? 0));
                $state = $states[$id] ?? null;
                $validState = $state && in_array((string) ($state['calculation_status'] ?? ''), ['calculated', 'not_applicable'], true);
                $entry = self::contractEntry($contracts, $row);
                $entry['installment_count']++;
                $entry['scheduled_principal'] += $base;

                // Weekly chart periods can straddle a month boundary. Compute
                // month KPIs from source due dates instead of chart-bucket keys.
                if ($monthStart <= $monthEnd && $dueDate >= $monthStart && $dueDate <= $monthEnd) {
                    $totals['current_month_due'] += $base;
                    $totals['current_month_cohort_obligation'] += $base;
                    if (!$validState) {
                        $totals['current_month_cohort_incomplete'] = true;
                    } else {
                        $totals['current_month_cohort_collected'] += min($base, max(0, normalize_money($state['effective_paid_principal'] ?? 0)));
                    }
                }

                if ($dueDate >= $filters['from'] && $dueDate <= $filters['to']) {
                    $key = self::periodKey($dueDate, $filters['group']);
                    if (isset($trend[$key])) {
                        $trend[$key]['due_amount'] += $base;
                        $trend[$key]['due_installments']++;
                        $trend[$key]['due_contract_ids'][$contractId] = true;
                    }
                    $totals['scheduled_period_amount'] += $base;
                    $totals['scheduled_period_count']++;
                }

                if ($dueDate <= $asOf && $dueDate >= $filters['from'] && $dueDate <= $filters['to']) {
                    $totals['cohort_obligation'] += $base;
                    $cohortKey = self::periodKey($dueDate, $filters['group']);
                    if (isset($trend[$cohortKey])) $trend[$cohortKey]['cohort_obligation'] += $base;
                    if (!$validState) {
                        $totals['cohort_incomplete'] = true;
                        if (isset($trend[$cohortKey])) $trend[$cohortKey]['cohort_incomplete'] = true;
                    } else {
                        $totals['cohort_collected'] += min($base, max(0, normalize_money($state['effective_paid_principal'] ?? 0)));
                        if (isset($trend[$cohortKey])) {
                            $trend[$cohortKey]['cohort_collected'] += min($base, max(0, normalize_money($state['effective_paid_principal'] ?? 0)));
                        }
                    }
                }

                if (!$validState) {
                    $contracts[$contractId] = $entry;
                    $totals['calculation_review_rows']++;
                    continue;
                }
                $totals['calculated_rows']++;
                $remaining = max(0, normalize_money($state['remaining_principal'] ?? 0));
                $penalties = max(0, normalize_money($state['normal_penalty'] ?? 0))
                    + max(0, normalize_money($state['legal_penalty'] ?? 0));
                $entry['outstanding_principal'] += $remaining;
                $totals['outstanding_principal'] += $remaining;

                if ($remaining > 0 && $dueDate >= $asOf && $dueDate <= (new DateTimeImmutable($asOf))->modify('+7 days')->format('Y-m-d')) {
                    $totals['due_week']++;
                    if ($dueDate === $asOf) $totals['due_today']++;
                }
                if ($remaining > 0 && $dueDate > $asOf && $dueDate <= $thirtyDayEnd) {
                    $totals['forecast_30'] += $remaining;
                }
                if ($dueDate > $asOf && $dueDate <= $horizonEnd && $remaining > 0
                    && !in_array((string) ($row['contract_status'] ?? ''), ['cancelled', 'closed', 'completed'], true)) {
                    $forecastKey = self::forecastKey($dueDate, $filters['horizon']);
                    if (isset($forecast[$forecastKey])) {
                        $forecast[$forecastKey]['amount'] += $remaining;
                        $forecast[$forecastKey]['installments']++;
                        $forecast[$forecastKey]['contract_ids'][$contractId] = true;
                    }
                }
                if ($dueDate < $asOf && ($remaining > 0 || $penalties > 0)) {
                    $daysLate = max(1, (int) (new DateTimeImmutable($dueDate))->diff(new DateTimeImmutable($asOf))->days);
                    $bucket = self::agingKey($daysLate);
                    if ($filters['aging'] !== 'all' && $filters['aging'] !== $bucket) {
                        $contracts[$contractId] = $entry;
                        continue;
                    }
                    $aging[$bucket]['installments']++;
                    $aging[$bucket]['contract_ids'][$contractId] = true;
                    $aging[$bucket]['principal'] += $remaining;
                    $aging[$bucket]['penalties'] += $penalties;
                    $entry['overdue_installments']++;
                    $entry['aging_keys'][$bucket] = true;
                    $entry['overdue_principal'] += $remaining;
                    $entry['maximum_delay_days'] = max($entry['maximum_delay_days'], $daysLate);
                    if ($entry['oldest_due_date'] === null || $dueDate < $entry['oldest_due_date']) $entry['oldest_due_date'] = $dueDate;
                    $totals['overdue_installments']++;
                    $totals['overdue_contracts'][$contractId] = true;
                    $totals['overdue_principal'] += $remaining;
                    $totals['overdue_penalties'] += $penalties;
                }
                $contracts[$contractId] = $entry;
            }
        } while (count($rows) === self::BATCH_SIZE);

        foreach ($trend as $key => &$point) {
            $point['due_contracts'] = count($point['due_contract_ids']);
            unset($point['due_contract_ids']);
            $point['cash_amount'] = 0;
            $point['cash_payment_count'] = 0;
            $point['cash_contracts'] = 0;
            $point['cohort_rate'] = empty($point['cohort_incomplete']) && $point['cohort_obligation'] > 0
                ? round($point['cohort_collected'] * 100 / $point['cohort_obligation'], 1) : null;
        }
        unset($point);
        $cash = self::periodCollections($filters);
        $periodCash = 0;
        $currentMonthCash = $monthStart <= $monthEnd ? self::collectionTotal($filters, $monthStart, $monthEnd) : 0;
        foreach ($cash as $key => $point) {
            if (isset($trend[$key])) {
                $trend[$key]['cash_amount'] = $point['amount'];
                $trend[$key]['cash_payment_count'] = $point['payments'];
                $trend[$key]['cash_contracts'] = $point['contracts'];
            }
            $periodCash += $point['amount'];
        }

        foreach ($aging as $key => &$bucket) {
            $bucket['contracts'] = count($bucket['contract_ids']);
            $bucket['total'] = $bucket['principal'] + $bucket['penalties'];
            unset($bucket['contract_ids']);
        }
        unset($bucket);
        foreach ($forecast as $key => &$bucket) {
            $bucket['contracts'] = count($bucket['contract_ids']);
            unset($bucket['contract_ids']);
        }
        unset($bucket);

        foreach ($contracts as $id => &$entry) {
            if ($entry['outstanding_principal'] > 0) {
                $totals['active_balance_contracts']++;
                if (!empty($entry['active_legal_case'])) $totals['active_legal_contracts']++;
            }
        }
        unset($entry);
        $customerIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return (int) ($row['customer_id'] ?? 0);
        }, array_values($contracts)))));
        $profiles = [];
        foreach (array_chunk($customerIds, self::RISK_BATCH_SIZE) as $ids) {
            // This path only reads recent saved scores. It never invokes profile(),
            // which recalculates and writes a customer_credit_profiles snapshot.
            $profiles += CreditRiskEngine::summariesForCustomers($ids, false);
        }
        $riskSummary = ['high' => ['contracts' => 0, 'overdue_principal' => 0], 'medium' => ['contracts' => 0, 'overdue_principal' => 0], 'low' => ['contracts' => 0, 'overdue_principal' => 0], 'unavailable' => ['contracts' => 0, 'overdue_principal' => 0]];
        $riskContracts = [];
        $customers = [];
        foreach ($contracts as $contractId => $entry) {
            if ($entry['outstanding_principal'] <= 0) continue;
            $profile = $profiles[(int) $entry['customer_id']] ?? null;
            $level = $profile && in_array(($profile['risk_level'] ?? ''), ['high', 'medium', 'low'], true)
                ? (string) $profile['risk_level'] : 'unavailable';
            $riskSummary[$level]['contracts']++;
            $riskSummary[$level]['overdue_principal'] += $entry['overdue_principal'];
            $entry['risk_level'] = $level;
            $entry['credit_score'] = $profile ? (int) ($profile['score'] ?? 0) : null;
            $entry['risk_updated_at'] = $profile['calculated_at'] ?? null;
            if ($entry['overdue_installments'] > 0) $riskContracts[] = $entry;
            if ($entry['overdue_installments'] > 0) {
                $customerId = (int) $entry['customer_id'];
                if (!isset($customers[$customerId])) {
                    $customers[$customerId] = [
                        'id' => $customerId, 'customer_id' => $customerId,
                        'full_name' => $entry['customer_name'], 'customer_name' => $entry['customer_name'],
                        'mobile' => $entry['mobile'] ?? '',
                        'overdue_count' => 0, 'debt' => 0, 'oldest_due_date' => $entry['oldest_due_date'],
                        'risk_level' => $level, 'risk_score' => $entry['credit_score'],
                    ];
                }
                $customers[$customerId]['overdue_count'] += $entry['overdue_installments'];
                $customers[$customerId]['debt'] += $entry['overdue_principal'];
                if ($entry['oldest_due_date'] < $customers[$customerId]['oldest_due_date']) $customers[$customerId]['oldest_due_date'] = $entry['oldest_due_date'];
                if ($level === 'high') { $customers[$customerId]['risk_level'] = 'high'; $customers[$customerId]['risk_score'] = $entry['credit_score']; }
            }
        }
        usort($riskContracts, static function (array $a, array $b): int {
            return ($b['overdue_principal'] <=> $a['overdue_principal'])
                ?: ($b['maximum_delay_days'] <=> $a['maximum_delay_days'])
                ?: ($a['contract_id'] <=> $b['contract_id']);
        });
        usort($customers, static function (array $a, array $b): int {
            return ($b['debt'] <=> $a['debt']) ?: ($b['overdue_count'] <=> $a['overdue_count']);
        });

        $filteredRiskContracts = array_values(array_filter($riskContracts, static function (array $row) use ($filters): bool {
            return ($filters['risk'] === 'all' || $row['risk_level'] === $filters['risk'])
                && ($filters['aging'] === 'all' || self::contractHasAging($row, $filters['aging']));
        }));
        $riskTotal = count($filteredRiskContracts);
        $riskPages = max(1, (int) ceil($riskTotal / $filters['per_page']));
        $riskPage = min($filters['page'], $riskPages);
        $riskOffset = ($riskPage - 1) * $filters['per_page'];

        $cohortRate = !$totals['cohort_incomplete'] && $totals['cohort_obligation'] > 0
            ? round($totals['cohort_collected'] * 100 / $totals['cohort_obligation'], 1) : null;
        $overdueShare = $totals['outstanding_principal'] > 0
            ? round($totals['overdue_principal'] * 100 / $totals['outstanding_principal'], 1) : null;
        return [
            'metric_version' => self::METRIC_VERSION,
            'computed_at' => date('Y-m-d H:i:s'), 'as_of' => $asOf,
            'filters' => $filters,
            'summary' => [
                'scheduled_period_amount' => $totals['scheduled_period_amount'],
                'scheduled_period_count' => $totals['scheduled_period_count'],
                'period_cash_collection' => $periodCash,
                'current_month_due' => $totals['current_month_due'],
                'current_month_cash' => $currentMonthCash,
                'current_month_cohort_obligation' => $totals['current_month_cohort_obligation'],
                'current_month_cohort_collected' => $totals['current_month_cohort_collected'],
                'current_month_cohort_rate' => !$totals['current_month_cohort_incomplete'] && $totals['current_month_cohort_obligation'] > 0
                    ? round($totals['current_month_cohort_collected'] * 100 / $totals['current_month_cohort_obligation'], 1) : null,
                'due_cohort_obligation' => $totals['cohort_obligation'],
                'due_cohort_collected' => $totals['cohort_collected'],
                'due_cohort_rate' => $cohortRate,
                'outstanding_principal' => $totals['outstanding_principal'],
                'overdue_principal' => $totals['overdue_principal'],
                'overdue_penalties' => $totals['overdue_penalties'],
                'overdue_installments' => $totals['overdue_installments'],
                'overdue_contracts' => count($totals['overdue_contracts']),
                'active_balance_contracts' => $totals['active_balance_contracts'],
                'active_legal_contracts' => $totals['active_legal_contracts'],
                'overdue_share' => $overdueShare,
                'due_today' => $totals['due_today'], 'due_week' => $totals['due_week'],
                'scheduled_inflow_30_days' => $totals['forecast_30'],
                'calculation_review_rows' => $totals['calculation_review_rows'],
                'calculated_rows' => $totals['calculated_rows'],
            ],
            'trend' => array_values($trend),
            'aging' => array_values($aging),
            'forecast' => array_values($forecast),
            'risk_summary' => $riskSummary,
            'risk_contracts' => array_slice($filteredRiskContracts, $riskOffset, $filters['per_page']),
            'risk_pagination' => ['total' => $riskTotal, 'page' => $riskPage, 'pages' => $riskPages, 'per_page' => $filters['per_page']],
            'risk_customers' => array_slice($customers, 0, 6),
            'coverage' => [
                'schedule_basis' => 'current_schedule',
                'historical_schedule_reconstructed' => false,
                'payment_events' => "payments.status='paid', is_corrected=0, payment_type='installment'; cohort numerator is canonical paid principal by installment id",
                'note' => 'بازه‌های گذشته از برنامهٔ اقساط فعلی خوانده می‌شوند؛ نسخهٔ تاریخی برنامه برای اصلاحات قدیمی بازسازی نشده است. اقساط نیازمند بررسی از نرخ Cohort حذف و به‌عنوان پوشش ناقص علامت‌گذاری می‌شوند.',
            ],
        ];
    }

    public static function details(string $metric, array $input = []): array
    {
        return self::readOnlySnapshot(static function () use ($metric, $input): array {
            return self::buildDetails($metric, $input);
        });
    }

    private static function buildDetails(string $metric, array $input = []): array
    {
        $allowed = ['balance', 'due', 'cash', 'cohort', 'aging', 'forecast'];
        if (!in_array($metric, $allowed, true)) throw new InvalidArgumentException('شاخص جزئیات معتبر نیست.');
        $filters = self::normalizeFilters($input);
        $page = $filters['page'];
        $perPage = $filters['per_page'];
        $offset = ($page - 1) * $perPage;
        if ($metric === 'cash') {
            return self::cashDetails($filters, $page, $perPage, $offset);
        }

        $params = [];
        $where = self::installmentWhere($filters, $params);
        $asOf = date('Y-m-d');
        if ($metric === 'due') {
            $where[] = 'i.due_date >= ? AND i.due_date <= ?';
            array_push($params, $filters['from'], $filters['to']);
        } elseif ($metric === 'cohort') {
            $where[] = 'i.due_date >= ? AND i.due_date <= ? AND i.due_date <= ?';
            array_push($params, $filters['from'], $filters['to'], $asOf);
        } elseif ($metric === 'forecast') {
            $horizonEnd = (new DateTimeImmutable($asOf))->modify('+' . $filters['horizon'] . ' days')->format('Y-m-d');
            $where[] = "i.due_date > ? AND i.due_date <= ? AND c.status NOT IN ('closed','completed')";
            array_push($params, $asOf, $horizonEnd);
            if (!empty($filters['period'])) {
                $group = $filters['horizon'] <= 7 ? 'day' : ($filters['horizon'] <= 90 ? 'week' : 'month');
                $periodStart = self::periodKey($filters['period'], $group);
                $periodDate = new DateTimeImmutable($periodStart);
                $periodEnd = $group === 'day' ? $periodStart : ($group === 'week'
                    ? $periodDate->modify('+6 days')->format('Y-m-d')
                    : $periodDate->modify('last day of this month')->format('Y-m-d'));
                $where[] = 'i.due_date >= ? AND i.due_date <= ?';
                array_push($params, $periodStart, $periodEnd);
            }
        } elseif ($metric === 'aging') {
            $where[] = 'i.due_date < ?';
            $params[] = $asOf;
        }
        if ($metric === 'aging' && $filters['aging'] !== 'all') {
            $bounds = self::agingBounds($filters['aging']);
            $where[] = 'DATEDIFF(?, i.due_date) BETWEEN ? AND ?';
            array_push($params, $asOf, $bounds[0], $bounds[1]);
        }
        $fromSql = self::installmentFromSql();
        $whereSql = implode(' AND ', $where);
        $count = Model::fetch("SELECT COUNT(*) AS total {$fromSql} WHERE {$whereSql}", $params);
        $total = (int) ($count['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Model::fetchAll(
            "SELECT i.id, i.contract_id, i.installment_number, i.due_date, i.base_amount, i.paid_amount,
                    i.remaining_amount, i.manual_penalty_adjustment, i.manual_reward_adjustment, i.penalty_discount_amount,
                    i.status, c.contract_number, c.status AS contract_status, c.customer_id, u.full_name AS customer_name
             {$fromSql} WHERE {$whereSql} ORDER BY i.due_date ASC, i.id ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $states = InstallmentFinancialStateService::statesForRows($rows, Settings::allKeyed(), $asOf);
        $contributionTotal = 0;
        foreach ($rows as &$row) {
            $state = $states[(int) $row['id']] ?? [];
            $row['calculation_status'] = (string) ($state['calculation_status'] ?? 'calculation_failed');
            $row['remaining_principal'] = max(0, normalize_money($state['remaining_principal'] ?? 0));
            $row['paid_principal'] = max(0, normalize_money($state['effective_paid_principal'] ?? 0));
            $row['normal_penalty'] = max(0, normalize_money($state['normal_penalty'] ?? 0));
            $row['legal_penalty'] = max(0, normalize_money($state['legal_penalty'] ?? 0));
            $row['overdue_days'] = max(0, (int) ($state['overdue_days'] ?? 0));
            $row['aging_bucket'] = self::agingKey(max(1, $row['overdue_days']));
            $row['contribution'] = 0;
            if ($metric === 'due') $row['contribution'] = max(0, normalize_money($row['base_amount'] ?? 0));
            if (in_array($row['calculation_status'], ['calculated', 'not_applicable'], true)) {
                if ($metric === 'cohort') $row['contribution'] = $row['paid_principal'];
                if ($metric === 'balance') $row['contribution'] = $row['remaining_principal'];
                if ($metric === 'forecast') $row['contribution'] = $row['remaining_principal'];
                if ($metric === 'aging') $row['contribution'] = $row['remaining_principal'] + $row['normal_penalty'] + $row['legal_penalty'];
                $contributionTotal += $row['contribution'];
            }
        }
        unset($row);
        return [
            'metric' => $metric, 'metric_version' => self::METRIC_VERSION, 'as_of' => $asOf,
            'filters' => $filters, 'rows' => $rows, 'total' => $total, 'page' => $page,
            'pages' => $pages, 'per_page' => $perPage, 'page_contribution_total' => $contributionTotal,
        ];
    }

    private static function cashDetails(array $filters, int $page, int $perPage, int $offset): array
    {
        $params = [];
        $where = ["p.status = 'paid'", 'COALESCE(p.is_corrected, 0) = 0', "COALESCE(p.payment_type, 'installment') = 'installment'", 'NOT EXISTS (SELECT 1 FROM payment_corrections pc_guard WHERE pc_guard.payment_id=p.id)', 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) >= ?', 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) <= ?'];
        array_push($params, $filters['from'], $filters['to']);
        $where = array_merge($where, self::contractWhere($filters, 'c', 'u', $params));
        $whereSql = implode(' AND ', $where);
        $fromSql = 'FROM payments p JOIN contracts c ON c.id=p.contract_id JOIN users u ON u.id=c.customer_id LEFT JOIN installments i ON i.id=p.installment_id';
        $count = Model::fetch("SELECT COUNT(*) AS total {$fromSql} WHERE {$whereSql}", $params);
        $total = (int) ($count['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Model::fetchAll(
            "SELECT p.id AS payment_id, p.contract_id, p.installment_id, p.payment_group_id,
                    p.amount AS contribution, p.amount, p.method, p.status, p.payment_type, p.is_corrected,
                    COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) AS payment_date,
                    c.contract_number, u.full_name AS customer_name, i.installment_number,
                    pc.created_at AS correction_reference
             {$fromSql} LEFT JOIN payment_corrections pc ON pc.payment_id=p.id
             WHERE {$whereSql} ORDER BY COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $sum = Model::fetch("SELECT COALESCE(SUM(p.amount),0) AS total {$fromSql} WHERE {$whereSql}", $params);
        $pageContribution = 0;
        foreach ($rows as $row) $pageContribution += normalize_money($row['contribution'] ?? 0);
        return [
            'metric' => 'cash', 'metric_version' => self::METRIC_VERSION, 'as_of' => date('Y-m-d'),
            'filters' => $filters, 'rows' => $rows, 'total' => $total, 'pages' => $pages,
            'page' => $page, 'per_page' => $perPage,
            'page_contribution_total' => $pageContribution,
            'total_contribution' => normalize_money($sum['total'] ?? 0),
        ];
    }

    private static function periodCollections(array $filters): array
    {
        $dateExpression = 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at))';
        $groupExpression = $filters['group'] === 'day'
            ? "DATE({$dateExpression})"
            : ($filters['group'] === 'week'
                ? "DATE_SUB(DATE({$dateExpression}), INTERVAL WEEKDAY({$dateExpression}) DAY)"
                : "DATE_FORMAT({$dateExpression}, '%Y-%m-01')");
        $params = [$filters['from'], $filters['to']];
        $where = ["p.status = 'paid'", 'COALESCE(p.is_corrected, 0) = 0', "COALESCE(p.payment_type, 'installment') = 'installment'", 'NOT EXISTS (SELECT 1 FROM payment_corrections pc_guard WHERE pc_guard.payment_id=p.id)', "{$dateExpression} >= ?", "{$dateExpression} <= ?"];
        $where = array_merge($where, self::contractWhere($filters, 'c', 'u', $params));
        $rows = Model::fetchAll(
            "SELECT {$groupExpression} AS period_key, COALESCE(SUM(p.amount),0) AS amount,
                    COUNT(*) AS payments, COUNT(DISTINCT p.contract_id) AS contracts
             FROM payments p JOIN contracts c ON c.id=p.contract_id JOIN users u ON u.id=c.customer_id
             WHERE " . implode(' AND ', $where) . " GROUP BY period_key ORDER BY period_key",
            $params
        );
        $data = [];
        foreach ($rows as $row) {
            $key = self::periodKey((string) ($row['period_key'] ?? ''), $filters['group']);
            $data[$key] = [
                'amount' => normalize_money($row['amount'] ?? 0),
                'payments' => (int) ($row['payments'] ?? 0),
                'contracts' => (int) ($row['contracts'] ?? 0),
            ];
        }
        return $data;
    }

    /** Sum valid installment receipts for an exact date window, independent of chart grouping. */
    private static function collectionTotal(array $filters, string $from, string $to): int
    {
        $dateExpression = 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at))';
        $params = [$from, $to];
        $where = [
            "p.status = 'paid'", 'COALESCE(p.is_corrected, 0) = 0',
            "COALESCE(p.payment_type, 'installment') = 'installment'",
            'NOT EXISTS (SELECT 1 FROM payment_corrections pc_guard WHERE pc_guard.payment_id=p.id)',
            "{$dateExpression} >= ?", "{$dateExpression} <= ?",
        ];
        $where = array_merge($where, self::contractWhere($filters, 'c', 'u', $params));
        $row = Model::fetch(
            'SELECT COALESCE(SUM(p.amount),0) AS amount
               FROM payments p JOIN contracts c ON c.id=p.contract_id JOIN users u ON u.id=c.customer_id
              WHERE ' . implode(' AND ', $where),
            $params
        );
        return normalize_money($row['amount'] ?? 0);
    }

    private static function installmentBatch(int $afterId, int $limit, array $filters): array
    {
        $params = [$afterId];
        $where = ['i.id > ?', 'i.status <> \'cancelled\''];
        $where = array_merge($where, self::installmentWhere($filters, $params));
        return Model::fetchAll(
            'SELECT i.id, i.contract_id, i.installment_number, i.due_date, i.base_amount, i.paid_amount,
                    i.remaining_amount, i.last_payment_date, i.effective_settlement_at,
                    i.manual_penalty_adjustment, i.manual_reward_adjustment, i.penalty_discount_amount, i.status,
                    c.customer_id, c.contract_number, c.status AS contract_status, c.legal_status,
                    c.assigned_operator_id, u.full_name AS customer_name, u.mobile,
                    COALESCE(lc.active_case_count,0) AS active_case_count, lc.active_case_status
             ' . self::installmentFromSql() . ' LEFT JOIN (
                    SELECT contract_id, COUNT(*) AS active_case_count,
                            SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY id DESC), CHAR(44), 1) AS active_case_status
                    FROM legal_cases WHERE status <> \'closed\' GROUP BY contract_id
             ) lc ON lc.contract_id=c.id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . (int) $limit,
            $params
        );
    }

    private static function installmentFromSql(): string
    {
        return 'FROM installments i JOIN contracts c ON c.id=i.contract_id JOIN users u ON u.id=c.customer_id';
    }

    private static function installmentWhere(array $filters, array &$params): array
    {
        $where = ["c.status <> 'cancelled'", "i.status <> 'cancelled'"];
        return array_merge($where, self::contractWhere($filters, 'c', 'u', $params));
    }

    private static function contractWhere(array $filters, string $contractAlias, string $userAlias, array &$params): array
    {
        $where = [];
        if ($filters['q'] !== '') {
            $needle = '%' . $filters['q'] . '%';
            $where[] = "({$contractAlias}.contract_number LIKE ? OR {$userAlias}.full_name LIKE ? OR {$userAlias}.mobile LIKE ? OR {$userAlias}.national_id LIKE ?)";
            array_push($params, $needle, $needle, $needle, $needle);
        }
        if (!empty($filters['contract_id'])) { $where[] = "{$contractAlias}.id = ?"; $params[] = $filters['contract_id']; }
        if (!empty($filters['customer_id'])) { $where[] = "{$contractAlias}.customer_id = ?"; $params[] = $filters['customer_id']; }
        if ($filters['operator_id'] !== null) { $where[] = "{$contractAlias}.assigned_operator_id = ?"; $params[] = $filters['operator_id']; }
        if ($filters['status'] !== 'all') { $where[] = "{$contractAlias}.status = ?"; $params[] = $filters['status']; }
        if ($filters['risk'] !== 'all') {
            $fingerprint = self::$riskRulesFingerprint ??= CreditRiskEngine::rulesFingerprint();
            $freshAfter = date('Y-m-d H:i:s', time() - CreditRiskEngine::SUMMARY_FRESHNESS_SECONDS);
            $freshProfile = "SELECT 1 FROM customer_credit_profiles rp WHERE rp.customer_id={$contractAlias}.customer_id AND rp.rules_hash = ? AND rp.calculated_at >= ?";
            if ($filters['risk'] === 'unavailable') {
                $where[] = 'NOT EXISTS (' . $freshProfile . ')';
                array_push($params, $fingerprint, $freshAfter);
            } else {
                $where[] = 'EXISTS (' . $freshProfile . ' AND rp.risk_level = ?)';
                array_push($params, $fingerprint, $freshAfter, $filters['risk']);
            }
        }
        return $where;
    }

    private static function emptyTrend(string $from, string $to, string $group): array
    {
        $start = new DateTimeImmutable(self::periodKey($from, $group));
        $end = new DateTimeImmutable(self::periodKey($to, $group));
        $points = [];
        $cursor = $start;
        while ($cursor <= $end && count($points) <= 800) {
            $key = $cursor->format('Y-m-d');
            $periodEnd = $group === 'day' ? $key : ($group === 'week'
                ? $cursor->modify('+6 days')->format('Y-m-d')
                : $cursor->modify('last day of this month')->format('Y-m-d'));
            $points[$key] = [
                'period_key' => $key, 'period_end' => $periodEnd, 'label' => self::periodLabel($cursor, $group),
                'due_amount' => 0, 'due_installments' => 0, 'due_contracts' => 0, 'due_contract_ids' => [],
                'cash_amount' => 0, 'cash_payment_count' => 0, 'cash_contracts' => 0,
                'cohort_obligation' => 0, 'cohort_collected' => 0, 'cohort_rate' => null, 'cohort_incomplete' => false,
            ];
            $cursor = $group === 'day' ? $cursor->modify('+1 day') : ($group === 'week' ? $cursor->modify('+7 days') : $cursor->modify('+1 month'));
        }
        return $points;
    }

    private static function emptyAging(): array
    {
        return [
            '1-7' => ['key' => '1-7', 'label' => '۱ تا ۷ روز', 'installments' => 0, 'contract_ids' => [], 'contracts' => 0, 'principal' => 0, 'penalties' => 0, 'total' => 0],
            '8-30' => ['key' => '8-30', 'label' => '۸ تا ۳۰ روز', 'installments' => 0, 'contract_ids' => [], 'contracts' => 0, 'principal' => 0, 'penalties' => 0, 'total' => 0],
            '31-60' => ['key' => '31-60', 'label' => '۳۱ تا ۶۰ روز', 'installments' => 0, 'contract_ids' => [], 'contracts' => 0, 'principal' => 0, 'penalties' => 0, 'total' => 0],
            '61-90' => ['key' => '61-90', 'label' => '۶۱ تا ۹۰ روز', 'installments' => 0, 'contract_ids' => [], 'contracts' => 0, 'principal' => 0, 'penalties' => 0, 'total' => 0],
            '90+' => ['key' => '90+', 'label' => 'بیش از ۹۰ روز', 'installments' => 0, 'contract_ids' => [], 'contracts' => 0, 'principal' => 0, 'penalties' => 0, 'total' => 0],
        ];
    }

    private static function emptyForecast(string $asOf, string $end, int $horizon): array
    {
        $group = $horizon <= 7 ? 'day' : ($horizon <= 90 ? 'week' : 'month');
        $start = new DateTimeImmutable(self::forecastKey($asOf, $horizon));
        $last = new DateTimeImmutable(self::forecastKey($end, $horizon));
        $points = [];
        for ($cursor = $start; $cursor <= $last && count($points) <= 60;) {
            $key = $cursor->format('Y-m-d');
            $points[$key] = ['period_key' => $key, 'label' => self::periodLabel($cursor, $group), 'amount' => 0, 'installments' => 0, 'contracts' => 0, 'contract_ids' => []];
            $cursor = $group === 'day' ? $cursor->modify('+1 day') : ($group === 'week' ? $cursor->modify('+7 days') : $cursor->modify('+1 month'));
        }
        return $points;
    }

    private static function contractEntry(array &$contracts, array $row): array
    {
        $id = (int) ($row['contract_id'] ?? 0);
        if (!isset($contracts[$id])) {
            $contracts[$id] = [
                'contract_id' => $id, 'customer_id' => (int) ($row['customer_id'] ?? 0),
                'contract_number' => (string) ($row['contract_number'] ?? ''),
                'contract_status' => (string) ($row['contract_status'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'mobile' => (string) ($row['mobile'] ?? ''),
                'legal_case_status' => (string) ($row['active_case_status'] ?? $row['legal_status'] ?? ''),
                'active_legal_case' => (int) ($row['active_case_count'] ?? 0) > 0,
                'installment_count' => 0, 'scheduled_principal' => 0, 'outstanding_principal' => 0,
                'overdue_installments' => 0, 'overdue_principal' => 0, 'maximum_delay_days' => 0,
                'oldest_due_date' => null, 'aging_keys' => [],
            ];
        }
        return $contracts[$id];
    }

    private static function periodKey(string $date, string $group): string
    {
        $parsed = self::isoDate($date);
        if (!$parsed) return $date;
        $value = new DateTimeImmutable($parsed);
        if ($group === 'week') $value = $value->modify('monday this week');
        if ($group === 'month') $value = $value->modify('first day of this month');
        return $value->format('Y-m-d');
    }

    private static function forecastKey(string $date, int $horizon): string
    {
        return self::periodKey($date, $horizon <= 7 ? 'day' : ($horizon <= 90 ? 'week' : 'month'));
    }

    private static function periodLabel(DateTimeImmutable $date, string $group): string
    {
        $label = jdate($date->format('Y-m-d'));
        return $group === 'month' ? mb_substr($label, 0, 7, 'UTF-8') : $label;
    }

    private static function agingKey(int $days): string
    {
        if ($days <= 7) return '1-7';
        if ($days <= 30) return '8-30';
        if ($days <= 60) return '31-60';
        if ($days <= 90) return '61-90';
        return '90+';
    }

    private static function agingBounds(string $key): array
    {
        return [
            '1-7' => [1, 7], '8-30' => [8, 30], '31-60' => [31, 60],
            '61-90' => [61, 90], '90+' => [91, 36500],
        ][$key] ?? [1, 36500];
    }

    private static function contractHasAging(array $contract, string $bucket): bool
    {
        if (isset($contract['aging_keys']) && is_array($contract['aging_keys'])) return !empty($contract['aging_keys'][$bucket]);
        $days = (int) ($contract['maximum_delay_days'] ?? 0);
        return self::agingKey(max(1, $days)) === $bucket;
    }

    private static function isoDate($value): ?string
    {
        $value = to_english_digits(trim((string) $value));
        if ($value === '') return null;
        $parsed = parse_jalali_date($value);
        if ($parsed && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $parsed)) $value = (string) $parsed;
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
