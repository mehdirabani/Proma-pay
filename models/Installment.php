<?php

class Installment extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE installments ADD COLUMN notes TEXT NULL AFTER status');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE installments ADD COLUMN guarantee_serial VARCHAR(190) NULL AFTER notes');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE installments ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER guarantee_serial');
        } catch (Throwable $e) {
        }
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];
        if (!empty($filters['contract_id'])) {
            $where[] = 'i.contract_id = ?';
            $params[] = (int) $filters['contract_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'c.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        $orderBy = !empty($filters['custom_last'])
            ? 'COALESCE(i.is_custom, 0) ASC, i.installment_number ASC, i.due_date ASC, i.id ASC'
            : 'i.due_date ASC, i.id ASC';
        $sql = "SELECT i.*, c.contract_number, c.customer_id, u.full_name AS customer_name, u.mobile, u.national_id
                FROM installments i
                JOIN contracts c ON c.id = i.contract_id
                JOIN users u ON u.id = c.customer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . $orderBy;
        $rows = self::fetchAll($sql, $params);
        return self::withPreview($rows);
    }

    public static function find($id)
    {
        self::ensureSchema();
        $row = self::fetch(
            "SELECT i.*, c.contract_number, c.customer_id, u.full_name AS customer_name, u.mobile, u.national_id
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE i.id = ?",
            [(int) $id]
        );
        if (!$row) {
            return null;
        }
        $preview = FinanceHelper::preview($row, Payment::forInstallment($id), Settings::allKeyed());
        return array_merge($row, $preview);
    }

    public static function overdue($bucket = null, $search = null)
    {
        self::ensureSchema();
        $today = date('Y-m-d');
        $where = "i.status != 'paid' AND i.due_date < ?";
        $params = [$today];
        if ($bucket === 'today') {
            $where = "i.status != 'paid' AND i.due_date = ?";
            $params = [$today];
        } elseif ($bucket === '1-7') {
            $where .= ' AND DATEDIFF(?, i.due_date) BETWEEN 1 AND 7';
            $params[] = $today;
        } elseif ($bucket === '8-30') {
            $where .= ' AND DATEDIFF(?, i.due_date) BETWEEN 8 AND 30';
            $params[] = $today;
        } elseif ($bucket === '30+') {
            $where .= ' AND DATEDIFF(?, i.due_date) > 30';
            $params[] = $today;
        }
        if ($search) {
            $needle = '%' . to_english_digits($search) . '%';
            $where .= ' AND (c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        $rows = self::fetchAll(
            "SELECT i.*, c.contract_number, c.customer_id, c.assigned_operator_id, u.full_name AS customer_name,
             u.mobile, u.secondary_phone, u.national_id,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.status != 'closed') AS legal_case_count
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE {$where}
             ORDER BY i.due_date ASC",
            $params
        );
        return self::withPreview($rows);
    }

    public static function createCustom($contractId, $dueDate, $amount, $notes = '', $guaranteeSerial = '')
    {
        self::ensureSchema();
        $number = (int) self::fetch('SELECT COALESCE(MAX(installment_number), 0) + 1 AS n FROM installments WHERE contract_id = ?', [$contractId])['n'];
        $amount = normalize_money($amount);
        self::execute(
            'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, notes, guarantee_serial, is_custom, created_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 1, NOW())',
            [(int) $contractId, $number, $dueDate, $amount, $amount, $dueDate < date('Y-m-d') ? 'overdue' : 'pending', trim((string) $notes), trim(to_english_digits($guaranteeSerial)) ?: null]
        );
    }

    public static function adjust($id, $penalty, $reward)
    {
        self::execute(
            'UPDATE installments SET manual_penalty_adjustment = ?, manual_reward_adjustment = ? WHERE id = ?',
            [normalize_money($penalty), normalize_money($reward), (int) $id]
        );
        self::refreshStatus($id);
    }

    public static function discountPenalty($id, $type, $value, $createdBy)
    {
        $installment = self::find($id);
        if (!$installment) {
            return false;
        }
        $value = normalize_money($value);
        $discount = $type === 'percent' ? round(((float) $installment['penalty']) * $value / 100) : $value;
        self::execute('UPDATE installments SET penalty_discount_amount = penalty_discount_amount + ? WHERE id = ?', [$discount, (int) $id]);
        self::execute(
            'INSERT INTO penalties (installment_id, type, amount, percent, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $id, $type === 'percent' ? 'percent' : 'fixed', $discount, $type === 'percent' ? $value : null, $createdBy]
        );
        return true;
    }

    public static function markPaid($id, $userId)
    {
        $installment = self::find($id);
        if (!$installment) {
            return false;
        }
        $amount = max(0, $installment['payable']);
        if ($amount > 0) {
            Payment::record($id, $installment['contract_id'], $userId, $amount, 'manual', 'paid', null, null, 'تسویه دستی قسط');
        }
        self::execute('UPDATE installments SET paid_amount = base_amount, remaining_amount = 0, last_payment_date = CURDATE(), status = ? WHERE id = ?', ['paid', (int) $id]);
        return true;
    }

    public static function refreshStatus($id)
    {
        $row = self::fetch('SELECT * FROM installments WHERE id = ?', [(int) $id]);
        if (!$row) {
            return;
        }
        $status = FinanceHelper::status((float) $row['base_amount'], (float) $row['paid_amount'], $row['due_date']);
        self::execute('UPDATE installments SET status = ?, remaining_amount = ? WHERE id = ?', [$status, max(0, (float) $row['base_amount'] - (float) $row['paid_amount']), (int) $id]);
    }

    public static function withPreview(array $rows)
    {
        $settings = Settings::allKeyed();
        foreach ($rows as &$row) {
            $storedStatus = $row['status'] ?? null;
            $storedRemaining = (float) ($row['remaining_amount'] ?? ((float) ($row['base_amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0)));
            $preview = FinanceHelper::preview($row, Payment::forInstallment($row['id']), $settings);
            $row = array_merge($row, $preview);
            if ($row['status'] !== $storedStatus || $storedRemaining !== (float) ($preview['remaining_amount'] ?? 0)) {
                self::execute('UPDATE installments SET status = ?, remaining_amount = ? WHERE id = ?', [$row['status'], $row['remaining_amount'], $row['id']]);
            }
        }
        unset($row);
        return $rows;
    }
}
