<?php

class Medal extends Model
{
    public static function definitions($includeInactive = false)
    {
        $where = $includeInactive ? '' : ' WHERE is_active = 1 AND archived_at IS NULL';
        return self::fetchAll('SELECT * FROM medal_definitions' . $where . ' ORDER BY sort_order ASC, id ASC');
    }

    public static function definitionsWithStats($filter = '')
    {
        $params = [];
        $where = 'WHERE md.archived_at IS NULL';
        if (in_array($filter, ['automatic', 'manual'], true)) {
            $where .= ' AND md.award_type = ?';
            $params[] = $filter;
        } elseif ($filter === 'active') {
            $where .= ' AND md.is_active = 1';
        } elseif ($filter === 'inactive') {
            $where .= ' AND md.is_active = 0';
        } elseif ($filter === 'reversible') {
            $where .= " AND md.behavior_type = 'reversible'";
        } elseif ($filter === 'permanent') {
            $where .= " AND md.behavior_type = 'permanent'";
        }
        return self::fetchAll(
            'SELECT md.*,
                    SUM(CASE WHEN um.revoked_at IS NULL THEN 1 ELSE 0 END) AS active_holders,
                    SUM(CASE WHEN um.revoked_at IS NOT NULL THEN 1 ELSE 0 END) AS revoked_holders,
                    MAX(um.awarded_at) AS last_awarded_at
             FROM medal_definitions md
             LEFT JOIN user_medals um ON um.medal_definition_id = md.id
             ' . $where . '
             GROUP BY md.id
             ORDER BY md.sort_order ASC, md.id ASC',
            $params
        );
    }

