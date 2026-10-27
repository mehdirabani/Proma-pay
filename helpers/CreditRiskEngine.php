<?php

/** Explainable, advisory Proma Score built only from recorded application data. */
final class CreditRiskEngine
{
    public const SUMMARY_FRESHNESS_SECONDS = 21600;

    public static function defaultRules(): array
    {
        return [
            'successful_contract_points' => 20, 'successful_contract_cap' => 100,
            'on_time_points_cap' => 150, 'early_installment_points' => 3, 'early_installment_cap' => 60,
            'average_delay_penalty_per_day' => 4, 'average_delay_penalty_cap' => 180,
            'open_legal_case_penalty' => 60, 'open_legal_case_penalty_cap' => 240,
            'broken_promise_penalty' => 25, 'broken_promise_penalty_cap' => 100,
            'debt_penalty_per_million' => 2, 'debt_penalty_cap' => 150,
            'starting_score' => 700,
        ];
    }

    public static function rules(): array
    {
        $stored = json_decode((string) Settings::get('credit_risk_rules_json', ''), true);
        $rules = self::defaultRules();
        if (is_array($stored)) {
            foreach ($rules as $key => $default) {
                if (isset($stored[$key]) && is_numeric($stored[$key])) $rules[$key] = max(0, min(1000, (int) $stored[$key]));
            }
        }
        return $rules;
    }

    public static function profile(int $customerId): array
    {
        $customer = User::find($customerId);
        if (!$customer || ($customer['role'] ?? '') !== 'customer') throw new InvalidArgumentException('مشتری پیدا نشد.', 404);

        $contracts = Model::fetchAll('SELECT id, status, principal_amount, down_payment_amount FROM contracts WHERE customer_id = ? ORDER BY id', [$customerId]);
        $successful = 0; $creditVolume = 0; $contractIds = [];
        foreach ($contracts as $contract) {
            if (($contract['status'] ?? '') !== 'cancelled') {
                $contractIds[] = (int) $contract['id'];
                $creditVolume += normalize_money($contract['principal_amount'] ?? 0);
            }
            if (in_array((string) ($contract['status'] ?? ''), ['closed', 'completed'], true)) $successful++;
        }
        $installments = Model::fetchAll(
            "SELECT i.id, i.due_date, i.status, MAX(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0 THEN COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) END) AS final_payment_date
             FROM installments i JOIN contracts c ON c.id = i.contract_id
             LEFT JOIN payments p ON p.installment_id = i.id
             WHERE c.customer_id = ? AND i.status != 'cancelled'
             GROUP BY i.id, i.due_date, i.status",
            [$customerId]
        );
        $fullyPaid = 0; $onTime = 0; $early = 0; $delayDays = [];
        $today = date('Y-m-d');
        foreach ($installments as $row) {
            if (($row['status'] ?? '') === 'paid' && !empty($row['final_payment_date'])) {
                $fullyPaid++;
                $late = max(0, (int) floor((strtotime((string) $row['final_payment_date']) - strtotime((string) $row['due_date'])) / 86400));
                if ($late === 0) $onTime++;
                if ((string) $row['final_payment_date'] < (string) $row['due_date']) $early++;
                $delayDays[] = $late;
            } elseif ((string) ($row['due_date'] ?? '') < $today) {
                $delayDays[] = max(0, (int) floor((strtotime($today) - strtotime((string) $row['due_date'])) / 86400));
            }
        }
        $averageDelay = $delayDays ? (int) round(array_sum($delayDays) / count($delayDays)) : 0;
        $maxDelay = $delayDays ? max($delayDays) : 0;
        $legal = Model::fetch("SELECT COUNT(*) AS total, SUM(CASE WHEN status != 'closed' THEN 1 ELSE 0 END) AS open_total FROM legal_cases WHERE customer_id = ?", [$customerId]) ?: [];
        $broken = Model::fetch(
            "SELECT COUNT(*) AS total FROM operator_calls oc
             WHERE oc.customer_id = ? AND oc.promise_payment_date < CURDATE()
               AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.contract_id = oc.contract_id AND p.status = 'paid'
                 AND COALESCE(p.is_corrected, 0) = 0 AND COALESCE(p.payment_date, DATE(p.paid_at), DATE(p.created_at)) >= oc.promise_payment_date)",
            [$customerId]
        ) ?: [];
        $currentDebt = 0; $overdueDebt = 0;
        foreach ($contractIds as $contractId) {
            try {
                $summary = FinancialDebtEngine::summarize($contractId);
                $currentDebt += normalize_money($summary['final_collectable_amount'] ?? 0);
                $overdueDebt += normalize_money($summary['due_installments_total'] ?? 0);
                $overdueDebt += normalize_money($summary['legal_costs_total'] ?? 0);
                $overdueDebt += normalize_money($summary['late_penalty_total'] ?? 0);
            } catch (Throwable $e) {
                // Invalid financial state is surfaced below rather than silently scored as a clean account.
                $currentDebt = PHP_INT_MAX;
            }
        }

