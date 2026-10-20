<?php

namespace Proma\Plugins\Repair\Domain;

final class RepairMoney
{
    public const MAX_INPUT_TOMAN = 1000000000000;

    /** Parse user-entered Toman without accepting signs, fractions, or overflow. */
    public static function input($value): int
    {
        $digits = self::englishDigits(trim((string) $value));
        $digits = str_ireplace(['تومان', 'ریال', "\xC2\xA0", "\xE2\x80\x89"], ['', '', ' ', ' '], $digits);
        $digits = trim($digits);
        if (preg_match('/^\+?\d+$/D', $digits)) {
            $integer = ltrim(ltrim($digits, '+'), '0');
            $integer = $integer === '' ? '0' : $integer;
        } elseif (preg_match('/^\+?\d{1,3}([,،٬ ])\d{3}(?:\1\d{3})*$/uD', $digits)) {
            $integer = str_replace([',', '،', '٬', ' '], '', ltrim($digits, '+'));
            $integer = ltrim($integer, '0');
            $integer = $integer === '' ? '0' : $integer;
        } else {
            throw new \InvalidArgumentException('مبلغ باید عدد صحیح و غیرمنفی به تومان باشد.');
        }
        if (self::greaterThan($integer, (string) self::MAX_INPUT_TOMAN)) {
            throw new \InvalidArgumentException('مبلغ از سقف مجاز تعمیرات بیشتر است.');
        }
        return (int) $integer;
    }

    /** Normalize an existing DECIMAL value only when it represents whole Toman. */
    public static function stored($value): int
    {
        if (is_int($value)) {
            if ($value < 0) throw new \UnexpectedValueException('Stored repair money cannot be negative.');
            return $value;
        }
        $digits = self::englishDigits(trim((string) $value));
        if (!preg_match('/^\+?(\d+)(?:\.(0+))?$/D', $digits, $matches)) {
            throw new \UnexpectedValueException('Stored repair money contains a fractional Toman value; data preflight is required.');
        }
        $integer = ltrim(ltrim($matches[1], '+'), '0');
        $integer = $integer === '' ? '0' : $integer;
        if (self::greaterThan($integer, (string) PHP_INT_MAX)) {
            throw new \OverflowException('Stored repair money exceeds the PHP integer range.');
        }
        return (int) $integer;
    }

    /** Ignore exact negative legacy ledger rows when producing nonnegative totals. */
    public static function nonNegativeStored($value): int
    {
        if (is_int($value) && $value < 0) {
            return 0;
        }
        $text = self::englishDigits(trim((string) $value));
        if (preg_match('/^-\d+(?:\.0+)?$/D', $text)) {
            return 0;
        }
        return self::stored($value);
    }

    /** Return a bounded percentage as a two-decimal string without float math. */
    public static function percentage($value): string
    {
        $text = trim(self::englishDigits((string) $value));
        $text = str_replace(['٪', '%', ' '], '', $text);
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $text, $matches)) {
            throw new \InvalidArgumentException('درصد کمیسیون باید عددی بین صفر و صد باشد.');
        }
        $whole = (int) $matches[1];
        $fraction = str_pad((string) ($matches[2] ?? ''), 2, '0');
        if ($whole > 100 || ($whole === 100 && (int) $fraction > 0)) {
            throw new \InvalidArgumentException('درصد کمیسیون نباید بیشتر از صد باشد.');
        }
        return $whole . '.' . $fraction;
    }

    private static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    private static function greaterThan(string $value, string $limit): bool
    {
        $value = ltrim($value, '0');
        $limit = ltrim($limit, '0');
        $value = $value === '' ? '0' : $value;
        $limit = $limit === '' ? '0' : $limit;
        return strlen($value) > strlen($limit) || (strlen($value) === strlen($limit) && strcmp($value, $limit) > 0);
    }
}
