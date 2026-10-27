<?php

declare(strict_types=1);

/** Converts WooCommerce totals exactly and validates live Proma Pay quotes. */
final class PromaPay_WC_Quote_Service
{
    private PromaPay_WC_Connection_Repository $repository;
    private PromaPay_WC_Api_Client $client;

    public function __construct(PromaPay_WC_Connection_Repository $repository, PromaPay_WC_Api_Client $client)
    {
        $this->repository = $repository;
        $this->client = $client;
    }

    public static function to_toman($amount, int $currencyDivisor, int $decimals): int
    {
        if (!in_array($currencyDivisor, [1, 10], true) || $decimals < 0 || $decimals > 4) {
            throw new InvalidArgumentException('promapaywc_currency_conversion_invalid');
        }
        $raw = trim((string) $amount);
        if (!preg_match('/\A(\d{1,15})(?:\.(\d{1,4}))?\z/D', $raw, $match)) {
            throw new InvalidArgumentException('promapaywc_currency_amount_invalid');
        }
        $fraction = $match[2] ?? '';
        if (strlen($fraction) > $decimals) {
            if (trim(substr($fraction, $decimals), '0') !== '') {
                throw new InvalidArgumentException('promapaywc_currency_precision_invalid');
            }
            $fraction = substr($fraction, 0, $decimals);
        }
        $scale = 10 ** $decimals;
        $fractionalUnits = $decimals > 0 ? (int) str_pad($fraction, $decimals, '0') : 0;
        $whole = (int) $match[1];
        if ($whole > intdiv(PHP_INT_MAX - $fractionalUnits, $scale)) {
            throw new OverflowException('promapaywc_currency_amount_overflow');
        }
        $minorUnits = ($whole * $scale) + $fractionalUnits;
        $denominator = $scale * $currencyDivisor;
        if ($minorUnits < 1 || $minorUnits % $denominator !== 0) {
            throw new InvalidArgumentException('promapaywc_currency_amount_not_whole_toman');
        }
        return intdiv($minorUnits, $denominator);
    }

    public static function from_toman(int $amountToman, int $currencyDivisor, int $decimals): string
    {
        if ($amountToman < 1 || !in_array($currencyDivisor, [1, 10], true) || $decimals < 0 || $decimals > 4) {
            throw new InvalidArgumentException('promapaywc_currency_conversion_invalid');
        }
        $scale = 10 ** $decimals;
        if ($amountToman > intdiv(PHP_INT_MAX, $currencyDivisor)
            || ($amountToman * $currencyDivisor) > intdiv(PHP_INT_MAX, $scale)) {
            throw new OverflowException('promapaywc_currency_amount_overflow');
        }
        $minorUnits = $amountToman * $currencyDivisor * $scale;
        $whole = intdiv($minorUnits, $scale);
        if ($decimals === 0) {
            return (string) $whole;
        }
        return $whole . '.' . str_pad((string) ($minorUnits % $scale), $decimals, '0', STR_PAD_LEFT);
    }

    public function quote(string $connectionUuid, int $cashPriceToman, int $downPaymentToman, int $months, string $method): array
    {
        $connection = $this->repository->get($connectionUuid);
        if ($connection === null || (int) ($connection['enabled'] ?? 0) !== 1
            || PromaPay_WC_Payment_Adapter::gateway((string) ($connection['prepayment_gateway_id'] ?? '')) === null) {
            throw new RuntimeException('promapaywc_connection_unavailable');
        }
        $policyResult = (new PromaPay_WC_Policy_Service($this->repository, $this->client))->current($connectionUuid);
        $policy = $policyResult['policy'] ?? null;
        if (empty($policyResult['ok']) || !PromaPay_WC_Policy_Service::valid_policy($policy)) {
            throw new RuntimeException((string) ($policyResult['error_code'] ?? 'promapaywc_policy_unavailable'));
        }
        $limits = $policy['document']['limits'] ?? [];
        $minimum = max(
            (int) ($limits['min_down_payment_toman'] ?? 0),
            self::ceil_percent($cashPriceToman, (int) ($limits['min_down_payment_bps'] ?? 0))
        );
        $maximumBps = (int) ($limits['max_down_payment_bps'] ?? 10000);
        $maximum = $cashPriceToman - self::ceil_percent($cashPriceToman, 10000 - $maximumBps);
        $minMonths = (int) ($limits['min_months'] ?? 1);
        $maxMonths = (int) ($limits['max_months'] ?? 480);
        if ($cashPriceToman < (int) ($limits['min_order_toman'] ?? 1)
            || $cashPriceToman > (int) ($limits['max_order_toman'] ?? PHP_INT_MAX)
            || $downPaymentToman < $minimum || $downPaymentToman > $maximum
            || $months < $minMonths || $months > $maxMonths
            || !in_array($method, $policy['document']['methods'], true)) {
            throw new InvalidArgumentException('promapaywc_checkout_policy_violation');
        }
        $response = $this->client->request_json($connection, 'POST', '/quotes', [
            'cash_price_toman' => $cashPriceToman,
            'down_payment_toman' => $downPaymentToman,
            'months' => $months,
            'interest_method' => $method,
        ]);
        if (!$response['ok']) {
            throw new RuntimeException((string) $response['error_code']);
        }
        $quote = $response['data']['quote'] ?? null;
        if (!self::valid_checkout_quote($quote, $cashPriceToman, $downPaymentToman, $months, $method)) {
            throw new RuntimeException('promapaywc_quote_invalid');
        }
        return $quote;
    }

