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
        $state = self::stateOnDate($installment, $payments, $settings, $date);
        $reward = self::potentialReward($installment, $state['remaining_amount'], $settings, $date);
        $payable = max(0, ceil($state['remaining_amount'] + $state['penalty'] - $reward));

        return [
            'base_amount' => $state['base_amount'],
            'paid_amount' => $state['paid_amount'],
            'remaining_amount' => $state['remaining_amount'],
            'penalty' => $state['penalty'],
            'reward' => $reward,
            'payable' => $payable,
            'status' => self::status($state['base_amount'], $state['paid_amount'], $installment['due_date'], $date),
        ];
    }

    public static function paymentPreview(array $installment, array $payments, array $settings, $paymentAmount = 0, $paymentDate = null)
    {
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
        } elseif ($paymentDate > $installment['due_date']) {
            $message = 'جریمه دیرکرد تا تاریخ پرداخت محاسبه شده است.';
        } elseif ($isFull && $reward > 0) {
            $message = 'این پرداخت مشمول پاداش تسویه به‌موقع است.';
        }

        return [
            'base_amount' => $state['base_amount'],
            'paid_amount' => $state['paid_amount'],
            'remaining_before_payment' => $state['remaining_amount'],
            'calculated_penalty' => $state['penalty'],
            'calculated_reward' => $reward,
            'payable_on_payment_date' => $payable,
            'remaining_after_payment' => $remainingAfter,
            'paid_amount_after_payment' => $paidAfter,
            'final_status' => $finalStatus,
            'is_full_payment' => $isFull,
            'message' => $message,
        ];
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
        $remaining = $baseAmount;
        $penalty = 0;
        $dailyPenaltyRate = ((float) ($settings['monthly_penalty_rate'] ?? 0)) / 100 / 30;

        usort($payments, function ($a, $b) {
            return strcmp($a['payment_date'] ?? $a['paid_at'] ?? $a['created_at'] ?? '', $b['payment_date'] ?? $b['paid_at'] ?? $b['created_at'] ?? '');
        });

        $lastPenaltyDate = $dueDate;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid' || ($payment['payment_type'] ?? 'installment') === 'down_payment') {
                continue;
            }
            $paymentDate = substr($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'], 0, 10);
            if ($paymentDate > $date) {
                continue;
            }
            if ($paymentDate > $dueDate && $remaining > 0) {
                $days = max(0, (int) ((strtotime($paymentDate) - strtotime($lastPenaltyDate)) / 86400));
                $penalty += $remaining * $dailyPenaltyRate * $days;
                $lastPenaltyDate = $paymentDate;
            }
            if (isset($payment['remaining_after_payment']) && $payment['remaining_after_payment'] !== null) {
                $remaining = max(0, (float) $payment['remaining_after_payment']);
            } else {
                $remaining = max(0, $remaining - (float) $payment['amount']);
            }
        }

        if ($remaining > 0 && $date > $dueDate) {
            $days = max(0, (int) ((strtotime($date) - strtotime($lastPenaltyDate)) / 86400));
            $penalty += $remaining * $dailyPenaltyRate * $days;
        }

        $manualPenalty = (float) ($installment['manual_penalty_adjustment'] ?? 0);
        $manualReward = (float) ($installment['manual_reward_adjustment'] ?? 0);
        $discount = (float) ($installment['penalty_discount_amount'] ?? 0);
        $penalty = max(0, ceil($penalty + $manualPenalty - $discount));

        return [
            'base_amount' => $baseAmount,
            'paid_amount' => max(0, ceil($baseAmount - $remaining)),
            'remaining_amount' => max(0, ceil($remaining)),
            'penalty' => $penalty,
            'manual_reward' => max(0, ceil($manualReward)),
        ];
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
}
