<?php

/** Deterministic quote math for externally originated installment offers. */
final class InstallmentFinancialCalculator
{
    public const CALCULATION_VERSION = 'proma-installment-offer-v1';
    public const CURRENCY_UNIT = 'toman';
    public const ROUNDING_POLICY = 'input_amount_half_up_to_toman; positive_interest_ceil_to_toman; final_installment_absorbs_remainder';

    public static function calculate(array $input): array
    {
        $cashPrice = self::parseAmount($input['cash_price'] ?? null, 'قیمت نقدی');
        $downPayment = self::parseAmount($input['down_payment'] ?? 0, 'پیش‌پرداخت');
        $months = self::parseMonths($input['months'] ?? null);
        $rateUnits = self::parseRate($input['monthly_interest_rate'] ?? null);
        $method = self::parseMethod($input['interest_method'] ?? null);

        if ($cashPrice <= 0) {
            throw new InvalidArgumentException('قیمت نقدی باید بیشتر از صفر باشد.');
        }
        if ($downPayment > $cashPrice) {
            throw new InvalidArgumentException('پیش‌پرداخت نمی‌تواند از قیمت نقدی بیشتر باشد.');
        }

        $financedPrincipal = $cashPrice - $downPayment;
        if ($financedPrincipal <= 0) {
            throw new InvalidArgumentException('مبلغ تأمین‌شده باید بیشتر از صفر باشد.');
        }
        if ($method === 'reducing_balance' && $financedPrincipal < $months) {
            throw new InvalidArgumentException('برای روش مانده‌کاهشی، مبلغ تأمین‌شده باید دست‌کم به تعداد اقساط تومان باشد.');
        }

        if ($method === 'flat') {
            $interest = self::simpleInterest($financedPrincipal, $rateUnits, $months);
            $schedule = self::levelSchedule($financedPrincipal, $interest, $months);
        } elseif ($method === 'compound') {
            $interest = self::compoundInterest($financedPrincipal, $rateUnits, $months);
            $schedule = self::levelSchedule($financedPrincipal, $interest, $months);
        } else {
            [$interest, $schedule] = self::reducingBalanceSchedule($financedPrincipal, $rateUnits, $months);
        }

        $totalInstallments = self::add($financedPrincipal, $interest);
        $scheduledTotal = 0;
        foreach ($schedule as $installment) {
            $scheduledTotal = self::add($scheduledTotal, $installment['amount']);
        }
        if ($scheduledTotal !== $totalInstallments) {
            throw new LogicException('جمع برنامه اقساط با تعهد محاسبه‌شده برابر نیست.');
        }

        $snapshot = [
            'calculation_version' => self::CALCULATION_VERSION,
            'currency_unit' => self::CURRENCY_UNIT,
            'rounding_policy' => self::ROUNDING_POLICY,
            'cash_price' => $cashPrice,
            'down_payment' => $downPayment,
            'financed_principal' => $financedPrincipal,
            'interest_method' => $method,
            'calculation_variant' => $method === 'reducing_balance' ? 'equal_principal' : $method,
            'monthly_interest_rate' => MoneyMath::rateLabel($rateUnits),
            'monthly_interest_rate_units' => $rateUnits,
            'months' => $months,
            'total_interest' => $interest,
            'total_installments' => $totalInstallments,
            'total_customer_payable' => self::add($downPayment, $totalInstallments),
            'installment_schedule' => $schedule,
        ];

        return $snapshot + ['calculation_snapshot' => $snapshot];
    }

    private static function simpleInterest(int $principal, int $rateUnits, int $months): int
    {
        $factor = $rateUnits * $months;
        $interest = MoneyMath::ceilMulDiv($principal, $factor, MoneyMath::PERCENT_DENOMINATOR);
        if ($interest === PHP_INT_MAX) {
            throw new OverflowException('مبلغ سود از دامنه عدد صحیح پشتیبانی‌شده بیشتر است.');
        }
        return $interest;
    }

    private static function compoundInterest(int $principal, int $rateUnits, int $months): int
    {
        $balance = $principal;
        for ($period = 0; $period < $months; $period++) {
            $next = MoneyMath::applyMonthlyRate($balance, $rateUnits);
            if ($next === PHP_INT_MAX) {
                throw new OverflowException('مبلغ مرکب از دامنه عدد صحیح پشتیبانی‌شده بیشتر است.');
            }
            $balance = $next;
        }
        return $balance - $principal;
    }

