<?php

class CustomerLoyalty extends Model
{
    public static function rules($sourceType = null, $activeOnly = false)
    {
        $where = [];
        $params = [];
        if (in_array($sourceType, ['system', 'manual'], true)) {
            $where[] = 'source_type = ?';
            $params[] = $sourceType;
        }
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        return self::fetchAll(
            'SELECT * FROM customer_point_rules' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY sort_order, id',
            $params
        );
    }

    public static function tiers($activeOnly = true)
    {
        return self::fetchAll(
            'SELECT * FROM customer_loyalty_tiers' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY min_points, sort_order, id'
        );
    }

    public static function createManualRule(array $data, $actorId)
    {
        $slug = strtolower(trim((string) ($data['activity_key'] ?? '')));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim($slug, '-_');
        if ($slug !== '' && strpos($slug, 'manual_') !== 0) {
            $slug = 'manual_' . $slug;
        }
        $title = trim((string) ($data['title'] ?? ''));
        $points = max(1, min(10000, (int) to_english_digits($data['points'] ?? 0)));
        if ($slug === 'manual_' || $title === '') {
            throw new InvalidArgumentException('شناسه و عنوان فعالیت الزامی است.');
        }
        if (self::fetch('SELECT id FROM customer_point_rules WHERE activity_key = ? LIMIT 1', [$slug])) {
            throw new InvalidArgumentException('این شناسهٔ فعالیت قبلاً ثبت شده است.');
        }
        self::execute(
            'INSERT INTO customer_point_rules (activity_key, title, description, source_type, points, is_active, sort_order, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 1, 100, ?, NOW())',
            [$slug, mb_substr($title, 0, 150, 'UTF-8'), mb_substr(trim((string) ($data['description'] ?? '')), 0, 255, 'UTF-8') ?: null, 'manual', $points, $actorId ? (int) $actorId : null]
        );
        return (int) self::lastInsertId();
    }

    public static function updateRule($id, $points, $isActive)
    {
        $rule = self::fetch('SELECT id FROM customer_point_rules WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$rule) {
            throw new InvalidArgumentException('فعالیت امتیازی پیدا نشد.');
        }
        self::execute(
            'UPDATE customer_point_rules SET points = ?, is_active = ?, updated_at = NOW() WHERE id = ?',
            [max(1, min(10000, (int) to_english_digits($points))), $isActive ? 1 : 0, (int) $id]
        );
    }

    public static function toggleRule($id)
    {
        $rule = self::fetch('SELECT is_active FROM customer_point_rules WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$rule) {
            throw new InvalidArgumentException('فعالیت امتیازی پیدا نشد.');
        }
        $next = empty($rule['is_active']) ? 1 : 0;
        self::execute('UPDATE customer_point_rules SET is_active = ?, updated_at = NOW() WHERE id = ?', [$next, (int) $id]);
        return (bool) $next;
    }

