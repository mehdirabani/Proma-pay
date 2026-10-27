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
$center = $read('models/CollectionCommandCenter.php');
$performance = $read('models/CollectionPerformanceService.php');
$calls = $read('models/OperatorCall.php');
$overdue = $read('models/OverdueAggregationService.php');
$criteria = $read('models/OverdueFilterCriteria.php');
$controllers = $read('controllers/CollectionsController.php') . $read('controllers/OperatorController.php') . $read('controllers/OverdueController.php');
$workspace = $read('views/collections/index.php');
$timeline = $read('views/collections/timeline.php');
$report = $read('views/collections/performance.php');
$migration = $read('database/migrations/2026_10_27_v704_collection_operations_workspace.sql');
$install = $read('database/proma-pay-install.sql');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '7.0.4', '>='), 'Collection operations must remain available in Core 7.0.4 and later.');
$assert(str_contains($center, 'OverdueAggregationService::paginated') && str_contains($center, 'FinancialDebtEngine::overdueBreakdown'), 'The workspace must reuse the current overdue aggregation and canonical debt engine.');
$assert(str_contains($center, 'function recordFollowup') && str_contains($center, 'function updatePromise') && str_contains($center, 'function assignContract'), 'Unified follow-up, promise history, or ownership assignment is missing.');
$assert(str_contains($center, 'collection:next-action:') && str_contains($center, 'collection:assign:') && str_contains($center, 'function completeTask'), 'Idempotent next actions or guarded completion is missing.');
$assert(str_contains($center, 'function timeline(int $contractId, int $page') && str_contains($center, 'LIMIT \' . $pageSize . \' OFFSET'), 'Timeline does not use bounded server-side pagination.');
$assert(!str_contains($overdue, 'MAX(promise_payment_date)') && str_contains($overdue, 'active_promise'), 'Overdue promise filtering still relies on MAX(promise_payment_date).');
$assert(str_contains($criteria, 'operator_id') && str_contains($overdue, 'operatorId === 0') && str_contains($overdue, "owner_user.status='active'"), 'The invalid/unassigned owner filter is not supported.');
$assert(!str_contains($controllers, 'OperatorCall::createCall'), 'A contact route still writes through a separate legacy path.');
$assert(str_contains($calls, 'function insertStructured') && str_contains($migration, 'result_code') && str_contains($migration, 'baseline_overdue_amount_toman'), 'Structured follow-up and report snapshots are not append-only on the existing call ledger.');
$assert(str_contains($performance, 'collection-kpi-v1') && str_contains($performance, 'firstEligibleFollowups') && str_contains($performance, 'paymentsForEvents') && str_contains($performance, 'paymentEvidenceAfter'), 'Versioned cohort/payment KPI logic is missing.');
$assert(str_contains($performance, 'is_corrected') && str_contains($performance, 'is_reversal') && str_contains($performance, 'needs_review_same_day'), 'Correction/reversal exclusions or same-day ambiguity reporting is missing.');
$assert(str_contains($performance, 'COUNT(DISTINCT CASE WHEN result_code IS NOT NULL THEN contract_id END)') && str_contains($performance, 'promise_fulfillment_rate'), 'Classified K1 denominators or K8 promise performance are incomplete.');
$assert(str_contains($performance, 'tasks_completed') && str_contains($performance, 'tasks_overdue') && str_contains($performance, 'promises_registered') && str_contains($performance, 'active_contracts'), 'K8 must report operator portfolio, calls, task completion/overdue, promises, and recovery.');
$assert(str_contains($workspace, 'صف اصلی وصول') && str_contains($workspace, 'collections/followup') && str_contains($workspace, 'collections/assignContract'), 'The main workspace lacks a contract-level worklist, follow-up, or assignment form.');
$assert(str_contains($timeline, 'page[\'pages\']') && str_contains($timeline, 'collections/promiseAction'), 'Timeline pagination or append-only promise controls are missing.');
$assert(str_contains($report, 'K1') && str_contains($report, 'K8') && str_contains($report, 'promise_fulfillment_rate') && str_contains($report, 'داده کافی نیست'), 'The report must expose KPI definitions and undefined-rate states.');
$assert(str_contains($install, '`action_type` varchar(40) DEFAULT NULL') && str_contains($install, '`promise_amount_toman` bigint(20) unsigned DEFAULT NULL') && str_contains($install, '`completion_result` varchar(40) DEFAULT NULL'), 'Fresh installation lacks the collection workspace columns.');

echo "STATIC_COLLECTION_OPERATIONS_V704_OK\n";
