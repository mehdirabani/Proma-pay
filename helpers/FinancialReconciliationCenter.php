<?php

/** Case registry and controlled workflow for FinancialReconciliationEngine. */
final class FinancialReconciliationCenter
{
    public const RULE_VERSION = 'financial-reconciliation-v1';
    private const BATCH_MAX = 50;

    public static function startRun(int $actorId, ?int $contractId = null, int $batchSize = 20): array
    {
        self::assertActor($actorId, ['admin', 'operator']);
        $contractId = $contractId && $contractId > 0 ? $contractId : null;
        $batchSize = max(1, min(self::BATCH_MAX, $batchSize));
        self::acquireNamedLock('proma-fin-reconciliation-run-start');
        try {
            Model::begin();
            try {
                Model::execute("UPDATE financial_reconciliation_runs SET status='partial', error_count=error_count+1, error_summary='اجرای اسکن بیش از دو ساعت بی‌پاسخ ماند؛ برای حفظ ایمنی ناقص علامت‌گذاری شد.', completed_at=NOW(), heartbeat_at=NOW() WHERE status='running' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
                $active = Model::fetch("SELECT id FROM financial_reconciliation_runs WHERE status = 'running' LIMIT 1 FOR UPDATE");
                if ($active) throw new RuntimeException('یک اجرای تطبیق دیگر در حال پردازش است.', 409);
                if ($contractId) {
                    $contract = Model::fetch('SELECT id FROM contracts WHERE id = ? LIMIT 1', [$contractId]);
                    if (!$contract) throw new InvalidArgumentException('قرارداد انتخاب‌شده پیدا نشد.', 404);
                    $targetCount = 1;
                    $maxContractId = $contractId;
                    $lastContractId = $contractId - 1;
                    $runType = 'contract';
                } else {
                    $range = Model::fetch('SELECT COUNT(*) AS total, COALESCE(MAX(id),0) AS max_id FROM contracts') ?: [];
                    $targetCount = (int) ($range['total'] ?? 0);
                    $maxContractId = (int) ($range['max_id'] ?? 0);
                    $lastContractId = 0;
                    $runType = 'full';
                }
                $runUuid = self::uuid();
                Model::execute(
                    'INSERT INTO financial_reconciliation_runs
                     (run_uuid,run_type,scope_contract_id,status,rule_version,coverage_basis,as_of_date,last_contract_id,max_contract_id,target_count,started_by,started_at,heartbeat_at,created_at)
                     VALUES (?,?,?,\'running\',?,?,?,?,?,?,?,NOW(),NOW(),NOW())',
                    [$runUuid, $runType, $contractId, self::RULE_VERSION, FinancialReconciliationEngine::COVERAGE_BASIS, date('Y-m-d'), $lastContractId, $maxContractId, $targetCount, $actorId]
                );
                $runId = (int) Model::lastInsertId();
                self::historySystemEvent($runId, $actorId, 'run_started', ['run_uuid' => $runUuid, 'run_type' => $runType, 'scope_contract_id' => $contractId, 'target_count' => $targetCount, 'as_of_date' => date('Y-m-d')]);
                Model::commit();
            } catch (Throwable $e) {
                Model::rollBack();
                throw $e;
            }
        } finally {
            self::releaseNamedLock('proma-fin-reconciliation-run-start');
        }
        return self::continueRun($runId, $actorId, $batchSize);
    }

    public static function continueRun(int $runId, int $actorId, int $batchSize = 20): array
    {
        self::assertActor($actorId, ['admin', 'operator']);
        $batchSize = max(1, min(self::BATCH_MAX, $batchSize));
        self::acquireNamedLock('proma-fin-reconciliation-run-batch');
        try {
            Model::begin();
            try {
                $run = Model::fetch('SELECT * FROM financial_reconciliation_runs WHERE id = ? LIMIT 1 FOR UPDATE', [$runId]);
                if (!$run) throw new InvalidArgumentException('اجرای تطبیق پیدا نشد.', 404);
                if (($run['status'] ?? '') !== 'running') {
                    Model::commit();
                    return self::run((int) $run['id']);
                }
                $sql = 'SELECT id FROM contracts WHERE id > ? AND id <= ? ORDER BY id LIMIT ' . $batchSize;
                $params = [(int) $run['last_contract_id'], (int) $run['max_contract_id']];
                if (($run['run_type'] ?? '') === 'contract') {
                    $sql = 'SELECT id FROM contracts WHERE id = ? AND id > ? ORDER BY id LIMIT 1';
                    $params = [(int) $run['scope_contract_id'], (int) $run['last_contract_id']];
                }
                $contracts = Model::fetchAll($sql, $params);
                $checked = 0;
                $findingCount = 0;
                $gapCount = 0;
                $errors = [];
                foreach ($contracts as $contractRow) {
                    $contractId = (int) $contractRow['id'];
                    try {
                        $inspection = FinancialReconciliationEngine::inspectContract($contractId, (string) $run['as_of_date']);
                        foreach ($inspection['findings'] as $finding) {
                            self::upsertFinding($finding, $run, $actorId);
                            $findingCount++;
                            if (($finding['classification'] ?? '') === 'historical_coverage_gap') $gapCount++;
                        }
                        if (empty($inspection['complete'])) $errors[] = 'قرارداد ' . $contractId . ': دامنهٔ منبع از سقف هر قرارداد فراتر رفت؛ نتیجهٔ اسکن برای این قرارداد ناقص است.';
                    } catch (Throwable $e) {
                        $errors[] = 'قرارداد ' . $contractId . ': ' . self::safeError($e);
                    }
                    $checked++;
                    $run['last_contract_id'] = $contractId;
                }
                $checkedTotal = (int) $run['checked_count'] + $checked;
                $errorTotal = (int) $run['error_count'] + count($errors);
                $newDiscrepancyTotal = (int) $run['discrepancy_count'] + $findingCount;
                $newGapTotal = (int) $run['historical_gap_count'] + $gapCount;
                $missingAtEnd = !$contracts && $checkedTotal < (int) $run['target_count'];
                if ($missingAtEnd) {
                    $errorTotal++;
                    $errors[] = 'تعداد قراردادهای باقی‌مانده با دامنهٔ ثبت‌شده برابر نبود؛ اجرای ناقص حفظ شد.';
                }
                $finished = !$contracts || $checkedTotal >= (int) $run['target_count'] || (int) $run['last_contract_id'] >= (int) $run['max_contract_id'];
                $status = $finished ? ($errorTotal === 0 ? 'completed' : 'partial') : 'running';
                $errorSummary = trim(implode("\n", array_filter([(string) ($run['error_summary'] ?? ''), implode("\n", $errors)])));
                Model::execute(
                    'UPDATE financial_reconciliation_runs SET last_contract_id=?,checked_count=?,error_count=?,discrepancy_count=?,historical_gap_count=?,status=?,error_summary=?,heartbeat_at=NOW(),completed_at=? WHERE id=?',
                    [(int) $run['last_contract_id'], $checkedTotal, $errorTotal, $newDiscrepancyTotal, $newGapTotal, $status, $errorSummary !== '' ? substr($errorSummary, 0, 4000) : null, $finished ? date('Y-m-d H:i:s') : null, $runId]
                );
                self::historySystemEvent($runId, $actorId, 'batch_processed', ['checked' => $checked, 'findings' => $findingCount, 'errors' => count($errors), 'status' => $status]);
                if ($finished) self::historySystemEvent($runId, $actorId, $status === 'completed' ? 'run_completed' : 'run_partial', ['checked_count' => $checkedTotal, 'error_count' => $errorTotal, 'discrepancy_count' => $newDiscrepancyTotal]);
                Model::commit();
            } catch (Throwable $e) {
                Model::rollBack();
                throw $e;
            }
        } finally {
            self::releaseNamedLock('proma-fin-reconciliation-run-batch');
        }
        return self::run($runId);
    }

    public static function run(int $runId): array
    {
        $row = Model::fetch('SELECT * FROM financial_reconciliation_runs WHERE id = ? LIMIT 1', [$runId]);
        return $row ?: [];
    }

    public static function latestRuns(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return Model::fetchAll('SELECT * FROM financial_reconciliation_runs ORDER BY id DESC LIMIT ' . $limit);
    }

    public static function summary(): array
    {
        $row = Model::fetch(
            "SELECT
                SUM(CASE WHEN status IN ('open','under_review','proposed_resolution','pending_approval','approved','failed_verification','reopened') AND classification='verified_mismatch' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN severity='critical' AND status NOT IN ('resolved','accepted_exception','rejected') AND classification='verified_mismatch' THEN 1 ELSE 0 END) AS critical_count,
                SUM(CASE WHEN status='under_review' THEN 1 ELSE 0 END) AS review_count,
                SUM(CASE WHEN status='pending_approval' THEN 1 ELSE 0 END) AS approval_count,
                SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN classification='historical_coverage_gap' THEN 1 ELSE 0 END) AS historical_gap_count,
                SUM(CASE WHEN classification='insufficient_evidence' OR status='needs_more_evidence' THEN 1 ELSE 0 END) AS evidence_count
             FROM financial_reconciliation_discrepancies"
        ) ?: [];
        $lastRun = Model::fetch("SELECT id,run_uuid,status,checked_count,target_count,discrepancy_count,started_at,completed_at FROM financial_reconciliation_runs WHERE status='completed' ORDER BY id DESC LIMIT 1");
        return [
            'open_count' => (int) ($row['open_count'] ?? 0),
            'critical_count' => (int) ($row['critical_count'] ?? 0),
            'review_count' => (int) ($row['review_count'] ?? 0),
            'approval_count' => (int) ($row['approval_count'] ?? 0),
            'resolved_count' => (int) ($row['resolved_count'] ?? 0),
            'historical_gap_count' => (int) ($row['historical_gap_count'] ?? 0),
            'evidence_count' => (int) ($row['evidence_count'] ?? 0),
            'last_run' => $lastRun,
            'open_amount_total' => null,
        ];
    }

    public static function listDiscrepancies(array $filters = [], int $page = 1, int $pageSize = 30): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        [$where, $params] = self::filterSql($filters);
        $count = Model::fetch('SELECT COUNT(*) AS total FROM financial_reconciliation_discrepancies d LEFT JOIN contracts c ON c.id=d.contract_id LEFT JOIN users u ON u.id=d.customer_id ' . $where, $params);
        $offset = ($page - 1) * $pageSize;
        $rows = Model::fetchAll(
            'SELECT d.*, c.contract_number, u.full_name AS customer_name, assigned.full_name AS assigned_name
               FROM financial_reconciliation_discrepancies d
               LEFT JOIN contracts c ON c.id=d.contract_id
               LEFT JOIN users u ON u.id=d.customer_id
               LEFT JOIN users assigned ON assigned.id=d.assigned_user_id
              ' . $where . ' ORDER BY FIELD(d.severity,\'critical\',\'high\',\'review\',\'info\'), d.last_seen_at DESC, d.id DESC LIMIT ' . $pageSize . ' OFFSET ' . $offset,
            $params
        );
        return ['items' => $rows, 'total' => (int) ($count['total'] ?? 0), 'page' => $page, 'page_size' => $pageSize, 'pages' => max(1, (int) ceil(((int) ($count['total'] ?? 0)) / $pageSize))];
    }

    public static function discrepancy(int $discrepancyId): array
    {
        $item = Model::fetch(
            'SELECT d.*, c.contract_number, c.status AS contract_status, u.full_name AS customer_name, assigned.full_name AS assigned_name
               FROM financial_reconciliation_discrepancies d
               LEFT JOIN contracts c ON c.id=d.contract_id
               LEFT JOIN users u ON u.id=d.customer_id
               LEFT JOIN users assigned ON assigned.id=d.assigned_user_id
              WHERE d.id=? LIMIT 1',
            [$discrepancyId]
        );
        if (!$item) throw new InvalidArgumentException('پروندهٔ مغایرت پیدا نشد.', 404);
        $payment = !empty($item['payment_id']) ? Model::fetch('SELECT id,contract_id,installment_id,payment_group_id,amount,method,status,payment_type,is_corrected,correction_reason,payment_date,paid_at,created_at FROM payments WHERE id=? LIMIT 1', [(int) $item['payment_id']]) : null;
        $paymentGroup = !empty($item['payment_group_id']) ? Model::fetch('SELECT id,group_number,contract_id,customer_id,requested_amount,allocated_amount,status,allocation_scope,created_at,completed_at FROM payment_groups WHERE id=? LIMIT 1', [(int) $item['payment_group_id']]) : null;
        $allocationIds = [];
        $allocations = [];
        if (!empty($item['payment_id'])) {
            $allocations = Model::fetchAll('SELECT id,payment_group_id,payment_id,contract_id,installment_id,allocated_amount,principal_applied,normal_penalty_applied,legal_penalty_applied,reward_applied,reversal_of_allocation_id,is_reversal,created_at FROM payment_allocations WHERE payment_id=? ORDER BY id', [(int) $item['payment_id']]);
            foreach ($allocations as $a) $allocationIds[] = (int) $a['id'];
            foreach (Model::fetchAll('SELECT id,payment_group_id,payment_id,legal_case_cost_id,contract_id,allocated_amount_toman,reversal_of_allocation_id,is_reversal,created_at FROM legal_cost_payment_allocations WHERE payment_id=? ORDER BY id', [(int) $item['payment_id']]) as $a) $allocations[] = $a + ['allocation_kind' => 'legal_cost'];
        }
        $ledgerEvents = [];
        if (!empty($item['ledger_event_id'])) {
            $ledgerEvents = Model::fetchAll('SELECT * FROM financial_ledger_events WHERE id=? ORDER BY id', [(int) $item['ledger_event_id']]);
        } elseif (!empty($item['payment_id'])) {
            $ledgerEvents = Model::fetchAll("SELECT * FROM financial_ledger_events WHERE source_type='payment' AND source_id=? ORDER BY id", [(int) $item['payment_id']]);
        } elseif (!empty($item['contract_id'])) {
            $ledgerEvents = Model::fetchAll('SELECT * FROM financial_ledger_events WHERE contract_id=? ORDER BY id DESC LIMIT 50', [(int) $item['contract_id']]);
        }
        foreach ($ledgerEvents as &$event) $event['lines'] = Model::fetchAll('SELECT id,component,delta_toman,source_line_id,description FROM financial_ledger_lines WHERE event_id=? ORDER BY id', [(int) $event['id']]);
        unset($event);
        $installment = !empty($item['installment_id']) ? Model::fetch('SELECT id,contract_id,installment_number,base_amount,paid_amount,remaining_amount,status,due_date,last_payment_date,effective_settlement_at FROM installments WHERE id=? LIMIT 1', [(int) $item['installment_id']]) : null;
        $history = Model::fetchAll('SELECT h.*,u.full_name AS actor_name FROM financial_reconciliation_history h LEFT JOIN users u ON u.id=h.actor_id WHERE h.discrepancy_id=? ORDER BY h.id DESC LIMIT 100', [$discrepancyId]);
        $proposals = Model::fetchAll('SELECT p.*, proposer.full_name AS proposer_name, reviewer.full_name AS reviewer_name FROM financial_reconciliation_proposals p LEFT JOIN users proposer ON proposer.id=p.proposed_by LEFT JOIN users reviewer ON reviewer.id=p.decided_by WHERE p.discrepancy_id=? ORDER BY p.id DESC LIMIT 20', [$discrepancyId]);
        return ['item' => $item, 'payment' => $payment, 'payment_group' => $paymentGroup, 'allocations' => $allocations, 'ledger_events' => $ledgerEvents, 'installment' => $installment, 'history' => $history, 'proposals' => $proposals, 'evidence' => json_decode((string) $item['evidence_json'], true) ?: []];
    }

    public static function startReview(int $discrepancyId, int $actorId, string $reason = ''): void
    {
        self::assertActor($actorId, ['admin', 'operator']);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            if (!in_array($case['status'], ['open', 'reopened', 'needs_more_evidence', 'failed_verification'], true)) throw new InvalidArgumentException('وضعیت فعلی پرونده برای شروع بررسی مجاز نیست.', 409);
            if ($case['classification'] === 'historical_coverage_gap') throw new InvalidArgumentException('نقص پوشش تاریخی پروندهٔ مغایرت تأییدشده نیست و باید با یادداشت بررسی شود.', 409);
            self::transition($case, 'under_review', 'reconciliation.review_started', $actorId, $reason, null);
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function addNote(int $discrepancyId, int $actorId, string $note): void
    {
        self::assertActor($actorId, ['admin', 'operator']);
        $note = trim($note);
        if (mb_strlen($note, 'UTF-8') < 2 || mb_strlen($note, 'UTF-8') > 2000) throw new InvalidArgumentException('یادداشت باید بین ۲ تا ۲۰۰۰ نویسه باشد.', 422);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            self::appendHistory($discrepancyId, null, null, $actorId, 'reconciliation.note_added', $case['status'], $case['status'], $note, null);
            self::audit($actorId, 'note_added', $case, $case['status'], $case['status'], ['note' => $note]);
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function assign(int $discrepancyId, int $assignedUserId, int $actorId): void
    {
        self::assertActor($actorId, ['admin']);
        $user = Model::fetch("SELECT id,role,archived_at FROM users WHERE id=? LIMIT 1", [$assignedUserId]);
        if (!$user || !in_array((string) $user['role'], ['admin', 'operator'], true) || !empty($user['archived_at'])) throw new InvalidArgumentException('مسئول انتخاب‌شده باید مدیر یا اپراتور فعال باشد.', 422);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            if (in_array($case['status'], ['resolved', 'accepted_exception', 'rejected'], true)) throw new InvalidArgumentException('پروندهٔ بسته‌شده قابل ارجاع نیست.', 409);
            Model::execute('UPDATE financial_reconciliation_discrepancies SET assigned_user_id=?,updated_at=NOW() WHERE id=?', [$assignedUserId, $discrepancyId]);
            self::appendHistory($discrepancyId, null, null, $actorId, 'reconciliation.assigned', $case['status'], $case['status'], 'ارجاع پرونده به کاربر ' . $assignedUserId, ['assigned_user_id' => $assignedUserId]);
            self::audit($actorId, 'assigned', $case, $case['status'], $case['status'], ['assigned_user_id' => $assignedUserId]);
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function proposeInstallmentRefresh(int $discrepancyId, int $actorId, string $reason): array
    {
        self::assertActor($actorId, ['admin', 'operator']);
        $reason = self::requiredReason($reason);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            if (($case['rule_id'] ?? '') !== 'R03' || ($case['issue_type'] ?? '') !== 'installment_materialization' || empty($case['installment_id']) || empty($case['source_fingerprint'])) {
                throw new InvalidArgumentException('برای این نوع مغایرت مسیر بازسازی وضعیت قسط تعریف نشده است.', 409);
            }
            if ($case['status'] !== 'under_review') throw new InvalidArgumentException('ابتدا پرونده را وارد مرحلهٔ بررسی کنید.', 409);
            $locked = InstallmentReconciliationService::lockMaterializationSource((int) $case['installment_id']);
            $fingerprint = InstallmentReconciliationService::sourceFingerprint((int) $case['installment_id']);
            if (!hash_equals((string) $case['source_fingerprint'], $fingerprint)) {
                self::transition($case, 'failed_verification', 'reconciliation.verification_failed', $actorId, 'منابع مالی پس از اسکن تغییر کرده‌اند؛ اسکن تازه لازم است.', ['source_fingerprint_changed' => true]);
                Model::commit();
                return ['status' => 'stale', 'proposal' => null];
            }
            $existing = Model::fetch("SELECT * FROM financial_reconciliation_proposals WHERE discrepancy_id=? AND source_fingerprint=? AND status IN ('pending_approval','approved') ORDER BY id DESC LIMIT 1 FOR UPDATE", [$discrepancyId, $fingerprint]);
            if ($existing) {
                Model::commit();
                return ['status' => (string) $existing['status'], 'proposal' => $existing];
            }
            $asOf = date('Y-m-d');
            $state = InstallmentFinancialStateService::state($locked, null, null, $asOf);
            $before = [
                'paid_toman' => normalize_money($locked['paid_amount'] ?? 0),
                'remaining_toman' => normalize_money($locked['remaining_amount'] ?? 0),
                'status' => (string) ($locked['status'] ?? ''),
            ];
            $after = [
                'paid_toman' => normalize_money($state['effective_paid_principal'] ?? 0),
                'remaining_toman' => normalize_money($state['remaining_principal'] ?? 0),
                'status' => (string) ($state['status'] ?? ''),
                'effective_settlement_at' => $state['effective_settlement_date'] ?? null,
                'as_of_date' => $asOf,
                'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
            ];
            $proposalUuid = self::uuid();
            $applyKey = hash('sha256', 'financial-reconciliation-apply|' . $proposalUuid);
            Model::execute(
                "INSERT INTO financial_reconciliation_proposals (proposal_uuid,discrepancy_id,correction_type,status,source_fingerprint,preview_json,reason,proposed_by,apply_idempotency_key,created_at)
                 VALUES (?,?,?,'pending_approval',?,?,?,?,?,NOW())",
                [$proposalUuid, $discrepancyId, 'refresh_installment_materialization', $fingerprint, json_encode(['installment_id' => (int) $case['installment_id'], 'before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $reason, $actorId, $applyKey]
            );
            $proposalId = (int) Model::lastInsertId();
            Model::execute("UPDATE financial_reconciliation_discrepancies SET status='pending_approval',updated_at=NOW() WHERE id=?", [$discrepancyId]);
            self::appendHistory($discrepancyId, null, $proposalId, $actorId, 'reconciliation.resolution_proposed', $case['status'], 'pending_approval', $reason, ['proposal_uuid' => $proposalUuid, 'before' => $before, 'after' => $after]);
            self::audit($actorId, 'resolution_proposed', $case, $case['status'], 'pending_approval', ['proposal_uuid' => $proposalUuid, 'before' => $before, 'after' => $after, 'reason' => $reason]);
            $proposal = Model::fetch('SELECT * FROM financial_reconciliation_proposals WHERE id=?', [$proposalId]);
            Model::commit();
            return ['status' => 'pending_approval', 'proposal' => $proposal];
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function decideProposal(string $proposalUuid, int $actorId, bool $approve, string $reason): array
    {
        self::assertActor($actorId, ['admin']);
        $reason = self::requiredReason($reason);
        $stale = false;
        Model::begin();
        try {
            $proposal = Model::fetch('SELECT * FROM financial_reconciliation_proposals WHERE proposal_uuid=? LIMIT 1 FOR UPDATE', [$proposalUuid]);
            if (!$proposal) throw new InvalidArgumentException('پیشنهاد اصلاح پیدا نشد.', 404);
            if (($proposal['status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('وضعیت پیشنهاد برای تصمیم‌گیری مجاز نیست.', 409);
            if ((int) $proposal['proposed_by'] === $actorId) throw new InvalidArgumentException('تأیید پیشنهاد توسط پیشنهاددهنده مجاز نیست.', 403);
            $case = self::lockedDiscrepancy((int) $proposal['discrepancy_id']);
            if (($case['status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('پرونده دیگر در انتظار تصمیم این پیشنهاد نیست.', 409);
            $preview = json_decode((string) $proposal['preview_json'], true) ?: [];
            $installmentId = (int) ($preview['installment_id'] ?? 0);
            $locked = InstallmentReconciliationService::lockMaterializationSource($installmentId);
            $fingerprint = InstallmentReconciliationService::sourceFingerprint($installmentId);
            if (!hash_equals((string) $proposal['source_fingerprint'], $fingerprint)) {
                $stale = true;
                Model::execute("UPDATE financial_reconciliation_proposals SET status='stale',decided_by=?,decision_reason=?,decided_at=NOW() WHERE id=?", [$actorId, $reason, (int) $proposal['id']]);
                self::transition($case, 'failed_verification', 'reconciliation.verification_failed', $actorId, 'منبع پس از پیشنهاد تغییر کرده است؛ تصمیم و اصلاح متوقف شد.', ['proposal_uuid' => $proposalUuid, 'stale_source' => true]);
            } else {
                $nextStatus = $approve ? 'approved' : 'under_review';
                Model::execute('UPDATE financial_reconciliation_proposals SET status=?,decided_by=?,decision_reason=?,decided_at=NOW() WHERE id=?', [$nextStatus, $actorId, $reason, (int) $proposal['id']]);
                self::transition($case, $nextStatus, $approve ? 'reconciliation.resolution_approved' : 'reconciliation.resolution_rejected', $actorId, $reason, ['proposal_uuid' => $proposalUuid, 'source_fingerprint' => $fingerprint]);
            }
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
        return ['status' => $stale ? 'stale' : ($approve ? 'approved' : 'rejected'), 'proposal_uuid' => $proposalUuid];
    }

    public static function applyProposal(string $proposalUuid, int $actorId): array
    {
        self::assertActor($actorId, ['admin']);
        $stale = false;
        $result = [];
        Model::begin();
        try {
            $proposal = Model::fetch('SELECT * FROM financial_reconciliation_proposals WHERE proposal_uuid=? LIMIT 1 FOR UPDATE', [$proposalUuid]);
            if (!$proposal) throw new InvalidArgumentException('پیشنهاد اصلاح پیدا نشد.', 404);
            if (in_array((string) $proposal['status'], ['applied', 'resolved'], true)) {
                $result = json_decode((string) ($proposal['result_json'] ?? ''), true) ?: [];
                $result['idempotent_replay'] = true;
                Model::commit();
                return $result;
            }
            if (($proposal['status'] ?? '') !== 'approved' || ($proposal['correction_type'] ?? '') !== 'refresh_installment_materialization') throw new InvalidArgumentException('این پیشنهاد برای اجرا تأیید نشده یا نوع اصلاح آن پشتیبانی نمی‌شود.', 409);
            $case = self::lockedDiscrepancy((int) $proposal['discrepancy_id']);
            if (($case['status'] ?? '') !== 'approved') throw new InvalidArgumentException('پرونده دیگر با این پیشنهاد تأییدشده نیست.', 409);
            $preview = json_decode((string) $proposal['preview_json'], true) ?: [];
            $installmentId = (int) ($preview['installment_id'] ?? 0);
            $approvedAsOf = (string) ($preview['after']['as_of_date'] ?? '');
            $refresh = InstallmentReconciliationService::applyApprovedMaterializationRefresh(
                $installmentId,
                (string) $proposal['source_fingerprint'],
                $approvedAsOf,
                $actorId
            );
            if (($refresh['status'] ?? '') === 'stale') {
                $stale = true;
                $staleReason = ($refresh['reason'] ?? '') === 'approved_date_expired' ? 'تاریخ مبنای پیشنهاد گذشته است؛ اسکن و پیشنهاد تازه لازم است.' : 'منبع مالی پس از تأیید تغییر کرده است؛ هیچ مبلغی بازنویسی نشد.';
                Model::execute("UPDATE financial_reconciliation_proposals SET status='stale',decided_by=?,decision_reason=?,decided_at=NOW() WHERE id=?", [$actorId, $staleReason, (int) $proposal['id']]);
                self::transition($case, 'failed_verification', 'reconciliation.verification_failed', $actorId, $staleReason, ['proposal_uuid' => $proposalUuid, 'stale_source' => true, 'reason' => $refresh['reason'] ?? 'source_changed']);
            } else {
                $operationId = 'FINREC-' . strtoupper(str_replace('-', '', $proposalUuid));
                $result = ['proposal_uuid' => $proposalUuid, 'operation_id' => $operationId, 'discrepancy_id' => (int) $case['id'], 'changed' => (bool) $refresh['changed'], 'before' => $refresh['before'], 'after' => $refresh['after'], 'reverification' => 'passed'];
                Model::execute("UPDATE financial_reconciliation_proposals SET status='resolved',operation_id=?,result_json=?,applied_at=NOW() WHERE id=?", [$operationId, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $proposal['id']]);
                Model::execute("UPDATE financial_reconciliation_discrepancies SET status='resolved',updated_at=NOW() WHERE id=?", [(int) $case['id']]);
                self::appendHistory((int) $case['id'], (int) $case['last_seen_run_id'], (int) $proposal['id'], $actorId, 'reconciliation.correction_applied', (string) $case['status'], 'resolved', (string) $proposal['reason'], $result);
                self::appendHistory((int) $case['id'], (int) $case['last_seen_run_id'], (int) $proposal['id'], $actorId, 'reconciliation.reverified', 'resolved', 'resolved', 'تطبیق مجدد همان قسط پس از اصلاح موفق بود.', $result);
                self::audit($actorId, 'correction_applied', $case, (string) $case['status'], 'resolved', $result, $installmentId);
                self::audit($actorId, 'resolved', $case, (string) $case['status'], 'resolved', $result, $installmentId);
            }
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
        if ($stale) return ['proposal_uuid' => $proposalUuid, 'status' => 'stale', 'changed' => false, 'reverification' => 'not_run'];
        return $result;
    }

    public static function verifyAndResolve(int $discrepancyId, int $actorId, string $reason): bool
    {
        self::assertActor($actorId, ['admin']);
        $reason = self::requiredReason($reason);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            if (!in_array($case['status'], ['under_review', 'failed_verification', 'reopened'], true)) throw new InvalidArgumentException('پرونده برای تطبیق مجدد در وضعیت مجاز نیست.', 409);
            $fresh = [];
            if (!empty($case['contract_id'])) {
                $inspection = FinancialReconciliationEngine::inspectContract((int) $case['contract_id'], date('Y-m-d'));
                $fresh = array_values(array_filter($inspection['findings'], static function ($finding) use ($case) { return hash_equals((string) $case['stable_key'], (string) ($finding['stable_key'] ?? '')); }));
            }
            if ($fresh) {
                self::transition($case, 'failed_verification', 'reconciliation.verification_failed', $actorId, $reason, ['current_evidence_hash' => $fresh[0]['evidence_hash'] ?? null]);
                Model::commit();
                return false;
            }
            self::transition($case, 'resolved', 'reconciliation.resolved', $actorId, $reason, ['verified_at' => date('Y-m-d H:i:s'), 'run_type' => 'targeted_complete_contract_scan']);
            Model::commit();
            return true;
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function acceptException(int $discrepancyId, int $actorId, string $reason): void
    {
        self::assertActor($actorId, ['admin']);
        $reason = self::requiredReason($reason);
        Model::begin();
        try {
            $case = self::lockedDiscrepancy($discrepancyId);
            if ($case['classification'] !== 'verified_mismatch' || in_array($case['status'], ['resolved', 'rejected'], true)) throw new InvalidArgumentException('این پرونده برای پذیرش استثنا واجد شرایط نیست.', 409);
            if (in_array($case['status'], ['pending_approval', 'approved'], true)) throw new InvalidArgumentException('ابتدا پیشنهاد اصلاح را رد یا لغو کنید.', 409);
            self::transition($case, 'accepted_exception', 'reconciliation.accepted_exception', $actorId, $reason, ['evidence_hash' => (string) $case['evidence_hash']]);
            Model::commit();
        } catch (Throwable $e) { Model::rollBack(); throw $e; }
    }

    public static function exportCsv(array $filters = []): string
    {
        $data = self::listDiscrepancies($filters, 1, 5000);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['شناسه', 'قرارداد', 'شناسه مشتری', 'Rule ID', 'نوع مغایرت', 'طبقه‌بندی', 'شدت', 'مبلغ مورد انتظار (تومان)', 'مبلغ واقعی (تومان)', 'اختلاف (تومان)', 'وضعیت', 'مسئول', 'تاریخ کشف', 'آخرین مشاهده', 'شناسه پرداخت', 'شناسه رویداد دفترکل', 'شناسه اصلاحیه']);
        foreach ($data['items'] as $row) {
            $cells = [
                $row['id'] ?? '', $row['contract_number'] ?? '', $row['customer_id'] ?? '', $row['rule_id'] ?? '', $row['issue_type'] ?? '',
                $row['classification'] ?? '', $row['severity'] ?? '', $row['expected_toman'] ?? '', $row['actual_toman'] ?? '', $row['difference_toman'] ?? '',
                $row['status'] ?? '', $row['assigned_user_id'] ?? '', $row['first_seen_at'] ?? '', $row['last_seen_at'] ?? '',
                $row['payment_id'] ?? '', $row['ledger_event_id'] ?? '', '',
            ];
            fputcsv($stream, array_map([self::class, 'csvSafe'], $cells));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return (string) $csv;
    }

    private static function upsertFinding(array $finding, array $run, int $actorId): void
    {
        $stableKey = (string) $finding['stable_key'];
        $current = Model::fetch('SELECT * FROM financial_reconciliation_discrepancies WHERE stable_key=? LIMIT 1 FOR UPDATE', [$stableKey]);
        $evidenceJson = json_encode($finding['evidence'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';
        $contractId = (int) ($finding['contract_id'] ?? 0) ?: null;
        $customerId = (int) ($finding['customer_id'] ?? 0) ?: null;
        $paymentId = (int) ($finding['payment_id'] ?? 0) ?: null;
        $paymentGroupId = (int) ($finding['payment_group_id'] ?? 0) ?: null;
        $allocationId = (int) ($finding['allocation_id'] ?? 0) ?: null;
        $installmentId = (int) ($finding['installment_id'] ?? 0) ?: null;
        $ledgerEventId = (int) ($finding['ledger_event_id'] ?? 0) ?: null;
        if (!$current) {
            $status = self::initialStatus((string) ($finding['classification'] ?? 'verified_mismatch'));
            $customer = $customerId ? Model::fetch('SELECT full_name FROM users WHERE id=? LIMIT 1', [$customerId]) : null;
            Model::execute(
                'INSERT INTO financial_reconciliation_discrepancies
                 (stable_key,rule_id,rule_version,issue_type,classification,severity,status,contract_id,customer_id,payment_id,payment_group_id,allocation_id,installment_id,ledger_event_id,expected_toman,actual_toman,difference_toman,source_fingerprint,evidence_json,evidence_hash,first_seen_run_id,last_seen_run_id,first_seen_at,last_seen_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())',
                [
                    $stableKey, (string) $finding['rule_id'], (string) $finding['rule_version'], (string) $finding['issue_type'], (string) $finding['classification'],
                    (string) $finding['severity'], $status, $contractId, $customerId, $paymentId, $paymentGroupId, $allocationId, $installmentId, $ledgerEventId,
                    $finding['expected_toman'] ?? null, $finding['actual_toman'] ?? null, $finding['difference_toman'] ?? null,
                    $finding['source_fingerprint'] ?? null, $evidenceJson, (string) $finding['evidence_hash'], (int) $run['id'], (int) $run['id'],
                ]
            );
            $id = (int) Model::lastInsertId();
            self::appendHistory($id, (int) $run['id'], null, $actorId, 'reconciliation.detected', null, $status, 'مغایرت با شواهد منبع ثبت شد.', ['stable_key' => $stableKey, 'rule_id' => $finding['rule_id'], 'evidence_hash' => $finding['evidence_hash'], 'classification' => $finding['classification']]);
            return;
        }

        $oldStatus = (string) $current['status'];
        $nextStatus = $oldStatus;
        if (in_array($oldStatus, ['resolved', 'accepted_exception'], true) && ($finding['classification'] ?? '') === 'verified_mismatch') $nextStatus = 'reopened';
        elseif ($oldStatus === 'needs_more_evidence' && ($finding['classification'] ?? '') === 'verified_mismatch') $nextStatus = 'open';
        Model::execute(
            'UPDATE financial_reconciliation_discrepancies
                SET rule_version=?,issue_type=?,classification=?,severity=?,status=?,contract_id=?,customer_id=?,payment_id=?,payment_group_id=?,allocation_id=?,installment_id=?,ledger_event_id=?,
                    expected_toman=?,actual_toman=?,difference_toman=?,source_fingerprint=?,evidence_json=?,evidence_hash=?,last_seen_run_id=?,last_seen_at=NOW(),updated_at=NOW()
              WHERE id=?',
            [
                (string) $finding['rule_version'], (string) $finding['issue_type'], (string) $finding['classification'], (string) $finding['severity'], $nextStatus,
                $contractId, $customerId, $paymentId, $paymentGroupId, $allocationId, $installmentId, $ledgerEventId,
                $finding['expected_toman'] ?? null, $finding['actual_toman'] ?? null, $finding['difference_toman'] ?? null,
                $finding['source_fingerprint'] ?? null, $evidenceJson, (string) $finding['evidence_hash'], (int) $run['id'], (int) $current['id'],
            ]
        );
        if ($oldStatus !== $nextStatus) self::appendHistory((int) $current['id'], (int) $run['id'], null, $actorId, $nextStatus === 'reopened' ? 'reconciliation.reopened' : 'reconciliation.classification_changed', $oldStatus, $nextStatus, 'اسکن تازه طبقه‌بندی/وضعیت پرونده را تغییر داد.', ['evidence_hash' => $finding['evidence_hash'], 'classification' => $finding['classification']]);
        elseif (!hash_equals((string) ($current['evidence_hash'] ?? ''), (string) $finding['evidence_hash'])) self::appendHistory((int) $current['id'], (int) $run['id'], null, $actorId, 'reconciliation.evidence_updated', $oldStatus, $oldStatus, 'شواهد پرونده در اسکن تازه تغییر کرده است.', ['previous_evidence_hash' => $current['evidence_hash'], 'evidence_hash' => $finding['evidence_hash']]);
    }

    private static function initialStatus(string $classification): string
    {
        if ($classification === 'historical_coverage_gap') return 'known_historical_gap';
        if ($classification === 'insufficient_evidence') return 'needs_more_evidence';
        return 'open';
    }

    private static function filterSql(array $filters): array
    {
        $where = [];
        $params = [];
        $contract = trim((string) ($filters['contract'] ?? ''));
        if ($contract !== '') {
            $where[] = '(c.contract_number LIKE ? OR CAST(d.contract_id AS CHAR)=?)';
            $params[] = '%' . mb_substr($contract, 0, 100, 'UTF-8') . '%';
            $params[] = $contract;
        }
        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $where[] = '(u.full_name LIKE ? OR CAST(d.customer_id AS CHAR)=?)';
            $params[] = '%' . mb_substr($customer, 0, 100, 'UTF-8') . '%';
            $params[] = $customer;
        }
        foreach (['rule_id' => 'd.rule_id', 'severity' => 'd.severity', 'status' => 'd.status', 'classification' => 'd.classification'] as $key => $column) {
            if (isset($filters[$key]) && trim((string) $filters[$key]) !== '') { $where[] = $column . '=?'; $params[] = trim((string) $filters[$key]); }
        }
        foreach (['payment_id' => 'd.payment_id', 'ledger_event_id' => 'd.ledger_event_id', 'assigned_user_id' => 'd.assigned_user_id'] as $key => $column) {
            $value = (int) ($filters[$key] ?? 0);
            if ($value > 0) { $where[] = $column . '=?'; $params[] = $value; }
        }
        $from = self::validFilterDate($filters['date_from'] ?? '');
        $to = self::validFilterDate($filters['date_to'] ?? '');
        if ($from !== null) { $where[] = 'd.last_seen_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to !== null) { $where[] = 'd.last_seen_at <= ?'; $params[] = $to . ' 23:59:59'; }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private static function validFilterDate($value): ?string
    {
        $date = trim((string) $value);
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) return null;
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private static function transition(array $case, string $next, string $action, int $actorId, string $reason, ?array $evidence): void
    {
        $before = (string) $case['status'];
        Model::execute('UPDATE financial_reconciliation_discrepancies SET status=?,updated_at=NOW() WHERE id=?', [$next, (int) $case['id']]);
        self::appendHistory((int) $case['id'], null, null, $actorId, $action, $before, $next, $reason, $evidence);
        self::audit($actorId, str_replace('reconciliation.', '', $action), $case, $before, $next, ['reason' => $reason, 'evidence' => $evidence]);
    }

    private static function appendHistory(?int $discrepancyId, ?int $runId, ?int $proposalId, ?int $actorId, string $action, ?string $before, ?string $after, ?string $reason, ?array $evidence): void
    {
        $key = hash('sha256', $action . '|' . ($discrepancyId ?: 0) . '|' . ($runId ?: 0) . '|' . ($proposalId ?: 0) . '|' . ($actorId ?: 0) . '|' . self::uuid());
        Model::execute(
            'INSERT INTO financial_reconciliation_history (discrepancy_id,run_id,proposal_id,actor_id,actor_role,action,status_before,status_after,reason,evidence_json,idempotency_key,occurred_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [$discrepancyId, $runId, $proposalId, $actorId, $actorId ? (string) (Auth::role() ?? 'unknown') : 'system', $action, $before, $after, $reason === '' ? null : $reason, $evidence === null ? null : json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), $key]
        );
    }

    private static function historySystemEvent(int $runId, int $actorId, string $action, array $evidence): void
    {
        self::appendHistory(null, $runId, null, $actorId, 'reconciliation.' . $action, null, null, null, $evidence);
    }

    private static function audit(int $actorId, string $action, array $case, string $before, string $after, array $values, ?int $installmentId = null): void
    {
        $oldValues = ['status' => $before];
        $newValues = ['status' => $after] + $values;
        if (isset($values['before']) && is_array($values['before'])) $oldValues += $values['before'];
        if (isset($values['after']) && is_array($values['after'])) $newValues = ['status' => $after] + $values['after'] + $values;
        AuditLog::record('financial_reconciliation', $action, 'financial_reconciliation', (int) $case['id'], [
            'actor_user_id' => $actorId,
            'severity' => in_array($action, ['correction_applied', 'resolved'], true) ? 'critical' : 'high',
            'contract_id' => !empty($case['contract_id']) ? (int) $case['contract_id'] : null,
            'customer_id' => !empty($case['customer_id']) ? (int) $case['customer_id'] : null,
            'installment_id' => $installmentId ?: (!empty($case['installment_id']) ? (int) $case['installment_id'] : null),
            'description' => 'مرکز تطبیق مالی: ' . $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    private static function lockedDiscrepancy(int $id): array
    {
        $case = Model::fetch('SELECT * FROM financial_reconciliation_discrepancies WHERE id=? LIMIT 1 FOR UPDATE', [$id]);
        if (!$case) throw new InvalidArgumentException('پروندهٔ مغایرت پیدا نشد.', 404);
        return $case;
    }

    private static function assertActor(int $actorId, array $roles): void
    {
        if ($actorId <= 0 || (int) Auth::id() !== $actorId || !in_array((string) Auth::role(), $roles, true)) throw new InvalidArgumentException('برای این عملیات دسترسی کافی ندارید.', 403);
        $user = Model::fetch('SELECT id,role,archived_at FROM users WHERE id=? LIMIT 1', [$actorId]);
        if (!$user || (string) $user['role'] !== (string) Auth::role() || !empty($user['archived_at'])) throw new InvalidArgumentException('نشست کاربری دیگر مجاز نیست.', 403);
    }

    private static function acquireNamedLock(string $name): void
    {
        $row = Model::fetch('SELECT GET_LOCK(?,0) AS acquired', [$name]);
        if ((int) ($row['acquired'] ?? 0) !== 1) throw new RuntimeException('یک عملیات تطبیق هم‌زمان در جریان است.', 409);
    }

    private static function releaseNamedLock(string $name): void
    {
        try { Model::fetch('SELECT RELEASE_LOCK(?) AS released', [$name]); } catch (Throwable $e) { /* Connection close releases the MySQL advisory lock. */ }
    }

    private static function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason, 'UTF-8') < 3 || mb_strlen($reason, 'UTF-8') > 1000) throw new InvalidArgumentException('شرح اقدام باید بین ۳ تا ۱۰۰۰ نویسه باشد.', 422);
        return $reason;
    }

    private static function safeError(Throwable $e): string
    {
        $message = trim(preg_replace('/[\r\n\t]+/', ' ', $e->getMessage()));
        return mb_substr($message !== '' ? $message : get_class($e), 0, 220, 'UTF-8');
    }

    private static function csvSafe($value): string
    {
        $value = (string) ($value ?? '');
        if (preg_match('/\A-?(?:\d+|\d*\.\d+)\z/', $value)) return $value;
        return preg_match('/\A[=+@\-]/u', $value) ? "'" . $value : $value;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