    public static function saveTier(array $data, $actorId)
    {
        $id = (int) ($data['id'] ?? 0);
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim($slug, '-_');
        $title = trim((string) ($data['title'] ?? ''));
        $medalTitle = trim((string) ($data['medal_title'] ?? ''));
        $minimum = max(1, min(10000000, (int) to_english_digits($data['min_points'] ?? 0)));
        if ($slug === '' || $title === '' || $medalTitle === '') {
            throw new InvalidArgumentException('شناسه، عنوان سطح و عنوان مدال الزامی است.');
        }
        $duplicate = self::fetch('SELECT id FROM customer_loyalty_tiers WHERE slug = ? AND id != ? LIMIT 1', [$slug, $id]);
        if ($duplicate) {
            throw new InvalidArgumentException('شناسهٔ این سطح قبلاً ثبت شده است.');
        }
        if (self::fetch('SELECT id FROM customer_loyalty_tiers WHERE min_points = ? AND id != ? LIMIT 1', [$minimum, $id])) {
            throw new InvalidArgumentException('حداقل امتیاز هر سطح باید با سطح‌های دیگر متفاوت باشد.');
        }
        $color = sanitize_hex_color($data['color'] ?? '', '#d97706');
        $icon = preg_replace('/[^a-z0-9_-]/i', '', (string) ($data['icon_key'] ?? 'award')) ?: 'award';
        $tierActive = $id > 0 ? isset($data['is_active']) : true;
        $values = [
            mb_substr($title, 0, 100, 'UTF-8'), mb_substr($medalTitle, 0, 150, 'UTF-8'),
            mb_substr(trim((string) ($data['description'] ?? '')), 0, 255, 'UTF-8') ?: null,
            $minimum, $icon, $color, (int) to_english_digits($data['sort_order'] ?? 0), $tierActive ? 1 : 0,
        ];
        if ($id > 0) {
            if (!self::fetch('SELECT id FROM customer_loyalty_tiers WHERE id = ? LIMIT 1', [$id])) {
                throw new InvalidArgumentException('سطح وفاداری پیدا نشد.');
            }
            self::execute(
                'UPDATE customer_loyalty_tiers SET title = ?, medal_title = ?, description = ?, min_points = ?, icon_key = ?, color = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?',
                array_merge($values, [$id])
            );
            if (!empty($values[7])) {
                self::reconcileCustomersWithPoints();
            }
            return $id;
        }
        self::execute(
            'INSERT INTO customer_loyalty_tiers (slug, title, medal_title, description, min_points, icon_key, color, sort_order, is_active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            array_merge([$slug], $values, [$actorId ? (int) $actorId : null])
        );
        $newId = (int) self::lastInsertId();
        if (!empty($values[7])) {
            self::reconcileCustomersWithPoints();
        }
        return $newId;
    }

    protected static function reconcileCustomersWithPoints()
    {
        $customers = self::fetchAll('SELECT DISTINCT customer_id FROM customer_point_ledger ORDER BY customer_id');
        foreach ($customers as $customer) {
            self::reconcileCustomer((int) $customer['customer_id']);
        }
    }

    public static function toggleTier($id)
    {
        $tier = self::fetch('SELECT is_active FROM customer_loyalty_tiers WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$tier) {
            throw new InvalidArgumentException('سطح وفاداری پیدا نشد.');
        }
        $next = empty($tier['is_active']) ? 1 : 0;
        self::execute('UPDATE customer_loyalty_tiers SET is_active = ?, updated_at = NOW() WHERE id = ?', [$next, (int) $id]);
        if ($next) {
            self::reconcileCustomersWithPoints();
        }
        return (bool) $next;
    }

    public static function awardManualActivity($customerId, $ruleId, $note, $actorId)
    {
        $note = trim((string) $note);
        if ($note === '') {
            throw new InvalidArgumentException('شرح فعالیت برای ثبت امتیاز الزامی است.');
        }
        $rule = self::fetch("SELECT * FROM customer_point_rules WHERE id = ? AND source_type = 'manual' AND is_active = 1 LIMIT 1", [(int) $ruleId]);
        if (!$rule) {
            throw new InvalidArgumentException('فعالیت دستی انتخاب‌شده فعال نیست.');
        }
        $customer = self::fetch("SELECT id FROM users WHERE id = ? AND role = 'customer' LIMIT 1", [(int) $customerId]);
        if (!$customer) {
            throw new InvalidArgumentException('مشتری پیدا نشد.');
        }
        $key = 'manual:' . bin2hex(random_bytes(24));
        return self::insertEntry(
            (int) $customerId, (int) $rule['id'], $rule['activity_key'], $rule['title'], (int) $rule['points'],
            'manual', $note, null, null, $key, $actorId
        );
    }

    public static function awardSystemActivity($customerId, $activityKey, $referenceType, $referenceId, $note = '')
    {
        $customerId = (int) $customerId;
        $referenceId = (int) $referenceId;
        if ($customerId <= 0 || $referenceId <= 0) {
            return false;
        }
        $rule = self::fetch("SELECT * FROM customer_point_rules WHERE activity_key = ? AND source_type = 'system' AND is_active = 1 LIMIT 1", [(string) $activityKey]);
        if (!$rule) {
            return false;
        }
        return self::insertEntry(
            $customerId, (int) $rule['id'], $rule['activity_key'], $rule['title'], (int) $rule['points'], 'system',
            trim((string) $note) ?: $rule['description'], (string) $referenceType, $referenceId,
            'system:' . $rule['activity_key'] . ':' . $referenceType . ':' . $referenceId, null
        );
    }

