<?php

/** Read model backed by the canonical installment financial state. */
class ContractFinancialSummaryService
{
    public static function summarize($contractId, $calculationDate = null, ?array $preloadedInstallments = null)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract) throw new InvalidArgumentException('قرارداد پیدا نشد.');
        $date = self::date($calculationDate);
        $installments = $preloadedInstallments ?? Installment::all(['contract_id' => (int) $contractId, 'custom_last' => true]);
        $settlement = PaymentAllocationService::quote($installments, $date);
        $downPaymentContract = normalize_money($contract['down_payment_amount'] ?? 0);
        $downPaymentPaid = self::downPaymentPaidUntil((int) $contractId, $date);
        $dueInstallments = 0;
        $contractInstallments = 0;
        $paidInstallments = 0;
        $projectedLegalPenalty = 0;
        $projectedLegalPenaltyVisible = false;
        $actualLegalReferral = false;
        $calculationWarnings = [];
        foreach ($installments as $installment) {
            $base = normalize_money($installment['original_principal'] ?? $installment['base_amount'] ?? 0);
            $contractInstallments += $base;
            $paidInstallments += normalize_money($installment['effective_paid_principal'] ?? $installment['paid_amount'] ?? 0);
            $projectedLegalPenalty += normalize_money($installment['projected_legal_penalty'] ?? 0);
            $projectedLegalPenaltyVisible = $projectedLegalPenaltyVisible || !empty($installment['show_projected_legal_penalty']);
            $actualLegalReferral = $actualLegalReferral || !empty($installment['canonical_legal_referral_at']);
            if (!in_array((string) ($installment['calculation_status'] ?? 'calculated'), ['calculated', 'not_applicable'], true)) {
                $calculationWarnings[] = [
                    'installment_id' => (int) ($installment['id'] ?? 0),
                    'status' => (string) ($installment['calculation_status'] ?? 'calculation_failed'),
                    'messages' => (array) ($installment['calculation_warnings'] ?? []),
                ];
            }
            if (($installment['due_date'] ?? '') <= $date && ($installment['status'] ?? '') !== 'cancelled') $dueInstallments += $base;
        }
        $legalCostSummary = ContractLegalCostSummaryService::forContract((int) $contractId);
        $legalCosts = normalize_money($legalCostSummary['outstanding_chargeable_legal_costs'] ?? 0);
        $final = max(0,
            normalize_money($settlement['principal_total']) + normalize_money($settlement['normal_penalty_total'])
            + normalize_money($settlement['legal_penalty_total']) - normalize_money($settlement['reward_total']) + $legalCosts
        );
        return [
            'contract_total' => $downPaymentContract + $contractInstallments,
            'paid_total' => min($downPaymentContract, $downPaymentPaid) + $paidInstallments,
            'due_installments_total' => $dueInstallments,
            'unpaid_installments_total' => normalize_money($settlement['principal_total']),
            'remaining_principal' => max(0, $downPaymentContract - $downPaymentPaid) + normalize_money($settlement['principal_total']),
            'late_penalty_total' => normalize_money($settlement['normal_penalty_total']) + normalize_money($settlement['legal_penalty_total']),
            'normal_late_penalty_total' => normalize_money($settlement['normal_penalty_total']),
            'legal_late_penalty_total' => normalize_money($settlement['legal_penalty_total']),
            'projected_legal_penalty_total' => $projectedLegalPenalty,
            'show_projected_legal_penalty' => $projectedLegalPenaltyVisible,
            'actual_legal_referral' => $actualLegalReferral,
            'effective_penalty_payable_total' => normalize_money($settlement['normal_penalty_total']) + normalize_money($settlement['legal_penalty_total']),
            'late_penalty_mode' => normalize_money($settlement['legal_penalty_total']) > 0 ? 'legal' : 'normal',
            'early_settlement_reward_total' => normalize_money($settlement['reward_total']),
            'legal_costs_total' => $legalCosts,
            'approved_outstanding_legal_costs' => $legalCosts,
            'legal_cost_summary' => $legalCostSummary,
            'final_collectable_amount' => $final,
            'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
            'calculation_status' => $calculationWarnings ? 'needs_review' : 'calculated',
            'calculation_warnings' => $calculationWarnings,
        ];
    }

    protected static function downPaymentPaidUntil($contractId, $date)
    {
        $row = Payment::fetch("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0 AND COALESCE(payment_type, 'installment') = 'down_payment' AND COALESCE(payment_date, DATE(paid_at), DATE(created_at)) <= ?", [(int) $contractId, $date]);
        return normalize_money($row['total'] ?? 0);
    }

    protected static function date($value)
    {
        if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
        $value = parse_jalali_date((string) $value) ?: trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }
}
