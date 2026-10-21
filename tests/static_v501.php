<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $value = file_get_contents($root . '/' . $relative);
    if ($value === false) throw new RuntimeException('Missing file: ' . $relative);
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$version = require $root . '/config/version.php';
$migration = $read('database/migrations/2026_10_09_v501_legal_cost_disbursements.sql');
$costService = $read('helpers/LegalCaseCostService.php');
$costSummary = $read('helpers/ContractLegalCostSummaryService.php');
$overdueService = $read('models/OverdueAggregationService.php');
$overdueView = $read('views/overdue/index.php');
$legalCostsView = $read('views/legal/costs.php');
$legalCaseView = $read('views/legal/show.php');
$overdueController = $read('controllers/OverdueController.php');
$legalController = $read('controllers/LegalController.php');
$installSql = $read('database/proma-pay-install.sql');
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '5.0.1', '>=') && version_compare((string) ($version['database'] ?? '0.0.0'), '2026.10.09', '>='), 'Core/database version is older than V5.0.1.');
$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS legal_cost_disbursements') !== false, 'Third-party expense ledger migration is missing.');
$assert(strpos($migration, 'INSERT IGNORE INTO legal_cost_disbursements') !== false && strpos($migration, 'legacy-cost-') !== false, 'Legacy company-paid cost status is not migrated idempotently.');
$assert(strpos($costService, 'recordThirdPartyPayment') !== false && strpos($costService, 'FOR UPDATE') !== false && strpos($costService, 'idempotency_key') !== false, 'Third-party payment is missing row locking or idempotency.');
$assert(strpos($costService, 'customer_receivable_changed') !== false && strpos($costService, 'reverseThirdPartyPayment') !== false, 'Third-party expense audit/reversal lifecycle is incomplete.');
$assert(strpos($costSummary, 'outstanding_chargeable_legal_costs') !== false && strpos($costSummary, 'legal_referred_at IS NOT NULL') !== false, 'Legal receivable summary is not bounded to referred cases.');
$assert(strpos($overdueService, 'buildOverdueBreakdown') !== false && strpos($overdueView, 'outstanding_legal_costs') !== false, 'Overdue debt breakdown is not wired into the contract queue.');
$assert(strpos($legalCostsView, 'paid_amount_toman') !== false && strpos($legalCostsView, 'company_paid_amount_toman') !== false && strpos($legalCostsView, 'customer_receivable_toman') !== false, 'Legal cost UI does not distinguish company outflow and customer collection.');
$assert(strpos($legalCostsView, 'reverseThirdPartyCostPayment') !== false && strpos($legalController, 'function reverseThirdPartyCostPayment') !== false, 'Third-party expense correction is not available from the legal cost ledger.');
$assert(strpos($legalCaseView, 'company_paid_legal_costs') !== false && strpos($legalCaseView, 'collected_legal_costs') !== false, 'Legal case summary does not distinguish paid-out expenses and customer receipts.');
$assert(strpos($overdueController, 'جریمه عادی قابل پرداخت') !== false && strpos($overdueController, 'هزینه حقوقی قابل وصول') !== false, 'Overdue export does not report the debt buckets separately.');
$assert(strpos($installSql, '`legal_cost_disbursements`') !== false, 'Fresh-install schema omits the third-party expense ledger.');
echo "STATIC_V501_OK\n";
