<?php

/** Read-only operational queues that reuse the existing overdue and legal policies. */
final class CollectionCommandCenter
{
    private static bool $promisePaymentCoverageComplete = true;

    /**
     * Financial snapshots must be read after row locks are acquired. Under MySQL's
     * default REPEATABLE READ, an earlier lookup can freeze a stale snapshot while
     * this transaction waits behind a payment writer that locks the contract.
     */
    private static function beginWorkTransaction(): void
    {
        Model::db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        Model::begin();
    }

    public static function overview(?int $operatorId = null, ?OverdueFilterCriteria $criteria = null): array
    {
        $warnings = [];
        $criteria = $criteria ?? new OverdueFilterCriteria();
        $criteria->perPage = in_array($criteria->perPage, [25, 50, 100], true) ? $criteria->perPage : 25;
        if ($criteria->sort === 'oldest' && $criteria->direction === 'asc') {
            $criteria->sort = 'delay_desc';
            $criteria->direction = 'desc';
        }
        $worklist = self::readSafely('worklist', static fn () => OverdueAggregationService::paginated($criteria, $operatorId, false), ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $criteria->perPage], $warnings);
        $worklist['items'] = self::enrichWorklist(self::withStages((array) ($worklist['items'] ?? [])));
        $base = new OverdueFilterCriteria();
        $base->bucket = '30+'; $base->perPage = 10; $base->sort = 'delay_desc'; $base->direction = 'desc';
        $critical = self::readSafely('critical_queue', static fn () => OverdueAggregationService::paginated($base, $operatorId, false), ['items' => [], 'total' => 0], $warnings);

        self::$promisePaymentCoverageComplete = true;
        $promiseRows = self::readSafely('promises_queue', static fn () => self::promisesDueOn(date('Y-m-d'), $operatorId, 10), [], $warnings);

