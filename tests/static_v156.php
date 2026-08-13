<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$version = require $root . '/config/version.php';
$settings = require $root . '/config/settings.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.7', '>='), 'Current version metadata regressed.');
$assert(($version['display'] ?? '') === 'V' . ($version['application'] ?? ''), 'Current version metadata is not synchronized.');
$assert(($settings['asset_version'] ?? '') === ($version['application'] ?? ''), 'Current asset version is not synchronized.');

$engine = (string) file_get_contents($root . '/helpers/InstallmentFinancialStateService.php');
$comparison = (string) file_get_contents($root . '/helpers/PenaltyComparisonService.php');
$presentation = (string) file_get_contents($root . '/helpers/CustomerPenaltyPresentationService.php');
$allocation = (string) file_get_contents($root . '/helpers/PaymentAllocationService.php');
$paymentGroups = (string) file_get_contents($root . '/helpers/PaymentGroupService.php');
$receipt = (string) file_get_contents($root . '/models/PaymentReceipt.php');
$quotes = (string) file_get_contents($root . '/helpers/SettlementQuoteService.php');
$accounting = (string) file_get_contents($root . '/plugins/PromaAccounting/src/AccountingServiceProvider.php');
$legalCase = (string) file_get_contents($root . '/models/LegalCase.php');
$installment = (string) file_get_contents($root . '/models/Installment.php');
$migration = (string) file_get_contents($root . '/database/migrations/2026_07_29_legal_penalty_projection_v156.sql');
$settingsView = (string) file_get_contents($root . '/views/settings/index.php');
$serviceWorker = (string) file_get_contents($root . '/service-worker.js');

$assert(strpos($engine, "installment-financial-state-v3") !== false, 'Financial engine version was not advanced.');
$assert(strpos($engine, "'effective_penalty_payable'") !== false && strpos($engine, "'projected_legal_penalty'") !== false, 'Canonical penalty fields are missing from the engine.');
$assert(strpos($comparison, 'projectedLegalPenalty') !== false && strpos($presentation, 'show_projected_legal_penalty') !== false, 'Projection/presentation services are missing.');
$assert(
    strpos($allocation, 'projected_legal_penalty') === false
    && strpos($paymentGroups, 'projected_legal_penalty') === false
    && strpos($quotes, 'projected_legal_penalty') === false
    && strpos($receipt, 'projected_legal_penalty') === false
    && strpos($accounting, 'projected_legal_penalty') === false,
    'Projected penalties must not enter payment, receipt, settlement, or Accounting boundaries.'
);
$assert(strpos($legalCase, 'legal_referred_at = COALESCE') !== false && strpos($legalCase, "'under_legal_review', 'تشکیل پرونده داخلی'") !== false, 'Legal referral persistence is not safely separated from internal review.');
$assert(strpos($installment, 'MIN(lc.legal_referred_at)') !== false && strpos($installment, 'MIN(lc.created_at) FROM legal_cases') === false, 'Installment engine still derives legal start from case creation.');
$assert(strpos($migration, 'show_projected_legal_penalty_to_customer') !== false && strpos($migration, "legal_referred_at = NULL") !== false, 'V1.5.6 migration is incomplete.');
$assert(strpos($settingsView, 'projected_legal_penalty_customer_message') !== false, 'Settings UI does not expose the customer comparison policy.');
$assert(strpos($serviceWorker, 'caches.keys') !== false && strpos($serviceWorker, 'fetch') !== false, 'Service-worker cleanup contract is missing.');

echo "STATIC_V156_OK\n";
