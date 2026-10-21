<?php

class MoneyMath
{
    public const RATE_SCALE = 10000;
    public const PERCENT_DENOMINATOR = 1000000;

    public static function amount($value)
    {
        $value = str_replace(['تومان', 'ریال', ',', '،', '٬', ' ', "\xC2\xA0"], '', to_english_digits((string) $value));
        if (!preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return 0;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > strlen((string) PHP_INT_MAX)
            || (strlen($whole) === strlen((string) PHP_INT_MAX) && strcmp($whole, (string) PHP_INT_MAX) > 0)) {
            return PHP_INT_MAX;
        }

        $amount = (int) $whole;
        $fraction = $matches[2] ?? '';
        if ($fraction !== '' && $fraction[0] >= '5' && $amount < PHP_INT_MAX) {
            $amount++;
        }
        return $amount;
    }

    public static function rateUnits($value)
    {
        $value = str_replace(['٪', '%', 'درصد', ',', '،', '٬', ' ', "\xC2\xA0"], '', to_english_digits((string) $value));
        if (!preg_match('/^\+?(\d+)(?:\.(\d{0,4}))?$/', $value, $matches)) {
            return 0;
        }

        $whole = min(100, (int) $matches[1]);
        $fraction = str_pad((string) ($matches[2] ?? ''), 4, '0');
        $units = ($whole * self::RATE_SCALE) + (int) substr($fraction, 0, 4);
        return max(0, min(self::PERCENT_DENOMINATOR, $units));
    }

    public static function rateLabel($units)
    {
        $units = max(0, min(self::PERCENT_DENOMINATOR, (int) $units));
        $whole = intdiv($units, self::RATE_SCALE);
        $fraction = str_pad((string) ($units % self::RATE_SCALE), 4, '0', STR_PAD_LEFT);
        return rtrim(rtrim($whole . '.' . $fraction, '0'), '.');
    }

    public static function ceilDiv($numerator, $denominator)
    {
        $numerator = max(0, (int) $numerator);
        $denominator = max(1, (int) $denominator);
        return intdiv($numerator, $denominator) + ($numerator % $denominator === 0 ? 0 : 1);
    }

    public static function ceilMulDiv($amount, $multiplier, $denominator)
    {
        $amount = max(0, (int) $amount);
        $multiplier = max(0, (int) $multiplier);
        $denominator = max(1, (int) $denominator);
        if ($amount === 0 || $multiplier === 0) {
            return 0;
        }

        $whole = intdiv($amount, $denominator);
        $remainder = $amount % $denominator;
        if ($whole > intdiv(PHP_INT_MAX, $multiplier)) {
            return PHP_INT_MAX;
        }
        $result = $whole * $multiplier;
        if ($remainder > 0) {
            if ($remainder > intdiv(PHP_INT_MAX, $multiplier)) {
                return PHP_INT_MAX;
            }
            $part = self::ceilDiv($remainder * $multiplier, $denominator);
            if ($result > PHP_INT_MAX - $part) {
                return PHP_INT_MAX;
            }
            $result += $part;
        }
        return $result;
    }

    public static function rateForDays($amount, $monthlyRateUnits, $days)
    {
        $days = max(0, min(3660, (int) $days));
        return self::ceilMulDiv($amount, max(0, (int) $monthlyRateUnits) * $days, self::PERCENT_DENOMINATOR * 30);
    }

    public static function applyMonthlyRate($amount, $monthlyRateUnits)
    {
        return self::ceilMulDiv($amount, self::PERCENT_DENOMINATOR + max(0, (int) $monthlyRateUnits), self::PERCENT_DENOMINATOR);
    }

    public static function ceilToStep($amount, $step)
    {
        $amount = max(0, (int) $amount);
        $step = max(1, (int) $step);
        $units = self::ceilDiv($amount, $step);
        return $units > intdiv(PHP_INT_MAX, $step) ? PHP_INT_MAX : $units * $step;
    }
}
