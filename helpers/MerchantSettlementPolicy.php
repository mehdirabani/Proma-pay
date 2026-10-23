<?php

declare(strict_types=1);

/** Validates immutable settlement rules and defaults the operation to disabled. */
final class MerchantSettlementPolicy
{
    public static function resolve(array $policy): array
    {
        if (($policy['currency_unit'] ?? 'toman') !== 'toman'
            || ($policy['settlement_basis'] ?? null) !== 'cash_product_price_less_verified_down_payment') {
            throw new InvalidArgumentException('merchant_settlement_basis_invalid');
        }
        $enabled = $policy['enabled'] ?? false;
        if (!in_array($enabled, [true, false], true)) {
            throw new InvalidArgumentException('merchant_settlement_enabled_invalid');
        }
        $ownership = $policy['receivable_ownership_model'] ?? 'unapproved';
        if (!in_array($ownership, ['merchant', 'platform', 'third_party_funder', 'unapproved'], true)) {
            throw new InvalidArgumentException('merchant_settlement_ownership_model_invalid');
        }
        $fundingAccountUuid = self::optionalUuid($policy['funding_account_uuid'] ?? null);
        $legalContractReference = self::optionalReference($policy['legal_contract_reference'] ?? null);
        $legalApprovalReference = self::optionalReference($policy['legal_approval_reference'] ?? null);
        if ($enabled && ($ownership === 'unapproved'
            || $fundingAccountUuid === null
            || $legalContractReference === null
            || $legalApprovalReference === null)) {
            throw new DomainException('merchant_settlement_legal_funding_approval_required');
        }

        $requirements = $policy['required_gates'] ?? [];
        if (!is_array($requirements)) {
            throw new InvalidArgumentException('merchant_settlement_requirements_invalid');
        }
        $required = [
            'initial_payment_verified', 'contract_approved', 'guarantee_confirmed',
            'documents_complete', 'delivery_confirmed', 'no_hold_or_dispute',
            'exposure_within_limit', 'legal_and_funding_approved', 'adjustments_resolved',
            'positive_net_settlement',
        ];
        $normalizedRequirements = [];
        foreach ($required as $gate) {
            $value = $requirements[$gate] ?? true;
            if ($value !== true) {
                throw new InvalidArgumentException('merchant_settlement_required_gate_cannot_be_disabled');
            }
            $normalizedRequirements[$gate] = true;
        }

        $minimumReserve = $policy['minimum_liquidity_reserve_toman'] ?? 0;
        if (!is_int($minimumReserve) || $minimumReserve < 0) {
            throw new InvalidArgumentException('merchant_liquidity_reserve_invalid');
        }
        $exposureLimits = [];
        foreach (['max_single_settlement_toman', 'max_merchant_exposure_toman', 'max_platform_exposure_toman'] as $key) {
            $limit = $policy[$key] ?? null;
            if ($limit !== null && (!is_int($limit) || $limit < 1)) {
                throw new InvalidArgumentException('merchant_settlement_exposure_limit_invalid');
            }
            if ($enabled && $limit === null) {
                throw new DomainException('merchant_settlement_exposure_limit_required');
            }
            $exposureLimits[$key] = $limit;
        }
        $deductionCodes = self::codeList($policy['allowed_deduction_codes'] ?? []);
        return [
            'enabled' => $enabled,
            'currency_unit' => 'toman',
            'settlement_basis' => 'cash_product_price_less_verified_down_payment',
            'receivable_ownership_model' => $ownership,
            'funding_account_uuid' => $fundingAccountUuid,
            'legal_contract_reference' => $legalContractReference,
            'legal_approval_reference' => $legalApprovalReference,
            'minimum_liquidity_reserve_toman' => $minimumReserve,
            'max_single_settlement_toman' => $exposureLimits['max_single_settlement_toman'],
            'max_merchant_exposure_toman' => $exposureLimits['max_merchant_exposure_toman'],
            'max_platform_exposure_toman' => $exposureLimits['max_platform_exposure_toman'],
            'allowed_deduction_codes' => $deductionCodes,
            'required_gates' => $normalizedRequirements,
            'schedule' => self::resolveSchedule($policy['schedule'] ?? null),
        ];
    }

