<?php

/**
 * Versioned financial contract amendments for v7.0.4.
 * The amendment workflow accepts an explicit future schedule; it deliberately
 * does not guess a new interest formula for an already-running contract.
 */
final class ContractAmendmentService
{
    const CALCULATION_VERSION = 'manual-approved-schedule-v1';
    const TABLES = ['contract_financial_versions', 'contract_amendments', 'contract_amendment_history'];

    public static function recordCreationBaseline($contractId, $actorId = null): void
    {
        self::recordBaseline((int) $contractId, $actorId ? (int) $actorId : null, 'created_state_approval_unrecorded', 1);
    }

    /** Imported schedules are observed snapshots, not proof of complete source history or legal approval. */
    public static function recordImportedBaseline($contractId, $actorId = null): void
    {
        self::recordBaseline((int) $contractId, $actorId ? (int) $actorId : null, 'imported_state_approval_unrecorded', 0);
    }

    private static function recordBaseline(int $contractId, ?int $actorId, string $provenance, int $historyComplete): void
    {
        if (!self::schemaAvailable()) return;
        self::requireSchema();
        if (Model::fetch('SELECT id FROM contract_financial_versions WHERE contract_id = ? LIMIT 1', [$contractId])) return;
        $snapshot = self::snapshot($contractId);
        $versionId = self::insertVersion($contractId, 1, null, null, $provenance, $historyComplete, $snapshot, $actorId, date('Y-m-d'));
        self::event(null, $contractId, 'version_created', $actorId, null, null, null, ['version_id' => $versionId, 'provenance' => $provenance, 'content_hash' => self::hashSnapshot($snapshot)]);
    }

    /** Record the state first observed after the migration without claiming it is original. */
    public static function ensureLegacyBaseline($contractId): array
    {
        self::requireSchema();
        $current = Model::fetch('SELECT * FROM contract_financial_versions WHERE contract_id = ? AND is_current = 1 ORDER BY version_number DESC LIMIT 1', [(int) $contractId]);
        if ($current) return $current;
        $ownsTransaction = !Model::db()->inTransaction();
        if ($ownsTransaction) Model::begin();
        try {
            $contract = Model::fetch('SELECT id FROM contracts WHERE id = ? FOR UPDATE', [(int) $contractId]);
            if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
            $current = Model::fetch('SELECT * FROM contract_financial_versions WHERE contract_id = ? AND is_current = 1 ORDER BY version_number DESC LIMIT 1 FOR UPDATE', [(int) $contractId]);
            if (!$current) {
                $latest = Model::fetch('SELECT COALESCE(MAX(version_number), 0) AS n FROM contract_financial_versions WHERE contract_id = ?', [(int) $contractId]);
                $snapshot = self::snapshot((int) $contractId);
                $id = self::insertVersion((int) $contractId, (int) ($latest['n'] ?? 0) + 1, null, null, 'legacy_baseline_incomplete_history', 0, $snapshot, null, date('Y-m-d'));
                self::event(null, (int) $contractId, 'version_created', null, null, null, null, ['version_id' => $id, 'provenance' => 'legacy_baseline_incomplete_history', 'content_hash' => self::hashSnapshot($snapshot)]);
                self::event(null, (int) $contractId, 'legacy_baseline_created', null, null, null, 'ثبت وضعیت موجود به‌عنوان خط مبنای تاریخی ناقص؛ تأییدکننده یا نسخه اولیه استنباط نشده است.', ['version_id' => $id, 'content_hash' => self::hashSnapshot($snapshot)]);
                $current = Model::fetch('SELECT * FROM contract_financial_versions WHERE id = ?', [$id]);
            }
            if ($ownsTransaction) Model::commit();
            return $current;
        } catch (Throwable $e) {
            if ($ownsTransaction) Model::rollBack();
            throw $e;
        }
    }

    public static function viewModel($contractId): array
    {
        self::requireSchema();
        $current = self::ensureLegacyBaseline((int) $contractId);
        $versions = Model::fetchAll('SELECT v.*, u.full_name AS created_by_name FROM contract_financial_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.contract_id = ? ORDER BY v.version_number DESC', [(int) $contractId]);
        foreach ($versions as &$version) {
            $terms = self::decode((string) $version['terms_snapshot_json']);
            $schedule = self::decode((string) $version['schedule_snapshot_json']);
            $version['terms'] = $terms;
            $version['schedule'] = $schedule;
            $version['hash_valid'] = hash_equals((string) $version['content_hash'], self::hashSnapshot(['terms' => $terms, 'schedule' => $schedule]));
        }
        unset($version);
        $documentFields = '';
        $documentJoin = '';
        if (SchemaGuard::tableExists('contract_document_versions')) {
            $documentFields = ', dv.version_number AS document_version_number, dv.source AS document_source, dv.checksum AS document_checksum';
            $documentJoin = ' LEFT JOIN contract_document_versions dv ON dv.id = a.document_version_id';
        }
        $amendments = Model::fetchAll(
            'SELECT a.*, requester.full_name AS requested_by_name, approver.full_name AS approved_by_name,
                    rejecter.full_name AS rejected_by_name, applier.full_name AS applied_by_name' . $documentFields . '
             FROM contract_amendments a
             LEFT JOIN users requester ON requester.id = a.requested_by
             LEFT JOIN users approver ON approver.id = a.approved_by
             LEFT JOIN users rejecter ON rejecter.id = a.rejected_by
             LEFT JOIN users applier ON applier.id = a.applied_by' . $documentJoin . '
             WHERE a.contract_id = ? ORDER BY a.amendment_number DESC', [(int) $contractId]
        );
        foreach ($amendments as &$amendment) {
            $amendment['proposed'] = self::decode((string) $amendment['proposed_snapshot_json']);
            $amendment['impact'] = self::decode((string) $amendment['impact_snapshot_json']);
            $amendment['history'] = Model::fetchAll('SELECT h.*, u.full_name AS actor_name FROM contract_amendment_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.amendment_id = ? ORDER BY h.id ASC', [(int) $amendment['id']]);
        }
        unset($amendment);
        $editor = self::editableFutureSchedule((int) $contractId, date('Y-m-d'));
        return ['current_version' => $current, 'versions' => $versions, 'amendments' => $amendments, 'schedule_editor' => $editor];
    }

