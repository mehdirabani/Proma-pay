<?php

class FinanceHelper
{
    public static function installmentAmount($principal, $months, $monthlyRate, $interestType)
    {
        $principal = max(0, (float) $principal);
        $months = max(1, (int) $months);
        if ($principal <= 0) {
            return 0;
        }
        $rate = (float) $monthlyRate / 100;
        if ($interestType === 'compound') {
            $total = $principal * pow(1 + $rate, $months);
        } else {
            $total = $principal * (1 + ($rate * $months));
        }
        return self::roundInstallmentAmount($total / $months);
    }

    public static function roundInstallmentAmount($amount)
    {
        $step = 100000;
        return (int) (ceil(max(0, (float) $amount) / $step) * $step);
    }

    public static function addMonths($date, $months)
    {
        $dt = new DateTime($date);
        $day = (int) $dt->format('d');
        $dt->modify('first day of +' . (int) $months . ' month');
        $lastDay = (int) $dt->format('t');
        $dt->setDate((int) $dt->format('Y'), (int) $dt->format('m'), min($day, $lastDay));
        return $dt->format('Y-m-d');
    }

    public static function preview(array $installment, array $payments, array $settings, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        if (($installment['status'] ?? '') === 'cancelled') {
            return self::cancelledPreview($installment);
        }
        $state = self::stateOnDate($installment, $payments, $settings, $date);
        $reward = ($state['paid_amount'] > 0 && $state['remaining_amount'] > 0)
            ? 0
            : self::potentialReward($installment, $state['remaining_amount'], $settings, $date);
        $payable = max(0, ceil($state['remaining_amount'] + $state['penalty'] - $reward));

        return [
            'base_amount' => $state['base_amount'],
            'paid_amount' => $state['paid_amount'],
            'remaining_amount' => $state['remaining_amount'],
            'penalty' => $state['penalty'],
            'normal_penalty' => $state['normal_penalty'],
            'legal_penalty' => $state['legal_penalty'],
            'penalty_mode' => $state['penalty_mode'],
            'penalty_rate' => $state['penalty_rate'],
            'normal_penalty_rate' => $state['normal_penalty_rate'],
            'legal_penalty_rate' => $state['legal_penalty_rate'],
            'grace_days' => $state['grace_days'],
            'penalty_start_date' => $state['penalty_start_date'],
            'overdue_days' => $state['overdue_days'],
            'reward' => $reward,
            'payable' => $payable,
            'status' => self::status($state['base_amount'], $state['paid_amount'], $installment['due_date'], $date),
        ];
    }

