<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) throw new RuntimeException('Missing file: ' . $relative);
    return $contents;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$version = require $root . '/config/version.php';
$calls = $read('models/OperatorCall.php');
$overdueController = $read('controllers/OverdueController.php');
$overdueView = $read('views/overdue/index.php');
$overdueStyles = $read('assets/css/components/role-redesign.css');
$contractsController = $read('controllers/ContractsController.php');
$contractsView = $read('views/contracts/show.php');
$paymentsModel = $read('models/Payment.php');
$settlementQuotes = $read('helpers/SettlementQuoteService.php');
$paymentGroups = $read('helpers/PaymentGroupService.php');
$deliveryBuilder = $read('scripts/build_candidate_delivery_bundle.php');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '5.0.2', '>=') && strpos((string) ($version['display'] ?? ''), 'V') === 0, 'Core version no longer retains V5.0.2 features.');
$assert(strpos($calls, 'recentForContracts') !== false && strpos($calls, "LIMIT ' . \$limitPerContract") !== false, 'Recent call history is not bounded per contract.');
$assert(strpos($overdueController, 'OperatorCall::recentForContracts') !== false && strpos($overdueController, 'followupHistoryByContract') !== false, 'Overdue controller does not provide recent call history.');
$assert(substr_count($overdueView, 'proma-followup-recorded') >= 2 && strpos($overdueStyles, '.proma-followup-recorded') !== false, 'Desktop and mobile follow-up buttons do not show their recorded state.');
$assert(strpos($overdueView, 'proma-followup-history__item') !== false && strpos($overdueView, 'nl2br(e($recentFollowup[\'notes\']))') !== false, 'Follow-up modal history is missing or not escaped.');
$assert(strpos($contractsController, '!empty($_POST[\'include_legal_costs\'])') !== false && strpos($contractsController, "'contract_legal_first'") !== false, 'Contract payment endpoints do not honor legal-cost priority.');
$assert(strpos($contractsView, 'collect-contract-legal-costs') !== false && strpos($contractsView, "!== 'cancelled'") !== false && strpos($contractsView, "Auth::role() === 'admin'") !== false, 'The legal-cost receipt form is not available to admins for completed contracts with an outstanding balance.');
$paymentTypeAssignment = strpos($paymentsModel, '$paymentType = in_array(');
$completedLegalCostException = strpos($paymentsModel, '$allowCompletedLegalCost = $contractStatus === \'completed\' && $paymentType === \'legal_cost\';');
$assert($paymentTypeAssignment !== false && $completedLegalCostException !== false && $completedLegalCostException > $paymentTypeAssignment, 'Completed contracts only allow the explicit legal-cost payment type.');
$assert(strpos($settlementQuotes, '$legalFirstAmount = $legalFirst ? min($requested, $legalCostTotal) : 0;') !== false && strpos($settlementQuotes, '$installmentAmount = min(max(0, $requested - $legalFirstAmount), $baseTotal);') !== false, 'Legal-cost-first allocation does not route the remainder to open installments.');
$assert(strpos($paymentGroups, "'legal_cost_allocations'") !== false && strpos($paymentGroups, 'LegalCaseCostService::recordPaymentAllocation') !== false, 'Mixed legal-cost/installment receipts are not persisted in both ledgers.');
$assert(strpos($deliveryBuilder, "'release-notes/V' . \$version . '.md'") !== false, 'Candidate delivery package does not include release notes.');

echo "STATIC_V502_OK\n";
