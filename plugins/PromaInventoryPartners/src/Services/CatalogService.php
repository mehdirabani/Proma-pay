<?php

namespace Proma\Plugins\InventoryPartners\Services;

final class CatalogService
{
    private const TABLES = [
        'brand' => 'plugin_inventory_brands',
        'category' => 'plugin_inventory_categories',
    ];

    public static function create($kind, $name, $actorId)
    {
        $table = self::table($kind);
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($name === '' || $length > 190) {
            throw new \InvalidArgumentException('نام برند یا دسته‌بندی معتبر نیست.');
        }
        if (\Model::fetch("SELECT id FROM {$table} WHERE name = ? LIMIT 1", [$name])) {
            throw new \InvalidArgumentException('این نام قبلاً ثبت شده است.');
        }
        try {
            \Model::execute("INSERT INTO {$table} (name, status, created_by, created_at) VALUES (?, 'active', ?, NOW())", [
                $name, $actorId ? (int) $actorId : null,
            ]);
        } catch (\Throwable $error) {
            // The unique key also closes the race between two concurrent submissions.
            if ((string) $error->getCode() === '23000' || (int) $error->getCode() === 1062) {
                throw new \InvalidArgumentException('این نام قبلاً ثبت شده است.');
            }
            throw $error;
        }
        $id = (int) \Model::lastInsertId();
        AuditService::record($kind . '_created', $kind, $id, $actorId, [], ['name' => $name]);
        return $id;
    }

    public static function search($kind, $query = '', $limit = 30)
    {
        $table = self::table($kind);
        $query = trim((string) $query);
        $limit = max(1, min(100, (int) $limit));
        if ($query === '') {
            return \Model::fetchAll("SELECT id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC, id ASC LIMIT {$limit}");
        }
        $like = self::escapeLike($query) . '%';
        return \Model::fetchAll("SELECT id, name FROM {$table} WHERE status = 'active' AND name LIKE ? ESCAPE '!' ORDER BY name ASC, id ASC LIMIT {$limit}", [$like]);
    }

    public static function active($kind, $limit = 500)
    {
        $table = self::table($kind);
        $limit = max(1, min(1000, (int) $limit));
        return \Model::fetchAll("SELECT id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC, id ASC LIMIT {$limit}");
    }

    public static function findActive($kind, $id)
    {
        $table = self::table($kind);
        $id = (int) $id;
        return $id > 0 ? \Model::fetch("SELECT id, name FROM {$table} WHERE id = ? AND status = 'active' LIMIT 1", [$id]) : null;
    }

    public static function findByName($kind, $name)
    {
        $table = self::table($kind);
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        return $name !== '' ? \Model::fetch("SELECT id, name FROM {$table} WHERE name = ? AND status = 'active' LIMIT 1", [$name]) : null;
    }

    private static function table($kind)
    {
        $kind = (string) $kind;
        if (!isset(self::TABLES[$kind])) {
            throw new \InvalidArgumentException('نوع فهرست کالا معتبر نیست.');
        }
        return self::TABLES[$kind];
    }

    private static function escapeLike($value)
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $value);
    }
}
