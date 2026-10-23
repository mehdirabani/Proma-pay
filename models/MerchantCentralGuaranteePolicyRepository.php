<?php

declare(strict_types=1);

/** Versioned central limits and the legally approved cheque beneficiary. */
final class MerchantCentralGuaranteePolicyRepository
{
    public static function createDraft(array $input, int $actorUserId): array
    {
        self::requireCentralManager($actorUserId);
        $minimum = MerchantGuaranteeCalculator::parseMultiplierUnits($input['minimum_multiplier'] ?? null);
        $maximum = MerchantGuaranteeCalculator::parseMultiplierUnits($input['maximum_multiplier'] ?? null);
        $default = MerchantGuaranteeCalculator::parseMultiplierUnits($input['default_multiplier'] ?? null);
        if ($minimum > $maximum || $default < $minimum || $default > $maximum
            || $maximum > 4294967295) {
            throw new InvalidArgumentException('merchant_central_guarantee_multiplier_range_invalid');
        }
        $enabledValue = $input['enabled'] ?? false;
        if (!in_array($enabledValue, [true, false, 1, 0, '1', '0'], true)) {
            throw new InvalidArgumentException('merchant_central_guarantee_enabled_invalid');
        }
        $enabled = in_array($enabledValue, [true, 1, '1'], true) ? 1 : 0;
        $beneficiaryName = self::requiredText($input['beneficiary_legal_name'] ?? null, 190);
        $beneficiaryIdentifier = self::normalizeIdentifier($input['beneficiary_identifier'] ?? null);
        $approvalReference = self::requiredReference($input['legal_approval_reference'] ?? null);
        $uuid = MerchantAuditLog::newUuid();

        Model::begin();
        try {
            $latest = Model::fetch('SELECT version_no FROM merchant_central_guarantee_policy_versions ORDER BY version_no DESC LIMIT 1 FOR UPDATE');
            $version = (int) ($latest['version_no'] ?? 0) + 1;
            Model::execute(
                'INSERT INTO merchant_central_guarantee_policy_versions
                    (policy_uuid, version_no, status, enabled, minimum_multiplier_units,
                     maximum_multiplier_units, default_multiplier_units, beneficiary_legal_name,
                     beneficiary_identifier, legal_approval_reference, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$uuid, $version, 'draft', $enabled, $minimum, $maximum, $default,
                    $beneficiaryName, $beneficiaryIdentifier, $approvalReference, $actorUserId]
            );
            $id = (int) Model::lastInsertId();
            self::appendEvent($id, 'policy.draft_created', $actorUserId, [
                'version_no' => $version,
                'enabled' => (bool) $enabled,
                'minimum_multiplier_units' => $minimum,
                'maximum_multiplier_units' => $maximum,
                'default_multiplier_units' => $default,
            ]);
            Model::commit();
            return ['id' => $id, 'policy_uuid' => $uuid, 'version_no' => $version, 'status' => 'draft'];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function publish(string $policyUuid, int $actorUserId): bool
    {
        self::requireCentralManager($actorUserId);
        $policyUuid = self::normalizeUuid($policyUuid);
        Model::begin();
        try {
            $policy = Model::fetch(
                "SELECT * FROM merchant_central_guarantee_policy_versions
                 WHERE policy_uuid = ? AND status = 'draft' FOR UPDATE",
                [$policyUuid]
            );
            if (!$policy) {
                Model::commit();
                return false;
            }
            $minimum = (int) $policy['minimum_multiplier_units'];
            $maximum = (int) $policy['maximum_multiplier_units'];
            $default = (int) $policy['default_multiplier_units'];
            if ($minimum < 1 || $minimum > $default || $default > $maximum
                || self::normalizeIdentifier($policy['beneficiary_identifier'] ?? null) === ''
                || self::requiredReference($policy['legal_approval_reference'] ?? null) === '') {
                throw new DomainException('merchant_central_guarantee_policy_invalid');
            }

            $head = Model::fetch('SELECT policy_version_id FROM merchant_central_guarantee_policy_head WHERE singleton_id = 1 FOR UPDATE');
            if ($head) {
                Model::execute(
                    "UPDATE merchant_central_guarantee_policy_versions SET status = 'retired'
                     WHERE id = ? AND status = 'published'",
                    [(int) $head['policy_version_id']]
                );
            }
            Model::execute(
                "UPDATE merchant_central_guarantee_policy_versions
                 SET status = 'published', published_by = ?, published_at = NOW()
                 WHERE id = ? AND status = 'draft'",
                [$actorUserId, (int) $policy['id']]
            );
            Model::execute(
                'INSERT INTO merchant_central_guarantee_policy_head (singleton_id, policy_version_id, selected_by)
                 VALUES (1, ?, ?)
                 ON DUPLICATE KEY UPDATE policy_version_id = VALUES(policy_version_id),
                                         selected_by = VALUES(selected_by), selected_at = NOW()',
                [(int) $policy['id'], $actorUserId]
            );
            self::appendEvent((int) $policy['id'], 'policy.published', $actorUserId, [
                'version_no' => (int) $policy['version_no'],
                'enabled' => (bool) $policy['enabled'],
            ]);
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function currentPublished(): array
    {
        $policy = Model::fetch(
            "SELECT p.* FROM merchant_central_guarantee_policy_head h
             INNER JOIN merchant_central_guarantee_policy_versions p ON p.id = h.policy_version_id
             WHERE h.singleton_id = 1 AND p.status = 'published' AND p.enabled = 1
             LIMIT 1"
        );
        if (!$policy) {
            throw new DomainException('merchant_central_guarantee_policy_unavailable');
        }
        return $policy;
    }

    private static function appendEvent(int $policyId, string $eventType, int $actorUserId, array $metadata): void
    {
        Model::execute(
            'INSERT INTO merchant_central_guarantee_policy_events
                (policy_version_id, event_type, actor_user_id, metadata_json)
             VALUES (?, ?, ?, ?)',
            [$policyId, $eventType, $actorUserId, MerchantAuditMetadata::encode($metadata)]
        );
    }

    private static function requireCentralManager(int $actorUserId): void
    {
        if ($actorUserId < 1 || !Auth::id() || (int) Auth::id() !== $actorUserId) {
            throw new DomainException('merchant_central_policy_actor_required');
        }
        PluginManager::requirePermission('merchant.guarantee_policy.manage');
    }

    private static function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('merchant_central_policy_uuid_invalid');
        }
        return $value;
    }

    private static function requiredText($value, int $maxCharacters): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('merchant_guarantee_beneficiary_invalid');
        }
        $value = trim($value);
        $count = preg_match_all('/./us', $value);
        if ($value === '' || $count === false || $count > $maxCharacters
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('merchant_guarantee_beneficiary_invalid');
        }
        return $value;
    }

    private static function normalizeIdentifier($value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('merchant_guarantee_beneficiary_invalid');
        }
        $identifier = strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        if (!preg_match('/^\d{5,20}$/D', $identifier)) {
            throw new InvalidArgumentException('merchant_guarantee_beneficiary_invalid');
        }
        return $identifier;
    }

    private static function requiredReference($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', trim($value))) {
            throw new InvalidArgumentException('merchant_guarantee_legal_approval_missing');
        }
        return trim($value);
    }
}
