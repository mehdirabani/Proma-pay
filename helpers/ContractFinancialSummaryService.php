<?php

class ContractFinancialSummaryService
{
    public static function summarize($contractId, $calculationDate = null)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }

        $date = self::normalizeDate($calculationDate);
        Payment::ensureCorrectionSchema();
        LegalCase::ensureSchema();
        LegalCaseLog::ensureSchema();

        $settings = Settings::allKeyed();
        $installments = Installment::all(['contract_id' => (int) $contractId, 'custom_last' => true]);
        $downPaymentContract = (float) ($contract['down_payment_amount'] ?? 0);
        $downPaymentPaid = self::downPaymentPaidUntil((int) $contractId, $date);

        $contractTotal = $downPaymentContract;
        $paidTotal = min($downPaymentContract, $downPaymentPaid);
        $dueInstallmentsTotal = 0;
        $unpaidInstallmentsTotal = 0;
        $remainingPrincipal = max(0, $downPaymentContract - $downPaymentPaid);
        $latePenaltyTotal = 0;
        $normalLatePenaltyTotal = 0;
        $legalLatePenaltyTotal = 0;
        $latePenaltyMode = 'normal';
        $earlySettlementRewardTotal = 0;

        foreach ($installments as $installment) {
            $payments = Payment::forInstallment((int) $installment['id']);
            $preview = FinanceHelper::preview($installment, $payments, $settings, $date);
            $baseAmount = (float) ($installment['base_amount'] ?? 0);
            $remainingAmount = (float) ($preview['remaining_amount'] ?? 0);
            $paidAmount = (float) ($preview['paid_amount'] ?? 0);
            $penalty = (float) ($preview['penalty'] ?? 0);
            if (($preview['penalty_mode'] ?? 'normal') === 'legal') {
                $latePenaltyMode = 'legal';
            }

            $contractTotal += $baseAmount;
            $paidTotal += $paidAmount;
            if (($installment['status'] ?? '') === 'cancelled') {
                continue;
            }
            $remainingPrincipal += $remainingAmount;

            if (($installment['due_date'] ?? '') <= $date) {
                $dueInstallmentsTotal += $baseAmount;
            }
            if ($remainingAmount > 0) {
                $unpaidInstallmentsTotal += $remainingAmount;
            }
            if ($remainingAmount > 0 && ($installment['due_date'] ?? '') < $date) {
                $latePenaltyTotal += $penalty;
                $normalLatePenaltyTotal += (float) ($preview['normal_penalty'] ?? $penalty);
                $legalLatePenaltyTotal += (float) ($preview['legal_penalty'] ?? $penalty);
            }
            if ($remainingAmount > 0 && ($installment['due_date'] ?? '') >= $date) {
                $settlementPreview = FinanceHelper::paymentPreview(
                    $installment,
                    $payments,
                    $settings,
                    $remainingAmount + $penalty,
                    $date
                );
                $earlySettlementRewardTotal += (float) ($settlementPreview['calculated_reward'] ?? 0);
            }
        }

        $legalCostsTotal = LegalCaseLog::costTotalForContract((int) $contractId) + LegalCase::expenseTotalForContract((int) $contractId);
        $finalCollectableAmount = max(0, $remainingPrincipal + $latePenaltyTotal - $earlySettlementRewardTotal + $legalCostsTotal);

        return [
            'contract_total' => self::roundMoney($contractTotal),
            'paid_total' => self::roundMoney($paidTotal),
            'due_installments_total' => self::roundMoney($dueInstallmentsTotal),
            'unpaid_installments_total' => self::roundMoney($unpaidInstallmentsTotal),
            'remaining_principal' => self::roundMoney($remainingPrincipal),
            'late_penalty_total' => self::roundMoney($latePenaltyTotal),
            'normal_late_penalty_total' => self::roundMoney($normalLatePenaltyTotal),
            'legal_late_penalty_total' => self::roundMoney($legalLatePenaltyTotal),
            'late_penalty_mode' => $latePenaltyMode,
            'early_settlement_reward_total' => self::roundMoney($earlySettlementRewardTotal),
            'legal_costs_total' => self::roundMoney($legalCostsTotal),
            'final_collectable_amount' => self::roundMoney($finalCollectableAmount),
        ];
    }

    protected static function downPaymentPaidUntil($contractId, $date)
    {
        $row = Payment::fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE contract_id = ?
               AND status = 'paid'
               AND COALESCE(is_corrected, 0) = 0
               AND COALESCE(payment_type, 'installment') = 'down_payment'
               AND COALESCE(payment_date, DATE(paid_at), DATE(created_at)) <= ?",
            [(int) $contractId, $date]
        );
        return (float) ($row['total'] ?? 0);
    }

    protected static function normalizeDate($value)
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }
        $parsed = parse_jalali_date($value) ?: $value;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsed) ? $parsed : date('Y-m-d');
    }

    protected static function roundMoney($value)
    {
        return max(0, ceil((float) $value));
    }
}