    protected static function insertEntry($customerId, $ruleId, $activityKey, $activityTitle, $points, $sourceType, $note, $referenceType, $referenceId, $idempotencyKey, $actorId)
    {
        $inserted = self::execute(
            'INSERT IGNORE INTO customer_point_ledger
             (customer_id, rule_id, activity_key, activity_title, points, source_type, note, reference_type, reference_id, idempotency_key, awarded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                (int) $customerId, $ruleId ? (int) $ruleId : null, (string) $activityKey, mb_substr((string) $activityTitle, 0, 150, 'UTF-8'),
                (int) $points, (string) $sourceType, $note !== '' ? mb_substr((string) $note, 0, 2000, 'UTF-8') : null,
                $referenceType ?: null, $referenceId ? (int) $referenceId : null, mb_substr((string) $idempotencyKey, 0, 191, 'UTF-8'),
                $actorId ? (int) $actorId : null,
            ]
        );
        if ($inserted > 0) {
            self::reconcileCustomer((int) $customerId);
            return true;
        }
        return false;
    }

    public static function processSystemEvent($event, array $payload)
    {
        $event = trim((string) $event);
        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($event === 'contract.created') {
            $contractId = (int) ($payload['contract_id'] ?? 0);
            $contract = $contractId > 0 ? self::fetch('SELECT customer_id, status FROM contracts WHERE id = ? LIMIT 1', [$contractId]) : null;
            if ($contract && (int) $contract['customer_id'] === $customerId && ($contract['status'] ?? '') !== 'cancelled') {
                self::awardSystemActivity($customerId, 'contract_created', 'contract', $contractId);
            }
            return;
        }
        if ($event === 'contract.cancelled') {
            $contractId = (int) ($payload['contract_id'] ?? 0);
            $contract = $contractId > 0 ? self::fetch('SELECT customer_id, status FROM contracts WHERE id = ? LIMIT 1', [$contractId]) : null;
            if ($contract && ($contract['status'] ?? '') === 'cancelled') {
                $actorId = (int) ($payload['actor_user_id'] ?? 0);
                self::reverseReference('contract', $contractId, $actorId, 'لغو قرارداد', 'contract_created');
                self::reverseReference('contract', $contractId, $actorId, 'لغو قرارداد', 'contract_completed');
            }
            return;
        }
        if ($event === 'identity.verified') {
            if ($customerId > 0 && class_exists('IdentityDocument') && IdentityDocument::isVerified($customerId)) {
                self::awardSystemActivity($customerId, 'identity_verified', 'customer', $customerId);
            }
            return;
        }
        if ($event !== 'payment.completed') {
            return;
        }
        $paymentId = (int) ($payload['payment_id'] ?? 0);
        $payment = $paymentId > 0 ? self::fetch(
            "SELECT p.id, p.contract_id, p.installment_id, p.payment_date, p.paid_at, p.status, p.is_corrected, p.payment_type,
                    c.customer_id, c.status AS contract_status,
                    i.status AS installment_status, i.due_date, i.last_payment_date, i.effective_settlement_at
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             LEFT JOIN installments i ON i.id = p.installment_id
             WHERE p.id = ? LIMIT 1",
            [$paymentId]
        ) : null;
        if (!$payment || ($payment['status'] ?? '') !== 'paid' || (int) ($payment['is_corrected'] ?? 0) === 1 || ($payment['payment_type'] ?? 'installment') === 'legal_cost') {
            return;
        }
        $customerId = (int) $payment['customer_id'];
        self::awardSystemActivity($customerId, 'payment_completed', 'payment', $paymentId);

        $installmentId = (int) ($payment['installment_id'] ?? 0);
        if ($installmentId > 0 && ($payment['installment_status'] ?? '') === 'paid') {
            $latest = self::fetch(
                "SELECT id FROM payments
                 WHERE installment_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0
                 ORDER BY COALESCE(payment_date, DATE(paid_at), DATE(created_at)) DESC, id DESC LIMIT 1",
                [$installmentId]
            );
            if ((int) ($latest['id'] ?? 0) === $paymentId) {
                $settledAt = $payment['effective_settlement_at'] ?: ($payment['last_payment_date'] ?: ($payment['payment_date'] ?: $payment['paid_at']));
                $settledDate = $settledAt ? substr((string) $settledAt, 0, 10) : '';
                $dueDate = substr((string) ($payment['due_date'] ?? ''), 0, 10);
                if ($settledDate !== '' && $dueDate !== '' && $settledDate < $dueDate) {
                    self::awardSystemActivity($customerId, 'payment_early', 'payment', $paymentId, 'تکمیل قسط پیش از سررسید');
                } elseif ($settledDate !== '' && $dueDate !== '' && $settledDate <= $dueDate) {
                    self::awardSystemActivity($customerId, 'payment_on_time', 'payment', $paymentId, 'تکمیل قسط تا تاریخ سررسید');
                }
            }
        }
        if (($payment['contract_status'] ?? '') === 'completed') {
            self::awardSystemActivity($customerId, 'contract_completed', 'contract', (int) $payment['contract_id']);
        }
    }

