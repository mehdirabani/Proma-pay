<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) throw new RuntimeException('Missing file: ' . $path);
    return $content;
};
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

$version = require $root . '/config/version.php';
$engine = $read('helpers/FinancialDebtEngine.php');
$compat = $read('helpers/ContractFinancialSummaryService.php');
$ledger = $read('helpers/FinancialLedgerService.php');
$payment = $read('models/Payment.php');
$group = $read('helpers/PaymentGroupService.php');
$risk = $read('helpers/CreditRiskEngine.php');
$collection = $read('models/CollectionCommandCenter.php');
$cron = $read('controllers/CronController.php');
$announcement = $read('models/SystemAnnouncement.php');
$notifications = $read('models/Notification.php');
$migration = $read('database/migrations/2026_10_12_v600_financial_engine_risk_collections_announcements.sql');
$install = $read('database/proma-pay-install.sql');
$settings = $read('views/settings/index.php');
$sellerSignature = $read('models/ContractDocument.php');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '6.0.2', '>=') && version_compare((string) ($version['database'] ?? '0.0.0'), '2026.10.12', '>='), 'V6 financial/risk feature or minimum database schema version is missing.');
$assert(strpos($engine, 'class FinancialDebtEngine') !== false && strpos($compat, 'FinancialDebtEngine::summarize') !== false, 'Legacy financial summary callers do not delegate to the V6 debt engine.');
$assert(strpos($ledger, 'financial_ledger_events') !== false && strpos($ledger, 'idempotency_key') !== false && strpos($ledger, 'payment-reversal:') !== false, 'Append-only payment journal or idempotent correction path is missing.');
$assert(strpos($payment, 'FinancialLedgerService::append') !== false && strpos($payment, 'FinancialLedgerService::reversePayment') !== false && strpos($group, 'FinancialLedgerService::payment') !== false, 'Direct/grouped payment and correction paths are not journaled.');
$assert(strpos($risk, 'on_time_rate') !== false && strpos($risk, 'reasons') !== false && strpos($risk, 'returned_checks\' => null') !== false && strpos($risk, 'advisory_only') !== false, 'Explainable risk profile or unsupported returned-check handling is missing.');
$assert(strpos($collection, 'runStageAutomation') !== false && strpos($collection, 'collection_tasks') !== false && strpos($collection, "legalCriteria->legalEligible = 'yes'") !== false && strpos($collection, 'OverdueAggregationService::paginated') !== false, 'Collection queues, stage notices, or legal eligibility integration is missing.');
$assert(strpos($cron, 'function collectionStages') !== false && strpos($cron, "CronLock::acquire('collection-stages')") !== false && strpos($settings, 'route=cron/collection-stages') !== false, 'Token-protected daily collection stage automation is not wired to cron.');
$assert(strpos($announcement, 'system_announcement_recipients') !== false && strpos($announcement, 'normalizeLocalRoute') !== false, 'Global announcement audience/read-state or local-link validation is missing.');
$assert(strpos($notifications, 'eventTypeEnabled') !== false && strpos($notifications, 'SystemAnnouncement::forUser') !== false, 'Action-driven notification controls or global inbox integration is missing.');
$assert(strpos($migration, 'financial_ledger_events') !== false && strpos($migration, 'system_announcements') !== false && strpos($migration, 'collection_tasks') !== false, 'Additive V6 migration schema is incomplete.');
$assert(strpos($install, 'CREATE TABLE IF NOT EXISTS `financial_ledger_events`') !== false && strpos($install, 'CREATE TABLE IF NOT EXISTS `system_announcement_recipients`') !== false, 'Fresh installation does not include the V6 feature schema.');
$assert(strpos($settings, 'credit_risk_rules_json') !== false && strpos($settings, 'collection_stage_rules_json') !== false && strpos($settings, 'notification_event_types[]') !== false && strpos($settings, 'payment_legal_cost_priority') !== false, 'Admin rule, allocation priority, and event configuration is missing.');
$assert(strpos($sellerSignature, 'injectSellerSignature') !== false && strpos($sellerSignature, 'contract_seller_signature_path') !== false, 'Configured seller signature is not injected into generated contracts.');

echo "STATIC_V600_FINANCIAL_RISK_COLLECTIONS_OK\n";
