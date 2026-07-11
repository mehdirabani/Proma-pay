<?php

namespace Proma\Plugins\Accounting\Services;

class Money
{
    public static function integer($value)
    {
        $value = trim(str_replace(['٬', ',', '،', 'تومان', 'ریال', ' '], '', self::englishDigits($value)));
        if ($value === '' || !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return 0;
        }
        return (int) preg_replace('/\..*$/', '', $value);
    }

    public static function rateBasisPoints($value)
    {
        $value = trim(str_replace([',', '،', '٪', '%', ' '], '', self::englishDigits($value)));
        if ($value === '' || !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return 0;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        return ((int) $whole * 100) + (int) $fraction;
    }

    public static function percentage($basis, $rate)
    {
        $basis = max(0, self::integer($basis));
        $rate = max(0, self::rateBasisPoints($rate));
        $product = $basis * $rate;
        return intdiv($product + 5000, 10000);
    }

    public static function decimal($value)
    {
        return number_format(self::integer($value), 2, '.', '');
    }

    protected static function englishDigits($value)
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
