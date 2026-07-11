<?php

class PaymentGroupService
{
    public static function create($contractId, array $installmentIds, $amount, $userId, $method = 'manual', $description = '', $allocateToNext = true, $idempotencyKey = null)
    {
        $contractId = (int) $contractId;
        $amount = self::moneyInteger($amount);
        $installmentIds = array_values(array_unique(array_filter(array_map('intval', $installmentIds))));
        if ($contractId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException('قرارداد و مبلغ پرداخت گروهی معتبر نیست.');
        }
        if (!$installmentIds) {
            throw new InvalidArgumentException('حداقل یک قسط را انتخاب کنید.');
        }
        $started = false;
        if (!Model::db()->inTransaction()) {
            Model::begin();
            $started = true;
        }
        try {
            if ($idempotencyKey) {
                $existing = Model::fetch('SELECT * FROM payment_groups WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [(string) $idempotencyKey]);
                if ($existing) {
                    if ($started) {
                        Model::commit();
                    }
                    return $existing;
                }
            }
            $contract = Model::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract || ($contract['status'] ?? '') === 'cancelled') {
                throw new InvalidArgumentException('قرارداد برای پرداخت گروهی معتبر نیست.');
            }
            $placeholders = implode(',', array_fill(0, count($installmentIds), '?'));
            $params = array_merge([$contractId], $installmentIds);
            $selected = Model::fetchAll(
                "SELECT * FROM installments
                 WHERE contract_id = ? AND id IN ({$placeholders}) AND status NOT IN ('paid', 'cancelled')
                 ORDER BY installment_number ASC FOR UPDATE",
                $params
            );
            if (count($selected) !== count($installmentIds)) {
                throw new InvalidArgumentException('یکی از اقساط انتخاب‌شده قابل پرداخت نیست یا به این قرارداد تعلق ندارد.');
            }
            if ($allocateToNext) {
                $selectedIds = array_map('intval', array_column($selected, 'id'));
                $next = Model::fetchAll(
                    "SELECT * FROM installments
                     WHERE contract_id = ? AND status NOT IN ('paid', 'cancelled')
                     AND id NOT IN ({$placeholders})
                     ORDER BY installment_number ASC FOR UPDATE",
                    array_merge([$contractId], $selectedIds)
                );
                $selected = array_merge($selected, $next);
            }
            $totalOutstanding = 0;
            foreach ($selected as $row) {
                $totalOutstanding += max(0, self::moneyInteger($row['base_amount']) - self::moneyInteger($row['paid_amount']));
            }
            if ($amount > $totalOutstanding) {
                throw new InvalidArgumentException('مبلغ پرداخت از مجموع بدهی قابل تخصیص این قرارداد بیشتر است.');
            }
            $groupNumber = 'PG-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            Model::execute(
                'INSERT INTO payment_groups (group_number, contract_id, customer_id, created_by, requested_amount, method, status, idempotency_key, description, created_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$groupNumber, $contractId, (int) $contract['customer_id'], $userId ? (int) $userId : null, self::moneyDecimal($amount), $method === 'card_transfer' ? 'card_transfer' : 'manual', 'paid', $idempotencyKey, trim((string) $description) ?: 'پرداخت گروهی اقساط']
            );
            $groupId = (int) Model::lastInsertId();
            $remaining = $amount;
            $allocated = 0;
            foreach ($selected as $row) {
                if ($remaining <= 0) {
                    break;
                }
                $outstanding = max(0, self::moneyInteger($row['base_amount']) - self::moneyInteger($row['paid_amount']));
                $chunk = min($remaining, $outstanding);
                if ($chunk <= 0) {
                    continue;
                }
                $paymentId = Payment::record((int) $row['id'], $contractId, $userId, self::moneyDecimal($chunk), $method, 'paid', null, null, 'پرداخت گروهی ' . $groupNumber, date('Y-m-d'), 'installment');
                Model::execute(
                    'INSERT INTO payment_allocations (payment_group_id, payment_id, contract_id, installment_id, allocated_amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                    [$groupId, $paymentId, $contractId, (int) $row['id'], self::moneyDecimal($chunk)]
                );
                $remaining -= $chunk;
                $allocated += $chunk;
            }
            if ($remaining !== 0) {
                throw new RuntimeException('تخصیص کامل مبلغ پرداخت گروهی انجام نشد.');
            }
            Model::execute('UPDATE payment_groups SET allocated_amount = ?, status = ?, completed_at = NOW() WHERE id = ?', [self::moneyDecimal($allocated), 'completed', $groupId]);
            if (class_exists('AuditLog')) {
                AuditLog::record('payment', 'group_completed', 'payment_group', $groupId, [
                    'actor_user_id' => $userId,
                    'customer_id' => (int) $contract['customer_id'],
                    'contract_id' => $contractId,
                    'new_values' => ['requested_amount' => self::moneyDecimal($amount), 'allocated_amount' => self::moneyDecimal($allocated)],
                ]);
            }
            if (class_exists('PluginManager')) {
                PluginManager::fire('payment.group.completed', ['payment_group_id' => $groupId, 'contract_id' => $contractId, 'actor_user_id' => $userId], true);
            }
            if ($started) {
                Model::commit();
            }
            return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [$groupId]);
        } catch (Throwable $e) {
            if ($started) {
                Model::rollBack();
            }
            throw $e;
        }
    }

    protected static function moneyInteger($value)
    {
        $value = trim(str_replace(['٬', ',', '،', 'تومان', 'ریال', ' '], '', to_english_digits($value)));
        if ($value === '' || !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return 0;
        }
        return (int) preg_replace('/\..*$/', '', $value);
    }

    protected static function moneyDecimal($value)
    {
        return number_format(self::moneyInteger($value), 2, '.', '');
    }
}
