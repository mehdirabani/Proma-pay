<?php

class Payment extends Model
{
    protected static $correctionSchemaReady = false;

    public static function ensureCorrectionSchema()
    {
        if (self::$correctionSchemaReady) {
            return;
        }
        // Payment schema is provisioned by installation and migrations, never by a page request.
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
             LEFT JOIN installments i ON i.id = p.installment_id
             WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC
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
             LEFT JOIN installments i ON i.id = p.installment_id
             WHERE c.customer_id IN ({$placeholders}) AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY c.customer_id ASC, COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC",
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
             LEFT JOIN installments i ON i.id = p.installment_id
             WHERE p.contract_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC
             LIMIT " . max(1, (int) $limit),
            [(int) $contractId]
        );
    }

    public static function forLegalCase($contractId, $customerId)
    {
        self::ensureCorrectionSchema();
        return self::fetchAll(
            "SELECT p.*, c.customer_id, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             JOIN users u ON u.id = c.customer_id
             LEFT JOIN installments i ON i.id = p.installment_id
             WHERE p.contract_id = ? AND c.customer_id = ? AND COALESCE(p.is_corrected, 0) = 0
             ORDER BY COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC",
            [(int) $contractId, (int) $customerId]
        );
    }

    public static function monthlyTrendForContract($contractId, $months = 6)
    {
        self::ensureCorrectionSchema();
        $months = max(1, (int) $months);
        $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $rows = self::fetchAll(
            "SELECT DATE_FORMAT(COALESCE(payment_date, DATE(paid_at), DATE(created_at)), '%Y-%m') AS month_key, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0 AND COALESCE(payment_date, DATE(paid_at), DATE(created_at)) >= ?
             GROUP BY month_key",
            [(int) $contractId, $start->format('Y-m-01')]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month_key']] = normalize_money($row['total'] ?? 0);
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
            "SELECT DATE_FORMAT(COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)), '%Y-%m') AS month_key, COALESCE(SUM(p.amount),0) AS total
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 AND COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) >= ?
             GROUP BY month_key",
            [(int) $customerId, $start->format('Y-m-01')]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['month_key']] = normalize_money($row['total'] ?? 0);
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
        return self::fetchAll("SELECT * FROM payments WHERE installment_id = ? AND COALESCE(is_corrected, 0) = 0 AND COALESCE(payment_type, 'installment') = 'installment' ORDER BY COALESCE(payment_date, paid_at, created_at) ASC, id ASC", [(int) $installmentId]);
    }

    public static function logs($filters = [])
    {
        self::ensureCorrectionSchema();
        $params = [];
        $where = [];
        if (!empty($filters['date_from'])) {
            $where[] = 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) <= ?';
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
                LEFT JOIN installments i ON i.id = p.installment_id
                LEFT JOIN users cu ON cu.id = p.corrected_by"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) DESC, p.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(200, (int) $filters['limit']));
        }
        return self::fetchAll($sql, $params);
    }

    public static function record($installmentId, $contractId, $userId, $amount, $method, $status, $trackId = null, $refId = null, $description = '', $paymentDate = null, $paymentType = 'installment', $paymentTime = null)
    {
        self::ensureCorrectionSchema();
        $contract = self::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1', [(int) $contractId]);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پرداخت پیدا نشد.');
        }
        if (in_array(($contract['status'] ?? ''), ['cancelled', 'completed', 'closed'], true)) {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده پرداخت جدید قابل ثبت نیست.');
        }
        if ($installmentId) {
            $installmentStatus = self::fetch('SELECT status FROM installments WHERE id = ? AND contract_id = ? LIMIT 1', [(int) $installmentId, (int) $contractId]);
            if (!$installmentStatus || ($installmentStatus['status'] ?? '') === 'cancelled') {
                throw new InvalidArgumentException('قسط انتخاب‌شده قابل پرداخت نیست.');
            }
        }
        $paymentType = $paymentType === 'down_payment' ? 'down_payment' : 'installment';
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $paymentTime = normalize_time($paymentTime) ?: date('H:i');
        $paidAt = $paymentDate . ' ' . $paymentTime . ':00';
        $amount = normalize_money($amount);
        $needsInstallmentTransaction = $status === 'paid' && $paymentType === 'installment' && $installmentId;
        $startedTransaction = false;
        if ($needsInstallmentTransaction && !self::db()->inTransaction()) {
            self::begin();
            $startedTransaction = true;
        }
        try {
            $preview = null;
            $before = null;
            if ($needsInstallmentTransaction) {
                self::fetch('SELECT id FROM installments WHERE id = ? FOR UPDATE', [(int) $installmentId]);
                $installment = Installment::find((int) $installmentId);
                $preview = FinanceHelper::paymentPreview($installment, self::forInstallment($installmentId), Settings::allKeyed(), $amount, $paymentDate);
                $before = self::installmentState($installmentId);
            }
            self::execute(
                'INSERT INTO payments
                 (installment_id, contract_id, user_id, amount, method, status, gateway_track_id, gateway_ref_id, description,
                  payment_date, calculated_penalty, calculated_reward, remaining_before_payment, remaining_after_payment, payment_type, paid_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $installmentId ? (int) $installmentId : null,
                    (int) $contractId,
                    $userId ?: null,
                    $amount,
                    $method,
                    $status,
                    $trackId,
                    $refId,
                    $description,
                    $status === 'paid' ? $paymentDate : null,
                    $preview['calculated_penalty'] ?? 0,
                    $preview['calculated_reward'] ?? 0,
                    $preview['remaining_before_payment'] ?? null,
                    $preview['remaining_after_payment'] ?? null,
                    $paymentType,
                    $status === 'paid' ? $paidAt : null,
                ]
            );
            $paymentId = (int) self::lastInsertId();
            if ($needsInstallmentTransaction) {
                self::applyToInstallment($installmentId);
                self::storeSnapshot($paymentId, $before, self::installmentState($installmentId));
            }
            self::recordPaymentAudit($paymentId, [
                'actor_user_id' => $userId ? (int) $userId : null,
                'contract_id' => (int) $contractId,
                'installment_id' => $installmentId ? (int) $installmentId : null,
                'status' => $status,
                'amount' => $amount,
                'method' => $method,
                'payment_type' => $paymentType,
            ]);
            if (class_exists('SystemOutbox')) {
                $createdPayload = self::paymentEventPayload($paymentId, $contractId, $installmentId, $userId, ['status' => $status]);
                SystemOutbox::safeEnqueuePluginHook('payment.created', $createdPayload, 'payment', $paymentId);
                if ($status === 'paid') {
                    SystemOutbox::safeEnqueuePluginHook('payment.completed', self::paymentEventPayload($paymentId, $contractId, $installmentId, $userId), 'payment', $paymentId);
                }
            }
            if ($startedTransaction) {
                self::commit();
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::processPending(25);
                }
            }
            return $paymentId;
        } catch (Throwable $e) {
            if ($startedTransaction) {
                self::rollBack();
            }
            throw $e;
        }
    }

    public static function createPendingGateway($installmentId, $contractId, $userId, $amount, $trackId)
    {
        return self::createPendingGatewayFor('zibal', $installmentId, $contractId, $userId, $amount, $trackId);
    }

    public static function createPendingGatewayFor($gatewayId, $installmentId, $contractId, $userId, $amount, $reference)
    {
        $gatewayId = strtolower(trim((string) $gatewayId));
        $reference = trim((string) $reference);
        if (!preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $gatewayId) || $reference === '' || strlen($reference) > 100) {
            throw new InvalidArgumentException('اطلاعات تراکنش درگاه معتبر نیست.');
        }
        return self::record($installmentId, $contractId, $userId, $amount, $gatewayId, 'pending', $reference, null, 'در انتظار تأیید درگاه ' . $gatewayId);
    }

    public static function failGateway($reference, $reason = '')
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return 0;
        }
        return self::execute(
            "UPDATE payments SET status = 'failed', description = ? WHERE gateway_track_id = ? AND status = 'pending'",
            [substr(trim((string) $reason) ?: 'پرداخت در درگاه تکمیل نشد.', 0, 255), $reference]
        );
    }

    public static function syncDownPayment($contractId, $userId, $amount, $paymentDate = null)
    {
        self::ensureCorrectionSchema();
        $amount = normalize_money($amount);
        $existing = self::fetch(
            "SELECT id FROM payments
             WHERE contract_id = ? AND payment_type = 'down_payment' AND COALESCE(is_corrected, 0) = 0
             LIMIT 1",
            [(int) $contractId]
        );
        if ($amount <= 0) {
            if ($existing) {
                self::execute(
                    "UPDATE payments
                     SET is_corrected = 1, status = 'corrected', correction_reason = 'حذف پیش‌پرداخت در ویرایش قرارداد',
                         corrected_at = NOW(), corrected_by = ?
                     WHERE id = ?",
                    [$userId ?: null, (int) $existing['id']]
                );
            }
            return null;
        }
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $paidAt = $paymentDate . ' ' . date('H:i:s');
        if ($existing) {
            self::execute(
                "UPDATE payments
                 SET amount = ?, user_id = ?, method = 'manual', status = 'paid', description = 'پیش‌پرداخت قرارداد',
                     payment_date = ?, paid_at = ?, calculated_penalty = 0, calculated_reward = 0,
                     remaining_before_payment = NULL, remaining_after_payment = NULL
                 WHERE id = ?",
                [$amount, $userId ?: null, $paymentDate, $paidAt, (int) $existing['id']]
            );
            return (int) $existing['id'];
        }
        return self::record(null, (int) $contractId, $userId ?: null, $amount, 'manual', 'paid', null, null, 'پیش‌پرداخت قرارداد', $paymentDate, 'down_payment');
    }

    public static function completeGateway($trackId, $refId, $amountToman, array $options = [])
    {
        self::ensureCorrectionSchema();
        $startedTransaction = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $startedTransaction = true;
        }
        try {
            $payment = self::fetch('SELECT * FROM payments WHERE gateway_track_id = ? FOR UPDATE', [$trackId]);
            if (!$payment) {
                if ($startedTransaction) {
                    self::rollBack();
                }
                return ['ok' => false, 'message' => 'پرداخت پیدا نشد.'];
            }
            if ($payment['status'] === 'paid') {
                if ($startedTransaction) {
                    self::commit();
                }
                return ['ok' => true, 'message' => 'این پرداخت قبلاً ثبت شده است.', 'payment_id' => (int) $payment['id'], 'already_paid' => true];
            }
            if ($payment['status'] !== 'pending') {
                if ($startedTransaction) {
                    self::rollBack();
                }
                return ['ok' => false, 'message' => 'وضعیت پرداخت برای تأیید درگاه معتبر نیست.'];
            }
            $verifiedAmount = normalize_money($amountToman ?: 0);
            $expectedAmount = normalize_money($payment['amount'] ?? 0);
            if ($verifiedAmount <= 0 || $expectedAmount <= 0 || $verifiedAmount !== $expectedAmount) {
                if ($startedTransaction) {
                    self::rollBack();
                }
                if (class_exists('AuditLog')) {
                    AuditLog::record('payment', 'gateway_amount_mismatch', 'payment', (int) $payment['id'], [
                        'actor_user_id' => $payment['user_id'] ? (int) $payment['user_id'] : null,
                        'contract_id' => (int) $payment['contract_id'],
                        'new_values' => ['expected_amount' => $expectedAmount, 'verified_amount' => $verifiedAmount],
                    ]);
                }
                return ['ok' => false, 'message' => 'مبلغ تأییدشده درگاه با مبلغ درخواست‌شده یکسان نیست و پرداخت ثبت نشد.'];
            }
            $paymentDate = date('Y-m-d');
            $installment = Installment::find((int) $payment['installment_id']);
            $preview = FinanceHelper::paymentPreview($installment, self::forInstallment((int) $payment['installment_id']), Settings::allKeyed(), $verifiedAmount, $paymentDate);
            $before = self::installmentState((int) $payment['installment_id']);
            self::execute(
                'UPDATE payments SET status = ?, gateway_ref_id = ?, amount = ?, payment_date = ?, calculated_penalty = ?, calculated_reward = ?, remaining_before_payment = ?, remaining_after_payment = ?, paid_at = NOW() WHERE id = ?',
                [
                    'paid',
                    $refId,
                    $verifiedAmount,
                    $paymentDate,
                    $preview['calculated_penalty'],
                    $preview['calculated_reward'],
                    $preview['remaining_before_payment'],
                    $preview['remaining_after_payment'],
                    $payment['id'],
                ]
            );
            self::applyToInstallment($payment['installment_id']);
            self::storeSnapshot((int) $payment['id'], $before, self::installmentState((int) $payment['installment_id']));
            self::recordPaymentAudit((int) $payment['id'], [
                'actor_type' => 'gateway',
                'actor_user_id' => $payment['user_id'] ? (int) $payment['user_id'] : null,
                'contract_id' => (int) $payment['contract_id'],
                'installment_id' => (int) $payment['installment_id'],
                'status' => 'paid',
                'amount' => $verifiedAmount,
                'method' => $payment['method'] ?? 'gateway',
                'gateway_ref_id' => $refId,
            ]);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('payment.completed', self::paymentEventPayload((int) $payment['id'], (int) $payment['contract_id'], (int) $payment['installment_id'], $payment['user_id']), 'payment', (int) $payment['id']);
                if (($options['notify'] ?? true)) {
                    SystemOutbox::safeEnqueueNotification($payment['user_id'], 'پرداخت جدید ثبت شد', 'پرداخت شما با موفقیت تأیید شد.', 'payment', url('installments/panel'), 'payment', (int) $payment['id']);
                }
            }
            if ($startedTransaction) {
                self::commit();
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::processPending(25);
                }
            }
            return ['ok' => true, 'message' => 'پرداخت با موفقیت ثبت شد.', 'payment_id' => (int) $payment['id'], 'already_paid' => false];
        } catch (Throwable $e) {
            if ($startedTransaction) {
                self::rollBack();
            }
            throw $e;
        }
    }

    protected static function paymentEventPayload($paymentId, $contractId, $installmentId, $userId, array $extra = [])
    {
        return $extra + [
            'payment_id' => (int) $paymentId,
            'contract_id' => (int) $contractId,
            'installment_id' => $installmentId ? (int) $installmentId : null,
            'actor_user_id' => $userId ? (int) $userId : null,
        ];
    }

    protected static function recordPaymentAudit($paymentId, array $values)
    {
        if (!class_exists('AuditLog')) {
            return;
        }
        try {
            AuditLog::record('payment', 'payment_recorded', 'payment', (int) $paymentId, [
                'actor_type' => $values['actor_type'] ?? 'user',
                'actor_user_id' => $values['actor_user_id'] ?? null,
                'customer_id' => $values['customer_id'] ?? null,
                'contract_id' => $values['contract_id'] ?? null,
                'installment_id' => $values['installment_id'] ?? null,
                'new_values' => $values,
                'description' => 'ثبت پرداخت مالی در هسته سامانه',
            ]);
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) {
                PluginRegistry::logRuntimeError('payment.audit', $e);
            }
        }
    }

    public static function applyToInstallment($installmentId)
    {
        self::ensureCorrectionSchema();
        $row = self::fetch('SELECT base_amount, due_date FROM installments WHERE id = ?', [(int) $installmentId]);
        if (!$row) {
            return;
        }
        $latest = self::fetch(
            "SELECT remaining_after_payment FROM payments
             WHERE installment_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0
             AND remaining_after_payment IS NOT NULL
             ORDER BY COALESCE(payment_date, paid_at, created_at) DESC, id DESC LIMIT 1",
            [(int) $installmentId]
        );
        if ($latest) {
            $paid = max(0, normalize_money($row['base_amount'] ?? 0) - normalize_money($latest['remaining_after_payment'] ?? 0));
        } else {
            $paid = min(normalize_money($row['base_amount'] ?? 0), normalize_money(self::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE installment_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0 AND COALESCE(payment_type, 'installment') = 'installment'", [(int) $installmentId])['total'] ?? 0));
        }
        $baseAmount = normalize_money($row['base_amount'] ?? 0);
        $status = FinanceHelper::status($baseAmount, $paid, $row['due_date']);
        self::execute('UPDATE installments SET paid_amount = ?, remaining_amount = ?, last_payment_date = (SELECT MAX(payment_date) FROM payments WHERE installment_id = ? AND status = ? AND COALESCE(is_corrected, 0) = 0), status = ? WHERE id = ?', [$paid, max(0, $baseAmount - $paid), (int) $installmentId, 'paid', $status, (int) $installmentId]);
        if (class_exists('Contract')) {
            Contract::syncCompletionStatuses();
        }
    }

    public static function correctForContract($contractId, $reason, $adminId)
    {
        self::ensureCorrectionSchema();
        $contractId = (int) $contractId;
        $reason = trim((string) $reason);
        if ($contractId <= 0 || $reason === '') {
            return 0;
        }
        $startedTransaction = false;
        if (!self::db()->inTransaction()) {
            self::begin();
            $startedTransaction = true;
        }
        try {
            $payments = self::fetchAll(
                "SELECT p.*, c.customer_id
                 FROM payments p
                 JOIN contracts c ON c.id = p.contract_id
                 WHERE p.contract_id = ? AND c.id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0
                 ORDER BY p.id ASC FOR UPDATE",
                [$contractId, $contractId]
            );
            $corrected = 0;
            foreach ($payments as $payment) {
                $installmentId = !empty($payment['installment_id']) ? (int) $payment['installment_id'] : null;
                if ($installmentId) {
                    self::fetch('SELECT id FROM installments WHERE id = ? FOR UPDATE', [$installmentId]);
                }
                $before = $installmentId ? self::installmentState($installmentId) : ['effective_amount' => normalize_money($payment['amount'] ?? 0)];
                self::execute(
                    "UPDATE payments
                     SET is_corrected = 1, status = 'corrected', correction_reason = ?, corrected_at = NOW(), corrected_by = ?
                     WHERE id = ? AND COALESCE(is_corrected, 0) = 0",
                    [$reason, (int) $adminId, (int) $payment['id']]
                );
                if ($installmentId) {
                    self::applyToInstallment($installmentId);
                }
                $after = $installmentId ? self::installmentState($installmentId) : ['effective_amount' => 0, 'status' => 'corrected'];
                $snapshot = json_encode([
                    'payment_id' => (int) $payment['id'],
                    'installment_id' => $installmentId,
                    'contract_id' => (int) $payment['contract_id'],
                    'customer_id' => (int) $payment['customer_id'],
                    'amount' => normalize_money($payment['amount'] ?? 0),
                    'payment_date' => $payment['payment_date'] ?: ($payment['paid_at'] ?: $payment['created_at']),
                    'before' => $before,
                    'after' => $after,
                    'effective_amount_before' => normalize_money($payment['amount'] ?? 0),
                    'effective_amount_after' => 0,
                ], JSON_UNESCAPED_UNICODE);
                self::execute(
                    'INSERT INTO payment_corrections
                     (payment_id, installment_id, contract_id, customer_id, reason, snapshot_json, corrected_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [(int) $payment['id'], $installmentId, $contractId, (int) $payment['customer_id'], $reason, $snapshot, (int) $adminId]
                );
                self::execute('UPDATE payments SET correction_snapshot_json = ? WHERE id = ?', [$snapshot, (int) $payment['id']]);
                if (class_exists('AuditLog')) {
                    AuditLog::record('payment', 'corrected', 'payment', (int) $payment['id'], [
                        'actor_user_id' => $adminId,
                        'customer_id' => (int) $payment['customer_id'],
                        'contract_id' => $contractId,
                        'installment_id' => $installmentId,
                        'description' => 'اصلاحیه مالی هنگام لغو قرارداد',
                        'old_values' => ['amount' => normalize_money($payment['amount'] ?? 0), 'effective_amount' => normalize_money($payment['amount'] ?? 0)],
                        'new_values' => ['amount' => normalize_money($payment['amount'] ?? 0), 'effective_amount' => 0, 'reason' => $reason],
                    ]);
                }
                $corrected++;
            }
            if ($startedTransaction) {
                self::commit();
            }
            return $corrected;
        } catch (Throwable $e) {
            if ($startedTransaction) {
                self::rollBack();
            }
            throw $e;
        }
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
            if (($payment['payment_type'] ?? 'installment') === 'down_payment' || empty($payment['installment_id'])) {
                self::rollBack();
                return ['ok' => false, 'message' => 'پیش‌پرداخت قرارداد به عنوان قسط اصلاح نمی‌شود. مبلغ پیش‌پرداخت را از ویرایش قرارداد تغییر دهید.'];
            }
            self::fetch('SELECT id FROM installments WHERE id = ? FOR UPDATE', [(int) $payment['installment_id']]);
            $snapshot = $payment['correction_snapshot_json'] ?: json_encode([
                'payment_id' => (int) $payment['id'],
                'installment_id' => (int) $payment['installment_id'],
                'contract_id' => (int) $payment['contract_id'],
                'customer_id' => (int) $payment['customer_id'],
                'amount' => normalize_money($payment['amount'] ?? 0),
                'payment_date' => $payment['payment_date'] ?: ($payment['paid_at'] ?: $payment['created_at']),
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
            'paid_amount' => normalize_money($row['paid_amount'] ?? 0),
            'remaining' => max(0, normalize_money($row['base_amount'] ?? 0) - normalize_money($row['paid_amount'] ?? 0)),
            'penalty' => normalize_money($row['penalty'] ?? 0),
            'reward' => normalize_money($row['reward'] ?? 0),
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
            'amount' => normalize_money($payment['amount'] ?? 0),
            'payment_date' => $payment['payment_date'] ?: ($payment['paid_at'] ?: $payment['created_at']),
            'paid_amount_before' => normalize_money($before['paid_amount'] ?? 0),
            'paid_amount_after' => normalize_money($after['paid_amount'] ?? 0),
            'remaining_before' => normalize_money($before['remaining'] ?? 0),
            'remaining_after' => normalize_money($after['remaining'] ?? 0),
            'penalty_before' => normalize_money($before['penalty'] ?? 0),
            'penalty_after' => normalize_money($after['penalty'] ?? 0),
            'reward_before' => normalize_money($before['reward'] ?? 0),
            'reward_after' => normalize_money($after['reward'] ?? 0),
            'status_before' => $before['status'] ?? null,
            'status_after' => $after['status'] ?? null,
            'created_by' => $payment['user_id'] ? (int) $payment['user_id'] : null,
            'created_at' => $payment['created_at'],
        ];
        self::execute('UPDATE payments SET correction_snapshot_json = ? WHERE id = ?', [json_encode($snapshot, JSON_UNESCAPED_UNICODE), (int) $paymentId]);
    }
}
