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
                Model::execute('UPDATE payments SET payment_group_id = ? WHERE id = ?', [$groupId, $paymentId]);
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
                try {
                    AuditLog::record('payment', 'group_completed', 'payment_group', $groupId, [
                        'actor_user_id' => $userId,
                        'customer_id' => (int) $contract['customer_id'],
                        'contract_id' => $contractId,
                        'new_values' => ['requested_amount' => self::moneyDecimal($amount), 'allocated_amount' => self::moneyDecimal($allocated)],
                    ]);
                } catch (Throwable $e) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment.group.audit', $e);
                    }
                }
            }
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('payment.group.completed', ['payment_group_id' => $groupId, 'contract_id' => $contractId, 'actor_user_id' => $userId], 'payment_group', $groupId);
            }
            if ($started) {
                Model::commit();
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::processPending(50);
                }
            }
            return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [$groupId]);
        } catch (Throwable $e) {
            if ($started) {
                Model::rollBack();
            }
            throw $e;
        }
    }

    public static function createPendingGateway($contractId, array $installmentIds, $amount, $userId, $trackId, $idempotencyKey = null, $gatewayId = 'zibal')
    {
        $contractId = (int) $contractId;
        $amount = self::moneyInteger($amount);
        $gatewayId = strtolower(trim((string) $gatewayId));
        $installmentIds = array_values(array_unique(array_filter(array_map('intval', $installmentIds))));
        if ($contractId <= 0 || $amount <= 0 || !$installmentIds || !preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $gatewayId)) {
            throw new InvalidArgumentException('اطلاعات پرداخت گروهی آنلاین معتبر نیست.');
        }
        $started = false;
        if (!Model::db()->inTransaction()) {
            Model::begin();
            $started = true;
        }
        try {
            if ($idempotencyKey) {
                $existing = Model::fetch('SELECT * FROM payment_groups WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
                if ($existing) {
                    if ($started) {
                        Model::commit();
                    }
                    return $existing;
                }
            }
            $contract = Model::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract || (int) $contract['customer_id'] !== (int) $userId || in_array($contract['status'], ['cancelled', 'completed', 'closed'], true)) {
                throw new InvalidArgumentException('قرارداد برای پرداخت آنلاین معتبر نیست.');
            }
            $selected = self::lockedInstallments($contractId, $installmentIds);
            if (count($selected) !== count($installmentIds)) {
                throw new InvalidArgumentException('یکی از اقساط انتخاب‌شده دیگر قابل پرداخت نیست.');
            }
            $groupNumber = 'PG-' . strtoupper(substr($gatewayId, 0, 3)) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            Model::execute(
                'INSERT INTO payment_groups (group_number, contract_id, customer_id, created_by, requested_amount, method, status, gateway_track_id, idempotency_key, description, selection_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$groupNumber, $contractId, (int) $userId, (int) $userId, self::moneyDecimal($amount), $gatewayId, 'pending', trim((string) $trackId), $idempotencyKey, 'پرداخت آنلاین چندقسطی', json_encode($installmentIds, JSON_UNESCAPED_UNICODE)]
            );
            $groupId = (int) Model::lastInsertId();
            if (class_exists('AuditLog')) {
                try {
                    AuditLog::record('payment', 'group_created', 'payment_group', $groupId, ['actor_user_id' => $userId, 'customer_id' => $userId, 'contract_id' => $contractId, 'new_values' => ['status' => 'pending', 'amount' => self::moneyDecimal($amount), 'installment_ids' => $installmentIds]]);
                } catch (Throwable $e) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment.group.audit', $e);
                    }
                }
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

    public static function failGateway($groupId, $reason = '')
    {
        $groupId = (int) $groupId;
        if ($groupId <= 0) {
            return 0;
        }
        return Model::execute(
            "UPDATE payment_groups SET status = 'failed', description = ? WHERE id = ? AND status = 'pending'",
            [substr(trim((string) $reason) ?: 'پرداخت گروهی در درگاه تکمیل نشد.', 0, 255), $groupId]
        );
    }

    public static function completeGateway($groupId, $amount, $refId, $actorId = null)
    {
        $amount = self::moneyInteger($amount);
        $started = false;
        if (!Model::db()->inTransaction()) {
            Model::begin();
            $started = true;
        }
        try {
            $group = Model::fetch('SELECT * FROM payment_groups WHERE id = ? FOR UPDATE', [(int) $groupId]);
            if (!$group) {
                throw new InvalidArgumentException('پرداخت گروهی درگاه پیدا نشد.');
            }
            if ($group['status'] === 'completed') {
                if ($started) {
                    Model::commit();
                }
                return $group;
            }
            if ($group['status'] !== 'pending' || self::moneyInteger($group['requested_amount']) !== $amount) {
                throw new InvalidArgumentException('مبلغ یا وضعیت پرداخت گروهی درگاه معتبر نیست.');
            }
            $ids = json_decode((string) ($group['selection_json'] ?? ''), true) ?: [];
            $selected = self::lockedInstallments((int) $group['contract_id'], $ids);
            if (count($selected) !== count($ids)) {
                throw new InvalidArgumentException('یکی از اقساط پرداخت گروهی دیگر قابل تخصیص نیست.');
            }
            $selectedIds = array_map('intval', array_column($selected, 'id'));
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $next = Model::fetchAll(
                "SELECT * FROM installments WHERE contract_id = ? AND status NOT IN ('paid','cancelled') AND id NOT IN ({$placeholders}) ORDER BY installment_number ASC FOR UPDATE",
                array_merge([(int) $group['contract_id']], $selectedIds)
            );
            $selected = array_merge($selected, $next);
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
                $allocationTrackId = substr((string) $group['gateway_track_id'], 0, 80) . ':' . (int) $row['id'];
                $paymentId = Payment::record((int) $row['id'], (int) $group['contract_id'], (int) $group['customer_id'], self::moneyDecimal($chunk), $group['method'], 'paid', $allocationTrackId, $refId, 'پرداخت آنلاین گروهی ' . $group['group_number'], date('Y-m-d'), 'installment');
                Model::execute('UPDATE payments SET payment_group_id = ? WHERE id = ?', [(int) $group['id'], $paymentId]);
                Model::execute('INSERT INTO payment_allocations (payment_group_id, payment_id, contract_id, installment_id, allocated_amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [(int) $group['id'], $paymentId, (int) $group['contract_id'], (int) $row['id'], self::moneyDecimal($chunk)]);
                $remaining -= $chunk;
                $allocated += $chunk;
            }
            if ($remaining !== 0) {
                throw new InvalidArgumentException('تخصیص مبلغ درگاه به اقساط کامل نشد.');
            }
            Model::execute('UPDATE payment_groups SET allocated_amount = ?, status = \'completed\', description = ?, completed_at = NOW() WHERE id = ?', [self::moneyDecimal($allocated), 'پرداخت آنلاین چندقسطی - ref ' . trim((string) $refId), (int) $group['id']]);
            if (class_exists('AuditLog')) {
                try {
                    AuditLog::record('payment', 'group_completed', 'payment_group', (int) $group['id'], ['actor_user_id' => $actorId ?: $group['customer_id'], 'customer_id' => (int) $group['customer_id'], 'contract_id' => (int) $group['contract_id'], 'new_values' => ['allocated_amount' => self::moneyDecimal($allocated), 'gateway_ref_id' => $refId]]);
                } catch (Throwable $e) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment.group.audit', $e);
                    }
                }
            }
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('payment.group.completed', ['payment_group_id' => (int) $group['id'], 'contract_id' => (int) $group['contract_id'], 'actor_user_id' => $actorId ?: (int) $group['customer_id']], 'payment_group', (int) $group['id']);
            }
            if ($started) {
                Model::commit();
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::processPending(50);
                }
            }
            return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [(int) $group['id']]);
        } catch (Throwable $e) {
            if ($started) {
                Model::rollBack();
            }
            throw $e;
        }
    }

    protected static function lockedInstallments($contractId, array $installmentIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $installmentIds))));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Model::fetchAll("SELECT * FROM installments WHERE contract_id = ? AND id IN ({$placeholders}) AND status NOT IN ('paid','cancelled') ORDER BY installment_number ASC FOR UPDATE", array_merge([(int) $contractId], $ids));
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
