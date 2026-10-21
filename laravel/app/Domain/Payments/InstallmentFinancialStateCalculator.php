<?php

namespace App\Domain\Payments;

use App\Support\PersianDigits;

/**
 * Versioned integer-only port of the payable financial state used by Legacy
 * settlement. The caller supplies immutable payment history, legal-referral
 * facts, and effective settings; materialized display balances are ignored.
 */
final class InstallmentFinancialStateCalculator
{
    public const CALCULATION_VERSION = 'installment-financial-state-v4-due-date-legal';

    /**
     * @param  array<string, mixed>  $installment
     * @param  array<int, array<string, mixed>>  $payments
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function state(array $installment, array $payments, array $settings, ?string $asOf = null): array
    {
        $asOf = $this->date($asOf ?: date('Y-m-d'));
        $base = $this->money($installment['base_amount'] ?? $installment['amount_toman'] ?? 0);
        $id = (int) ($installment['id'] ?? 0);
        $contractId = (int) ($installment['contract_id'] ?? 0);
        $dueDate = $this->date($installment['due_date'] ?? $asOf);

        if (($installment['status'] ?? '') === 'cancelled' || ($installment['contract_status'] ?? '') === 'cancelled') {
            return $this->closedState($id, $contractId, $base, $dueDate, 'cancelled');
        }

        $normalRate = MoneyMath::rateUnits($settings['monthly_penalty_rate'] ?? 0);
        $legalRate = MoneyMath::rateUnits($settings['legal_monthly_penalty_rate'] ?? 0);
        $rewardRate = MoneyMath::rateUnits($settings['monthly_reward_rate'] ?? 0);
        $graceDays = max(0, min(365, $this->integer($settings['late_penalty_grace_days'] ?? 0)));
        $graceEnd = $this->addDays($dueDate, $graceDays);
        $legalStartedAt = $this->dateOrNull($installment['legal_started_at'] ?? null);
        $calculationStatus = 'calculated';
        $warnings = [];

        if ((int) ($installment['inconsistent_legal_referral_count'] ?? 0) > 0) {
            $calculationStatus = 'inconsistent_referral';
            $warnings[] = 'برای پرونده حقوقی فعال، تاریخ ارجاع رسمی ثبت نشده است.';
        } elseif ($legalStartedAt && $asOf >= $legalStartedAt && $base > 0 && $legalRate <= 0) {
            $calculationStatus = 'configuration_missing';
            $warnings[] = 'نرخ جریمه حقوقی برای ارجاع رسمی تنظیم نشده است.';
        }

        usort($payments, function (array $left, array $right): int {
            $leftDate = (string) ($left['payment_date'] ?? $left['paid_at'] ?? $left['created_at'] ?? '');
            $rightDate = (string) ($right['payment_date'] ?? $right['paid_at'] ?? $right['created_at'] ?? '');
            $compare = strcmp($leftDate, $rightDate);

            return $compare !== 0 ? $compare : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        $remainingPrincipal = $base;
        $normalPenalty = $this->money($installment['manual_penalty_adjustment'] ?? 0);
        $legalPenalty = 0;
        $discount = $this->money($installment['penalty_discount_amount'] ?? 0);
        $normalPenalty = max(0, $normalPenalty - $discount);
        $penaltyCursor = $dueDate;
        $settlementDate = null;
        $effectivePayments = [];

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid'
                || (int) ($payment['is_corrected'] ?? 0) === 1
                || ($payment['payment_type'] ?? 'installment') === 'down_payment') {
                continue;
            }

            $paymentDate = $this->date(substr((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? $asOf), 0, 10));
            if ($paymentDate > $asOf) {
                continue;
            }

            $this->accrueTo($normalPenalty, $legalPenalty, $remainingPrincipal, $penaltyCursor, $paymentDate, $dueDate, $graceEnd, $normalRate, $legalRate, $legalStartedAt);
            $allocation = $this->paymentAllocation($payment, $remainingPrincipal, $normalPenalty, $legalPenalty);
            $legalPenalty = max(0, $legalPenalty - $allocation['legal_penalty_applied']);
            $normalPenalty = max(0, $normalPenalty - $allocation['normal_penalty_applied']);
            $remainingPrincipal = max(0, $remainingPrincipal - $allocation['principal_applied']);
            if ($paymentDate > $graceEnd) {
                $penaltyCursor = max($penaltyCursor, $paymentDate);
            }

            $effectivePayments[] = $payment;
            if ($remainingPrincipal === 0 && $normalPenalty === 0 && $legalPenalty === 0) {
                $settlementDate = $paymentDate;
            }
        }

        $this->accrueTo($normalPenalty, $legalPenalty, $remainingPrincipal, $penaltyCursor, $asOf, $dueDate, $graceEnd, $normalRate, $legalRate, $legalStartedAt);
        $reward = $this->eligibleReward($installment, $remainingPrincipal, $rewardRate, $asOf, $dueDate);
        $totalPenalty = $normalPenalty + $legalPenalty;
        $finalPayable = max(0, $remainingPrincipal + $totalPenalty - $reward);
        $paidPrincipal = max(0, $base - $remainingPrincipal);
        $isPaid = $remainingPrincipal === 0 && $totalPenalty === 0;
        $lastPayment = $effectivePayments ? $effectivePayments[count($effectivePayments) - 1] : null;
        $status = $isPaid ? 'paid' : $this->openStatus($installment, $remainingPrincipal, $dueDate, $asOf);
        $contractStatus = (string) ($installment['contract_status'] ?? 'active');
        $installmentLifecycleOpen = ($installment['status'] ?? 'pending') !== 'draft';
        $paymentAllowed = $calculationStatus === 'calculated' && ! $isPaid && $finalPayable > 0
            && $installmentLifecycleOpen
            && ! in_array($contractStatus, ['draft', 'pending_approval', 'approved', 'cancelled', 'closed'], true);

        return [
            'installment_id' => $id,
            'contract_id' => $contractId,
            'status' => $status,
            'financial_status' => $this->financialStatus($installment, $remainingPrincipal, $dueDate, $asOf, $graceEnd, $legalStartedAt),
            'financial_priority' => $this->priority($installment, $remainingPrincipal, $dueDate, $asOf, $graceEnd, $legalStartedAt, $isPaid),
            'due_date' => $dueDate,
            'original_principal' => $base,
            'base_amount' => $base,
            'effective_paid_principal' => $paidPrincipal,
            'paid_amount' => $paidPrincipal,
            'remaining_principal' => $remainingPrincipal,
            'remaining_amount' => $remainingPrincipal,
            'normal_penalty' => $normalPenalty,
            'legal_penalty' => $legalPenalty,
            'total_penalty' => $totalPenalty,
            'penalty' => $totalPenalty,
            'effective_penalty_payable' => $totalPenalty,
            'penalty_mode' => ($legalPenalty > 0 || ($legalStartedAt && $asOf >= $legalStartedAt)) ? 'legal' : 'normal',
            'eligible_reward' => $reward,
            'reward' => $reward,
            'reward_applied' => 0,
            'effective_settlement_date' => $settlementDate ?: $this->storedSettlementDate($installment, $isPaid),
            'last_payment_id' => $lastPayment ? (int) ($lastPayment['id'] ?? 0) : null,
            'last_payment_method' => $lastPayment['method'] ?? null,
            'last_payment_reference' => $lastPayment['gateway_ref_id'] ?? ($lastPayment['gateway_track_id'] ?? null),
            'final_payable' => $finalPayable,
            'payable' => $finalPayable,
            'payment_allowed' => $paymentAllowed,
            'calculated_at' => $asOf,
            'calculation_version' => self::CALCULATION_VERSION,
            'calculation_status' => $calculationStatus,
            'calculation_warnings' => $warnings,
            'grace_days' => $graceDays,
            'legal_started_at' => $legalStartedAt,
            'normal_penalty_rate' => MoneyMath::rateLabel($normalRate),
            'legal_penalty_rate' => MoneyMath::rateLabel($legalRate),
            'overdue_days' => $this->daysBetween($dueDate, $asOf),
            'effective_payment_count' => count($effectivePayments),
        ];
    }

    private function paymentAllocation(array $payment, int $remainingPrincipal, int $normalPenalty, int $legalPenalty): array
    {
        if (array_key_exists('principal_applied', $payment) && $payment['principal_applied'] !== null) {
            return [
                'principal_applied' => min(max(0, $remainingPrincipal), $this->money($payment['principal_applied'] ?? 0)),
                'normal_penalty_applied' => min(max(0, $normalPenalty), $this->money($payment['normal_penalty_applied'] ?? 0)),
                'legal_penalty_applied' => min(max(0, $legalPenalty), $this->money($payment['legal_penalty_applied'] ?? 0)),
                'reward_applied' => $this->money($payment['reward_applied'] ?? $payment['calculated_reward'] ?? 0),
            ];
        }

        $amount = $this->money($payment['amount_toman'] ?? $payment['amount'] ?? 0);
        $principal = array_key_exists('remaining_after_payment', $payment) && $payment['remaining_after_payment'] !== null
            ? min($remainingPrincipal, max(0, $remainingPrincipal - $this->money($payment['remaining_after_payment'])))
            : min($remainingPrincipal, $amount);
        $reward = min($principal, $this->money($payment['calculated_reward'] ?? 0));
        $penaltyBudget = max(0, $amount + $reward - $principal);
        $legal = min($legalPenalty, $penaltyBudget);
        $penaltyBudget -= $legal;
        $normal = min($normalPenalty, $penaltyBudget);

        return [
            'principal_applied' => $principal,
            'normal_penalty_applied' => $normal,
            'legal_penalty_applied' => $legal,
            'reward_applied' => $reward,
        ];
    }

    private function accrueTo(int &$normal, int &$legal, int $principal, string $cursor, string $target, string $dueDate, string $graceEnd, int $normalRate, int $legalRate, ?string $legalStart): void
    {
        if ($principal <= 0 || $target <= $cursor || $target <= $graceEnd) {
            return;
        }

        $from = $cursor === $dueDate && $target > $graceEnd ? $dueDate : $cursor;
        if ($target <= $from) {
            return;
        }

        if (! $legalStart || $target < $legalStart) {
            $normal += MoneyMath::rateForDays($principal, $normalRate, $this->daysBetween($from, $target));

            return;
        }

        $legal += MoneyMath::rateForDays($principal, $legalRate, $this->daysBetween($from, $target));
    }

    private function eligibleReward(array $installment, int $remainingPrincipal, int $rewardRate, string $asOf, string $dueDate): int
    {
        if ($remainingPrincipal <= 0 || $asOf >= $dueDate) {
            return 0;
        }

        $days = max(1, $this->daysBetween($asOf, $dueDate));

        return max(0, MoneyMath::rateForDays($remainingPrincipal, $rewardRate, $days) + $this->money($installment['manual_reward_adjustment'] ?? 0));
    }

    private function openStatus(array $installment, int $remaining, string $dueDate, string $asOf): string
    {
        if ($remaining < $this->money($installment['base_amount'] ?? $installment['amount_toman'] ?? 0)) {
            return 'partial';
        }

        return $asOf > $dueDate ? 'overdue' : 'pending';
    }

    private function financialStatus(array $installment, int $remaining, string $dueDate, string $asOf, string $graceEnd, ?string $legalStartedAt): string
    {
        if (($installment['status'] ?? '') === 'cancelled') {
            return 'cancelled';
        }
        if ($remaining <= 0) {
            return 'paid';
        }
        if ($dueDate < $asOf && $legalStartedAt && $legalStartedAt <= $asOf) {
            return 'legal_overdue';
        }
        if ($dueDate < $asOf && $asOf <= $graceEnd) {
            return 'grace';
        }
        if ($dueDate < $asOf) {
            return 'overdue';
        }
        if ($remaining < $this->money($installment['base_amount'] ?? $installment['amount_toman'] ?? 0)) {
            return 'partial';
        }

        return 'upcoming';
    }

    private function priority(array $installment, int $remaining, string $dueDate, string $asOf, string $graceEnd, ?string $legalStartedAt, bool $isPaid): int
    {
        if (($installment['status'] ?? '') === 'cancelled') {
            return 90;
        }
        if ($isPaid) {
            return 80;
        }
        if ($dueDate < $asOf && $legalStartedAt && $legalStartedAt <= $asOf) {
            return 0;
        }
        if ($dueDate < $asOf && $asOf > $graceEnd) {
            return 1;
        }
        if ($dueDate === $asOf) {
            return 2;
        }
        if ($dueDate < $asOf) {
            return 3;
        }
        if ($remaining < $this->money($installment['base_amount'] ?? $installment['amount_toman'] ?? 0)) {
            return 4;
        }

        return 5;
    }

    private function closedState(int $id, int $contractId, int $base, string $dueDate, string $status): array
    {
        return [
            'installment_id' => $id,
            'contract_id' => $contractId,
            'status' => $status,
            'financial_status' => $status,
            'financial_priority' => 90,
            'due_date' => $dueDate,
            'original_principal' => $base,
            'base_amount' => $base,
            'effective_paid_principal' => 0,
            'remaining_principal' => 0,
            'normal_penalty' => 0,
            'legal_penalty' => 0,
            'eligible_reward' => 0,
            'final_payable' => 0,
            'payment_allowed' => false,
            'calculation_version' => self::CALCULATION_VERSION,
            'calculation_status' => 'not_applicable',
        ];
    }

    private function storedSettlementDate(array $installment, bool $isPaid): ?string
    {
        if (! $isPaid) {
            return null;
        }

        return $this->dateOrNull($installment['effective_settlement_at'] ?? $installment['last_payment_date'] ?? null);
    }

    private function money(mixed $value): int
    {
        $english = PersianDigits::toEnglish((string) $value);
        $english = str_replace([',', '،', '٬', ' ', "\xC2\xA0", 'تومان', 'ریال'], '', $english);
        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $english, $matches)) {
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

    private function integer(mixed $value): int
    {
        $value = PersianDigits::toEnglish((string) $value);

        return preg_match('/^\d+$/', $value) ? (int) $value : 0;
    }

    private function date(mixed $value): string
    {
        $value = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function addDays(string $date, int $days): string
    {
        $dateTime = new \DateTimeImmutable($date);

        return $days > 0 ? $dateTime->modify('+'.$days.' day')->format('Y-m-d') : $dateTime->format('Y-m-d');
    }

    private function daysBetween(string $from, string $to): int
    {
        return max(0, (int) ((strtotime($to) - strtotime($from)) / 86_400));
    }
}
