<?php

class Medal extends Model
{
    public static function definitions($includeInactive = false)
    {
        $where = $includeInactive ? '' : ' WHERE is_active = 1 AND archived_at IS NULL';
        return self::fetchAll('SELECT * FROM medal_definitions' . $where . ' ORDER BY sort_order ASC, id ASC');
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
