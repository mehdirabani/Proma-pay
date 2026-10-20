<?php

namespace Proma\Plugins\Repair\Support;

/** Central UI-to-database date conversion for Repair. UI dates are Jalali. */
final class RepairDate
{
    public static function date($value): ?string
    {
        $value = trim(\to_english_digits((string) $value));
        if ($value === '') {
            return null;
        }
        $date = \parse_jalali_date($value);
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    public static function dateTime($date, $time = ''): ?string
    {
        $date = self::date($date);
        if (!$date) {
            return null;
        }
        $rawTime = trim((string) $time);
        $normalizedTime = \normalize_time($rawTime);
        if ($rawTime !== '' && !$normalizedTime) {
            return null;
        }
        return $date . ' ' . ($normalizedTime ?: '17:00') . ':00';
    }

    public static function legacyDateTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $parts = preg_split('/[T ]/', $value, 2);
        return self::dateTime($parts[0] ?? '', $parts[1] ?? '');
    }
}
