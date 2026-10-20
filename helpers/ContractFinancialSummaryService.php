<?php

/** Backwards-compatible name for the V6 centralized debt read model. */
final class ContractFinancialSummaryService
{
    public static function overdueBreakdown($contractId, $calculationDate = null, ?array $preloadedInstallments = null): array
    {
        return FinancialDebtEngine::overdueBreakdown($contractId, $calculationDate, $preloadedInstallments);
    }

    public static function buildOverdueBreakdown(array $installmentQuote, array $legalCostSummary): array
    {
        return FinancialDebtEngine::buildOverdueBreakdown($installmentQuote, $legalCostSummary);
    }

    public static function summarize($contractId, $calculationDate = null, ?array $preloadedInstallments = null): array
    {
        return FinancialDebtEngine::summarize($contractId, $calculationDate, $preloadedInstallments);
    }
}