    private static function levelSchedule(int $principal, int $interest, int $months): array
    {
        $principalParts = self::allocateToFinalInstallment($principal, $months);
        $interestParts = self::allocateToFinalInstallment($interest, $months);
        $remainingPrincipal = $principal;
        $schedule = [];

        for ($index = 0; $index < $months; $index++) {
            $principalPart = $principalParts[$index];
            $interestPart = $interestParts[$index];
            $remainingPrincipal -= $principalPart;
            $schedule[] = [
                'installment_number' => $index + 1,
                'principal_amount' => $principalPart,
                'interest_amount' => $interestPart,
                'amount' => self::add($principalPart, $interestPart),
                'remaining_principal' => $remainingPrincipal,
            ];
        }

        return $schedule;
    }

    private static function reducingBalanceSchedule(int $principal, int $rateUnits, int $months): array
    {
        $principalParts = self::allocateToFinalInstallment($principal, $months);
        $remainingPrincipal = $principal;
        $totalInterest = 0;
        $schedule = [];

        foreach ($principalParts as $index => $principalPart) {
            $interest = MoneyMath::ceilMulDiv($remainingPrincipal, $rateUnits, MoneyMath::PERCENT_DENOMINATOR);
            if ($interest === PHP_INT_MAX) {
                throw new OverflowException('مبلغ سود دوره‌ای از دامنه عدد صحیح پشتیبانی‌شده بیشتر است.');
            }
            $totalInterest = self::add($totalInterest, $interest);
            $remainingPrincipal -= $principalPart;
            $schedule[] = [
                'installment_number' => $index + 1,
                'principal_amount' => $principalPart,
                'interest_amount' => $interest,
                'amount' => self::add($principalPart, $interest),
                'remaining_principal' => $remainingPrincipal,
            ];
        }

        return [$totalInterest, $schedule];
    }

    /** Keep early installments level and place indivisible Toman remainder in the last one. */
    private static function allocateToFinalInstallment(int $amount, int $months): array
    {
        $regular = intdiv($amount, $months);
        $parts = array_fill(0, $months, $regular);
        $parts[$months - 1] = $amount - ($regular * ($months - 1));
        return $parts;
    }

    private static function parseAmount($value, string $label): int
    {
        $raw = trim(to_english_digits((string) $value));
        $raw = str_replace(['تومان', 'ریال', ',', '،', '٬', ' ', "\xC2\xA0"], '', $raw);
        if (!preg_match('/^\+?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException($label . ' معتبر نیست.');
        }
        $amount = MoneyMath::amount($raw);
        if ($amount === PHP_INT_MAX) {
            throw new OverflowException($label . ' از دامنه عدد صحیح پشتیبانی‌شده بیشتر است.');
        }
        return $amount;
    }

    private static function parseMonths($value): int
    {
        $raw = trim(to_english_digits((string) $value));
        if (!preg_match('/^\d+$/', $raw)) {
            throw new InvalidArgumentException('تعداد اقساط باید عدد صحیح باشد.');
        }
        $months = (int) $raw;
        if ($months < 1 || $months > 480) {
            throw new InvalidArgumentException('تعداد اقساط باید بین ۱ تا ۴۸۰ باشد.');
        }
        return $months;
    }

    private static function parseRate($value): int
    {
        $raw = trim(to_english_digits((string) $value));
        $raw = str_replace(['٪', '%', 'درصد', ',', '،', '٬', ' ', "\xC2\xA0"], '', $raw);
        if (!preg_match('/^\+?(\d+)(?:\.(\d{0,4}))?$/', $raw, $matches)) {
            throw new InvalidArgumentException('نرخ ماهانه معتبر نیست.');
        }
        $wholeText = ltrim($matches[1], '0');
        $wholeText = $wholeText === '' ? '0' : $wholeText;
        if (strlen($wholeText) > 3 || (int) $wholeText > 100) {
            throw new InvalidArgumentException('نرخ ماهانه نمی‌تواند از ۱۰۰ درصد بیشتر باشد.');
        }
        if ((int) $wholeText === 100 && (int) ($matches[2] ?? '0') !== 0) {
            throw new InvalidArgumentException('نرخ ماهانه نمی‌تواند از ۱۰۰ درصد بیشتر باشد.');
        }
        return MoneyMath::rateUnits($raw);
    }

    private static function parseMethod($value): string
    {
        $method = strtolower(trim((string) $value));
        if ($method === 'simple') {
            $method = 'flat';
        }
        if (!in_array($method, ['flat', 'compound', 'reducing_balance'], true)) {
            throw new InvalidArgumentException('روش محاسبه سود معتبر نیست.');
        }
        return $method;
    }

    private static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new OverflowException('مبلغ از دامنه عدد صحیح پشتیبانی‌شده بیشتر است.');
        }
        return $left + $right;
    }
}