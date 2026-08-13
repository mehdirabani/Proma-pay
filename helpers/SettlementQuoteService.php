<?php

/** Persisted, short-lived server quote used by both selected and full settlement. */
final class SettlementQuoteService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_USED = 'used';

    public static function ttlSeconds(?array $settings = null)
    {
        $settings = $settings ?? Settings::allKeyed();
        return max(60, min(1800, (int) to_english_digits($settings['settlement_quote_ttl_seconds'] ?? 300)));
    }

    public static function create($contractId, array $installmentIds, $actorId = null, $scope = 'selected', $asOf = null)
    {
        $contract = Model::fetch('SELECT * FROM contracts WHERE id = ? LIMIT 1', [(int) $contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
        $rows = self::loadInstallments((int) $contractId, $installmentIds);
        return self::persistQuote($contract, $rows, $actorId, $scope, $asOf);
    }

    public static function persistQuote(array $contract, array $rows, $actorId = null, $scope = 'selected', $asOf = null)
    {
        $plan = self::planForScope($contract, $rows, null, $scope, $asOf, false);
        $ids = array_map(static function ($row) { return (int) $row['id']; }, $plan['rows']);
        if (!$ids) throw new InvalidArgumentException('قسط قابل پرداختی برای محاسبه وجود ندارد.', 409);
        $quoteUuid = bin2hex(random_bytes(20));
        $calculatedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::ttlSeconds());
        $snapshot = self::snapshotHash($plan, $ids);
        Model::execute(
            'INSERT INTO settlement_quotes (quote_uuid, actor_id, customer_id, contract_id, scope, selected_installment_ids_json, calculation_date, calculated_at, principal_total, normal_penalty_total, legal_penalty_total, reward_total, final_payable, snapshot_hash, calculation_version, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $quoteUuid, $actorId ? (int) $actorId : null, (int) $contract['customer_id'], (int) $contract['id'],
                $scope === 'contract' ? 'contract' : 'selected', json_encode($ids), date('Y-m-d'), $calculatedAt,
                self::decimal($plan['principal_total']), self::decimal($plan['normal_penalty_total']), self::decimal($plan['legal_penalty_total']),
                self::decimal($plan['reward_total']), self::decimal($plan['full_settlement_total']), $snapshot,
                InstallmentFinancialStateService::CALCULATION_VERSION . '+' . PaymentAllocationService::ALLOCATION_VERSION . '+legal-cost-v1',
                self::STATUS_OPEN, $expiresAt,
            ]
        );
        return self::formatQuote(Model::fetch('SELECT * FROM settlement_quotes WHERE quote_uuid = ? LIMIT 1', [$quoteUuid]), $plan);
    }

    public static function verifyLocked($quoteUuid, array $contract, array $rows, array $requestedIds, $amount, $actorId = null, $scope = 'selected', $asOf = null)
    {
        $quoteUuid = trim((string) $quoteUuid);
        $plan = self::planForScope($contract, $rows, $amount, $scope, $asOf, true);
        if ($quoteUuid === '') return ['quote' => null, 'plan' => $plan];
        $quote = Model::fetch('SELECT * FROM settlement_quotes WHERE quote_uuid = ? FOR UPDATE', [$quoteUuid]);
        if (!$quote || (int) $quote['contract_id'] !== (int) $contract['id'] || ($actorId && (int) ($quote['actor_id'] ?? 0) !== (int) $actorId)) {
            throw new InvalidArgumentException('پیش‌فاکتور تسویه معتبر نیست. محاسبه را تازه کنید.', 409);
        }
        if (($quote['status'] ?? '') !== self::STATUS_OPEN || strtotime((string) ($quote['expires_at'] ?? '')) < time()) {
            throw new InvalidArgumentException('پیش‌فاکتور تسویه منقضی یا مصرف شده است. مبلغ جدید را تأیید کنید.', 409);
        }
        $expectedIds = array_values(array_unique(array_map('intval', json_decode((string) ($quote['selected_installment_ids_json'] ?? '[]'), true) ?: [])));
        sort($expectedIds);
        $actualIds = array_values(array_unique(array_map('intval', $requestedIds)));
        sort($actualIds);
        if ($expectedIds !== $actualIds || ($quote['scope'] ?? 'selected') !== ($scope === 'contract' ? 'contract' : 'selected')) {
            throw new InvalidArgumentException('دامنهٔ اقساط با پیش‌فاکتور تسویه یکسان نیست. محاسبه را تازه کنید.', 409);
        }
        $snapshot = self::snapshotHash($plan, $actualIds);
        if (!hash_equals((string) $quote['snapshot_hash'], $snapshot)) {
            throw new InvalidArgumentException('مبالغ مالی تغییر کرده است. پیش‌فاکتور جدید را بررسی و دوباره تأیید کنید.', 409);
        }
        return ['quote' => $quote, 'plan' => $plan];
    }

    public static function markUsed($quoteUuid, $paymentGroupId = null)
    {
        if (trim((string) $quoteUuid) === '') return;
        $paymentGroupId = $paymentGroupId ? (int) $paymentGroupId : null;
        Model::execute("UPDATE settlement_quotes SET status = ?, used_at = NOW(), payment_group_id = ? WHERE quote_uuid = ? AND status = ?", [self::STATUS_USED, $paymentGroupId, $quoteUuid, self::STATUS_OPEN]);
    }

    public static function loadInstallments($contractId, array $ids = [], $forUpdate = false)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $params = [(int) $contractId];
        $where = 'i.contract_id = ?';
        if ($ids) {
            $where .= ' AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $rows = Model::fetchAll(
            "SELECT i.*, c.contract_number, c.customer_id, c.status AS contract_status, c.legal_status AS contract_legal_status,
                    (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
                    (SELECT MIN(lc.legal_referred_at) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.legal_referred_at IS NOT NULL) AS legal_started_at
             FROM installments i JOIN contracts c ON c.id = i.contract_id
             WHERE {$where} ORDER BY i.id ASC" . ($forUpdate ? ' FOR UPDATE' : ''),
            $params
        );
        if ($ids && count($rows) !== count($ids)) throw new InvalidArgumentException('یکی از اقساط انتخاب‌شده به این قرارداد تعلق ندارد.', 409);
        return $rows;
    }

    public static function formatQuote(array $quote, array $plan)
    {
        return $quote + [
            'selected_count' => $plan['selected_count'],
            'allocations' => $plan['allocations'],
            'full_settlement_total' => $plan['full_settlement_total'],
            'legal_cost_total' => $plan['legal_cost_total'] ?? 0,
            'formatted' => [
                'principal_total' => money_toman($plan['principal_total']),
                'normal_penalty_total' => money_toman($plan['normal_penalty_total']),
                'legal_penalty_total' => money_toman($plan['legal_penalty_total']),
                'legal_cost_total' => money_toman($plan['legal_cost_total'] ?? 0),
                'reward_total' => money_toman($plan['reward_total']),
                'final_payable' => money_toman($plan['full_settlement_total']),
            ],
        ];
    }

    private static function snapshotHash(array $plan, array $ids)
    {
        sort($ids);
        return hash('sha256', json_encode([
            'ids' => $ids,
            'principal' => normalize_money($plan['principal_total'] ?? 0),
            'normal' => normalize_money($plan['normal_penalty_total'] ?? 0),
            'legal' => normalize_money($plan['legal_penalty_total'] ?? 0),
            'reward' => normalize_money($plan['reward_total'] ?? 0),
            'legal_cost' => normalize_money($plan['legal_cost_total'] ?? 0),
            'legal_cost_allocations' => array_map(static function ($row) {
                return [(int) ($row['legal_case_cost_id'] ?? 0), normalize_money($row['allocated_amount_toman'] ?? 0)];
            }, $plan['legal_cost_allocations'] ?? []),
            'total' => normalize_money($plan['full_settlement_total'] ?? 0),
            'version' => $plan['calculation_version'] ?? PaymentAllocationService::ALLOCATION_VERSION,
        ], JSON_UNESCAPED_UNICODE));
    }

    private static function decimal($amount)
    {
        return number_format(normalize_money($amount), 2, '.', '');
    }

    /**
     * A contract-wide quote may include approved legal costs. Selected-payment
     * quotes deliberately exclude them so a user never accidentally charges a
     * contract-level legal expense against a single installment.
     */
    private static function planForScope(array $contract, array $rows, $amount, $scope, $asOf, bool $forUpdate): array
    {
        $scope = $scope === 'contract' ? 'contract' : 'selected';
        $baseQuote = PaymentAllocationService::quote($rows, $asOf);
        $baseTotal = normalize_money($baseQuote['full_settlement_total'] ?? 0);
        if ($baseTotal <= 0) throw new InvalidArgumentException('قسط قابل پرداختی برای محاسبه وجود ندارد.', 409);
        $costRows = $scope === 'contract'
            ? LegalCaseCostService::outstandingChargeableForContract((int) $contract['id'], $forUpdate)
            : [];
        $legalCostTotal = 0;
        foreach ($costRows as $cost) {
            $legalCostTotal += max(0, normalize_money($cost['amount_toman'] ?? 0) - normalize_money($cost['paid_amount_toman'] ?? 0));
        }
        $total = $baseTotal + $legalCostTotal;
        $requested = $amount === null ? $total : normalize_money($amount);
        if ($requested <= 0) throw new InvalidArgumentException('مبلغ پرداخت باید بیشتر از صفر باشد.');
        if ($requested > $total) throw new InvalidArgumentException('مبلغ پرداخت از مبلغ قابل تسویه بیشتر است.', 422);

        $installmentAmount = min($requested, $baseTotal);
        $plan = PaymentAllocationService::plan($rows, $installmentAmount, $asOf);
        $remainder = $requested - $installmentAmount;
        $costAllocations = [];
        foreach ($costRows as $cost) {
            if ($remainder <= 0) break;
            $available = max(0, normalize_money($cost['amount_toman'] ?? 0) - normalize_money($cost['paid_amount_toman'] ?? 0));
            $applied = min($available, $remainder);
            if ($applied <= 0) continue;
            $costAllocations[] = [
                'legal_case_cost_id' => (int) $cost['id'],
                'allocated_amount_toman' => $applied,
                'amount_before_toman' => $available,
                'amount_after_toman' => $available - $applied,
            ];
            $remainder -= $applied;
        }
        if ($remainder !== 0) throw new RuntimeException('تخصیص هزینه حقوقی کامل نشد.');
        $plan['legal_cost_total'] = $legalCostTotal;
        $plan['legal_cost_allocations'] = $costAllocations;
        $plan['installment_settlement_total'] = $baseTotal;
        $plan['full_settlement_total'] = $total;
        $plan['received_amount'] = $requested;
        $plan['allocated_amount'] = $requested;
        $plan['unused_amount'] = 0;
        $plan['calculation_version'] = (string) ($plan['calculation_version'] ?? PaymentAllocationService::ALLOCATION_VERSION) . '+legal-cost-v1';
        return $plan;
    }
}
