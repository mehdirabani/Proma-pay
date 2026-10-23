<?php

declare(strict_types=1);

/** Exact integer calculator for the WooCommerce Sayadi cheque guarantee. */
final class MerchantGuaranteeCalculator
{
    public const MULTIPLIER_SCALE = 10000;

    public static function parseMultiplierUnits($value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('merchant_cheque_multiplier_invalid');
        }
        $raw = self::englishDigits(trim((string) $value));
        if (!preg_match('/^(\d{1,6})(?:\.(\d{1,4}))?$/D', $raw, $matches)) {
            throw new InvalidArgumentException('merchant_cheque_multiplier_invalid');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad((string) ($matches[2] ?? ''), 4, '0');
        $units = ($whole * self::MULTIPLIER_SCALE) + (int) $fraction;
        if ($units < 1) {
            throw new InvalidArgumentException('merchant_cheque_multiplier_invalid');
        }
        return $units;
    }

    /** @param array<int,array{installment_number:int|string,due_date:string,amount:string|int}> $schedule */
    public static function calculate(array $schedule, int $multiplierUnits): array
    {
        if (!$schedule || $multiplierUnits < 1) {
            throw new InvalidArgumentException('merchant_cheque_calculation_invalid');
        }

        $normalized = [];
        $seenNumbers = [];
        foreach ($schedule as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('merchant_installment_schedule_invalid');
            }
            $number = self::parseInstallmentNumber($row['installment_number'] ?? null);
            if (isset($seenNumbers[$number])) {
                throw new InvalidArgumentException('merchant_installment_schedule_invalid');
            }
            $seenNumbers[$number] = true;
            $normalized[] = [
                'installment_number' => $number,
                'due_date' => self::normalizeDate((string) ($row['due_date'] ?? '')),
                'amount_toman' => self::parseWholeToman($row['amount'] ?? null),
            ];
        }
        usort($normalized, static function (array $left, array $right): int {
            return $left['installment_number'] <=> $right['installment_number'];
        });

        $totalToman = 0;
        foreach ($normalized as $row) {
            if ($row['amount_toman'] < 1 || $totalToman > PHP_INT_MAX - $row['amount_toman']) {
                throw new OverflowException('merchant_installment_total_overflow');
            }
            $totalToman += $row['amount_toman'];
        }
        if ($totalToman > intdiv(PHP_INT_MAX, 10)) {
            throw new OverflowException('merchant_cheque_amount_overflow');
        }

        $baseRial = $totalToman * 10;
        if ($baseRial > intdiv(PHP_INT_MAX, $multiplierUnits)) {
            throw new OverflowException('merchant_cheque_amount_overflow');
        }
        $scaledAmount = $baseRial * $multiplierUnits;
        $amountRial = intdiv($scaledAmount, self::MULTIPLIER_SCALE);
        $remainder = $scaledAmount % self::MULTIPLIER_SCALE;
        if ($remainder >= intdiv(self::MULTIPLIER_SCALE + 1, 2)) {
            if ($amountRial === PHP_INT_MAX) {
                throw new OverflowException('merchant_cheque_amount_overflow');
            }
            $amountRial++;
        }
        if ($amountRial < 1) {
            throw new InvalidArgumentException('merchant_cheque_amount_invalid');
        }

        return [
            'guarantee_type' => 'sayadi_cheque',
            'required_cheque_count' => 1,
            'guarantee_base' => 'scheduled_installments',
            'scheduled_installments_toman' => $totalToman,
            'multiplier_units' => $multiplierUnits,
            'multiplier_scale' => self::MULTIPLIER_SCALE,
            'cheque_amount_rial' => $amountRial,
            'first_installment_due_date' => $normalized[0]['due_date'],
            'cheque_due_date' => $normalized[0]['due_date'],
            'rounding_policy' => 'nearest_rial_half_up',
        ];
    }

    private static function parseWholeToman($value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('merchant_installment_amount_invalid');
        }
        $raw = self::englishDigits(trim((string) $value));
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $raw, $matches)
            || trim((string) ($matches[2] ?? ''), '0') !== '') {
            throw new InvalidArgumentException('merchant_installment_amount_must_be_whole_toman');
        }
        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $maximum = (string) PHP_INT_MAX;
        if (strlen($whole) > strlen($maximum)
            || (strlen($whole) === strlen($maximum) && strcmp($whole, $maximum) > 0)) {
            throw new OverflowException('merchant_installment_amount_overflow');
        }
        return (int) $whole;
    }

    private static function parseInstallmentNumber($value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('merchant_installment_schedule_invalid');
        }
        $raw = trim((string) $value);
        if (!preg_match('/^[1-9]\d{0,8}$/D', $raw)) {
            throw new InvalidArgumentException('merchant_installment_schedule_invalid');
        }
        return (int) $raw;
    }

    private static function normalizeDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('merchant_installment_due_date_invalid');
        }
        return $value;
    }

    private static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
