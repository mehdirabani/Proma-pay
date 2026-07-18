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
                    SUM(award_type = 'manual') AS manual
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
        if (!(int) $definition['is_repeatable'] && self::fetch('SELECT id FROM user_medals WHERE user_id = ? AND medal_definition_id = ? AND revoked_at IS NULL LIMIT 1', [(int) $userId, (int) $definition['id']])) {
            return false;
        }
        $started = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $started = true;
        }
        try {
            self::execute('INSERT INTO user_medals (user_id, medal_definition_id, source, note, related_contract_id, related_payment_id, awarded_by, awarded_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [(int) $userId, (int) $definition['id'], trim((string) $source) ?: 'manual', trim((string) $note) ?: null, $contractId ? (int) $contractId : null, $paymentId ? (int) $paymentId : null, $actorId ? (int) $actorId : null]);
            $medalId = (int) self::lastInsertId();
            self::execute('INSERT INTO user_medal_history (user_medal_id, action, reason, performed_by, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$medalId, 'awarded', trim((string) $note) ?: null, $actorId ? (int) $actorId : null, json_encode($definition, JSON_UNESCAPED_UNICODE)]);
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
            self::execute('UPDATE user_medals SET revoked_at = NOW(), revoked_by = ?, revoke_reason = ? WHERE id = ?', [(int) $actorId, $reason, (int) $id]);
            self::execute('INSERT INTO user_medal_history (user_medal_id, action, reason, performed_by, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [(int) $id, 'revoked', $reason, (int) $actorId, json_encode($medal, JSON_UNESCAPED_UNICODE)]);
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
            self::execute('UPDATE user_medals SET revoked_at = NULL, revoked_by = NULL, revoke_reason = NULL WHERE id = ?', [(int) $id]);
            self::execute('INSERT INTO user_medal_history (user_medal_id, action, reason, performed_by, snapshot_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [(int) $id, 'restored', trim((string) $reason), (int) $actorId, json_encode($medal, JSON_UNESCAPED_UNICODE)]);
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
            $contractCount = (int) (self::fetch("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status != 'cancelled'", [$userId])['total'] ?? 0);
            $paymentCount = (int) (self::fetch("SELECT COUNT(*) AS total FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0", [$userId])['total'] ?? 0);
            $onTimeCount = (int) (self::fetch("SELECT COUNT(DISTINCT i.id) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id JOIN payments p ON p.installment_id = i.id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND DATE(COALESCE(p.paid_at, p.created_at)) <= i.due_date", [$userId])['total'] ?? 0);
            $earlyPaymentCount = (int) (self::fetch("SELECT COUNT(DISTINCT i.id) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id JOIN payments p ON p.installment_id = i.id WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND DATE(COALESCE(p.paid_at, p.created_at)) < i.due_date", [$userId])['total'] ?? 0);
            $completedCount = (int) (self::fetch("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status = 'completed'", [$userId])['total'] ?? 0);
            $overdueCount = (int) (self::fetch("SELECT COUNT(*) AS total FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE c.customer_id = ? AND i.status = 'overdue'", [$userId])['total'] ?? 0);
            $earlySettlementCount = (int) (self::fetch("SELECT COUNT(*) AS total FROM contracts c WHERE c.customer_id = ? AND c.status = 'completed' AND c.updated_at IS NOT NULL AND DATE(c.updated_at) < (SELECT MAX(i.due_date) FROM installments i WHERE i.contract_id = c.id)", [$userId])['total'] ?? 0);
            $metrics = ['contract_count' => $contractCount, 'payment_count' => $paymentCount, 'on_time_count' => $onTimeCount, 'early_payment_count' => $earlyPaymentCount, 'completed_contract_count' => $completedCount, 'overdue_count' => $overdueCount, 'early_settlement_count' => $earlySettlementCount];
            foreach (self::definitions() as $definition) {
                $criteria = json_decode((string) ($definition['criteria_json'] ?? ''), true) ?: [];
                $metric = (string) ($definition['criteria_type'] ?? '');
                $metricValue = $metrics[$metric] ?? null;
                $minimumMet = $metricValue !== null && (!isset($criteria['minimum']) || $metricValue >= (int) $criteria['minimum']);
                $maximumMet = $metricValue !== null && (!isset($criteria['maximum']) || $metricValue <= (int) $criteria['maximum']);
                if ($metric !== '' && $metricValue !== null && $minimumMet && $maximumMet) {
                    self::award($userId, $definition['slug'], 'automatic', $actorId);
                }
            }
        } catch (Throwable $e) {
            if (!($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1146)) {
                throw $e;
            }
        }
    }
}
