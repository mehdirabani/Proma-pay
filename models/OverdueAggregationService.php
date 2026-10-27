<?php

declare(strict_types=1);

/**
 * Contract-level overdue queue.
 *
 * The previous screen paginated installment rows, which made a contract with
 * three late installments appear three times and made count-based sorting
 * impossible.  This service groups in MariaDB first, then only enriches the
 * currently visible contracts with the authoritative financial state.
 */
final class OverdueAggregationService
{
    public static function paginated(OverdueFilterCriteria $criteria, ?int $forcedOperatorId = null, bool $includeSummary = true): array
    {
        [$from, $where, $whereParams, $having, $havingParams] = self::queryParts($criteria, $forcedOperatorId);
        $select = self::aggregateSelect();
        $groupBy = self::groupBy();
        $groupSql = "SELECT {$select} {$from} WHERE {$where} GROUP BY {$groupBy}" . ($having ? ' HAVING ' . implode(' AND ', $having) : '');
        $params = array_merge($whereParams, $havingParams);

        $count = Model::fetch("SELECT COUNT(*) AS total FROM ({$groupSql}) overdue_contracts", $params);
        $total = (int) ($count['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $criteria->perPage));
        $page = min(max(1, $criteria->page), $pages);
        $offset = ($page - 1) * $criteria->perPage;
        $orderBy = self::orderBy($criteria);
        $items = Model::fetchAll("{$groupSql} ORDER BY {$orderBy} LIMIT {$criteria->perPage} OFFSET {$offset}", $params);
        $items = self::hydrateFinancialState($items, $criteria->bucket === 'today');

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $criteria->perPage,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + count($items)),
            'summary' => $includeSummary ? self::summary($groupSql, $params) : [],
        ];
    }

    private static function queryParts(OverdueFilterCriteria $criteria, ?int $forcedOperatorId): array
    {
        $from = "FROM installments i
            JOIN contracts c ON c.id = i.contract_id
            JOIN users u ON u.id = c.customer_id
            LEFT JOIN (
                SELECT contract_id, COUNT(*) AS legal_case_count, MIN(legal_referred_at) AS legal_referred_at,
                       MAX(notice_date) AS latest_notice_date,
                       MIN(COALESCE(hearing_date, court_date, notice_date)) AS next_legal_action
                FROM legal_cases
                WHERE status != 'closed'
                GROUP BY contract_id
            ) lc ON lc.contract_id = c.id
            LEFT JOIN (
                SELECT contract_id, MAX(created_at) AS last_contact_at
                FROM operator_calls GROUP BY contract_id
            ) oc ON oc.contract_id = c.id
            LEFT JOIN operator_calls active_promise ON active_promise.id = (
                SELECT candidate.id FROM operator_calls candidate
                 WHERE candidate.contract_id=c.id AND candidate.promise_payment_date IS NOT NULL
                   AND COALESCE(candidate.promise_event_type,'registered') IN ('registered','rescheduled')
                   AND NOT EXISTS (SELECT 1 FROM operator_calls event_row WHERE event_row.related_promise_call_id=candidate.id AND event_row.promise_event_type IN ('cancelled','rescheduled'))
                   AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=c.id AND newer.promise_payment_date IS NOT NULL
                        AND (newer.created_at>candidate.created_at OR (newer.created_at=candidate.created_at AND newer.id>candidate.id)))
                 ORDER BY candidate.created_at DESC,candidate.id DESC LIMIT 1
            )";