    private static function resolveSchedule($schedule): array
    {
        if (!is_array($schedule)) {
            throw new InvalidArgumentException('merchant_settlement_schedule_invalid');
        }
        $type = $schedule['type'] ?? null;
        $types = ['every_n_days', 'weekly', 'fixed_jalali_days', 'jalali_month_end', 'manual'];
        if (!is_string($type) || !in_array($type, $types, true)) {
            throw new InvalidArgumentException('merchant_settlement_schedule_invalid');
        }
        $holidayPolicy = $schedule['holiday_policy'] ?? 'no_shift';
        if (!in_array($holidayPolicy, ['no_shift', 'next_business_day', 'previous_business_day'], true)) {
            throw new InvalidArgumentException('merchant_settlement_holiday_policy_invalid');
        }
        $weekdays = $schedule['business_weekdays_iso'] ?? [];
        if (!is_array($weekdays)) {
            throw new InvalidArgumentException('merchant_settlement_business_days_invalid');
        }
        $weekdays = self::integerList($weekdays, 1, 7);
        if ($holidayPolicy !== 'no_shift' && !$weekdays) {
            throw new InvalidArgumentException('merchant_settlement_business_days_required');
        }
        $normalized = [
            'type' => $type,
            'holiday_policy' => $holidayPolicy,
            'business_weekdays_iso' => $weekdays,
        ];
        if ($type === 'every_n_days') {
            $interval = $schedule['interval_days'] ?? null;
            if (!is_int($interval) || $interval < 1 || $interval > 366) {
                throw new InvalidArgumentException('merchant_settlement_interval_invalid');
            }
            $normalized['interval_days'] = $interval;
        } elseif ($type === 'weekly') {
            $weekday = $schedule['weekday_iso'] ?? null;
            if (!is_int($weekday) || $weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('merchant_settlement_weekday_invalid');
            }
            $normalized['weekday_iso'] = $weekday;
        } elseif ($type === 'fixed_jalali_days') {
            $days = self::integerList($schedule['days'] ?? null, 1, 31);
            if (!$days) {
                throw new InvalidArgumentException('merchant_settlement_month_days_invalid');
            }
            $shortMonthBehavior = $schedule['short_month_behavior'] ?? null;
            if (!in_array($shortMonthBehavior, ['clamp_to_month_end', 'skip'], true)) {
                throw new InvalidArgumentException('merchant_settlement_short_month_behavior_invalid');
            }
            $normalized['days'] = $days;
            $normalized['short_month_behavior'] = $shortMonthBehavior;
        } elseif ($type === 'manual') {
            $normalized['manual_requires_approval'] = true;
        }
        return $normalized;
    }

    private static function integerList($values, int $minimum, int $maximum): array
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('merchant_settlement_list_invalid');
        }
        $result = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value < $minimum || $value > $maximum) {
                throw new InvalidArgumentException('merchant_settlement_list_invalid');
            }
            $result[$value] = $value;
        }
        sort($result, SORT_NUMERIC);
        return array_values($result);
    }

    private static function codeList($values): array
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('merchant_settlement_deduction_codes_invalid');
        }
        $codes = [];
        foreach ($values as $value) {
            if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_.-]{0,59}$/D', $value)) {
                throw new InvalidArgumentException('merchant_settlement_deduction_codes_invalid');
            }
            $codes[$value] = $value;
        }
        sort($codes, SORT_STRING);
        return array_values($codes);
    }

    private static function optionalUuid($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value)) {
            throw new InvalidArgumentException('merchant_settlement_funding_account_invalid');
        }
        return strtolower($value);
    }

    private static function optionalReference($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,189}$/D', trim($value))) {
            throw new InvalidArgumentException('merchant_settlement_reference_invalid');
        }
        return trim($value);
    }
}
