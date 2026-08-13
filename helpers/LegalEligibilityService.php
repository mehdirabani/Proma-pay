<?php

/**
 * Resolves a contract's versioned legal policy and evaluates actual unpaid
 * obligations.  This deliberately consumes the central installment state;
 * it never trusts the materialised installment/contract "paid" flags.
 */
final class LegalEligibilityService
{
    public const POLICY_CODE = 'default';

    public static function defaults(array $settings = [])
    {
        return [
            'delay_value' => max(1, min(3650, (int) to_english_digits($settings['legal_delay_value'] ?? 30))),
            'delay_unit' => ($settings['legal_delay_unit'] ?? 'day') === 'month' ? 'month' : 'day',
            'overdue_count_threshold' => max(1, min(1000, (int) to_english_digits($settings['legal_overdue_count_threshold'] ?? 1))),
            'overdue_amount_enabled' => !empty($settings['legal_overdue_amount_enabled']) ? 1 : 0,
            'overdue_amount_threshold' => max(0, normalize_money($settings['legal_overdue_amount_threshold'] ?? 0)),
            'eligibility_operator' => in_array($settings['legal_eligibility_operator'] ?? '', ['and', 'or'], true)
                ? $settings['legal_eligibility_operator'] : 'delay_only',
            'warning_before_days' => max(0, min(365, (int) to_english_digits($settings['legal_warning_before_days'] ?? 0))),
            'allow_self_initiation' => !empty($settings['legal_allow_self_initiation']) ? 1 : 0,
            'legal_referred_at_mode' => 'persisted_referral_confirmation',
            'clause_text' => (string) ($settings['contract_legal_penalty_clause'] ?? ''),
        ];
    }

    public static function activePolicy(array $settings = null)
    {
        $settings = $settings ?? Settings::allKeyed();
        $fallback = self::defaults($settings);
        try {
            $row = Model::fetch(
                "SELECT * FROM legal_policy_versions WHERE policy_code = ? AND is_active = 1 ORDER BY version_number DESC, id DESC LIMIT 1",
                [self::POLICY_CODE]
            );
            if ($row && !empty($row['policy_json'])) {
                $decoded = json_decode((string) $row['policy_json'], true);
                if (is_array($decoded)) {
                    return ['id' => (int) $row['id'], 'version' => (int) $row['version_number'], 'policy' => array_merge($fallback, $decoded), 'source' => 'active_version'];
                }
            }
        } catch (Throwable $ignored) {
            // A pre-update database can still display its old legal pages.
        }
        return ['id' => null, 'version' => 0, 'policy' => $fallback, 'source' => 'settings_fallback'];
    }

    public static function policyForContract($contractId, array $settings = null)
    {
        $settings = $settings ?? Settings::allKeyed();
        try {
            $snapshot = Model::fetch('SELECT * FROM contract_legal_policy_snapshots WHERE contract_id = ? LIMIT 1', [(int) $contractId]);
            if ($snapshot && !empty($snapshot['policy_json'])) {
                $decoded = json_decode((string) $snapshot['policy_json'], true);
                if (is_array($decoded)) {
                    return [
                        'id' => !empty($snapshot['policy_version_id']) ? (int) $snapshot['policy_version_id'] : null,
                        'version' => (int) ($snapshot['policy_version_number'] ?? 0),
                        'policy' => array_merge(self::defaults($settings), $decoded),
                        'source' => 'contract_snapshot',
                    ];
                }
            }
        } catch (Throwable $ignored) {
        }
        return self::activePolicy($settings);
    }

