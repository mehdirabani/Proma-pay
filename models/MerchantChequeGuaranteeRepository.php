<?php

declare(strict_types=1);

/** WooCommerce-only Sayadi guarantee snapshots, verification and custody events. */
final class MerchantChequeGuaranteeRepository
{
    public static function createForWooContract(MerchantAccessContext $context, int $contractId, ?int $actorUserId): array
    {
        $context->requireScope('guarantee:create');
        if ($contractId < 1 || ($actorUserId !== null && $actorUserId < 1)) {
            throw new InvalidArgumentException('merchant_guarantee_contract_invalid');
        }
        $guaranteeUuid = MerchantAuditLog::newUuid();

        Model::begin();
        try {
            self::lockActiveConnection($context);
            $connectionPolicy = self::lockPublishedConnectionPolicy($context, 'guarantee');
            $centralPolicy = self::lockPublishedCentralPolicy();
            $resolvedPolicy = MerchantGuaranteePolicy::resolve($connectionPolicy['document'], $centralPolicy);

            $link = Model::fetch(
                "SELECT id, contract_id, order_status FROM merchant_contract_links
                 WHERE contract_id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?
                 FOR UPDATE",
                [$contractId, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$link || $link['order_status'] !== 'contracted') {
                throw new DomainException('merchant_woocommerce_contract_link_unavailable');
            }
            $contract = Model::fetch('SELECT id, customer_id, first_due_date, status FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract || in_array((string) $contract['status'], ['cancelled', 'deleted'], true)) {
                throw new DomainException('merchant_guarantee_contract_unavailable');
            }
            $schedule = self::lockedSchedule($contractId);
            $snapshot = MerchantGuaranteeCalculator::calculate($schedule, $resolvedPolicy['multiplier_units']);
            if ($snapshot['first_installment_due_date'] !== (string) $contract['first_due_date']) {
                throw new DomainException('merchant_contract_first_due_date_schedule_mismatch');
            }

            Model::execute(
                'INSERT INTO merchant_cheque_guarantees
                    (guarantee_uuid, merchant_id, site_id, connection_id, contract_link_id, contract_id,
                     customer_id, policy_version_id, policy_type, central_policy_version_id, guarantee_type,
                     required_cheque_count, guarantee_base, scheduled_installments_toman,
                     multiplier_units, multiplier_scale, cheque_amount_rial,
                     first_installment_due_date, cheque_due_date, beneficiary_legal_name,
                     beneficiary_identifier, sayad_status, physical_status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $guaranteeUuid,
                    $context->merchantId(),
                    $context->siteId(),
                    $context->connectionId(),
                    (int) $link['id'],
                    $contractId,
                    (int) $contract['customer_id'],
                    (int) $connectionPolicy['id'],
                    'guarantee',
                    (int) $centralPolicy['id'],
                    'sayadi_cheque',
                    1,
                    'scheduled_installments',
                    $snapshot['scheduled_installments_toman'],
                    $snapshot['multiplier_units'],
                    $snapshot['multiplier_scale'],
                    $snapshot['cheque_amount_rial'],
                    $snapshot['first_installment_due_date'],
                    $snapshot['cheque_due_date'],
                    $resolvedPolicy['beneficiary_legal_name'],
                    $resolvedPolicy['beneficiary_identifier'],
                    'pending',
                    'pending',
                    $actorUserId,
                ]
            );
            $guaranteeId = (int) Model::lastInsertId();
            self::appendEvent($guaranteeId, $context, 'guarantee.created', $actorUserId, null, null, null, [
                'contract_id' => $contractId,
                'contract_link_id' => (int) $link['id'],
                'policy_version_id' => (int) $connectionPolicy['id'],
                'central_policy_version_id' => (int) $centralPolicy['id'],
                'scheduled_installments_toman' => $snapshot['scheduled_installments_toman'],
                'multiplier_units' => $snapshot['multiplier_units'],
                'cheque_amount_rial' => $snapshot['cheque_amount_rial'],
                'cheque_due_date' => $snapshot['cheque_due_date'],
            ]);
            MerchantAuditLog::append(
                $context->merchantId(),
                $context->siteId(),
                $context->connectionId(),
                $actorUserId,
                'guarantee.created',
                'guarantee',
                $guaranteeUuid,
                ['contract_id' => $contractId, 'cheque_amount_rial' => $snapshot['cheque_amount_rial']]
            );
            Model::commit();
            return array_merge([
                'id' => $guaranteeId,
                'guarantee_uuid' => $guaranteeUuid,
                'sayad_status' => 'pending',
                'physical_status' => 'pending',
                'beneficiary_legal_name' => $resolvedPolicy['beneficiary_legal_name'],
                'beneficiary_identifier' => $resolvedPolicy['beneficiary_identifier'],
            ], $snapshot);
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function refreshPendingSnapshot(MerchantAccessContext $context, string $guaranteeUuid): array
    {
        $context->requireScope('guarantee:write');
        Model::begin();
        try {
            $guarantee = self::lockScopedGuarantee($context, $guaranteeUuid);
            if ($guarantee['sayad_status'] !== 'pending') {
                throw new DomainException('merchant_guarantee_snapshot_locked');
            }
            $schedule = self::lockedSchedule((int) $guarantee['contract_id']);
            $snapshot = MerchantGuaranteeCalculator::calculate($schedule, (int) $guarantee['multiplier_units']);
            Model::execute(
                'UPDATE merchant_cheque_guarantees
                 SET scheduled_installments_toman = ?, cheque_amount_rial = ?,
                     first_installment_due_date = ?, cheque_due_date = ?
                 WHERE id = ? AND sayad_status = ?',
                [
                    $snapshot['scheduled_installments_toman'],
                    $snapshot['cheque_amount_rial'],
                    $snapshot['first_installment_due_date'],
                    $snapshot['cheque_due_date'],
                    (int) $guarantee['id'],
                    'pending',
                ]
            );
            self::appendEvent((int) $guarantee['id'], $context, 'guarantee.snapshot_refreshed', null, null, null, null, [
                'scheduled_installments_toman' => $snapshot['scheduled_installments_toman'],
                'cheque_amount_rial' => $snapshot['cheque_amount_rial'],
                'first_installment_due_date' => $snapshot['first_installment_due_date'],
            ]);
            Model::commit();
            return $snapshot;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function recordSayadiRegistration(
        MerchantAccessContext $context,
        string $guaranteeUuid,
        string $chequeNumber,
        string $bankName,
        string $chequeDueDate,
        string $method,
        string $evidenceReference,
        string $evidenceSha256,
        ?int $actorUserId
    ): bool {
        $context->requireScope('guarantee:write');
        $chequeNumber = self::normalizeChequeNumber($chequeNumber);
        $bankName = self::requiredText($bankName, 100, 'merchant_cheque_bank_invalid');
        $evidence = self::validateEvidence($method, $evidenceReference, $evidenceSha256);
        if ($actorUserId !== null && $actorUserId < 1) {
            throw new InvalidArgumentException('merchant_guarantee_actor_invalid');
        }

        Model::begin();
        try {
            $guarantee = self::lockScopedGuarantee($context, $guaranteeUuid);
            if ($guarantee['sayad_status'] !== 'pending') {
                Model::commit();
                return false;
            }
            $schedule = self::lockedSchedule((int) $guarantee['contract_id']);
            $snapshot = MerchantGuaranteeCalculator::calculate($schedule, (int) $guarantee['multiplier_units']);
            self::requireScheduleMatches($guarantee, $snapshot);
            $chequeDueDate = self::normalizeDate($chequeDueDate);
            if ($chequeDueDate !== $snapshot['first_installment_due_date']) {
                throw new DomainException('merchant_cheque_due_date_must_match_first_installment');
            }

            Model::execute(
                "UPDATE merchant_cheque_guarantees
                 SET cheque_number = ?, bank_name = ?, sayad_status = 'registered',
                     registration_method = ?, registration_reference = ?,
                     registration_evidence_sha256 = ?, registered_at = NOW()
                 WHERE id = ? AND sayad_status = 'pending'",
                [$chequeNumber, $bankName, $evidence['method'], $evidence['reference'], $evidence['sha256'], (int) $guarantee['id']]
            );
            self::appendEvent(
                (int) $guarantee['id'], $context, 'guarantee.sayadi_registered', $actorUserId,
                $evidence['method'], $evidence['reference'], $evidence['sha256'],
                ['cheque_due_date' => $chequeDueDate, 'bank_name' => $bankName]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $actorUserId,
                'guarantee.sayadi_registered', 'guarantee', (string) $guarantee['guarantee_uuid'],
                ['cheque_due_date' => $chequeDueDate, 'verification_method' => $evidence['method']]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function confirmBeneficiary(
        MerchantAccessContext $context,
        string $guaranteeUuid,
        string $method,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        self::requireCentralReviewer($reviewerUserId, 'merchant.guarantee.verify');
        $evidence = self::validateEvidence($method, $evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            $guarantee = self::lockScopedGuarantee($context, $guaranteeUuid);
            if ($guarantee['sayad_status'] === 'confirmed') {
                Model::commit();
                return false;
            }
            if ($guarantee['sayad_status'] !== 'registered') {
                throw new DomainException('merchant_cheque_not_registered');
            }
            $schedule = self::lockedSchedule((int) $guarantee['contract_id']);
            $snapshot = MerchantGuaranteeCalculator::calculate($schedule, (int) $guarantee['multiplier_units']);
            self::requireScheduleMatches($guarantee, $snapshot);
            if ((string) $guarantee['cheque_due_date'] !== $snapshot['first_installment_due_date']) {
                throw new DomainException('merchant_cheque_due_date_must_match_first_installment');
            }
            Model::execute(
                "UPDATE merchant_cheque_guarantees
                 SET sayad_status = 'confirmed', confirmation_method = ?, confirmation_reference = ?,
                     confirmation_evidence_sha256 = ?, confirmed_by = ?, confirmed_at = NOW()
                 WHERE id = ? AND sayad_status = 'registered'",
                [$evidence['method'], $evidence['reference'], $evidence['sha256'], $reviewerUserId, (int) $guarantee['id']]
            );
            self::appendEvent(
                (int) $guarantee['id'], $context, 'guarantee.beneficiary_confirmed', $reviewerUserId,
                $evidence['method'], $evidence['reference'], $evidence['sha256'],
                ['first_installment_due_date' => $snapshot['first_installment_due_date']]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'guarantee.beneficiary_confirmed', 'guarantee', (string) $guarantee['guarantee_uuid'],
                ['first_installment_due_date' => $snapshot['first_installment_due_date'], 'verification_method' => $evidence['method']]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function assertReadyForContractActivation(MerchantAccessContext $context, int $contractId): void
    {
        $context->requireScope('contract:activate');
        if ($contractId < 1) {
            throw new InvalidArgumentException('merchant_contract_id_invalid');
        }
        self::lockActiveConnection($context);
        $guarantee = Model::fetch(
            "SELECT g.* FROM merchant_cheque_guarantees g
             INNER JOIN merchant_contract_links l ON l.id = g.contract_link_id
             WHERE g.contract_id = ? AND l.contract_id = ?
               AND g.merchant_id = ? AND g.site_id = ? AND g.connection_id = ?
               AND l.merchant_id = g.merchant_id AND l.site_id = g.site_id AND l.connection_id = g.connection_id
               AND l.order_status = 'contracted'
             LIMIT 1 FOR UPDATE",
            [$contractId, $contractId, $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$guarantee || $guarantee['sayad_status'] !== 'confirmed'
            || $guarantee['guarantee_type'] !== 'sayadi_cheque'
            || (int) $guarantee['required_cheque_count'] !== 1) {
            throw new DomainException('merchant_cheque_confirmation_required_before_activation');
        }
        $schedule = self::lockedSchedule($contractId);
        $snapshot = MerchantGuaranteeCalculator::calculate($schedule, (int) $guarantee['multiplier_units']);
        self::requireScheduleMatches($guarantee, $snapshot);
        if ((string) $guarantee['cheque_due_date'] !== $snapshot['first_installment_due_date']) {
            throw new DomainException('merchant_cheque_due_date_must_match_first_installment');
        }
    }

    /** Call from the WooCommerce contract edit flow before a schedule change. */
    public static function assertScheduleDateChangeAllowed(int $contractId, string $newFirstDueDate): void
    {
        if ($contractId < 1) {
            return;
        }
        $newFirstDueDate = self::normalizeDate($newFirstDueDate);
        $guarantee = Model::fetch(
            'SELECT g.sayad_status, g.cheque_due_date, g.first_installment_due_date
             FROM merchant_cheque_guarantees g
             INNER JOIN merchant_contract_links l ON l.id = g.contract_link_id
             WHERE l.contract_id = ? LIMIT 1',
            [$contractId]
        );
        if (!$guarantee || $newFirstDueDate === (string) $guarantee['first_installment_due_date']) {
            return;
        }
        if ($guarantee['sayad_status'] === 'confirmed') {
            throw new DomainException('merchant_guarantee_amendment_required');
        }
        if ($guarantee['sayad_status'] === 'registered') {
            throw new DomainException('merchant_guarantee_reregistration_required');
        }
    }

    /** Lock the Woo guarantee before Contract::updateContract locks the contract and installments. */
    public static function lockForContractScheduleEdit(int $contractId): void
    {
        if ($contractId < 1) {
            return;
        }
        Model::fetch(
            'SELECT g.id FROM merchant_cheque_guarantees g
             INNER JOIN merchant_contract_links l ON l.id = g.contract_link_id
             WHERE l.contract_id = ? FOR UPDATE',
            [$contractId]
        );
    }

    /** Call after a contract edit has regenerated installments, inside that edit transaction. */
    public static function assertCurrentScheduleMatchesConfirmedGuarantee(int $contractId): void
    {
        if ($contractId < 1) {
            return;
        }
        $guarantee = Model::fetch(
            'SELECT g.* FROM merchant_cheque_guarantees g
             INNER JOIN merchant_contract_links l ON l.id = g.contract_link_id
             WHERE l.contract_id = ? LIMIT 1',
            [$contractId]
        );
        if (!$guarantee || !in_array($guarantee['sayad_status'], ['registered', 'confirmed'], true)) {
            return;
        }
        $snapshot = MerchantGuaranteeCalculator::calculate(
            self::lockedSchedule($contractId),
            (int) $guarantee['multiplier_units']
        );
        try {
            self::requireScheduleMatches($guarantee, $snapshot);
        } catch (DomainException $e) {
            if ($guarantee['sayad_status'] === 'confirmed') {
                throw new DomainException('merchant_guarantee_amendment_required', 0, $e);
            }
            throw new DomainException('merchant_guarantee_reregistration_required', 0, $e);
        }
    }

    public static function updatePhysicalStatus(
        MerchantAccessContext $context,
        string $guaranteeUuid,
        string $newStatus,
        string $evidenceReference,
        string $evidenceSha256,
        int $actorUserId
    ): bool {
        self::requireCentralReviewer($actorUserId, 'merchant.guarantee.manage');
        $newStatus = strtolower(trim($newStatus));
        if (!in_array($newStatus, ['received', 'returned', 'released', 'legal_followup'], true)) {
            throw new InvalidArgumentException('merchant_guarantee_physical_status_invalid');
        }
        $evidence = self::validateEvidence('manual_documented', $evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            $guarantee = self::lockScopedGuarantee($context, $guaranteeUuid);
            $contract = Model::fetch('SELECT status FROM contracts WHERE id = ? FOR UPDATE', [(int) $guarantee['contract_id']]);
            if (!$contract) {
                throw new DomainException('merchant_guarantee_contract_unavailable');
            }
            $current = (string) $guarantee['physical_status'];
            $allowed = [
                'pending' => ['received'],
                'received' => ['returned', 'released', 'legal_followup'],
                'legal_followup' => ['returned', 'released'],
                'returned' => [],
                'released' => [],
            ];
            if (!in_array($newStatus, $allowed[$current] ?? [], true)) {
                throw new DomainException('merchant_guarantee_physical_transition_invalid');
            }
            if ($newStatus === 'received' && $guarantee['sayad_status'] !== 'confirmed') {
                throw new DomainException('merchant_cheque_confirmation_required_before_receipt');
            }
            if ($newStatus === 'released' && !in_array($contract['status'], ['closed', 'completed'], true)) {
                throw new DomainException('merchant_cheque_release_requires_completed_contract');
            }
            if ($newStatus === 'returned' && $contract['status'] !== 'cancelled') {
                throw new DomainException('merchant_cheque_return_requires_cancelled_contract');
            }
            Model::execute(
                'UPDATE merchant_cheque_guarantees SET physical_status = ? WHERE id = ?',
                [$newStatus, (int) $guarantee['id']]
            );
            self::appendEvent(
                (int) $guarantee['id'], $context, 'guarantee.physical_' . $newStatus, $actorUserId,
                $evidence['method'], $evidence['reference'], $evidence['sha256'],
                ['previous_status' => $current, 'new_status' => $newStatus]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $actorUserId,
                'guarantee.physical_' . $newStatus, 'guarantee', (string) $guarantee['guarantee_uuid'],
                ['previous_status' => $current, 'new_status' => $newStatus]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function lockActiveConnection(MerchantAccessContext $context): void
    {
        $row = Model::fetch(
            "SELECT c.id FROM merchant_connections c
             INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
             INNER JOIN merchant_accounts m ON m.id = c.merchant_id
             WHERE c.id = ? AND c.merchant_id = ? AND c.site_id = ?
               AND c.status = 'active' AND c.emergency_disabled = 0
               AND s.status = 'active' AND s.verification_status = 'verified'
               AND m.status = 'active'
             FOR UPDATE",
            [$context->connectionId(), $context->merchantId(), $context->siteId()]
        );
        if (!$row) {
            throw new DomainException('merchant_connection_not_available');
        }
    }

    private static function lockPublishedConnectionPolicy(MerchantAccessContext $context, string $type): array
    {
        $row = Model::fetch(
            "SELECT p.id, p.policy_json, p.content_sha256
             FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p
               ON p.id = h.policy_version_id AND p.connection_id = h.connection_id AND p.policy_type = h.policy_type
             WHERE h.connection_id = ? AND h.policy_type = ? AND p.status = 'published'
             FOR UPDATE",
            [$context->connectionId(), $type]
        );
        if (!$row || !hash_equals((string) $row['content_sha256'], hash('sha256', (string) $row['policy_json']))) {
            throw new DomainException('merchant_guarantee_policy_unavailable');
        }
        try {
            $document = json_decode((string) $row['policy_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('merchant_guarantee_policy_invalid', 0, $e);
        }
        if (!is_array($document)) {
            throw new DomainException('merchant_guarantee_policy_invalid');
        }
        $row['document'] = $document;
        return $row;
    }

    private static function lockPublishedCentralPolicy(): array
    {
        $row = Model::fetch(
            "SELECT p.* FROM merchant_central_guarantee_policy_head h
             INNER JOIN merchant_central_guarantee_policy_versions p ON p.id = h.policy_version_id
             WHERE h.singleton_id = 1 AND p.status = 'published' AND p.enabled = 1
             FOR UPDATE"
        );
        if (!$row) {
            throw new DomainException('merchant_central_guarantee_policy_unavailable');
        }
        return $row;
    }

    private static function lockScopedGuarantee(MerchantAccessContext $context, string $guaranteeUuid): array
    {
        $guaranteeUuid = self::normalizeUuid($guaranteeUuid);
        $row = Model::fetch(
            'SELECT g.* FROM merchant_cheque_guarantees g
             INNER JOIN merchant_contract_links l ON l.id = g.contract_link_id
             WHERE g.guarantee_uuid = ? AND g.merchant_id = ? AND g.site_id = ? AND g.connection_id = ?
               AND l.merchant_id = g.merchant_id AND l.site_id = g.site_id AND l.connection_id = g.connection_id
             FOR UPDATE',
            [$guaranteeUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_guarantee_not_found');
        }
        return $row;
    }

    private static function lockedSchedule(int $contractId): array
    {
        $rows = Model::fetchAll(
            "SELECT installment_number, due_date, base_amount AS amount
             FROM installments
             WHERE contract_id = ? AND status NOT IN ('cancelled', 'deleted')
             ORDER BY installment_number ASC FOR UPDATE",
            [$contractId]
        );
        return $rows;
    }

    private static function requireScheduleMatches(array $guarantee, array $snapshot): void
    {
        if ((int) $guarantee['scheduled_installments_toman'] !== $snapshot['scheduled_installments_toman']
            || (string) $guarantee['first_installment_due_date'] !== $snapshot['first_installment_due_date']
            || (string) $guarantee['cheque_due_date'] !== $snapshot['first_installment_due_date']
            || (int) $guarantee['cheque_amount_rial'] !== $snapshot['cheque_amount_rial']) {
            throw new DomainException('merchant_guarantee_schedule_changed_recheck_required');
        }
    }

    private static function appendEvent(
        int $guaranteeId,
        MerchantAccessContext $context,
        string $eventType,
        ?int $actorUserId,
        ?string $method,
        ?string $reference,
        ?string $sha256,
        array $metadata
    ): void {
        Model::execute(
            'INSERT INTO merchant_cheque_guarantee_events
                (guarantee_id, merchant_id, site_id, connection_id, event_type, actor_user_id,
                 verification_method, evidence_reference, evidence_sha256, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $guaranteeId,
                $context->merchantId(),
                $context->siteId(),
                $context->connectionId(),
                $eventType,
                $actorUserId,
                $method,
                $reference,
                $sha256,
                MerchantAuditMetadata::encode($metadata),
            ]
        );
    }

    private static function requireCentralReviewer(int $actorUserId, string $permission): void
    {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId) {
            throw new DomainException('merchant_guarantee_reviewer_required');
        }
        PluginManager::requirePermission($permission);
    }

    private static function validateEvidence(string $method, string $reference, string $sha256): array
    {
        if (!in_array($method, ['authorized_provider', 'manual_documented'], true)) {
            throw new InvalidArgumentException('merchant_guarantee_verification_method_invalid');
        }
        $reference = trim($reference);
        $sha256 = strtolower(trim($sha256));
        if ($reference === '' || strlen($reference) > 190
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $reference)
            || !preg_match('/^[0-9a-f]{64}$/D', $sha256)) {
            throw new InvalidArgumentException('merchant_guarantee_evidence_invalid');
        }
        return ['method' => $method, 'reference' => $reference, 'sha256' => $sha256];
    }

    private static function normalizeChequeNumber(string $value): string
    {
        $number = strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        if (!preg_match('/^\d{16}$/D', $number)) {
            throw new InvalidArgumentException('merchant_sayadi_identifier_invalid');
        }
        return $number;
    }

    private static function requiredText($value, int $maxCharacters, string $error): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($error);
        }
        $value = trim($value);
        $count = preg_match_all('/./us', $value);
        if ($value === '' || $count === false || $count > $maxCharacters
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function normalizeDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('merchant_cheque_due_date_invalid');
        }
        return $value;
    }

    private static function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('merchant_guarantee_uuid_invalid');
        }
        return $value;
    }
}
