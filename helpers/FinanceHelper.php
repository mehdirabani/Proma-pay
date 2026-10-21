<?php

require_once __DIR__ . '/InstallmentSettlementService.php';
require_once __DIR__ . '/InstallmentFinancialStateService.php';
require_once __DIR__ . '/PaymentAllocationService.php';
require_once __DIR__ . '/CustomerPenaltyPresentationService.php';

class FinanceHelper
{
    public static function installmentAmount($principal, $months, $monthlyRate, $interestType)
    {
        $principal = normalize_money($principal);
        $months = max(1, (int) $months);
        if ($principal <= 0) {
            return 0;
        }
        $rate = MoneyMath::rateUnits($monthlyRate);
        if ($interestType === 'compound') {
            $total = $principal;
            for ($month = 0; $month < $months; $month++) {
                $total = MoneyMath::applyMonthlyRate($total, $rate);
            }
        } else {
            $total = $principal + MoneyMath::rateForDays($principal, $rate, $months * 30);
        }
        return self::roundInstallmentAmount(MoneyMath::ceilDiv($total, $months));
    }

    public static function roundInstallmentAmount($amount)
    {
        return MoneyMath::ceilToStep(normalize_money($amount), 100000);
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
        $state = InstallmentFinancialStateService::state($installment, $payments, $settings, $date ?: date('Y-m-d'));
        return array_merge($state, CustomerPenaltyPresentationService::forState($state, $settings));
    }

    public static function paymentPreview(array $installment, array $payments, array $settings, $paymentAmount = 0, $paymentDate = null)
    {
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $paymentAmount = normalize_money($paymentAmount);
        $state = self::preview($installment, $payments, $settings, $paymentDate);
        $payable = normalize_money($state['final_payable'] ?? 0);
        $allocation = [];
        if ($payable > 0 && $paymentAmount > 0) {
            $plan = PaymentAllocationService::plan([$installment], min($paymentAmount, $payable), $paymentDate, $settings);
            $allocation = $plan['allocations'][0] ?? [];
        }
        $remainingAfter = normalize_money($allocation['remaining_after'] ?? $state['remaining_principal']);
        return [
            'base_amount' => $state['original_principal'], 'paid_amount' => $state['effective_paid_principal'],
            'remaining_before_payment' => $state['remaining_principal'], 'penalty' => $state['total_penalty'],
            'calculated_penalty' => $state['total_penalty'], 'normal_penalty' => $state['normal_penalty'],
            'legal_penalty' => $state['legal_penalty'], 'penalty_mode' => $state['penalty_mode'],
            'normal_penalty_accrued' => $state['normal_penalty_accrued'],
            'legal_penalty_accrued' => $state['legal_penalty_accrued'],
            'effective_penalty_payable' => $state['effective_penalty_payable'],
            'projected_legal_penalty' => $state['projected_legal_penalty'],
            'show_projected_legal_penalty' => $state['show_projected_legal_penalty'],
            'projected_legal_penalty_customer_message' => $state['projected_legal_penalty_customer_message'],
            'penalty_rate' => $state['penalty_rate'], 'normal_penalty_rate' => $state['normal_penalty_rate'],
            'legal_penalty_rate' => $state['legal_penalty_rate'], 'grace_days' => $state['grace_days'],
            'penalty_start_date' => $state['penalty_start_date'], 'overdue_days' => $state['overdue_days'],
            'calculated_reward' => normalize_money($allocation['reward_applied'] ?? 0),
            'payable_on_payment_date' => $payable, 'remaining_after_payment' => $remainingAfter,
            'paid_amount_after_payment' => max(0, $state['original_principal'] - $remainingAfter),
            'final_status' => $allocation['final_status'] ?? $state['status'],
            'is_full_payment' => !empty($allocation['is_full_settlement']),
            'message' => $paymentAmount > 0 && empty($allocation['is_full_settlement']) ? 'پرداخت جزئی شامل پاداش تسویه زودتر از موعد نمی‌شود.' : 'محاسبه پرداخت انجام شد.',
        ];
    }

    public static function previewForMode(array $installment, array $payments, array $settings, $penaltyMode, $date = null)
    {
        $state = self::preview($installment, $payments, $settings, $date);
        $mode = $penaltyMode === 'legal' ? 'legal' : 'normal';
        $state['penalty_mode'] = $mode;
        $state['penalty'] = normalize_money($state[$mode . '_penalty'] ?? 0);
        $state['payable'] = $mode === 'legal'
            ? normalize_money($state['final_payable'] ?? 0)
            : max(0, normalize_money($state['remaining_principal'] ?? 0) + normalize_money($state['normal_penalty'] ?? 0) - normalize_money($state['eligible_reward'] ?? 0));
        return $state;
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
            'total_payable' => MoneyMath::ceilMulDiv($installment, $months, 1),
            'months' => $months,
            'interest_type' => $interestType === 'compound' ? 'compound' : 'simple',
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
