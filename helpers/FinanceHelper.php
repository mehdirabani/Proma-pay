<?php

class FinanceHelper
{
    public static function installmentAmount($principal, $months, $monthlyRate, $interestType)
    {
        $principal = (float) $principal;
        $months = max(1, (int) $months);
        $rate = (float) $monthlyRate / 100;
        if ($interestType === 'compound') {
            $total = $principal * pow(1 + $rate, $months);
        } else {
            $total = $principal * (1 + ($rate * $months));
        }
        return ceil($total / $months);
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
        $dueDate = $installment['due_date'];
        $baseAmount = (float) $installment['base_amount'];
        $paid = 0;
        $penalty = 0;
        $reward = 0;
        $outstanding = $baseAmount;
        $dailyPenaltyRate = ((float) ($settings['monthly_penalty_rate'] ?? 0)) / 100 / 30;
        $dailyRewardRate = ((float) ($settings['monthly_reward_rate'] ?? 0)) / 100 / 30;

        usort($payments, function ($a, $b) {
            return strcmp($a['paid_at'], $b['paid_at']);
        });

        $lastPenaltyDate = $dueDate;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid') {
                continue;
            }
            $paymentDate = substr($payment['paid_at'], 0, 10);
            $amount = (float) $payment['amount'];
            if ($paymentDate > $dueDate && $outstanding > 0) {
                $days = max(0, (int) ((strtotime($paymentDate) - strtotime($lastPenaltyDate)) / 86400));
                $penalty += $outstanding * $dailyPenaltyRate * $days;
                $lastPenaltyDate = $paymentDate;
            }
            $paid += $amount;
            $outstanding = max(0, $baseAmount - $paid);
        }

        if ($outstanding > 0 && $date > $dueDate) {
            $days = max(0, (int) ((strtotime($date) - strtotime($lastPenaltyDate)) / 86400));
            $penalty += $outstanding * $dailyPenaltyRate * $days;
        }

        $fullPaidOnTimePayment = null;
        $runningPaid = 0;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid') {
                continue;
            }
            $runningPaid += (float) $payment['amount'];
            $paymentDate = substr($payment['paid_at'], 0, 10);
            if ($runningPaid >= $baseAmount && $paymentDate <= $dueDate) {
                $fullPaidOnTimePayment = $paymentDate;
                break;
            }
        }

        if ($fullPaidOnTimePayment !== null) {
            $days = max(1, (int) ((strtotime($dueDate) - strtotime($fullPaidOnTimePayment)) / 86400));
            $reward = $baseAmount * $dailyRewardRate * $days;
        }

        $manualPenalty = (float) ($installment['manual_penalty_adjustment'] ?? 0);
        $manualReward = (float) ($installment['manual_reward_adjustment'] ?? 0);
        $discount = (float) ($installment['penalty_discount_amount'] ?? 0);
        $penalty = max(0, ceil($penalty + $manualPenalty - $discount));
        $reward = max(0, ceil($reward + $manualReward));
        $payable = max(0, ceil($baseAmount - $paid + $penalty - $reward));

        return [
            'base_amount' => $baseAmount,
            'paid_amount' => $paid,
            'penalty' => $penalty,
            'reward' => $reward,
            'payable' => $payable,
            'status' => self::status($baseAmount, $paid, $dueDate, $date),
        ];
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
