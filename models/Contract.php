<?php

class Contract extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE contracts ADD COLUMN down_payment_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER principal_amount');
        } catch (Throwable $e) {
        }
        if (class_exists('Payment')) {
            Payment::ensureCorrectionSchema();
        }
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];
        if (!empty($filters['customer_id'])) {
            $where[] = 'c.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['guarantor_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM contract_guarantors cg WHERE cg.contract_id = c.id AND cg.guarantor_id = ?)';
            $params[] = (int) $filters['guarantor_id'];
        }
        if (!empty($filters['operator_id'])) {
            $where[] = 'c.assigned_operator_id = ?';
            $params[] = (int) $filters['operator_id'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        $sql = "SELECT c.*, u.full_name AS customer_name, u.mobile, u.national_id, u.secondary_phone,
                op.full_name AS operator_name
                FROM contracts c
                JOIN users u ON u.id = c.customer_id
                LEFT JOIN users op ON op.id = c.assigned_operator_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.id DESC';
        return self::fetchAll($sql, $params);
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT c.*, u.full_name AS customer_name, u.mobile, u.national_id, u.secondary_phone
             FROM contracts c JOIN users u ON u.id = c.customer_id WHERE c.id = ?",
            [(int) $id]
        );
    }

    public static function guarantors($contractId)
    {
        return self::fetchAll(
            'SELECT u.* FROM contract_guarantors cg JOIN users u ON u.id = cg.guarantor_id WHERE cg.contract_id = ? ORDER BY u.full_name',
            [(int) $contractId]
        );
    }

    public static function guarantorsForCustomer($customerId)
    {
        return self::fetchAll(
            "SELECT c.contract_number, c.id AS contract_id, gu.*
             FROM contracts c
             JOIN contract_guarantors cg ON cg.contract_id = c.id
             JOIN users gu ON gu.id = cg.guarantor_id
             WHERE c.customer_id = ?
             ORDER BY c.id DESC, gu.full_name",
            [(int) $customerId]
        );
    }

    public static function installmentStats($contractId)
    {
        return self::fetch(
            "SELECT COUNT(*) AS total,
             SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
             SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue,
             COALESCE(SUM(GREATEST(base_amount - paid_amount, 0)),0) AS outstanding
             FROM installments
             WHERE contract_id = ?",
            [(int) $contractId]
        ) ?: ['total' => 0, 'paid' => 0, 'overdue' => 0, 'outstanding' => 0];
    }

    public static function createWithInstallments(array $data, array $guarantorIds)
    {
        self::ensureSchema();
        self::begin();
        try {
            self::validateFinancialData($data);
            $settings = Settings::allKeyed();
            $prefix = trim($data['prefix'] ?: $settings['contract_prefix']);
            $serial = max((int) $settings['contract_next_serial'], self::maxSerial($prefix) + 1);
            $contractNumber = self::uniqueNumber($prefix, $serial);

            self::execute(
                'INSERT INTO contracts
                 (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, assigned_operator_id, notes, created_at)
                 VALUES (:customer_id, :contract_number, :prefix, :serial, :principal_amount, :down_payment_amount, :monthly_interest_rate, :interest_type, :months, :start_date, :first_due_date, :status, :assigned_operator_id, :notes, NOW())',
                [
                    'customer_id' => (int) $data['customer_id'],
                    'contract_number' => $contractNumber,
                    'prefix' => $prefix,
                    'serial' => $serial,
                    'principal_amount' => normalize_money($data['principal_amount']),
                    'down_payment_amount' => normalize_money($data['down_payment_amount'] ?? 0),
                    'monthly_interest_rate' => (float) to_english_digits($data['monthly_interest_rate']),
                    'interest_type' => $data['interest_type'] === 'compound' ? 'compound' : 'simple',
                    'months' => (int) to_english_digits($data['months']),
                    'start_date' => $data['start_date'],
                    'first_due_date' => $data['first_due_date'],
                    'status' => 'active',
                    'assigned_operator_id' => $data['assigned_operator_id'] ?: null,
                    'notes' => $data['notes'] ?? '',
                ]
            );
            $contractId = (int) self::lastInsertId();
            self::syncGuarantors($contractId, $guarantorIds, (int) $data['customer_id']);
            self::generateInstallments($contractId, $data);
            Payment::syncDownPayment($contractId, $data['created_by'] ?? null, normalize_money($data['down_payment_amount'] ?? 0), $data['start_date']);
            Settings::set('contract_next_serial', (string) ($serial + 1));
            self::commit();
            Achievement::evaluateCustomer((int) $data['customer_id']);
            return $contractId;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function updateContract($id, array $data, array $guarantorIds)
    {
        self::ensureSchema();
        self::begin();
        try {
            self::validateFinancialData($data);
            self::execute(
                'UPDATE contracts SET customer_id = :customer_id, principal_amount = :principal_amount,
                 down_payment_amount = :down_payment_amount,
                 monthly_interest_rate = :monthly_interest_rate, interest_type = :interest_type,
                 months = :months, start_date = :start_date, first_due_date = :first_due_date,
                 assigned_operator_id = :assigned_operator_id, notes = :notes WHERE id = :id',
                [
                    'id' => (int) $id,
                    'customer_id' => (int) $data['customer_id'],
                    'principal_amount' => normalize_money($data['principal_amount']),
                    'down_payment_amount' => normalize_money($data['down_payment_amount'] ?? 0),
                    'monthly_interest_rate' => (float) to_english_digits($data['monthly_interest_rate']),
                    'interest_type' => $data['interest_type'] === 'compound' ? 'compound' : 'simple',
                    'months' => (int) to_english_digits($data['months']),
                    'start_date' => $data['start_date'],
                    'first_due_date' => $data['first_due_date'],
                    'assigned_operator_id' => $data['assigned_operator_id'] ?: null,
                    'notes' => $data['notes'] ?? '',
                ]
            );
            self::syncGuarantors($id, $guarantorIds, (int) $data['customer_id']);
            Payment::syncDownPayment((int) $id, $data['updated_by'] ?? null, normalize_money($data['down_payment_amount'] ?? 0), $data['start_date']);
            self::commit();
            return true;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function deleteContract($id)
    {
        return self::execute('DELETE FROM contracts WHERE id = ?', [(int) $id]);
    }

    public static function generateInstallments($contractId, array $data)
    {
        $financedAmount = self::financedAmount($data);
        $amount = FinanceHelper::installmentAmount(
            $financedAmount,
            (int) to_english_digits($data['months']),
            (float) to_english_digits($data['monthly_interest_rate']),
            $data['interest_type'] === 'compound' ? 'compound' : 'simple'
        );
        $months = max(1, (int) to_english_digits($data['months']));
        $firstDue = $data['first_due_date'];
        for ($i = 1; $i <= $months; $i++) {
            $dueDate = FinanceHelper::addMonths($firstDue, $i - 1);
            self::execute(
                'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?, NOW())',
                [$contractId, $i, $dueDate, $amount, $amount, $amount <= 0 ? 'paid' : ($dueDate < date('Y-m-d') ? 'overdue' : 'pending')]
            );
        }
    }

    public static function financedAmount(array $data)
    {
        return max(0, normalize_money($data['principal_amount'] ?? 0) - normalize_money($data['down_payment_amount'] ?? 0));
    }

    public static function validateFinancialData(array $data)
    {
        $principalRaw = to_english_digits((string) ($data['principal_amount'] ?? ''));
        $downPaymentRaw = to_english_digits((string) ($data['down_payment_amount'] ?? ''));
        $principal = normalize_money($data['principal_amount'] ?? 0);
        $downPayment = normalize_money($data['down_payment_amount'] ?? 0);
        $months = (int) to_english_digits($data['months'] ?? 0);
        if (strpos($principalRaw, '-') !== false || strpos($downPaymentRaw, '-') !== false) {
            throw new InvalidArgumentException('مبلغ قرارداد و پیش‌پرداخت نمی‌تواند منفی باشد.');
        }
        if ($principal <= 0) {
            throw new InvalidArgumentException('مبلغ اصل قرارداد معتبر نیست.');
        }
        if ($downPayment < 0) {
            throw new InvalidArgumentException('مبلغ پیش‌پرداخت نمی‌تواند منفی باشد.');
        }
        if ($downPayment > $principal) {
            throw new InvalidArgumentException('مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ اصل قرارداد باشد.');
        }
        if ($months <= 0) {
            throw new InvalidArgumentException('تعداد اقساط معتبر نیست.');
        }
    }

    protected static function maxSerial($prefix)
    {
        $row = self::fetch('SELECT MAX(serial) AS max_serial FROM contracts WHERE prefix = ?', [$prefix]);
        return (int) ($row['max_serial'] ?? 0);
    }

    protected static function uniqueNumber($prefix, &$serial)
    {
        do {
            $number = $prefix . '/' . $serial;
            $exists = self::fetch('SELECT id FROM contracts WHERE contract_number = ?', [$number]);
            if ($exists) {
                $serial++;
            }
        } while ($exists);
        return $number;
    }

    protected static function syncGuarantors($contractId, array $guarantorIds, $customerId = null)
    {
        self::execute('DELETE FROM contract_guarantors WHERE contract_id = ?', [(int) $contractId]);
        $guarantorIds = array_unique(array_filter(array_map('intval', $guarantorIds)));
        foreach ($guarantorIds as $guarantorId) {
            if ($customerId && (int) $guarantorId === (int) $customerId) {
                continue;
            }
            self::execute('INSERT INTO contract_guarantors (contract_id, guarantor_id) VALUES (?, ?)', [(int) $contractId, $guarantorId]);
        }
    }
}
