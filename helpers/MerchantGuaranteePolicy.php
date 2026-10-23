<?php

declare(strict_types=1);

/** Validates the central cap and a connection's versioned Woo guarantee terms. */
final class MerchantGuaranteePolicy
{
    public static function resolve(array $connectionPolicy, array $centralPolicy): array
    {
        if (($centralPolicy['status'] ?? '') !== 'published'
            || !in_array($centralPolicy['enabled'] ?? null, [1, '1', true], true)) {
            throw new DomainException('merchant_central_guarantee_policy_unavailable');
        }

        $minimum = self::positiveInteger($centralPolicy['minimum_multiplier_units'] ?? null);
        $maximum = self::positiveInteger($centralPolicy['maximum_multiplier_units'] ?? null);
        $default = self::positiveInteger($centralPolicy['default_multiplier_units'] ?? null);
        if ($minimum > $maximum || $default < $minimum || $default > $maximum) {
            throw new DomainException('merchant_central_guarantee_policy_invalid');
        }

        $chequeCount = $connectionPolicy['required_cheque_count'] ?? null;
        if (($connectionPolicy['guarantee_type'] ?? null) !== 'sayadi_cheque'
            || (!is_int($chequeCount) && !(is_string($chequeCount) && preg_match('/^\d+$/D', $chequeCount)))
            || (int) $chequeCount !== 1
            || ($connectionPolicy['guarantee_base'] ?? null) !== 'scheduled_installments') {
            throw new DomainException('merchant_woocommerce_guarantee_policy_invalid');
        }

        $rawMultiplier = $connectionPolicy['cheque_multiplier'] ?? null;
        $multiplier = $rawMultiplier === null
            ? $default
            : MerchantGuaranteeCalculator::parseMultiplierUnits($rawMultiplier);
        if ($multiplier < $minimum || $multiplier > $maximum) {
            throw new DomainException('merchant_cheque_multiplier_outside_central_limits');
        }

        $beneficiaryName = self::requiredText($centralPolicy['beneficiary_legal_name'] ?? null, 190);
        $beneficiaryId = self::normalizeIdentifier($centralPolicy['beneficiary_identifier'] ?? null);
        $approvalReference = self::requiredReference($centralPolicy['legal_approval_reference'] ?? null);

        return [
            'guarantee_type' => 'sayadi_cheque',
            'required_cheque_count' => 1,
            'guarantee_base' => 'scheduled_installments',
            'multiplier_units' => $multiplier,
            'multiplier_scale' => MerchantGuaranteeCalculator::MULTIPLIER_SCALE,
            'central_minimum_multiplier_units' => $minimum,
            'central_maximum_multiplier_units' => $maximum,
            'default_multiplier_units' => $default,
            'beneficiary_legal_name' => $beneficiaryName,
            'beneficiary_identifier' => $beneficiaryId,
            'central_policy_version_id' => (int) ($centralPolicy['id'] ?? 0),
            'legal_approval_reference' => $approvalReference,
        ];
    }

    private static function positiveInteger($value): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/D', $value))) {
            throw new DomainException('merchant_central_guarantee_policy_invalid');
        }
        $number = (int) $value;
        if ($number < 1 || $number > 4294967295) {
            throw new DomainException('merchant_central_guarantee_policy_invalid');
        }
        return $number;
    }

    private static function requiredText($value, int $maxCharacters): string
    {
        if (!is_string($value)) {
            throw new DomainException('merchant_guarantee_beneficiary_invalid');
        }
        $value = trim($value);
        $characters = preg_match_all('/./us', $value);
        if ($value === '' || $characters === false || $characters > $maxCharacters
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new DomainException('merchant_guarantee_beneficiary_invalid');
        }
        return $value;
    }

    private static function normalizeIdentifier($value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new DomainException('merchant_guarantee_beneficiary_invalid');
        }
        $identifier = strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        if (!preg_match('/^\d{5,20}$/D', $identifier)) {
            throw new DomainException('merchant_guarantee_beneficiary_invalid');
        }
        return $identifier;
    }

    private static function requiredReference($value): string
    {
        if (!is_string($value)) {
            throw new DomainException('merchant_guarantee_legal_approval_missing');
        }
        $reference = trim($value);
        if ($reference === '' || strlen($reference) > 190
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $reference)) {
            throw new DomainException('merchant_guarantee_legal_approval_missing');
        }
        return $reference;
    }
}
