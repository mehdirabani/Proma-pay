<?php

final class InstallmentSettlementService
{
    const SETTLED_MESSAGE = 'این قسط قبلاً به‌طور کامل تسویه شده و پرداخت مجدد آن ممکن نیست.';

    public static function isSettled(array $installment)
    {
        $base = normalize_money($installment['base_amount'] ?? 0);
        $paid = normalize_money($installment['paid_amount'] ?? 0);
        $remaining = array_key_exists('remaining_amount', $installment)
            ? normalize_money($installment['remaining_amount'])
            : max(0, $base - $paid);

        return ($installment['status'] ?? '') === 'paid'
            || ($base > 0 && $paid >= $base)
            || ($base > 0 && $remaining <= 0);
    }

    public static function assertPayable(array $installment, $amount = null, $paymentDate = null)
    {
        if (($installment['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قسط لغو شده قابل پرداخت نیست.', 409);
        }
        if ((string) ($installment['contract_status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده پرداخت جدید قابل ثبت نیست.', 409);
        }
        if (self::isSettled($installment)) {
            throw new InvalidArgumentException(self::SETTLED_MESSAGE, 409);
        }
        if (in_array((string) ($installment['contract_status'] ?? ''), ['completed', 'closed'], true)) {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده پرداخت جدید قابل ثبت نیست.', 409);
        }

        $payments = !empty($installment['id']) ? Payment::forInstallment((int) $installment['id']) : [];
        $preview = FinanceHelper::paymentPreview(
            $installment,
            $payments,
            Settings::allKeyed(),
            $amount === null ? 0 : $amount,
            $paymentDate ?: date('Y-m-d')
        );
        $payable = normalize_money($preview['payable_on_payment_date'] ?? 0);
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
        return $preview;
    }
}