        $rules = self::rules();
        $score = $rules['starting_score']; $reasons = [];
        $contractPoints = min($rules['successful_contract_cap'], $successful * $rules['successful_contract_points']);
        self::apply($score, $reasons, 'قراردادهای موفق ' . $successful . ' مورد', $contractPoints);
        if ($fullyPaid > 0) {
            $onTimePoints = (int) round($rules['on_time_points_cap'] * ($onTime / $fullyPaid));
            self::apply($score, $reasons, 'پرداخت به‌موقع ' . $onTime . ' از ' . $fullyPaid . ' قسط تکمیل‌شده', $onTimePoints);
        } else {
            $reasons[] = ['label' => 'سابقهٔ قسط تکمیل‌شده کافی نیست', 'points' => 0];
        }
        self::apply($score, $reasons, 'تسویه زودهنگام اقساط ' . $early . ' مورد', min($rules['early_installment_cap'], $early * $rules['early_installment_points']));
        self::apply($score, $reasons, 'میانگین تأخیر ' . $averageDelay . ' روز', -min($rules['average_delay_penalty_cap'], $averageDelay * $rules['average_delay_penalty_per_day']));
        $openLegal = (int) ($legal['open_total'] ?? 0);
        self::apply($score, $reasons, 'پروندهٔ حقوقی باز ' . $openLegal . ' مورد', -min($rules['open_legal_case_penalty_cap'], $openLegal * $rules['open_legal_case_penalty']));
        $brokenCount = (int) ($broken['total'] ?? 0);
        self::apply($score, $reasons, 'وعدهٔ پرداخت گذشته بدون پرداخت ثبت‌شده ' . $brokenCount . ' مورد', -min($rules['broken_promise_penalty_cap'], $brokenCount * $rules['broken_promise_penalty']));
        $debtPenalty = min($rules['debt_penalty_cap'], (int) floor($currentDebt / 1000000) * $rules['debt_penalty_per_million']);
        self::apply($score, $reasons, 'بدهی جاری ' . number_format($currentDebt) . ' تومان', -$debtPenalty);
        $score = max(100, min(1000, $score));
        $risk = $score < 500 ? 'high' : ($score < 700 ? 'medium' : 'low');
        $averagePrincipal = $successful > 0 ? (int) round($creditVolume / max(1, count($contractIds))) : 0;
        $limit = $risk === 'low' ? min(50000000, max(5000000, (int) ($averagePrincipal * 0.35))) : ($risk === 'medium' ? min(25000000, max(3000000, (int) ($averagePrincipal * 0.2))) : min(5000000, (int) ($averagePrincipal * 0.1)));
        $recommendation = $risk === 'low'
            ? ['credit_limit_toman' => $limit, 'down_payment_percent' => 20, 'max_term_months' => 12, 'guarantor_required' => false]
            : ($risk === 'medium'
                ? ['credit_limit_toman' => $limit, 'down_payment_percent' => 30, 'max_term_months' => 6, 'guarantor_required' => true]
                : ['credit_limit_toman' => $limit, 'down_payment_percent' => 50, 'max_term_months' => 3, 'guarantor_required' => true]);

