<?php

/**
 * Safe, auditable change boundary for a single installment.
 *
 * Financial history is never overwritten: rows with any payment, legal or
 * accounting dependency are routed to an auditable change request instead of
 * being edited/voided in place.
 */
final class InstallmentChangeService
{
    public static function change($installmentId, array $input, $actorId): array
    {
        $installmentId = (int) $installmentId;
        $actorId = (int) $actorId;
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($installmentId <= 0 || $actorId <= 0 || mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت تغییر قسط را کامل وارد کنید.');
        }
        $requested = self::normaliseChange($input);
        Model::begin();
        try {
            $before = self::lockedInstallment($installmentId);
            self::assertVersion($before, (string) ($input['version_token'] ?? ''));
            self::assertActiveContract($before);
            $dependencies = self::dependencies($before);
            $hasFinancialChange = $requested['base_amount'] !== normalize_money($before['base_amount'] ?? 0)
                || $requested['due_date'] !== (string) ($before['due_date'] ?? '');
            if ($dependencies['has_history'] && $hasFinancialChange) {
                $requestId = self::recordRequest($before, $requested, $dependencies, $reason, $actorId, 'pending');
                Model::commit();
                self::audit('change_requested', $before, $requested, $reason, $actorId, $requestId, $dependencies);
                return ['mode' => 'approval_required', 'request_id' => $requestId];
            }

            $affected = [(int) $before['id']];
            if ($requested['base_amount'] !== normalize_money($before['base_amount'] ?? 0)) {
                if (($requested['difference_mode'] ?? '') !== 'transfer_to_last_unpaid') {
                    $requestId = self::recordRequest($before, $requested, $dependencies, $reason, $actorId, 'pending');
                    Model::commit();
                    self::audit('amount_change_requested', $before, $requested, $reason, $actorId, $requestId, $dependencies);
                    return ['mode' => 'approval_required', 'request_id' => $requestId];
                }
                $difference = $requested['base_amount'] - normalize_money($before['base_amount'] ?? 0);
                $target = self::lastUnpaidPeer($before);
                if (!$target) {
                    throw new InvalidArgumentException('برای انتقال اختلاف، یک قسط پرداخت‌نشده دیگر لازم است.');
                }
                $targetAmount = normalize_money($target['base_amount'] ?? 0) - $difference;
                if ($targetAmount <= 0) {
                    throw new InvalidArgumentException('انتقال اختلاف باعث نامعتبر شدن مبلغ آخرین قسط می‌شود.');
                }
                Model::execute('UPDATE installments SET base_amount = ?, remaining_amount = ?, updated_at = NOW() WHERE id = ?', [$targetAmount, $targetAmount, (int) $target['id']]);
                $affected[] = (int) $target['id'];
            }

            Model::execute(
                'UPDATE installments SET due_date = ?, base_amount = ?, remaining_amount = ?, custom_title = ?, custom_description = ?, notes = ?, internal_note = ?, updated_at = NOW() WHERE id = ?',
                [
                    $requested['due_date'], $requested['base_amount'], $requested['base_amount'],
                    $requested['custom_title'] ?: null, $requested['custom_description'] ?: null,
                    $requested['custom_description'] ?: null, $requested['internal_note'] ?: null, $installmentId,
                ]
            );
            foreach ($affected as $affectedId) {
                Installment::refreshStatus($affectedId);
            }
            $requestId = self::recordRequest($before, $requested, $dependencies, $reason, $actorId, 'applied', $affected);
            Contract::syncCompletionStatuses((int) $before['contract_id']);
            Model::commit();
            self::audit('changed', $before, $requested, $reason, $actorId, $requestId, $dependencies);
            self::enqueue((int) $before['contract_id'], $affected, $actorId, 'changed');
            return ['mode' => 'applied', 'request_id' => $requestId, 'affected_installment_ids' => $affected];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function void($installmentId, array $input, $actorId): array
    {
        $installmentId = (int) $installmentId;
        $actorId = (int) $actorId;
        $reason = trim((string) ($input['reason'] ?? ''));
        if (empty($input['confirm_void']) || mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت ابطال و تأیید پیامدهای آن الزامی است.');
        }
        Model::begin();
        try {
            $before = self::lockedInstallment($installmentId);
            self::assertVersion($before, (string) ($input['version_token'] ?? ''));
            self::assertActiveContract($before);
            $expected = 'ابطال قسط شماره ' . to_persian_digits($before['installment_number']);
            if (trim((string) ($input['typed_confirmation'] ?? '')) !== $expected) {
                throw new InvalidArgumentException('عبارت تأیید ابطال دقیق وارد نشده است.');
            }
            $dependencies = self::dependencies($before);
            if ($dependencies['has_history']) {
                $requestId = self::recordRequest($before, ['action' => 'void'], $dependencies, $reason, $actorId, 'pending');
                Model::commit();
                self::audit('void_requested', $before, ['action' => 'void'], $reason, $actorId, $requestId, $dependencies);
                return ['mode' => 'approval_required', 'request_id' => $requestId];
            }
            Model::execute(
                "UPDATE installments SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?, updated_at = NOW() WHERE id = ?",
                [$actorId, $reason, $installmentId]
            );
            $voidRequestNumber = self::requestId();
            Model::execute(
                'INSERT INTO installment_voids (installment_id, contract_id, previous_amount, previous_due_date, void_reason, voided_by, request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$installmentId, (int) $before['contract_id'], normalize_money($before['base_amount'] ?? 0), $before['due_date'], $reason, $actorId, $voidRequestNumber]
            );
            $requestId = self::recordRequest($before, ['action' => 'void'], $dependencies, $reason, $actorId, 'applied', [$installmentId], $voidRequestNumber);
            Contract::syncCompletionStatuses((int) $before['contract_id']);
            Model::commit();
            self::audit('voided', $before, ['status' => 'cancelled'], $reason, $actorId, $requestId, $dependencies);
            self::enqueue((int) $before['contract_id'], [$installmentId], $actorId, 'voided');
            return ['mode' => 'applied', 'request_id' => $requestId];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function versionToken(array $installment): string
    {
        return hash('sha256', implode('|', [
            (int) ($installment['id'] ?? 0), (string) ($installment['updated_at'] ?? ''),
            (string) ($installment['due_date'] ?? ''), normalize_money($installment['base_amount'] ?? 0),
            normalize_money($installment['paid_amount'] ?? 0), (string) ($installment['status'] ?? ''),
        ]));
    }

    private static function normaliseChange(array $input): array
    {
        $dueDate = parse_jalali_date($input['due_date'] ?? '') ?: trim((string) ($input['due_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new InvalidArgumentException('تاریخ سررسید معتبر نیست.');
        }
        $amount = normalize_money($input['base_amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ قسط باید بزرگ‌تر از صفر باشد.');
        }
        return [
            'due_date' => $dueDate,
            'base_amount' => $amount,
            'custom_title' => mb_substr(trim((string) ($input['custom_title'] ?? '')), 0, 100),
            'custom_description' => mb_substr(trim((string) ($input['custom_description'] ?? '')), 0, 2000),
            'internal_note' => mb_substr(trim((string) ($input['internal_note'] ?? '')), 0, 2000),
            'difference_mode' => in_array(($input['difference_mode'] ?? ''), ['transfer_to_last_unpaid', 'selected_distribution', 'contract_adjustment', 'manual_schedule'], true)
                ? $input['difference_mode'] : 'manual_schedule',
        ];
    }

    private static function lockedInstallment($installmentId): array
    {
        $row = Model::fetch('SELECT i.*, c.status AS contract_status FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE i.id = ? FOR UPDATE', [(int) $installmentId]);
        if (!$row) throw new InvalidArgumentException('قسط پیدا نشد.');
        if (($row['status'] ?? '') === 'cancelled') throw new InvalidArgumentException('قسط ابطال‌شده قابل تغییر نیست.');
        return $row;
    }

    private static function assertVersion(array $before, string $submitted): void
    {
        if ($submitted === '' || !hash_equals(self::versionToken($before), $submitted)) {
            throw new InvalidArgumentException('قسط پس از باز شدن فرم تغییر کرده است. صفحه را تازه‌سازی و دوباره بررسی کنید.');
        }
    }

    private static function assertActiveContract(array $row): void
    {
        if (in_array((string) ($row['contract_status'] ?? ''), ['cancelled', 'completed', 'closed'], true)) {
            throw new InvalidArgumentException('برای قرارداد لغو یا بسته‌شده تغییر قسط مجاز نیست.');
        }
    }

    private static function dependencies(array $row): array
    {
        $paymentCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE installment_id = ?', [(int) $row['id']])['total'] ?? 0);
        $legalCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM legal_cases WHERE contract_id = ?', [(int) $row['contract_id']])['total'] ?? 0);
        $accountingCount = 0;
        try {
            $exists = Model::fetch("SHOW TABLES LIKE 'accounting_entries'");
            if ($exists) {
                $accountingCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM accounting_entries WHERE installment_id = ?', [(int) $row['id']])['total'] ?? 0);
            }
        } catch (Throwable $ignored) {
            // Optional accounting plugin may not be installed on shared hosts.
        }
        return [
            'payment_count' => $paymentCount,
            'legal_case_count' => $legalCount,
            'accounting_entry_count' => $accountingCount,
            'has_history' => $paymentCount > 0 || $legalCount > 0 || $accountingCount > 0 || normalize_money($row['paid_amount'] ?? 0) > 0,
        ];
    }

    private static function lastUnpaidPeer(array $row): ?array
    {
        return Model::fetch(
            "SELECT i.* FROM installments i
             WHERE i.contract_id = ? AND i.id != ? AND i.status NOT IN ('paid', 'cancelled')
               AND COALESCE(i.paid_amount, 0) = 0
               AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.installment_id = i.id)
             ORDER BY i.installment_number DESC, i.id DESC LIMIT 1 FOR UPDATE",
            [(int) $row['contract_id'], (int) $row['id']]
        );
    }

    private static function recordRequest(array $before, array $requested, array $dependencies, string $reason, int $actorId, string $status, array $affected = [], ?string $requestNumber = null): int
    {
        $requestId = $requestNumber ?: self::requestId();
        Model::execute(
            'INSERT INTO installment_change_requests (request_id, installment_id, contract_id, request_type, status, before_snapshot_json, requested_snapshot_json, dependency_snapshot_json, reason, requested_by, approved_by, affected_installment_ids_json, calculation_version, created_at, applied_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $requestId, (int) $before['id'], (int) $before['contract_id'], isset($requested['action']) ? 'void' : 'edit', $status,
                json_encode($before, JSON_UNESCAPED_UNICODE), json_encode($requested, JSON_UNESCAPED_UNICODE), json_encode($dependencies, JSON_UNESCAPED_UNICODE),
                $reason, $actorId, $status === 'applied' ? $actorId : null, json_encode($affected, JSON_UNESCAPED_UNICODE),
                InstallmentFinancialStateService::CALCULATION_VERSION, $status === 'applied' ? date('Y-m-d H:i:s') : null,
            ]
        );
        return (int) Model::lastInsertId();
    }

    private static function audit(string $action, array $before, array $after, string $reason, int $actorId, int $requestId, array $dependencies): void
    {
        if (!class_exists('AuditLog')) return;
        AuditLog::record('installment', $action, 'installment_change_request', $requestId, [
            'actor_user_id' => $actorId, 'contract_id' => (int) $before['contract_id'], 'installment_id' => (int) $before['id'],
            'old_values' => $before, 'new_values' => $after + ['dependencies' => $dependencies, 'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION],
            'description' => $reason,
        ]);
    }

    private static function enqueue(int $contractId, array $installmentIds, int $actorId, string $action): void
    {
        if (!class_exists('SystemOutbox')) return;
        SystemOutbox::safeEnqueuePluginHook('installment.financial_state_changed', [
            'contract_id' => $contractId, 'installment_ids' => $installmentIds, 'actor_user_id' => $actorId,
            'action' => $action, 'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
        ], 'contract', $contractId);
    }

    private static function requestId(): string
    {
        return 'ICR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