    /** Rebuild contract terms and installment state from one immutable, checksum-verified snapshot. */
    public static function reconstructVersion(int $contractId, int $versionId): array
    {
        self::requireSchema();
        $version = Model::fetch('SELECT * FROM contract_financial_versions WHERE id = ? AND contract_id = ? LIMIT 1', [$versionId, $contractId]);
        if (!$version) throw new InvalidArgumentException('نسخه مالی قرارداد پیدا نشد.');
        $snapshot = [
            'terms' => self::decode((string) $version['terms_snapshot_json']),
            'schedule' => self::decode((string) $version['schedule_snapshot_json']),
        ];
        $hashValid = hash_equals((string) $version['content_hash'], self::hashSnapshot($snapshot));
        if (!$hashValid) throw new RuntimeException('Checksum نسخه مالی معتبر نیست؛ بازسازی متوقف شد.');
        return [
            'version_id' => (int) $version['id'], 'version_number' => (int) $version['version_number'],
            'provenance' => (string) $version['provenance'], 'history_complete' => (bool) $version['history_complete'],
            'effective_from' => (string) $version['effective_from'], 'content_hash' => (string) $version['content_hash'],
            'terms' => $snapshot['terms'], 'schedule' => $snapshot['schedule'],
            'scope' => 'contract_terms_and_installment_state_not_historical_balance',
        ];
    }

    /** Compare only verified snapshots; no historical value is inferred from the current contract. */
    public static function compareVersions(int $contractId, int $leftVersionId, int $rightVersionId): array
    {
        $left = self::reconstructVersion($contractId, $leftVersionId);
        $right = self::reconstructVersion($contractId, $rightVersionId);
        $termChanges = [];
        foreach (array_unique(array_merge(array_keys($left['terms']), array_keys($right['terms']))) as $key) {
            $before = $left['terms'][$key] ?? null;
            $after = $right['terms'][$key] ?? null;
            if ((string) $before !== (string) $after) $termChanges[$key] = ['before' => $before, 'after' => $after];
        }
        $indexSchedule = static function (array $schedule): array {
            $indexed = [];
            foreach ($schedule as $row) {
                if (!is_array($row)) continue;
                $id = (int) ($row['id'] ?? $row['installment_id'] ?? 0);
                $key = $id > 0 ? 'id:' . $id : 'line:' . (int) ($row['installment_number'] ?? count($indexed) + 1);
                $indexed[$key] = $row;
            }
            return $indexed;
        };
        $leftSchedule = $indexSchedule($left['schedule']);
        $rightSchedule = $indexSchedule($right['schedule']);
        $scheduleChanges = [];
        $fields = ['installment_number', 'due_date', 'base_amount', 'paid_amount', 'remaining_amount', 'status', 'is_custom'];
        foreach (array_unique(array_merge(array_keys($leftSchedule), array_keys($rightSchedule))) as $key) {
            $before = $leftSchedule[$key] ?? null;
            $after = $rightSchedule[$key] ?? null;
            $changes = [];
            foreach ($fields as $field) {
                $old = $before[$field] ?? null;
                $new = $after[$field] ?? null;
                if (in_array($field, ['base_amount', 'paid_amount', 'remaining_amount'], true)) {
                    $old = $old === null ? null : normalize_money($old);
                    $new = $new === null ? null : normalize_money($new);
                }
                if ((string) $old !== (string) $new) $changes[$field] = ['before' => $old, 'after' => $new];
            }
            if ($changes) {
                $scheduleChanges[] = [
                    'reference' => $key,
                    'classification' => !$before ? 'added' : (!$after ? 'removed' : 'changed'),
                    'before' => $before, 'after' => $after, 'changes' => $changes,
                ];
            }
        }
        return [
            'contract_id' => $contractId, 'left_version_id' => $leftVersionId, 'right_version_id' => $rightVersionId,
            'left_hash' => $left['content_hash'], 'right_hash' => $right['content_hash'],
            'term_changes' => $termChanges, 'schedule_changes' => $scheduleChanges,
            'scope' => 'contract_terms_and_installment_state_not_historical_balance',
        ];
    }

    /** Return the latest verified contractual snapshot effective on a date, or an explicit history gap. */
    public static function reconstructAsOf(int $contractId, string $asOfDate): array
    {
        self::requireSchema();
        $asOfDate = self::dateValue($asOfDate, 'تاریخ بازسازی معتبر نیست.');
        $version = Model::fetch('SELECT id FROM contract_financial_versions WHERE contract_id = ? AND effective_from <= ? ORDER BY effective_from DESC, version_number DESC LIMIT 1', [$contractId, $asOfDate]);
        if (!$version) return ['available' => false, 'as_of' => $asOfDate, 'reason' => 'در Snapshotهای ثبت‌شده نسخه‌ای با تاریخ اثرگذاری تا این روز وجود ندارد.'];
        $snapshot = self::reconstructVersion($contractId, (int) $version['id']);
        return ['available' => true, 'as_of' => $asOfDate, 'historical_balance_reconstructed' => false, 'snapshot' => $snapshot];
    }

