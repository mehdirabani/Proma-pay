<?php

declare(strict_types=1);

/** Hash-only credential storage and tenant-bound authentication. */
final class MerchantApiCredentialRepository
{
    public static function issueForConnection(
        int $connectionId,
        array $scopes,
        ?int $issuedBy,
        DateTimeImmutable $expiresAt,
        string $environment = 'test'
    ): array {
        if ($connectionId < 1 || ($issuedBy !== null && $issuedBy < 1)) {
            throw new InvalidArgumentException('merchant_credential_issue_invalid');
        }
        if ($environment !== 'test') {
            throw new LogicException('merchant_live_credentials_require_phase5_approval');
        }
        $now = new DateTimeImmutable('now');
        if ($expiresAt <= $now || $expiresAt > $now->modify('+30 days')) {
            throw new InvalidArgumentException('merchant_test_credential_expiry_invalid');
        }
        $scopesJson = MerchantApiCredentialToken::encodeScopes($scopes);
        $expiresAtSql = $expiresAt->format('Y-m-d H:i:s');
        $credentialUuid = MerchantAuditLog::newUuid();

        Model::begin();
        try {
            $connection = Model::fetch(
                "SELECT id, merchant_id, site_id, status
                 FROM merchant_connections WHERE id = ? AND status <> 'revoked' FOR UPDATE",
                [$connectionId]
            );
            if (!$connection) {
                throw new DomainException('merchant_connection_not_available');
            }

            $issued = MerchantApiCredentialToken::issue('test');
            Model::execute(
                'INSERT INTO merchant_api_credentials
                    (connection_id, credential_uuid, token_prefix, token_hash, environment, scopes_json, status, issued_by, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $connectionId,
                    $credentialUuid,
                    $issued['prefix'],
                    $issued['hash'],
                    'test',
                    $scopesJson,
                    'active',
                    $issuedBy,
                    $expiresAtSql,
                ]
            );

            MerchantAuditLog::append(
                (int) $connection['merchant_id'],
                (int) $connection['site_id'],
                $connectionId,
                $issuedBy,
                'credential.issued',
                'credential',
                $credentialUuid,
                ['environment' => 'test', 'scopes' => json_decode($scopesJson, true)]
            );
            Model::commit();
            return [
                'credential_uuid' => $credentialUuid,
                'token' => $issued['token'],
                'token_prefix' => $issued['prefix'],
                'scopes' => json_decode($scopesJson, true),
                'expires_at' => $expiresAtSql,
            ];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function revoke(string $credentialUuid, ?int $actorUserId): bool
    {
        $credentialUuid = trim($credentialUuid);
        if (!preg_match('/^[0-9a-f-]{36}$/iD', $credentialUuid)
            || ($actorUserId !== null && $actorUserId < 1)) {
            throw new InvalidArgumentException('merchant_credential_revoke_invalid');
        }

        Model::begin();
        try {
            $credential = Model::fetch(
                'SELECT k.id, k.credential_uuid, k.status, c.id AS connection_id,
                        c.merchant_id, c.site_id
                 FROM merchant_api_credentials k
                 INNER JOIN merchant_connections c ON c.id = k.connection_id
                 WHERE k.credential_uuid = ? FOR UPDATE',
                [$credentialUuid]
            );
            if (!$credential || $credential['status'] === 'revoked') {
                Model::commit();
                return false;
            }

            Model::execute(
                "UPDATE merchant_api_credentials
                 SET status = 'revoked', revoked_by = ?, revoked_at = NOW()
                 WHERE id = ? AND status = 'active'",
                [$actorUserId, (int) $credential['id']]
            );
            MerchantAuditLog::append(
                (int) $credential['merchant_id'],
                (int) $credential['site_id'],
                (int) $credential['connection_id'],
                $actorUserId,
                'credential.revoked',
                'credential',
                (string) $credential['credential_uuid']
            );
            Model::commit();
            return true;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function authenticate(string $token): ?MerchantAccessContext
    {
        try {
            $tokenHash = MerchantApiCredentialToken::hashForLookup($token);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        $row = Model::fetch(
            "SELECT k.id AS credential_id, k.scopes_json, c.id AS connection_id,
                    c.merchant_id, c.site_id
             FROM merchant_api_credentials k
             INNER JOIN merchant_connections c ON c.id = k.connection_id
             INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
             INNER JOIN merchant_accounts m ON m.id = c.merchant_id
             WHERE k.token_hash = ?
               AND k.environment = 'test'
               AND k.status = 'active'
               AND (k.expires_at IS NULL OR k.expires_at > NOW())
               AND c.status = 'active'
               AND c.emergency_disabled = 0
               AND s.status = 'active'
               AND s.verification_status = 'verified'
               AND m.status = 'active'
             LIMIT 1",
            [$tokenHash]
        );
        if (!$row) {
            return null;
        }

        $scopes = MerchantApiCredentialToken::decodeScopes((string) $row['scopes_json']);
        Model::execute(
            'UPDATE merchant_api_credentials SET last_used_at = NOW() WHERE id = ? AND status = ?',
            [(int) $row['credential_id'], 'active']
        );

        return new MerchantAccessContext(
            (int) $row['merchant_id'],
            (int) $row['site_id'],
            (int) $row['connection_id'],
            $scopes
        );
    }
}
