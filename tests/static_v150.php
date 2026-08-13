<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.0', '>='), 'Core version must include V1.5 settlement features.');

foreach (['helpers/InstallmentFinancialStateService.php', 'helpers/PaymentAllocationService.php', 'helpers/SettlementQuoteService.php', 'helpers/LegalEligibilityService.php', 'helpers/LegalDocumentService.php'] as $path) {
    $assert(is_file($root . '/' . $path), 'Required V1.5 service is missing: ' . $path);
}
$settlement = (string) file_get_contents($root . '/helpers/InstallmentSettlementService.php');
$state = (string) file_get_contents($root . '/helpers/InstallmentFinancialStateService.php');
$assert(strpos($settlement, 'InstallmentFinancialStateService') !== false, 'Settlement guard does not use central financial state.');
$assert(strpos($state, "['cancelled', 'closed']") !== false && strpos($state, 'materialised contract status') !== false, 'Stale completed contracts are not explicitly handled by central state.');

$legalModel = (string) file_get_contents($root . '/models/LegalCase.php');
$legalView = (string) file_get_contents($root . '/views/legal/show.php');
$migration = (string) file_get_contents($root . '/database/migrations/2026_07_28_legal_eligibility_workflow.sql');
$assert(strpos($legalModel, 'createSelfInitiated') !== false && strpos($legalModel, 'legal_case_requests') !== false, 'Legal self-initiation idempotency is missing.');
$assert(strpos($legalModel, "status = 'archived'") !== false, 'Legal records are still physically removed instead of archived.');
$assert(strpos($legalView, 'ابلاغ رسمی') !== false && strpos($legalView, 'ثبت رسمی قضایی') !== false, 'Legal terminology safety notice is missing from UI.');
foreach (['legal_policy_versions', 'contract_legal_policy_snapshots', 'legal_documents', 'legal_case_requests'] as $table) {
    $assert(strpos($migration, $table) !== false, 'Legal migration is missing ' . $table . '.');
}
$builder = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($builder, "'/dist/core'") !== false && strpos($builder, "'/dist/updates'") !== false, 'Release output paths are not canonical.');
echo "STATIC_V150_OK\n";