    public static function request($contractId, array $input, $actorId): array
    {
        self::requireSchema();
        $contractId = (int) $contractId;
        $actorId = (int) $actorId;
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($contractId <= 0 || $actorId <= 0 || mb_strlen($reason, 'UTF-8') < 5) {
            throw new InvalidArgumentException('علت اصلاحیه را حداقل با ۵ نویسه وارد کنید.');
        }
        $effectiveOn = self::dateValue($input['effective_on'] ?? date('Y-m-d'), 'تاریخ اثرگذاری معتبر نیست.');
        if ($effectiveOn < date('Y-m-d')) throw new InvalidArgumentException('اصلاحیه آینده‌نگر نمی‌تواند تاریخ اثرگذاری گذشته داشته باشد.');
        $termsInput = is_array($input['terms'] ?? null) ? $input['terms'] : $input;
        $terms = self::normaliseTerms($termsInput);
        Contract::validateFinancialData($terms);
        if (!array_key_exists('schedule', $input) || !is_array($input['schedule'])) {
            throw new InvalidArgumentException('برنامه اقساط پیشنهادی را کامل کنید.');
        }
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') $idempotencyKey = bin2hex(random_bytes(24));
        if (strlen($idempotencyKey) > 100) throw new InvalidArgumentException('شناسه تکرار درخواست معتبر نیست.');

        Model::begin();
        try {
            $contract = Model::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
            if (in_array((string) $contract['status'], ['cancelled', 'completed', 'closed'], true)) {
                throw new InvalidArgumentException('برای قرارداد لغو یا مختومه اصلاحیه مالی ثبت نمی‌شود.');
            }
            $duplicate = Model::fetch('SELECT id, amendment_number, status FROM contract_amendments WHERE contract_id = ? AND idempotency_key = ? LIMIT 1', [$contractId, $idempotencyKey]);
            if ($duplicate) {
                Model::commit();
                return ['id' => (int) $duplicate['id'], 'amendment_number' => (int) $duplicate['amendment_number'], 'status' => $duplicate['status'], 'duplicate' => true];
            }
            $baseVersion = self::ensureLegacyBaseline($contractId);
            $before = self::snapshot($contractId);
            $previewFingerprint = trim((string) ($input['preview_fingerprint'] ?? ''));
            if ($previewFingerprint === '' || !hash_equals(self::fingerprint($before), $previewFingerprint)) {
                throw new InvalidArgumentException('پیش‌نمایش لازم است و نسخه مالی درخواستی باید همان نسخه نمایش‌داده‌شده باشد.');
            }
            $schedule = self::normaliseProposedSchedule($contractId, $effectiveOn, $input['schedule'], $before['schedule']);
            $future = self::candidateSchedule($before['schedule'], $effectiveOn);
            self::validateProtectedSchedule($future, $schedule);
            $impact = self::impact($terms, $schedule, $future);
            $changes = self::changedTerms($before['terms'], $terms);
            if (!$changes && !$impact['schedule_changed']) throw new InvalidArgumentException('هیچ تغییر مالی مؤثری برای ثبت وجود ندارد.');
            $latest = Model::fetch('SELECT COALESCE(MAX(amendment_number), 0) AS n FROM contract_amendments WHERE contract_id = ?', [$contractId]);
            $number = (int) ($latest['n'] ?? 0) + 1;
            $proposed = ['terms' => $terms, 'future_schedule' => $schedule, 'effective_on' => $effectiveOn, 'calculation_version' => self::CALCULATION_VERSION];
            $fingerprint = self::fingerprint($before);
            $requestChanges = ['terms' => $changes, 'schedule_changed' => $impact['schedule_changed'], 'calculation_policy' => 'manual_schedule_no_formula_inference'];
            Model::execute(
                'INSERT INTO contract_amendments (contract_id, amendment_number, base_version_id, status, reason, effective_on, base_fingerprint, requested_changes_json, before_snapshot_json, proposed_snapshot_json, impact_snapshot_json, requested_by, requested_at, idempotency_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                [$contractId, $number, (int) $baseVersion['id'], 'pending_approval', $reason, $effectiveOn, $fingerprint,
                    self::json($requestChanges), self::json($before), self::json($proposed), self::json($impact), $actorId, $idempotencyKey]
            );
            $amendmentId = (int) Model::lastInsertId();
            self::event($amendmentId, $contractId, 'submitted', $actorId, 'draft', 'pending_approval', $reason, ['amendment_number' => $number, 'base_version_id' => (int) $baseVersion['id'], 'base_fingerprint' => $fingerprint, 'requested_changes' => $requestChanges]);
            self::audit('contract.amendment_submitted', $amendmentId, $contractId, $actorId, $before, $proposed, $reason);
            Model::commit();
            return ['id' => $amendmentId, 'amendment_number' => $number, 'status' => 'pending_approval', 'duplicate' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function approve($amendmentId, $actorId): array
    {
        self::requireSchema();
        Model::begin();
        try {
            $amendment = self::lockedAmendment((int) $amendmentId);
            if (($amendment['status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('این اصلاحیه در انتظار تأیید نیست.');
            if ((int) $amendment['requested_by'] === (int) $actorId) throw new InvalidArgumentException('ثبت‌کننده نمی‌تواند اصلاحیهٔ خودش را تأیید کند.');
            if (!self::baseStillCurrent($amendment)) {
                self::transitionConflict($amendment, (int) $actorId, 'نسخه پایه هنگام تأیید تغییر کرده است.');
                Model::commit();
                return ['status' => 'conflict'];
            }
            Model::execute("UPDATE contract_amendments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending_approval'", [(int) $actorId, (int) $amendmentId]);
            self::event((int) $amendmentId, (int) $amendment['contract_id'], 'approved', (int) $actorId, 'pending_approval', 'approved', null, ['base_version_id' => (int) $amendment['base_version_id']]);
            self::audit('contract.amendment_approved', (int) $amendmentId, (int) $amendment['contract_id'], (int) $actorId, null, self::decode((string) $amendment['proposed_snapshot_json']), 'تأیید داخلی اصلاحیه');
            Model::commit();
            return ['status' => 'approved'];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reject($amendmentId, $actorId, $reason): array
    {
        self::requireSchema();
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت رد اصلاحیه الزامی است.');
        Model::begin();
        try {
            $amendment = self::lockedAmendment((int) $amendmentId);
            if (($amendment['status'] ?? '') !== 'pending_approval') throw new InvalidArgumentException('فقط اصلاحیه در انتظار تأیید قابل رد است.');
            Model::execute("UPDATE contract_amendments SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ? AND status = 'pending_approval'", [(int) $actorId, $reason, (int) $amendmentId]);
            self::event((int) $amendmentId, (int) $amendment['contract_id'], 'rejected', (int) $actorId, 'pending_approval', 'rejected', $reason, []);
            self::audit('contract.amendment_rejected', (int) $amendmentId, (int) $amendment['contract_id'], (int) $actorId, null, ['status' => 'rejected'], $reason);
            Model::commit();
            return ['status' => 'rejected'];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function apply($amendmentId, $actorId): array
    {
        self::requireSchema();
        Model::begin();
        try {
            $amendment = self::lockedAmendment((int) $amendmentId);
            if (($amendment['status'] ?? '') === 'applied') {
                Model::commit();
                return ['status' => 'applied', 'version_id' => (int) $amendment['result_version_id'], 'duplicate' => true];
            }
            if (($amendment['status'] ?? '') !== 'approved' || empty($amendment['approved_by']) || empty($amendment['approved_at'])) {
                throw new InvalidArgumentException('اصلاحیه باید با اقدام صریح یک مدیر دیگر تأیید شود.');
            }
            if ((string) $amendment['effective_on'] > date('Y-m-d')) throw new InvalidArgumentException('تاریخ اثرگذاری اصلاحیه هنوز نرسیده است.');
            if (!self::baseStillCurrent($amendment)) {
                self::transitionConflict($amendment, (int) $actorId, 'نسخه پایه یا وضعیت مالی قرارداد پس از تأیید تغییر کرده است.');
                Model::commit();
                return ['status' => 'conflict'];
            }
            $before = self::snapshot((int) $amendment['contract_id']);
            Contract::applyApprovedAmendmentSchedule((int) $amendmentId);
            $after = self::snapshot((int) $amendment['contract_id']);
            $nextNumber = (int) Model::fetch('SELECT COALESCE(MAX(version_number), 0) AS n FROM contract_financial_versions WHERE contract_id = ? FOR UPDATE', [(int) $amendment['contract_id']])['n'] + 1;
            Model::execute('UPDATE contract_financial_versions SET is_current = 0 WHERE contract_id = ? AND is_current = 1', [(int) $amendment['contract_id']]);
            $versionId = self::insertVersion((int) $amendment['contract_id'], $nextNumber, (int) $amendment['base_version_id'], (int) $amendmentId, 'approved_internal_amendment_party_consent_unrecorded', 1, $after, (int) $actorId, (string) $amendment['effective_on']);
            $requester = Model::fetch('SELECT full_name FROM users WHERE id = ?', [(int) $amendment['requested_by']]);
            $approver = Model::fetch('SELECT full_name FROM users WHERE id = ?', [(int) $amendment['approved_by']]);
            $documentVersionId = ContractDocument::recordAmendmentDraftVersion([
                'contract_id' => (int) $amendment['contract_id'], 'amendment_number' => (int) $amendment['amendment_number'],
                'financial_version_id' => $versionId, 'requested_by' => (int) $amendment['requested_by'],
                'approved_by' => (int) $amendment['approved_by'], 'generated_by' => (int) $actorId,
                'requested_by_name' => (string) ($requester['full_name'] ?? ''), 'approved_by_name' => (string) ($approver['full_name'] ?? ''),
                'effective_on' => (string) $amendment['effective_on'], 'reason' => (string) $amendment['reason'],
                'before' => $before, 'after' => $after,
            ]);
            if ($documentVersionId !== null) {
                Model::execute('UPDATE contract_financial_versions SET document_version_id = ? WHERE id = ?', [$documentVersionId, $versionId]);
            }
            Model::execute("UPDATE contract_amendments SET status = 'applied', result_version_id = ?, document_version_id = ?, applied_by = ?, applied_at = NOW() WHERE id = ? AND status = 'approved'", [$versionId, $documentVersionId, (int) $actorId, (int) $amendmentId]);
            self::event((int) $amendmentId, (int) $amendment['contract_id'], 'version_created', (int) $actorId, null, null, (string) $amendment['reason'], ['version_id' => $versionId, 'document_version_id' => $documentVersionId, 'content_hash' => self::hashSnapshot($after)]);
            self::event((int) $amendmentId, (int) $amendment['contract_id'], 'applied', (int) $actorId, 'approved', 'applied', (string) $amendment['reason'], ['result_version_id' => $versionId, 'document_version_id' => $documentVersionId, 'content_hash' => self::hashSnapshot($after)]);
            self::audit('contract.amendment_applied', (int) $amendmentId, (int) $amendment['contract_id'], (int) $actorId, $before, $after, (string) $amendment['reason']);
            Model::commit();
            return ['status' => 'applied', 'version_id' => $versionId, 'duplicate' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function baseStillCurrent(array $amendment): bool
    {
        if (Model::db()->inTransaction()) {
            Model::fetchAll('SELECT id FROM installments WHERE contract_id = ? ORDER BY id FOR UPDATE', [(int) $amendment['contract_id']]);
        }
        $current = Model::fetch('SELECT id, content_hash FROM contract_financial_versions WHERE contract_id = ? AND is_current = 1 LIMIT 1', [(int) $amendment['contract_id']]);
        if (!$current || (int) $current['id'] !== (int) $amendment['base_version_id']) return false;
        $snapshot = self::snapshot((int) $amendment['contract_id']);
        return hash_equals((string) $amendment['base_fingerprint'], self::fingerprint($snapshot));
    }

    /** Read-only financial impact calculation bound to the current schedule fingerprint. */
    public static function preview(int $contractId, array $input): array
    {
        self::requireSchema();
        $contract = Model::fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
        if (in_array((string) $contract['status'], ['cancelled', 'completed', 'closed'], true)) throw new InvalidArgumentException('برای قرارداد لغو یا مختومه پیش‌نمایش اصلاحیه انجام نمی‌شود.');
        $effectiveOn = self::dateValue($input['effective_on'] ?? date('Y-m-d'), 'تاریخ اثرگذاری معتبر نیست.');
        if ($effectiveOn < date('Y-m-d')) throw new InvalidArgumentException('پیش‌نمایش اصلاحیه آینده‌نگر نمی‌تواند اثر گذشته‌نگر داشته باشد.');
        $terms = self::normaliseTerms(is_array($input['terms'] ?? null) ? $input['terms'] : $input);
        Contract::validateFinancialData($terms);
        if (!array_key_exists('schedule', $input) || !is_array($input['schedule'])) throw new InvalidArgumentException('برنامه اقساط پیشنهادی را کامل کنید.');
        $before = self::snapshot($contractId);
        $schedule = self::normaliseProposedSchedule($contractId, $effectiveOn, $input['schedule'], $before['schedule']);
        $current = self::candidateSchedule($before['schedule'], $effectiveOn);
        $impact = self::impact($terms, $schedule, $current);
        $changes = self::changedTerms($before['terms'], $terms);
        if (!$changes && !$impact['schedule_changed']) throw new InvalidArgumentException('هیچ تغییر مالی مؤثری در پیشنهاد نیست.');
        $activeVersion = Model::fetch('SELECT id, version_number FROM contract_financial_versions WHERE contract_id = ? AND is_current = 1 LIMIT 1', [$contractId]);
        return [
            'computed_at' => date('Y-m-d H:i:s'), 'calculation_version' => self::CALCULATION_VERSION,
            'base_version_id' => $activeVersion ? (int) $activeVersion['id'] : null,
            'base_version_number' => $activeVersion ? (int) $activeVersion['version_number'] : null,
            'base_fingerprint' => self::fingerprint($before), 'changed_terms' => $changes,
            'impact' => $impact, 'warnings' => [$impact['unavailable_metrics_reason'], 'این پیش‌نمایش تغییرات اقساط آینده را با مبالغ صریح واردشده مقایسه می‌کند و فرمول سود جدیدی اجرا نمی‌کند.'],
        ];
    }

    public static function cancel($amendmentId, $actorId, $reason): array
    {
        self::requireSchema();
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) throw new InvalidArgumentException('علت لغو اصلاحیه الزامی است.');
        Model::begin();
        try {
            $amendment = self::lockedAmendment((int) $amendmentId);
            if (!in_array((string) ($amendment['status'] ?? ''), ['pending_approval', 'approved', 'conflict'], true)) {
                throw new InvalidArgumentException('این اصلاحیه دیگر قابل لغو نیست.');
            }
            if ((int) $amendment['requested_by'] !== (int) $actorId) {
                throw new InvalidArgumentException('فقط درخواست‌کننده می‌تواند اصلاحیهٔ اعمال‌نشده را لغو کند.');
            }
            $oldStatus = (string) $amendment['status'];
            Model::execute("UPDATE contract_amendments SET status = 'cancelled' WHERE id = ?", [(int) $amendmentId]);
            self::event((int) $amendmentId, (int) $amendment['contract_id'], 'cancelled', (int) $actorId, $oldStatus, 'cancelled', $reason, []);
            self::audit('contract.amendment_cancelled', (int) $amendmentId, (int) $amendment['contract_id'], (int) $actorId, ['status' => $oldStatus], ['status' => 'cancelled'], $reason);
            Model::commit();
            return ['status' => 'cancelled'];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Invoked after payment/financial dependencies change to ensure pending reviews cannot be applied. */
    public static function requestInstallmentChange(int $installmentId, array $requested, int $actorId, string $reason, bool $void = false): array
    {
        $row = Model::fetch('SELECT i.*, c.id AS owning_contract_id FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?', [$installmentId]);
        if (!$row) throw new InvalidArgumentException('قسط پیدا نشد.');
        $contractId = (int) $row['owning_contract_id'];
        $effective = date('Y-m-d');
        $snapshot = self::snapshot($contractId);
        $schedule = [];
        $found = false;
        foreach (self::candidateSchedule($snapshot['schedule'], $effective) as $line) {
            if ((int) $line['installment_id'] === $installmentId) {
                $found = true;
                if (!$void) {
                    $line['due_date'] = (string) $requested['due_date'];
                    $line['base_amount'] = (int) $requested['base_amount'];
                    $line['remaining_amount'] = (int) $requested['base_amount'];
                }
                if (!$void || !empty($line['has_history'])) $schedule[] = $line;
            } else {
                $schedule[] = $line;
            }
        }
        if (!$found) throw new InvalidArgumentException('قسط گذشته، سفارشی یا دارای وضعیت غیرقابل اصلاح در اصلاحیهٔ آینده‌نگر قابل تغییر نیست.');
        $contract = Model::fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]);
        $terms = self::normaliseTerms($contract);
        if ($void) {
            $activeRegularCount = count(array_filter($snapshot['schedule'], static function ($candidate) {
                return (int) ($candidate['is_custom'] ?? 0) === 0 && ($candidate['status'] ?? '') !== 'cancelled';
            }));
            if ($activeRegularCount <= 1) throw new InvalidArgumentException('اصلاحیه نمی‌تواند آخرین قسط فعال قرارداد را حذف کند؛ قرارداد باید از گردش تسویه یا خاتمه عبور کند.');
            $terms['months'] = $activeRegularCount - 1;
        }
        $input = [
            'reason' => $reason,
            'effective_on' => $effective,
            'terms' => $terms,
            'schedule' => array_map(static function ($line) {
                return ['installment_id' => $line['installment_id'], 'due_date' => $line['due_date'], 'base_amount' => $line['base_amount']];
            }, $schedule),
            'idempotency_key' => 'installment-' . $installmentId . '-' . bin2hex(random_bytes(12)),
        ];
        $input['preview_fingerprint'] = self::fingerprint($snapshot);
        $result = self::request($contractId, $input, $actorId);
        return $result + ['mode' => 'approval_required'];
    }

    public static function snapshot(int $contractId): array
    {
        $contract = Model::fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
        $terms = self::normaliseTerms($contract);
        $subqueries = [];
        if (SchemaGuard::tableExists('payments') && SchemaGuard::columnsExist('payments', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM payments p WHERE p.installment_id = i.id) AS payment_count';
        }
        if (SchemaGuard::tableExists('payment_allocations') && SchemaGuard::columnsExist('payment_allocations', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM payment_allocations pa WHERE pa.installment_id = i.id) AS allocation_count';
        }
        if (SchemaGuard::tableExists('installment_change_requests') && SchemaGuard::columnsExist('installment_change_requests', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM installment_change_requests cr WHERE cr.installment_id = i.id) AS change_request_count';
        }
        if (SchemaGuard::tableExists('installment_voids') && SchemaGuard::columnsExist('installment_voids', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM installment_voids iv WHERE iv.installment_id = i.id) AS void_count';
        }
        if (SchemaGuard::tableExists('accounting_entries') && SchemaGuard::columnsExist('accounting_entries', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM accounting_entries ae WHERE ae.installment_id = i.id) AS accounting_count';
        }
        if (SchemaGuard::tableExists('accounting_ledger_entries') && SchemaGuard::columnsExist('accounting_ledger_entries', ['installment_id'])) {
            $subqueries[] = '(SELECT COUNT(*) FROM accounting_ledger_entries ale WHERE ale.installment_id = i.id) AS accounting_ledger_count';
        }
        foreach (['legal_cases', 'plugin_accounting_sales', 'plugin_accounting_commissions', 'accounting_ledger_entries'] as $contractDependencyTable) {
            if (SchemaGuard::tableExists($contractDependencyTable) && SchemaGuard::columnsExist($contractDependencyTable, ['contract_id'])) {
                $subqueries[] = '(SELECT COUNT(*) FROM `' . $contractDependencyTable . '` cd WHERE cd.contract_id = i.contract_id) AS contract_dep_' . $contractDependencyTable;
            }
        }
        $select = $subqueries ? ', ' . implode(', ', $subqueries) : '';
        $rows = Model::fetchAll('SELECT i.*' . $select . ' FROM installments i WHERE i.contract_id = ? ORDER BY i.installment_number, i.id', [$contractId]);
        $schedule = [];
        foreach ($rows as $row) {
            $dependencyCount = (int) ($row['payment_count'] ?? 0) + (int) ($row['allocation_count'] ?? 0)
                + (int) ($row['change_request_count'] ?? 0) + (int) ($row['void_count'] ?? 0)
                + (int) ($row['accounting_count'] ?? 0) + (int) ($row['accounting_ledger_count'] ?? 0)
                + (int) ($row['contract_dep_legal_cases'] ?? 0) + (int) ($row['contract_dep_plugin_accounting_sales'] ?? 0)
                + (int) ($row['contract_dep_plugin_accounting_commissions'] ?? 0) + (int) ($row['contract_dep_accounting_ledger_entries'] ?? 0);
            $row['installment_id'] = (int) $row['id'];
            $row['base_amount'] = normalize_money($row['base_amount'] ?? 0);
            $row['paid_amount'] = normalize_money($row['paid_amount'] ?? 0);
            $row['remaining_amount'] = normalize_money($row['remaining_amount'] ?? 0);
            $row['has_history'] = $dependencyCount > 0 || $row['paid_amount'] > 0 || ($row['status'] ?? '') === 'paid' || !empty($row['effective_settlement_at']);
            $row['dependency_count'] = $dependencyCount;
            $schedule[] = $row;
        }
        return ['terms' => $terms, 'schedule' => $schedule];
    }

    public static function editableFutureSchedule(int $contractId, string $effectiveOn): array
    {
        $snapshot = self::snapshot($contractId);
        return self::candidateSchedule($snapshot['schedule'], $effectiveOn);
    }

    public static function candidateSchedule(array $schedule, string $effectiveOn): array
    {
        return array_values(array_filter($schedule, static function ($row) use ($effectiveOn) {
            return (int) ($row['is_custom'] ?? 0) === 0
                && ($row['status'] ?? '') !== 'cancelled'
                && (string) ($row['due_date'] ?? '') >= $effectiveOn;
        }));
    }

    public static function normaliseTerms(array $input): array
    {
        $rateUnits = MoneyMath::rateUnits($input['monthly_interest_rate'] ?? 0);
        $interestType = (string) ($input['interest_type'] ?? 'simple');
        if ($rateUnits < 0) throw new InvalidArgumentException('نرخ سود ماهانه نمی‌تواند منفی باشد.');
        if (!in_array($interestType, ['simple', 'compound'], true)) throw new InvalidArgumentException('روش محاسبه سود معتبر نیست.');
        return [
            'principal_amount' => normalize_money($input['principal_amount'] ?? 0),
            'down_payment_amount' => normalize_money($input['down_payment_amount'] ?? 0),
            'monthly_interest_rate' => MoneyMath::rateLabel($rateUnits),
            'interest_type' => $interestType,
            'months' => max(1, (int) to_english_digits($input['months'] ?? 1)),
            'start_date' => self::dateValue($input['start_date'] ?? '', 'تاریخ شروع قرارداد معتبر نیست.'),
            'first_due_date' => self::dateValue($input['first_due_date'] ?? '', 'اولین سررسید معتبر نیست.'),
        ];
    }

    private static function normaliseProposedSchedule(int $contractId, string $effectiveOn, array $input, array $fullSchedule): array
    {
        $allowed = [];
        $allExisting = [];
        foreach ($fullSchedule as $row) {
            if ((int) ($row['is_custom'] ?? 0) === 0 && ($row['status'] ?? '') !== 'cancelled') $allExisting[(int) $row['id']] = $row;
        }
        foreach (self::candidateSchedule($fullSchedule, $effectiveOn) as $row) $allowed[(int) $row['id']] = $row;
        if (count($input) > 240) throw new InvalidArgumentException('برنامه پیشنهادی بیش از حد مجاز ردیف دارد.');
        $seen = [];
        $seenDates = [];
        $result = [];
        foreach ($input as $line) {
            if (!is_array($line)) continue;
            $installmentId = (int) ($line['installment_id'] ?? 0);
            $dueDate = self::dateValue($line['due_date'] ?? '', 'تاریخ سررسید پیشنهادی معتبر نیست.');
            $amount = normalize_money($line['base_amount'] ?? 0);
            if ($amount <= 0) throw new InvalidArgumentException('مبلغ قسط پیشنهادی باید مثبت باشد.');
            if ($installmentId > 0) {
                if (isset($seen[$installmentId]) || !isset($allExisting[$installmentId])) throw new InvalidArgumentException('قسط انتخاب‌شده به برنامه این قرارداد تعلق ندارد.');
                $existing = $allExisting[$installmentId];
                if (!isset($allowed[$installmentId])) {
                    if ($dueDate !== (string) $existing['due_date'] || $amount !== normalize_money($existing['base_amount'] ?? 0)) {
                        throw new InvalidArgumentException('قسطی که پیش از تاریخ اثرگذاری سررسید می‌شود باید عیناً و بدون تغییر حفظ شود.');
                    }
                    $seen[$installmentId] = true;
                    continue;
                }
                if ($dueDate < $effectiveOn) throw new InvalidArgumentException('مبلغ و تاریخ اقساط آینده باید معتبر باشند.');
                $seen[$installmentId] = true;
            } elseif ($dueDate < $effectiveOn) {
                throw new InvalidArgumentException('قسط جدید باید در تاریخ اثرگذاری یا پس از آن سررسید شود.');
            }
            if (isset($seenDates[$dueDate])) throw new InvalidArgumentException('در برنامه پیشنهادی، تاریخ سررسید تکراری وجود دارد.');
            $seenDates[$dueDate] = true;
            $result[] = ['installment_id' => $installmentId, 'due_date' => $dueDate, 'base_amount' => $amount];
        }
        usort($result, static function ($a, $b) { return [$a['due_date'], $a['installment_id']] <=> [$b['due_date'], $b['installment_id']]; });
        self::validateProtectedSchedule(array_values($allowed), $result);
        return $result;
    }

    private static function validateProtectedSchedule(array $current, array $proposed): void
    {
        $byId = [];
        foreach ($proposed as $line) if ((int) ($line['installment_id'] ?? 0) > 0) $byId[(int) $line['installment_id']] = $line;
        foreach ($current as $row) {
            if (empty($row['has_history'])) continue;
            $id = (int) $row['id'];
            $line = $byId[$id] ?? null;
            if (!$line || $line['due_date'] !== (string) $row['due_date'] || (int) $line['base_amount'] !== normalize_money($row['base_amount'] ?? 0)) {
                throw new InvalidArgumentException('قسط دارای پرداخت، تخصیص یا سابقه مالی باید عیناً با مبلغ و تاریخ فعلی در برنامه پیشنهادی حفظ شود.');
            }
        }
    }

    private static function impact(array $terms, array $proposed, array $current): array
    {
        $beforeTotal = 0;
        $currentById = [];
        foreach ($current as $row) {
            $currentById[(int) $row['id']] = $row;
            $beforeTotal += normalize_money($row['base_amount'] ?? 0);
        }
        $afterTotal = 0;
        $lines = [];
        $kept = [];
        foreach ($proposed as $line) {
            $afterTotal += (int) $line['base_amount'];
            $id = (int) $line['installment_id'];
            if ($id > 0) $kept[$id] = true;
            $old = $id > 0 ? ($currentById[$id] ?? null) : null;
            if (!$old || (string) $old['due_date'] !== $line['due_date'] || normalize_money($old['base_amount'] ?? 0) !== (int) $line['base_amount']) {
                $lines[] = ['installment_id' => $id, 'old_due_date' => $old['due_date'] ?? null, 'new_due_date' => $line['due_date'], 'old_amount' => $old ? normalize_money($old['base_amount'] ?? 0) : null, 'new_amount' => (int) $line['base_amount'], 'classification' => $old ? 'changed_future_obligation' : 'new_future_obligation'];
            }
        }
        foreach ($current as $row) {
            $id = (int) $row['id'];
            if (!isset($kept[$id])) $lines[] = ['installment_id' => $id, 'old_due_date' => (string) $row['due_date'], 'new_due_date' => null, 'old_amount' => normalize_money($row['base_amount'] ?? 0), 'new_amount' => null, 'classification' => 'superseded_future_obligation'];
        }
        return [
            'schedule_changed' => (bool) $lines,
            'method' => 'explicit_manual_schedule',
            'automatic_interest_recalculation' => false,
            'current_future_installments' => count($current),
            'proposed_future_installments' => count($proposed),
            'current_future_total' => $beforeTotal,
            'proposed_future_total' => $afterTotal,
            'future_total_delta' => $afterTotal - $beforeTotal,
            'remaining_principal' => null,
            'future_interest' => null,
            'unavailable_metrics_reason' => 'سیاست محاسبه سود آینده‌نگر در نسخه موجود تعریف نشده است؛ هیچ فرمول جدیدی حدس زده نشده است.',
            'lines' => $lines,
            'target_months' => (int) $terms['months'],
        ];
    }

    private static function changedTerms(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) $changed[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
        }
        return $changed;
    }

    private static function insertVersion(int $contractId, int $number, ?int $parentId, ?int $amendmentId, string $provenance, int $historyComplete, array $snapshot, ?int $actorId, string $effectiveFrom): int
    {
        $hash = self::hashSnapshot($snapshot);
        Model::execute('INSERT INTO contract_financial_versions (contract_id, version_number, parent_version_id, source_amendment_id, provenance, history_complete, is_current, terms_snapshot_json, schedule_snapshot_json, calculation_version, content_hash, created_by, effective_from, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())', [
            $contractId, $number, $parentId, $amendmentId, $provenance, $historyComplete,
            self::json($snapshot['terms']), self::json($snapshot['schedule']), self::CALCULATION_VERSION, $hash, $actorId, $effectiveFrom,
        ]);
        return (int) Model::lastInsertId();
    }

    private static function lockedAmendment(int $id): array
    {
        $row = Model::fetch('SELECT * FROM contract_amendments WHERE id = ? FOR UPDATE', [$id]);
        if (!$row) throw new InvalidArgumentException('اصلاحیه پیدا نشد.');
        Model::fetch('SELECT id FROM contracts WHERE id = ? FOR UPDATE', [(int) $row['contract_id']]);
        return $row;
    }

    private static function transitionConflict(array $amendment, int $actorId, string $reason): void
    {
        Model::execute("UPDATE contract_amendments SET status = 'conflict' WHERE id = ?", [(int) $amendment['id']]);
        self::event((int) $amendment['id'], (int) $amendment['contract_id'], 'conflict', $actorId, (string) $amendment['status'], 'conflict', $reason, ['base_version_id' => (int) $amendment['base_version_id']]);
        self::audit('contract.amendment_conflict', (int) $amendment['id'], (int) $amendment['contract_id'], $actorId, null, ['status' => 'conflict'], $reason);
    }

    private static function event(?int $amendmentId, int $contractId, string $action, ?int $actorId, ?string $oldStatus, ?string $newStatus, ?string $reason, array $metadata): void
    {
        if ($amendmentId === null) {
            // A baseline has no amendment foreign identity; retain its event under amendment_id 0.
            $amendmentId = 0;
        }
        Model::execute('INSERT INTO contract_amendment_history (amendment_id, contract_id, action, actor_id, old_status, new_status, reason, metadata_json, request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [$amendmentId, $contractId, $action, $actorId, $oldStatus, $newStatus, $reason, self::json($metadata), 'CAH-' . strtoupper(bin2hex(random_bytes(8)))]);
    }

    private static function audit(string $action, int $amendmentId, int $contractId, int $actorId, $old, $new, string $reason): void
    {
        if (!class_exists('AuditLog') || !SchemaGuard::tableExists('audit_logs')) throw new RuntimeException('ثبت AuditLog الزامی است، اما جدول ممیزی در دسترس نیست.');
        $contract = Model::fetch('SELECT customer_id FROM contracts WHERE id = ?', [$contractId]);
        AuditLog::record('contract', $action, 'contract_amendment', $amendmentId, [
            'actor_user_id' => $actorId,
            'contract_id' => $contractId,
            'customer_id' => (int) ($contract['customer_id'] ?? 0),
            'description' => $reason,
            'old_values' => $old,
            'new_values' => $new,
            'severity' => 'critical',
        ]);
    }

    private static function fingerprint(array $snapshot): string
    {
        $schedule = [];
        foreach ($snapshot['schedule'] as $row) {
            $schedule[] = [
                'id' => (int) $row['id'], 'number' => (int) $row['installment_number'], 'due_date' => (string) $row['due_date'],
                'base_amount' => normalize_money($row['base_amount'] ?? 0), 'paid_amount' => normalize_money($row['paid_amount'] ?? 0),
                'remaining_amount' => normalize_money($row['remaining_amount'] ?? 0), 'is_custom' => (int) ($row['is_custom'] ?? 0),
                'status' => in_array((string) ($row['status'] ?? ''), ['paid', 'cancelled'], true) ? (string) $row['status'] : 'open',
                'dependency_count' => (int) ($row['dependency_count'] ?? 0), 'effective_settlement_at' => $row['effective_settlement_at'] ?? null,
            ];
        }
        return self::hashSnapshot(['terms' => $snapshot['terms'], 'schedule' => $schedule]);
    }

    private static function hashSnapshot(array $snapshot): string
    {
        return hash('sha256', self::json($snapshot));
    }

    private static function json($value): string
    {
        return json_encode(self::canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function canonical($value)
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'canonical'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonical($item);
        return $value;
    }

    private static function decode(string $json): array
    {
        $value = json_decode($json, true);
        return is_array($value) ? $value : [];
    }

    private static function dateValue($value, string $message): string
    {
        $date = parse_jalali_date((string) $value) ?: trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException($message);
        return $date;
    }

    private static function schemaAvailable(): bool
    {
        return SchemaGuard::tableExists('contract_financial_versions')
            && SchemaGuard::tableExists('contract_amendments')
            && SchemaGuard::tableExists('contract_amendment_history');
    }

    public static function isAvailable(): bool
    {
        return self::schemaAvailable();
    }

    private static function requireSchema(): void
    {
        SchemaGuard::requireTables(array_merge(self::TABLES, ['audit_logs']));
    }
}