        $where = ["c.status NOT IN ('cancelled', 'closed')", "i.status NOT IN ('paid', 'cancelled')"];
        $params = [];
        if ($criteria->bucket === 'today') {
            $where[] = 'i.due_date = CURDATE()';
        } else {
            $where[] = 'i.due_date < CURDATE()';
        }
        if ($criteria->bucket === '1-7') {
            $where[] = 'DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 7';
        } elseif ($criteria->bucket === '8-30') {
            $where[] = 'DATEDIFF(CURDATE(), i.due_date) BETWEEN 8 AND 30';
        } elseif ($criteria->bucket === '30+') {
            $where[] = 'DATEDIFF(CURDATE(), i.due_date) > 30';
        }
        if ($criteria->search !== '') {
            $needle = '%' . to_english_digits($criteria->search) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ? OR u.national_id LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        if ($criteria->customerId) { $where[] = 'c.customer_id = ?'; $params[] = $criteria->customerId; }
        if ($criteria->contractId) { $where[] = 'c.id = ?'; $params[] = $criteria->contractId; }
        if ($criteria->contractStatus !== '') { $where[] = 'c.status = ?'; $params[] = $criteria->contractStatus; }
        if ($criteria->legalStatus !== '') { $where[] = 'c.legal_status = ?'; $params[] = $criteria->legalStatus; }
        $operatorId = $forcedOperatorId !== null ? $forcedOperatorId : $criteria->operatorId;
        if ($operatorId === 0) {
            $where[] = "(c.assigned_operator_id IS NULL OR NOT EXISTS (SELECT 1 FROM users owner_user WHERE owner_user.id=c.assigned_operator_id AND owner_user.role='operator' AND owner_user.status='active' AND owner_user.archived_at IS NULL))";
        } elseif ($operatorId !== null) {
            $where[] = 'c.assigned_operator_id = ?'; $params[] = $operatorId;
        }
        if ($criteria->lawyerId) {
            $where[] = 'EXISTS (SELECT 1 FROM legal_cases lc_filter WHERE lc_filter.contract_id = c.id AND lc_filter.lawyer_id = ? AND lc_filter.status != \'closed\')';
            $params[] = $criteria->lawyerId;
        }
        if ($criteria->dueDateFrom !== '') { $where[] = 'i.due_date >= ?'; $params[] = $criteria->dueDateFrom; }
        if ($criteria->dueDateTo !== '') { $where[] = 'i.due_date <= ?'; $params[] = $criteria->dueDateTo; }
        if ($criteria->legalCaseExists === 'yes') { $where[] = 'COALESCE(lc.legal_case_count, 0) > 0'; }
        if ($criteria->legalCaseExists === 'no') { $where[] = 'COALESCE(lc.legal_case_count, 0) = 0'; }
        if ($criteria->excludeLegalCases) { $where[] = 'COALESCE(lc.legal_case_count, 0) = 0'; }
        if ($criteria->warningStatus === 'sent') { $where[] = 'lc.latest_notice_date IS NOT NULL'; }
        if ($criteria->warningStatus === 'not_sent') { $where[] = '(lc.legal_case_count IS NULL OR lc.latest_notice_date IS NULL)'; }
        if ($criteria->promiseState === 'active') { $where[] = 'oc.latest_promise_date >= CURDATE()'; }
        if ($criteria->promiseState === 'expired') { $where[] = 'oc.latest_promise_date < CURDATE()'; }
        if ($criteria->promiseState === 'none') { $where[] = 'oc.latest_promise_date IS NULL'; }
        if ($criteria->noContactDays !== null) {
            $where[] = '(oc.last_contact_at IS NULL OR oc.last_contact_at < DATE_SUB(NOW(), INTERVAL ? DAY))';
            $params[] = $criteria->noContactDays;
        }

        $having = [];
        $havingParams = [];
        if ($criteria->minOverdueCount !== null) { $having[] = 'overdue_count >= ?'; $havingParams[] = $criteria->minOverdueCount; }
        if ($criteria->maxOverdueCount !== null) { $having[] = 'overdue_count <= ?'; $havingParams[] = $criteria->maxOverdueCount; }
        if ($criteria->minOverdueDays !== null) { $having[] = 'max_overdue_days >= ?'; $havingParams[] = $criteria->minOverdueDays; }
        if ($criteria->maxOverdueDays !== null) { $having[] = 'max_overdue_days <= ?'; $havingParams[] = $criteria->maxOverdueDays; }
        if ($criteria->minOverdueAmount !== null) { $having[] = 'estimated_payable >= ?'; $havingParams[] = $criteria->minOverdueAmount; }
        if ($criteria->maxOverdueAmount !== null) { $having[] = 'estimated_payable <= ?'; $havingParams[] = $criteria->maxOverdueAmount; }
        if ($criteria->legalEligible !== '') {
            $policy = LegalEligibilityService::activePolicy()['policy'] ?? LegalEligibilityService::defaults();
            $having[] = self::legalEligibilityHaving($criteria->legalEligible, $policy, $havingParams);
        }
        return [$from, implode(' AND ', $where), $params, $having, $havingParams];
    }