    public static function reversePayment($paymentId, $actorId, $reason = '')
    {
        self::reverseReference('payment', (int) $paymentId, $actorId, $reason);
    }

    public static function reverseContractCompletion($contractId, $customerId, $actorId, $reason = '')
    {
        $contract = self::fetch('SELECT status FROM contracts WHERE id = ? AND customer_id = ? LIMIT 1', [(int) $contractId, (int) $customerId]);
        if ($contract && ($contract['status'] ?? '') !== 'completed') {
            self::reverseReference('contract', (int) $contractId, $actorId, $reason, 'contract_completed');
        }
    }

    protected static function reverseReference($referenceType, $referenceId, $actorId, $reason, $activityKey = null)
    {
        if ((int) $referenceId <= 0) {
            return;
        }
        $sql = "SELECT * FROM customer_point_ledger WHERE source_type = 'system' AND reference_type = ? AND reference_id = ? AND points > 0";
        $params = [(string) $referenceType, (int) $referenceId];
        if ($activityKey !== null) {
            $sql .= ' AND activity_key = ?';
            $params[] = (string) $activityKey;
        }
        foreach (self::fetchAll($sql, $params) as $entry) {
            self::execute(
                'INSERT IGNORE INTO customer_point_ledger
                 (customer_id, rule_id, activity_key, activity_title, points, source_type, note, reference_type, reference_id, idempotency_key, awarded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    (int) $entry['customer_id'], $entry['rule_id'] ? (int) $entry['rule_id'] : null, $entry['activity_key'],
                    mb_substr('برگشت امتیاز: ' . $entry['activity_title'], 0, 150, 'UTF-8'), -(int) $entry['points'], 'reversal',
                    mb_substr(trim((string) $reason) ?: 'اصلاح فعالیت مرجع', 0, 2000, 'UTF-8'), (string) $referenceType,
                    (int) $referenceId, 'reversal:' . (int) $entry['id'], $actorId ? (int) $actorId : null,
                ]
            );
            self::reconcileCustomer((int) $entry['customer_id']);
        }
    }

