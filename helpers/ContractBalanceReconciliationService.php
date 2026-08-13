<?php

final class ContractBalanceReconciliationService
{
    public static function scan($contractId = null, $limit = 500)
    {
        return InstallmentReconciliationService::scan($limit, $contractId);
    }
}
