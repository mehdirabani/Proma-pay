<?php

/**
 * Bounded contract-detail loader.  A contract page gets all visible
 * installment states from the batch state service; it never performs one
 * calculation request or one payment query per row.
 */
final class ContractInstallmentFinancialBatchService
{
    public static function load($contractId, $asOf = null): array
    {
        $installments = Installment::all(['contract_id' => (int) $contractId]);
        $warnings = [];
        foreach ($installments as $installment) {
            $status = (string) ($installment['calculation_status'] ?? 'calculated');
            if (!in_array($status, ['calculated', 'not_applicable'], true)) {
                $warnings[] = [
                    'installment_id' => (int) ($installment['id'] ?? 0),
                    'installment_number' => (int) ($installment['installment_number'] ?? 0),
                    'status' => $status,
                    'messages' => (array) ($installment['calculation_warnings'] ?? []),
                ];
            }
        }
        return [
            'items' => $installments,
            'calculation_date' => $asOf ?: date('Y-m-d'),
            'calculation_version' => InstallmentFinancialStateService::CALCULATION_VERSION,
            'warnings' => $warnings,
            'is_safe_to_collect' => !$warnings,
        ];
    }
}
