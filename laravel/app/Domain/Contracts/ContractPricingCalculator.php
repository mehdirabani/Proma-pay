<?php

namespace App\Domain\Contracts;

use App\Support\PersianDigits;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use InvalidArgumentException;

/**
 * Integer-only port of the current Legacy FinanceHelper::contractPreview rules.
 * It calculates a quote; it does not create a contract or post a payment.
 */
final class ContractPricingCalculator
{
    private const PERCENT_DENOMINATOR = 1_000_000;

    private const RATE_SCALE = 10_000;

    private const INSTALLMENT_STEP_TOMAN = 100_000;

    private const MAX_INSTALLMENTS = 65_535;

    /**
     * @return array{calculation_version:string,principal_toman:string,down_payment_toman:string,financed_toman:string,monthly_rate_percent:string,monthly_rate_units:int,interest_type:string,installment_count:int,installment_amount_toman:string,total_installments_toman:string}
     */
    public function quote(
        string|int $principalToman,
        string|int $downPaymentToman,
        string|int $monthlyRate,
        int $installmentCount,
        string $interestType,
    ): array {
        $principal = $this->parseAmount($principalToman, 'اصل قرارداد');
        $downPayment = $this->parseAmount($downPaymentToman, 'پیش‌پرداخت');

        if ($principal <= 0) {
            throw new InvalidArgumentException('مبلغ اصل قرارداد باید بیشتر از صفر باشد.');
        }

        if ($downPayment > $principal) {
            throw new InvalidArgumentException('پیش‌پرداخت نمی‌تواند از اصل قرارداد بیشتر باشد.');
        }

        if ($installmentCount < 1 || $installmentCount > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('تعداد اقساط باید بین ۱ و ۶۵۵۳۵ باشد.');
        }

        if (! in_array($interestType, ['simple', 'compound'], true)) {
            throw new InvalidArgumentException('نوع سود باید ساده یا مرکب باشد.');
        }

        $rateUnits = $this->parseRateUnits($monthlyRate);
        $financed = $principal - $downPayment;
        $installmentAmount = $this->calculateInstallmentAmount($financed, $installmentCount, $rateUnits, $interestType);

        return [
            'calculation_version' => 'legacy-contract-finance-v1',
            'principal_toman' => (string) $principal,
            'down_payment_toman' => (string) $downPayment,
            'financed_toman' => (string) $financed,
            'monthly_rate_percent' => $this->rateLabel($rateUnits),
            'monthly_rate_units' => $rateUnits,
            'interest_type' => $interestType,
            'installment_count' => $installmentCount,
            'installment_amount_toman' => (string) $installmentAmount,
            'total_installments_toman' => (string) $this->safeMultiply($installmentAmount, $installmentCount),
        ];
    }

    /**
     * Generate monthly dates from the original anchor day, matching the Legacy rule.
     * For example, an anchor on January 31 yields February 28/29 and then March 31.
     *
     * @return Generator<int, string>
     */
    public function dueDates(string $firstDueDate, int $installmentCount): Generator
    {
        if ($installmentCount < 1 || $installmentCount > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('تعداد اقساط باید بین ۱ و ۶۵۵۳۵ باشد.');
        }

        $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $firstDueDate, new DateTimeZone('UTC'));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($anchor === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $anchor->format('Y-m-d') !== $firstDueDate) {
            throw new InvalidArgumentException('تاریخ اولین سررسید معتبر نیست.');
        }

        $anchorDay = (int) $anchor->format('j');
        $monthStart = $anchor->modify('first day of this month');

