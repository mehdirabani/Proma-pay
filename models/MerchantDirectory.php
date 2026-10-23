<?php

declare(strict_types=1);

/** Creates inactive merchant configuration and immutable draft policy versions. */
final class MerchantDirectory
{
    public static function createMerchant(array $input, ?int $actorUserId): int
    {
        $code = self::normalizeCode($input['merchant_code'] ?? '');
        $name = self::requiredText($input['display_name'] ?? '', 190, 'merchant_display_name_invalid');
        $legalName = self::optionalText($input['legal_name'] ?? null, 190, 'merchant_legal_name_invalid');
        $email = self::optionalText($input['contact_email'] ?? null, 190, 'merchant_email_invalid');
        $mobile = self::optionalText($input['contact_mobile'] ?? null, 32, 'merchant_mobile_invalid');
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('merchant_email_invalid');
        }
        self::validateActor($actorUserId);

        $uuid = MerchantAuditLog::newUuid();
        return self::transaction(function () use ($code, $name, $legalName, $email, $mobile, $actorUserId, $uuid): int {
            Model::execute(
                'INSERT INTO merchant_accounts
                    (merchant_uuid, merchant_code, display_name, legal_name, contact_email, contact_mobile, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$uuid, $code, $name, $legalName, $email, $mobile, 'pending', $actorUserId]
            );
            $merchantId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                $merchantId,
                null,
                null,
                $actorUserId,
                'merchant.created',
                'merchant',
                $uuid,
                ['merchant_code' => $code]
            );
            return $merchantId;
        });
    }

    public static function createSite(int $merchantId, array $input, ?int $actorUserId): int
    {
        if ($merchantId < 1) {
            throw new InvalidArgumentException('merchant_id_invalid');
        }
        $code = self::normalizeCode($input['site_code'] ?? '');
        $name = self::requiredText($input['display_name'] ?? '', 190, 'merchant_site_name_invalid');
        $origin = MerchantSiteOrigin::normalize((string) ($input['site_origin'] ?? ''));
        self::validateActor($actorUserId);
        $uuid = MerchantAuditLog::newUuid();

        return self::transaction(function () use ($merchantId, $code, $name, $origin, $actorUserId, $uuid): int {
            $merchant = Model::fetch(
                "SELECT id, status FROM merchant_accounts WHERE id = ? FOR UPDATE",
                [$merchantId]
            );
            if (!$merchant || !in_array($merchant['status'], ['pending', 'active'], true)) {
                throw new DomainException('merchant_not_available');
            }

            Model::execute(
                'INSERT INTO merchant_sites
                    (merchant_id, site_uuid, site_code, display_name, site_origin, verification_status, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$merchantId, $uuid, $code, $name, $origin, 'unverified', 'pending', $actorUserId]
            );
            $siteId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                $merchantId,
                $siteId,
                null,
                $actorUserId,
                'site.created',
                'site',
                $uuid,
                ['site_code' => $code, 'site_origin' => $origin]
            );
            return $siteId;
        });
    }

    public static function createConnection(int $merchantId, int $siteId, array $input, ?int $actorUserId): int
    {
        if ($merchantId < 1 || $siteId < 1) {
            throw new InvalidArgumentException('merchant_connection_scope_invalid');
        }
        $code = self::normalizeCode($input['connection_code'] ?? '');
        $name = self::requiredText($input['display_name'] ?? '', 190, 'merchant_connection_name_invalid');
        $apiVersion = strtolower(trim((string) ($input['api_version'] ?? 'v1')));
        $rateLimit = (int) ($input['rate_limit_per_minute'] ?? 60);
        if ($apiVersion !== 'v1' || $rateLimit < 1 || $rateLimit > 10000) {
            throw new InvalidArgumentException('merchant_connection_configuration_invalid');
        }
        self::validateActor($actorUserId);
        $uuid = MerchantAuditLog::newUuid();

        return self::transaction(function () use ($merchantId, $siteId, $code, $name, $apiVersion, $rateLimit, $actorUserId, $uuid): int {
            $site = Model::fetch(
                'SELECT s.id, s.status, s.verification_status, m.status AS merchant_status
                 FROM merchant_sites s
                 INNER JOIN merchant_accounts m ON m.id = s.merchant_id
                 WHERE s.id = ? AND s.merchant_id = ? FOR UPDATE',
                [$siteId, $merchantId]
            );
            if (!$site
                || !in_array($site['merchant_status'], ['pending', 'active'], true)
                || !in_array($site['status'], ['pending', 'active'], true)
                || $site['verification_status'] === 'rejected') {
                throw new DomainException('merchant_site_not_available');
            }

            Model::execute(
                'INSERT INTO merchant_connections
                    (merchant_id, site_id, connection_uuid, connection_code, display_name, api_version, status, rate_limit_per_minute, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$merchantId, $siteId, $uuid, $code, $name, $apiVersion, 'inactive', $rateLimit, $actorUserId]
            );
            $connectionId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                $merchantId,
                $siteId,
                $connectionId,
                $actorUserId,
                'connection.created',
                'connection',
                $uuid,
                ['connection_code' => $code, 'api_version' => $apiVersion]
            );
            return $connectionId;
        });
    }

    public static function grantMembership(int $merchantId, int $userId, string $role, ?int $actorUserId): int
    {
        if ($merchantId < 1 || $userId < 1 || !in_array($role, MerchantMembershipPolicy::roles(), true)) {
            throw new InvalidArgumentException('merchant_membership_invalid');
        }
        self::validateActor($actorUserId);

        return self::transaction(function () use ($merchantId, $userId, $role, $actorUserId): int {
            $merchant = Model::fetch('SELECT id, status FROM merchant_accounts WHERE id = ? FOR UPDATE', [$merchantId]);
            $user = Model::fetch("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1", [$userId]);
            if (!$merchant || !in_array($merchant['status'], ['pending', 'active'], true) || !$user) {
                throw new DomainException('merchant_membership_subject_unavailable');
            }

            Model::execute(
                'INSERT INTO merchant_memberships (merchant_id, user_id, membership_role, status, granted_by)
                 VALUES (?, ?, ?, ?, ?)',
                [$merchantId, $userId, $role, 'invited', $actorUserId]
            );
            $membershipId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                $merchantId,
                null,
                null,
                $actorUserId,
                'membership.invited',
                'membership',
                null,
                ['membership_id' => $membershipId, 'user_id' => $userId, 'role' => $role]
            );
            return $membershipId;
        });
    }

    public static function createPolicyDraft(int $connectionId, string $policyType, array $policy, ?int $actorUserId): array
    {
        if ($connectionId < 1) {
            throw new InvalidArgumentException('merchant_connection_id_invalid');
        }
        self::validateActor($actorUserId);
        $snapshot = MerchantPolicyDocument::canonicalize($policyType, $policy);
        $uuid = MerchantAuditLog::newUuid();

        return self::transaction(function () use ($connectionId, $snapshot, $actorUserId, $uuid): array {
            $connection = Model::fetch(
                "SELECT id, merchant_id, site_id FROM merchant_connections
                 WHERE id = ? AND status <> 'revoked' FOR UPDATE",
                [$connectionId]
            );
            if (!$connection) {
                throw new DomainException('merchant_connection_not_available');
            }

            $latest = Model::fetch(
                'SELECT COALESCE(MAX(version_no), 0) AS max_version
                 FROM merchant_policy_versions
                 WHERE connection_id = ? AND policy_type = ?',
                [$connectionId, $snapshot['type']]
            );
            $version = (int) ($latest['max_version'] ?? 0) + 1;

            Model::execute(
                'INSERT INTO merchant_policy_versions
                    (connection_id, policy_version_uuid, policy_type, version_no, policy_json, content_sha256, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$connectionId, $uuid, $snapshot['type'], $version, $snapshot['json'], $snapshot['sha256'], 'draft', $actorUserId]
            );
            $policyVersionId = (int) Model::lastInsertId();
            MerchantAuditLog::append(
                (int) $connection['merchant_id'],
                (int) $connection['site_id'],
                $connectionId,
                $actorUserId,
                'policy.draft_created',
                'policy',
                $uuid,
                [
                    'policy_type' => $snapshot['type'],
                    'version_no' => $version,
                    'content_sha256' => $snapshot['sha256'],
                ]
            );
            return [
                'id' => $policyVersionId,
                'policy_version_uuid' => $uuid,
                'policy_type' => $snapshot['type'],
                'version_no' => $version,
                'content_sha256' => $snapshot['sha256'],
                'status' => 'draft',
            ];
        });
    }

    public static function emergencyDisableConnection(int $connectionId, string $reasonCode, ?int $actorUserId): bool
    {
        if ($connectionId < 1
            || !in_array($reasonCode, ['security_incident', 'credential_compromise', 'merchant_request', 'manual_review', 'other'], true)) {
            throw new InvalidArgumentException('merchant_emergency_disable_invalid');
        }
        self::validateActor($actorUserId);

        return self::transaction(function () use ($connectionId, $reasonCode, $actorUserId): bool {
            $connection = Model::fetch(
                'SELECT id, merchant_id, site_id, connection_uuid, emergency_disabled
                 FROM merchant_connections WHERE id = ? FOR UPDATE',
                [$connectionId]
            );
            if (!$connection) {
                throw new DomainException('merchant_connection_not_found');
            }
            if ((int) $connection['emergency_disabled'] === 1) {
                return false;
            }

            Model::execute(
                'UPDATE merchant_connections
                 SET emergency_disabled = 1, emergency_disabled_at = NOW(),
                     emergency_disabled_by = ?, emergency_disable_reason = ?
                 WHERE id = ? AND emergency_disabled = 0',
                [$actorUserId, $reasonCode, $connectionId]
            );
            MerchantAuditLog::append(
                (int) $connection['merchant_id'],
                (int) $connection['site_id'],
                $connectionId,
                $actorUserId,
                'connection.emergency_disabled',
                'connection',
                (string) $connection['connection_uuid'],
                ['reason_code' => $reasonCode]
            );
            return true;
        });
    }

    private static function transaction(callable $callback)
    {
        Model::begin();
        try {
            $result = $callback();
            Model::commit();
            return $result;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function normalizeCode($value): string
    {
        $code = strtolower(trim((string) $value));
        if (strlen($code) > 80 || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/D', $code)) {
            throw new InvalidArgumentException('merchant_code_invalid');
        }
        return $code;
    }

    private static function requiredText($value, int $maxCharacters, string $error): string
    {
        $text = self::optionalText($value, $maxCharacters, $error);
        if ($text === null) {
            throw new InvalidArgumentException($error);
        }
        return $text;
    }

    private static function optionalText($value, int $maxCharacters, string $error): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException($error);
        }
        $text = trim($value);
        if ($text === '') {
            return null;
        }
        $characterCount = preg_match_all('/./us', $text);
        if ($characterCount === false
            || $characterCount > $maxCharacters
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw new InvalidArgumentException($error);
        }
        return $text;
    }

    private static function validateActor(?int $actorUserId): void
    {
        if ($actorUserId !== null && $actorUserId < 1) {
            throw new InvalidArgumentException('merchant_actor_invalid');
        }
    }
}