        $noContactCriteria = new OverdueFilterCriteria();
        $noContactCriteria->noContactDays = 7; $noContactCriteria->perPage = 10; $noContactCriteria->sort = 'oldest';
        $noContact = self::readSafely('no_contact_queue', static fn () => OverdueAggregationService::paginated($noContactCriteria, $operatorId, false), ['items' => [], 'total' => 0], $warnings);
        $legalCriteria = new OverdueFilterCriteria(); $legalCriteria->legalEligible = 'yes'; $legalCriteria->perPage = 1;
        $legalResult = $operatorId ? ['items' => [], 'total' => 0] : self::readSafely('legal_review_count', static fn () => OverdueAggregationService::paginated($legalCriteria, null, false), ['items' => [], 'total' => 0], $warnings);
        $legalQueueCriteria = clone $legalCriteria; $legalQueueCriteria->perPage = 10;
        $legalQueueResult = $operatorId ? ['items' => []] : self::readSafely('legal_review_queue', static fn () => OverdueAggregationService::paginated($legalQueueCriteria, null, false), ['items' => []], $warnings);
        $legalQueue = $legalQueueResult['items'] ?? [];
        $legalCount = (int) ($legalResult['total'] ?? 0);
        $criticalCount = self::readSafely('critical_count', static fn () => self::countOverdue(21, $operatorId), 0, $warnings);
        $promiseCount = self::readSafely('promises_count', static fn () => self::countCurrentPromisesDueOn(date('Y-m-d'), $operatorId), 0, $warnings);
        $noContactCount = self::readSafely('no_contact_count', static fn () => self::countNoContact($operatorId), 0, $warnings);
        $tasks = self::readSafely('open_tasks', static fn () => self::openTasks($operatorId), [], $warnings);
        $actionsToday = self::countTasks($operatorId, 'today');
        $actionsOverdue = self::countTasks($operatorId, 'overdue');
        $unassignedCount = $operatorId ? 0 : self::countUnassignedOverdue();
        $openTaskCount = self::countTasks($operatorId, 'open');
        $brokenPromises = self::readSafely('broken_promises', static fn () => self::countBrokenPromises($operatorId), 0, $warnings);
        return [
            'metrics' => ['my_overdue' => (int) ($worklist['total'] ?? 0), 'actions_today' => $actionsToday, 'actions_overdue' => $actionsOverdue, 'critical' => $criticalCount, 'promises_today' => $promiseCount, 'promises_broken' => $brokenPromises, 'no_contact_7d' => $noContactCount, 'legal_review' => (int) $legalCount, 'unassigned' => $unassignedCount, 'open_tasks' => $openTaskCount],
            'critical' => self::withStages($critical['items'] ?? []), 'promises' => $promiseRows, 'no_contact' => self::withStages($noContact['items'] ?? []), 'legal' => $legalQueue, 'tasks' => $tasks,
            'worklist' => $worklist,
            'operators' => $operatorId ? [] : User::all('operator', null, 'active', ['limit' => 100]),
            'assignment_manager_configured' => self::activeAssignmentManagerId() !== null,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private static function enrichWorklist(array $items): array
    {
        if (!$items) return [];
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['contract_id'] ?? 0), $items))));
        if (!$ids) return $items;
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $ownerIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['assigned_operator_id'] ?? 0), $items))));
        $owners = [];
        if ($ownerIds) {
            $ownerMarks = implode(',', array_fill(0, count($ownerIds), '?'));
            foreach (Model::fetchAll("SELECT id,full_name,role,status,archived_at FROM users WHERE id IN ({$ownerMarks})", $ownerIds) as $owner) {
                $owners[(int) $owner['id']] = $owner;
            }
        }
        $tasksByContract = [];
        foreach (Model::fetchAll("SELECT * FROM collection_tasks WHERE contract_id IN ({$marks}) AND status='open' ORDER BY due_at ASC,id ASC", $ids) as $task) {
            $contractId = (int) $task['contract_id'];
            if (!isset($tasksByContract[$contractId]) || in_array((string) ($task['action_type'] ?? ''), ['next_action', 'assign_case'], true)) {
                $tasksByContract[$contractId] = $task;
            }
        }
        $callsByContract = [];
        foreach (OperatorCall::recentForContracts($ids, 2) as $call) {
            $callsByContract[(int) $call['contract_id']][] = $call;
        }
        $promises = self::currentPromisesForContracts($ids);
        $promiseAmounts = self::paymentsForPromises($promises);
        foreach ($items as &$item) {
            $contractId = (int) $item['contract_id'];
            $ownerId = (int) ($item['assigned_operator_id'] ?? 0);
            $owner = $owners[$ownerId] ?? null;
            $item['assigned_operator_name'] = $owner['full_name'] ?? null;
            $item['owner_valid'] = $owner && ($owner['role'] ?? '') === 'operator' && ($owner['status'] ?? '') === 'active' && empty($owner['archived_at']);
            $item['needs_assignment'] = !$item['owner_valid'];
            $item['next_task'] = $tasksByContract[$contractId] ?? null;
            $item['next_action_valid'] = !empty($item['next_task']['action_type']) && !empty($item['next_task']['due_at'])
                && in_array((string) ($item['next_task']['status'] ?? ''), ['open'], true)
                && ($item['next_task']['action_type'] ?? '') !== 'assign_case';
            $item['last_followup'] = $callsByContract[$contractId][0] ?? null;
            $item['active_promise'] = $promises[$contractId] ?? null;
            if (!empty($item['active_promise'])) {
                $amount = (int) ($promiseAmounts[(int) $item['active_promise']['id']] ?? 0);
                $item['active_promise']['eligible_paid_toman'] = $amount;
                $item['active_promise']['derived_status'] = self::derivePromiseStatus($item['active_promise'], $amount, date('Y-m-d'));
            }
            $item['priority'] = $item['next_task']['priority'] ?? self::priorityForStage((string) ($item['collection_stage'] ?? ''));
        }
        unset($item);
        return $items;
    }

    private static function currentPromisesForContracts(array $contractIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $contractIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = Model::fetchAll(
            "SELECT oc.*,c.contract_number,u.full_name AS customer_name
               FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id JOIN users u ON u.id=oc.customer_id
              WHERE oc.contract_id IN ({$marks}) AND oc.promise_payment_date IS NOT NULL
                AND COALESCE(oc.promise_event_type,'registered') IN ('registered','rescheduled')
                AND NOT EXISTS (SELECT 1 FROM operator_calls ev WHERE ev.related_promise_call_id=oc.id AND ev.promise_event_type IN ('cancelled','rescheduled'))
                AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=oc.contract_id AND newer.promise_payment_date IS NOT NULL
                      AND (newer.created_at>oc.created_at OR (newer.created_at=oc.created_at AND newer.id>oc.id)))
              ORDER BY oc.created_at DESC,oc.id DESC",
            $ids
        );
        $current = [];
        foreach ($rows as $row) {
            $contractId = (int) $row['contract_id'];
            if (!isset($current[$contractId])) $current[$contractId] = $row;
        }
        return $current;
    }

    private static function paymentsForPromises(array $promises): array
    {
        if (!$promises) return [];
        $contractIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['contract_id'], $promises)));
        $marks = implode(',', array_fill(0, count($contractIds), '?'));
        $paidByPromise = [];
        $regular = Model::fetchAll(
            "SELECT p.id AS payment_id,p.contract_id,p.paid_at,p.payment_date,p.created_at,
                    i.due_date,a.principal_applied,a.normal_penalty_applied,a.legal_penalty_applied
               FROM payments p JOIN payment_allocations a ON a.payment_id=p.id
               JOIN installments i ON i.id=a.installment_id
              WHERE p.contract_id IN ({$marks}) AND p.status='paid' AND COALESCE(p.is_corrected,0)=0 AND COALESCE(a.is_reversal,0)=0 LIMIT 5001",
            $contractIds
        );
        $legal = Model::fetchAll(
            "SELECT p.id AS payment_id,p.contract_id,p.paid_at,p.payment_date,p.created_at,k.approved_at,k.cost_date,a.allocated_amount_toman
               FROM payments p JOIN legal_cost_payment_allocations a ON a.payment_id=p.id
               JOIN legal_case_costs k ON k.id=a.legal_case_cost_id
              WHERE p.contract_id IN ({$marks}) AND p.status='paid' AND COALESCE(p.is_corrected,0)=0 AND COALESCE(a.is_reversal,0)=0
                AND k.approval_status='approved' AND k.chargeable_to_customer=1 LIMIT 5001",
            $contractIds
        );
        if (count($regular) >= 5001 || count($legal) >= 5001) self::$promisePaymentCoverageComplete = false;
        foreach ($promises as $promise) {
            $promiseId = (int) $promise['id'];
            $startAt = (string) $promise['created_at'];
            $startDay = substr($startAt, 0, 10);
            $endDay = (string) $promise['promise_payment_date'];
            foreach ($regular as $row) {
                if ((int) $row['contract_id'] !== (int) $promise['contract_id'] || (string) $row['due_date'] > $endDay) continue;
                if (!self::paymentFallsInPromiseWindow($row, $startAt, $startDay, $endDay)) continue;
                $paidByPromise[$promiseId] = ($paidByPromise[$promiseId] ?? 0)
                    + normalize_money($row['principal_applied'] ?? 0) + normalize_money($row['normal_penalty_applied'] ?? 0) + normalize_money($row['legal_penalty_applied'] ?? 0);
            }
            foreach ($legal as $row) {
                if ((int) $row['contract_id'] !== (int) $promise['contract_id']) continue;
                $costDay = substr((string) ($row['approved_at'] ?: $row['cost_date'] ?: ''), 0, 10);
                if ($costDay !== '' && $costDay > $endDay) continue;
                if (!self::paymentFallsInPromiseWindow($row, $startAt, $startDay, $endDay)) continue;
                $paidByPromise[$promiseId] = ($paidByPromise[$promiseId] ?? 0) + normalize_money($row['allocated_amount_toman'] ?? 0);
            }
        }
        return $paidByPromise;
    }

    private static function paymentFallsInPromiseWindow(array $payment, string $startAt, string $startDay, string $endDay): bool
    {
        if (!empty($payment['paid_at'])) {
            return (string) $payment['paid_at'] > $startAt && substr((string) $payment['paid_at'], 0, 10) <= $endDay;
        }
        $day = (string) ($payment['payment_date'] ?? '');
        if ($day === '' || $day <= $startDay || $day > $endDay) return false;
        return true;
    }

    private static function derivePromiseStatus(array $promise, int $paidAmount, string $asOf): string
    {
        $due = (string) ($promise['promise_payment_date'] ?? '');
        $amount = (int) ($promise['promise_amount_toman'] ?? 0);
        if ($due === '' || !self::$promisePaymentCoverageComplete) return 'needs_review';
        if ($due > $asOf) return 'active';
        if ($amount <= 0) return 'needs_review';
        if ($paidAmount >= $amount) return 'fulfilled';
        return $paidAmount > 0 ? 'partially_fulfilled' : 'broken';
    }

    private static function countBrokenPromises(?int $operatorId): int
    {
        self::$promisePaymentCoverageComplete = true;
        $rows = Model::fetchAll(
            "SELECT oc.*,c.contract_number,u.full_name AS customer_name
               FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id JOIN users u ON u.id=c.customer_id
              WHERE oc.promise_payment_date<CURDATE() AND c.status NOT IN ('cancelled','closed')
                AND COALESCE(oc.promise_event_type,'registered') IN ('registered','rescheduled')
                AND (? IS NULL OR c.assigned_operator_id=?)
                AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=oc.contract_id AND newer.promise_payment_date IS NOT NULL
                     AND (newer.created_at>oc.created_at OR (newer.created_at=oc.created_at AND newer.id>oc.id)))
                AND NOT EXISTS (SELECT 1 FROM operator_calls ev WHERE ev.related_promise_call_id=oc.id AND ev.promise_event_type IN ('cancelled','rescheduled'))
              ORDER BY oc.promise_payment_date,oc.id LIMIT 5001",
            [$operatorId, $operatorId]
        );
        if (count($rows) > 5000) { self::$promisePaymentCoverageComplete = false; $rows = array_slice($rows, 0, 5000); }
        $amounts = self::paymentsForPromises($rows);
        $broken = 0;
        foreach ($rows as $promise) {
            if (self::derivePromiseStatus($promise, (int) ($amounts[(int) $promise['id']] ?? 0), date('Y-m-d')) === 'broken') $broken++;
        }
        return $broken;
    }

    private static function promisesDueOn(string $date, ?int $operatorId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $rows = Model::fetchAll(
            "SELECT oc.*,c.contract_number,u.full_name AS customer_name,u.mobile
               FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id JOIN users u ON u.id=oc.customer_id
              WHERE oc.promise_payment_date=? AND c.status NOT IN ('cancelled','closed')
                AND COALESCE(oc.promise_event_type,'registered') IN ('registered','rescheduled')
                AND (? IS NULL OR c.assigned_operator_id=?)
                AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=oc.contract_id AND newer.promise_payment_date IS NOT NULL
                     AND (newer.created_at>oc.created_at OR (newer.created_at=oc.created_at AND newer.id>oc.id)))
                AND NOT EXISTS (SELECT 1 FROM operator_calls ev WHERE ev.related_promise_call_id=oc.id AND ev.promise_event_type IN ('cancelled','rescheduled'))
              ORDER BY oc.created_at DESC,oc.id DESC LIMIT {$limit}",
            [$date, $operatorId, $operatorId]
        );
        $amounts = self::paymentsForPromises($rows);
        foreach ($rows as &$row) {
            $row['eligible_paid_toman'] = (int) ($amounts[(int) $row['id']] ?? 0);
            $row['derived_status'] = self::derivePromiseStatus($row, $row['eligible_paid_toman'], $date);
        }
        unset($row);
        return $rows;
    }

    private static function countCurrentPromisesDueOn(string $date, ?int $operatorId): int
    {
        $row = Model::fetch(
            "SELECT COUNT(*) AS total FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id
              WHERE oc.promise_payment_date=? AND c.status NOT IN ('cancelled','closed')
                AND COALESCE(oc.promise_event_type,'registered') IN ('registered','rescheduled')
                AND (? IS NULL OR c.assigned_operator_id=?)
                AND NOT EXISTS (SELECT 1 FROM operator_calls newer WHERE newer.contract_id=oc.contract_id AND newer.promise_payment_date IS NOT NULL
                     AND (newer.created_at>oc.created_at OR (newer.created_at=oc.created_at AND newer.id>oc.id)))
                AND NOT EXISTS (SELECT 1 FROM operator_calls ev WHERE ev.related_promise_call_id=oc.id AND ev.promise_event_type IN ('cancelled','rescheduled'))",
            [$date, $operatorId, $operatorId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private static function countTasks(?int $operatorId, string $bucket): int
    {
        $where = "status='open' AND action_type IS NOT NULL";
        $params = [];
        if ($bucket === 'today') $where .= ' AND DATE(due_at)=CURDATE()';
        elseif ($bucket === 'overdue') $where .= ' AND due_at<NOW()';
        elseif ($bucket === 'open') { /* count every open actionable item */ }
        else throw new InvalidArgumentException('Unknown collection task count.');
        if ($operatorId !== null) { $where .= ' AND assigned_to=?'; $params[] = $operatorId; }
        $row = Model::fetch("SELECT COUNT(*) AS total FROM collection_tasks WHERE {$where}", $params);
        return (int) ($row['total'] ?? 0);
    }

    private static function countUnassignedOverdue(): int
    {
        $row = Model::fetch(
            "SELECT COUNT(DISTINCT c.id) AS total FROM contracts c JOIN installments i ON i.contract_id=c.id
              LEFT JOIN users op ON op.id=c.assigned_operator_id AND op.role='operator' AND op.status='active' AND op.archived_at IS NULL
              WHERE c.status NOT IN ('cancelled','closed') AND i.status NOT IN ('paid','cancelled') AND i.due_date<CURDATE()
                AND COALESCE(i.base_amount,0)>COALESCE(i.paid_amount,0) AND op.id IS NULL"
        );
        return (int) ($row['total'] ?? 0);
    }

    private static function priorityForStage(string $stage): string
    {
        return ['legal_review' => 'urgent', 'serious_warning' => 'high', 'operator_task' => 'normal', 'reminder' => 'low'][$stage] ?? 'normal';
    }

    public static function recordFollowup(array $input, int $actorId, string $actorRole): int
    {
        $contractId = (int) ($input['contract_id'] ?? 0);
        $installmentId = (int) ($input['installment_id'] ?? 0);
        $actionType = trim((string) ($input['action_type'] ?? 'customer_call'));
        $contactTarget = trim((string) ($input['contact_target'] ?? 'customer'));
        $resultCode = trim((string) ($input['result_code'] ?? ''));
        $nextAction = trim((string) ($input['next_action_type'] ?? ''));
        $promiseDate = self::isoDate($input['promise_payment_date'] ?? '');
        $nextDate = self::isoDate($input['next_followup_date'] ?? '');
        $promiseAmount = self::optionalToman($input['promise_amount_toman'] ?? null);
        $notes = trim((string) ($input['notes'] ?? ''));
        $legacyResult = trim((string) ($input['call_result'] ?? ''));

        $actionTypes = ['customer_call', 'guarantor_call', 'reminder', 'payment_review', 'legal_review', 'other_authorized'];
        $targets = ['customer', 'guarantor', 'other_authorized'];
        $results = ['contacted', 'no_answer', 'unreachable', 'promise_given', 'requested_extension', 'disputed', 'needs_followup', 'referred_to_legal', 'other'];
        $nextActions = ['call_customer', 'call_guarantor', 'check_promise', 'send_reminder', 'review_payment', 'review_legal_referral', 'legal_followup', 'manager_review'];
        if ($contractId <= 0 || !in_array($actionType, $actionTypes, true) || !in_array($contactTarget, $targets, true) || ($resultCode !== '' && !in_array($resultCode, $results, true))) {
            throw new InvalidArgumentException('نوع اقدام، مخاطب یا نتیجه پیگیری معتبر نیست.');
        }
        if ((function_exists('mb_strlen') ? mb_strlen($notes, 'UTF-8') : strlen($notes)) > 2000) throw new InvalidArgumentException('یادداشت پیگیری بیش از حد مجاز است.');
        if ((function_exists('mb_strlen') ? mb_strlen($legacyResult, 'UTF-8') : strlen($legacyResult)) > 190) throw new InvalidArgumentException('متن نتیجهٔ پیگیری بیش از حد مجاز است.');
        if ($resultCode === 'promise_given' && $promiseDate === '') throw new InvalidArgumentException('برای قول پرداخت، تاریخ وعده الزامی است.');
        if ($promiseDate !== '' && $nextDate === '') { $nextDate = $promiseDate; $nextAction = 'check_promise'; }
        if ($nextDate !== '' && (!in_array($nextAction, $nextActions, true) || $nextDate < date('Y-m-d'))) {
            throw new InvalidArgumentException('اقدام بعدی یا موعد آن معتبر نیست.');
        }

        self::beginWorkTransaction();
        try {
            self::assertActor($actorId, $actorRole);
            $contract = Model::fetch('SELECT id,customer_id,assigned_operator_id,status FROM contracts WHERE id=? FOR UPDATE', [$contractId]);
            if (!$contract || in_array((string) $contract['status'], ['cancelled', 'closed'], true)) throw new InvalidArgumentException('قرارداد فعال پیدا نشد.', 404);
            self::assertContractAccess($contract, $actorId, $actorRole);
            if ($installmentId > 0 && !Model::fetch('SELECT id FROM installments WHERE id=? AND contract_id=? LIMIT 1', [$installmentId, $contractId])) {
                throw new InvalidArgumentException('قسط انتخاب‌شده متعلق به این قرارداد نیست.', 404);
            }

            $snapshot = FinancialDebtEngine::overdueBreakdown($contractId, date('Y-m-d'));
            $snapshotStatus = (string) ($snapshot['calculation_status'] ?? 'needs_review');
            $baseline = $snapshotStatus === 'calculated' ? max(0, (int) ($snapshot['total_overdue_debt'] ?? 0)) : null;
            $hasDebt = $snapshotStatus !== 'calculated' || $baseline > 0;
            if ($hasDebt && ($nextDate === '' || !in_array($nextAction, $nextActions, true))) {
                throw new InvalidArgumentException('برای پروندهٔ دارای بدهی، اقدام بعدی و موعد آن الزامی است.');
            }
            if ($snapshotStatus !== 'calculated' && $nextAction !== 'review_payment') {
                throw new InvalidArgumentException('محاسبهٔ بدهی نیازمند بررسی است؛ اقدام بعدی باید بررسی پرداخت باشد.');
            }

            $currentPromise = $promiseDate !== '' ? (self::currentPromisesForContracts([$contractId])[$contractId] ?? null) : null;
            $promiseEvent = $promiseDate === '' ? null : ($currentPromise ? 'rescheduled' : 'registered');
            $relatedPromiseId = $currentPromise ? (int) $currentPromise['id'] : null;
            $labels = ['contacted' => 'تماس برقرار شد', 'no_answer' => 'پاسخ نداد', 'unreachable' => 'در دسترس نبود', 'promise_given' => 'قول پرداخت ثبت شد', 'requested_extension' => 'درخواست مهلت داد', 'disputed' => 'اعتراض یا اختلاف', 'needs_followup' => 'نیازمند پیگیری مجدد', 'referred_to_legal' => 'پیشنهاد بررسی حقوقی', 'other' => 'سایر'];
            $callId = OperatorCall::insertStructured([
                'operator_id' => $actorId, 'customer_id' => (int) $contract['customer_id'], 'contract_id' => $contractId,
                'installment_id' => $installmentId ?: null, 'call_result' => $labels[$resultCode] ?? ($legacyResult ?: 'پیگیری بدون طبقه‌بندی'), 'notes' => $notes,
                'next_followup_date' => $nextDate ?: null, 'promise_payment_date' => $promiseDate ?: null,
                'action_type' => $actionType, 'contact_target' => $contactTarget, 'result_code' => $resultCode !== '' ? $resultCode : null,
                'promise_amount_toman' => $promiseAmount, 'promise_event_type' => $promiseEvent,
                'related_promise_call_id' => $relatedPromiseId, 'next_action_type' => $nextAction ?: null,
                'baseline_overdue_amount_toman' => $baseline, 'baseline_snapshot_status' => $snapshotStatus,
            ]);

            $taskId = (int) ($input['task_id'] ?? 0);
            if ($taskId > 0) {
                self::completeTaskForFollowup($taskId, $contractId, $actorId, $actorRole, $callId);
            } else {
                self::completeCurrentNextAction($contractId, $actorId, $actorRole, $callId);
            }
            if ($hasDebt && $nextDate !== '') {
                $owner = self::activeOperator((int) ($contract['assigned_operator_id'] ?? 0));
                $stage = self::stageForDelay(self::maxOverdueDays($contractId));
                self::scheduleNextAction($contractId, (int) $contract['customer_id'], $owner ? (int) $owner['id'] : null, $actorId,
                    $owner ? $nextAction : 'assign_case', $nextDate, $owner ? self::priorityForStage($stage) : 'urgent', $stage, $callId, $owner ? 'next_action' : 'assign_case');
            }
            if (class_exists('AuditLog')) AuditLog::record('collection', 'followup_recorded', 'operator_call', $callId, [
                'actor_user_id' => $actorId, 'customer_id' => (int) $contract['customer_id'], 'contract_id' => $contractId,
                'new_values' => ['result_code' => $resultCode !== '' ? $resultCode : null, 'action_type' => $actionType, 'contact_target' => $contactTarget,
                    'promise_call_id' => $promiseEvent ? $callId : null, 'next_action_type' => $nextAction ?: null,
                    'baseline_overdue_amount_toman' => $baseline, 'baseline_snapshot_status' => $snapshotStatus],
            ]);
            Model::commit();
            return $callId;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function assignContract(int $contractId, int $operatorId, int $actorId): void
    {
        self::beginWorkTransaction();
        try {
            self::assertActor($actorId, 'admin');
            $contract = Model::fetch('SELECT id,customer_id,assigned_operator_id,status FROM contracts WHERE id=? FOR UPDATE', [$contractId]);
            if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.', 404);
            $operator = self::activeOperator($operatorId);
            if (!$operator) throw new InvalidArgumentException('اپراتور انتخاب‌شده فعال نیست.');
            $oldOperatorId = (int) ($contract['assigned_operator_id'] ?? 0);
            if ($oldOperatorId === $operatorId) { Model::commit(); return; }
            Model::execute('UPDATE contracts SET assigned_operator_id=? WHERE id=?', [$operatorId, $contractId]);
            Model::execute("UPDATE collection_tasks SET assigned_to=? WHERE contract_id=? AND status='open' AND COALESCE(action_type,'')<>'assign_case'", [$operatorId, $contractId]);
            $assignmentTask = Model::fetch("SELECT * FROM collection_tasks WHERE contract_id=? AND status='open' AND action_type='assign_case' ORDER BY id LIMIT 1 FOR UPDATE", [$contractId]);
            if ($assignmentTask) self::closeTask((int) $assignmentTask['id'], $actorId, 'case_assigned', 'پرونده به اپراتور فعال واگذار شد.');
            $snapshot = FinancialDebtEngine::overdueBreakdown($contractId, date('Y-m-d'));
            if (($snapshot['calculation_status'] ?? '') === 'calculated' && (int) ($snapshot['total_overdue_debt'] ?? 0) > 0) {
                $stage = self::stageForDelay(self::maxOverdueDays($contractId));
                self::scheduleNextAction($contractId, (int) $contract['customer_id'], $operatorId, $actorId,
                    $stage === 'legal_review' ? 'review_legal_referral' : 'call_customer', date('Y-m-d'), self::priorityForStage($stage), $stage, null, 'next_action');
            }
            if (class_exists('AuditLog')) AuditLog::record('collection_assignment', 'owner_changed', 'contract', $contractId, [
                'actor_user_id' => $actorId, 'customer_id' => (int) $contract['customer_id'], 'contract_id' => $contractId,
                'old_values' => ['assigned_operator_id' => $oldOperatorId ?: null],
                'new_values' => ['assigned_operator_id' => $operatorId],
            ]);
            Notification::create($operatorId, 'پرونده وصول به شما واگذار شد', 'یک قرارداد معوق برای پیگیری به شما واگذار شده است.', 'overdue', url('collections/timeline/' . $contractId), 'collection:owner:' . $contractId . ':' . $operatorId . ':' . date('YmdHis'));
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Append a promise lifecycle event; the original promise row is never edited. */
    public static function updatePromise(int $promiseCallId, int $actorId, string $actorRole, string $action, string $promiseDate, $amount, string $reason): void
    {
        $action = trim($action);
        $promiseDate = self::isoDate($promiseDate);
        $reason = trim($reason);
        if (!in_array($action, ['reschedule', 'cancel'], true)) throw new InvalidArgumentException('نوع تغییر قول پرداخت معتبر نیست.');
        if ($action === 'reschedule' && $promiseDate === '') throw new InvalidArgumentException('تاریخ جدید قول پرداخت الزامی است.');
        if ($reason === '' || (function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason)) > 500) throw new InvalidArgumentException('دلیل تغییر قول پرداخت الزامی است و باید کمتر از ۵۰۰ نویسه باشد.');
        $newAmount = self::optionalToman($amount);

        self::beginWorkTransaction();
        try {
            self::assertActor($actorId, $actorRole);
            $original = Model::fetch('SELECT * FROM operator_calls WHERE id=? AND promise_payment_date IS NOT NULL FOR UPDATE', [$promiseCallId]);
            if (!$original) throw new InvalidArgumentException('قول پرداخت پیدا نشد.', 404);
            $contract = Model::fetch('SELECT id,customer_id,assigned_operator_id,status FROM contracts WHERE id=? FOR UPDATE', [(int) $original['contract_id']]);
            if (!$contract || in_array((string) $contract['status'], ['cancelled', 'closed'], true)) throw new InvalidArgumentException('قرارداد فعال پیدا نشد.', 404);
            self::assertContractAccess($contract, $actorId, $actorRole);
            $active = self::currentPromisesForContracts([(int) $contract['id']])[(int) $contract['id']] ?? null;
            if (!$active || (int) $active['id'] !== $promiseCallId) throw new InvalidArgumentException('این قول دیگر فعال نیست؛ تاریخچه را بررسی کنید.');

            $eventType = $action === 'cancel' ? 'cancelled' : 'rescheduled';
            $nextDate = $action === 'reschedule' ? $promiseDate : null;
            $nextAmount = $action === 'reschedule' ? ($newAmount ?? (isset($original['promise_amount_toman']) ? (int) $original['promise_amount_toman'] : null)) : null;
            $eventId = OperatorCall::insertStructured([
                'operator_id' => $actorId, 'customer_id' => (int) $contract['customer_id'], 'contract_id' => (int) $contract['id'],
                'installment_id' => null, 'call_result' => $action === 'cancel' ? 'قول پرداخت لغو شد' : 'موعد قول پرداخت تغییر کرد',
                'notes' => $reason, 'next_followup_date' => $nextDate, 'promise_payment_date' => $nextDate,
                'action_type' => 'payment_review', 'contact_target' => 'customer', 'result_code' => 'needs_followup',
                'promise_amount_toman' => $nextAmount, 'promise_event_type' => $eventType,
                'related_promise_call_id' => $promiseCallId,
                'next_action_type' => $action === 'reschedule' ? 'check_promise' : null,
                'baseline_overdue_amount_toman' => null, 'baseline_snapshot_status' => null,
            ]);
            $snapshot = FinancialDebtEngine::overdueBreakdown((int) $contract['id'], date('Y-m-d'));
            $stillOwed = ($snapshot['calculation_status'] ?? '') !== 'calculated' || (int) ($snapshot['total_overdue_debt'] ?? 0) > 0;
            if ($stillOwed) {
                $owner = self::activeOperator((int) ($contract['assigned_operator_id'] ?? 0));
                $stage = self::stageForDelay(self::maxOverdueDays((int) $contract['id']));
                $nextAction = $action === 'reschedule' ? 'check_promise' : 'call_customer';
                $nextDue = $action === 'reschedule' ? $promiseDate : date('Y-m-d');
                self::scheduleNextAction((int) $contract['id'], (int) $contract['customer_id'], $owner ? (int) $owner['id'] : null, $actorId,
                    $owner ? $nextAction : 'assign_case', $nextDue, $owner ? self::priorityForStage($stage) : 'urgent', $stage, $eventId, $owner ? 'next_action' : 'assign_case');
            }
            if (class_exists('AuditLog')) AuditLog::record('collection_promise', $eventType, 'operator_call', $eventId, [
                'actor_user_id' => $actorId, 'customer_id' => (int) $contract['customer_id'], 'contract_id' => (int) $contract['id'],
                'old_values' => ['promise_call_id' => $promiseCallId, 'promise_payment_date' => $original['promise_payment_date'], 'promise_amount_toman' => $original['promise_amount_toman'] ?? null],
                'new_values' => ['promise_call_id' => $eventId, 'promise_payment_date' => $nextDate, 'promise_amount_toman' => $nextAmount],
                'description' => $reason,
            ]);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function scheduleNextAction(int $contractId, int $customerId, ?int $assigneeId, int $actorId, string $actionType, string $dueDate, string $priority, string $stage, ?int $followupId, string $taskType): int
    {
        $validActions = ['call_customer', 'call_guarantor', 'check_promise', 'send_reminder', 'review_payment', 'review_legal_referral', 'legal_followup', 'manager_review', 'assign_case'];
        if (!in_array($actionType, $validActions, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) throw new InvalidArgumentException('اقدام بعدی یا موعد آن معتبر نیست.');
        $managerId = self::activeAssignmentManagerId();
        if ($taskType === 'assign_case') $assigneeId = $managerId;
        $dedupe = $taskType === 'assign_case' ? 'collection:assign:' . $contractId : 'collection:next-action:' . $contractId;
        $dueAt = $dueDate . ' 23:59:59';
        $existing = Model::fetch('SELECT * FROM collection_tasks WHERE dedupe_key=? AND status=\'open\' FOR UPDATE', [$dedupe]);
        if ($existing) {
            if ((string) $existing['action_type'] !== $actionType || (int) ($existing['assigned_to'] ?? 0) !== (int) ($assigneeId ?? 0) || (string) $existing['due_at'] !== $dueAt) {
                Model::execute('UPDATE collection_tasks SET assigned_to=?,stage=?,due_at=?,action_type=?,priority=?,related_followup_id=? WHERE id=?', [
                    $assigneeId, $stage, $dueAt, $actionType, $priority, $followupId, (int) $existing['id'],
                ]);
                if (class_exists('AuditLog')) AuditLog::record('collection_task', 'next_action_rescheduled', 'collection_task', (int) $existing['id'], [
                    'actor_user_id' => $actorId, 'customer_id' => $customerId, 'contract_id' => $contractId,
                    'old_values' => ['action_type' => $existing['action_type'], 'due_at' => $existing['due_at'], 'assigned_to' => $existing['assigned_to']],
                    'new_values' => ['action_type' => $actionType, 'due_at' => $dueAt, 'assigned_to' => $assigneeId],
                ]);
            }
            return (int) $existing['id'];
        }
        Model::execute(
            'INSERT INTO collection_tasks (contract_id,customer_id,assigned_to,stage,status,due_at,dedupe_key,created_at,action_type,priority,created_by,related_followup_id) VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?)',
            [$contractId, $customerId, $assigneeId, $stage, 'open', $dueAt, $dedupe, $actionType, $priority, $actorId ?: null, $followupId]
        );
        $taskId = (int) Model::db()->lastInsertId();
        if (class_exists('AuditLog')) AuditLog::record('collection_task', 'created', 'collection_task', $taskId, [
            'actor_user_id' => $actorId, 'customer_id' => $customerId, 'contract_id' => $contractId,
            'new_values' => ['action_type' => $actionType, 'priority' => $priority, 'due_at' => $dueAt, 'assigned_to' => $assigneeId, 'related_followup_id' => $followupId],
        ]);
        return $taskId;
    }

    private static function activeOperator(int $operatorId): ?array
    {
        if ($operatorId <= 0) return null;
        return Model::fetch("SELECT id,full_name FROM users WHERE id=? AND role='operator' AND status='active' AND archived_at IS NULL LIMIT 1", [$operatorId]) ?: null;
    }

    private static function activeAssignmentManagerId(): ?int
    {
        $configured = (int) Settings::get('collection_assignment_manager_id', '0');
        if ($configured <= 0) return null;
        $manager = Model::fetch("SELECT id FROM users WHERE id=? AND role='admin' AND status='active' AND archived_at IS NULL LIMIT 1", [$configured]);
        return $manager ? (int) $manager['id'] : null;
    }

    private static function assertActor(int $actorId, string $expectedRole): void
    {
        $actor = Model::fetch('SELECT id,role,status,archived_at FROM users WHERE id=? LIMIT 1', [$actorId]);
        if (!$actor || ($actor['role'] ?? '') !== $expectedRole || ($actor['status'] ?? '') !== 'active' || !empty($actor['archived_at'])) {
            throw new InvalidArgumentException('دسترسی به عملیات وصول مجاز نیست.', 403);
        }
    }

    private static function assertContractAccess(array $contract, int $actorId, string $actorRole): void
    {
        if ($actorRole === 'operator') {
            $owner = self::activeOperator((int) ($contract['assigned_operator_id'] ?? 0));
            if (!$owner || (int) $owner['id'] !== $actorId) throw new InvalidArgumentException('این قرارداد به شما واگذار نشده است.', 403);
        } elseif ($actorRole !== 'admin') {
            throw new InvalidArgumentException('دسترسی به عملیات وصول مجاز نیست.', 403);
        }
    }

    private static function isoDate($value): string
    {
        $value = trim(to_english_digits((string) $value));
        if ($value === '') return '';
        $date = parse_jalali_date($value) ?: $value;
        $check = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $check && $check->format('Y-m-d') === $date ? $date : '';
    }

    private static function optionalToman($value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $digits = preg_replace('/[,\s٬]/u', '', to_english_digits((string) $value));
        if (!preg_match('/^\d{1,18}$/', (string) $digits)) throw new InvalidArgumentException('مبلغ قول پرداخت معتبر نیست.');
        $amount = (int) $digits;
        return $amount > 0 ? $amount : null;
    }

    private static function maxOverdueDays(int $contractId): int
    {
        $row = Model::fetch("SELECT COALESCE(MAX(DATEDIFF(CURDATE(),due_date)),0) AS days FROM installments WHERE contract_id=? AND due_date<CURDATE() AND status NOT IN ('paid','cancelled')", [$contractId]);
        return max(0, (int) ($row['days'] ?? 0));
    }

    private static function completeTaskForFollowup(int $taskId, int $contractId, int $actorId, string $actorRole, int $callId): void
    {
        $task = Model::fetch('SELECT * FROM collection_tasks WHERE id=? AND contract_id=? FOR UPDATE', [$taskId, $contractId]);
        if (!$task || ($task['status'] ?? '') !== 'open') throw new InvalidArgumentException('اقدام باز برای تکمیل پیدا نشد.', 404);
        if ($actorRole === 'operator' && (int) ($task['assigned_to'] ?? 0) !== $actorId) throw new InvalidArgumentException('این اقدام به شما واگذار نشده است.', 403);
        self::closeTask($taskId, $actorId, 'followup_recorded', 'پیگیری در دفتر تماس ثبت شد.', $callId);
    }

    private static function completeCurrentNextAction(int $contractId, int $actorId, string $actorRole, int $callId): void
    {
        $params = [$contractId];
        $ownerFilter = '';
        if ($actorRole === 'operator') { $ownerFilter = ' AND assigned_to=?'; $params[] = $actorId; }
        $params[] = 'collection:next-action:' . $contractId;
        $task = Model::fetch("SELECT id FROM collection_tasks WHERE contract_id=? AND status='open'{$ownerFilter} AND dedupe_key=? ORDER BY due_at,id LIMIT 1 FOR UPDATE", $params);
        if ($task) self::closeTask((int) $task['id'], $actorId, 'followup_recorded', 'پیگیری در دفتر تماس ثبت شد.', $callId);
    }

    private static function closeTask(int $taskId, int $actorId, string $result, string $note, ?int $followupId = null): void
    {
        $task = Model::fetch('SELECT * FROM collection_tasks WHERE id=? FOR UPDATE', [$taskId]);
        if (!$task || ($task['status'] ?? '') !== 'open') return;
        $archiveKey = 'collection:closed:' . $taskId . ':' . substr(hash('sha256', (string) $task['dedupe_key']), 0, 12);
        Model::execute("UPDATE collection_tasks SET status='completed',completed_at=NOW(),completed_by=?,completion_result=?,completion_note=?,related_followup_id=COALESCE(?,related_followup_id),dedupe_key=? WHERE id=? AND status='open'", [
            $actorId, $result, $note, $followupId, $archiveKey, $taskId,
        ]);
        if (class_exists('AuditLog')) AuditLog::record('collection_task', 'completed', 'collection_task', $taskId, [
            'actor_user_id' => $actorId, 'contract_id' => (int) $task['contract_id'],
            'old_values' => ['status' => 'open', 'action_type' => $task['action_type'] ?? null],
            'new_values' => ['status' => 'completed', 'completion_result' => $result, 'related_followup_id' => $followupId],
        ]);
    }

    private static function readSafely(string $component, callable $read, $fallback, array &$warnings)
    {
        try {
            return $read();
        } catch (Throwable $e) {
            $warnings[] = $component;
            if (class_exists('ErrorHandler')) ErrorHandler::log('collection_' . $component, $e, 500);
            return $fallback;
        }
    }

    /** Explicitly run stage notices and create one idempotent work item per contract/stage. */
    public static function runStageAutomation(int $actorId): int
    {
        $lastId = 0; $created = 0;
        $admins = User::all('admin', null, 'active');
        $managerId = self::activeAssignmentManagerId();
        self::beginWorkTransaction();
        try {
            self::assertActor($actorId, 'admin');
            do {
                $rows = Model::fetchAll(
                    "SELECT c.id AS contract_id,c.customer_id,c.assigned_operator_id,op.id AS active_operator_id,c.contract_number,u.full_name AS customer_name,
                            MIN(i.due_date) AS oldest_due_date,MAX(DATEDIFF(CURDATE(),i.due_date)) AS max_overdue_days
                     FROM contracts c JOIN installments i ON i.contract_id=c.id JOIN users u ON u.id=c.customer_id
                     LEFT JOIN users op ON op.id=c.assigned_operator_id AND op.role='operator' AND op.status='active' AND op.archived_at IS NULL
                     WHERE c.id>? AND c.status NOT IN ('cancelled','closed') AND i.status NOT IN ('paid','cancelled') AND i.due_date<CURDATE()
                       AND COALESCE(i.base_amount,0)>COALESCE(i.paid_amount,0)
                     GROUP BY c.id,c.customer_id,c.assigned_operator_id,op.id,c.contract_number,u.full_name ORDER BY c.id LIMIT 200",
                    [$lastId]
                );
                foreach ($rows as $row) {
                    $lastId = max($lastId, (int) $row['contract_id']);
                    $locked = Model::fetch('SELECT id,customer_id,assigned_operator_id,status FROM contracts WHERE id=? FOR UPDATE', [(int) $row['contract_id']]);
                    if (!$locked || in_array((string) $locked['status'], ['cancelled', 'closed'], true)) continue;
                    $contractId = (int) $row['contract_id'];
                    $snapshot = FinancialDebtEngine::overdueBreakdown($contractId, date('Y-m-d'));
                    $snapshotStatus = (string) ($snapshot['calculation_status'] ?? 'needs_review');
                    if ($snapshotStatus === 'calculated' && (int) ($snapshot['total_overdue_debt'] ?? 0) <= 0) {
                        self::cancelSettledContractTasks($contractId, $actorId);
                        continue;
                    }
                    $stage = self::stageForDelay((int) $row['max_overdue_days']);
                    if ($snapshotStatus !== 'calculated' && $stage === 'current') $stage = 'reminder';
                    if ($stage === 'current') continue;
                    $dueKey = (string) $row['oldest_due_date'];
                    $dedupe = 'collection:' . $contractId . ':' . $stage . ':' . $dueKey;
                    if ($stage === 'reminder') {
                        Notification::create((int) $row['customer_id'], 'یادآوری سررسید قسط', 'برای قرارداد ' . $row['contract_number'] . ' قسط سررسیدشده وجود دارد. برای مشاهده جزئیات وارد پنل اقساط شوید.', 'overdue', url('installments/panel'), $dedupe);
                    } elseif ($stage === 'serious_warning') {
                        Notification::create((int) $row['customer_id'], 'هشدار تأخیر پرداخت', 'تاخیر قرارداد ' . $row['contract_number'] . ' از بازهٔ عادی پیگیری عبور کرده است. لطفاً وضعیت اقساط را بررسی کنید.', 'overdue', url('installments/panel'), $dedupe . ':customer');
                    }
                    $currentOwner = self::activeOperator((int) ($locked['assigned_operator_id'] ?? 0));
                    $owner = (int) ($currentOwner['id'] ?? 0);
                    $taskType = $owner > 0 ? 'next_action' : 'assign_case';
                    $actionType = $owner > 0 ? ($snapshotStatus !== 'calculated' ? 'review_payment' : ($stage === 'reminder' ? 'check_promise' : ($stage === 'legal_review' ? 'review_legal_referral' : 'call_customer'))) : 'assign_case';
                    $taskDueDate = self::nextActionDateForStage($stage, (int) $row['max_overdue_days']);
                    $beforeAction = Model::fetch("SELECT id FROM collection_tasks WHERE dedupe_key=? AND status='open' LIMIT 1", [$taskType === 'assign_case' ? 'collection:assign:' . $contractId : 'collection:next-action:' . $contractId]);
                    self::retireLegacyTasks($contractId, $actorId);
                    $taskId = self::scheduleNextAction($contractId, (int) $row['customer_id'], $owner > 0 ? $owner : $managerId, $actorId,
                        $actionType, $taskDueDate, $owner > 0 ? self::priorityForStage($stage) : 'urgent', $stage, null, $taskType);
                    if (!$beforeAction) $created++;
                    $targets = [];
                    if ($owner > 0) {
                        $targets[] = $owner;
                        if ($stage === 'serious_warning') foreach ($admins as $admin) $targets[] = (int) $admin['id'];
                    } else {
                        foreach ($admins as $admin) $targets[] = (int) $admin['id'];
                    }
                    foreach (array_unique($targets) as $targetId) {
                        Notification::create($targetId, $owner > 0 ? 'اقدام بعدی وصول ' . $stage : 'نیازمند تخصیص مسئول وصول', 'قرارداد ' . $row['contract_number'] . ' وارد مرحلهٔ پیگیری شده است.', 'overdue', url($taskId ? 'collections' : 'collections/timeline/' . $contractId), $dedupe . ':user:' . $targetId);
                    }
                }
            } while (count($rows) === 200);

            self::cancelTasksForSettledContracts($actorId, 200);
            if (class_exists('AuditLog')) AuditLog::record('collection', 'stage_automation_run', 'user', $actorId, ['actor_user_id' => $actorId, 'new_values' => ['tasks_created' => $created]]);
            Model::commit();
            return $created;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function nextActionDateForStage(string $stage, int $days): string
    {
        $rules = json_decode((string) Settings::get('collection_stage_rules_json', ''), true);
        if (!is_array($rules)) $rules = [];
        if ($stage === 'reminder') {
            $remaining = max(1, (int) ($rules['operator_task_min_days'] ?? 4) - $days);
            return date('Y-m-d', strtotime('+' . $remaining . ' days'));
        }
        if ($stage === 'operator_task') return date('Y-m-d', strtotime('+1 day'));
        return date('Y-m-d');
    }

    private static function countTasksForContract(int $contractId, string $status): int
    {
        $row = Model::fetch('SELECT COUNT(*) AS total FROM collection_tasks WHERE contract_id=? AND status=?', [$contractId, $status]);
        return (int) ($row['total'] ?? 0);
    }

    private static function retireLegacyTasks(int $contractId, int $actorId): void
    {
        $legacy = Model::fetchAll("SELECT * FROM collection_tasks WHERE contract_id=? AND status='open' AND action_type IS NULL AND dedupe_key LIKE ? FOR UPDATE", [$contractId, 'collection:' . $contractId . ':%']);
        foreach ($legacy as $task) {
            $archiveKey = 'collection:legacy:' . (int) $task['id'] . ':' . substr(hash('sha256', (string) $task['dedupe_key']), 0, 12);
            Model::execute("UPDATE collection_tasks SET status='cancelled',completed_at=NOW(),completed_by=?,completion_result='replaced_by_workspace',completion_note='وظیفهٔ مرحله‌ای قدیمی با اقدام صریح میزکار جایگزین شد.',dedupe_key=? WHERE id=? AND status='open'", [$actorId, $archiveKey, (int) $task['id']]);
            if (class_exists('AuditLog')) AuditLog::record('collection_task', 'legacy_task_replaced', 'collection_task', (int) $task['id'], [
                'actor_user_id' => $actorId, 'contract_id' => $contractId,
                'old_values' => ['status' => 'open', 'dedupe_key' => $task['dedupe_key']],
                'new_values' => ['status' => 'cancelled', 'completion_result' => 'replaced_by_workspace'],
            ]);
        }
    }

    private static function cancelTasksForSettledContracts(int $actorId, int $limit): void
    {
        $rows = Model::fetchAll(
            "SELECT DISTINCT c.id AS contract_id FROM collection_tasks t JOIN contracts c ON c.id=t.contract_id
              WHERE t.status='open' AND c.status NOT IN ('cancelled','closed')
                AND NOT EXISTS (SELECT 1 FROM installments i WHERE i.contract_id=c.id AND i.due_date<CURDATE()
                     AND i.status NOT IN ('paid','cancelled') AND COALESCE(i.base_amount,0)>COALESCE(i.paid_amount,0))
              ORDER BY c.id LIMIT " . max(1, min(500, $limit))
        );
        foreach ($rows as $row) {
            $contractId = (int) $row['contract_id'];
            $snapshot = FinancialDebtEngine::overdueBreakdown($contractId, date('Y-m-d'));
            if (($snapshot['calculation_status'] ?? '') !== 'calculated' || (int) ($snapshot['total_overdue_debt'] ?? 0) > 0) continue;
            self::cancelSettledContractTasks($contractId, $actorId);
        }
    }

    private static function cancelSettledContractTasks(int $contractId, int $actorId): void
    {
        $tasks = Model::fetchAll("SELECT id,dedupe_key FROM collection_tasks WHERE contract_id=? AND status='open' FOR UPDATE", [$contractId]);
        foreach ($tasks as $task) {
            $archiveKey = 'collection:settled:' . $contractId . ':' . (int) $task['id'];
            Model::execute("UPDATE collection_tasks SET status='cancelled',completed_at=NOW(),completed_by=?,completion_result='contract_settled',completion_note='بدهی سررسیدشده در بازمحاسبهٔ رسمی صفر است.',dedupe_key=? WHERE id=? AND status='open'", [$actorId, $archiveKey, (int) $task['id']]);
            if (class_exists('AuditLog')) AuditLog::record('collection_task', 'cancelled_after_settlement', 'collection_task', (int) $task['id'], [
                'actor_user_id' => $actorId, 'contract_id' => $contractId,
                'old_values' => ['status' => 'open'], 'new_values' => ['status' => 'cancelled', 'completion_result' => 'contract_settled'],
            ]);
        }
    }

    public static function completeTask(int $taskId, int $actorId, bool $admin, array $completion = []): void
    {
        $seed = Model::fetch('SELECT contract_id FROM collection_tasks WHERE id=? LIMIT 1', [$taskId]);
        if (!$seed) throw new InvalidArgumentException('وظیفه پیدا نشد.', 404);
        self::beginWorkTransaction();
        try {
            $contract = Model::fetch('SELECT id,customer_id,assigned_operator_id,status FROM contracts WHERE id=? FOR UPDATE', [(int) $seed['contract_id']]);
            if (!$contract) throw new InvalidArgumentException('قرارداد وظیفه پیدا نشد.', 404);
            $actorRole = $admin ? 'admin' : 'operator';
            self::assertActor($actorId, $actorRole);
            $task = Model::fetch('SELECT * FROM collection_tasks WHERE id=? AND contract_id=? FOR UPDATE', [$taskId, (int) $contract['id']]);
            if (!$task || ($task['status'] ?? '') !== 'open') throw new InvalidArgumentException('وظیفهٔ باز پیدا نشد.', 404);
            if (($task['action_type'] ?? '') === 'assign_case') throw new InvalidArgumentException('ابتدا باید مسئول پرونده را مشخص کنید.');
            if (!$admin && ((int) ($task['assigned_to'] ?? 0) !== $actorId || (int) ($contract['assigned_operator_id'] ?? 0) !== $actorId)) throw new InvalidArgumentException('این وظیفه به شما ارجاع نشده است.', 403);
            $result = trim((string) ($completion['result'] ?? ''));
            $note = trim((string) ($completion['note'] ?? ''));
            $nextAction = trim((string) ($completion['next_action_type'] ?? ''));
            $nextDate = self::isoDate($completion['next_followup_date'] ?? '');
            $allowedResults = ['action_completed', 'promise_waiting', 'payment_review', 'customer_unreachable', 'legal_review_required', 'other'];
            if (!in_array($result, $allowedResults, true) || mb_strlen($note, 'UTF-8') > 500) throw new InvalidArgumentException('نتیجه یا یادداشت تکمیل اقدام معتبر نیست.');
            $snapshot = FinancialDebtEngine::overdueBreakdown((int) $contract['id'], date('Y-m-d'));
            $snapshotStatus = (string) ($snapshot['calculation_status'] ?? 'needs_review');
            $hasDebt = $snapshotStatus !== 'calculated' || (int) ($snapshot['total_overdue_debt'] ?? 0) > 0;
            $actions = ['call_customer', 'call_guarantor', 'check_promise', 'send_reminder', 'review_payment', 'review_legal_referral', 'legal_followup', 'manager_review'];
            if ($hasDebt && ($nextDate === '' || !in_array($nextAction, $actions, true))) throw new InvalidArgumentException('بدهی همچنان برقرار است؛ اقدام بعدی و موعد آن را تعیین کنید.');
            if ($snapshotStatus !== 'calculated' && $nextAction !== 'review_payment') throw new InvalidArgumentException('محاسبهٔ بدهی نیازمند بررسی است؛ اقدام بعدی باید بررسی پرداخت باشد.');

            self::closeTask($taskId, $actorId, $result, $note ?: 'نتیجهٔ تکمیل اقدام ثبت شد.');
            if ($hasDebt && $nextDate !== '') {
                $owner = self::activeOperator((int) ($contract['assigned_operator_id'] ?? 0));
                $stage = self::stageForDelay(self::maxOverdueDays((int) $contract['id']));
                self::scheduleNextAction((int) $contract['id'], (int) $contract['customer_id'], $owner ? (int) $owner['id'] : null, $actorId,
                    $owner ? $nextAction : 'assign_case', $nextDate, $owner ? self::priorityForStage($stage) : 'urgent', $stage, null, $owner ? 'next_action' : 'assign_case');
            }
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function timeline(int $contractId, int $page = 1): array
    {
        $contract = Model::fetch('SELECT c.*, u.full_name AS customer_name, u.mobile, u.secondary_phone FROM contracts c JOIN users u ON u.id = c.customer_id WHERE c.id = ?', [$contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.', 404);
        $pageSize = 25;
        $page = max(1, min(100000, $page));
        $offset = ($page - 1) * $pageSize;
        $sources = [
            "SELECT CONCAT(i.due_date,' 00:00:00') AS at,i.id,'installment_due' AS kind,CONCAT('سررسید قسط ',i.installment_number) AS title,CONCAT('ماندهٔ ثبت‌شده: ',FORMAT(GREATEST(0,COALESCE(i.base_amount,0)-COALESCE(i.paid_amount,0)),0),' تومان') AS detail,CONCAT('contracts/show/',i.contract_id) AS url,i.status AS status FROM installments i WHERE i.contract_id=?",
            "SELECT COALESCE(p.paid_at,CONCAT(COALESCE(p.payment_date,DATE(p.created_at)),' 00:00:00')) AS at,p.id,'payment' AS kind,IF(p.status='corrected','اصلاح پرداخت','پرداخت') AS title,CONCAT(FORMAT(COALESCE(p.amount,0),0),' تومان — ',COALESCE(p.payment_type,'installment')) AS detail,'payments' AS url,p.status AS status FROM payments p WHERE p.contract_id=?",
            "SELECT oc.created_at AS at,oc.id,'operator_call' AS kind,CONCAT('پیگیری: ',oc.call_result) AS title,CONCAT(COALESCE(oc.notes,''),IF(oc.promise_payment_date IS NOT NULL,CONCAT(' | وعده: ',oc.promise_payment_date),''),IF(oc.result_code IS NULL,' | Legacy / طبقه‌بندی‌نشده','')) AS detail,'overdue' AS url,COALESCE(oc.result_code,'legacy_unclassified') AS status FROM operator_calls oc WHERE oc.contract_id=?",
            "SELECT COALESCE(lc.legal_referred_at,lc.updated_at) AS at,lc.id,'legal_stage' AS kind,CONCAT('پرونده حقوقی: ',lc.stage) AS title,CONCAT('وضعیت: ',lc.status,IF(lc.complaint_number IS NOT NULL,CONCAT(' | شماره پرونده: ',lc.complaint_number),'')) AS detail,CONCAT('legal/show/',lc.id) AS url,lc.status AS status FROM legal_cases lc WHERE lc.contract_id=?",
            "SELECT CONCAT(l.action_date,' ',COALESCE(l.action_time,'00:00:00')) AS at,l.id,'legal_action' AS kind,l.action_title AS title,CONCAT(COALESCE(l.description,''),IF(COALESCE(l.cost_amount,0)>0,CONCAT(' | هزینهٔ ثبت‌شده: ',FORMAT(l.cost_amount,0),' تومان'),'')) AS detail,NULL AS url,'logged' AS status FROM legal_case_logs l WHERE l.contract_id=?",
            "SELECT t.created_at AS at,t.id,'collection_task' AS kind,CONCAT('اقدام وصول: ',COALESCE(t.action_type,t.stage)) AS title,CONCAT('وضعیت: ',t.status,IF(t.due_at IS NOT NULL,CONCAT(' | موعد: ',t.due_at),''),IF(t.completion_note IS NOT NULL,CONCAT(' | ',t.completion_note),'')) AS detail,CONCAT('collections/timeline/',t.contract_id) AS url,t.status AS status FROM collection_tasks t WHERE t.contract_id=?",
            "SELECT a.created_at AS at,a.id,'collection_audit' AS kind,CONCAT('رویداد وصول: ',a.event_action) AS title,COALESCE(a.description,CONCAT(COALESCE(a.old_values,''),' → ',COALESCE(a.new_values,''))) AS detail,NULL AS url,a.event_result AS status FROM audit_logs a WHERE a.contract_id=? AND a.event_type IN ('collection','collection_task','collection_assignment','collection_promise')",
        ];
        if (SchemaGuard::tableExists('financial_ledger_events') && SchemaGuard::tableExists('financial_ledger_lines')) {
            $sources[] = "SELECT e.effective_at AS at,l.id,'ledger' AS kind,CONCAT('دفترکل: ',e.event_type,' / ',l.component) AS title,CONCAT(l.delta_toman,' تومان — ',COALESCE(l.description,e.description,'')) AS detail,NULL AS url,'posted' AS status FROM financial_ledger_events e JOIN financial_ledger_lines l ON l.event_id=e.id WHERE e.contract_id=?";
        }
        $union = implode(' UNION ALL ', $sources);
        $params = array_fill(0, count($sources), $contractId);
        $totalRow = Model::fetch('SELECT COUNT(*) AS total FROM (' . $union . ') timeline_count', $params);
        $items = Model::fetchAll('SELECT * FROM (' . $union . ') timeline_items ORDER BY at DESC,id DESC,kind ASC LIMIT ' . $pageSize . ' OFFSET ' . $offset, $params);
        $total = (int) ($totalRow['total'] ?? 0);
        $promise = self::currentPromisesForContracts([$contractId])[$contractId] ?? null;
        if ($promise) {
            self::$promisePaymentCoverageComplete = true;
            $paid = self::paymentsForPromises([$promise]);
            $promise['eligible_paid_toman'] = (int) ($paid[(int) $promise['id']] ?? 0);
            $promise['derived_status'] = self::derivePromiseStatus($promise, $promise['eligible_paid_toman'], date('Y-m-d'));
        }
        return ['contract' => $contract, 'items' => $items, 'page' => ['page' => $page, 'per_page' => $pageSize, 'total' => $total, 'pages' => max(1, (int) ceil($total / $pageSize))], 'financial_summary' => FinancialDebtEngine::summarize($contractId), 'active_promise' => $promise];
    }

    private static function countOverdue(int $days, ?int $operatorId): int
    {
        $row = Model::fetch("SELECT COUNT(DISTINCT c.id) AS total FROM contracts c JOIN installments i ON i.contract_id=c.id WHERE c.status NOT IN ('cancelled','closed') AND i.status NOT IN ('paid','cancelled') AND DATEDIFF(CURDATE(),i.due_date)>=? AND (? IS NULL OR c.assigned_operator_id=?)", [$days, $operatorId, $operatorId]);
        return (int) ($row['total'] ?? 0);
    }

    private static function countPromises(?int $operatorId): int
    {
        $row = Model::fetch("SELECT COUNT(*) AS total FROM operator_calls oc JOIN contracts c ON c.id=oc.contract_id WHERE oc.promise_payment_date=CURDATE() AND c.status NOT IN ('cancelled','closed') AND (? IS NULL OR c.assigned_operator_id=?)", [$operatorId, $operatorId]);
        return (int) ($row['total'] ?? 0);
    }

    private static function countNoContact(?int $operatorId): int
    {
        $row = Model::fetch("SELECT COUNT(*) AS total FROM contracts c WHERE c.status NOT IN ('cancelled','closed') AND (? IS NULL OR c.assigned_operator_id=?) AND EXISTS(SELECT 1 FROM installments i WHERE i.contract_id=c.id AND i.status NOT IN ('paid','cancelled') AND i.due_date<CURDATE()) AND NOT EXISTS(SELECT 1 FROM operator_calls oc WHERE oc.contract_id=c.id AND oc.created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY))", [$operatorId, $operatorId]);
        return (int) ($row['total'] ?? 0);
    }

    private static function withStages(array $items): array
    {
        $rules = json_decode((string) Settings::get('collection_stage_rules_json', ''), true);
        if (!is_array($rules)) $rules = [];
        foreach ($items as &$item) {
            $item['collection_stage'] = self::stageForDelay((int) ($item['max_overdue_days'] ?? 0), $rules);
        }
        unset($item);
        return $items;
    }

    private static function stageForDelay(int $days, ?array $rules = null): string
    {
        $rules = $rules ?? json_decode((string) Settings::get('collection_stage_rules_json', ''), true);
        if (!is_array($rules)) $rules = [];
        if ($days >= (int) ($rules['legal_review_min_days'] ?? 21)) return 'legal_review';
        if ($days >= (int) ($rules['serious_warning_min_days'] ?? 11)) return 'serious_warning';
        if ($days >= (int) ($rules['operator_task_min_days'] ?? 4)) return 'operator_task';
        if ($days >= (int) ($rules['reminder_min_days'] ?? 1)) return 'reminder';
        return 'current';
    }

    private static function openTasks(?int $operatorId): array
    {
        return Model::fetchAll(
            "SELECT t.*,c.contract_number,u.full_name AS customer_name,op.full_name AS assignee_name
             FROM collection_tasks t JOIN contracts c ON c.id=t.contract_id JOIN users u ON u.id=t.customer_id
             LEFT JOIN users op ON op.id=t.assigned_to WHERE t.status='open' AND (? IS NULL OR t.assigned_to=?)
             ORDER BY t.due_at ASC,t.id ASC LIMIT 50",
            [$operatorId, $operatorId]
        );
    }
}
