<?php

declare(strict_types=1);

/** Exact Toman math for seller receivables and independent earning ledgers. */
final class MerchantSettlementCalculator
{
    public const CURRENCY_UNIT = 'toman';
    public const BASIS_POINT_SCALE = 10000;

    public static function calculateReceivable(
        int $cashProductPriceToman,
        int $verifiedDownPaymentToman,
        int $contractualDeductionsToman = 0,
        int $approvedAdjustmentsToman = 0
    ): array {
        if ($cashProductPriceToman < 1
            || $verifiedDownPaymentToman < 0
            || $verifiedDownPaymentToman > $cashProductPriceToman
            || $contractualDeductionsToman < 0) {
            throw new InvalidArgumentException('merchant_settlement_amount_invalid');
        }

        $gross = $cashProductPriceToman - $verifiedDownPaymentToman;
        if ($contractualDeductionsToman > $gross) {
            throw new InvalidArgumentException('merchant_settlement_deductions_exceed_gross');
        }
        $beforeAdjustments = $gross - $contractualDeductionsToman;
        if ($approvedAdjustmentsToman > 0
            && $beforeAdjustments > PHP_INT_MAX - $approvedAdjustmentsToman) {
            throw new OverflowException('merchant_settlement_amount_overflow');
        }
        if ($approvedAdjustmentsToman < 0
            && $beforeAdjustments < PHP_INT_MIN - $approvedAdjustmentsToman) {
            throw new OverflowException('merchant_settlement_amount_overflow');
        }
        $net = $beforeAdjustments + $approvedAdjustmentsToman;
        if ($net < 0) {
            throw new InvalidArgumentException('merchant_settlement_net_amount_negative');
        }

        return [
            'currency_unit' => self::CURRENCY_UNIT,
            'cash_product_price_toman' => $cashProductPriceToman,
            'verified_down_payment_toman' => $verifiedDownPaymentToman,
            'gross_receivable_toman' => $gross,
            'contractual_deductions_toman' => $contractualDeductionsToman,
            'approved_adjustments_toman' => $approvedAdjustmentsToman,
            'net_settlement_toman' => $net,
        ];
    }

    /** Returns eligible only when every evidence and risk gate is explicitly true. */
    public static function eligibility(array $checks): array
    {
        $required = [
            'initial_payment_verified',
            'contract_approved',
            'guarantee_confirmed',
            'documents_complete',
            'delivery_confirmed',
            'no_hold_or_dispute',
            'exposure_within_limit',
            'legal_and_funding_approved',
            'adjustments_resolved',
            'positive_net_settlement',
        ];
        $missing = [];
        foreach ($required as $key) {
            if (($checks[$key] ?? null) !== true) {
                $missing[] = $key;
            }
        }
        $held = ($checks['hold_or_dispute'] ?? false) === true;
        return [
            'status' => $held ? 'suspended' : ($missing ? 'pending' : 'eligible'),
            'missing_checks' => $missing,
        ];
    }

    /**
     * @param array{type:string,rate_bps?:int,fixed_amount_toman?:int,tiers?:array} $rule
     * @param array{cash_product_price_toman?:int,financed_principal_toman?:int,installment_collected_toman?:int,monthly_cash_volume_toman?:int} $basis
     */
    public static function calculateEarning(array $rule, array $basis): int
    {
        $type = $rule['type'] ?? null;
        if (!is_string($type)) {
            throw new InvalidArgumentException('merchant_commission_rule_invalid');
        }
        if ($type === 'fixed_per_sale' || $type === 'fixed_per_referral') {
            $amount = $rule['fixed_amount_toman'] ?? null;
            if (!is_int($amount) || $amount < 0) {
                throw new InvalidArgumentException('merchant_commission_rule_invalid');
            }
            return $amount;
        }

        if ($type === 'tiered_cash_volume_percent') {
            $volume = self::nonNegativeAmount($basis['monthly_cash_volume_toman'] ?? null);
            $tiers = $rule['tiers'] ?? null;
            if (!is_array($tiers) || !$tiers) {
                throw new InvalidArgumentException('merchant_commission_tiers_invalid');
            }
            $selected = null;
            foreach ($tiers as $tier) {
                if (!is_array($tier)
                    || !is_int($tier['minimum_monthly_cash_volume_toman'] ?? null)
                    || !is_int($tier['rate_bps'] ?? null)) {
                    throw new InvalidArgumentException('merchant_commission_tiers_invalid');
                }
                if ($tier['minimum_monthly_cash_volume_toman'] <= $volume) {
                    $selected = $tier;
                }
            }
            if ($selected === null) {
                return 0;
            }
            return self::percentOf(
                self::nonNegativeAmount($basis['cash_product_price_toman'] ?? null),
                $selected['rate_bps']
            );
        }

        $basisKey = [
            'cash_price_percent' => 'cash_product_price_toman',
            'financed_principal_percent' => 'financed_principal_toman',
            'installments_collected_percent' => 'installment_collected_toman',
        ][$type] ?? null;
        if ($basisKey === null) {
            throw new InvalidArgumentException('merchant_commission_rule_invalid');
        }
        return self::percentOf(
            self::nonNegativeAmount($basis[$basisKey] ?? null),
            $rule['rate_bps'] ?? null
        );
    }

    private static function percentOf(int $amount, $rateBps): int
    {
        if (!is_int($rateBps) || $rateBps < 0 || $rateBps > self::BASIS_POINT_SCALE) {
            throw new InvalidArgumentException('merchant_commission_rate_invalid');
        }
        if ($rateBps === 0 || $amount === 0) {
            return 0;
        }
        if ($amount > intdiv(PHP_INT_MAX, $rateBps)) {
            throw new OverflowException('merchant_commission_amount_overflow');
        }
        $scaled = $amount * $rateBps;
        $whole = intdiv($scaled, self::BASIS_POINT_SCALE);
        $remainder = $scaled % self::BASIS_POINT_SCALE;
        if ($remainder >= intdiv(self::BASIS_POINT_SCALE + 1, 2)) {
            if ($whole === PHP_INT_MAX) {
                throw new OverflowException('merchant_commission_amount_overflow');
            }
            $whole++;
        }
        return $whole;
    }

    private static function nonNegativeAmount($value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException('merchant_commission_basis_invalid');
        }
        return $value;
    }
}
