<?php

/** Read-only operational queues that reuse the existing overdue and legal policies. */
final class CollectionCommandCenter
{
    public static function overview(?int $operatorId = null): array
    {
        $warnings = [];
        $base = new OverdueFilterCriteria();
        $base->bucket = '30+'; $base->perPage = 10; $base->sort = 'delay_desc'; $base->direction = 'desc';
        $critical = self::readSafely('critical_queue', static fn () => OverdueAggregationService::paginated($base, $operatorId, false), ['items' => [], 'total' => 0], $warnings);

        $promiseRows = self::readSafely('promises_queue', static fn () => Model::fetchAll(
            "SELECT oc.contract_id, oc.customer_id, oc.promise_payment_date, oc.notes, oc.created_at, c.contract_number, u.full_name AS customer_name, u.mobile
             FROM operator_calls oc JOIN contracts c ON c.id = oc.contract_id JOIN users u ON u.id = oc.customer_id
             WHERE oc.promise_payment_date = CURDATE() AND c.status NOT IN ('cancelled', 'closed')
               AND (? IS NULL OR c.assigned_operator_id = ?)
             ORDER BY oc.created_at DESC, oc.id DESC LIMIT 10",
            [$operatorId, $operatorId]
        ), [], $warnings);

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
        $promiseCount = self::readSafely('promises_count', static fn () => self::countPromises($operatorId), 0, $warnings);
        $noContactCount = self::readSafely('no_contact_count', static fn () => self::countNoContact($operatorId), 0, $warnings);
        $tasks = self::readSafely('open_tasks', static fn () => self::openTasks($operatorId), [], $warnings);
        return [
            'metrics' => ['critical' => $criticalCount, 'promises_today' => $promiseCount, 'no_contact_7d' => $noContactCount, 'legal_review' => (int) $legalCount, 'open_tasks' => count($tasks)],
            'critical' => self::withStages($critical['items'] ?? []), 'promises' => $promiseRows, 'no_contact' => self::withStages($noContact['items'] ?? []), 'legal' => $legalQueue, 'tasks' => $tasks,
            'warnings' => array_values(array_unique($warnings)),
        ];
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
        Model::begin();
        try {
            do {
                $rows = Model::fetchAll(
                    "SELECT c.id AS contract_id,c.customer_id,c.assigned_operator_id,c.contract_number,u.full_name AS customer_name,
                            MIN(i.due_date) AS oldest_due_date,MAX(DATEDIFF(CURDATE(),i.due_date)) AS max_overdue_days
                     FROM contracts c JOIN installments i ON i.contract_id=c.id JOIN users u ON u.id=c.customer_id
                     WHERE c.id>? AND c.status NOT IN ('cancelled','closed') AND i.status NOT IN ('paid','cancelled') AND i.due_date<CURDATE()
                     GROUP BY c.id,c.customer_id,c.assigned_operator_id,c.contract_number,u.full_name ORDER BY c.id LIMIT 200",
                    [$lastId]
                );
                foreach ($rows as $row) {
                    $lastId = max($lastId, (int) $row['contract_id']);
                    $stage = self::stageForDelay((int) $row['max_overdue_days']);
                    if ($stage === 'current') continue;
                    $dueKey = (string) $row['oldest_due_date'];
                    $dedupe = 'collection:' . (int) $row['contract_id'] . ':' . $stage . ':' . $dueKey;
                    if ($stage === 'reminder') {
                        Notification::create((int) $row['customer_id'], 'یادآوری سررسید قسط', 'برای قرارداد ' . $row['contract_number'] . ' قسط سررسیدشده وجود دارد. برای مشاهده جزئیات وارد پنل اقساط شوید.', 'overdue', url('installments/panel'), $dedupe);
                    } elseif ($stage === 'serious_warning') {
                        Notification::create((int) $row['customer_id'], 'هشدار تأخیر پرداخت', 'تاخیر قرارداد ' . $row['contract_number'] . ' از بازهٔ عادی پیگیری عبور کرده است. لطفاً وضعیت اقساط را بررسی کنید.', 'overdue', url('installments/panel'), $dedupe . ':customer');
                    }
                    $taskId = null;
                    if (in_array($stage, ['operator_task', 'serious_warning', 'legal_review'], true)) {
                        $assignedTo = (int) ($row['assigned_operator_id'] ?? 0);
                        if ($stage === 'legal_review' || $assignedTo <= 0) $assignedTo = (int) ($admins[0]['id'] ?? 0);
                        $taskDue = $stage === 'operator_task' ? date('Y-m-d H:i:s', strtotime('+1 day')) : date('Y-m-d H:i:s');
                        $inserted = Model::execute(
                            'INSERT IGNORE INTO collection_tasks (contract_id,customer_id,assigned_to,stage,status,due_at,dedupe_key,created_at) VALUES (?,?,?,?,?,?,?,NOW())',
                            [(int) $row['contract_id'], (int) $row['customer_id'], $assignedTo ?: null, $stage, 'open', $taskDue, $dedupe]
                        );
                        if ($inserted > 0) $created++;
                        $task = Model::fetch('SELECT id FROM collection_tasks WHERE dedupe_key=? LIMIT 1', [$dedupe]);
                        $taskId = (int) ($task['id'] ?? 0);
                    }
                    $targets = [];
                    if ($stage === 'legal_review') {
                        foreach ($admins as $admin) $targets[] = (int) $admin['id'];
                    } elseif (!empty($row['assigned_operator_id'])) {
                        $targets[] = (int) $row['assigned_operator_id'];
                        if ($stage === 'serious_warning') foreach ($admins as $admin) $targets[] = (int) $admin['id'];
                    } else {
                        foreach ($admins as $admin) $targets[] = (int) $admin['id'];
                    }
                    foreach (array_unique($targets) as $targetId) {
                        Notification::create($targetId, 'وظیفهٔ وصول ' . $stage, 'قرارداد ' . $row['contract_number'] . ' وارد مرحلهٔ جدید پیگیری شده است.', 'overdue', url($taskId ? 'collections' : 'collections/timeline/' . (int) $row['contract_id']), $dedupe . ':user:' . $targetId);
                    }
                }
            } while (count($rows) === 200);
            if (class_exists('AuditLog')) AuditLog::record('collection', 'stage_automation_run', 'user', $actorId, ['actor_user_id' => $actorId, 'new_values' => ['tasks_created' => $created]]);
            Model::commit();
            return $created;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function completeTask(int $taskId, int $actorId, bool $admin): void
    {
        Model::begin();
        try {
            $task = Model::fetch('SELECT * FROM collection_tasks WHERE id=? FOR UPDATE', [$taskId]);
            if (!$task || ($task['status'] ?? '') !== 'open') throw new InvalidArgumentException('وظیفهٔ باز پیدا نشد.', 404);
            if (!$admin && (int) ($task['assigned_to'] ?? 0) !== $actorId) throw new InvalidArgumentException('این وظیفه به شما ارجاع نشده است.', 403);
            Model::execute("UPDATE collection_tasks SET status='completed',completed_at=NOW(),completed_by=? WHERE id=? AND status='open'", [$actorId, $taskId]);
            if (class_exists('AuditLog')) AuditLog::record('collection_task', 'completed', 'collection_task', $taskId, ['actor_user_id' => $actorId, 'contract_id' => (int) $task['contract_id'], 'old_values' => ['status' => 'open'], 'new_values' => ['status' => 'completed']]);
            Model::commit();
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function timeline(int $contractId): array
    {
        $contract = Model::fetch('SELECT c.*, u.full_name AS customer_name, u.mobile, u.secondary_phone FROM contracts c JOIN users u ON u.id = c.customer_id WHERE c.id = ?', [$contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.', 404);
        $items = [];
        foreach (Model::fetchAll('SELECT id, installment_number, due_date, base_amount, paid_amount, status FROM installments WHERE contract_id = ? ORDER BY due_date, installment_number, id LIMIT 200', [$contractId]) as $row) {
            $items[] = ['at' => (string) $row['due_date'], 'kind' => 'installment_due', 'title' => 'سررسید قسط ' . (int) $row['installment_number'], 'detail' => 'ماندهٔ ثبت‌شده: ' . number_format(max(0, normalize_money($row['base_amount']) - normalize_money($row['paid_amount']))) . ' تومان', 'url' => 'contracts/show/' . $contractId, 'status' => (string) $row['status']];
        }
        foreach (Model::fetchAll("SELECT id, amount, payment_date, paid_at, payment_type, method, status FROM payments WHERE contract_id = ? ORDER BY id DESC LIMIT 200", [$contractId]) as $row) {
            $items[] = ['at' => (string) ($row['payment_date'] ?: $row['paid_at']), 'kind' => 'payment', 'title' => ($row['status'] === 'corrected' ? 'اصلاح پرداخت' : 'پرداخت'), 'detail' => number_format(normalize_money($row['amount'])) . ' تومان — ' . (string) ($row['payment_type'] ?? 'installment'), 'url' => 'payments', 'status' => (string) $row['status']];
        }
        foreach (Model::fetchAll('SELECT oc.*, u.full_name AS operator_name FROM operator_calls oc LEFT JOIN users u ON u.id = oc.operator_id WHERE oc.contract_id = ? ORDER BY oc.id DESC LIMIT 200', [$contractId]) as $row) {
            $items[] = ['at' => (string) $row['created_at'], 'kind' => 'operator_call', 'title' => 'تماس اپراتور: ' . trim((string) $row['call_result']), 'detail' => trim((string) ($row['notes'] ?? '')) . (!empty($row['promise_payment_date']) ? ' | وعده: ' . jdate($row['promise_payment_date']) : ''), 'url' => 'overdue', 'status' => 'logged'];
        }
        foreach (Model::fetchAll('SELECT id, stage, status, legal_referred_at, updated_at, complaint_number FROM legal_cases WHERE contract_id = ? ORDER BY id DESC LIMIT 100', [$contractId]) as $row) {
            $items[] = ['at' => (string) ($row['legal_referred_at'] ?: $row['updated_at']), 'kind' => 'legal_stage', 'title' => 'پرونده حقوقی: ' . trim((string) $row['stage']), 'detail' => 'وضعیت: ' . (string) $row['status'] . (!empty($row['complaint_number']) ? ' | شماره پرونده: ' . $row['complaint_number'] : ''), 'url' => 'legal/show/' . (int) $row['id'], 'status' => (string) $row['status']];
        }
        foreach (Model::fetchAll('SELECT action_title,description,action_date,action_time,cost_amount,cost_type FROM legal_case_logs WHERE contract_id = ? ORDER BY action_date DESC,id DESC LIMIT 200', [$contractId]) as $row) {
            $detail = trim((string) ($row['description'] ?? ''));
            $cost = normalize_money($row['cost_amount'] ?? 0);
            if ($cost > 0) $detail .= ($detail !== '' ? ' | ' : '') . 'هزینهٔ ثبت‌شده: ' . number_format($cost) . ' تومان';
            $items[] = ['at' => (string) $row['action_date'] . ' ' . (string) ($row['action_time'] ?? '00:00:00'), 'kind' => 'legal_action', 'title' => (string) $row['action_title'], 'detail' => $detail, 'url' => null, 'status' => 'logged'];
        }
        foreach (FinancialLedgerService::recentForContract($contractId, 200) as $row) {
            $items[] = ['at' => (string) $row['effective_at'], 'kind' => 'ledger', 'title' => 'دفترکل: ' . (string) $row['event_type'] . ' / ' . (string) $row['component'], 'detail' => number_format((int) $row['delta_toman']) . ' تومان — ' . trim((string) ($row['description'] ?? '')), 'url' => null, 'status' => 'posted'];
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));
        return ['contract' => $contract, 'items' => array_slice($items, 0, 300), 'financial_summary' => FinancialDebtEngine::summarize($contractId)];
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
