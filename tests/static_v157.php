<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$view = $read('views/contracts/show.php');
$engine = $read('helpers/InstallmentFinancialStateService.php');
$change = $read('helpers/InstallmentChangeService.php');
$operatorDebt = $read('helpers/OperatorDebtScenarioService.php');
$legalCosts = $read('helpers/LegalCaseCostService.php');
$costSummary = $read('helpers/ContractLegalCostSummaryService.php');
$settlementQuote = $read('helpers/SettlementQuoteService.php');
$paymentGroup = $read('helpers/PaymentGroupService.php');
$css = $read('assets/css/app.css');
$js = $read('assets/js/app.js');
$releaseBuilder = $read('scripts/build_release.php');
$assert(strpos($view, 'proma-contract-workspace') !== false && strpos($view, 'data-contract-tab-panel="installments"') !== false, 'Contract details has no content-aware installment workspace.');
$assert(strpos($view, '<script>') === false, 'Contract details still contains CSP-blocked inline script.');
$assert(strpos($view, 'قابل پرداخت امروز') !== false && strpos($view, 'جریمه حقوقی اعمال‌شده') !== false, 'Installment rows do not show payable/legal values.');
$assert(strpos($view, 'edit-installment-') !== false && strpos($view, 'void-installment-') !== false, 'Safe installment actions are not rendered.');
$assert(strpos($engine, "'inconsistent_referral'") !== false && strpos($engine, "'configuration_missing'") !== false, 'Legal calculation failures can still be silently zero.');
$assert(strpos($engine, "status IN ('referred', 'external_submission_confirmed')") !== false, 'Canonical legal referral inconsistency is not checked in batch.');
$assert(strpos($change, 'DELETE FROM installments') === false && strpos($change, 'installment_voids') !== false, 'Installment change service permits physical deletion or lacks void audit.');
$assert(strpos($operatorDebt, 'مبلغ واقعی قابل پرداخت امروز') !== false && strpos($operatorDebt, 'غیرقابل پرداخت') !== false, 'Operator debt scenarios do not distinguish projected legal debt.');
$assert(strpos($legalCosts, "'pending_approval'") !== false && strpos($costSummary, 'outstanding_chargeable_legal_costs') !== false, 'Approved legal-cost boundary is missing.');
$assert(strpos($legalCosts, 'recordPaymentAllocation') !== false && strpos($legalCosts, 'reversePaymentAllocations') !== false, 'Legal-cost payment allocation lifecycle is incomplete.');
$legalCostMigration = $read('database/migrations/2026_08_01_legal_cost_approval_v157.sql');
$assert(strpos($legalCostMigration, 'legal_case_costs') !== false && strpos($legalCostMigration, 'legal_cost_payment_allocations') !== false, 'Legal-cost migration is missing.');
$assert(strpos($settlementQuote, 'planForScope') !== false && strpos($settlementQuote, 'legal_cost_allocations') !== false && strpos($paymentGroup, 'legal_cost_allocations') !== false, 'Contract settlement does not persist approved legal costs.');
$assert(strpos($css, '--space-1: 4px') !== false && strpos($css, '--control-height: 48px') !== false, 'Shared spacing/control tokens are missing.');
$assert(strpos($js, 'initContractDetailWorkspace') !== false && strpos($js, 'initContractSettlement') !== false, 'Contract page CSP-safe JavaScript is missing.');
$assert(strpos($releaseBuilder, "'.playwright-cli/'") !== false, 'Release archives can include browser QA artifacts.');
echo "STATIC_V157_OK\n";