    public static function valid_checkout_quote($quote, int $cashPriceToman, int $downPaymentToman, int $months, string $method): bool
    {
        if (!is_array($quote) || !PromaPay_WC_Connection_Repository::valid_uuid((string) ($quote['quote_uuid'] ?? ''))
            || !PromaPay_WC_Connection_Repository::valid_uuid((string) ($quote['policy_version_uuid'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($quote['policy_sha256'] ?? ''))
            || !is_array($quote['calculation'] ?? null) || !is_array($quote['guarantee'] ?? null)) {
            return false;
        }
        try {
            $expires = new DateTimeImmutable((string) ($quote['expires_at'] ?? ''));
        } catch (Throwable $error) {
            return false;
        }
        if ($expires <= new DateTimeImmutable('now') || $expires > new DateTimeImmutable('+16 minutes')) {
            return false;
        }
        $calculation = $quote['calculation'];
        if (($calculation['currency_unit'] ?? '') !== 'toman'
            || ($calculation['cash_price'] ?? null) !== $cashPriceToman
            || ($calculation['down_payment'] ?? null) !== $downPaymentToman
            || ($calculation['months'] ?? null) !== $months
            || ($calculation['interest_method'] ?? '') !== $method
            || !is_int($calculation['total_installments'] ?? null)
            || !is_int($calculation['total_customer_payable'] ?? null)
            || !is_array($calculation['installment_schedule'] ?? null)
            || count($calculation['installment_schedule']) !== $months) {
            return false;
        }
        $scheduleSum = 0;
        $lastDate = '';
        foreach ($calculation['installment_schedule'] as $index => $installment) {
            if (!is_array($installment) || ($installment['installment_number'] ?? null) !== $index + 1
                || !is_int($installment['amount'] ?? null) || $installment['amount'] < 1
                || !self::valid_date((string) ($installment['due_date'] ?? ''))
                || ($lastDate !== '' && (string) $installment['due_date'] <= $lastDate)
                || $scheduleSum > PHP_INT_MAX - $installment['amount']) {
                return false;
            }
            $scheduleSum += $installment['amount'];
            $lastDate = (string) $installment['due_date'];
        }
        $firstDate = (string) ($calculation['first_installment_due_date'] ?? '');
        $guarantee = $quote['guarantee'];
        $document = $guarantee['document'] ?? [];
        $guaranteeCalculation = $guarantee['calculation'] ?? [];
        if ($downPaymentToman > PHP_INT_MAX - $scheduleSum
            || ($downPaymentToman + $scheduleSum) !== $calculation['total_customer_payable']) {
            return false;
        }
        return $scheduleSum === $calculation['total_installments']
            && self::valid_date($firstDate)
            && $firstDate === (string) ($calculation['installment_schedule'][0]['due_date'] ?? '')
            && self::valid_guarantee($guarantee, $calculation['total_installments'], $firstDate);
    }

    private static function valid_guarantee(array $guarantee, int $scheduledToman, string $firstDate): bool
    {
        if (!PromaPay_WC_Connection_Repository::valid_uuid((string) ($guarantee['policy_version_uuid'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($guarantee['source_policy_sha256'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($guarantee['policy_sha256'] ?? ''))
            || !is_array($guarantee['document'] ?? null) || !is_array($guarantee['calculation'] ?? null)) {
            return false;
        }
        $document = $guarantee['document'];
        $allowedDocumentKeys = [
            'guarantee_type','required_cheque_count','guarantee_base','multiplier_units','multiplier_scale',
            'central_minimum_multiplier_units','central_maximum_multiplier_units','beneficiary_legal_name',
            'beneficiary_identifier','central_policy_uuid','central_policy_version_no',
        ];
        if (array_diff(array_keys($document), $allowedDocumentKeys) !== []
            || ($document['guarantee_type'] ?? '') !== 'sayadi_cheque'
            || ($document['required_cheque_count'] ?? null) !== 1
            || ($document['guarantee_base'] ?? '') !== 'scheduled_installments'
            || ($document['multiplier_scale'] ?? null) !== 10000
            || !is_int($document['multiplier_units'] ?? null) || $document['multiplier_units'] < 1
            || !is_int($document['central_minimum_multiplier_units'] ?? null)
            || !is_int($document['central_maximum_multiplier_units'] ?? null)
            || $document['multiplier_units'] < $document['central_minimum_multiplier_units']
            || $document['multiplier_units'] > $document['central_maximum_multiplier_units']
            || !is_string($document['beneficiary_legal_name'] ?? null) || trim($document['beneficiary_legal_name']) === ''
            || strlen($document['beneficiary_legal_name']) > 760
            || !is_string($document['beneficiary_identifier'] ?? null)
            || !preg_match('/\A\d{5,20}\z/D', $document['beneficiary_identifier'])
            || !PromaPay_WC_Connection_Repository::valid_uuid((string) ($document['central_policy_uuid'] ?? ''))
            || !is_int($document['central_policy_version_no'] ?? null) || $document['central_policy_version_no'] < 1) {
            return false;
        }
        try {
            $canonical = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return false;
        }
        if (!hash_equals((string) $guarantee['policy_sha256'], hash('sha256', $canonical))) {
            return false;
        }
        $calculation = $guarantee['calculation'];
        $allowedCalculationKeys = [
            'guarantee_type','required_cheque_count','guarantee_base','scheduled_installments_toman',
            'multiplier_units','multiplier_scale','cheque_amount_rial','first_installment_due_date',
            'cheque_due_date','rounding_policy',
        ];
        if (array_diff(array_keys($calculation), $allowedCalculationKeys) !== []
            || ($calculation['guarantee_type'] ?? '') !== 'sayadi_cheque'
            || ($calculation['required_cheque_count'] ?? null) !== 1
            || ($calculation['guarantee_base'] ?? '') !== 'scheduled_installments'
            || ($calculation['scheduled_installments_toman'] ?? null) !== $scheduledToman
            || ($calculation['multiplier_units'] ?? null) !== $document['multiplier_units']
            || ($calculation['multiplier_scale'] ?? null) !== 10000
            || ($calculation['first_installment_due_date'] ?? '') !== $firstDate
            || ($calculation['cheque_due_date'] ?? '') !== $firstDate
            || ($calculation['rounding_policy'] ?? '') !== 'nearest_rial_half_up'
            || !is_int($calculation['cheque_amount_rial'] ?? null)) {
            return false;
        }
        if ($scheduledToman > intdiv(PHP_INT_MAX, 10)) {
            return false;
        }
        $baseRial = $scheduledToman * 10;
        if ($baseRial > intdiv(PHP_INT_MAX, $document['multiplier_units'])) {
            return false;
        }
        $scaledAmount = $baseRial * $document['multiplier_units'];
        $expected = intdiv($scaledAmount, 10000);
        if (($scaledAmount % 10000) >= 5000) {
            $expected++;
        }
        return $expected > 0 && $calculation['cheque_amount_rial'] === $expected;
    }

    private static function ceil_percent(int $amount, int $basisPoints): int
    {
        if ($amount < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('promapaywc_policy_invalid');
        }
        $whole = intdiv($amount, 10000);
        $remainder = $amount % 10000;
        if ($whole > intdiv(PHP_INT_MAX, max(1, $basisPoints))) {
            throw new OverflowException('promapaywc_policy_amount_overflow');
        }
        $base = $whole * $basisPoints;
        $part = $basisPoints === 0 ? 0 : intdiv(($remainder * $basisPoints) + 9999, 10000);
        return $base + $part;
    }

    private static function valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date instanceof DateTimeImmutable
            && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
