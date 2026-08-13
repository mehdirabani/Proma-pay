<?php

final class InstallmentSettlementService
{
    const SETTLED_MESSAGE = 'این قسط قبلاً به‌طور کامل تسویه شده است و امکان ثبت پرداخت جدید ندارد.';

    public static function isSettled(array $installment)
    {
        // `paid_amount`, `remaining_amount` and a legacy `paid` status are
        // materialised display fields.  They are not authoritative for an
        // installment that has partial historical payments or penalties.
        // Always derive eligibility from the immutable effective payments.
        $state = array_key_exists('calculation_version', $installment)
            ? $installment
            : InstallmentFinancialStateService::state($installment);
        return empty($state['payment_allowed']) || normalize_money($state['final_payable'] ?? 0) <= 0;
    }

    public static function assertPayable(array $installment, $amount = null, $paymentDate = null)
    {
        if (($installment['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قسط لغو شده قابل پرداخت نیست.', 409);
        }
        if ((string) ($installment['contract_status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده پرداخت جدید قابل ثبت نیست.', 409);
        }
        if (in_array((string) ($installment['contract_status'] ?? ''), ['closed'], true)) {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده پرداخت جدید قابل ثبت نیست.', 409);
        }

        $paymentDate = $paymentDate ?: date('Y-m-d');
        $state = InstallmentFinancialStateService::state($installment, null, Settings::allKeyed(), $paymentDate);
        $payable = normalize_money($state['final_payable'] ?? 0);
        if ($payable <= 0) {
            throw new InvalidArgumentException(self::SETTLED_MESSAGE, 409);
        }
        if ($amount !== null) {
            $amount = normalize_money($amount);
            if ($amount <= 0) {
                throw new InvalidArgumentException('مبلغ پرداخت معتبر نیست.');
            }
            if ($amount > $payable) {
                throw new InvalidArgumentException('مبلغ پرداخت از مبلغ قابل پرداخت این قسط بیشتر است.');
            }
        }
        $preview = [
            'base_amount' => $state['original_principal'],
            'paid_amount' => $state['effective_paid_principal'],
            'remaining_before_payment' => $state['remaining_principal'],
            'penalty' => $state['total_penalty'],
            'calculated_penalty' => $state['total_penalty'],
            'normal_penalty' => $state['normal_penalty'],
            'legal_penalty' => $state['legal_penalty'],
            'penalty_mode' => $state['penalty_mode'],
            'calculated_reward' => $state['eligible_reward'],
            'payable_on_payment_date' => $payable,
            'payment_allowed' => true,
        ];
        if ($amount !== null) {
            $plan = PaymentAllocationService::plan([$installment], $amount, $paymentDate, Settings::allKeyed());
            $allocation = $plan['allocations'][0] ?? [];
            $preview['remaining_after_payment'] = normalize_money($allocation['remaining_after'] ?? $state['remaining_principal']);
            $preview['paid_amount_after_payment'] = max(0, $state['original_principal'] - $preview['remaining_after_payment']);
            $preview['calculated_reward'] = normalize_money($allocation['reward_applied'] ?? 0);
            $preview['is_full_payment'] = !empty($allocation['is_full_settlement']);
            $preview['final_status'] = $allocation['final_status'] ?? $state['status'];
        }
        return $preview;
    }
}
