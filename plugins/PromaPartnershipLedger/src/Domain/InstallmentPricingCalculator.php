<?php

namespace Proma\Plugins\PartnershipLedger\Domain;

/**
 * Deterministic integer-Rial installment quote calculator.
 * Rates are basis points (10000 = 100% monthly); all rounding is half-up to Rial.
 */
final class InstallmentPricingCalculator
{
    private const FACTOR_SCALE = 100000000;

    public static function quote($basePriceRial, $downPaymentRial, $monthlyRateBp, $months, $method, $basis)
    {
        $base = Money::integer($basePriceRial);
        $down = Money::integer($downPaymentRial);
        $rate = filter_var($monthlyRateBp, FILTER_VALIDATE_INT);
        $months = filter_var($months, FILTER_VALIDATE_INT);
        $method = trim((string) $method);
        $basis = trim((string) $basis);
        if ($base <= 0 || $rate === false || $rate < 0 || $rate > 10000 || $months === false || $months < 1 || $months > 120
            || !in_array($method, ['simple', 'compound'], true)
            || !in_array($basis, ['full_base_price', 'funded_amount'], true)) {
            throw new \InvalidArgumentException('پارامترهای محاسبهٔ فروش اقساطی معتبر نیستند.');
        }
        if ($down > $base && $basis === 'funded_amount') {
            throw new \InvalidArgumentException('پیش‌پرداخت در مبنای مبلغ تأمین‌شده نمی‌تواند از قیمت پایه بیشتر باشد.');
        }
        $financeBase = $basis === 'funded_amount' ? $base - $down : $base;
        $premium = $method === 'simple'
            ? self::simplePremium($financeBase, $rate, $months)
            : self::compoundPremium($financeBase, $rate, $months);
        $final = Money::add($base, $premium);
        if ($down > $final) {
            throw new \InvalidArgumentException('پیش‌پرداخت نمی‌تواند از مبلغ نهایی بیشتر باشد.');
        }
        $remaining = $final - $down;
        $regular = intdiv($remaining, $months);
        $remainder = $remaining % $months;
        $installments = [];
        for ($index = 0; $index < $months; $index++) {
            $installments[] = $regular + ($index < $remainder ? 1 : 0);
        }
        return [
            'base_price_rial' => $base,
            'down_payment_rial' => $down,
            'financing_basis_rial' => $financeBase,
            'monthly_rate_bp' => $rate,
            'months' => $months,
            'method' => $method,
            'basis' => $basis,
            'financing_premium_rial' => $premium,
            'final_sale_price_rial' => $final,
            'remaining_payable_rial' => $remaining,
            'installments_rial' => $installments,
        ];
    }

    private static function simplePremium($base, $rateBp, $months)
    {
        $totalRateBp = $rateBp * $months;
        $whole = intdiv($base, 10000);
        $tail = $base % 10000;
        if ($totalRateBp > 0 && $whole > intdiv(PHP_INT_MAX, $totalRateBp)) {
            throw new \OverflowException('سود مدت بازپرداخت از محدودهٔ محاسبات صحیح بیشتر است.');
        }
        $premium = $whole * $totalRateBp;
        $tailProduct = $tail * $totalRateBp;
        $tailPremium = intdiv($tailProduct, 10000);
        if (($tailProduct % 10000) >= 5000) {
            $tailPremium++;
        }
        return Money::add($premium, $tailPremium);
    }

    private static function compoundPremium($base, $rateBp, $months)
    {
        $factor = self::FACTOR_SCALE;
        $multiplier = 10000 + $rateBp;
        for ($period = 0; $period < $months; $period++) {
            if ($multiplier > 0 && $factor > intdiv(PHP_INT_MAX, $multiplier)) {
                throw new \OverflowException('ضریب سود مرکب از محدودهٔ محاسبات صحیح بیشتر است.');
            }
            $numerator = $factor * $multiplier;
            $factor = intdiv($numerator, 10000);
            if (($numerator % 10000) >= 5000) {
                $factor++;
            }
        }
        $growth = $factor - self::FACTOR_SCALE;
        if ($growth <= 0 || $base === 0) {
            return 0;
        }
        $whole = intdiv($base, self::FACTOR_SCALE);
        $tail = $base % self::FACTOR_SCALE;
        if ($whole > 0 && $growth > intdiv(PHP_INT_MAX, $whole)) {
            throw new \OverflowException('سود مدت بازپرداخت از محدودهٔ محاسبات صحیح بیشتر است.');
        }
        if ($tail > 0 && $growth > intdiv(PHP_INT_MAX, $tail)) {
            throw new \OverflowException('سود مدت بازپرداخت از محدودهٔ محاسبات صحیح بیشتر است.');
        }
        $premium = $whole * $growth;
        $tailProduct = $tail * $growth;
        $tailPremium = intdiv($tailProduct, self::FACTOR_SCALE);
        if (($tailProduct % self::FACTOR_SCALE) >= intdiv(self::FACTOR_SCALE, 2)) {
            $tailPremium++;
        }
        return Money::add($premium, $tailPremium);
    }
}
