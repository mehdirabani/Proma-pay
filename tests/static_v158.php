<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$legalController = $read('controllers/LegalController.php');
$legalCase = $read('models/LegalCase.php');
$documentService = $read('helpers/LegalDocumentService.php');
$settings = $read('models/Settings.php');
$settingsView = $read('views/settings/index.php');
$legalView = $read('views/legal/show.php');
$legalCostSummary = $read('helpers/ContractLegalCostSummaryService.php');
$qaSeed = $read('tests/qa_seed_roles_v150.php');
$overdueIntegration = $read('tests/integration_overdue_aggregation_v153.php');

$assert(strpos($legalController, 'function storeCost($id)') !== false, 'Lawyer cannot submit a legal cost from the case view.');
$assert(strpos($legalController, "'canRegisterLegalCost'") !== false, 'Legal cost permission is not supplied to the case view.');
$assert(strpos($legalView, "url('legal/storeAttachment/") !== false && strpos($legalView, "url('legal/storeCost/") !== false, 'Case attachments or cost form is not reachable.');
$assert(strpos($legalView, 'ثبت برای تأیید') !== false, 'Legal cost approval boundary is not communicated in the UI.');
$assert(strpos($legalCase, "'under_legal_review'") !== false && strpos($legalCase, "allow_self_initiation") !== false, 'Independent internal legal case workflow is missing.');
$assert(strpos($legalCase, 'empty($eligibility[\'eligible\'])') === false, 'Independent internal case creation is still wrongly limited to overdue eligibility.');
$assert(strpos($settings, 'legal_contractual_warning_template') !== false && strpos($settings, 'legal_petition_draft_template') !== false && strpos($settings, 'legal_complaint_draft_template') !== false, 'Default legal templates are not persisted in settings.');
$assert(strpos($settingsView, 'قالب پیش‌فرض اخطار قراردادی داخلی') !== false && strpos($settingsView, 'قالب پیش‌فرض پیش‌نویس دادخواست') !== false && strpos($settingsView, 'قالب پیش‌فرض پیش‌نویس شکواییه') !== false, 'Legal template fields are not exposed in settings.');
$assert(strpos($documentService, "'{{contract_number}}'") !== false && strpos($documentService, 'return strtr($template, $tokens)') !== false, 'Legal template placeholders are not resolved by the server.');
$assert(strpos($legalController, 'ContractInstallmentFinancialBatchService::load') !== false && strpos($legalController, 'Payment::forInstallment') === false, 'The legal financial screen still has an N+1 or duplicate calculation path.');
$assert(strpos($legalController, 'ContractFinancialSummaryService::summarize') !== false && strpos($legalController, 'ContractLegalCostSummaryService::forContract') !== false, 'Legal finance does not use the canonical contract totals.');
$assert(strpos($legalCostSummary, 'pending_approval_legal_costs') !== false && strpos($legalCostSummary, 'collected_legal_costs') !== false, 'Pending and collected legal cost totals are unavailable.');
$assert(strpos($legalView, 'هزینه در انتظار تأیید') !== false && strpos($legalView, 'مبلغ واقعی قابل پرداخت امروز') !== false && strpos($legalView, 'ریز هزینه‌های حقوقی') !== false, 'Legal cost states or canonical payable amount are not visible.');
$assert(strpos($qaSeed, 'PROMA_TEST_DB_DSN is required for the QA seed') !== false && strpos($qaSeed, 'new PDO($dsn') !== false, 'QA seed can fall back to a non-isolated developer database.');
$assert(strpos($overdueIntegration, 'PROMA_TEST_DB_DSN is required for this destructive integration test') !== false && strpos($overdueIntegration, 'new PDO($dsn') !== false, 'Overdue integration can fall back to a non-isolated developer database.');

echo "STATIC_V158_OK\n";
