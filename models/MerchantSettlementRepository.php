<?php

declare(strict_types=1);

/** Scoped, evidence-gated seller settlement obligations and payout batches. */
final class MerchantSettlementRepository
{
    public static function createPendingObligation(
        MerchantAccessContext $context,
        int $contractLinkId,
        int $cashProductPriceToman,
        int $claimedDownPaymentToman,
        string $cashPriceEvidenceReference,
        string $cashPriceEvidenceSha256,
        ?int $actorUserId
    ): array {
        $context->requireScope('settlement:write');
        if ($contractLinkId < 1 || ($actorUserId !== null && $actorUserId < 1)
            || $cashProductPriceToman < 1 || $claimedDownPaymentToman < 0
            || $claimedDownPaymentToman > $cashProductPriceToman) {
            throw new InvalidArgumentException('merchant_settlement_obligation_invalid');
        }
        self::validateEvidence($cashPriceEvidenceReference, $cashPriceEvidenceSha256);
        $uuid = MerchantAuditLog::newUuid();

        Model::begin();
        try {
            self::lockActiveConnection($context);
            $policyRow = self::lockPublishedPolicy($context);
            $policy = self::decodePolicy($policyRow);
            $link = Model::fetch(
                "SELECT l.id, l.external_order_id, l.contract_id, c.status AS contract_status
                 FROM merchant_contract_links l
                 INNER JOIN contracts c ON c.id = l.contract_id
                 WHERE l.id = ? AND l.merchant_id = ? AND l.site_id = ? AND l.connection_id = ?
                   AND l.order_status = 'contracted'
                 FOR UPDATE",
                [$contractLinkId, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$link || in_array((string) $link['contract_status'], ['cancelled', 'deleted'], true)) {
                throw new DomainException('merchant_settlement_order_unavailable');
            }
            $existing = Model::fetch(
                'SELECT obligation_uuid, cash_product_price_toman, claimed_down_payment_toman,
                        cash_price_evidence_sha256, status
                 FROM merchant_settlement_obligations WHERE contract_link_id = ? FOR UPDATE',
                [$contractLinkId]
            );
            if ($existing) {
                if ((int) $existing['cash_product_price_toman'] !== $cashProductPriceToman
                    || (int) $existing['claimed_down_payment_toman'] !== $claimedDownPaymentToman
                    || !hash_equals((string) $existing['cash_price_evidence_sha256'], strtolower($cashPriceEvidenceSha256))) {
                    throw new DomainException('merchant_settlement_obligation_conflict');
                }
                Model::commit();
                return [
                    'obligation_uuid' => (string) $existing['obligation_uuid'],
                    'status' => (string) $existing['status'],
                    'idempotent' => true,
                ];
            }

            $fundingAccountId = null;
            if ($policy['funding_account_uuid'] !== null) {
                $account = Model::fetch(
                    'SELECT id FROM merchant_liquidity_accounts WHERE account_uuid = ? LIMIT 1',
                    [$policy['funding_account_uuid']]
                );
                if (!$account) {
                    throw new DomainException('merchant_settlement_funding_account_unavailable');
                }
                $fundingAccountId = (int) $account['id'];
            }
            Model::execute(
                'INSERT INTO merchant_settlement_obligations
                    (obligation_uuid, merchant_id, site_id, connection_id, contract_link_id, contract_id,
                     settlement_policy_version_id, policy_type, funding_account_id, external_order_id,
                     cash_product_price_toman, claimed_down_payment_toman, verified_down_payment_toman,
                     gross_receivable_toman, contractual_deductions_toman, approved_adjustments_toman,
                     net_settlement_toman, cash_price_evidence_reference, cash_price_evidence_sha256,
                     legal_contract_reference, legal_approval_reference, receivable_ownership_model,
                     status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuid, $context->merchantId(), $context->siteId(), $context->connectionId(),
                    $contractLinkId, (int) $link['contract_id'], (int) $policyRow['id'], 'settlement',
                    $fundingAccountId, (string) $link['external_order_id'], $cashProductPriceToman,
                    $claimedDownPaymentToman, trim($cashPriceEvidenceReference), strtolower($cashPriceEvidenceSha256),
                    $policy['legal_contract_reference'], $policy['legal_approval_reference'],
                    $policy['receivable_ownership_model'], 'pending', $actorUserId,
                ]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $actorUserId,
                'settlement.obligation_created', 'settlement_obligation', $uuid,
                ['contract_link_id' => $contractLinkId, 'cash_product_price_toman' => $cashProductPriceToman]
            );
            Model::commit();
            return ['obligation_uuid' => $uuid, 'status' => 'pending', 'idempotent' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function verifyDownPayment(
        MerchantAccessContext $context,
        string $obligationUuid,
        int $verifiedAmountToman,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        $context->requireScope('settlement:review');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.payment.verify');
        if ($verifiedAmountToman < 0) {
            throw new InvalidArgumentException('merchant_settlement_down_payment_invalid');
        }
        self::validateEvidence($evidenceReference, $evidenceSha256);
        $obligationUuid = self::normalizeUuid($obligationUuid);

        Model::begin();
        try {
            self::lockActiveConnection($context);
            $obligation = self::lockObligation($context, $obligationUuid);
            self::assertEditable($obligation);
            if ($verifiedAmountToman > (int) $obligation['cash_product_price_toman']) {
                throw new DomainException('merchant_settlement_down_payment_exceeds_cash_price');
            }
            if ($obligation['down_payment_status'] === 'verified') {
                $same = hash_equals((string) $obligation['down_payment_evidence_sha256'], strtolower($evidenceSha256))
                    && (int) $obligation['verified_down_payment_toman'] === $verifiedAmountToman;
                if (!$same) {
                    throw new DomainException('merchant_settlement_down_payment_already_verified');
                }
                Model::commit();
                return true;
            }
            $calculation = MerchantSettlementCalculator::calculateReceivable(
                (int) $obligation['cash_product_price_toman'], $verifiedAmountToman,
                (int) $obligation['contractual_deductions_toman'], (int) $obligation['approved_adjustments_toman']
            );
            Model::execute(
                "UPDATE merchant_settlement_obligations
                 SET verified_down_payment_toman = ?, gross_receivable_toman = ?, net_settlement_toman = ?,
                     down_payment_status = 'verified', down_payment_evidence_reference = ?,
                     down_payment_evidence_sha256 = ?, down_payment_verified_by = ?, down_payment_verified_at = NOW(),
                     status = 'pending', eligible_at = NULL, settlement_due_date = NULL
                 WHERE id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?",
                [
                    $verifiedAmountToman, $calculation['gross_receivable_toman'], $calculation['net_settlement_toman'],
                    trim($evidenceReference), strtolower($evidenceSha256), $reviewerUserId, (int) $obligation['id'],
                    $context->merchantId(), $context->siteId(), $context->connectionId(),
                ]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.down_payment_verified', 'settlement_obligation', $obligationUuid,
                ['verified_down_payment_toman' => $verifiedAmountToman, 'net_settlement_toman' => $calculation['net_settlement_toman']]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** A proposed adjustment blocks eligibility until an independent reviewer resolves it. */
    public static function requestAdjustment(
        MerchantAccessContext $context,
        string $adjustmentUuid,
        string $obligationUuid,
        string $kind,
        int $amountToman,
        ?string $deductionCode,
        string $legalContractReference,
        string $evidenceReference,
        string $evidenceSha256,
        ?int $requesterUserId
    ): array {
        $context->requireScope('settlement:write');
        self::assertRequestActor($context, $requesterUserId, 'settlement.adjustment.request');
        $adjustmentUuid = self::normalizeUuid($adjustmentUuid);
        $obligationUuid = self::normalizeUuid($obligationUuid);
        if (!in_array($kind, ['contractual_deduction', 'approved_credit', 'approved_debit'], true)
            || $amountToman < 1 || ($requesterUserId !== null && $requesterUserId < 1)) {
            throw new InvalidArgumentException('merchant_settlement_adjustment_invalid');
        }
        $legalContractReference = self::requiredReference($legalContractReference);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        $deductionCode = $deductionCode !== null ? trim($deductionCode) : null;
        if ($kind === 'contractual_deduction'
            && ($deductionCode === null || !preg_match('/^[a-z][a-z0-9_.-]{0,59}$/D', $deductionCode))) {
            throw new InvalidArgumentException('merchant_settlement_deduction_code_invalid');
        }
        if ($kind !== 'contractual_deduction' && $deductionCode !== null) {
            throw new InvalidArgumentException('merchant_settlement_deduction_code_invalid');
        }

        Model::begin();
        try {
            self::lockActiveConnection($context);
            $obligation = self::lockObligation($context, $obligationUuid);
            self::assertEditable($obligation);
            if ($kind === 'contractual_deduction') {
                $policyRow = Model::fetch(
                    "SELECT policy_json FROM merchant_policy_versions
                     WHERE id = ? AND connection_id = ? AND policy_type = 'settlement'",
                    [(int) $obligation['settlement_policy_version_id'], $context->connectionId()]
                );
                if (!$policyRow) {
                    throw new DomainException('merchant_settlement_policy_unavailable');
                }
                $policy = self::decodePolicy($policyRow);
                if (!in_array($deductionCode, $policy['allowed_deduction_codes'], true)) {
                    throw new DomainException('merchant_settlement_deduction_code_not_allowed');
                }
            }
            $existing = Model::fetch(
                'SELECT adjustment_uuid, obligation_id, adjustment_kind, deduction_code, amount_toman,
                        legal_contract_reference, evidence_sha256, status
                 FROM merchant_settlement_adjustments WHERE adjustment_uuid = ? FOR UPDATE',
                [$adjustmentUuid]
            );
            if ($existing) {
                $same = (int) $existing['obligation_id'] === (int) $obligation['id']
                    && (string) $existing['adjustment_kind'] === $kind
                    && $existing['deduction_code'] === $deductionCode
                    && (int) $existing['amount_toman'] === $amountToman
                    && (string) $existing['legal_contract_reference'] === $legalContractReference
                    && hash_equals((string) $existing['evidence_sha256'], strtolower($evidenceSha256));
                if (!$same) {
                    throw new DomainException('merchant_settlement_adjustment_idempotency_conflict');
                }
                Model::commit();
                return ['adjustment_uuid' => $adjustmentUuid, 'status' => (string) $existing['status'], 'idempotent' => true];
            }
            Model::execute(
                'INSERT INTO merchant_settlement_adjustments
                    (adjustment_uuid,obligation_id,merchant_id,site_id,connection_id,adjustment_kind,deduction_code,
                     amount_toman,legal_contract_reference,evidence_reference,evidence_sha256,requested_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $adjustmentUuid, (int) $obligation['id'], $context->merchantId(), $context->siteId(),
                    $context->connectionId(), $kind, $deductionCode, $amountToman, $legalContractReference,
                    trim($evidenceReference), strtolower($evidenceSha256), $requesterUserId,
                ]
            );
            if ($obligation['status'] === 'eligible') {
                Model::execute(
                    "UPDATE merchant_settlement_obligations
                     SET status = 'pending', eligible_at = NULL, settlement_due_date = NULL
                     WHERE id = ?",
                    [(int) $obligation['id']]
                );
            }
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $requesterUserId,
                'settlement.adjustment_requested', 'settlement_adjustment', $adjustmentUuid,
                ['obligation_uuid' => $obligationUuid, 'kind' => $kind, 'amount_toman' => $amountToman]
            );
            Model::commit();
            return ['adjustment_uuid' => $adjustmentUuid, 'status' => 'pending', 'idempotent' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reviewAdjustment(
        MerchantAccessContext $context,
        string $adjustmentUuid,
        bool $approve,
        string $reviewEvidenceReference,
        string $reviewEvidenceSha256,
        int $reviewerUserId
    ): string {
        $context->requireScope('settlement:review');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.adjustment.review');
        $adjustmentUuid = self::normalizeUuid($adjustmentUuid);
        self::validateEvidence($reviewEvidenceReference, $reviewEvidenceSha256);
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $adjustment = Model::fetch(
                'SELECT a.*, o.obligation_uuid, o.status AS obligation_status,
                        o.cash_product_price_toman, o.verified_down_payment_toman,
                        o.contractual_deductions_toman, o.approved_adjustments_toman
                 FROM merchant_settlement_adjustments a
                 INNER JOIN merchant_settlement_obligations o ON o.id = a.obligation_id
                 WHERE a.adjustment_uuid = ? AND a.merchant_id = ? AND a.site_id = ? AND a.connection_id = ?
                 FOR UPDATE',
                [$adjustmentUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$adjustment || $adjustment['status'] !== 'pending') {
                throw new DomainException('merchant_settlement_adjustment_not_reviewable');
            }
            if ($adjustment['requested_by'] !== null && (int) $adjustment['requested_by'] === $reviewerUserId) {
                throw new DomainException('merchant_settlement_adjustment_dual_control_required');
            }
            if (!in_array((string) $adjustment['obligation_status'], ['pending', 'eligible', 'suspended'], true)) {
                throw new DomainException('merchant_settlement_obligation_locked');
            }
            if (!$approve) {
                Model::execute(
                    "UPDATE merchant_settlement_adjustments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                     WHERE id = ? AND status = 'pending'",
                    [$reviewerUserId, (int) $adjustment['id']]
                );
                MerchantAuditLog::append(
                    $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                    'settlement.adjustment_rejected', 'settlement_adjustment', $adjustmentUuid,
                    ['review_evidence_reference' => trim($reviewEvidenceReference), 'review_evidence_sha256' => strtolower($reviewEvidenceSha256)]
                );
                Model::commit();
                return 'rejected';
            }

            $deductions = (int) $adjustment['contractual_deductions_toman'];
            $approvedAdjustments = (int) $adjustment['approved_adjustments_toman'];
            $amount = (int) $adjustment['amount_toman'];
            if ($adjustment['adjustment_kind'] === 'contractual_deduction') {
                $deductions = self::safeAdd($deductions, $amount);
            } elseif ($adjustment['adjustment_kind'] === 'approved_credit') {
                $approvedAdjustments = self::safeSignedAdd($approvedAdjustments, $amount);
            } else {
                $approvedAdjustments = self::safeSignedAdd($approvedAdjustments, -$amount);
            }
            $calculation = MerchantSettlementCalculator::calculateReceivable(
                (int) $adjustment['cash_product_price_toman'],
                (int) $adjustment['verified_down_payment_toman'], $deductions, $approvedAdjustments
            );
            Model::execute(
                "UPDATE merchant_settlement_adjustments SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = 'pending'",
                [$reviewerUserId, (int) $adjustment['id']]
            );
            Model::execute(
                "UPDATE merchant_settlement_obligations
                 SET contractual_deductions_toman = ?, approved_adjustments_toman = ?,
                     net_settlement_toman = ?, status = 'pending', eligible_at = NULL,
                     settlement_due_date = NULL, risk_review_snapshot_json = NULL
                 WHERE id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?",
                [
                    $deductions, $approvedAdjustments, $calculation['net_settlement_toman'],
                    (int) $adjustment['obligation_id'], $context->merchantId(), $context->siteId(), $context->connectionId(),
                ]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.adjustment_approved', 'settlement_adjustment', $adjustmentUuid,
                [
                    'net_settlement_toman' => $calculation['net_settlement_toman'],
                    'review_evidence_reference' => trim($reviewEvidenceReference),
                    'review_evidence_sha256' => strtolower($reviewEvidenceSha256),
                ]
            );
            Model::commit();
            return 'approved';
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Reviews documentary gates and assigns a due date only when every gate passes. */
    public static function reviewEligibility(
        MerchantAccessContext $context,
        string $obligationUuid,
        array $review,
        int $reviewerUserId
    ): array {
        $context->requireScope('settlement:review');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.review');
        $obligationUuid = self::normalizeUuid($obligationUuid);
        $contractReference = self::requiredReference($review['contract_approval_reference'] ?? null);
        $documentsReference = self::requiredReference($review['documents_evidence_reference'] ?? null);
        $documentsHash = self::normalizeHash($review['documents_evidence_sha256'] ?? null);
        $deliveryReference = self::requiredReference($review['delivery_evidence_reference'] ?? null);
        $deliveryHash = self::normalizeHash($review['delivery_evidence_sha256'] ?? null);
        $riskReference = self::requiredReference($review['risk_review_reference'] ?? null);
        $hold = $review['hold_status'] ?? 'none';
        if (!in_array($hold, ['none', 'dispute', 'compliance', 'risk'], true)) {
            throw new InvalidArgumentException('merchant_settlement_hold_status_invalid');
        }
        $holdReason = trim((string) ($review['hold_reason'] ?? ''));
        if ($hold === 'none' && $holdReason !== '') {
            throw new InvalidArgumentException('merchant_settlement_hold_reason_invalid');
        }
        $manualDate = isset($review['manual_due_date']) ? self::normalizeDate((string) $review['manual_due_date']) : null;
        $holidays = $review['bank_holidays'] ?? [];
        if (!is_array($holidays)) {
            throw new InvalidArgumentException('merchant_settlement_calendar_invalid');
        }

        Model::begin();
        try {
            self::lockActiveConnection($context);
            $policyRow = self::lockPublishedPolicy($context);
            $policy = self::decodePolicy($policyRow);
            $obligation = self::lockObligation($context, $obligationUuid);
            self::assertEditable($obligation);
            Model::fetch('SELECT id FROM merchant_accounts WHERE id = ? FOR UPDATE', [$context->merchantId()]);
            if ($policy['receivable_ownership_model'] !== 'merchant') {
                self::lockPlatformExposureGuard();
            }
            $contract = Model::fetch(
                'SELECT id, status FROM contracts WHERE id = ? FOR UPDATE',
                [(int) $obligation['contract_id']]
            );
            $guarantee = Model::fetch(
                "SELECT id, sayad_status, first_installment_due_date, cheque_due_date
                 FROM merchant_cheque_guarantees
                 WHERE contract_link_id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?
                 FOR UPDATE",
                [(int) $obligation['contract_link_id'], $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            $account = null;
            if ($policy['funding_account_uuid'] !== null) {
                $account = Model::fetch(
                    'SELECT * FROM merchant_liquidity_accounts WHERE account_uuid = ? FOR UPDATE',
                    [$policy['funding_account_uuid']]
                );
            }
            $net = (int) $obligation['net_settlement_toman'];
            $pendingAdjustments = Model::fetch(
                "SELECT COUNT(*) AS total FROM merchant_settlement_adjustments
                 WHERE obligation_id = ? AND status = 'pending'",
                [(int) $obligation['id']]
            );
            $merchantExposure = self::safeAdd(
                self::sumExposure($context->merchantId(), false, (int) $obligation['id']),
                $net
            );
            $platformExposure = self::sumExposure(0, true, (int) $obligation['id']);
            if ($policy['receivable_ownership_model'] === 'platform'
                || $policy['receivable_ownership_model'] === 'third_party_funder') {
                $platformExposure = self::safeAdd($platformExposure, $net);
            }
            $ownerMatches = $account && $account['status'] === 'active'
                && self::accountOwnerMatches($account, $context, $policy['receivable_ownership_model']);
            $limitsPass = $net <= $policy['max_single_settlement_toman']
                && $merchantExposure <= $policy['max_merchant_exposure_toman']
                && ($policy['receivable_ownership_model'] === 'merchant'
                    || $platformExposure <= $policy['max_platform_exposure_toman']);
            $cashPass = $ownerMatches
                && (int) $account['available_balance_toman'] >= $net
                && (int) $account['available_balance_toman'] - $net >= $policy['minimum_liquidity_reserve_toman'];
            $checks = [
                'initial_payment_verified' => $obligation['down_payment_status'] === 'verified',
                'contract_approved' => $contract && $contract['status'] === 'active' && $contractReference !== '',
                'guarantee_confirmed' => $guarantee && $guarantee['sayad_status'] === 'confirmed'
                    && $guarantee['first_installment_due_date'] === $guarantee['cheque_due_date'],
                'documents_complete' => true,
                'delivery_confirmed' => true,
                'no_hold_or_dispute' => $hold === 'none',
                'exposure_within_limit' => $limitsPass && $cashPass,
                'legal_and_funding_approved' => $policy['enabled'] === true
                    && $policy['receivable_ownership_model'] !== 'unapproved'
                    && $policy['legal_contract_reference'] !== null
                    && $policy['legal_approval_reference'] !== null && $ownerMatches,
                'adjustments_resolved' => (int) ($pendingAdjustments['total'] ?? 0) === 0,
                'positive_net_settlement' => $net > 0,
                'hold_or_dispute' => $hold !== 'none',
            ];
            $result = MerchantSettlementCalculator::eligibility($checks);
            $dueDate = null;
            if ($result['status'] === 'eligible') {
                $dueDate = MerchantSettlementCalendar::nextDueDate(
                    $policy, substr((string) $obligation['created_at'], 0, 10),
                    date('Y-m-d'), $holidays, $manualDate, $manualDate !== null
                );
            }
            $riskSnapshot = json_encode([
                'merchant_exposure_toman' => $merchantExposure,
                'platform_exposure_toman' => $platformExposure,
                'cash_available_toman' => $account ? (int) $account['available_balance_toman'] : 0,
                'net_settlement_toman' => $net,
                'missing_checks' => $result['missing_checks'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            Model::execute(
                'UPDATE merchant_settlement_obligations
                 SET contract_approval_reference = ?, contract_approved_by = ?, contract_approved_at = NOW(),
                     documents_evidence_reference = ?, documents_evidence_sha256 = ?, documents_verified_by = ?, documents_verified_at = NOW(),
                     delivery_evidence_reference = ?, delivery_evidence_sha256 = ?, delivery_verified_by = ?, delivery_verified_at = NOW(),
                     risk_review_status = ?, risk_review_reference = ?, risk_review_snapshot_json = ?, risk_reviewed_by = ?, risk_reviewed_at = NOW(),
                     hold_status = ?, hold_reason = ?, hold_set_by = ?, hold_set_at = ?,
                     status = ?, settlement_due_date = ?, eligible_at = IF(? = ?, NOW(), NULL)
                 WHERE id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?',
                [
                    $contractReference, $reviewerUserId, $documentsReference, $documentsHash, $reviewerUserId,
                    $deliveryReference, $deliveryHash, $reviewerUserId,
                    $result['status'] === 'eligible' ? 'passed' : 'pending', $riskReference, $riskSnapshot, $reviewerUserId,
                    $hold, $holdReason !== '' ? $holdReason : null,
                    $hold !== 'none' ? $reviewerUserId : null, $hold !== 'none' ? date('Y-m-d H:i:s') : null,
                    $result['status'], $dueDate, $result['status'], 'eligible',
                    (int) $obligation['id'], $context->merchantId(), $context->siteId(), $context->connectionId(),
                ]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.obligation_reviewed', 'settlement_obligation', $obligationUuid,
                ['status' => $result['status'], 'missing_checks' => $result['missing_checks'], 'due_date' => $dueDate]
            );
            Model::commit();
            return ['status' => $result['status'], 'missing_checks' => $result['missing_checks'], 'settlement_due_date' => $dueDate];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function createDueBatch(MerchantAccessContext $context, string $scheduledFor, int $actorUserId): array
    {
        $context->requireScope('settlement:batch:create');
        self::requireReviewer($actorUserId, 'merchant.settlement.batch.create');
        $scheduledFor = self::normalizeDate($scheduledFor);
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $policyRow = self::lockPublishedPolicy($context);
            $policy = self::decodePolicy($policyRow);
            if (!$policy['enabled'] || $policy['funding_account_uuid'] === null) {
                throw new DomainException('merchant_settlement_policy_not_operational');
            }
            $existing = Model::fetch(
                'SELECT batch_uuid, status, net_settlement_toman FROM merchant_settlement_batches
                 WHERE merchant_id = ? AND site_id = ? AND connection_id = ?
                   AND settlement_policy_version_id = ? AND scheduled_for = ? FOR UPDATE',
                [$context->merchantId(), $context->siteId(), $context->connectionId(), (int) $policyRow['id'], $scheduledFor]
            );
            if ($existing) {
                Model::commit();
                return [
                    'batch_uuid' => (string) $existing['batch_uuid'], 'status' => (string) $existing['status'],
                    'net_settlement_toman' => (int) $existing['net_settlement_toman'], 'idempotent' => true,
                ];
            }
            $account = Model::fetch(
                'SELECT * FROM merchant_liquidity_accounts WHERE account_uuid = ? FOR UPDATE',
                [$policy['funding_account_uuid']]
            );
            if (!$account || $account['status'] !== 'active'
                || !self::accountOwnerMatches($account, $context, $policy['receivable_ownership_model'])) {
                throw new DomainException('merchant_settlement_funding_account_unavailable');
            }
            $obligations = Model::fetchAll(
                "SELECT * FROM merchant_settlement_obligations
                 WHERE merchant_id = ? AND site_id = ? AND connection_id = ?
                   AND settlement_policy_version_id = ? AND status = 'eligible'
                   AND settlement_due_date <= ?
                 ORDER BY id FOR UPDATE",
                [$context->merchantId(), $context->siteId(), $context->connectionId(), (int) $policyRow['id'], $scheduledFor]
            );
            if (!$obligations) {
                throw new DomainException('merchant_settlement_no_eligible_obligations');
            }
            $pendingAdjustment = Model::fetch(
                "SELECT COUNT(*) AS total FROM merchant_settlement_adjustments a
                 INNER JOIN merchant_settlement_obligations o ON o.id = a.obligation_id
                 WHERE o.merchant_id = ? AND o.site_id = ? AND o.connection_id = ?
                   AND o.settlement_policy_version_id = ? AND o.status = 'eligible'
                   AND a.status = 'pending'",
                [$context->merchantId(), $context->siteId(), $context->connectionId(), (int) $policyRow['id']]
            );
            if ((int) ($pendingAdjustment['total'] ?? 0) !== 0) {
                throw new DomainException('merchant_settlement_adjustments_pending');
            }
            $totals = ['gross' => 0, 'deductions' => 0, 'adjustments' => 0, 'net' => 0];
            foreach ($obligations as $obligation) {
                $totals['gross'] = self::safeAdd($totals['gross'], (int) $obligation['gross_receivable_toman']);
                $totals['deductions'] = self::safeAdd($totals['deductions'], (int) $obligation['contractual_deductions_toman']);
                $totals['adjustments'] = self::safeSignedAdd($totals['adjustments'], (int) $obligation['approved_adjustments_toman']);
                $totals['net'] = self::safeAdd($totals['net'], (int) $obligation['net_settlement_toman']);
            }
            if ($totals['net'] < 1 || (int) $account['available_balance_toman'] < $totals['net']
                || (int) $account['available_balance_toman'] - $totals['net'] < $policy['minimum_liquidity_reserve_toman']) {
                throw new DomainException('merchant_settlement_liquidity_insufficient');
            }
            $batchUuid = MerchantAuditLog::newUuid();
            Model::execute(
                "INSERT INTO merchant_settlement_batches
                    (batch_uuid,merchant_id,site_id,connection_id,settlement_policy_version_id,policy_type,
                     liquidity_account_id,scheduled_for,line_count,gross_receivable_toman,contractual_deductions_toman,
                     approved_adjustments_toman,net_settlement_toman,status,approved_by,approved_at,created_by)
                 VALUES (?,?,?,?,?,'settlement',?,?,?,?,?,?,?,'scheduled',?,NOW(),?)",
                [
                    $batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId(),
                    (int) $policyRow['id'], (int) $account['id'], $scheduledFor, count($obligations),
                    $totals['gross'], $totals['deductions'], $totals['adjustments'], $totals['net'],
                    $actorUserId, $actorUserId,
                ]
            );
            $batchId = (int) Model::lastInsertId();
            foreach ($obligations as $obligation) {
                Model::execute(
                    'INSERT INTO merchant_settlement_lines
                        (batch_id,obligation_id,merchant_id,site_id,connection_id,contract_link_id,contract_id,
                         external_order_id,gross_receivable_toman,contractual_deductions_toman,
                         approved_adjustments_toman,net_settlement_toman)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $batchId, (int) $obligation['id'], $context->merchantId(), $context->siteId(),
                        $context->connectionId(), (int) $obligation['contract_link_id'], (int) $obligation['contract_id'],
                        (string) $obligation['external_order_id'], (int) $obligation['gross_receivable_toman'],
                        (int) $obligation['contractual_deductions_toman'], (int) $obligation['approved_adjustments_toman'],
                        (int) $obligation['net_settlement_toman'],
                    ]
                );
                Model::execute(
                    "UPDATE merchant_settlement_obligations SET status = 'scheduled' WHERE id = ? AND status = 'eligible'",
                    [(int) $obligation['id']]
                );
            }
            $availableAfter = (int) $account['available_balance_toman'] - $totals['net'];
            $reservedAfter = self::safeAdd((int) $account['reserved_balance_toman'], $totals['net']);
            Model::execute(
                'UPDATE merchant_liquidity_accounts SET available_balance_toman = ?, reserved_balance_toman = ? WHERE id = ?',
                [$availableAfter, $reservedAfter, (int) $account['id']]
            );
            self::appendLiquidityEvent(
                (int) $account['id'], 'settlement_reserved', 'settlement.reserve:' . $batchUuid,
                'settlement-reserve:' . $batchUuid, 'policy:' . (string) $policy['legal_approval_reference'],
                hash('sha256', $batchUuid . ':reserve:' . $totals['net']), 0, -$totals['net'], $totals['net'],
                (int) $account['cash_balance_toman'], $availableAfter, $reservedAfter, $actorUserId
            );
            self::appendBatchEvent($batchId, $context, 'batch.scheduled', $actorUserId, 'batch.schedule:' . $batchUuid, [
                'batch_uuid' => $batchUuid, 'line_count' => count($obligations),
                'net_settlement_toman' => $totals['net'], 'scheduled_for' => $scheduledFor,
            ]);
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $actorUserId,
                'settlement.batch_scheduled', 'settlement_batch', $batchUuid,
                ['line_count' => count($obligations), 'net_settlement_toman' => $totals['net'], 'scheduled_for' => $scheduledFor]
            );
            Model::commit();
            return ['batch_uuid' => $batchUuid, 'status' => 'scheduled', 'net_settlement_toman' => $totals['net'], 'idempotent' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function confirmPaid(
        MerchantAccessContext $context,
        string $batchUuid,
        string $bankReference,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        $context->requireScope('settlement:payout:confirm');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.payout.confirm');
        $batchUuid = self::normalizeUuid($batchUuid);
        $bankReference = self::requiredReference($bankReference);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $batch = Model::fetch(
                'SELECT * FROM merchant_settlement_batches
                 WHERE batch_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE',
                [$batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$batch) {
                throw new DomainException('merchant_settlement_batch_not_found');
            }
            if ($batch['status'] === 'paid') {
                if ((string) $batch['bank_reference'] !== $bankReference
                    || !hash_equals((string) $batch['bank_evidence_sha256'], strtolower($evidenceSha256))) {
                    throw new DomainException('merchant_settlement_paid_batch_conflict');
                }
                Model::commit();
                return true;
            }
            if (!in_array($batch['status'], ['scheduled', 'processing'], true)) {
                throw new DomainException('merchant_settlement_batch_not_payable');
            }
            $account = Model::fetch('SELECT * FROM merchant_liquidity_accounts WHERE id = ? FOR UPDATE', [(int) $batch['liquidity_account_id']]);
            $amount = (int) $batch['net_settlement_toman'];
            if (!$account || $account['status'] !== 'active'
                || (int) $account['reserved_balance_toman'] < $amount
                || (int) $account['cash_balance_toman'] < $amount) {
                throw new DomainException('merchant_settlement_reserved_cash_unavailable');
            }
            $cashAfter = (int) $account['cash_balance_toman'] - $amount;
            $reservedAfter = (int) $account['reserved_balance_toman'] - $amount;
            $availableAfter = (int) $account['available_balance_toman'];
            Model::execute(
                'UPDATE merchant_liquidity_accounts SET cash_balance_toman = ?, reserved_balance_toman = ? WHERE id = ?',
                [$cashAfter, $reservedAfter, (int) $account['id']]
            );
            self::appendLiquidityEvent(
                (int) $account['id'], 'settlement_paid', 'settlement.paid:' . $batchUuid,
                $bankReference, trim($evidenceReference), strtolower($evidenceSha256), -$amount, 0, -$amount,
                $cashAfter, $availableAfter, $reservedAfter, $reviewerUserId
            );
            Model::execute(
                "UPDATE merchant_settlement_batches
                 SET status = 'paid', accounting_status = 'posted', bank_reference = ?,
                     bank_evidence_sha256 = ?, paid_at = NOW(), approved_by = ?, approved_at = NOW()
                 WHERE id = ?",
                [$bankReference, strtolower($evidenceSha256), $reviewerUserId, (int) $batch['id']]
            );
            Model::execute(
                "UPDATE merchant_settlement_obligations o
                 INNER JOIN merchant_settlement_lines l ON l.obligation_id = o.id
                 SET o.status = 'paid'
                 WHERE l.batch_id = ? AND o.status = 'scheduled'",
                [(int) $batch['id']]
            );
            self::appendBatchEvent((int) $batch['id'], $context, 'batch.paid', $reviewerUserId, 'batch.paid:' . $batchUuid, [
                'batch_uuid' => $batchUuid, 'bank_reference' => $bankReference, 'net_settlement_toman' => $amount,
            ]);
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.batch_paid', 'settlement_batch', $batchUuid,
                ['bank_reference' => $bankReference, 'net_settlement_toman' => $amount]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Records a verified manual payout initiation; no external rail is invoked here. */
    public static function markProcessing(
        MerchantAccessContext $context,
        string $batchUuid,
        string $transferReference,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        $context->requireScope('settlement:payout:initiate');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.payout.initiate');
        $batchUuid = self::normalizeUuid($batchUuid);
        $transferReference = self::requiredReference($transferReference);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $batch = Model::fetch(
                'SELECT * FROM merchant_settlement_batches
                 WHERE batch_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE',
                [$batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$batch) {
                throw new DomainException('merchant_settlement_batch_not_found');
            }
            if ($batch['status'] === 'processing') {
                $event = Model::fetch(
                    'SELECT metadata_json FROM merchant_settlement_events
                     WHERE connection_id = ? AND idempotency_key = ? LIMIT 1',
                    [$context->connectionId(), 'batch.processing:' . $batchUuid]
                );
                if (!$event) {
                    throw new DomainException('merchant_settlement_processing_record_missing');
                }
                $metadata = json_decode((string) $event['metadata_json'], true);
                if (!is_array($metadata) || ($metadata['transfer_reference'] ?? null) !== $transferReference
                    || ($metadata['evidence_sha256'] ?? null) !== strtolower($evidenceSha256)) {
                    throw new DomainException('merchant_settlement_processing_idempotency_conflict');
                }
                Model::commit();
                return true;
            }
            if ($batch['status'] !== 'scheduled') {
                throw new DomainException('merchant_settlement_batch_not_initiable');
            }
            Model::execute(
                "UPDATE merchant_settlement_batches SET status = 'processing' WHERE id = ? AND status = 'scheduled'",
                [(int) $batch['id']]
            );
            self::appendBatchEvent((int) $batch['id'], $context, 'batch.processing', $reviewerUserId,
                'batch.processing:' . $batchUuid, [
                    'transfer_reference' => $transferReference,
                    'evidence_reference' => trim($evidenceReference),
                    'evidence_sha256' => strtolower($evidenceSha256),
                ]);
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.batch_processing', 'settlement_batch', $batchUuid,
                ['transfer_reference' => $transferReference]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Releases a reserved batch only after a reviewer records external failure evidence. */
    public static function failPayoutAndRelease(
        MerchantAccessContext $context,
        string $batchUuid,
        string $failureReference,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        $context->requireScope('settlement:payout:fail');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.payout.fail');
        $batchUuid = self::normalizeUuid($batchUuid);
        $failureReference = self::requiredReference($failureReference);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $batch = Model::fetch(
                'SELECT * FROM merchant_settlement_batches
                 WHERE batch_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE',
                [$batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$batch) {
                throw new DomainException('merchant_settlement_batch_not_found');
            }
            if ($batch['status'] === 'failed') {
                $event = Model::fetch(
                    "SELECT metadata_json FROM merchant_settlement_events
                     WHERE connection_id = ? AND idempotency_key = ? LIMIT 1",
                    [$context->connectionId(), 'batch.failed:' . $batchUuid]
                );
                if ($event) {
                    $metadata = json_decode((string) $event['metadata_json'], true);
                    if (!is_array($metadata) || ($metadata['failure_reference'] ?? null) !== $failureReference
                        || ($metadata['evidence_sha256'] ?? null) !== strtolower($evidenceSha256)) {
                        throw new DomainException('merchant_settlement_failure_idempotency_conflict');
                    }
                    Model::commit();
                    return true;
                }
            }
            if (!in_array($batch['status'], ['scheduled', 'processing'], true)) {
                throw new DomainException('merchant_settlement_batch_not_fail_releasable');
            }
            $account = Model::fetch(
                'SELECT * FROM merchant_liquidity_accounts WHERE id = ? FOR UPDATE',
                [(int) $batch['liquidity_account_id']]
            );
            $amount = (int) $batch['net_settlement_toman'];
            if (!$account || $account['status'] !== 'active' || (int) $account['reserved_balance_toman'] < $amount) {
                throw new DomainException('merchant_settlement_reserved_cash_unavailable');
            }
            $availableAfter = self::safeAdd((int) $account['available_balance_toman'], $amount);
            $reservedAfter = (int) $account['reserved_balance_toman'] - $amount;
            Model::execute(
                'UPDATE merchant_liquidity_accounts SET available_balance_toman = ?, reserved_balance_toman = ? WHERE id = ?',
                [$availableAfter, $reservedAfter, (int) $account['id']]
            );
            self::appendLiquidityEvent(
                (int) $account['id'], 'settlement_reservation_released', 'settlement.failed:' . $batchUuid,
                $failureReference, trim($evidenceReference), strtolower($evidenceSha256), 0, $amount, -$amount,
                (int) $account['cash_balance_toman'], $availableAfter, $reservedAfter, $reviewerUserId
            );
            Model::execute(
                "UPDATE merchant_settlement_batches SET status = 'failed' WHERE id = ?",
                [(int) $batch['id']]
            );
            Model::execute(
                "UPDATE merchant_settlement_obligations o
                 INNER JOIN merchant_settlement_lines l ON l.obligation_id = o.id
                 SET o.status = 'eligible'
                 WHERE l.batch_id = ? AND o.status = 'scheduled'",
                [(int) $batch['id']]
            );
            self::appendBatchEvent((int) $batch['id'], $context, 'batch.failed', $reviewerUserId,
                'batch.failed:' . $batchUuid, [
                    'failure_reference' => $failureReference, 'released_amount_toman' => $amount,
                    'evidence_reference' => trim($evidenceReference), 'evidence_sha256' => strtolower($evidenceSha256),
                ]);
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.batch_failed', 'settlement_batch', $batchUuid,
                ['failure_reference' => $failureReference, 'released_amount_toman' => $amount]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function reconcileBankStatement(
        MerchantAccessContext $context,
        string $batchUuid,
        string $statementReference,
        string $bankReference,
        int $statementAmountToman,
        string $evidenceSha256,
        int $reviewerUserId
    ): array {
        $context->requireScope('settlement:reconcile');
        self::requireReviewer($reviewerUserId, 'merchant.settlement.reconcile');
        $batchUuid = self::normalizeUuid($batchUuid);
        $statementReference = self::requiredReference($statementReference);
        $bankReference = self::requiredReference($bankReference);
        $evidenceSha256 = self::normalizeHash($evidenceSha256);
        if ($statementAmountToman < 0) {
            throw new InvalidArgumentException('merchant_settlement_statement_amount_invalid');
        }
        Model::begin();
        try {
            self::lockActiveConnection($context);
            $batch = Model::fetch(
                'SELECT * FROM merchant_settlement_batches
                 WHERE batch_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE',
                [$batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$batch || $batch['status'] !== 'paid') {
                throw new DomainException('merchant_settlement_batch_not_reconcilable');
            }
            $existing = Model::fetch(
                'SELECT settlement_batch_id,bank_reference,statement_amount_toman,evidence_sha256,reconciliation_status
                 FROM merchant_financial_reconciliations
                 WHERE liquidity_account_id = ? AND statement_reference = ? FOR UPDATE',
                [(int) $batch['liquidity_account_id'], $statementReference]
            );
            if ($existing) {
                if ((int) $existing['settlement_batch_id'] !== (int) $batch['id']
                    || (string) $existing['bank_reference'] !== $bankReference
                    || (int) $existing['statement_amount_toman'] !== $statementAmountToman
                    || !hash_equals((string) $existing['evidence_sha256'], $evidenceSha256)) {
                    throw new DomainException('merchant_settlement_reconciliation_idempotency_conflict');
                }
                Model::commit();
                return ['status' => (string) $existing['reconciliation_status'], 'batch_uuid' => $batchUuid, 'idempotent' => true];
            }
            $status = ((string) $batch['bank_reference'] === $bankReference
                && (int) $batch['net_settlement_toman'] === $statementAmountToman) ? 'matched' : 'mismatch';
            Model::execute(
                'INSERT INTO merchant_financial_reconciliations
                    (reconciliation_uuid,liquidity_account_id,settlement_batch_id,merchant_id,site_id,connection_id,
                     bank_reference,statement_reference,statement_amount_toman,internal_amount_toman,
                     reconciliation_status,evidence_sha256,reviewed_by,reviewed_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
                [
                    MerchantAuditLog::newUuid(), (int) $batch['liquidity_account_id'], (int) $batch['id'],
                    $context->merchantId(), $context->siteId(), $context->connectionId(), $bankReference,
                    $statementReference, $statementAmountToman, (int) $batch['net_settlement_toman'],
                    $status, $evidenceSha256, $reviewerUserId,
                ]
            );
            Model::execute(
                'UPDATE merchant_settlement_batches SET accounting_status = ? WHERE id = ?',
                [$status === 'matched' ? 'reconciled' : 'discrepancy', (int) $batch['id']]
            );
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                'settlement.bank_reconciled', 'settlement_batch', $batchUuid,
                ['reconciliation_status' => $status, 'statement_reference' => $statementReference]
            );
            Model::commit();
            return ['status' => $status, 'batch_uuid' => $batchUuid];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function liquiditySnapshot(MerchantAccessContext $context): array
    {
        $context->requireScope('settlement:read');
        $policyHead = Model::fetch(
            "SELECT p.policy_json FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p ON p.id = h.policy_version_id
             WHERE h.connection_id = ? AND h.policy_type = 'settlement' AND p.status = 'published'",
            [$context->connectionId()]
        );
        $policy = $policyHead ? self::decodePolicy($policyHead) : null;
        $cash = $policy && $policy['funding_account_uuid'] !== null
            ? Model::fetch(
                "SELECT owner_type,available_balance_toman AS available_toman,
                        reserved_balance_toman AS reserved_toman
                 FROM merchant_liquidity_accounts
                 WHERE account_uuid = ? AND status = 'active'
                   AND (owner_type = 'platform'
                     OR (merchant_id = ? AND (site_id IS NULL OR site_id = ?)
                         AND (connection_id IS NULL OR connection_id = ?)))",
                [$policy['funding_account_uuid'], $context->merchantId(), $context->siteId(), $context->connectionId()]
            )
            : null;
        $payables = Model::fetch(
            "SELECT COALESCE(SUM(net_settlement_toman),0) AS payable_toman,
                    COALESCE(SUM(CASE WHEN settlement_due_date > CURDATE() THEN net_settlement_toman ELSE 0 END),0) AS upcoming_toman,
                    COALESCE(SUM(CASE WHEN receivable_ownership_model IN ('platform','third_party_funder') THEN net_settlement_toman ELSE 0 END),0) AS platform_exposure_toman
             FROM merchant_settlement_obligations
             WHERE merchant_id = ? AND site_id = ? AND connection_id = ?
               AND status IN ('eligible','scheduled','processing')",
            [$context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        $receivables = Model::fetch(
            "SELECT CAST(COALESCE(SUM(i.remaining_amount),0) AS CHAR) AS outstanding,
                    CAST(COALESCE(SUM(CASE WHEN i.due_date < CURDATE() THEN i.remaining_amount ELSE 0 END),0) AS CHAR) AS overdue
             FROM installments i
             INNER JOIN merchant_contract_links l ON l.contract_id = i.contract_id
             WHERE l.merchant_id = ? AND l.site_id = ? AND l.connection_id = ?
               AND l.order_status = 'contracted' AND i.status NOT IN ('paid','cancelled')",
            [$context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        return [
            'currency_unit' => 'toman',
            'cash_available_toman' => $cash && $cash['owner_type'] === 'platform' ? null : (int) ($cash['available_toman'] ?? 0),
            'liquidity_balance_visibility' => !$cash ? 'unavailable' : ($cash['owner_type'] === 'platform' ? 'shared_platform_hidden' : 'merchant_scoped'),
            'settlement_payables_toman' => (int) ($payables['payable_toman'] ?? 0),
            'receivables_outstanding_decimal' => (string) ($receivables['outstanding'] ?? '0'),
            'upcoming_settlement_obligations_toman' => (int) ($payables['upcoming_toman'] ?? 0),
            'merchant_exposure_toman' => (int) ($payables['payable_toman'] ?? 0),
            'platform_exposure_toman' => (int) ($payables['platform_exposure_toman'] ?? 0),
            'overdue_receivables_decimal' => (string) ($receivables['overdue'] ?? '0'),
            'liquidity_reserve_toman' => $cash && $cash['owner_type'] === 'platform' ? null : (int) ($cash['reserved_toman'] ?? 0),
        ];
    }

    private static function lockActiveConnection(MerchantAccessContext $context): void
    {
        $connection = Model::fetch(
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
        if (!$connection) {
            throw new DomainException('merchant_connection_not_available');
        }
    }

    private static function lockPublishedPolicy(MerchantAccessContext $context): array
    {
        $policy = Model::fetch(
            "SELECT p.* FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p ON p.id = h.policy_version_id
             WHERE h.connection_id = ? AND h.policy_type = 'settlement' AND p.status = 'published'
             FOR UPDATE",
            [$context->connectionId()]
        );
        if (!$policy) {
            throw new DomainException('merchant_settlement_policy_unavailable');
        }
        return $policy;
    }

    private static function decodePolicy(array $policyRow): array
    {
        try {
            $document = json_decode((string) $policyRow['policy_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('merchant_settlement_policy_invalid', 0, $e);
        }
        if (!is_array($document)) {
            throw new DomainException('merchant_settlement_policy_invalid');
        }
        return MerchantSettlementPolicy::resolve($document);
    }

    private static function lockObligation(MerchantAccessContext $context, string $uuid): array
    {
        $row = Model::fetch(
            'SELECT * FROM merchant_settlement_obligations
             WHERE obligation_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE',
            [$uuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_settlement_obligation_not_found');
        }
        return $row;
    }

    private static function assertEditable(array $obligation): void
    {
        if (!in_array((string) $obligation['status'], ['pending','eligible','suspended'], true)) {
            throw new DomainException('merchant_settlement_obligation_locked');
        }
    }

    private static function sumExposure(int $merchantId, bool $platformOnly, int $excludeId): int
    {
        $sql = "SELECT COALESCE(SUM(net_settlement_toman),0) AS total
                FROM merchant_settlement_obligations
                WHERE status IN ('eligible','scheduled','processing') AND id <> ?";
        $params = [$excludeId];
        if ($merchantId > 0) {
            $sql .= ' AND merchant_id = ?';
            $params[] = $merchantId;
        }
        if ($platformOnly) {
            $sql .= " AND receivable_ownership_model IN ('platform','third_party_funder')";
        }
        $row = Model::fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    private static function accountOwnerMatches(array $account, MerchantAccessContext $context, string $model): bool
    {
        if ($model === 'platform') {
            return $account['owner_type'] === 'platform' && $account['merchant_id'] === null;
        }
        if ($model === 'merchant' || $model === 'third_party_funder') {
            return $account['owner_type'] === $model
                && (int) $account['merchant_id'] === $context->merchantId()
                && ($account['site_id'] === null || (int) $account['site_id'] === $context->siteId())
                && ($account['connection_id'] === null || (int) $account['connection_id'] === $context->connectionId());
        }
        return false;
    }

    private static function lockPlatformExposureGuard(): void
    {
        $guard = Model::fetch(
            "SELECT guard_code FROM merchant_financial_control_locks
             WHERE guard_code = 'platform_exposure' FOR UPDATE"
        );
        if (!$guard) {
            throw new DomainException('merchant_platform_exposure_guard_unavailable');
        }
    }

    private static function appendLiquidityEvent(
        int $accountId,
        string $eventType,
        string $sourceKey,
        string $externalReference,
        string $evidenceReference,
        string $evidenceHash,
        int $cashDelta,
        int $availableDelta,
        int $reservedDelta,
        int $cashAfter,
        int $availableAfter,
        int $reservedAfter,
        int $actorUserId
    ): void {
        Model::execute(
            'INSERT INTO merchant_liquidity_events
                (event_uuid,account_id,event_type,source_event_key,external_reference,evidence_reference,evidence_sha256,
                 cash_delta_toman,available_delta_toman,reserved_delta_toman,cash_balance_after_toman,
                 available_balance_after_toman,reserved_balance_after_toman,actor_user_id,verified_by,verified_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                MerchantAuditLog::newUuid(), $accountId, $eventType, $sourceKey, $externalReference,
                $evidenceReference, self::normalizeHash($evidenceHash), $cashDelta, $availableDelta, $reservedDelta,
                $cashAfter, $availableAfter, $reservedAfter, $actorUserId, $actorUserId,
            ]
        );
    }

    private static function appendBatchEvent(
        int $batchId,
        MerchantAccessContext $context,
        string $eventType,
        ?int $actorUserId,
        string $idempotencyKey,
        array $metadata
    ): void {
        Model::execute(
            'INSERT INTO merchant_settlement_events
                (event_uuid,batch_id,merchant_id,site_id,connection_id,event_type,actor_user_id,idempotency_key,metadata_json)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                MerchantAuditLog::newUuid(), $batchId, $context->merchantId(), $context->siteId(),
                $context->connectionId(), $eventType, $actorUserId, $idempotencyKey,
                MerchantAuditMetadata::encode($metadata),
            ]
        );
    }

    private static function requireReviewer(int $actorUserId, string $permission): void
    {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId) {
            throw new DomainException('merchant_settlement_reviewer_required');
        }
        PluginManager::requirePermission($permission);
    }

    private static function assertRequestActor(MerchantAccessContext $context, ?int $actorUserId, string $permission): void
    {
        if ($actorUserId === null) {
            return;
        }
        if (!Auth::id() || (int) Auth::id() !== $actorUserId
            || !MerchantMembershipRepository::allows($actorUserId, $context->merchantId(), $permission)) {
            throw new DomainException('merchant_settlement_actor_denied');
        }
    }

    private static function validateEvidence(string $reference, string $sha256): void
    {
        self::requiredReference($reference);
        self::normalizeHash($sha256);
    }

    private static function normalizeHash($value): string
    {
        if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/iD', $value)) {
            throw new InvalidArgumentException('merchant_settlement_evidence_hash_invalid');
        }
        return strtolower($value);
    }

    private static function requiredReference($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', trim($value))) {
            throw new InvalidArgumentException('merchant_settlement_evidence_reference_invalid');
        }
        return trim($value);
    }

    private static function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('merchant_settlement_uuid_invalid');
        }
        return $value;
    }

    private static function normalizeDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('merchant_settlement_date_invalid');
        }
        return $value;
    }

    private static function safeAdd(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new OverflowException('merchant_settlement_amount_overflow');
        }
        return $left + $right;
    }

    private static function safeSignedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new OverflowException('merchant_settlement_amount_overflow');
        }
        return $left + $right;
    }
}
