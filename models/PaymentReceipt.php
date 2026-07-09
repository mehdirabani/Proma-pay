<?php

class PaymentReceipt extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        Payment::ensureCorrectionSchema();
        self::execute(
            "CREATE TABLE IF NOT EXISTS payment_receipts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payment_id BIGINT UNSIGNED NOT NULL,
                installment_id BIGINT UNSIGNED NOT NULL,
                contract_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                amount DECIMAL(18,2) NOT NULL,
                receipt_path VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                review_note TEXT NULL,
                reviewed_by BIGINT UNSIGNED NULL,
                submitted_at DATETIME NOT NULL,
                reviewed_at DATETIME NULL,
                KEY idx_payment_receipts_status (status),
                KEY idx_payment_receipts_customer (customer_id),
                CONSTRAINT fk_payment_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
        if ($amount <= 0 || $amount > (float) $installment['payable']) {
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
            Notification::create((int) $customerId, 'رسید پرداخت دریافت شد', 'رسید کارت به کارت شما ثبت شد و در صف بررسی قرار گرفت.', 'payment_receipt', url('portal/installments'));
            foreach (User::all('admin', null, 'active') as $admin) {
                Notification::create(
                    (int) $admin['id'],
                    'رسید پرداخت جدید',
                    'یک رسید کارت به کارت برای بررسی مدیریت ارسال شد.',
                    'payment_receipt',
                    url('review', ['tab' => 'receipts'])
                );
            }
            self::commit();
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
                $payable = (float) ($installment['payable'] ?? $installment['remaining_amount'] ?? $installment['base_amount'] ?? 0);
                if ($payable > 0 && $actualAmount > $payable) {
                    throw new InvalidArgumentException('مبلغ تأییدشده نمی‌تواند بیشتر از بدهی قابل پرداخت قسط باشد.');
                }
                $preview = FinanceHelper::paymentPreview(
                    $installment,
                    Payment::forInstallment((int) $receipt['installment_id']),
                    Settings::allKeyed(),
                    $actualAmount,
                    $paymentDate
                );
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
                Notification::create((int) $receipt['customer_id'], 'رسید پرداخت تأیید شد', 'رسید کارت به کارت شما تأیید شد و روی قسط اعمال شد.', 'payment', url('portal/installments'));
                $botBody = 'رسید کارت به کارت شما تایید شد و روی قسط اعمال شد.';
            } else {
                self::execute(
                    "UPDATE payments SET status = 'failed', description = ? WHERE id = ?",
                    ['رسید کارت به کارت رد شد', (int) $receipt['payment_id']]
                );
                Notification::create((int) $receipt['customer_id'], 'رسید پرداخت رد شد', 'رسید کارت به کارت شما تأیید نشد.', 'payment', url('portal/installments'));
                $botBody = 'رسید کارت به کارت شما تایید نشد. وضعیت پرداخت دوباره در انتظار پرداخت است.';
            }
            UploadHelper::deleteRelative($receipt['receipt_path']);
            self::execute(
                "UPDATE payment_receipts
                 SET status = ?, amount = ?, receipt_path = '', review_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$status, $status === 'approved' ? $actualAmount : (float) $receipt['amount'], trim((string) $note) ?: null, $adminId, $id]
            );
            self::commit();
            try {
                Chat::botMessage((int) $receipt['customer_id'], $botBody, url('portal/installments'));
            } catch (Throwable $ignored) {
            }
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
