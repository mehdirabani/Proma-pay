<?php

class PluginRegistry extends Model
{
    public static function tableExists()
    {
        try {
            self::fetch('SELECT 1 FROM system_plugins LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function all()
    {
        try {
            return self::fetchAll('SELECT * FROM system_plugins WHERE deleted_at IS NULL ORDER BY name, id');
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function find($pluginId)
    {
        try {
            return self::fetch('SELECT * FROM system_plugins WHERE plugin_id = ? AND deleted_at IS NULL LIMIT 1', [trim((string) $pluginId)]);
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function upsert(array $manifest, $root, $userId = null)
    {
        self::execute(
            'INSERT INTO system_plugins
             (plugin_id, name, description, version, path, status, installed_at, installed_by, manifest_json, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), version = VALUES(version), path = VALUES(path), manifest_json = VALUES(manifest_json), updated_at = NOW()',
            [
                $manifest['id'],
                $manifest['name'],
                trim((string) ($manifest['description'] ?? '')),
                $manifest['version'],
                str_replace('\\', '/', $root),
                'installed',
                $userId ? (int) $userId : null,
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public static function setStatus($pluginId, $status, $error = null)
    {
        $fields = ['status = ?', 'last_error = ?', 'updated_at = NOW()'];
        $params = [$status, $error ?: null];
        if ($status === 'active') {
            $fields[] = 'activated_at = NOW()';
        } elseif ($status === 'inactive') {
            $fields[] = 'deactivated_at = NOW()';
        }
        $params[] = trim((string) $pluginId);
        self::execute('UPDATE system_plugins SET ' . implode(', ', $fields) . ' WHERE plugin_id = ?', $params);
    }

    public static function migrationDone($pluginId, $migrationName)
    {
        return (bool) self::fetch('SELECT id FROM system_plugin_migrations WHERE plugin_id = ? AND migration_name = ? AND status = ? LIMIT 1', [(string) $pluginId, (string) $migrationName, 'success']);
    }

    public static function recordMigration($pluginId, $migrationName, $checksum, $status, $timeMs = 0, $error = null)
    {
        self::execute(
            'INSERT INTO system_plugin_migrations
             (plugin_id, migration_name, batch, checksum, executed_at, execution_time_ms, status, error_message)
             VALUES (?, ?, 1, ?, NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), executed_at = NOW(), execution_time_ms = VALUES(execution_time_ms), status = VALUES(status), error_message = VALUES(error_message)',
            [(string) $pluginId, (string) $migrationName, (string) $checksum, (int) $timeMs, (string) $status, $error]
        );
    }

    public static function savePermission($pluginId, $permissionKey, $label)
    {
        self::execute(
            'INSERT INTO system_plugin_permissions (plugin_id, permission_key, label, is_active, created_at)
             VALUES (?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1',
            [(string) $pluginId, (string) $permissionKey, trim((string) $label)]
        );
    }

    public static function roleCan($pluginId, $permissionKey, $role)
    {
        try {
            $row = self::fetch(
                'SELECT is_allowed FROM system_plugin_role_permissions
                 WHERE plugin_id = ? AND permission_key = ? AND role = ? LIMIT 1',
                [(string) $pluginId, (string) $permissionKey, (string) $role]
            );
            return $row ? (int) $row['is_allowed'] === 1 : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function logRuntimeError($event, Throwable $error)
    {
        try {
            self::execute(
                'INSERT INTO system_plugin_logs (plugin_id, log_level, log_type, message, context_json, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                ['core', 'error', 'hook', trim((string) $event) . ': ' . $error->getMessage(), json_encode(['class' => get_class($error)], JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $ignored) {
        }
    }
}
