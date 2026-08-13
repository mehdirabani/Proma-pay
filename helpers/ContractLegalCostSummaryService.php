<?php

/** Single source for only approved, chargeable, outstanding legal costs. */
final class ContractLegalCostSummaryService
{
    public static function forContract(int $contractId): array
    {
        if (!LegalCaseCostService::tableAvailable()) {
            $legacy = normalize_money(LegalCaseLog::costTotalForContract($contractId))
                + normalize_money(LegalCase::expenseTotalForContract($contractId));
            return [
                'total_legal_costs' => $legacy,
                'approved_legal_costs' => $legacy,
                'pending_approval_legal_costs' => 0,
                'collected_legal_costs' => 0,
                'outstanding_chargeable_legal_costs' => $legacy,
                'reimbursed_legal_costs' => 0,
                'reversed_legal_costs' => 0,
                'case_count' => 0,
                'latest_case_status' => null,
                'calculated_at' => date('Y-m-d H:i:s'),
                'calculation_status' => 'legacy_import_required',
            ];
        }
        $row = Model::fetch(
            "SELECT
                COALESCE(SUM(amount_toman), 0) AS total_legal_costs,
                COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount_toman ELSE 0 END), 0) AS approved_legal_costs,
                COALESCE(SUM(CASE WHEN approval_status = 'pending_approval' AND reversed_at IS NULL THEN amount_toman ELSE 0 END), 0) AS pending_approval_legal_costs,
                COALESCE(SUM(CASE WHEN approval_status = 'approved' AND reversed_at IS NULL THEN COALESCE(paid_amount_toman, 0) ELSE 0 END), 0) AS collected_legal_costs,
                COALESCE(SUM(CASE WHEN approval_status = 'approved' AND chargeable_to_customer = 1 AND payment_status NOT IN ('reimbursed', 'cancelled') AND reversed_at IS NULL THEN GREATEST(0, amount_toman - COALESCE(paid_amount_toman, 0)) ELSE 0 END), 0) AS outstanding_chargeable_legal_costs,
                COALESCE(SUM(CASE WHEN payment_status = 'reimbursed' THEN amount_toman ELSE 0 END), 0) AS reimbursed_legal_costs,
                COALESCE(SUM(CASE WHEN approval_status = 'reversed' OR reversed_at IS NOT NULL THEN amount_toman ELSE 0 END), 0) AS reversed_legal_costs
             FROM legal_case_costs WHERE contract_id = ?",
            [$contractId]
        ) ?: [];
        // Keep this compatible with MariaDB versions commonly found on shared hosting.
        $case = Model::fetch('SELECT status FROM legal_cases WHERE contract_id = ? ORDER BY id DESC LIMIT 1', [$contractId]);
        $caseCount = Model::fetch('SELECT COUNT(*) AS total FROM legal_cases WHERE contract_id = ?', [$contractId]);
        return [
            'total_legal_costs' => normalize_money($row['total_legal_costs'] ?? 0),
            'approved_legal_costs' => normalize_money($row['approved_legal_costs'] ?? 0),
            'pending_approval_legal_costs' => normalize_money($row['pending_approval_legal_costs'] ?? 0),
            'collected_legal_costs' => normalize_money($row['collected_legal_costs'] ?? 0),
            'outstanding_chargeable_legal_costs' => normalize_money($row['outstanding_chargeable_legal_costs'] ?? 0),
            'reimbursed_legal_costs' => normalize_money($row['reimbursed_legal_costs'] ?? 0),
            'reversed_legal_costs' => normalize_money($row['reversed_legal_costs'] ?? 0),
            'case_count' => (int) ($caseCount['total'] ?? 0),
            'latest_case_status' => $case['status'] ?? null,
            'calculated_at' => date('Y-m-d H:i:s'),
            'calculation_status' => 'calculated',
        ];
    }
}
