<?php

namespace Proma\Plugins\InventoryPartners\Services;

final class AuditService
{
    public static function record($action, $objectType, $objectId, $actorId, array $before = [], array $after = [])
    {
        \Model::execute(
            'INSERT INTO plugin_inventory_audit_logs (event_uuid, action, object_type, object_id, actor_user_id, before_json, after_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                bin2hex(random_bytes(16)),
                substr(trim((string) $action), 0, 80),
                substr(trim((string) $objectType), 0, 60),
                $objectId ? (int) $objectId : null,
                $actorId ? (int) $actorId : null,
                $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
                $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
            ]
        );
    }
}