    public static function managementSummary()
    {
        $definitions = self::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(is_active = 1) AS active,
                    SUM(award_type = 'automatic') AS automatic,
                    SUM(award_type = 'manual') AS manual,
                    SUM(behavior_type = 'reversible') AS reversible,
                    SUM(is_active = 1 AND award_type = 'automatic' AND last_evaluated_at IS NULL) AS pending_reconciliation
             FROM medal_definitions WHERE archived_at IS NULL"
        ) ?: [];
        $awards = self::fetch(
            'SELECT SUM(revoked_at IS NULL) AS awarded, SUM(revoked_at IS NOT NULL) AS revoked FROM user_medals'
        ) ?: [];
        return array_merge([
            'total' => 0,
            'active' => 0,
            'automatic' => 0,
            'manual' => 0,
            'reversible' => 0,
            'pending_reconciliation' => 0,
            'awarded' => 0,
            'revoked' => 0,
        ], $definitions, $awards);
    }

    public static function history($limit = 20)
    {
        return self::fetchAll(
            'SELECT h.*, um.user_id, md.title AS medal_title, md.icon_key, u.full_name AS user_name,
                    actor.full_name AS actor_name
             FROM user_medal_history h
             JOIN user_medals um ON um.id = h.user_medal_id
             JOIN medal_definitions md ON md.id = um.medal_definition_id
             JOIN users u ON u.id = um.user_id
             LEFT JOIN users actor ON actor.id = h.performed_by
             ORDER BY h.id DESC LIMIT ' . max(1, min(100, (int) $limit))
        );
    }

    public static function toggleDefinition($id, $actorId)
    {
        $definition = self::fetch('SELECT * FROM medal_definitions WHERE id = ? AND archived_at IS NULL LIMIT 1', [(int) $id]);
        if (!$definition) {
            throw new InvalidArgumentException('تعریف مدال پیدا نشد.');
        }
        $next = empty($definition['is_active']) ? 1 : 0;
        self::execute('UPDATE medal_definitions SET is_active = ?, updated_at = NOW() WHERE id = ?', [$next, (int) $id]);
        if (class_exists('AuditLog')) {
            AuditLog::record('medal_definition', $next ? 'activated' : 'deactivated', 'medal_definition', (int) $id, [
                'actor_user_id' => (int) $actorId,
                'old_values' => ['is_active' => (int) $definition['is_active']],
                'new_values' => ['is_active' => $next],
            ]);
        }
        return $next === 1;
    }

    public static function synchronizeCustomers($actorId, $userId = null, $limit = 100)
    {
        $params = [];
        $where = "WHERE role = 'customer' AND status = 'active'";
        if ($userId) {
            $where .= ' AND id = ?';
            $params[] = (int) $userId;
        }
        $users = self::fetchAll('SELECT id FROM users ' . $where . ' ORDER BY id ASC LIMIT ' . max(1, min(500, (int) $limit)), $params);
        $processed = 0;
        foreach ($users as $user) {
            self::evaluateCustomer((int) $user['id'], $actorId);
            $processed++;
        }
        if (class_exists('AuditLog')) {
            AuditLog::record('medal', 'synchronized', 'medal_definition', 0, [
                'actor_user_id' => (int) $actorId,
                'new_values' => ['processed_customers' => $processed, 'user_id' => $userId ? (int) $userId : null],
            ]);
        }
        return $processed;
    }

    public static function forUsers(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = self::fetchAll(
            "SELECT um.id, um.user_id, um.source, um.note, um.awarded_at, um.related_contract_id, um.related_payment_id,
                    md.slug, md.title, md.short_description AS description, md.full_description, md.how_to_earn,
                    md.icon_key, md.icon_path, md.color, md.category, md.points
             FROM user_medals um
             JOIN medal_definitions md ON md.id = um.medal_definition_id
             WHERE um.user_id IN ({$placeholders}) AND um.revoked_at IS NULL AND md.is_active = 1
             ORDER BY um.awarded_at DESC, um.id DESC",
            $userIds
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['user_id']][] = $row;
        }
        return $grouped;
    }

    public static function mergeLegacy(array $grouped, array $userIds)
    {
        try {
            $modern = self::forUsers($userIds);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1146) {
                return $grouped;
            }
            throw $e;
        }
        foreach ($modern as $userId => $rows) {
            $grouped[(int) $userId] = array_merge($rows, $grouped[(int) $userId] ?? []);
        }
        return $grouped;
    }

    public static function award($userId, $slug, $source = 'manual', $actorId = null, $note = '', $contractId = null, $paymentId = null)
    {
        $definition = self::fetch('SELECT * FROM medal_definitions WHERE slug = ? AND is_active = 1 AND archived_at IS NULL LIMIT 1', [(string) $slug]);
        if (!$definition) {
            throw new InvalidArgumentException('تعریف مدال پیدا نشد یا غیرفعال است.');
        }
        $started = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $started = true;
        }
        try {
            $definition = self::fetch('SELECT * FROM medal_definitions WHERE id = ? AND is_active = 1 AND archived_at IS NULL FOR UPDATE', [(int) $definition['id']]);
            if (!$definition) {
                throw new InvalidArgumentException('تعریف مدال پیدا نشد یا غیرفعال است.');
            }
            if (!(int) $definition['is_repeatable'] && self::fetch('SELECT id FROM user_medals WHERE user_id = ? AND medal_definition_id = ? AND revoked_at IS NULL LIMIT 1', [(int) $userId, (int) $definition['id']])) {
                if ($started) {
                    self::commit();
                }
                return false;
            }
            self::execute('INSERT INTO user_medals (user_id, medal_definition_id, source, note, related_contract_id, related_payment_id, awarded_by, awarded_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [(int) $userId, (int) $definition['id'], trim((string) $source) ?: 'manual', trim((string) $note) ?: null, $contractId ? (int) $contractId : null, $paymentId ? (int) $paymentId : null, $actorId ? (int) $actorId : null]);
            $medalId = (int) self::lastInsertId();
            MedalHistoryService::record($medalId, 'awarded', $note, $actorId, $definition);
            if (class_exists('AuditLog')) {
                AuditLog::record('medal', 'awarded', 'user_medal', $medalId, ['actor_user_id' => $actorId, 'customer_id' => (int) $userId, 'new_values' => ['slug' => $slug, 'source' => $source]]);
            }
            if ($started) {
                self::commit();
            }
            return $medalId;
        } catch (Throwable $e) {
            if ($started) {
                self::rollBack();
            }
            throw $e;
        }
    }

    public static function revoke($id, $actorId, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new InvalidArgumentException('علت لغو مدال الزامی است.');
        }
        $started = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $started = true;
        }
        try {
            $medal = self::fetch('SELECT * FROM user_medals WHERE id = ? AND revoked_at IS NULL FOR UPDATE', [(int) $id]);
            if (!$medal) {
                throw new InvalidArgumentException('مدال فعال پیدا نشد.');
            }
            self::execute('UPDATE user_medals SET revoked_at = NOW(), revoked_by = ?, revoke_reason = ? WHERE id = ?', [$actorId ? (int) $actorId : null, $reason, (int) $id]);
            MedalHistoryService::record((int) $id, 'revoked', $reason, $actorId, $medal);
            if (class_exists('AuditLog')) {
                AuditLog::record('medal', 'revoked', 'user_medal', (int) $id, ['actor_user_id' => $actorId, 'customer_id' => (int) $medal['user_id'], 'new_values' => ['reason' => $reason]]);
            }
            if ($started) {
                self::commit();
            }
        } catch (Throwable $e) {
            if ($started) {
                self::rollBack();
            }
            throw $e;
        }
    }

    public static function restore($id, $actorId, $reason = 'بازگردانی مدال')
    {
        $started = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $started = true;
        }
        try {
            $medal = self::fetch('SELECT * FROM user_medals WHERE id = ? AND revoked_at IS NOT NULL FOR UPDATE', [(int) $id]);
            if (!$medal) {
                throw new InvalidArgumentException('مدال لغوشده پیدا نشد.');
            }
            $active = self::fetch('SELECT id FROM user_medals WHERE user_id = ? AND medal_definition_id = ? AND revoked_at IS NULL LIMIT 1', [(int) $medal['user_id'], (int) $medal['medal_definition_id']]);
            if ($active) {
                throw new InvalidArgumentException('یک نمونه فعال از این مدال برای کاربر وجود دارد.');
            }
            self::execute('UPDATE user_medals SET revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL WHERE id = ?', [(int) $id]);
            MedalHistoryService::record((int) $id, 'restored', $reason, $actorId, $medal);
            if (class_exists('AuditLog')) {
                AuditLog::record('medal', 'restored', 'user_medal', (int) $id, ['actor_user_id' => $actorId, 'customer_id' => (int) $medal['user_id']]);
            }
            if ($started) {
                self::commit();
            }
        } catch (Throwable $e) {
            if ($started) {
                self::rollBack();
            }
            throw $e;
        }
    }

    public static function evaluateCustomer($userId, $actorId = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }
        try {
            return MedalReconciliationService::reconcileCustomer($userId, $actorId);
        } catch (Throwable $e) {
            if (!($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1146)) {
                throw $e;
            }
        }
    }
}