    private static function aggregateSelect(): string
    {
        return "c.id AS contract_id, c.customer_id, c.contract_number, c.status AS contract_status,
            c.legal_status, c.assigned_operator_id, u.full_name AS customer_name, u.mobile, u.secondary_phone,
            COUNT(i.id) AS overdue_count, MIN(i.due_date) AS oldest_due_date,
            MAX(DATEDIFF(CURDATE(), i.due_date)) AS max_overdue_days,
            SUM(GREATEST(COALESCE(i.base_amount, 0) - COALESCE(i.paid_amount, 0), 0)) AS remaining_principal,
            SUM(GREATEST(COALESCE(i.manual_penalty_adjustment, 0) - COALESCE(i.penalty_discount_amount, 0), 0)) AS estimated_penalty,
            SUM(GREATEST(COALESCE(i.base_amount, 0) - COALESCE(i.paid_amount, 0), 0)
                + GREATEST(COALESCE(i.manual_penalty_adjustment, 0) - COALESCE(i.penalty_discount_amount, 0), 0)) AS estimated_payable,
            COALESCE(lc.legal_case_count, 0) AS legal_case_count, lc.legal_referred_at, lc.latest_notice_date, lc.next_legal_action,
            oc.last_contact_at, active_promise.promise_payment_date AS latest_promise_date";
    }

    private static function groupBy(): string
    {
        return 'c.id, c.customer_id, c.contract_number, c.status, c.legal_status, c.assigned_operator_id, u.full_name, u.mobile, u.secondary_phone,
            lc.legal_case_count, lc.legal_referred_at, lc.latest_notice_date, lc.next_legal_action, oc.last_contact_at, active_promise.promise_payment_date';
    }

    private static function legalEligibilityHaving(string $value, array $policy, array &$params): string
    {
        $delay = max(1, min(3650, (int) ($policy['delay_value'] ?? 30)));
        $count = max(1, min(1000, (int) ($policy['overdue_count_threshold'] ?? 1)));
        $amountEnabled = !empty($policy['overdue_amount_enabled']);
        $amount = max(0, normalize_money($policy['overdue_amount_threshold'] ?? 0));
        $delaySql = 'max_overdue_days >= ?';
        $countSql = 'overdue_count >= ?';
        $amountSql = $amountEnabled ? 'estimated_payable >= ?' : '1=1';
        $operator = (string) ($policy['eligibility_operator'] ?? 'delay_only');
        if ($operator === 'and') {
            $eligible = "({$delaySql} AND {$countSql} AND {$amountSql})";
            $params[] = $delay;
            $params[] = $count;
            if ($amountEnabled) $params[] = $amount;
        } elseif ($operator === 'or') {
            if ($amountEnabled) {
                $eligible = "(({$delaySql} AND {$countSql}) OR {$amountSql})";
                $params[] = $delay;
                $params[] = $count;
                $params[] = $amount;
            } else {
                $eligible = "({$delaySql} OR {$countSql})";
                $params[] = $delay;
                $params[] = $count;
            }
        } else {
            $eligible = "({$delaySql} AND {$amountSql})";
            $params[] = $delay;
            if ($amountEnabled) $params[] = $amount;
        }
        return $value === 'yes' ? $eligible : "NOT {$eligible}";
    }

    private static function orderBy(OverdueFilterCriteria $criteria): string
    {
        $direction = $criteria->direction === 'desc' ? 'DESC' : 'ASC';
        switch ($criteria->sort) {
            case 'overdue_count_desc':
            case 'overdue_count_asc': return "overdue_count {$direction}, oldest_due_date ASC, contract_id ASC";
            case 'delay_desc':
            case 'delay_asc': return "max_overdue_days {$direction}, oldest_due_date ASC, contract_id ASC";
            case 'amount_desc':
            case 'amount_asc': return "estimated_payable {$direction}, overdue_count DESC, contract_id ASC";
            case 'penalty_desc':
            case 'penalty_asc': return "estimated_penalty {$direction}, estimated_payable DESC, contract_id ASC";
            case 'newest': return 'oldest_due_date DESC, contract_id DESC';
            case 'legal_action': return 'CASE WHEN next_legal_action IS NULL THEN 1 ELSE 0 END ASC, next_legal_action ASC, oldest_due_date ASC';
            case 'legal_eligible_newest': return 'CASE WHEN legal_case_count = 0 THEN 0 ELSE 1 END ASC, max_overdue_days DESC, legal_referred_at DESC';
            case 'name_asc':
            case 'name_desc': return "customer_name {$direction}, oldest_due_date ASC, contract_id ASC";
            case 'contract_asc':
            case 'contract_desc': return "contract_number {$direction}, contract_id {$direction}";
            default: return 'oldest_due_date ASC, contract_id ASC';
        }
    }

