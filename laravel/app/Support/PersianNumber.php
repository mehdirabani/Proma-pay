<?php

namespace App\Support;

class PersianNumber
{
    public static function format(int|string $value): string
    {
        return PersianDigits::toPersian(number_format((int) $value, 0, '', '٬'));
    }

    public static function toman(int|string $value): string
    {
        return self::format($value).' '.config('proma.currency_label');
    }
}
