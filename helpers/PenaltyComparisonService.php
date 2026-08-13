<?php

/**
 * Builds a non-financial comparison of the current normal penalty with the
 * legal rate.  The result is deliberately analytical: it is never an input
 * to a quote, payment allocation, receipt, ledger, or customer debt.
 */
final class PenaltyComparisonService
{
    public static function projectedLegalPenalty(array $installment, array $payments, array $settings, string $asOf, ?string $actualLegalReferralAt): array
    {
        $legalRate = MoneyMath::rateUnits($settings['legal_monthly_penalty_rate'] ?? 0);
        $base = normalize_money($installment['base_amount'] ?? 0);
        $dueDate = self::date($installment['due_date'] ?? $asOf);
        $graceDays = max(0, min(365, (int) to_english_digits($settings['late_penalty_grace_days'] ?? 0)));
        $graceEnd = self::addDays($dueDate, $graceDays);

        // An actual, persisted referral starts the real legal calculation in
        // the financial engine.  It must never be duplicated as a projection.
        if ($actualLegalReferralAt !== null || $legalRate <= 0 || $base <= 0 || $asOf <= $graceEnd) {
            return [
                'projected_legal_penalty' => 0,
                'projected_legal_penalty_available' => false,
                'projected_legal_penalty_rate' => MoneyMath::rateLabel($legalRate),
                'projected_legal_penalty_reason' => $actualLegalReferralAt !== null ? 'actual_referral' : ($legalRate <= 0 ? 'missing_rate' : 'not_overdue'),
            ];
        }

        usort($payments, static function (array $left, array $right): int {
            $leftDate = (string) ($left['payment_date'] ?? $left['paid_at'] ?? $left['created_at'] ?? '');
            $rightDate = (string) ($right['payment_date'] ?? $right['paid_at'] ?? $right['created_at'] ?? '');
            $compare = strcmp($leftDate, $rightDate);
            return $compare !== 0 ? $compare : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        $principal = $base;
        $penalty = 0;
        $cursor = $dueDate;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid'
                || (int) ($payment['is_corrected'] ?? 0) === 1
                || ($payment['payment_type'] ?? 'installment') === 'down_payment') {
                continue;
            }
            $paymentDate = self::date(substr((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? $asOf), 0, 10));
            if ($paymentDate > $asOf) {
                continue;
            }
            self::accrue($penalty, $principal, $cursor, $paymentDate, $dueDate, $graceEnd, $legalRate);

            // Replay only the received amount as an analytical, legal-rate
            // alternative.  Persisted real allocations remain untouched.
            $received = normalize_money($payment['amount'] ?? 0);
            $paidPenalty = min($penalty, $received);
            $penalty -= $paidPenalty;
            $principal = max(0, $principal - max(0, $received - $paidPenalty));
            if ($paymentDate > $graceEnd) {
                $cursor = max($cursor, $paymentDate);
            }
        }
        self::accrue($penalty, $principal, $cursor, $asOf, $dueDate, $graceEnd, $legalRate);

        return [
            'projected_legal_penalty' => max(0, $penalty),
            'projected_legal_penalty_available' => $principal > 0 && $penalty > 0,
            'projected_legal_penalty_rate' => MoneyMath::rateLabel($legalRate),
            'projected_legal_penalty_reason' => $principal > 0 ? 'comparison_only' : 'settled',
        ];
    }

    private static function accrue(int &$penalty, int $principal, string $cursor, string $target, string $dueDate, string $graceEnd, int $legalRate): void
    {
        if ($principal <= 0 || $target <= $cursor || $target <= $graceEnd) {
            return;
        }
        $from = $cursor === $dueDate && $target > $graceEnd ? $dueDate : $cursor;
        if ($target > $from) {
            $penalty += MoneyMath::rateForDays($principal, $legalRate, self::daysBetween($from, $target));
        }
    }

    private static function date($value): string
    {
        $value = substr(trim((string) $value), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }

    private static function addDays(string $date, int $days): string
    {
        $dateTime = new DateTime($date);
        if ($days > 0) {
            $dateTime->modify('+' . $days . ' day');
        }
        return $dateTime->format('Y-m-d');
    }

    private static function daysBetween(string $from, string $to): int
    {
        return max(0, (int) ((strtotime($to) - strtotime($from)) / 86400));
    }
}