    /** Store the policy applied at contract creation; existing contracts stay explicit legacy/current fallbacks until reviewed. */
    public static function snapshotForContract($contractId, $actorId = null)
    {
        $resolved = self::activePolicy();
        try {
            Model::execute(
                'INSERT INTO contract_legal_policy_snapshots (contract_id, policy_version_id, policy_version_number, policy_json, source, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE contract_id = contract_id',
                [
                    (int) $contractId,
                    $resolved['id'],
                    (int) $resolved['version'],
                    json_encode($resolved['policy'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'contract_creation',
                    $actorId ? (int) $actorId : null,
                ]
            );
        } catch (Throwable $e) {
            throw new RuntimeException('نسخه سیاست حقوقی قرارداد ذخیره نشد. ابتدا بروزرسانی پایگاه‌داده را کامل کنید.', 0, $e);
        }
        return $resolved;
    }

    /** Publishing policy is an explicit settings action, never an implicit page-view write. */
    public static function publishFromSettings($actorId = null)
    {
        $policy = self::defaults(Settings::allKeyed());
        $current = self::activePolicy();
        if (($current['policy'] ?? []) === $policy) {
            return $current;
        }
        try {
            Model::begin();
            $row = Model::fetch('SELECT COALESCE(MAX(version_number), 0) AS max_version FROM legal_policy_versions WHERE policy_code = ? FOR UPDATE', [self::POLICY_CODE]);
            Model::execute('UPDATE legal_policy_versions SET is_active = 0 WHERE policy_code = ? AND is_active = 1', [self::POLICY_CODE]);
            Model::execute(
                'INSERT INTO legal_policy_versions (policy_code, version_number, policy_json, approved_by, approved_at, is_active, created_at) VALUES (?, ?, ?, ?, NOW(), 1, NOW())',
                [self::POLICY_CODE, (int) ($row['max_version'] ?? 0) + 1, json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $actorId ? (int) $actorId : null]
            );
            $id = (int) Model::lastInsertId();
            Model::commit();
            return ['id' => $id, 'version' => (int) ($row['max_version'] ?? 0) + 1, 'policy' => $policy, 'source' => 'published_settings'];
        } catch (Throwable $e) {
            Model::rollBack();
            throw new RuntimeException('نسخه جدید سیاست حقوقی ذخیره نشد.', 0, $e);
        }
    }

    public static function evaluate(array $contract, array $installments, array $states, array $policy, $asOf = null)
    {
        $asOf = self::date($asOf ?: date('Y-m-d'));
        $overdue = [];
        foreach ($installments as $installment) {
            $state = $states[(int) ($installment['id'] ?? 0)] ?? null;
            if (!$state || empty($state['payment_allowed']) || (string) ($state['due_date'] ?? '') >= $asOf) {
                continue;
            }
            $overdue[] = $state;
        }
        usort($overdue, static function ($a, $b) {
            $dateCompare = strcmp((string) $a['due_date'], (string) $b['due_date']);
            return $dateCompare !== 0 ? $dateCompare : ((int) $a['installment_id'] <=> (int) $b['installment_id']);
        });
        $oldestDue = $overdue ? (string) $overdue[0]['due_date'] : null;
        $delayDays = $oldestDue ? max(0, (int) floor((strtotime($asOf) - strtotime($oldestDue)) / 86400)) : 0;
        $overdueAmount = array_sum(array_map(static function ($item) { return normalize_money($item['final_payable'] ?? 0); }, $overdue));
        $delayDate = $oldestDue ? self::thresholdDate($oldestDue, $policy) : null;
        $delayMet = $delayDate !== null && $asOf >= $delayDate;
        $countMet = count($overdue) >= max(1, (int) ($policy['overdue_count_threshold'] ?? 1));
        $amountEnabled = !empty($policy['overdue_amount_enabled']);
        $amountMet = !$amountEnabled || $overdueAmount >= max(0, normalize_money($policy['overdue_amount_threshold'] ?? 0));
        $operator = $policy['eligibility_operator'] ?? 'delay_only';
        if ($operator === 'and') {
            $eligible = $delayMet && $countMet && $amountMet;
        } elseif ($operator === 'or') {
            $eligible = $amountEnabled ? (($delayMet && $countMet) || $amountMet) : ($delayMet || $countMet);
        } else {
            $eligible = $delayMet && $amountMet;
        }
        $reasons = [];
        if ($delayMet) $reasons[] = 'تاخیر از حد سیاست حقوقی عبور کرده است.';
        if ($countMet) $reasons[] = 'تعداد اقساط معوق به حد تعیین‌شده رسیده است.';
        if ($amountEnabled && $amountMet) $reasons[] = 'مبلغ معوق به حد تعیین‌شده رسیده است.';
        return [
            'contract_id' => (int) ($contract['id'] ?? 0),
            'customer_id' => (int) ($contract['customer_id'] ?? 0),
            'eligible' => (bool) $eligible,
            'as_of' => $asOf,
            'oldest_due_date' => $oldestDue,
            'delay_days' => $delayDays,
            'eligible_on' => $delayDate,
            'overdue_count' => count($overdue),
            'overdue_amount' => $overdueAmount,
            'policy' => $policy,
            'reasons' => $reasons,
            'overdue_states' => $overdue,
        ];
    }

    public static function forContract($contractId, $asOf = null)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract || in_array((string) ($contract['status'] ?? ''), ['cancelled', 'closed'], true)) {
            return ['contract_id' => (int) $contractId, 'eligible' => false, 'reasons' => ['قرارداد فعال نیست.'], 'overdue_states' => []];
        }
        $rows = Model::fetchAll(
            'SELECT i.*, c.status AS contract_status,
                    (SELECT MIN(lc.legal_referred_at) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.legal_referred_at IS NOT NULL) AS legal_started_at
             FROM installments i JOIN contracts c ON c.id = i.contract_id WHERE i.contract_id = ? ORDER BY i.due_date ASC, i.installment_number ASC, i.id ASC',
            [(int) $contractId]
        );
        $states = InstallmentFinancialStateService::statesForRows($rows, null, $asOf);
        $resolved = self::policyForContract((int) $contractId);
        $result = self::evaluate($contract, $rows, $states, $resolved['policy'], $asOf);
        $result['policy_version'] = (int) $resolved['version'];
        $result['policy_source'] = $resolved['source'];
        return $result;
    }