    public static function paymentPreview(array $installment, array $payments, array $settings, $paymentAmount = 0, $paymentDate = null)
    {
        if (($installment['status'] ?? '') === 'cancelled') {
            $preview = self::cancelledPreview($installment);
            $preview['remaining_before_payment'] = $preview['remaining_amount'];
            $preview['remaining_after_payment'] = $preview['remaining_amount'];
            $preview['paid_amount_after_payment'] = $preview['paid_amount'];
            $preview['payable_on_payment_date'] = 0;
            $preview['calculated_penalty'] = 0;
            $preview['calculated_reward'] = 0;
            $preview['is_full_payment'] = false;
            $preview['final_status'] = 'cancelled';
            $preview['message'] = 'قسط لغو شده قابل پرداخت نیست.';
            return $preview;
        }
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $paymentAmount = normalize_money($paymentAmount);
        $state = self::stateOnDate($installment, $payments, $settings, $paymentDate);
        $possibleReward = self::potentialReward($installment, $state['remaining_amount'], $settings, $paymentDate);
        $fullPayableWithReward = max(0, ceil($state['remaining_amount'] + $state['penalty'] - $possibleReward));
        $fullPayableWithoutReward = max(0, ceil($state['remaining_amount'] + $state['penalty']));
        $isFull = $state['remaining_amount'] <= 0 || ($paymentAmount > 0 && $paymentAmount >= min($fullPayableWithReward, $fullPayableWithoutReward));
        $reward = $isFull ? $possibleReward : 0;
        $payable = max(0, ceil($state['remaining_amount'] + $state['penalty'] - $reward));
        $remainingAfter = $isFull ? 0 : max(0, ceil($state['remaining_amount'] - min($paymentAmount, $state['remaining_amount'])));
        $paidAfter = max(0, ceil($state['base_amount'] - $remainingAfter));
        $finalStatus = self::status($state['base_amount'], $paidAfter, $installment['due_date'], $paymentDate);
        $message = 'محاسبه پرداخت انجام شد.';
        if (!$isFull && $paymentDate <= $installment['due_date']) {
            $message = 'پرداخت جزئی شامل پاداش تسویه زودتر از موعد نمی‌شود.';
        } elseif ($paymentDate > $installment['due_date'] && (int) ($state['grace_days'] ?? 0) > 0 && (float) ($state['penalty'] ?? 0) <= 0) {
            $message = 'پرداخت در بازه تنفس دیرکرد است و جریمه‌ای محاسبه نشده است.';
        } elseif ($paymentDate > $installment['due_date']) {
            $message = $state['penalty_mode'] === 'legal'
                ? 'جریمه دیرکرد حقوقی تا تاریخ پرداخت محاسبه شده است.'
                : 'جریمه دیرکرد عادی تا تاریخ پرداخت محاسبه شده است.';
        } elseif ($isFull && $reward > 0) {
            $message = 'این پرداخت مشمول پاداش تسویه به‌موقع است.';
        }

        return [
            'base_amount' => $state['base_amount'],
            'paid_amount' => $state['paid_amount'],
            'remaining_before_payment' => $state['remaining_amount'],
            'penalty' => $state['penalty'],
            'calculated_penalty' => $state['penalty'],
            'normal_penalty' => $state['normal_penalty'],
            'legal_penalty' => $state['legal_penalty'],
            'penalty_mode' => $state['penalty_mode'],
            'penalty_rate' => $state['penalty_rate'],
            'normal_penalty_rate' => $state['normal_penalty_rate'],
            'legal_penalty_rate' => $state['legal_penalty_rate'],
            'grace_days' => $state['grace_days'],
            'penalty_start_date' => $state['penalty_start_date'],
            'overdue_days' => $state['overdue_days'],
            'calculated_reward' => $reward,
            'payable_on_payment_date' => $payable,
            'remaining_after_payment' => $remainingAfter,
            'paid_amount_after_payment' => $paidAfter,
            'final_status' => $finalStatus,
            'is_full_payment' => $isFull,
            'message' => $message,
        ];
    }

    public static function previewForMode(array $installment, array $payments, array $settings, $penaltyMode, $date = null)
    {
        $installment['__penalty_mode_override'] = $penaltyMode === 'legal' ? 'legal' : 'normal';
        return self::preview($installment, $payments, $settings, $date);
    }

    public static function contractPreview($principal, $downPayment, $months, $monthlyRate, $interestType)
    {
        $principal = normalize_money($principal);
        $downPayment = normalize_money($downPayment);
        $financed = max(0, $principal - $downPayment);
        $months = max(1, (int) to_english_digits($months));
        $installment = self::installmentAmount($financed, $months, $monthlyRate, $interestType);
        return [
            'principal_amount' => $principal,
            'down_payment_amount' => $downPayment,
            'financed_amount' => $financed,
            'installment_amount' => $installment,
            'total_payable' => ceil($installment * $months),
            'months' => $months,
            'interest_type' => $interestType === 'compound' ? 'compound' : 'simple',
        ];
    }