        for ($offset = 0; $offset < $installmentCount; $offset++) {
            $month = $monthStart->modify('+'.$offset.' months');
            $day = min($anchorDay, (int) $month->format('t'));
            yield $offset + 1 => $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day)->format('Y-m-d');
        }
    }

    private function calculateInstallmentAmount(int $financed, int $months, int $rateUnits, string $interestType): int
    {
        if ($financed <= 0) {
            return 0;
        }

        if ($interestType === 'compound') {
            $total = $financed;
            for ($month = 0; $month < $months; $month++) {
                $total = $this->ceilMultiplyDivide($total, self::PERCENT_DENOMINATOR + $rateUnits, self::PERCENT_DENOMINATOR);
            }
        } else {
            $days = $months * 30;
            $interest = $this->ceilMultiplyDivide($financed, $rateUnits * $days, self::PERCENT_DENOMINATOR * 30);
            $total = $this->safeAdd($financed, $interest);
        }

        $unrounded = $this->ceilDivide($total, $months);

        return $this->ceilToStep($unrounded, self::INSTALLMENT_STEP_TOMAN);
    }

    private function parseAmount(string|int $value, string $field): int
    {
        $normalized = PersianDigits::toEnglish((string) $value);
        $normalized = str_replace(['تومان', 'ریال', ',', '،', '٬', ' ', "\u{00A0}"], '', $normalized);

        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("مبلغ {$field} باید عدد صحیح تومان باشد.");
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $maximum = (string) PHP_INT_MAX;
        if (strlen($whole) > strlen($maximum)
            || (strlen($whole) === strlen($maximum) && strcmp($whole, $maximum) > 0)) {
            throw new InvalidArgumentException("مبلغ {$field} از محدودهٔ محاسبهٔ امن بیشتر است.");
        }

        $amount = (int) $whole;
        $fraction = $matches[2] ?? '';
        if ($fraction !== '' && $fraction[0] >= '5') {
            if ($amount === PHP_INT_MAX) {
                throw new InvalidArgumentException("مبلغ {$field} از محدودهٔ محاسبهٔ امن بیشتر است.");
            }
            $amount++;
        }

        return $amount;
    }

    private function parseRateUnits(string|int $value): int
    {
        $normalized = PersianDigits::toEnglish((string) $value);
        $normalized = str_replace(['٪', '%', 'درصد', ',', '،', '٬', ' ', "\u{00A0}"], '', $normalized);
        $normalized = str_replace('٫', '.', $normalized);

        if (! preg_match('/^\+?(\d+)(?:\.(\d{0,4}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('نرخ سود باید عددی با حداکثر چهار رقم اعشار باشد.');
        }

        $whole = min(100, (int) $matches[1]);
        $fraction = str_pad((string) ($matches[2] ?? ''), 4, '0');
        $units = ($whole * self::RATE_SCALE) + (int) substr($fraction, 0, 4);

        return max(0, min(self::PERCENT_DENOMINATOR, $units));
    }

    private function rateLabel(int $units): string
    {
        $whole = intdiv($units, self::RATE_SCALE);
        $fraction = str_pad((string) ($units % self::RATE_SCALE), 4, '0', STR_PAD_LEFT);

        return rtrim(rtrim($whole.'.'.$fraction, '0'), '.');
    }

    private function ceilMultiplyDivide(int $amount, int $multiplier, int $denominator): int
    {
        $amount = max(0, $amount);
        $multiplier = max(0, $multiplier);
        $denominator = max(1, $denominator);

        if ($amount === 0 || $multiplier === 0) {
            return 0;
        }

        $whole = intdiv($amount, $denominator);
        $remainder = $amount % $denominator;
        if ($whole > intdiv(PHP_INT_MAX, $multiplier)) {
            return PHP_INT_MAX;
        }

        $result = $whole * $multiplier;
        if ($remainder > intdiv(PHP_INT_MAX, $multiplier)) {
            return PHP_INT_MAX;
        }

        $part = $this->ceilDivide($remainder * $multiplier, $denominator);
        if ($result > PHP_INT_MAX - $part) {
            return PHP_INT_MAX;
        }

        return $result + $part;
    }

    private function ceilDivide(int $numerator, int $denominator): int
    {
        $numerator = max(0, $numerator);
        $denominator = max(1, $denominator);

        return intdiv($numerator, $denominator) + ($numerator % $denominator === 0 ? 0 : 1);
    }

    private function ceilToStep(int $amount, int $step): int
    {
        $units = $this->ceilDivide($amount, $step);
        if ($units > intdiv(PHP_INT_MAX, $step)) {
            return PHP_INT_MAX;
        }

        return $units * $step;
    }

    private function safeAdd(int $left, int $right): int
    {
        return $left > PHP_INT_MAX - $right ? PHP_INT_MAX : $left + $right;
    }

    private function safeMultiply(int $left, int $right): int
    {
        return $left > 0 && $right > intdiv(PHP_INT_MAX, $left) ? PHP_INT_MAX : $left * $right;
    }
}
