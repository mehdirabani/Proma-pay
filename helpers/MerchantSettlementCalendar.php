<?php

declare(strict_types=1);

/** Gregorian storage with explicit Jalali billing-cycle rules and holiday shifts. */
final class MerchantSettlementCalendar
{
    public static function nextDueDate(
        array $policy,
        string $anchorDate,
        string $onOrAfterDate,
        array $holidays = [],
        ?string $manualDueDate = null,
        bool $manualApproved = false
    ): string {
        $schedule = $policy['schedule'] ?? $policy;
        if (!is_array($schedule) || !isset($schedule['type'])) {
            throw new InvalidArgumentException('merchant_settlement_schedule_invalid');
        }
        $anchor = self::date($anchorDate);
        $after = self::date($onOrAfterDate);
        $holidaySet = [];
        foreach ($holidays as $holiday) {
            if (!is_string($holiday)) {
                throw new InvalidArgumentException('merchant_settlement_holiday_invalid');
            }
            $holidaySet[self::date($holiday)->format('Y-m-d')] = true;
        }
        $type = $schedule['type'];
        if ($type === 'manual') {
            if (!$manualApproved || $manualDueDate === null) {
                throw new DomainException('merchant_settlement_manual_date_requires_approval');
            }
            $manual = self::date($manualDueDate);
            if ($manual < $after) {
                throw new InvalidArgumentException('merchant_settlement_manual_date_in_past');
            }
            return self::shift($manual, $schedule, $holidaySet)->format('Y-m-d');
        }

        $candidates = [];
        if ($type === 'every_n_days') {
            $interval = $schedule['interval_days'] ?? null;
            if (!is_int($interval) || $interval < 1 || $interval > 366) {
                throw new InvalidArgumentException('merchant_settlement_interval_invalid');
            }
            $days = (int) $anchor->diff($after)->format('%r%a');
            $steps = $days <= 0 ? 0 : intdiv($days + $interval - 1, $interval);
            $candidates[] = $anchor->modify('+' . ($steps * $interval) . ' days');
        } elseif ($type === 'weekly') {
            $weekday = $schedule['weekday_iso'] ?? null;
            if (!is_int($weekday) || $weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('merchant_settlement_weekday_invalid');
            }
            $delta = ($weekday - (int) $after->format('N') + 7) % 7;
            $candidates[] = $after->modify('+' . $delta . ' days');
        } elseif ($type === 'jalali_month_end' || $type === 'fixed_jalali_days') {
            if (!function_exists('gregorian_to_jalali') || !function_exists('jalali_to_gregorian')) {
                throw new RuntimeException('merchant_settlement_jalali_calendar_unavailable');
            }
            [$year, $month] = gregorian_to_jalali((int) $after->format('Y'), (int) $after->format('n'), (int) $after->format('j'));
            for ($offset = 0; $offset < 3; $offset++) {
                [$targetYear, $targetMonth] = self::jalaliMonthAfter($year, $month, $offset);
                $monthStart = self::jalaliDate($targetYear, $targetMonth, 1);
                [$nextYear, $nextMonth] = self::jalaliMonthAfter($targetYear, $targetMonth, 1);
                $nextMonthStart = self::jalaliDate($nextYear, $nextMonth, 1);
                $lastDay = (int) $monthStart->diff($nextMonthStart)->format('%a');
                $days = $type === 'jalali_month_end'
                    ? [$lastDay]
                    : ($schedule['days'] ?? null);
                if (!is_array($days) || !$days) {
                    throw new InvalidArgumentException('merchant_settlement_month_days_invalid');
                }
                foreach ($days as $day) {
                    if (!is_int($day) || $day < 1 || $day > 31) {
                        throw new InvalidArgumentException('merchant_settlement_month_days_invalid');
                    }
                    if ($day > $lastDay) {
                        if (($schedule['short_month_behavior'] ?? null) !== 'clamp_to_month_end') {
                            continue;
                        }
                        $day = $lastDay;
                    }
                    $candidates[] = self::jalaliDate($targetYear, $targetMonth, $day);
                }
            }
        } else {
            throw new InvalidArgumentException('merchant_settlement_schedule_invalid');
        }

        $shifted = [];
        foreach ($candidates as $candidate) {
            $candidate = self::shift($candidate, $schedule, $holidaySet);
            if ($candidate >= $after) {
                $shifted[$candidate->format('Y-m-d')] = $candidate;
            }
        }
        if (!$shifted && ($type === 'jalali_month_end' || $type === 'fixed_jalali_days')) {
            return self::nextDueDate($schedule, $anchorDate, $after->modify('+1 day')->format('Y-m-d'), $holidays);
        }
        if (!$shifted) {
            throw new RuntimeException('merchant_settlement_due_date_unavailable');
        }
        ksort($shifted, SORT_STRING);
        return reset($shifted)->format('Y-m-d');
    }

    private static function shift(DateTimeImmutable $date, array $schedule, array $holidays): DateTimeImmutable
    {
        $policy = $schedule['holiday_policy'] ?? 'no_shift';
        if ($policy === 'no_shift') {
            return $date;
        }
        $weekdays = $schedule['business_weekdays_iso'] ?? [];
        if (!is_array($weekdays) || !$weekdays) {
            throw new InvalidArgumentException('merchant_settlement_business_days_required');
        }
        $step = $policy === 'previous_business_day' ? -1 : 1;
        for ($i = 0; $i < 32; $i++) {
            $key = $date->format('Y-m-d');
            if (in_array((int) $date->format('N'), $weekdays, true) && !isset($holidays[$key])) {
                return $date;
            }
            $date = $date->modify(($step > 0 ? '+' : '') . $step . ' days');
        }
        throw new RuntimeException('merchant_settlement_calendar_has_no_business_day');
    }

    private static function jalaliDate(int $year, int $month, int $day): DateTimeImmutable
    {
        [$gy, $gm, $gd] = jalali_to_gregorian($year, $month, $day);
        $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        return self::date($date);
    }

    private static function jalaliMonthAfter(int $year, int $month, int $offset): array
    {
        $index = ($year * 12) + ($month - 1) + $offset;
        return [intdiv($index, 12), ($index % 12) + 1];
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('merchant_settlement_date_invalid');
        }
        return $date;
    }
}