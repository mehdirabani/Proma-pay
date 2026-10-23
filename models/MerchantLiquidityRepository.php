<?php

declare(strict_types=1);

/** Verified bank evidence and balance projections for available/reserved cash. */
final class MerchantLiquidityRepository
{
    public static function createAccount(
        string $accountCode,
        string $displayName,
        string $ownerType,
        ?int $merchantId,
        ?int $siteId,
        ?int $connectionId,
        ?string $bankAccountReference,
        int $actorUserId
    ): array {
        self::requireReviewer($actorUserId, 'merchant.liquidity.account.manage');
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/D', $accountCode)
            || trim($displayName) === '' || strlen(trim($displayName)) > 190
            || !in_array($ownerType, ['platform', 'merchant', 'third_party_funder'], true)
            || ($ownerType === 'platform' && ($merchantId !== null || $siteId !== null || $connectionId !== null))
            || ($ownerType !== 'platform' && ($merchantId === null || $merchantId < 1))
            || ($connectionId !== null && ($siteId === null || $connectionId < 1))
            || ($siteId !== null && $siteId < 1)) {
            throw new InvalidArgumentException('merchant_liquidity_account_invalid');
        }
        $bankAccountReference = $bankAccountReference !== null ? trim($bankAccountReference) : null;
        if ($bankAccountReference !== null
            && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', $bankAccountReference)) {
            throw new InvalidArgumentException('merchant_liquidity_bank_reference_invalid');
        }
        $uuid = MerchantAuditLog::newUuid();
        Model::execute(
            "INSERT INTO merchant_liquidity_accounts
                (account_uuid,owner_type,merchant_id,site_id,connection_id,account_code,display_name,
                 bank_account_reference,currency_unit,status,created_by)
             VALUES (?,?,?,?,?,?,?,?,'toman','pending',?)",
            [
                $uuid, $ownerType, $merchantId, $siteId, $connectionId, $accountCode, trim($displayName),
                $bankAccountReference, $actorUserId,
            ]
        );
        $accountId = (int) Model::lastInsertId();
        if ($merchantId !== null) {
            MerchantAuditLog::append(
                $merchantId, $siteId, $connectionId, $actorUserId, 'liquidity.account_created',
                'liquidity_account', $uuid, ['owner_type' => $ownerType, 'account_code' => $accountCode]
            );
        }
        return ['id' => $accountId, 'account_uuid' => $uuid, 'status' => 'pending'];
    }

    public static function activateAccount(
        string $accountUuid,
        string $verificationReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): bool {
        self::requireReviewer($reviewerUserId, 'merchant.liquidity.account.verify');
        $accountUuid = self::normalizeUuid($accountUuid);
        self::validateEvidence($verificationReference, $evidenceSha256);
        Model::begin();
        try {
            $account = Model::fetch(
                'SELECT * FROM merchant_liquidity_accounts WHERE account_uuid = ? FOR UPDATE',
                [$accountUuid]
            );
            if (!$account) {
                throw new DomainException('merchant_liquidity_account_not_found');
            }
            if ($account['status'] === 'active') {
                if ((string) ($account['verification_reference'] ?? '') !== $verificationReference
                    || !hash_equals((string) ($account['verification_evidence_sha256'] ?? ''), strtolower($evidenceSha256))) {
                    throw new DomainException('merchant_liquidity_account_verification_conflict');
                }
                Model::commit();
                return true;
            }
            if ($account['status'] !== 'pending') {
                throw new DomainException('merchant_liquidity_account_not_activatable');
            }
            Model::execute(
                "UPDATE merchant_liquidity_accounts
                 SET status = 'active', verification_reference = ?, verification_evidence_sha256 = ?,
                     verified_by = ?, verified_at = NOW()
                 WHERE id = ? AND status = 'pending'",
                [$verificationReference, strtolower($evidenceSha256), $reviewerUserId, (int) $account['id']]
            );
            if ($account['merchant_id'] !== null) {
                MerchantAuditLog::append(
                    (int) $account['merchant_id'],
                    $account['site_id'] !== null ? (int) $account['site_id'] : null,
                    $account['connection_id'] !== null ? (int) $account['connection_id'] : null,
                    $reviewerUserId, 'liquidity.account_activated', 'liquidity_account', $accountUuid,
                    ['verification_reference' => $verificationReference, 'evidence_sha256' => strtolower($evidenceSha256)]
                );
            }
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function recordVerifiedFunding(
        string $accountUuid,
        string $sourceEventKey,
        string $bankReference,
        int $amountToman,
        string $evidenceReference,
        string $evidenceSha256,
        int $reviewerUserId
    ): array {
        self::requireReviewer($reviewerUserId, 'merchant.liquidity.funding.verify');
        $accountUuid = self::normalizeUuid($accountUuid);
        $sourceEventKey = self::requiredReference($sourceEventKey);
        $bankReference = self::requiredReference($bankReference);
        self::validateEvidence($evidenceReference, $evidenceSha256);
        if ($amountToman < 1) {
            throw new InvalidArgumentException('merchant_liquidity_funding_amount_invalid');
        }

        Model::begin();
        try {
            $account = Model::fetch(
                'SELECT * FROM merchant_liquidity_accounts WHERE account_uuid = ? FOR UPDATE',
                [$accountUuid]
            );
            if (!$account || $account['status'] !== 'active') {
                throw new DomainException('merchant_liquidity_account_unavailable');
            }
            $existing = Model::fetch(
                'SELECT event_uuid,cash_delta_toman,external_reference,evidence_sha256
                 FROM merchant_liquidity_events WHERE account_id = ? AND source_event_key = ? FOR UPDATE',
                [(int) $account['id'], $sourceEventKey]
            );
            if ($existing) {
                if ((int) $existing['cash_delta_toman'] !== $amountToman
                    || (string) $existing['external_reference'] !== $bankReference
                    || !hash_equals((string) $existing['evidence_sha256'], strtolower($evidenceSha256))) {
                    throw new DomainException('merchant_liquidity_funding_idempotency_conflict');
                }
                Model::commit();
                return ['event_uuid' => (string) $existing['event_uuid'], 'idempotent' => true];
            }
            $cashAfter = self::safeAdd((int) $account['cash_balance_toman'], $amountToman);
            $availableAfter = self::safeAdd((int) $account['available_balance_toman'], $amountToman);
            $reserved = (int) $account['reserved_balance_toman'];
            Model::execute(
                'UPDATE merchant_liquidity_accounts SET cash_balance_toman = ?, available_balance_toman = ? WHERE id = ?',
                [$cashAfter, $availableAfter, (int) $account['id']]
            );
            $eventUuid = MerchantAuditLog::newUuid();
            Model::execute(
                "INSERT INTO merchant_liquidity_events
                    (event_uuid,account_id,event_type,source_event_key,external_reference,evidence_reference,evidence_sha256,
                     cash_delta_toman,available_delta_toman,reserved_delta_toman,cash_balance_after_toman,
                     available_balance_after_toman,reserved_balance_after_toman,actor_user_id,verified_by,verified_at)
                 VALUES (?,?,'funding_verified',?,?,?,?,?,?,0,?,?,?, ?,?,NOW())",
                [
                    $eventUuid, (int) $account['id'], $sourceEventKey, $bankReference, trim($evidenceReference),
                    strtolower($evidenceSha256), $amountToman, $amountToman, $cashAfter, $availableAfter,
                    $reserved, $reviewerUserId, $reviewerUserId,
                ]
            );
            if ($account['merchant_id'] !== null) {
                MerchantAuditLog::append(
                    (int) $account['merchant_id'],
                    $account['site_id'] !== null ? (int) $account['site_id'] : null,
                    $account['connection_id'] !== null ? (int) $account['connection_id'] : null,
                    $reviewerUserId, 'liquidity.funding_verified', 'liquidity_account', $accountUuid,
                    ['bank_reference' => $bankReference, 'amount_toman' => $amountToman, 'event_uuid' => $eventUuid]
                );
            }
            Model::commit();
            return ['event_uuid' => $eventUuid, 'idempotent' => false];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function requireReviewer(int $actorUserId, string $permission): void
    {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId) {
            throw new DomainException('merchant_liquidity_reviewer_required');
        }
        PluginManager::requirePermission($permission);
    }

    private static function validateEvidence(string $reference, string $hash): void
    {
        self::requiredReference($reference);
        self::normalizeHash($hash);
    }

    private static function normalizeHash($value): string
    {
        if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/iD', $value)) {
            throw new InvalidArgumentException('merchant_liquidity_evidence_hash_invalid');
        }
        return strtolower($value);
    }

    private static function requiredReference($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', trim($value))) {
            throw new InvalidArgumentException('merchant_liquidity_reference_invalid');
        }
        return trim($value);
    }

    private static function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('merchant_liquidity_account_uuid_invalid');
        }
        return $value;
    }

    private static function safeAdd(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new OverflowException('merchant_liquidity_amount_overflow');
        }
        return $left + $right;
    }
}