        $profile = [
            'customer' => $customer, 'score' => $score, 'risk_level' => $risk,
            'metrics' => ['contracts' => count($contractIds), 'successful_contracts' => $successful, 'credit_volume_toman' => $creditVolume, 'paid_installments' => $fullyPaid, 'on_time_installments' => $onTime, 'early_installments' => $early, 'on_time_rate' => $fullyPaid ? round($onTime * 100 / $fullyPaid, 1) : null, 'average_delay_days' => $averageDelay, 'maximum_delay_days' => $maxDelay, 'legal_cases' => (int) ($legal['total'] ?? 0), 'open_legal_cases' => $openLegal, 'broken_promises_estimated' => $brokenCount, 'current_debt_toman' => $currentDebt === PHP_INT_MAX ? null : $currentDebt, 'overdue_debt_toman' => $overdueDebt],
            'reasons' => $reasons, 'recommendation' => $recommendation, 'returned_checks' => null,
            'returned_checks_note' => 'در این نصب، منبع دادهٔ قابل اتکایی برای چک برگشتی یافت نشد؛ در امتیاز لحاظ نشده است.',
            'rules' => $rules, 'advisory_only' => true,
        ];
        self::storeSnapshot($profile);
        return $profile;
    }

    /** Return a recent saved score, recalculating only after its freshness window expires. */
    public static function summaryForCustomer(int $customerId, int $maxAgeSeconds = 1800): array
    {
        $customer = User::find($customerId);
        if (!$customer || ($customer['role'] ?? '') !== 'customer') throw new InvalidArgumentException('مشتری پیدا نشد.', 404);
        $rulesHash = self::rulesHash();
        $snapshot = Model::fetch('SELECT score, risk_level, metrics_json, recommendation_json, reasons_json, rules_hash, calculated_at FROM customer_credit_profiles WHERE customer_id = ? LIMIT 1', [$customerId]);
        $fresh = $snapshot
            && hash_equals($rulesHash, (string) ($snapshot['rules_hash'] ?? ''))
            && strtotime((string) ($snapshot['calculated_at'] ?? '')) >= time() - max(0, $maxAgeSeconds);
        if (!$fresh) return self::profile($customerId);

        $metrics = json_decode((string) ($snapshot['metrics_json'] ?? ''), true);
        $recommendation = json_decode((string) ($snapshot['recommendation_json'] ?? ''), true);
        $reasons = json_decode((string) ($snapshot['reasons_json'] ?? ''), true);
        if (!is_array($metrics) || !is_array($recommendation) || !is_array($reasons)) return self::profile($customerId);
        return [
            'customer' => $customer,
            'score' => (int) ($snapshot['score'] ?? 0),
            'risk_level' => (string) ($snapshot['risk_level'] ?? 'high'),
            'metrics' => $metrics,
            'recommendation' => $recommendation,
            'reasons' => $reasons,
            'rules' => self::rules(),
            'advisory_only' => true,
            'cached' => true,
            'calculated_at' => (string) ($snapshot['calculated_at'] ?? ''),
        ];
    }

    /** Load cached customer badges in one query; refresh only when requested by the caller. */
    public static function summariesForCustomers(array $customerIds, bool $refreshStale = false, int $maxAgeSeconds = self::SUMMARY_FRESHNESS_SECONDS): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Model::fetchAll('SELECT customer_id, score, risk_level, rules_hash, calculated_at FROM customer_credit_profiles WHERE customer_id IN (' . $placeholders . ')', $ids);
        $byCustomer = [];
        foreach ($rows as $row) $byCustomer[(int) $row['customer_id']] = $row;
        $rulesHash = self::rulesHash();
        $summaries = [];
        foreach ($ids as $id) {
            $row = $byCustomer[$id] ?? null;
            $fresh = $row
                && hash_equals($rulesHash, (string) ($row['rules_hash'] ?? ''))
                && strtotime((string) ($row['calculated_at'] ?? '')) >= time() - max(0, $maxAgeSeconds);
            if (!$fresh && $refreshStale) {
                try {
                    $row = self::summaryForCustomer($id, $maxAgeSeconds);
                    $summaries[$id] = [
                        'score' => (int) ($row['score'] ?? 0),
                        'risk_level' => (string) ($row['risk_level'] ?? 'high'),
                        'calculated_at' => (string) ($row['calculated_at'] ?? date('Y-m-d H:i:s')),
                    ];
                } catch (Throwable $e) {
                    if (class_exists('ErrorHandler')) ErrorHandler::log('credit_profile_summary', $e, 500);
                }
                continue;
            }
            if ($fresh) {
                $summaries[$id] = [
                    'score' => (int) $row['score'],
                    'risk_level' => (string) $row['risk_level'],
                    'calculated_at' => (string) $row['calculated_at'],
                ];
            }
        }
        return $summaries;
    }

    public static function invalidate(int $customerId): void
    {
        if ($customerId <= 0) return;
        try {
            Model::execute('DELETE FROM customer_credit_profiles WHERE customer_id = ?', [$customerId]);
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) ErrorHandler::log('credit_profile_cache_invalidate', $e, 500);
        }
    }

    private static function storeSnapshot(array $profile): void
    {
        $customerId = (int) ($profile['customer']['id'] ?? 0);
        if ($customerId <= 0) return;
        try {
            Model::execute(
                'INSERT INTO customer_credit_profiles (customer_id, score, risk_level, metrics_json, recommendation_json, reasons_json, rules_hash, calculated_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE score=VALUES(score), risk_level=VALUES(risk_level), metrics_json=VALUES(metrics_json), recommendation_json=VALUES(recommendation_json), reasons_json=VALUES(reasons_json), rules_hash=VALUES(rules_hash), calculated_at=NOW(), updated_at=NOW()',
                [
                    $customerId,
                    (int) ($profile['score'] ?? 0),
                    (string) ($profile['risk_level'] ?? 'high'),
                    json_encode($profile['metrics'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    json_encode($profile['recommendation'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    json_encode($profile['reasons'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    self::rulesHash(),
                ]
            );
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) ErrorHandler::log('credit_profile_cache_write', $e, 500);
        }
    }

    private static function rulesHash(): string
    {
        return hash('sha256', json_encode(self::rules(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Stable public fingerprint for read-only reporting filters. */
    public static function rulesFingerprint(): string
    {
        return self::rulesHash();
    }

    private static function apply(int &$score, array &$reasons, string $label, int $points): void
    {
        $score += $points;
        $reasons[] = ['label' => $label, 'points' => $points];
    }
}
