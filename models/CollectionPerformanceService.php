<?php

/** Bounded, explainable collection metrics. Financial tables are read-only here. */
final class CollectionPerformanceService
{
    private const VERSION = 'collection-kpi-v1';
    private const FOLLOWUP_LIMIT = 5000;
    private const PAYMENT_ROW_LIMIT = 20000;
    private const OVERDUE_SNAPSHOT_LIMIT = 2000;

    public static function report(string $from, string $to, int $operatorId = 0, int $windowDays = 7, int $evidencePage = 1, string $evidenceType = 'collection'): array
    {
        $from = self::isoDate($from);
        $to = self::isoDate($to);
        $today = date('Y-m-d');
        if ($from === '' || $to === '' || $from > $to || $to > $today) throw new InvalidArgumentException('بازهٔ گزارش معتبر نیست؛ پایان بازه نمی‌تواند در آینده باشد.');
        $windowDays = max(1, min(30, $windowDays));
        $evidencePage = max(1, $evidencePage);
        $evidenceType = in_array($evidenceType, ['collection', 'promise'], true) ? $evidenceType : 'collection';
        Model::begin();
        try {
        $generatedAt = date('Y-m-d H:i:s');
        $endExclusive = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        $params = [$from . ' 00:00:00', $endExclusive];
        $operatorClause = '';
        if ($operatorId > 0) { $operatorClause = ' AND operator_id=?'; $params[] = $operatorId; }
        $followups = Model::fetch(
            "SELECT COUNT(*) AS events,COUNT(DISTINCT CASE WHEN result_code IS NOT NULL THEN contract_id END) AS contracts,
                    SUM(CASE WHEN result_code IS NULL THEN 1 ELSE 0 END) AS unclassified
               FROM operator_calls WHERE created_at>=? AND created_at<? AND action_type IS NOT NULL AND promise_event_type IS NULL{$operatorClause}",
            $params
        ) ?: [];
        $actorFollowupParams = [$from . ' 00:00:00', $endExclusive];
        $actorFollowupFilter = '';
        if ($operatorId > 0) { $actorFollowupFilter = ' AND operator_id=?'; $actorFollowupParams[] = $operatorId; }
        $actorFollowups = Model::fetchAll(
            "SELECT COALESCE(operator_id,0) AS actor_id,COUNT(*) AS calls_registered,
                    COUNT(DISTINCT CASE WHEN action_type IS NOT NULL AND result_code IS NOT NULL THEN contract_id END) AS contracts
               FROM operator_calls WHERE created_at>=? AND created_at<? AND promise_event_type IS NULL{$actorFollowupFilter}
              GROUP BY operator_id",
            $actorFollowupParams
        );

        $taskParams = [$from . ' 00:00:00', $endExclusive, $generatedAt];
        $taskOperatorClause = '';
        if ($operatorId > 0) { $taskOperatorClause = ' AND COALESCE(completed_by,assigned_to,created_by)=?'; $taskParams[] = $operatorId; }
        $taskStats = Model::fetch(
            "SELECT COUNT(*) AS denominator,
                    SUM(CASE WHEN status='completed' AND completed_at<=due_at THEN 1 ELSE 0 END) AS on_time
               FROM collection_tasks
              WHERE action_type IS NOT NULL AND status IN ('open','completed') AND due_at>=? AND due_at<? AND due_at<=?{$taskOperatorClause}",
            $taskParams
        ) ?: [];
        $actorTaskParams = [$from . ' 00:00:00', $endExclusive, $generatedAt];
        $actorTaskFilter = '';
        if ($operatorId > 0) { $actorTaskFilter = ' AND COALESCE(completed_by,assigned_to)=?'; $actorTaskParams[] = $operatorId; }
        $actorTasks = Model::fetchAll(
            "SELECT COALESCE(completed_by,assigned_to,0) AS actor_id,COUNT(*) AS denominator,
                    SUM(CASE WHEN status='completed' AND completed_at<=due_at THEN 1 ELSE 0 END) AS on_time
              FROM collection_tasks WHERE action_type IS NOT NULL AND status IN ('open','completed') AND due_at>=? AND due_at<? AND due_at<=?{$actorTaskFilter}
              GROUP BY COALESCE(completed_by,assigned_to,0)",
            $actorTaskParams
        );
        $completedTaskParams = [$from . ' 00:00:00', $endExclusive];
        $completedTaskFilter = '';
        if ($operatorId > 0) { $completedTaskFilter = ' AND completed_by=?'; $completedTaskParams[] = $operatorId; }
        $actorCompletedTasks = Model::fetchAll(
            "SELECT COALESCE(completed_by,0) AS actor_id,COUNT(*) AS completed
               FROM collection_tasks WHERE action_type IS NOT NULL AND status='completed' AND completed_at>=? AND completed_at<?{$completedTaskFilter}
              GROUP BY completed_by",
            $completedTaskParams
        );
        $overdueTaskParams = [$generatedAt];
        $overdueTaskFilter = '';
        if ($operatorId > 0) { $overdueTaskFilter = ' AND assigned_to=?'; $overdueTaskParams[] = $operatorId; }
        $actorOverdueTasks = Model::fetchAll(
            "SELECT COALESCE(assigned_to,0) AS actor_id,COUNT(*) AS overdue
               FROM collection_tasks WHERE action_type IS NOT NULL AND status='open' AND due_at<?{$overdueTaskFilter}
              GROUP BY assigned_to",
            $overdueTaskParams
        );
        $promiseActorParams = [$from . ' 00:00:00', $endExclusive];
        $promiseActorFilter = '';
        if ($operatorId > 0) { $promiseActorFilter = ' AND operator_id=?'; $promiseActorParams[] = $operatorId; }
        $actorPromisesRegistered = Model::fetchAll(
            "SELECT COALESCE(operator_id,0) AS actor_id,COUNT(*) AS registered
               FROM operator_calls WHERE created_at>=? AND created_at<? AND promise_payment_date IS NOT NULL
                 AND COALESCE(promise_event_type,'registered')='registered'{$promiseActorFilter}
              GROUP BY operator_id",
            $promiseActorParams
        );
        $contractOwnerParams = [];
        $contractOwnerFilter = '';
        if ($operatorId > 0) { $contractOwnerFilter = ' AND c.assigned_operator_id=?'; $contractOwnerParams[] = $operatorId; }
        $actorContracts = Model::fetchAll(
            "SELECT c.assigned_operator_id AS actor_id,COUNT(*) AS active_contracts
               FROM contracts c JOIN users op ON op.id=c.assigned_operator_id AND op.role='operator' AND op.status='active' AND op.archived_at IS NULL
              WHERE c.status NOT IN ('cancelled','closed'){$contractOwnerFilter}
              GROUP BY c.assigned_operator_id",
            $contractOwnerParams
        );

        $overdue = self::overdueCoverage($operatorId > 0 ? $operatorId : null, $generatedAt);
        $cohort = self::firstEligibleFollowups($from, $endExclusive, $operatorId);
        $promises = self::maturePromises($from, $endExclusive, $operatorId);
        $events = array_merge($cohort['rows'], $promises['rows']);
        [$payments, $paymentCoverage] = self::paymentsForEvents($events, $windowDays);

        $mature = 0; $pending = 0; $review = 0; $paidContracts = 0; $grossCollected = 0; $cappedCollected = 0; $baselineTotal = 0;
        $byOperator = [];
        $collectionEvidence = [];
        foreach ($cohort['rows'] as $event) {
            $id = (int) $event['contract_id'];
            $eventAt = (string) $event['created_at'];
            $deadline = date('Y-m-d H:i:s', strtotime($eventAt . ' +' . $windowDays . ' days'));
            if ($deadline > $generatedAt) {
                $pending++;
                $collectionEvidence[] = ['contract_id' => $id, 'followup_id' => (int) $event['id'], 'operator_id' => (int) ($event['operator_id'] ?? 0), 'event_at' => $eventAt, 'baseline_toman' => (int) $event['baseline_overdue_amount_toman'], 'collected_toman' => 0, 'status' => 'pending', 'ambiguous_same_day' => false, 'payments' => []];
                continue;
            }
            $match = self::paymentEvidenceAfter($event, $payments[$id] ?? [], $deadline, substr($eventAt, 0, 10), false);
            $collected = $match['amount_toman'];
            $ambiguous = $match['ambiguous'];
            $evidenceStatus = $ambiguous && $collected <= 0 ? 'needs_review' : ($collected > 0 ? 'collected' : 'no_collection');
            $collectionEvidence[] = ['contract_id' => $id, 'followup_id' => (int) $event['id'], 'operator_id' => (int) ($event['operator_id'] ?? 0), 'event_at' => $eventAt, 'baseline_toman' => (int) $event['baseline_overdue_amount_toman'], 'collected_toman' => $collected, 'status' => $evidenceStatus, 'ambiguous_same_day' => $ambiguous, 'payments' => $match['payments']];
            if ($ambiguous && $collected <= 0) { $review++; continue; }
            $mature++;
            $baseline = max(0, (int) $event['baseline_overdue_amount_toman']);
            $baselineTotal += $baseline;
            $grossCollected += $collected;
            $capped = min($collected, $baseline);
            $cappedCollected += $capped;
            if ($collected > 0) $paidContracts++;
            $actor = (int) ($event['operator_id'] ?? 0);
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['matured_cohort']++;
            if ($collected > 0) $byOperator[$actor]['collected_contracts']++;
            $byOperator[$actor]['gross_collected_toman'] += $collected;
            $byOperator[$actor]['capped_recovery_toman'] += $capped;
            $byOperator[$actor]['baseline_toman'] += $baseline;
        }

        $promiseFulfilled = 0; $promisePartial = 0; $promiseBroken = 0; $promiseReview = 0; $promiseDenominator = 0;
        $promiseEvidence = [];
        foreach ($promises['rows'] as $promise) {
            $id = (int) $promise['contract_id'];
            $promiseDate = (string) $promise['promise_payment_date'];
            $amount = (int) ($promise['promise_amount_toman'] ?? 0);
            if ($amount <= 0) {
                $promiseEvidence[] = ['contract_id' => $id, 'promise_id' => (int) $promise['id'], 'operator_id' => (int) ($promise['operator_id'] ?? 0), 'promise_date' => $promiseDate, 'promise_amount_toman' => null, 'paid_toman' => 0, 'status' => 'amountless_excluded', 'ambiguous_same_day' => false, 'payments' => []];
                continue;
            }
            $windowEnd = $promiseDate . ' 23:59:59';
            $match = self::paymentEvidenceAfter($promise, $payments[$id] ?? [], $windowEnd, $promiseDate, true);
            $paid = $match['amount_toman'];
            $ambiguous = $match['ambiguous'];
            $promiseStatus = $ambiguous && $paid <= 0 ? 'needs_review' : ($paid >= $amount ? 'fulfilled' : ($paid > 0 ? 'partially_fulfilled' : 'broken'));
            $promiseEvidence[] = ['contract_id' => $id, 'promise_id' => (int) $promise['id'], 'operator_id' => (int) ($promise['operator_id'] ?? 0), 'promise_date' => $promiseDate, 'promise_amount_toman' => $amount, 'paid_toman' => $paid, 'status' => $promiseStatus, 'ambiguous_same_day' => $ambiguous, 'payments' => $match['payments']];
            if ($ambiguous && $paid <= 0) { $promiseReview++; continue; }
            $promiseDenominator++;
            if ($paid >= $amount) $promiseFulfilled++;
            elseif ($paid > 0) $promisePartial++;
            else $promiseBroken++;
            $actor = (int) ($promise['operator_id'] ?? 0);
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['promises_matured'] = ($byOperator[$actor]['promises_matured'] ?? 0) + 1;
            if ($paid >= $amount) $byOperator[$actor]['promises_fulfilled'] = ($byOperator[$actor]['promises_fulfilled'] ?? 0) + 1;
        }

        foreach ($actorFollowups as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['followup_contracts'] = (int) $actorRow['contracts'];
            $byOperator[$actor]['calls_registered'] = (int) $actorRow['calls_registered'];
        }
        foreach ($actorTasks as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['tasks_due'] = (int) $actorRow['denominator'];
            $byOperator[$actor]['tasks_on_time'] = (int) ($actorRow['on_time'] ?? 0);
        }
        foreach ($actorCompletedTasks as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['tasks_completed'] = (int) $actorRow['completed'];
        }
        foreach ($actorOverdueTasks as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['tasks_overdue'] = (int) $actorRow['overdue'];
        }
        foreach ($actorPromisesRegistered as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['promises_registered'] = (int) $actorRow['registered'];
        }
        foreach ($actorContracts as $actorRow) {
            $actor = (int) $actorRow['actor_id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
            $byOperator[$actor]['active_contracts'] = (int) $actorRow['active_contracts'];
        }
        $activeOperatorParams = [];
        $activeOperatorFilter = '';
        if ($operatorId > 0) { $activeOperatorFilter = ' AND id=?'; $activeOperatorParams[] = $operatorId; }
        foreach (Model::fetchAll("SELECT id FROM users WHERE role='operator' AND status='active' AND archived_at IS NULL{$activeOperatorFilter}", $activeOperatorParams) as $activeOperator) {
            $actor = (int) $activeOperator['id'];
            if (!isset($byOperator[$actor])) $byOperator[$actor] = self::emptyOperatorMetrics($actor);
        }

        $actors = array_map('intval', array_keys($byOperator));
        $names = [];
        if ($actors) {
            $marks = implode(',', array_fill(0, count($actors), '?'));
            foreach (Model::fetchAll("SELECT id,full_name FROM users WHERE id IN ({$marks})", $actors) as $actorRow) $names[(int) $actorRow['id']]=(string) $actorRow['full_name'];
        }
        foreach ($byOperator as &$row) {
            $row['operator_name'] = $names[$row['operator_id']] ?? ($row['operator_id'] > 0 ? 'کاربر غیرفعال یا حذف‌شده' : 'ثبت‌کننده نامشخص');
            $row['collection_rate'] = $row['matured_cohort'] > 0 ? $row['collected_contracts'] / $row['matured_cohort'] : null;
            $row['recovery_rate'] = $row['baseline_toman'] > 0 ? $row['capped_recovery_toman'] / $row['baseline_toman'] : null;
            $row['task_on_time_rate'] = ($row['tasks_due'] ?? 0) > 0 ? $row['tasks_on_time'] / $row['tasks_due'] : null;
            $row['promise_fulfillment_rate'] = ($row['promises_matured'] ?? 0) > 0 ? ($row['promises_fulfilled'] ?? 0) / $row['promises_matured'] : null;
        }
        unset($row);
        usort($byOperator, static fn (array $a, array $b): int => strcmp((string) $a['operator_name'], (string) $b['operator_name']));

        $cohortComplete = $cohort['complete'] && $paymentCoverage['complete'];
        $reportComplete = $cohortComplete && $overdue['complete'] && $promises['complete'];
        $rate = $mature > 0 && $cohortComplete ? $paidContracts / $mature : null;
        $recoveryRate = $baselineTotal > 0 && $cohortComplete ? $cappedCollected / $baselineTotal : null;
        $promiseRate = $promiseDenominator > 0 && $paymentCoverage['complete'] && $promises['complete'] ? $promiseFulfilled / $promiseDenominator : null;

        $report = [
            'meta' => ['version' => self::VERSION, 'generated_at' => $generatedAt, 'timezone' => date_default_timezone_get(), 'from' => $from, 'to' => $to, 'window_days' => $windowDays, 'operator_id' => $operatorId, 'complete' => $reportComplete, 'payment_coverage_complete' => $paymentCoverage['complete']],
            'followups' => ['events' => (int) ($followups['events'] ?? 0), 'contracts' => (int) ($followups['contracts'] ?? 0), 'unclassified' => (int) ($followups['unclassified'] ?? 0)],
            'ownership' => $overdue,
            'tasks' => ['on_time' => (int) ($taskStats['on_time'] ?? 0), 'denominator' => (int) ($taskStats['denominator'] ?? 0), 'rate' => (int) ($taskStats['denominator'] ?? 0) > 0 ? (int) $taskStats['on_time'] / (int) $taskStats['denominator'] : null],
            'collection' => ['matured_cohort' => $mature, 'pending_window' => $pending, 'needs_review_same_day' => $review, 'collected_contracts' => $paidContracts, 'rate' => $rate, 'gross_collected_toman' => $grossCollected, 'capped_recovery_toman' => $cappedCollected, 'baseline_toman' => $baselineTotal, 'recovery_rate' => $recoveryRate, 'cohort_complete' => $cohortComplete],
            'promises' => ['matured_with_amount' => $promiseDenominator, 'fulfilled' => $promiseFulfilled, 'partially_fulfilled' => $promisePartial, 'broken' => $promiseBroken, 'needs_review' => $promiseReview, 'amountless_excluded' => $promises['amountless'], 'rate' => $promiseRate],
            'operators' => $byOperator,
            'evidence' => self::evidencePage($evidenceType === 'promise' ? $promiseEvidence : $collectionEvidence, $evidenceType, $evidencePage),
        ];
        Model::commit();
        return $report;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function overdueCoverage(?int $operatorId, string $asOf): array
    {
        $criteria = new OverdueFilterCriteria();
        $criteria->perPage = 100;
        $criteria->sort = 'oldest';
        $criteria->direction = 'asc';
        $coverage = ['eligible' => 0, 'valid_owner' => 0, 'valid_next_action' => 0, 'complete' => true, 'as_of' => $asOf];
        $first = OverdueAggregationService::paginated($criteria, $operatorId, false);
        $total = (int) ($first['total'] ?? 0);
        $limit = min($total, self::OVERDUE_SNAPSHOT_LIMIT);
        $coverage['eligible'] = $total;
        $coverage['complete'] = $total <= self::OVERDUE_SNAPSHOT_LIMIT;
        $pages = (int) ceil($limit / $criteria->perPage);
        for ($page = 1; $page <= $pages; $page++) {
            $criteria->page = $page;
            $result = $page === (int) ($first['page'] ?? 1) ? $first : OverdueAggregationService::paginated($criteria, $operatorId, false);
            $ids = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['contract_id'] ?? 0), $result['items'] ?? []))));
            if (!$ids) continue;
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = Model::fetchAll(
                "SELECT c.id,
                        (op.id IS NOT NULL) AS owner_valid,
                        EXISTS(SELECT 1 FROM collection_tasks t WHERE t.contract_id=c.id AND t.assigned_to=c.assigned_operator_id
                          AND t.status='open' AND t.action_type IS NOT NULL AND t.action_type<>'assign_case' AND t.due_at IS NOT NULL) AS next_action_valid
                   FROM contracts c LEFT JOIN users op ON op.id=c.assigned_operator_id AND op.role='operator' AND op.status='active' AND op.archived_at IS NULL
                  WHERE c.id IN ({$marks})",
                $ids
            );
            foreach ($rows as $row) {
                if (!empty($row['owner_valid'])) $coverage['valid_owner']++;
                if (!empty($row['next_action_valid'])) $coverage['valid_next_action']++;
                if (!empty($row['owner_valid']) && !empty($row['next_action_valid'])) $coverage['covered'] = ($coverage['covered'] ?? 0) + 1;
            }
        }
        $coverage['rate'] = $coverage['complete'] && $coverage['eligible'] > 0 ? (int) ($coverage['covered'] ?? 0) / $coverage['eligible'] : null;
        return $coverage;
    }

    private static function firstEligibleFollowups(string $from, string $endExclusive, int $operatorId): array
    {
        $operator = $operatorId > 0 ? ' AND oc.operator_id=?' : '';
        $earlierOperator = $operatorId > 0 ? ' AND earlier.operator_id=?' : '';
        $params = [$from . ' 00:00:00', $endExclusive];
        if ($operatorId > 0) $params[] = $operatorId;
        $params[] = $from . ' 00:00:00'; $params[] = $endExclusive;
        if ($operatorId > 0) $params[] = $operatorId;
        $rows = Model::fetchAll(
            "SELECT oc.* FROM operator_calls oc
              WHERE oc.created_at>=? AND oc.created_at<? AND oc.action_type IS NOT NULL AND oc.result_code IS NOT NULL
                AND oc.baseline_snapshot_status='calculated' AND COALESCE(oc.baseline_overdue_amount_toman,0)>0{$operator}
                AND NOT EXISTS (SELECT 1 FROM operator_calls earlier WHERE earlier.contract_id=oc.contract_id
                  AND earlier.created_at>=? AND earlier.created_at<? AND earlier.action_type IS NOT NULL AND earlier.result_code IS NOT NULL
                  AND earlier.baseline_snapshot_status='calculated' AND COALESCE(earlier.baseline_overdue_amount_toman,0)>0{$earlierOperator}
                  AND (earlier.created_at<oc.created_at OR (earlier.created_at=oc.created_at AND earlier.id<oc.id)))
              ORDER BY oc.created_at,oc.id LIMIT " . (self::FOLLOWUP_LIMIT + 1),
            $params
        );
        $complete = count($rows) <= self::FOLLOWUP_LIMIT;
        return ['rows' => array_slice($rows, 0, self::FOLLOWUP_LIMIT), 'complete' => $complete, 'unknown_baseline_excluded' => 0];
    }

    private static function maturePromises(string $from, string $endExclusive, int $operatorId): array
    {
        $operator = $operatorId > 0 ? ' AND oc.operator_id=?' : '';
        $params = [$from, substr($endExclusive, 0, 10)];
        if ($operatorId > 0) $params[] = $operatorId;
        $rows = Model::fetchAll(
            "SELECT oc.* FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id
              WHERE oc.promise_payment_date>=? AND oc.promise_payment_date<? AND oc.promise_payment_date<CURDATE()
                AND c.status NOT IN ('cancelled','closed') AND COALESCE(oc.promise_event_type,'registered') IN ('registered','rescheduled'){$operator}
                AND NOT EXISTS (SELECT 1 FROM operator_calls ev WHERE ev.related_promise_call_id=oc.id AND ev.promise_event_type IN ('cancelled','rescheduled'))
                AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=oc.contract_id AND newer.promise_payment_date IS NOT NULL
                  AND (newer.created_at>oc.created_at OR (newer.created_at=oc.created_at AND newer.id>oc.id)))
              ORDER BY oc.promise_payment_date,oc.id LIMIT " . (self::FOLLOWUP_LIMIT + 1),
            $params
        );
        $complete = count($rows) <= self::FOLLOWUP_LIMIT;
        $rows = array_slice($rows, 0, self::FOLLOWUP_LIMIT);
        $amountless = count(array_filter($rows, static fn (array $row): bool => (int) ($row['promise_amount_toman'] ?? 0) <= 0));
        return ['rows' => $rows, 'amountless' => $amountless, 'complete' => $complete];
    }

    /** Return payment allocations grouped by contract/payment, preserving due dates for snapshot eligibility. */
    private static function paymentsForEvents(array $events, int $windowDays): array
    {
        if (!$events) return [[], ['complete' => true, 'rows' => 0]];
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['contract_id'] ?? 0), $events))));
        if (!$ids) return [[], ['complete' => true, 'rows' => 0]];
        $starts = array_map(static fn (array $row): string => substr((string) ($row['created_at'] ?? ''), 0, 10), $events);
        $ends = array_map(static function (array $row) use ($windowDays): string {
            $start = (string) ($row['created_at'] ?? '');
            $windowEnd = strtotime($start . ' +' . max(1, min(30, $windowDays)) . ' days');
            $promiseEnd = !empty($row['promise_payment_date']) ? strtotime((string) $row['promise_payment_date'] . ' 23:59:59') : 0;
            return date('Y-m-d', max($windowEnd, $promiseEnd) + 86400);
        }, $events);
        $min = min($starts) . ' 00:00:00';
        $max = max($ends) . ' 00:00:00';
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $dateFilter = "((p.paid_at IS NOT NULL AND p.paid_at>=? AND p.paid_at<?) OR (p.paid_at IS NULL AND p.payment_date>=? AND p.payment_date<?))";
        $regular = "SELECT p.contract_id,p.id AS payment_id,p.paid_at,p.payment_date,i.due_date AS obligation_date,
                           SUM(GREATEST(COALESCE(a.principal_applied,0),0)+GREATEST(COALESCE(a.normal_penalty_applied,0),0)+GREATEST(COALESCE(a.legal_penalty_applied,0),0)) AS allocated_toman
                      FROM payments p JOIN payment_allocations a ON a.payment_id=p.id JOIN installments i ON i.id=a.installment_id
                     WHERE p.contract_id IN ({$marks}) AND p.status='paid' AND COALESCE(p.is_corrected,0)=0 AND COALESCE(a.is_reversal,0)=0 AND {$dateFilter}
                     GROUP BY p.contract_id,p.id,p.paid_at,p.payment_date,i.due_date";
        $legal = "SELECT p.contract_id,p.id AS payment_id,p.paid_at,p.payment_date,COALESCE(DATE(k.approved_at),k.cost_date) AS obligation_date,
                         SUM(GREATEST(COALESCE(a.allocated_amount_toman,0),0)) AS allocated_toman
                    FROM payments p JOIN legal_cost_payment_allocations a ON a.payment_id=p.id JOIN legal_case_costs k ON k.id=a.legal_case_cost_id
                   WHERE p.contract_id IN ({$marks}) AND p.status='paid' AND COALESCE(p.is_corrected,0)=0 AND COALESCE(a.is_reversal,0)=0
                     AND k.approval_status='approved' AND k.chargeable_to_customer=1 AND COALESCE(DATE(k.approved_at),k.cost_date) IS NOT NULL AND {$dateFilter}
                   GROUP BY p.contract_id,p.id,p.paid_at,p.payment_date,COALESCE(DATE(k.approved_at),k.cost_date)";
        $params = array_merge($ids, [$min, $max, substr($min, 0, 10), substr($max, 0, 10)], $ids, [$min, $max, substr($min, 0, 10), substr($max, 0, 10)]);
        $rows = Model::fetchAll('SELECT * FROM (' . $regular . ' UNION ALL ' . $legal . ') allocated_events ORDER BY contract_id,payment_id LIMIT ' . (self::PAYMENT_ROW_LIMIT + 1), $params);
        $complete = count($rows) <= self::PAYMENT_ROW_LIMIT;
        $rows = array_slice($rows, 0, self::PAYMENT_ROW_LIMIT);
        $byContract = [];
        foreach ($rows as $row) {
            $contract = (int) $row['contract_id']; $payment = (int) $row['payment_id'];
            if (!isset($byContract[$contract][$payment])) $byContract[$contract][$payment] = ['payment_id' => $payment, 'paid_at' => $row['paid_at'], 'payment_date' => $row['payment_date'], 'allocations' => []];
            $day = (string) ($row['obligation_date'] ?? '');
            $byContract[$contract][$payment]['allocations'][$day] = ($byContract[$contract][$payment]['allocations'][$day] ?? 0) + (int) normalize_money($row['allocated_toman'] ?? 0);
        }
        foreach ($byContract as &$items) $items = array_values($items);
        unset($items);
        return [$byContract, ['complete' => $complete, 'rows' => count($rows)]];
    }

    private static function paymentEvidenceAfter(array $event, array $payments, string $deadline, string $snapshotDay, bool $promise): array
    {
        $ambiguous = false; $total = 0; $evidence = [];
        $eventAt = (string) ($event['created_at'] ?? '');
        $eventDay = substr($eventAt, 0, 10);
        $deadlineDay = substr($deadline, 0, 10);
        foreach ($payments as $payment) {
            $amount = 0;
            foreach (($payment['allocations'] ?? []) as $obligationDay => $allocated) {
                if ($obligationDay === '' || $obligationDay > $snapshotDay) continue;
                $amount += max(0, (int) $allocated);
            }
            if ($amount <= 0) continue;
            if (!empty($payment['paid_at'])) {
                $paidAt = (string) $payment['paid_at'];
                if ($paidAt > $eventAt && $paidAt <= $deadline) {
                    $total += $amount;
                    $evidence[] = ['payment_id' => (int) $payment['payment_id'], 'paid_at' => $paidAt, 'payment_date' => (string) ($payment['payment_date'] ?? ''), 'allocated_toman' => $amount];
                }
                continue;
            }
            $day = (string) ($payment['payment_date'] ?? '');
            if ($day === '' || $day > $deadlineDay || $day < $eventDay) continue;
            if ($day === $eventDay && (!$promise || $day === $deadlineDay)) { $ambiguous = true; continue; }
            if ($day > $eventDay && $day <= $deadlineDay) {
                $total += $amount;
                $evidence[] = ['payment_id' => (int) $payment['payment_id'], 'paid_at' => null, 'payment_date' => $day, 'allocated_toman' => $amount];
            }
        }
        return ['amount_toman' => $total, 'payments' => $evidence, 'ambiguous' => $ambiguous];
    }

    private static function evidencePage(array $rows, string $type, int $page): array
    {
        $perPage = 50;
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $page));
        return ['type' => $type, 'items' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'total' => $total];
    }

    private static function isoDate(string $value): string
    {
        $value = trim(to_english_digits($value));
        $value = parse_jalali_date($value) ?: $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private static function emptyOperatorMetrics(int $operatorId): array
    {
        return [
            'operator_id' => $operatorId,
            'active_contracts' => 0,
            'calls_registered' => 0,
            'followup_contracts' => 0,
            'tasks_completed' => 0,
            'tasks_overdue' => 0,
            'tasks_due' => 0,
            'tasks_on_time' => 0,
            'promises_registered' => 0,
            'promises_matured' => 0,
            'promises_fulfilled' => 0,
            'matured_cohort' => 0,
            'collected_contracts' => 0,
            'gross_collected_toman' => 0,
            'capped_recovery_toman' => 0,
            'baseline_toman' => 0,
        ];
    }
}
