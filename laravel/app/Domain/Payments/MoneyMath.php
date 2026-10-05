<?php

namespace App\Domain\Payments;

use App\Support\PersianDigits;
use DomainException;

/** Integer-only port of the Legacy MoneyMath primitives used by settlement. */
final class MoneyMath
{
    public const RATE_SCALE = 10_000;

    public const PERCENT_DENOMINATOR = 1_000_000;

    public static function rateUnits(int|float|string $value): int
    {
        $value = str_replace(['٪', '%', 'درصد', ',', '،', '٬', ' ', "\xC2\xA0"], '', PersianDigits::toEnglish((string) $value));
        if (! preg_match('/^\+?(\d+)(?:\.(\d{0,4}))?$/', $value, $matches)) {
            return 0;
        }

        $whole = min(100, (int) $matches[1]);
        $fraction = str_pad((string) ($matches[2] ?? ''), 4, '0');
        $units = ($whole * self::RATE_SCALE) + (int) substr($fraction, 0, 4);

        return max(0, min(self::PERCENT_DENOMINATOR, $units));
    }

    public static function rateLabel(int $units): string
    {
        $units = max(0, min(self::PERCENT_DENOMINATOR, $units));
        $whole = intdiv($units, self::RATE_SCALE);
        $fraction = str_pad((string) ($units % self::RATE_SCALE), 4, '0', STR_PAD_LEFT);

        return rtrim(rtrim($whole.'.'.$fraction, '0'), '.');
    }

    public static function ceilDiv(int $numerator, int $denominator): int
    {
        $numerator = max(0, $numerator);
        $denominator = max(1, $denominator);

        return intdiv($numerator, $denominator) + ($numerator % $denominator === 0 ? 0 : 1);
    }

    public static function ceilMulDiv(int $amount, int $multiplier, int $denominator): int
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
            throw new DomainException('مبلغ محاسبهٔ جریمه از محدودهٔ عدد صحیح خارج است.');
        }

        $result = $whole * $multiplier;
        if ($remainder > 0) {
            if ($remainder > intdiv(PHP_INT_MAX, $multiplier)) {
                throw new DomainException('مبلغ محاسبهٔ جریمه از محدودهٔ عدد صحیح خارج است.');
            }
            $part = self::ceilDiv($remainder * $multiplier, $denominator);
            if ($result > PHP_INT_MAX - $part) {
                throw new DomainException('مبلغ محاسبهٔ جریمه از محدودهٔ عدد صحیح خارج است.');
            }
            $result += $part;
        }

        return $result;
    }

    public static function rateForDays(int $amount, int $monthlyRateUnits, int $days): int
    {
        $days = max(0, min(3660, $days));

        return self::ceilMulDiv($amount, max(0, $monthlyRateUnits) * $days, self::PERCENT_DENOMINATOR * 30);
    }

    public static function ceilToStep(int $amount, int $step): int
    {
        $units = self::ceilDiv(max(0, $amount), max(1, $step));
        if ($units > intdiv(PHP_INT_MAX, max(1, $step))) {
            throw new DomainException('مبلغ محاسبه از محدودهٔ عدد صحیح خارج است.');
        }

        return $units * max(1, $step);
    }
}
