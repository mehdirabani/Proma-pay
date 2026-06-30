<?php

class LegalCase extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE legal_cases ADD COLUMN expense_reason TEXT NULL AFTER expense_amount');
        } catch (Throwable $e) {
        }
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];
        if (!empty($filters['lawyer_id'])) {
            $where[] = '(lc.lawyer_id = ? OR lc.lawyer_id IS NULL)';
            $params[] = (int) $filters['lawyer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'lc.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR lc.complaint_number LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        $sql = "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile,
                lawyer.full_name AS lawyer_name
                FROM legal_cases lc
                JOIN contracts c ON c.id = lc.contract_id
                JOIN users u ON u.id = lc.customer_id
                LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY lc.id DESC';
        return self::fetchAll($sql, $params);
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             WHERE lc.id = ?",
            [(int) $id]
        );
    }

    public static function createCase($lawyerId, $contractId, $notes = '')
    {
        self::ensureSchema();
        $contract = Contract::find($contractId);
        if (!$contract) {
            return false;
        }
        self::execute(
            'INSERT INTO legal_cases (lawyer_id, customer_id, contract_id, status, stage, notes, expense_amount, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())',
            [$lawyerId ?: null, $contract['customer_id'], (int) $contractId, 'open', 'ثبت اولیه', $notes]
        );
        self::execute('UPDATE contracts SET legal_status = ? WHERE id = ?', ['referred', (int) $contractId]);
        if ($lawyerId) {
            Notification::create($lawyerId, 'پرونده حقوقی جدید', 'یک پرونده حقوقی برای بررسی ثبت شد.', 'legal', url('lawyer'));
        }
        return true;
    }

    public static function updateCase($id, array $data)
    {
        self::ensureSchema();
        $expense = normalize_money($data['expense_amount'] ?? 0);
        if ($expense > 0 && trim((string) ($data['expense_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('علت هزینه حقوقی الزامی است.');
        }
        self::execute(
            'UPDATE legal_cases SET lawyer_id = ?, stage = ?, status = ?, complaint_number = ?, expense_amount = ?, expense_reason = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [
                $data['lawyer_id'] ?: null,
                $data['stage'],
                $data['status'],
                $data['complaint_number'] ?: null,
                $expense,
                $data['expense_reason'] ?? '',
                $data['notes'] ?? '',
                (int) $id,
            ]
        );
    }

    public static function eligibleContracts()
    {
        return self::fetchAll(
            "SELECT DISTINCT c.*, u.full_name AS customer_name, u.mobile
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.contract_id = c.id
             WHERE i.status != 'paid' AND DATEDIFF(CURDATE(), i.due_date) > 30
             ORDER BY c.id DESC"
        );
    }
}
