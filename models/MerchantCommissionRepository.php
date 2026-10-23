<?php

declare(strict_types=1);

/** Versioned, tenant-scoped commission and referral reward ledgers. */
final class MerchantCommissionRepository
{
    public static function recordEvent(
        MerchantAccessContext $context,
        int $contractLinkId,
        string $sourceType,
        string $sourceIdentifier,
        ?int $actorUserId = null
    ): array {
        $context->requireScope('commission:record');
        self::validateEvent($sourceType, $contractLinkId, $actorUserId);
        self::assertRequestActor($context, $actorUserId, 'commission.record');
        Model::begin();
        try {
            self::lockConnection($context);
            $link = self::lockLink($context, $contractLinkId);
            $source = self::loadSource($context, $link, $sourceType, $sourceIdentifier);
            $obligation = self::loadVerifiedObligation($context, $link);
            $policyRow = self::lockPolicy($context);
            $policy = self::decodePolicy($policyRow);
            if (!$policy['enabled']) {
                Model::commit();
                return ['entries' => [], 'idempotent' => true, 'reason' => 'policy_disabled'];
            }

            $basis = [
                'cash_product_price_toman' => (int) $obligation['cash_product_price_toman'],
                'financed_principal_toman' => (int) $obligation['cash_product_price_toman']
                    - (int) $obligation['verified_down_payment_toman'],
                'installment_collected_toman' => $source['amount_toman'],
            ];
            $basis['monthly_cash_volume_toman'] = self::monthlyCashVolume(
                $context, (int) $obligation['contract_id'], (string) $link['contract_created_at']
            );
            $basis['cash_volume_window'] = date('Y-m', strtotime((string) $link['contract_created_at']));
            if ($sourceType === 'contract_activated') {
                $source['amount_toman'] = (int) $obligation['cash_product_price_toman'];
            }

            $entries = [];
            foreach ($policy['commission_rules'] as $rule) {
                if ($rule['recognition_event'] !== $sourceType) {
                    continue;
                }
                self::assertRuleEventCompatible($rule, $sourceType);
                $amount = MerchantSettlementCalculator::calculateEarning($rule, $basis);
                $entries[] = self::insertEntry(
                    $context, $link, $policyRow, $rule, $sourceType, $source['reference'],
                    $source['amount_toman'], $basis, $amount, false, $actorUserId
                );
            }
            Model::commit();
            $idempotent = !$entries;
            foreach ($entries as $entry) {
                $idempotent = $idempotent || ($entry['idempotent'] ?? false);
                if (!($entry['idempotent'] ?? false)) {
                    $idempotent = false;
                    break;
                }
            }
            return ['entries' => $entries, 'idempotent' => $idempotent];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Mirrors a qualified existing Affiliate sale into the separate reward ledger. */
    public static function recordVerifiedReferralReward(
        MerchantAccessContext $context,
        int $contractLinkId,
        string $ruleCode,
        string $recognitionEvent,
        ?int $paymentId = null,
        ?int $actorUserId = null
    ): array {
        $context->requireScope('reward:record');
        if ($contractLinkId < 1 || !in_array($recognitionEvent, ['contract_activated','installment_collected'], true)
            || ($paymentId !== null && $paymentId < 1) || ($actorUserId !== null && $actorUserId < 1)) {
            throw new InvalidArgumentException('merchant_reward_source_invalid');
        }
        self::assertRequestActor($context, $actorUserId, 'reward.record');
        Model::begin();
        try {
            self::lockConnection($context);
            $link = self::lockLink($context, $contractLinkId);
            if (!SchemaGuard::tableExists('proma_affiliate_sales')) {
                throw new DomainException('merchant_referral_source_unavailable');
            }
            SchemaGuard::requireColumns('proma_affiliate_sales', [
                'id', 'contract_id', 'customer_id', 'member_user_id', 'status', 'reward_amount', 'payment_id',
            ]);
            $sale = Model::fetch(
                "SELECT id, customer_id, member_user_id, reward_amount, payment_id
                 FROM proma_affiliate_sales WHERE contract_id = ? AND status = 'qualified'
                   AND reward_amount > 0 FOR UPDATE",
                [(int) $link['contract_id']]
            );
            if (!$sale || (int) $sale['customer_id'] !== (int) $link['customer_id']) {
                throw new DomainException('merchant_referral_not_qualified');
            }
            if ($recognitionEvent === 'contract_activated' && $link['contract_status'] !== 'active') {
                throw new DomainException('merchant_referral_contract_not_active');
            }
            if ($recognitionEvent === 'installment_collected') {
                if ($paymentId === null || (int) ($sale['payment_id'] ?? 0) !== $paymentId) {
                    throw new DomainException('merchant_referral_qualifying_payment_mismatch');
                }
                self::loadSource($context, $link, $recognitionEvent, (string) $paymentId);
            }
            $policyRow = self::lockPolicy($context);
            $policy = self::decodePolicy($policyRow);
            if (!$policy['enabled']) {
                Model::commit();
                return ['entries' => [], 'idempotent' => true, 'reason' => 'policy_disabled'];
            }
            $rule = self::findRewardRule($policy, $ruleCode, $recognitionEvent, (int) $sale['member_user_id']);
            $amount = MerchantSettlementCalculator::calculateEarning($rule, []);
            $reference = 'affiliate-sale:' . (int) $sale['id'];
            $snapshot = [
                'rule' => $rule,
                'affiliate_sale_id' => (int) $sale['id'],
                'affiliate_reward_amount_toman' => MoneyMath::amount($sale['reward_amount']),
                'referrer_user_id' => (int) $sale['member_user_id'],
            ];
            $entry = self::insertEntry(
                $context, $link, $policyRow, $rule, $recognitionEvent, $reference,
                MoneyMath::amount($sale['reward_amount']), $snapshot, $amount, true, $actorUserId,
                (int) $sale['member_user_id']
            );
            Model::commit();
            return ['entry' => $entry, 'idempotent' => $entry['idempotent']];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Moves an approved/manual or expired-window ledger entry to payable, or cancels it. */
    public static function reviewEntry(
        MerchantAccessContext $context,
        string $ledgerType,
        string $entryUuid,
        bool $approve,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): string {
        $context->requireScope('commission:review');
        self::requireReviewer($reviewerUserId, 'merchant.commission.review');
        $table = self::ledgerTable($ledgerType);
        $entryUuid = self::normalizeUuid($entryUuid);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            self::lockConnection($context);
            $entry = Model::fetch(
                "SELECT * FROM {$table}
                 WHERE entry_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE",
                [$entryUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$entry || $entry['status'] !== 'pending') {
                throw new DomainException('merchant_commission_entry_not_reviewable');
            }
            if (!$approve) {
                Model::execute("UPDATE {$table} SET status = 'cancelled' WHERE id = ?", [(int) $entry['id']]);
                $status = 'cancelled';
            } else {
                if ($entry['payable_rule'] === 'after_refund_window'
                    && ($entry['payable_at'] === null || strtotime((string) $entry['payable_at']) > time())) {
                    throw new DomainException('merchant_commission_refund_window_open');
                }
                Model::execute("UPDATE {$table} SET status = 'payable', payable_at = COALESCE(payable_at,NOW()) WHERE id = ?", [(int) $entry['id']]);
                $status = 'payable';
            }
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                $ledgerType === 'reward' ? 'commission.reward_reviewed' : 'commission.entry_reviewed',
                $ledgerType === 'reward' ? 'reward_entry' : 'commission_entry', $entryUuid,
                ['status' => $status, 'evidence_reference' => trim($evidenceReference), 'evidence_sha256' => strtolower($evidenceSha256)]
            );
            Model::commit();
            return $status;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    /** Reverses a full unpaid earning with evidence; paid reversals need a separate cash adjustment. */
    public static function reverseUnpaidEntry(
        MerchantAccessContext $context,
        string $ledgerType,
        string $entryUuid,
        string $reason,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        $context->requireScope('commission:reverse');
        self::requireReviewer($reviewerUserId, 'merchant.commission.reverse');
        $table = self::ledgerTable($ledgerType);
        $entryUuid = self::normalizeUuid($entryUuid);
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500) {
            throw new InvalidArgumentException('merchant_commission_reversal_reason_invalid');
        }
        self::validateEvidence($evidenceReference, $evidenceSha256);
        Model::begin();
        try {
            self::lockConnection($context);
            $entry = Model::fetch(
                "SELECT * FROM {$table}
                 WHERE entry_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE",
                [$entryUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
            );
            if (!$entry) {
                throw new DomainException('merchant_commission_entry_not_found');
            }
            if ($entry['status'] === 'reversed') {
                Model::commit();
                return true;
            }
            if (!in_array($entry['status'], ['pending','approved','payable'], true)) {
                if ($entry['status'] === 'paid') {
                    throw new DomainException('merchant_commission_paid_reversal_requires_financial_adjustment');
                }
                throw new DomainException('merchant_commission_entry_not_reversible');
            }
            Model::execute("UPDATE {$table} SET status = 'reversed' WHERE id = ?", [(int) $entry['id']]);
            MerchantAuditLog::append(
                $context->merchantId(), $context->siteId(), $context->connectionId(), $reviewerUserId,
                $ledgerType === 'reward' ? 'commission.reward_reversed' : 'commission.entry_reversed',
                $ledgerType === 'reward' ? 'reward_entry' : 'commission_entry', $entryUuid,
                [
                    'reason' => $reason, 'amount_toman' => (int) $entry['amount_toman'],
                    'evidence_reference' => trim($evidenceReference), 'evidence_sha256' => strtolower($evidenceSha256),
                ]
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function insertEntry(
        MerchantAccessContext $context,
        array $link,
        array $policyRow,
        array $rule,
        string $sourceType,
        string $sourceReference,
        int $sourceAmountToman,
        array $basis,
        int $amountToman,
        bool $reward,
        ?int $actorUserId,
        ?int $recipientUserOverride = null
    ): array {
        $table = $reward ? 'merchant_reward_entries' : 'merchant_commission_entries';
        $existing = Model::fetch(
            "SELECT entry_uuid, source_amount_toman, policy_version_id
             FROM {$table} WHERE connection_id = ? AND rule_code = ? AND source_reference = ? FOR UPDATE",
            [$context->connectionId(), $rule['rule_code'], $sourceReference]
        );
        if ($existing) {
            if ((int) $existing['source_amount_toman'] !== $sourceAmountToman
                || (int) $existing['policy_version_id'] !== (int) $policyRow['id']) {
                throw new DomainException('merchant_commission_idempotency_conflict');
            }
            return ['entry_uuid' => (string) $existing['entry_uuid'], 'amount_toman' => $amountToman, 'idempotent' => true];
        }
        $entryUuid = MerchantAuditLog::newUuid();
        $eventUuid = MerchantAuditLog::newUuid();
        $payableAt = $rule['payable_rule'] === 'on_recognition'
            ? 'NOW()'
            : ($rule['payable_rule'] === 'after_refund_window'
                ? 'DATE_ADD(NOW(), INTERVAL ' . (int) $rule['refund_window_days'] . ' DAY)'
                : 'NULL');
        $status = $rule['payable_rule'] === 'on_recognition' ? 'payable' : 'pending';
        $snapshot = json_encode([
            'rule' => $rule, 'basis' => $basis, 'amount_toman' => $amountToman,
            'source_type' => $sourceType, 'source_reference' => $sourceReference,
            'policy_version_id' => (int) $policyRow['id'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payer = $rule['payer'];
        $recipientUser = $rule['payee_type'] === 'referral_member'
            ? $recipientUserOverride
            : ($rule['payee_type'] === 'user' ? $rule['payee_user_id'] : null);
        $recipientMerchant = $rule['payee_type'] === 'merchant' ? $context->merchantId() : null;
        if ($reward) {
            $rewardType = $rule['type'];
            Model::execute(
                "INSERT INTO merchant_reward_entries
                    (entry_uuid,merchant_id,site_id,connection_id,policy_version_id,policy_type,
                     contract_link_id,contract_id,rule_code,reward_type,payer_type,recipient_user_id,recipient_merchant_id,
                     source_type,source_event_uuid,source_reference,source_amount_toman,calculation_snapshot_json,
                     payable_rule,refund_window_days,amount_toman,reversal_policy,status,recognized_at,payable_at,created_by)
                 VALUES (?,?,?,?,?,'commission',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'{$status}',NOW(),{$payableAt},?)",
                [
                    $entryUuid, $context->merchantId(), $context->siteId(), $context->connectionId(), (int) $policyRow['id'],
                    (int) $link['id'], (int) $link['contract_id'], $rule['rule_code'], $rewardType, $payer,
                    $recipientUser, $recipientMerchant, $sourceType, $eventUuid, $sourceReference, $sourceAmountToman,
                    $snapshot, $rule['payable_rule'], $rule['refund_window_days'], $amountToman,
                    $rule['reversal_policy'], $actorUserId,
                ]
            );
        } else {
            Model::execute(
                "INSERT INTO merchant_commission_entries
                    (entry_uuid,merchant_id,site_id,connection_id,policy_version_id,policy_type,
                     contract_link_id,contract_id,rule_code,commission_type,payer_type,recipient_user_id,recipient_merchant_id,
                     source_type,source_event_uuid,source_reference,source_amount_toman,calculation_snapshot_json,
                     payable_rule,refund_window_days,cash_volume_window,amount_toman,reversal_policy,status,
                     recognized_at,payable_at,created_by)
                 VALUES (?,?,?,?,?,'commission',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?,?,'{$status}',NOW(),{$payableAt},?)",
                [
                    $entryUuid, $context->merchantId(), $context->siteId(), $context->connectionId(), (int) $policyRow['id'],
                    (int) $link['id'], (int) $link['contract_id'], $rule['rule_code'], $rule['type'], $payer,
                    $recipientUser, $recipientMerchant, $sourceType, $eventUuid, $sourceReference, $sourceAmountToman,
                    $snapshot, $rule['payable_rule'], $rule['refund_window_days'], $basis['cash_volume_window'] ?? null,
                    $amountToman, $rule['reversal_policy'], $actorUserId,
                ]
            );
        }
        MerchantAuditLog::append(
            $context->merchantId(), $context->siteId(), $context->connectionId(), $actorUserId,
            $reward ? 'commission.reward_recorded' : 'commission.entry_recorded',
            $reward ? 'reward_entry' : 'commission_entry', $entryUuid,
            ['rule_code' => $rule['rule_code'], 'amount_toman' => $amountToman, 'source_reference' => $sourceReference]
        );
        return ['entry_uuid' => $entryUuid, 'amount_toman' => $amountToman, 'status' => $status, 'idempotent' => false];
    }

    private static function loadSource(MerchantAccessContext $context, array $link, string $type, string $identifier): array
    {
        if ($type === 'contract_activated') {
            if (!ctype_digit($identifier) || (int) $identifier !== (int) $link['contract_id']
                || $link['contract_status'] !== 'active') {
                throw new DomainException('merchant_commission_contract_source_unverified');
            }
            return ['reference' => 'contract:' . (int) $link['contract_id'] . ':activated', 'amount_toman' => 0];
        }
        if ($type === 'settlement_paid') {
            $batchUuid = self::normalizeUuid($identifier);
            $batch = Model::fetch(
                "SELECT b.batch_uuid, b.net_settlement_toman FROM merchant_settlement_batches b
                 INNER JOIN merchant_settlement_lines l ON l.batch_id = b.id
                 WHERE b.batch_uuid = ? AND b.merchant_id = ? AND b.site_id = ? AND b.connection_id = ?
                   AND b.status = 'paid' AND l.contract_link_id = ? FOR UPDATE",
                [$batchUuid, $context->merchantId(), $context->siteId(), $context->connectionId(), (int) $link['id']]
            );
            if (!$batch) {
                throw new DomainException('merchant_commission_settlement_source_unverified');
            }
            return ['reference' => 'settlement:' . $batchUuid, 'amount_toman' => (int) $batch['net_settlement_toman']];
        }
        if ($type === 'installment_collected') {
            if (!ctype_digit($identifier) || (int) $identifier < 1) {
                throw new InvalidArgumentException('merchant_commission_payment_source_invalid');
            }
            $payment = Model::fetch(
                "SELECT id, amount FROM payments
                 WHERE id = ? AND contract_id = ? AND installment_id IS NOT NULL
                   AND payment_type = 'installment' AND status = 'paid' AND COALESCE(is_corrected,0) = 0
                 FOR UPDATE",
                [(int) $identifier, (int) $link['contract_id']]
            );
            if (!$payment) {
                throw new DomainException('merchant_commission_payment_source_unverified');
            }
            return ['reference' => 'payment:' . (int) $payment['id'], 'amount_toman' => MoneyMath::amount($payment['amount'])];
        }
        throw new InvalidArgumentException('merchant_commission_source_type_invalid');
    }

    private static function lockLink(MerchantAccessContext $context, int $contractLinkId): array
    {
        $link = Model::fetch(
            "SELECT l.id,l.contract_id,c.customer_id,l.order_status,c.status AS contract_status,c.created_at AS contract_created_at
             FROM merchant_contract_links l INNER JOIN contracts c ON c.id = l.contract_id
             WHERE l.id = ? AND l.merchant_id = ? AND l.site_id = ? AND l.connection_id = ?
               AND l.order_status = 'contracted' FOR UPDATE",
            [$contractLinkId, $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$link) {
            throw new DomainException('merchant_commission_contract_link_unavailable');
        }
        return $link;
    }

    private static function loadVerifiedObligation(MerchantAccessContext $context, array $link): array
    {
        $obligation = Model::fetch(
            "SELECT cash_product_price_toman,verified_down_payment_toman,down_payment_status,contract_id,created_at
             FROM merchant_settlement_obligations
             WHERE contract_link_id = ? AND merchant_id = ? AND site_id = ? AND connection_id = ? FOR UPDATE",
            [(int) $link['id'], $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$obligation || $obligation['down_payment_status'] !== 'verified'
            || (int) $obligation['cash_product_price_toman'] < 1) {
            throw new DomainException('merchant_commission_cash_price_evidence_unavailable');
        }
        return $obligation;
    }

    private static function monthlyCashVolume(MerchantAccessContext $context, int $contractId, string $contractCreatedAt): int
    {
        $window = date('Y-m', strtotime($contractCreatedAt));
        $row = Model::fetch(
            "SELECT COALESCE(SUM(o.cash_product_price_toman),0) AS volume
             FROM merchant_settlement_obligations o
             INNER JOIN contracts c ON c.id = o.contract_id
             WHERE o.merchant_id = ? AND o.site_id = ? AND o.connection_id = ?
               AND o.down_payment_status = 'verified' AND c.status = 'active'
               AND DATE_FORMAT(c.created_at,'%Y-%m') = ?",
            [$context->merchantId(), $context->siteId(), $context->connectionId(), $window]
        );
        return (int) ($row['volume'] ?? 0);
    }

    private static function findRewardRule(array $policy, string $ruleCode, string $event, int $memberUserId): array
    {
        foreach ($policy['reward_rules'] as $rule) {
            if ($rule['rule_code'] !== $ruleCode) {
                continue;
            }
            if ($rule['type'] !== 'fixed_per_referral' || $rule['recognition_event'] !== $event) {
                throw new DomainException('merchant_reward_rule_source_mismatch');
            }
            if ($rule['payee_type'] === 'user' && $rule['payee_user_id'] !== $memberUserId) {
                throw new DomainException('merchant_reward_recipient_mismatch');
            }
            if (!in_array($rule['payee_type'], ['user','referral_member'], true)) {
                throw new DomainException('merchant_reward_recipient_invalid');
            }
            return $rule;
        }
        throw new DomainException('merchant_reward_rule_unavailable');
    }

    private static function assertRuleEventCompatible(array $rule, string $sourceType): void
    {
        if ($rule['type'] === 'installments_collected_percent' && $sourceType !== 'installment_collected') {
            throw new DomainException('merchant_commission_rule_source_mismatch');
        }
        if ($rule['type'] !== 'installments_collected_percent' && $sourceType === 'installment_collected') {
            throw new DomainException('merchant_commission_rule_source_mismatch');
        }
    }

    private static function lockConnection(MerchantAccessContext $context): void
    {
        $row = Model::fetch(
            "SELECT id FROM merchant_connections
             WHERE id = ? AND merchant_id = ? AND site_id = ? AND status = 'active' AND emergency_disabled = 0
             FOR UPDATE",
            [$context->connectionId(), $context->merchantId(), $context->siteId()]
        );
        if (!$row) {
            throw new DomainException('merchant_connection_not_available');
        }
    }

    private static function lockPolicy(MerchantAccessContext $context): array
    {
        $row = Model::fetch(
            "SELECT p.* FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p ON p.id = h.policy_version_id
             WHERE h.connection_id = ? AND h.policy_type = 'commission' AND p.status = 'published' FOR UPDATE",
            [$context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_commission_policy_unavailable');
        }
        return $row;
    }

    private static function decodePolicy(array $row): array
    {
        try {
            $document = json_decode((string) $row['policy_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('merchant_commission_policy_invalid', 0, $e);
        }
        if (!is_array($document)) {
            throw new DomainException('merchant_commission_policy_invalid');
        }
        return MerchantCommissionPolicy::resolve($document);
    }

    private static function ledgerTable(string $type): string
    {
        return [
            'commission' => 'merchant_commission_entries',
            'reward' => 'merchant_reward_entries',
        ][$type] ?? throw new InvalidArgumentException('merchant_commission_ledger_type_invalid');
    }

    private static function validateEvent(string $type, int $linkId, ?int $actorUserId): void
    {
        if ($linkId < 1 || !in_array($type, ['contract_activated','settlement_paid','installment_collected'], true)
            || ($actorUserId !== null && $actorUserId < 1)) {
            throw new InvalidArgumentException('merchant_commission_source_invalid');
        }
    }

    private static function requireReviewer(int $actorUserId, string $permission): void
    {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId) {
            throw new DomainException('merchant_commission_reviewer_required');
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
            throw new DomainException('merchant_commission_actor_denied');
        }
    }

    private static function validateEvidence(string $reference, string $hash): void
    {
        self::requiredReference($reference);
        self::normalizeHash($hash);
    }

    private static function requiredReference($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', trim($value))) {
            throw new InvalidArgumentException('merchant_commission_reference_invalid');
        }
        return trim($value);
    }

    private static function normalizeHash($value): string
    {
        if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/iD', $value)) {
            throw new InvalidArgumentException('merchant_commission_evidence_hash_invalid');
        }
        return strtolower($value);
    }

    private static function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('merchant_commission_uuid_invalid');
        }
        return $value;
    }
}