    private static function summary(string $groupSql, array $params): array
    {
        $row = Model::fetch(
            "SELECT COUNT(*) AS contracts, COALESCE(SUM(overdue_count), 0) AS installments,
                    COALESCE(SUM(estimated_payable), 0) AS estimated_payable,
                    COALESCE(MAX(max_overdue_days), 0) AS max_overdue_days
             FROM ({$groupSql}) overdue_summary",
            $params
        ) ?: [];
        return [
            'contracts' => (int) ($row['contracts'] ?? 0),
            'installments' => (int) ($row['installments'] ?? 0),
            'estimated_payable' => normalize_money($row['estimated_payable'] ?? 0),
            'max_overdue_days' => (int) ($row['max_overdue_days'] ?? 0),
        ];
    }

    private static function hydrateFinancialState(array $items, bool $includeToday = false): array
    {
        if (!$items) return [];
        $contractIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['contract_id'], $items)));
        $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
        $dueWhere = $includeToday ? 'i.due_date <= CURDATE()' : 'i.due_date < CURDATE()';
        $rows = Model::fetchAll(
            "SELECT i.*, c.customer_id, c.status AS contract_status,
                    (SELECT MIN(lc.legal_referred_at) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.legal_referred_at IS NOT NULL) AS legal_started_at
             FROM installments i JOIN contracts c ON c.id = i.contract_id
             WHERE i.contract_id IN ({$placeholders}) AND i.status NOT IN ('paid', 'cancelled') AND {$dueWhere}
             ORDER BY i.contract_id ASC, i.due_date ASC, i.installment_number ASC, i.id ASC",
            $contractIds
        );
        $states = InstallmentFinancialStateService::statesForRows($rows);
        $byContract = [];
        foreach ($rows as $row) {
            $byContract[(int) $row['contract_id']][] = $row;
        }
        $settings = Settings::allKeyed();
        $fallback = LegalEligibilityService::defaults($settings);
        $snapshotRows = Model::fetchAll("SELECT contract_id, policy_json FROM contract_legal_policy_snapshots WHERE contract_id IN ({$placeholders})", $contractIds);
        $policies = [];
        foreach ($snapshotRows as $snapshot) {
            $decoded = json_decode((string) ($snapshot['policy_json'] ?? ''), true);
            if (is_array($decoded)) $policies[(int) $snapshot['contract_id']] = array_merge($fallback, $decoded);
        }
        $activePolicy = LegalEligibilityService::activePolicy($settings)['policy'] ?? $fallback;

        foreach ($items as &$item) {
            $contractId = (int) $item['contract_id'];
            $contractRows = $byContract[$contractId] ?? [];
            $installmentQuote = PaymentAllocationService::quote($contractRows);
            $legalCostSummary = ContractLegalCostSummaryService::forContract($contractId);
            $breakdown = ContractFinancialSummaryService::buildOverdueBreakdown($installmentQuote, $legalCostSummary);
            $item['overdue_installments_total'] = $breakdown['overdue_installments_total'];
            $item['outstanding_normal_late_fees'] = $breakdown['outstanding_normal_late_fees'];
            $item['outstanding_legal_late_fees'] = $breakdown['outstanding_legal_late_fees'];
            $item['total_penalty'] = $breakdown['outstanding_late_fees'];
            $item['outstanding_legal_costs'] = $breakdown['outstanding_legal_costs'];
            $item['total_overdue_amount'] = $breakdown['total_overdue_debt'];
            $item['financial_breakdown'] = $breakdown;
            $item['calculation_warnings'] = array_values(array_filter(array_map(
                static fn (array $row): array => $states[(int) ($row['id'] ?? 0)] ?? [],
                $contractRows
            ), static fn (array $state): bool => !in_array((string) ($state['calculation_status'] ?? ''), ['calculated', 'not_applicable'], true)));
            $policy = $policies[$contractId] ?? $activePolicy;
            $eligibility = LegalEligibilityService::evaluate([
                'id' => $contractId,
                'customer_id' => (int) $item['customer_id'],
                'status' => (string) $item['contract_status'],
            ], $contractRows, $states, $policy);
            $item['legal_eligible'] = !empty($eligibility['eligible']);
            $item['legal_eligible_on'] = $eligibility['eligible_on'] ?? null;
            $item['installment_ids'] = array_values(array_map(static fn (array $row): int => (int) $row['id'], $contractRows));
        }
        unset($item);
        return $items;
    }
}
