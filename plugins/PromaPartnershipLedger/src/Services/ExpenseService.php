<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\Money;

final class ExpenseService
{
    private const TYPES = ['rent', 'legal', 'utilities', 'transport', 'marketing', 'maintenance', 'other'];
    private const METHODS = ['cash', 'bank_transfer', 'card_to_card', 'pos', 'other'];

    public static function create(array $data, $actorId)
    {
        $type = trim((string) ($data['expense_type'] ?? ''));
        $method = trim((string) ($data['payment_method'] ?? ''));
        $amount = Money::fromToman($data['amount_toman'] ?? '0');
        $date = trim((string) ($data['expense_date'] ?? ''));
        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if (!in_array($type, self::TYPES, true) || !in_array($method, self::METHODS, true)
            || $amount <= 0 || !self::validDate($date) || $key === '') {
            throw new \InvalidArgumentException('نوع، تاریخ، مبلغ، روش پرداخت و کلید یکتای هزینه را بررسی کنید.');
        }
        if (strlen($reference) > 120 || strlen($note) > 500) {
            throw new \InvalidArgumentException('طول شرح یا مرجع پرداخت بیش از حد مجاز است.');
        }
        $shares = OwnershipService::resolve($data, 'expense_shares');
        $allocations = OwnershipService::allocationRows($amount, $shares);
        $snapshot = [];
        foreach ($allocations as $partnerId => $row) {
            $snapshot[(int) $partnerId] = ['share_bp' => (int) $row['share_bp'], 'amount_rial' => Money::db($row['amount_rial'])];
        }
        $hash = hash('sha256', json_encode([
            'type' => $type, 'date' => $date, 'amount_rial' => Money::db($amount), 'method' => $method,
            'reference' => $reference, 'note' => $note, 'allocations' => $snapshot,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $started = !\Model::db()->inTransaction();
        if ($started) { \Model::begin(); }
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_expenses WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$key]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('کلید تکرار هزینه با اطلاعات متفاوت استفاده شده است.');
                }
                if ($started) { \Model::commit(); }
                return (int) $existing['id'];
            }
            $expenseNo = self::number();
            \Model::execute('INSERT INTO plugin_inventory_expenses
                (expense_no, expense_type, expense_date, amount_rial, payment_method, payment_reference, note, status, request_hash, idempotency_key, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?, NOW())', [
                $expenseNo, $type, $date, Money::db($amount), $method, $reference !== '' ? $reference : null,
                $note !== '' ? $note : null, $hash, $key, $actorId ? (int) $actorId : null,
            ]);
            $expenseId = (int) \Model::lastInsertId();
            $lines = [];
            foreach ($allocations as $partnerId => $row) {
                \Model::execute('INSERT INTO plugin_inventory_expense_partner_allocations (expense_id, partner_id, share_bp, amount_rial) VALUES (?, ?, ?, ?)', [
                    $expenseId, (int) $partnerId, (int) $row['share_bp'], Money::db($row['amount_rial']),
                ]);
                if ((int) $row['amount_rial'] > 0) {
                    $lines[] = ['account_code' => 'operating_expense', 'partner_id' => (int) $partnerId,
                        'debit_rial' => (int) $row['amount_rial'], 'credit_rial' => 0, 'memo' => 'سهم هزینه ' . $expenseNo];
                }
            }
            $lines[] = ['account_code' => 'cash', 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'پرداخت هزینه ' . $expenseNo];
            $journalId = LedgerService::post('expense:' . $expenseId, 'inventory_expense', $expenseId, 'ثبت هزینه ' . $expenseNo, $actorId, $lines);
            \Model::execute('UPDATE plugin_inventory_expenses SET journal_id = ? WHERE id = ?', [$journalId, $expenseId]);
            AuditService::record('expense_posted', 'expense', $expenseId, $actorId, [], [
                'expense_no' => $expenseNo, 'type' => $type, 'amount_rial' => Money::db($amount), 'allocations' => $snapshot,
            ]);
            if ($started) { \Model::commit(); }
            return $expenseId;
        } catch (\Throwable $error) {
            if ($started) { \Model::rollBack(); }
            throw $error;
        }
    }

    public static function typeLabels()
    {
        return [
            'rent' => 'اجاره', 'legal' => 'حقوقی و شکایت', 'utilities' => 'آب، برق و خدمات',
            'transport' => 'حمل‌ونقل', 'marketing' => 'بازاریابی', 'maintenance' => 'تعمیر و نگهداری', 'other' => 'سایر',
        ];
    }

    private static function number()
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = 'EXP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            if (!\Model::fetch('SELECT id FROM plugin_inventory_expenses WHERE expense_no = ? LIMIT 1', [$number])) { return $number; }
        }
        throw new \RuntimeException('شماره یکتای هزینه تولید نشد. دوباره تلاش کنید.');
    }

    private static function validDate($date)
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
