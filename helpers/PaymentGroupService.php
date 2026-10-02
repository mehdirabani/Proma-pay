<?php

/**
 * Commits a quoted payment as one parent payment_group and immutable child
 * allocations. Selected scope stays within the selected rows; schedule scope
 * explicitly expands to all eligible installments in the same contract.
 */
class PaymentGroupService
{
    public static function create($contractId, array $installmentIds, $amount, $userId, $method = 'manual', $description = '', $allocateToNext = false, $idempotencyKey = null, $quoteUuid = null, $paymentDate = null, $paymentTime = null, $scope = 'selected')
    {
        return self::commit(
            (int) $contractId,
            $installmentIds,
            $amount,
            $userId,
            $method,
            $description,
            $idempotencyKey,
            $quoteUuid,
            $paymentDate ?: date('Y-m-d'),
            $paymentTime ?: date('H:i'),
            null,
            null,
            $scope
        );
    }

    public static function createPendingGateway($contractId, array $installmentIds, $amount, $userId, $trackId, $idempotencyKey = null, $gatewayId = 'zibal', $quoteUuid = null, $scope = 'selected')
    {
        $contractId = (int) $contractId;
        $amount = self::moneyInteger($amount);
        $ids = self::ids($installmentIds);
        $scope = self::allocationScope($scope);
        if ($contractId <= 0 || !$ids || $amount <= 0) throw new InvalidArgumentException('اطلاعات پرداخت آنلاین چندقسطی معتبر نیست.');
        $started = !Model::db()->inTransaction();
        if ($started) Model::begin();
        try {
            if ($idempotencyKey && ($existing = Model::fetch('SELECT * FROM payment_groups WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [(string) $idempotencyKey]))) {
                if ($started) Model::commit();
                return $existing;
            }
            $contract = self::lockedContract($contractId, $userId, true);
            $rows = SettlementQuoteService::loadInstallments($contractId, $ids, true);
            $verified = SettlementQuoteService::verifyLocked($quoteUuid, $contract, $rows, $ids, $amount, $userId, $scope);
            $groupNumber = self::number('PG-' . strtoupper(substr((string) $gatewayId, 0, 3)));
            Model::execute(
                'INSERT INTO payment_groups (group_number, contract_id, customer_id, created_by, requested_amount, method, status, gateway_track_id, idempotency_key, quote_uuid, allocation_scope, description, selection_json, allocation_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$groupNumber, $contractId, (int) $contract['customer_id'], (int) $userId, self::moneyDecimal($amount), self::method($gatewayId), 'pending', trim((string) $trackId), $idempotencyKey, trim((string) $quoteUuid) ?: null, $scope, 'پرداخت آنلاین چندقسطی', json_encode($ids), json_encode($verified['plan']['allocations'], JSON_UNESCAPED_UNICODE)]
            );
            $groupId = (int) Model::lastInsertId();
            if ($started) Model::commit();
            return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [$groupId]);
        } catch (Throwable $e) {
            if ($started) Model::rollBack();
            throw $e;
        }
    }

    public static function completeGateway($groupId, $amount, $refId, $actorId = null)
    {
        $groupId = (int) $groupId;
        $started = !Model::db()->inTransaction();
        $group = null;
        if ($started) Model::begin();
        try {
            $group = Model::fetch('SELECT * FROM payment_groups WHERE id = ? FOR UPDATE', [$groupId]);
            if (!$group) throw new InvalidArgumentException('پرداخت گروهی درگاه پیدا نشد.');
            if (($group['status'] ?? '') === 'completed') {
                if (!empty($group['gateway_ref_id']) && (string) $group['gateway_ref_id'] !== (string) $refId) {
                    throw new InvalidArgumentException('شناسه تأیید درگاه با پرداخت ثبت‌شده یکسان نیست.', 409);
                }
                if ($started) Model::commit();
                return $group;
            }
            if (($group['status'] ?? '') === 'review_required') {
                if (normalize_money($group['gateway_verified_amount'] ?? 0) !== self::moneyInteger($amount)
                    || (string) ($group['gateway_ref_id'] ?? '') !== (string) $refId) {
                    throw new InvalidArgumentException('اطلاعات تأیید درگاه با پرداخت در انتظار بررسی یکسان نیست.', 409);
                }
                if ($started) Model::commit();
                return $group;
            }
            if (($group['status'] ?? '') !== 'pending' || self::moneyInteger($group['requested_amount']) !== self::moneyInteger($amount)) {
                throw new InvalidArgumentException('مبلغ یا وضعیت پرداخت گروهی درگاه معتبر نیست.', 409);
            }
            $ids = self::ids(json_decode((string) ($group['selection_json'] ?? '[]'), true) ?: []);
            $contract = self::lockedContract((int) $group['contract_id'], (int) $group['customer_id'], true);
            $rows = SettlementQuoteService::loadInstallments((int) $group['contract_id'], $ids, true);
            $scope = self::allocationScope($group['allocation_scope'] ?? 'selected');
            $verified = SettlementQuoteService::verifyLocked((string) ($group['quote_uuid'] ?? ''), $contract, $rows, $ids, $amount, (int) $group['customer_id'], $scope);
            $result = self::writeAllocations($group, $verified['plan'], date('Y-m-d'), date('H:i'), $refId, $actorId ?: (int) $group['customer_id']);
            Model::execute('UPDATE payment_groups SET gateway_verified_amount = ?, gateway_ref_id = ? WHERE id = ?', [self::moneyInteger($amount), substr((string) $refId, 0, 100), $groupId]);
            SettlementQuoteService::markUsed((string) ($group['quote_uuid'] ?? ''), (int) $group['id']);
            if ($started) Model::commit();
            if ($started) self::afterCommit($result, $actorId ?: (int) $group['customer_id']);
            $result['gateway_verified_amount'] = self::moneyInteger($amount);
            $result['gateway_ref_id'] = substr((string) $refId, 0, 100);
            return $result;
        } catch (Throwable $e) {
            if ($started) Model::rollBack();
            if ($started && ($group['status'] ?? '') === 'pending') {
                return self::recordGatewayReview(
                    $groupId,
                    $amount,
                    $refId,
                    $e instanceof InvalidArgumentException ? 'مبلغ یا پیش‌فاکتور پس از تأیید درگاه نیاز به بررسی دارد.' : 'تخصیص وجه تأییدشده در سامانه انجام نشد.'
                );
            }
            throw $e;
        }
    }

    /** Preserve verified gateway money until an authorized reconciliation is completed. */
    public static function recordGatewayReview($groupId, $amount, $refId, $reason)
    {
        $groupId = (int) $groupId;
        $started = !Model::db()->inTransaction();
        if ($started) Model::begin();
        try {
            $group = Model::fetch('SELECT * FROM payment_groups WHERE id = ? FOR UPDATE', [$groupId]);
            if (!$group) throw new InvalidArgumentException('پرداخت گروهی درگاه پیدا نشد.', 404);
            if (($group['status'] ?? '') === 'completed') {
                if ($started) Model::commit();
                return $group;
            }
            if (($group['status'] ?? '') === 'review_required') {
                if ((string) ($group['gateway_ref_id'] ?? '') !== (string) $refId
                    || normalize_money($group['gateway_verified_amount'] ?? 0) !== self::moneyInteger($amount)) {
                    throw new InvalidArgumentException('دو تأیید متفاوت برای یک پرداخت گروهی ثبت شده است.', 409);
                }
                if ($started) Model::commit();
                return $group;
            }
            if (($group['status'] ?? '') !== 'pending') throw new InvalidArgumentException('وضعیت پرداخت گروهی برای بررسی معتبر نیست.', 409);
            Model::execute(
                "UPDATE payment_groups SET status = 'review_required', gateway_verified_amount = ?, gateway_ref_id = ?, reconciliation_reason = ? WHERE id = ?",
                [$amount === null ? null : self::moneyInteger($amount), substr((string) $refId, 0, 100) ?: null, substr(trim((string) $reason), 0, 255), $groupId]
            );
            $review = Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [$groupId]);
            if ($started) Model::commit();
            if ($started) {
                try {
                    if (class_exists('AuditLog')) AuditLog::record('payment_gateway', 'reconciliation_required', 'payment_group', $groupId, [
                        'actor_type' => 'gateway', 'contract_id' => (int) $group['contract_id'],
                        'new_values' => ['status' => 'review_required', 'gateway_verified_amount' => $review['gateway_verified_amount'], 'gateway_ref_id' => $review['gateway_ref_id']],
                    ]);
                    if (class_exists('SystemOutbox')) SystemOutbox::safeEnqueuePluginHook('payment.group.reconciliation_required', ['payment_group_id' => $groupId, 'contract_id' => (int) $group['contract_id']], 'payment_group', $groupId);
                } catch (Throwable $auditError) {
                    if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('payment_group.reconciliation_audit', $auditError);
                }
            }
            return $review;
        } catch (Throwable $e) {
            if ($started) Model::rollBack();
            throw $e;
        }
    }

