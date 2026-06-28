<?php

class Achievement extends Model
{
    public static function evaluateCustomer($customerId)
    {
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return;
        }

        $contractCount = (int) self::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$customerId])['total'];
        if ($contractCount >= 5) {
            User::addMedalIfMissing($customerId, 'five_contracts', 'خریدار وفادار', 'خرید حداقل ۵ قرارداد اقساطی', 50);
        }

        $earlyPaidInstallments = (int) self::fetch(
            "SELECT COUNT(DISTINCT i.id) AS total
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN payments p ON p.installment_id = i.id
             WHERE c.customer_id = ? AND i.status = 'paid'
             AND p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0
             AND DATE(COALESCE(p.paid_at, p.created_at)) < i.due_date",
            [$customerId]
        )['total'];
        if ($earlyPaidInstallments >= 5) {
            User::addMedalIfMissing($customerId, 'five_early_installments', 'خوش‌حساب طلایی', 'تسویه زودهنگام ۵ قسط', 60);
        }

        $earlyClosedContracts = (int) self::fetch(
            "SELECT COUNT(*) AS total
             FROM contracts c
             WHERE c.customer_id = ?
             AND EXISTS (SELECT 1 FROM installments i WHERE i.contract_id = c.id)
             AND NOT EXISTS (SELECT 1 FROM installments i WHERE i.contract_id = c.id AND i.status != 'paid')
             AND (SELECT MAX(DATE(COALESCE(p.paid_at, p.created_at)))
                  FROM payments p
                  JOIN installments pi ON pi.id = p.installment_id
                  WHERE pi.contract_id = c.id AND p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0)
                 < (SELECT MAX(i.due_date) FROM installments i WHERE i.contract_id = c.id)",
            [$customerId]
        )['total'];
        if ($earlyClosedContracts >= 1) {
            User::addMedalIfMissing($customerId, 'early_contract_close', 'تسویه زودهنگام', 'تسویه زودهنگام یک قرارداد', 70);
        }

        $onTimePayments = (int) self::fetch(
            "SELECT COUNT(DISTINCT i.id) AS total
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN payments p ON p.installment_id = i.id
             WHERE c.customer_id = ? AND i.status = 'paid'
             AND p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0
             AND DATE(COALESCE(p.paid_at, p.created_at)) <= i.due_date",
            [$customerId]
        )['total'];
        if ($onTimePayments >= 10) {
            User::addMedalIfMissing($customerId, 'ten_on_time_payments', 'اعتبارساز', 'ثبت ۱۰ پرداخت به‌موقع یا زودتر', 40);
        }
    }
}