    public static function reconcileCustomer($customerId)
    {
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return 0;
        }
        $points = self::totalPoints($customerId);
        $awarded = 0;
        foreach (self::tiers(true) as $tier) {
            if ($points < (int) $tier['min_points']) {
                continue;
            }
            $inserted = self::execute(
                'INSERT IGNORE INTO customer_loyalty_tier_awards
                 (customer_id, tier_id, tier_title, medal_title, description, icon_key, color, points_at_award, awarded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$customerId, (int) $tier['id'], $tier['title'], $tier['medal_title'], $tier['description'], $tier['icon_key'], $tier['color'], $points]
            );
            if ($inserted > 0) {
                $awarded++;
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::safeEnqueueNotification(
                        $customerId,
                        'سطح جدید باشگاه مشتریان',
                        'تبریک! امتیازهای شما به سطح «' . $tier['title'] . '» رسید و ' . $tier['medal_title'] . ' دریافت کردید.',
                        'loyalty',
                        url('dashboard'),
                        'loyalty_tier',
                        (int) $tier['id']
                    );
                }
            }
        }
        return $awarded;
    }

    public static function totalPoints($customerId)
    {
        $row = self::fetch('SELECT COALESCE(SUM(points), 0) AS total FROM customer_point_ledger WHERE customer_id = ?', [(int) $customerId]);
        return max(0, (int) ($row['total'] ?? 0));
    }

    public static function summariesForUsers(array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $totals = self::fetchAll(
            "SELECT customer_id, COALESCE(SUM(points), 0) AS total_points
             FROM customer_point_ledger WHERE customer_id IN ({$placeholders}) GROUP BY customer_id",
            $ids
        );
        $byId = [];
        foreach ($totals as $row) {
            $byId[(int) $row['customer_id']] = max(0, (int) $row['total_points']);
        }
        $tiers = self::tiers(true);
        $medals = self::medalsForUsers($ids);
        $summaries = [];
        foreach ($ids as $id) {
            $points = $byId[$id] ?? 0;
            $current = null;
            $next = null;
            foreach ($tiers as $tier) {
                if ($points >= (int) $tier['min_points']) {
                    $current = $tier;
                } elseif ($next === null) {
                    $next = $tier;
                }
            }
            $floor = $current ? (int) $current['min_points'] : 0;
            $progress = $next ? (int) round(max(0, min(1, ($points - $floor) / max(1, (int) $next['min_points'] - $floor))) * 100) : ($current ? 100 : 0);
            $summaries[$id] = [
                'points' => $points,
                'tier' => $current,
                'next_tier' => $next,
                'progress' => $progress,
                'medals' => $medals[$id] ?? [],
            ];
        }
        return $summaries;
    }

    public static function medalsForUsers(array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::fetchAll(
            "SELECT id, customer_id AS user_id, tier_id, tier_title AS title, medal_title, description,
                    icon_key, color, points_at_award AS points, awarded_at
             FROM customer_loyalty_tier_awards
             WHERE customer_id IN ({$placeholders}) ORDER BY awarded_at DESC, id DESC",
            $ids
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['user_id']][] = $row;
        }
        return $grouped;
    }

    public static function profile($customerId, $historyLimit = 30)
    {
        $id = (int) $customerId;
        $summary = self::summariesForUsers([$id])[$id] ?? ['points' => 0, 'tier' => null, 'next_tier' => null, 'progress' => 0, 'medals' => []];
        $summary['history'] = self::history($id, $historyLimit);
        return $summary;
    }

    public static function history($customerId, $limit = 30)
    {
        return self::fetchAll(
            'SELECT l.*, actor.full_name AS actor_name
             FROM customer_point_ledger l
             LEFT JOIN users actor ON actor.id = l.awarded_by
             WHERE l.customer_id = ? ORDER BY l.id DESC LIMIT ' . max(1, min(100, (int) $limit)),
            [(int) $customerId]
        );
    }

    public static function managementSummary()
    {
        $row = self::fetch(
            'SELECT COALESCE(SUM(points), 0) AS points_issued, COUNT(*) AS entries,
                    COUNT(DISTINCT customer_id) AS customers_with_points
             FROM customer_point_ledger'
        ) ?: [];
        $tiers = self::fetch('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM customer_loyalty_tiers') ?: [];
        $awards = self::fetch('SELECT COUNT(*) AS total FROM customer_loyalty_tier_awards') ?: [];
        return array_merge(['points_issued' => 0, 'entries' => 0, 'customers_with_points' => 0, 'tiers' => 0, 'active_tiers' => 0, 'medals_awarded' => 0], [
            'points_issued' => max(0, (int) ($row['points_issued'] ?? 0)),
            'entries' => (int) ($row['entries'] ?? 0),
            'customers_with_points' => (int) ($row['customers_with_points'] ?? 0),
            'tiers' => (int) ($tiers['total'] ?? 0),
            'active_tiers' => (int) ($tiers['active'] ?? 0),
            'medals_awarded' => (int) ($awards['total'] ?? 0),
        ]);
    }
}