    public static function failGateway($groupId, $reason = '')
    {
        return Model::execute("UPDATE payment_groups SET status = 'failed', description = ? WHERE id = ? AND status = 'pending'", [substr(trim((string) $reason) ?: 'پرداخت گروهی در درگاه تکمیل نشد.', 0, 255), (int) $groupId]);
    }

    private static function commit($contractId, array $installmentIds, $amount, $userId, $method, $description, $requestUuid, $quoteUuid, $paymentDate, $paymentTime, $gatewayTrackId, $gatewayRefId, $scope = 'selected')
    {
        $ids = self::ids($installmentIds);
        $amount = self::moneyInteger($amount);
        $scope = self::allocationScope($scope);
        if ($contractId <= 0 || !$ids || $amount <= 0) throw new InvalidArgumentException('قرارداد، اقساط و مبلغ پرداخت را کامل کنید.');
        $requestUuid = PaymentRequest::normalizeUuid($requestUuid ?: '');
        $requestHash = PaymentRequest::hash([
            'contract_id' => (int) $contractId, 'installment_ids' => $ids, 'amount' => $amount,
            'method' => self::method($method), 'quote_uuid' => trim((string) $quoteUuid), 'payment_date' => $paymentDate, 'scope' => $scope,
        ]);
        $request = PaymentRequest::beginSettlement($requestUuid, $userId, $contractId, $ids, $quoteUuid, $requestHash);
        if (($request['status'] ?? '') === 'completed') {
            return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [(int) $request['payment_group_id']]);
        }
        Model::begin();
        try {
            $contract = self::lockedContract($contractId, $userId, false);
            $submittedIds = $ids;
            if ($scope === 'schedule') {
                // Overflow is a contract-schedule policy, not an instruction to
                // trust client-supplied future rows. Expand while holding the
                // contract lock, then persist the exact eligible row set in the
                // quote and payment group.
                $allRows = SettlementQuoteService::loadInstallments($contractId, [], true);
                $allIds = array_map(static function ($row) { return (int) $row['id']; }, $allRows);
                if (array_diff($submittedIds, $allIds)) {
                    throw new InvalidArgumentException('یکی از اقساط انتخاب‌شده به این قرارداد تعلق ندارد.', 409);
                }
                if (trim((string) $quoteUuid) === '') {
                    $generatedQuote = SettlementQuoteService::persistQuote($contract, $allRows, $userId, $scope, $paymentDate);
                    $quoteUuid = (string) ($generatedQuote['quote_uuid'] ?? '');
                    $ids = array_values(array_unique(array_map('intval', json_decode((string) ($generatedQuote['selected_installment_ids_json'] ?? '[]'), true) ?: [])));
                    if (!array_intersect($submittedIds, $ids)) {
                        throw new InvalidArgumentException('از اقساط انتخاب‌شده مورد قابل پرداختی باقی نمانده است.', 409);
                    }
                }
            }
            $rows = SettlementQuoteService::loadInstallments($contractId, $ids, true);
            if (trim((string) $quoteUuid) === '') {
                $generatedQuote = SettlementQuoteService::persistQuote($contract, $rows, $userId, $scope, $paymentDate);
                $quoteUuid = (string) ($generatedQuote['quote_uuid'] ?? '');
                $ids = array_values(array_unique(array_map('intval', json_decode((string) ($generatedQuote['selected_installment_ids_json'] ?? '[]'), true) ?: [])));
                $rows = SettlementQuoteService::loadInstallments($contractId, $ids, true);
            }
            $verified = SettlementQuoteService::verifyLocked($quoteUuid, $contract, $rows, $ids, $amount, $userId, $scope, $paymentDate);
            $groupNumber = self::number('PG');
            Model::execute(
                'INSERT INTO payment_groups (group_number, contract_id, customer_id, created_by, requested_amount, method, status, idempotency_key, quote_uuid, payment_request_uuid, allocation_scope, description, selection_json, allocation_json, created_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$groupNumber, $contractId, (int) $contract['customer_id'], $userId ? (int) $userId : null, self::moneyDecimal($amount), self::method($method), 'completed', $requestUuid, trim((string) $quoteUuid) ?: null, $requestUuid, $scope, trim((string) $description) ?: 'پرداخت گروهی اقساط', json_encode($ids), json_encode($verified['plan']['allocations'], JSON_UNESCAPED_UNICODE)]
            );
            $group = Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [(int) Model::lastInsertId()]);
            $result = self::writeAllocations($group, $verified['plan'], $paymentDate, $paymentTime, $gatewayRefId, $userId);
            SettlementQuoteService::markUsed((string) ($quoteUuid ?? ''), (int) $group['id']);
            PaymentRequest::completeSettlement($requestUuid, (int) $group['id']);
            Model::commit();
            self::afterCommit($result, $userId);
            return $result;
        } catch (Throwable $e) {
            Model::rollBack();
            PaymentRequest::fail($requestUuid, $e);
            throw $e;
        }
    }

    private static function writeAllocations(array $group, array $plan, $paymentDate, $paymentTime, $gatewayRefId, $actorId)
    {
        $allocated = 0;
        foreach ($plan['allocations'] as $allocation) {
            if (normalize_money($allocation['allocated_amount'] ?? 0) <= 0) continue;
            $track = self::allocationTrackId($group, $allocation);
            $paymentId = Payment::record(
                (int) $allocation['installment_id'], (int) $group['contract_id'], $actorId ?: (int) $group['customer_id'],
                self::moneyDecimal($allocation['allocated_amount']), (string) $group['method'], 'paid', $track, $gatewayRefId,
                'تخصیص پرداخت ' . $group['group_number'], $paymentDate, 'installment', $paymentTime, $allocation, $group['quote_uuid'] ?? null
            );
            Model::execute('UPDATE payments SET payment_group_id = ? WHERE id = ?', [(int) $group['id'], $paymentId]);
            Model::execute(
                'INSERT INTO payment_allocations (payment_group_id, payment_id, contract_id, installment_id, allocated_amount, principal_applied, normal_penalty_applied, legal_penalty_applied, reward_applied, remaining_before, remaining_after, status_after, quote_uuid, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $group['id'], $paymentId, (int) $group['contract_id'], (int) $allocation['installment_id'], self::moneyDecimal($allocation['allocated_amount']), self::moneyDecimal($allocation['principal_applied']), self::moneyDecimal($allocation['normal_penalty_applied']), self::moneyDecimal($allocation['legal_penalty_applied']), self::moneyDecimal($allocation['reward_applied']), self::moneyDecimal($allocation['remaining_before']), self::moneyDecimal($allocation['remaining_after']), $allocation['final_status'], $group['quote_uuid'] ?? null]
            );
            $allocated += normalize_money($allocation['allocated_amount']);
        }
        foreach ($plan['legal_cost_allocations'] ?? [] as $allocation) {
            $amount = normalize_money($allocation['allocated_amount_toman'] ?? 0);
            if ($amount <= 0) continue;
            $paymentId = Payment::record(
                null,
                (int) $group['contract_id'],
                $actorId ?: (int) $group['customer_id'],
                self::moneyDecimal($amount),
                (string) $group['method'],
                'paid',
                null,
                $gatewayRefId,
                'دریافت هزینه حقوقی ' . $group['group_number'],
                $paymentDate,
                'legal_cost',
                $paymentTime,
                null,
                $group['quote_uuid'] ?? null
            );
            Model::execute('UPDATE payments SET payment_group_id = ? WHERE id = ?', [(int) $group['id'], $paymentId]);
            LegalCaseCostService::recordPaymentAllocation(
                (int) $allocation['legal_case_cost_id'],
                (int) $group['id'],
                $paymentId,
                (int) $group['contract_id'],
                $amount,
                (int) $actorId
            );
            $allocated += $amount;
        }
        if ($allocated !== normalize_money($group['requested_amount'])) throw new RuntimeException('تخصیص کامل مبلغ پرداخت انجام نشد.');
        Model::execute("UPDATE payment_groups SET allocated_amount = ?, status = 'completed', completed_at = NOW() WHERE id = ?", [self::moneyDecimal($allocated), (int) $group['id']]);
        return Model::fetch('SELECT * FROM payment_groups WHERE id = ?', [(int) $group['id']]);
    }

    private static function lockedContract($contractId, $userId, $customerOnly)
    {
        $contract = Model::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [(int) $contractId]);
        if (!$contract || in_array((string) ($contract['status'] ?? ''), ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('قرارداد برای پرداخت معتبر نیست.', 409);
        }
        if ($customerOnly && (int) $contract['customer_id'] !== (int) $userId) {
            throw new InvalidArgumentException('دسترسی پرداخت این قرارداد را ندارید.', 403);
        }
        return $contract;
    }

    private static function afterCommit(array $group, $actorId)
    {
        try {
            if (class_exists('AuditLog')) AuditLog::record('payment', 'group_completed', 'payment_group', (int) $group['id'], ['actor_user_id' => $actorId ?: null, 'contract_id' => (int) $group['contract_id'], 'new_values' => ['requested_amount' => $group['requested_amount'], 'allocated_amount' => $group['allocated_amount']]]);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('payment.group.completed', ['payment_group_id' => (int) $group['id'], 'contract_id' => (int) $group['contract_id'], 'actor_user_id' => $actorId ?: null], 'payment_group', (int) $group['id']);
                SystemOutbox::processPending(25);
            }
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('payment.group.post_commit', $e);
        }
    }

    private static function allocationTrackId(array $group, array $allocation)
    {
        if (empty($group['gateway_track_id'])) return null;
        return substr((string) $group['gateway_track_id'], 0, 80) . ':' . (int) ($allocation['installment_id'] ?? 0);
    }

    private static function ids(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        return $ids;
    }

    private static function method($method)
    {
        $method = strtolower(trim((string) $method));
        return preg_match('/^[a-z][a-z0-9_-]{1,29}$/', $method) ? $method : 'manual';
    }

    private static function allocationScope($scope)
    {
        $scope = strtolower(trim((string) $scope));
        return in_array($scope, ['selected', 'contract', 'schedule'], true) ? $scope : 'selected';
    }

    private static function number($prefix)
    {
        return $prefix . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private static function moneyInteger($value)
    {
        return normalize_money($value);
    }

    private static function moneyDecimal($value)
    {
        return number_format(normalize_money($value), 2, '.', '');
    }
}