    /** Bounded queue: never scan an unbounded shared-host portfolio in a request. */
    public static function queue($search = null, $limit = 100, $asOf = null)
    {
        $limit = max(1, min(200, (int) $limit));
        $params = [];
        $where = "c.status NOT IN ('cancelled', 'closed')";
        if (trim((string) $search) !== '') {
            $needle = '%' . to_english_digits($search) . '%';
            $where .= ' AND (c.contract_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        $contracts = Model::fetchAll(
            "SELECT c.*, u.full_name AS customer_name, u.mobile FROM contracts c JOIN users u ON u.id = c.customer_id WHERE {$where} ORDER BY c.updated_at DESC, c.id DESC LIMIT {$limit}",
            $params
        );
        $items = [];
        foreach ($contracts as $contract) {
            $result = self::forContract((int) $contract['id'], $asOf);
            if ($result['eligible']) {
                $items[] = array_merge($contract, $result);
            }
        }
        usort($items, static function ($a, $b) {
            $dateCompare = strcmp((string) ($a['oldest_due_date'] ?? ''), (string) ($b['oldest_due_date'] ?? ''));
            return $dateCompare !== 0 ? $dateCompare : ((int) ($b['overdue_amount'] ?? 0) <=> (int) ($a['overdue_amount'] ?? 0));
        });
        return $items;
    }

    private static function thresholdDate($dueDate, array $policy)
    {
        try {
            $date = new DateTimeImmutable((string) $dueDate);
            $value = max(1, (int) ($policy['delay_value'] ?? 30));
            return ($policy['delay_unit'] ?? 'day') === 'month'
                ? $date->modify('+' . $value . ' months')->format('Y-m-d')
                : $date->modify('+' . $value . ' days')->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function date($value)
    {
        return substr((string) $value, 0, 10);
    }
}
