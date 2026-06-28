<?php

class Payment extends Model
{
    protected static $correctionSchemaReady = false;

    public static function ensureCorrectionSchema()
    {
        if (self::$correctionSchemaReady) {
            return;
        }
        foreach ([
            'is_corrected' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'correction_reason' => 'TEXT NULL',
            'corrected_at' => 'DATETIME NULL',
            'corrected_by' => 'BIGINT UNSIGNED NULL',
            'correction_snapshot_json' => 'LONGTEXT NULL',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE payments ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        self::execute(
            "CREATE TABLE IF NOT EXISTS payment_corrections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payment_id BIGINT UNSIGNED NOT NULL,
                installment_id BIGINT UNSIGNED NOT NULL,
                contract_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                reason TEXT NOT NULL,
                snapshot_json LONGTEXT NOT NULL,
                corrected_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_payment_correction (payment_id),
                KEY idx_payment_correction_installment (installment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$correctionSchemaReady = true;
    }

    public static function recentForCustomer($customerId, $limit = 8)
    {
        self::ensureCorrectionSchema();
        return self::fetchAll(
            "SELECT p.*, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.id = p.installment_id
             WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
             LIMIT " . max(1, (int) $limit),
            [(int) $customerId]
        );
    }

    public static function recentForCustomers(array $customerIds, $limitPerCustomer = 3)
    {
        self::ensureCorrectionSchema();
        $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));
        if (!$customerIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $rows = self::fetchAll(
            "SELECT p.*, c.customer_id, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.id = p.installment_id
             WHERE c.customer_id IN ({$placeholders}) AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY c.customer_id ASC, COALESCE(p.paid_at, p.created_at) DESC, p.id DESC",
            $customerIds
        );
        $grouped = [];
        foreach ($rows as $row) {
            $customerId = (int) $row['customer_id'];
            if (count($grouped[$customerId] ?? []) >= (int) $limitPerCustomer) {
                continue;
            }
            $grouped[$customerId][] = $row;
        }
        return $grouped;
    }

    public static function recentForContract($contractId, $limit = 6)
    {
        self::ensureCorrectionSchema();
        return self::fetchAll(
            "SELECT p.*, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.id = p.installment_id
             WHERE p.contract_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
             LIMIT " . max(1, (int) $limit),
            [(int) $contractId]
        );
    }

    public static function monthlyTrendForContract($contractId, $months = 6)
    {
        self::ensureCorrectionSchema();
        $months = max(1, (int) $months);
        $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $rows = self::fetchAll(
            "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') AS month_key, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0 AND COALESCE(paid_at, created_at) >= ?
             GROUP BY month_key",
            [(int) $contractId, $start->format('Y-m-01')]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month_key']] = (float) $row['total'];
        }
        $data = [];
        for ($i = 0; $i < $months; $i++) {
            $date = (clone $start)->modify('+' . $i . ' months');
            $data[] = $totals[$date->format('Y-m')] ?? 0;
        }
        return $data;
    }

    public static function monthlyTrendForCustomer($customerId, $months = 6)
    {
        self::ensureCorrectionSchema();
        $months = max(1, (int) $months);
        $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $rows = self::fetchAll(
            "SELECT DATE_FORMAT(COALESCE(p.paid_at, p.created_at), '%Y-%m') AS month_key, COALESCE(SUM(p.amount),0) AS total
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND COALESCE(p.paid_at, p.created_at) >= ?
             GROUP BY month_key",
            [(int) $customerId, $start->format('Y-m-01')]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month_key']] = (float) $row['total'];
        }
        $data = [];
        for ($i = 0; $i < $months; $i++) {
            $date = (clone $start)->modify('+' . $i . ' months');
            $data[] = $totals[$date->format('Y-m')] ?? 0;
        }
        return $data;
    }

    public static function forInstallment($installmentId)
    {
        self::ensureCorrectionSchema();
        return self::fetchAll("SELECT * FROM payments WHERE installment_id = ? AND COALESCE(is_corrected, 0) = 0 ORDER BY paid_at ASC, id ASC", [(int) $installmentId]);
    }

    public static function logs($filters = [])
    {
        self::ensureCorrectionSchema();
        $params = [];
        $where = [];
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(p.paid_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(p.paid_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['contract_number'])) {
            $where[] = 'c.contract_number LIKE ?';
            $params[] = '%' . to_english_digits($filters['contract_number']) . '%';
        }
        if (!empty($filters['customer'])) {
            $needle = '%' . to_english_digits($filters['customer']) . '%';
            $where[] = '(u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        $sql = "SELECT p.*, c.customer_id, c.contract_number, u.full_name AS customer_name, i.installment_number,
                cu.full_name AS corrected_by_name
                FROM payments p
                JOIN contracts c ON c.id = p.contract_id
                JOIN users u ON u.id = c.customer_id
                JOIN installments i ON i.id = p.installment_id
                LEFT JOIN users cu ON cu.id = p.corrected_by"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY p.id DESC';
        return self::fetchAll($sql, $params);
    }

    public static function record($installmentId, $contractId, $userId, $amount, $method, $status, $trackId = null, $refId = null, $description = '')
    {
        self::ensureCorrectionSchema();
        $before = $status === 'paid' ? self::installmentState($installmentId) : null;
        self::execute(
            'INSERT INTO payments
             (installment_id, contract_id, user_id, amount, method, status, gateway_track_id, gateway_ref_id, description, paid_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [(int) $installmentId, (int) $contractId, $userId ?: null, normalize_money($amount), $method, $status, $trackId, $refId, $description]
        );
        $paymentId = (int) self::lastInsertId();
        if ($status === 'paid') {
            self::applyToInstallment($installmentId);
            self::storeSnapshot($paymentId, $before, self::installmentState($installmentId));
        }
        return $paymentId;
    }

    public static function createPendingGateway($installmentId, $contractId, $userId, $amount, $trackId)
    {
        return self::record($installmentId, $contractId, $userId, $amount, 'zibal', 'pending', $trackId, null, 'در انتظار تأیید درگاه');
    }

    public static function completeGateway($trackId, $refId, $amountToman)
    {
        self::ensureCorrectionSchema();
        self::begin();
        try {
            $payment = self::fetch('SELECT * FROM payments WHERE gateway_track_id = ? FOR UPDATE', [$trackId]);
            if (!$payment) {
                self::rollBack();
                return ['ok' => false, 'message' => 'پرداخت پیدا نشد.'];
            }
            if ($payment['status'] === 'paid') {
                self::commit();
                return ['ok' => true, 'message' => 'این پرداخت قبلاً ثبت شده است.'];
            }
            $before = self::installmentState((int) $payment['installment_id']);
            self::execute(
                'UPDATE payments SET status = ?, gateway_ref_id = ?, amount = ?, paid_at = NOW() WHERE id = ?',
                ['paid', $refId, normalize_money($amountToman ?: $payment['amount']), $payment['id']]
            );
            self::applyToInstallment($payment['installment_id']);
            self::storeSnapshot((int) $payment['id'], $before, self::installmentState((int) $payment['installment_id']));
            Notification::create($payment['user_id'], 'پرداخت جدید ثبت شد', 'پرداخت شما با موفقیت تأیید شد.', 'payment', url('payments'));
            self::commit();
            return ['ok' => true, 'message' => 'پرداخت با موفقیت ثبت شد.'];
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function applyToInstallment($installmentId)
    {
        self::ensureCorrectionSchema();
        $paid = (float) self::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE installment_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0", [(int) $installmentId])['total'];
        $row = self::fetch('SELECT base_amount, due_date FROM installments WHERE id = ?', [(int) $installmentId]);
        if (!$row) {
            return;
        }
        $status = FinanceHelper::status((float) $row['base_amount'], $paid, $row['due_date']);
        self::execute('UPDATE installments SET paid_amount = ?, status = ? WHERE id = ?', [$paid, $status, (int) $installmentId]);
    }

    public static function correct($paymentId, $reason, $adminId)
    {
        self::ensureCorrectionSchema();
        $reason = trim((string) $reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'علت اصلاحیه الزامی است.'];
        }
        self::begin();
        try {
            $payment = self::fetch(
                "SELECT p.*, c.customer_id
                 FROM payments p
                 JOIN contracts c ON c.id = p.contract_id
                 WHERE p.id = ? FOR UPDATE",
                [(int) $paymentId]
            );
            if (!$payment) {
                self::rollBack();
                return ['ok' => false, 'message' => 'پرداخت پیدا نشد.'];
            }
            if ((int) ($payment['is_corrected'] ?? 0) === 1) {
                self::rollBack();
                return ['ok' => false, 'message' => 'این پرداخت قبلاً اصلاح شده است.'];
            }
            if (($payment['status'] ?? '') !== 'paid') {
                self::rollBack();
                return ['ok' => false, 'message' => 'فقط پرداخت‌های موفق قابل اصلاح هستند.'];
            }
            self::fetch('SELECT id FROM installments WHERE id = ? FOR UPDATE', [(int) $payment['installment_id']]);
            $snapshot = $payment['correction_snapshot_json'] ?: json_encode([
                'payment_id' => (int) $payment['id'],
                'installment_id' => (int) $payment['installment_id'],
                'contract_id' => (int) $payment['contract_id'],
                'customer_id' => (int) $payment['customer_id'],
                'amount' => (float) $payment['amount'],
                'payment_date' => $payment['paid_at'] ?: $payment['created_at'],
                'before' => self::installmentState((int) $payment['installment_id']),
                'after' => self::installmentState((int) $payment['installment_id']),
            ], JSON_UNESCAPED_UNICODE);
            self::execute(
                "UPDATE payments
                 SET is_corrected = 1, status = 'corrected', correction_reason = ?, corrected_at = NOW(), corrected_by = ?
                 WHERE id = ? AND COALESCE(is_corrected, 0) = 0",
                [$reason, (int) $adminId, (int) $payment['id']]
            );
            self::execute(
                'INSERT INTO payment_corrections
                 (payment_id, installment_id, contract_id, customer_id, reason, snapshot_json, corrected_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $payment['id'], (int) $payment['installment_id'], (int) $payment['contract_id'], (int) $payment['customer_id'], $reason, $snapshot, (int) $adminId]
            );
            self::applyToInstallment((int) $payment['installment_id']);
            self::commit();
            return [
                'ok' => true,
                'message' => ($payment['method'] === 'zibal')
                    ? 'اصلاحیه ثبت شد. این اصلاحیه فقط وضعیت داخلی سامانه را تغییر می‌دهد و بازگشت وجه بانکی انجام نمی‌دهد.'
                    : 'اصلاحیه پرداخت ثبت شد.',
            ];
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    protected static function installmentState($installmentId)
    {
        $row = Installment::find((int) $installmentId);
        if (!$row) {
            return [];
        }
        return [
            'paid_amount' => (float) $row['paid_amount'],
            'remaining' => max(0, (float) $row['base_amount'] - (float) $row['paid_amount']),
            'penalty' => (float) $row['penalty'],
            'reward' => (float) $row['reward'],
            'status' => $row['status'],
        ];
    }

    protected static function storeSnapshot($paymentId, ?array $before, ?array $after)
    {
        $payment = self::fetch(
            "SELECT p.*, c.customer_id
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             WHERE p.id = ?",
            [(int) $paymentId]
        );
        if (!$payment) {
            return;
        }
        $snapshot = [
            'payment_id' => (int) $payment['id'],
            'installment_id' => (int) $payment['installment_id'],
            'contract_id' => (int) $payment['contract_id'],
            'customer_id' => (int) $payment['customer_id'],
            'amount' => (float) $payment['amount'],
            'payment_date' => $payment['paid_at'] ?: $payment['created_at'],
            'paid_amount_before' => (float) ($before['paid_amount'] ?? 0),
            'paid_amount_after' => (float) ($after['paid_amount'] ?? 0),
            'remaining_before' => (float) ($before['remaining'] ?? 0),
            'remaining_after' => (float) ($after['remaining'] ?? 0),
            'penalty_before' => (float) ($before['penalty'] ?? 0),
            'penalty_after' => (float) ($after['penalty'] ?? 0),
            'reward_before' => (float) ($before['reward'] ?? 0),
            'reward_after' => (float) ($after['reward'] ?? 0),
            'status_before' => $before['status'] ?? null,
            'status_after' => $after['status'] ?? null,
            'created_by' => $payment['user_id'] ? (int) $payment['user_id'] : null,
            'created_at' => $payment['created_at'],
        ];
        self::execute('UPDATE payments SET correction_snapshot_json = ? WHERE id = ?', [json_encode($snapshot, JSON_UNESCAPED_UNICODE), (int) $paymentId]);
    }
}