    protected static function stateOnDate(array $installment, array $payments, array $settings, $date)
    {
        $dueDate = $installment['due_date'];
        $baseAmount = (float) $installment['base_amount'];
        $normalMonthlyRate = (float) to_english_digits($settings['monthly_penalty_rate'] ?? 0);
        $legalMonthlyRate = (float) to_english_digits($settings['legal_monthly_penalty_rate'] ?? $normalMonthlyRate);
        $graceDays = max(0, min(365, (int) to_english_digits($settings['late_penalty_grace_days'] ?? 0)));
        if ($legalMonthlyRate <= 0 && $normalMonthlyRate > 0) {
            $legalMonthlyRate = $normalMonthlyRate;
        }
        $normalDailyRate = $normalMonthlyRate / 100 / 30;
        $legalDailyRate = $legalMonthlyRate / 100 / 30;
        $legalStartDate = self::legalPenaltyStartDate($installment);
        $penaltyStartDate = self::addDays($dueDate, $graceDays);

        usort($payments, function ($a, $b) {
            return strcmp($a['payment_date'] ?? $a['paid_at'] ?? $a['created_at'] ?? '', $b['payment_date'] ?? $b['paid_at'] ?? $b['created_at'] ?? '');
        });

        $remaining = $baseAmount;
        $normalPenalty = 0;
        $legalPenalty = 0;
        $lastPenaltyDate = $penaltyStartDate;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid' || ($payment['payment_type'] ?? 'installment') === 'down_payment') {
                continue;
            }
            $paymentDate = substr($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'], 0, 10);
            if ($paymentDate > $date) {
                continue;
            }
            if ($paymentDate > $penaltyStartDate && $remaining > 0) {
                $days = max(0, (int) ((strtotime($paymentDate) - strtotime($lastPenaltyDate)) / 86400));
                $normalPenalty += $remaining * $normalDailyRate * $days;
                $legalPenalty += self::legalPenaltyForPeriod($remaining, $lastPenaltyDate, $paymentDate, $normalDailyRate, $legalDailyRate, $legalStartDate);
                $lastPenaltyDate = $paymentDate;
            }
            if (isset($payment['remaining_after_payment']) && $payment['remaining_after_payment'] !== null) {
                $remaining = max(0, (float) $payment['remaining_after_payment']);
            } else {
                $remaining = max(0, $remaining - (float) $payment['amount']);
            }
        }

        if ($remaining > 0 && $date > $penaltyStartDate) {
            $days = max(0, (int) ((strtotime($date) - strtotime($lastPenaltyDate)) / 86400));
            $normalPenalty += $remaining * $normalDailyRate * $days;
            $legalPenalty += self::legalPenaltyForPeriod($remaining, $lastPenaltyDate, $date, $normalDailyRate, $legalDailyRate, $legalStartDate);
        }

        $manualPenalty = (float) ($installment['manual_penalty_adjustment'] ?? 0);
        $manualReward = (float) ($installment['manual_reward_adjustment'] ?? 0);
        $discount = (float) ($installment['penalty_discount_amount'] ?? 0);
        $normalPenalty = max(0, ceil($normalPenalty + $manualPenalty - $discount));
        $legalPenalty = max(0, ceil($legalPenalty + $manualPenalty - $discount));
        $penaltyMode = $installment['__penalty_mode_override'] ?? (self::usesLegalPenalty($installment) ? 'legal' : 'normal');
        $penaltyMode = $penaltyMode === 'legal' ? 'legal' : 'normal';
        $penalty = $penaltyMode === 'legal' ? $legalPenalty : $normalPenalty;

