<?php

class PaymentReceipt extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        // Receipt tables are provisioned by installation and migrations, never by a page request.
        self::$schemaReady = true;
    }

    public static function submit($installmentId, $customerId, $amount, $receiptPath)
    {
        self::ensureSchema();
        $installment = Installment::find((int) $installmentId);
        if (!$installment || (int) $installment['customer_id'] !== (int) $customerId) {
            throw new InvalidArgumentException('قسط برای پرداخت پیدا نشد.');
        }
        $amount = normalize_money($amount);
        InstallmentSettlementService::assertPayable($installment, $amount);
        $payable = normalize_money($installment['payable'] ?? $installment['remaining_amount'] ?? $installment['base_amount'] ?? 0);
        if ($amount <= 0 || ($payable > 0 && $amount > $payable)) {
            throw new InvalidArgumentException('مبلغ پرداخت معتبر نیست.');
        }
        self::begin();
        try {
            $paymentId = Payment::record(
                (int) $installmentId,
                (int) $installment['contract_id'],
                (int) $customerId,
                $amount,
                'card_transfer',
                'pending',
                null,
                null,
                'رسید کارت به کارت در انتظار بررسی مدیریت'
            );
            self::execute(
                'INSERT INTO payment_receipts (payment_id, installment_id, contract_id, customer_id, amount, receipt_path, status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$paymentId, (int) $installmentId, (int) $installment['contract_id'], (int) $customerId, $amount, $receiptPath, 'pending']
            );
            $receiptId = (int) self::lastInsertId();
            if (class_exists('FileRecord')) {
                try {
                    FileRecord::relatePath($receiptPath, 'payment_receipt', $receiptId, 'payment_receipt', (int) $customerId);
                    FileRecord::relatePath($receiptPath, 'payment', (int) $paymentId, 'payment_receipt', (int) $customerId);
                    FileRecord::relatePath($receiptPath, 'contract', (int) $installment['contract_id'], 'payment_receipt', (int) $customerId);
                } catch (Throwable $e) {
                    ErrorHandler::log('payment_receipt_file_relation', $e, 500);
                }
            }
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueueNotification((int) $customerId, 'رسید پرداخت دریافت شد', 'رسید کارت به کارت شما ثبت شد و در صف بررسی قرار گرفت.', 'payment_receipt', url('installments/panel'), 'payment_receipt', $paymentId);
                foreach (User::all('admin', null, 'active') as $admin) {
                    SystemOutbox::safeEnqueueNotification(
                        (int) $admin['id'],
                        'رسید پرداخت جدید',
                        'یک رسید کارت به کارت برای بررسی مدیریت ارسال شد.',
                        'payment_receipt',
                        url('review', ['tab' => 'receipts']),
                        'payment_receipt',
                        $paymentId
                    );
                }
            }
            self::commit();
            if (class_exists('SystemOutbox')) {
                SystemOutbox::processPending(20, 'payment_receipt', $paymentId);
            }
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function pending()
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT pr.*, p.description, c.contract_number, u.full_name AS customer_name, u.mobile, i.installment_number
             FROM payment_receipts pr
             JOIN payments p ON p.id = pr.payment_id
             JOIN contracts c ON c.id = pr.contract_id
             JOIN users u ON u.id = pr.customer_id
             JOIN installments i ON i.id = pr.installment_id
             WHERE pr.status = 'pending'
            ORDER BY pr.id DESC"
        );
    }

    public static function pendingCount()
    {
        self::ensureSchema();
        $row = self::fetch("SELECT COUNT(*) AS total FROM payment_receipts WHERE status = 'pending'");
        return (int) ($row['total'] ?? 0);
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT pr.*, p.status AS payment_status, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM payment_receipts pr
             JOIN payments p ON p.id = pr.payment_id
             JOIN contracts c ON c.id = pr.contract_id
             JOIN users u ON u.id = pr.customer_id
             JOIN installments i ON i.id = pr.installment_id
             WHERE pr.id = ?",
            [(int) $id]
        );
    }

    public static function approve($id, $adminId, $note = '', $approvedAmount = null)
    {
        self::review((int) $id, (int) $adminId, 'approved', $note, $approvedAmount);
    }

    public static function reject($id, $adminId, $note = '')
    {
        self::review((int) $id, (int) $adminId, 'rejected', $note);
    }

    protected static function review($id, $adminId, $status, $note, $approvedAmount = null)
    {
        self::ensureSchema();
        $receipt = self::find($id);
        if (!$receipt || $receipt['status'] !== 'pending') {
            throw new InvalidArgumentException('رسید قابل بررسی نیست.');
        }
        $botBody = '';
        self::begin();
        try {
            if ($status === 'approved') {
                $paymentDate = date('Y-m-d');
                $installment = Installment::find((int) $receipt['installment_id']);
                if (!$installment) {
                    throw new InvalidArgumentException('قسط مرتبط با رسید پیدا نشد.');
                }
                $actualAmount = $approvedAmount === null || trim((string) $approvedAmount) === ''
                    ? normalize_money($receipt['amount'])
                    : normalize_money($approvedAmount);
                if ($actualAmount <= 0) {
                    throw new InvalidArgumentException('مبلغ تأییدشده باید بیشتر از صفر باشد.');
                }
                $preview = InstallmentSettlementService::assertPayable($installment, $actualAmount, $paymentDate);
                $payable = normalize_money($installment['payable'] ?? $installment['remaining_amount'] ?? $installment['base_amount'] ?? 0);
                if ($payable > 0 && $actualAmount > $payable) {
                    throw new InvalidArgumentException('مبلغ تأییدشده نمی‌تواند بیشتر از بدهی قابل پرداخت قسط باشد.');
                }
                self::execute(
                    "UPDATE payments
                     SET amount = ?, status = 'paid', payment_date = ?, calculated_penalty = ?, calculated_reward = ?,
                         remaining_before_payment = ?, remaining_after_payment = ?, paid_at = NOW(),
                         description = ?
                     WHERE id = ?",
                    [
                        $actualAmount,
                        $paymentDate,
                        $preview['calculated_penalty'],
                        $preview['calculated_reward'],
                        $preview['remaining_before_payment'],
                        $preview['remaining_after_payment'],
                        'رسید کارت به کارت تأیید شد',
                        (int) $receipt['payment_id'],
                    ]
                );
                Payment::applyToInstallment((int) $receipt['installment_id']);
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::safeEnqueueNotification((int) $receipt['customer_id'], 'رسید پرداخت تأیید شد', 'رسید کارت به کارت شما تأیید شد و روی قسط اعمال شد.', 'payment', url('installments/panel'), 'payment_receipt', (int) $receipt['id']);
                }
                $botBody = 'رسید کارت به کارت شما تایید شد و روی قسط اعمال شد.';
            } else {
                self::execute(
                    "UPDATE payments SET status = 'failed', description = ? WHERE id = ?",
                    ['رسید کارت به کارت رد شد', (int) $receipt['payment_id']]
                );
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::safeEnqueueNotification((int) $receipt['customer_id'], 'رسید پرداخت رد شد', 'رسید کارت به کارت شما تأیید نشد.', 'payment', url('installments/panel'), 'payment_receipt', (int) $receipt['id']);
                }
                $botBody = 'رسید کارت به کارت شما تایید نشد. وضعیت پرداخت دوباره در انتظار پرداخت است.';
            }
            UploadHelper::deleteRelative($receipt['receipt_path']);
            self::execute(
                "UPDATE payment_receipts
                 SET status = ?, amount = ?, receipt_path = '', review_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$status, $status === 'approved' ? $actualAmount : normalize_money($receipt['amount'] ?? 0), trim((string) $note) ?: null, $adminId, $id]
            );
            self::commit();
            if (class_exists('SystemOutbox')) {
                SystemOutbox::processPending(20, 'payment_receipt', (int) $receipt['id']);
            }
            try {
                Chat::botMessage((int) $receipt['customer_id'], $botBody, url('installments/panel'));
            } catch (Throwable $ignored) {
            }
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
