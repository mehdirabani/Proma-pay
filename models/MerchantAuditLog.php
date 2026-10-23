<?php

declare(strict_types=1);

/** Append-only merchant audit writer. Metadata rejects credential values. */
final class MerchantAuditLog
{
    public static function append(
        int $merchantId,
        ?int $siteId,
        ?int $connectionId,
        ?int $actorUserId,
        string $eventType,
        string $subjectType,
        ?string $subjectUuid,
        array $metadata = []
    ): void {
        if ($merchantId < 1
            || ($siteId !== null && $siteId < 1)
            || ($connectionId !== null && $connectionId < 1)
            || ($actorUserId !== null && $actorUserId < 1)
            || !preg_match('/^[a-z][a-z0-9_.]{1,79}$/D', $eventType)
            || !preg_match('/^[a-z][a-z0-9_]{1,59}$/D', $subjectType)) {
            throw new InvalidArgumentException('merchant_audit_event_invalid');
        }

        if ($siteId !== null) {
            $site = Model::fetch(
                'SELECT id FROM merchant_sites WHERE id = ? AND merchant_id = ? LIMIT 1',
                [$siteId, $merchantId]
            );
            if (!$site) {
                throw new DomainException('merchant_audit_site_scope_mismatch');
            }
        }

        if ($connectionId !== null) {
            $sql = 'SELECT id FROM merchant_connections WHERE id = ? AND merchant_id = ?';
            $params = [$connectionId, $merchantId];
            if ($siteId !== null) {
                $sql .= ' AND site_id = ?';
                $params[] = $siteId;
            }
            $connection = Model::fetch($sql . ' LIMIT 1', $params);
            if (!$connection) {
                throw new DomainException('merchant_audit_connection_scope_mismatch');
            }
        }

        $metadataJson = MerchantAuditMetadata::encode($metadata);
        Model::execute(
            'INSERT INTO merchant_audit_events
                (event_uuid, merchant_id, site_id, connection_id, actor_user_id, event_type, subject_type, subject_uuid, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                self::newUuid(),
                $merchantId,
                $siteId,
                $connectionId,
                $actorUserId,
                $eventType,
                $subjectType,
                $subjectUuid,
                $metadataJson,
            ]
        );
    }

    public static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