        return [
            'base_amount' => $baseAmount,
            'paid_amount' => max(0, ceil($baseAmount - $remaining)),
            'remaining_amount' => max(0, ceil($remaining)),
            'penalty' => $penalty,
            'normal_penalty' => $normalPenalty,
            'legal_penalty' => $legalPenalty,
            'penalty_mode' => $penaltyMode,
            'penalty_rate' => $penaltyMode === 'legal' ? $legalMonthlyRate : $normalMonthlyRate,
            'normal_penalty_rate' => $normalMonthlyRate,
            'legal_penalty_rate' => $legalMonthlyRate,
            'grace_days' => $graceDays,
            'penalty_start_date' => $penaltyStartDate,
            'overdue_days' => max(0, (int) ((strtotime($date) - strtotime($dueDate)) / 86400)),
            'manual_reward' => max(0, ceil($manualReward)),
        ];
    }

    protected static function usesLegalPenalty(array $installment)
    {
        if ((int) ($installment['legal_case_count'] ?? 0) > 0) {
            return true;
        }
        foreach (['contract_legal_status', 'legal_status'] as $key) {
            $status = trim((string) ($installment[$key] ?? ''));
            if ($status !== '') {
                return true;
            }
        }
        return in_array((string) ($installment['contract_status'] ?? ''), ['referred', 'legal'], true);
    }

    protected static function legalPenaltyStartDate(array $installment)
    {
        $date = substr((string) ($installment['legal_started_at'] ?? ''), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        if (self::usesLegalPenalty($installment)) {
            return $installment['due_date'] ?? null;
        }
        return null;
    }

    protected static function legalPenaltyForPeriod($remaining, $fromDate, $toDate, $normalDailyRate, $legalDailyRate, $legalStartDate = null)
    {
        $fromDate = substr((string) $fromDate, 0, 10);
        $toDate = substr((string) $toDate, 0, 10);
        if ($fromDate === '' || $toDate === '' || $toDate <= $fromDate) {
            return 0;
        }
        if (!$legalStartDate) {
            $days = max(0, (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400));
            return (float) $remaining * (float) $legalDailyRate * $days;
        }
        if ($toDate <= $legalStartDate) {
            $days = max(0, (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400));
            return (float) $remaining * (float) $normalDailyRate * $days;
        }
        if ($fromDate >= $legalStartDate) {
            $days = max(0, (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400));
            return (float) $remaining * (float) $legalDailyRate * $days;
        }

        $normalDays = max(0, (int) ((strtotime($legalStartDate) - strtotime($fromDate)) / 86400));
        $legalDays = max(0, (int) ((strtotime($toDate) - strtotime($legalStartDate)) / 86400));
        return ((float) $remaining * (float) $normalDailyRate * $normalDays)
            + ((float) $remaining * (float) $legalDailyRate * $legalDays);
    }

    protected static function addDays($date, $days)
    {
        $dt = new DateTime($date);
        if ((int) $days > 0) {
            $dt->modify('+' . (int) $days . ' day');
        }
        return $dt->format('Y-m-d');
    }

    protected static function potentialReward(array $installment, $remainingAmount, array $settings, $date)
    {
        if ($remainingAmount <= 0 || $date > $installment['due_date']) {
            return 0;
        }
        $dailyRewardRate = ((float) ($settings['monthly_reward_rate'] ?? 0)) / 100 / 30;
        $days = max(1, (int) ((strtotime($installment['due_date']) - strtotime($date)) / 86400));
        $manualReward = (float) ($installment['manual_reward_adjustment'] ?? 0);
        return max(0, ceil(((float) $remainingAmount * $dailyRewardRate * $days) + $manualReward));
    }

    public static function status($baseAmount, $paidAmount, $dueDate, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        if ($paidAmount >= $baseAmount) {
            return 'paid';
        }
        if ($paidAmount > 0) {
            return 'partial';
        }
        return $date > $dueDate ? 'overdue' : 'pending';
    }

    protected static function cancelledPreview(array $installment)
    {
        $baseAmount = max(0, (float) ($installment['base_amount'] ?? 0));
        $paidAmount = max(0, min($baseAmount, (float) ($installment['paid_amount'] ?? 0)));
        return [
            'base_amount' => $baseAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, $baseAmount - $paidAmount),
            'penalty' => 0,
            'normal_penalty' => 0,
            'legal_penalty' => 0,
            'penalty_mode' => 'normal',
            'penalty_rate' => 0,
            'normal_penalty_rate' => 0,
            'legal_penalty_rate' => 0,
            'grace_days' => 0,
            'penalty_start_date' => null,
            'overdue_days' => 0,
            'reward' => 0,
            'payable' => 0,
            'status' => 'cancelled',
        ];
    }
}
