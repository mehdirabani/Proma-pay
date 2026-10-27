<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (preg_match('/(?:^|;)dbname=proma_qa_collection_ops_[a-f0-9]{10}(?:;|$)/i', $dsn) !== 1 || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use an isolated proma_qa_collection_ops_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}

putenv('PROMA_DB_DSN=' . $dsn);
putenv('PROMA_DB_USER=' . ((string) getenv('PROMA_TEST_DB_USER') ?: 'root'));
putenv('PROMA_DB_PASSWORD=' . (string) getenv('PROMA_TEST_DB_PASSWORD'));
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.93';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);
(new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema'))->invoke(null);
$migrationRunner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$migrationName = 'database/migrations/2026_10_27_v704_collection_operations_workspace.sql';
$migrationRunner->invoke(null, $migrationName, (string) file_get_contents(dirname(__DIR__) . '/' . $migrationName));

$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$passes = [];
$pass = static function (string $label) use (&$passes): void { $passes[] = $label; };
$expectFailure = static function (callable $callback, string $message) use ($assert): Throwable {
    try { $callback(); } catch (Throwable $e) { return $e; }
    throw new RuntimeException($message . ' was accepted unexpectedly.');
};
$uuid = static fn (): string => bin2hex(random_bytes(8));
$user = static function (string $role, string $tag) use ($uuid): int {
    $name = 'qa-collection-' . $tag . '-' . $uuid();
    Model::execute('INSERT INTO users (role,username,full_name,password_hash,status,created_at) VALUES (?,?,?,?,?,NOW())', [$role, $name, 'آزمون وصول ' . $tag, password_hash($uuid(), PASSWORD_DEFAULT), 'active']);
    return (int) Model::db()->lastInsertId();
};
$customer = $user('customer', 'customer');
$admin = $user('admin', 'admin');
$operatorA = $user('operator', 'operator-a');
$operatorB = $user('operator', 'operator-b');
$operatorC = $user('operator', 'operator-c');
$operatorD = $user('operator', 'operator-d');
$operatorE = $user('operator', 'operator-e');
Settings::saveMany(['collection_assignment_manager_id' => (string) $admin]);

$contractSequence = 0;
$makeContract = static function (?int $owner, int $installmentCount = 1, int $amount = 100000, int $daysLate = 15, ?string $label = null) use (&$contractSequence, $customer, $uuid): array {
    $contractSequence++;
    $token = strtoupper(substr($uuid(), 0, 7));
    $prefix = 'Q' . $token;
    $number = $label ?: ('QA-' . $token . '-' . str_pad((string) $contractSequence, 5, '0', STR_PAD_LEFT));
    $due = date('Y-m-d', strtotime('-' . max(1, $daysLate) . ' days'));
    Model::execute(
        'INSERT INTO contracts (customer_id,contract_number,prefix,serial,principal_amount,interest_type,months,start_date,first_due_date,status,assigned_operator_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$customer, $number, $prefix, $contractSequence, $amount * $installmentCount, 'simple', $installmentCount, date('Y-m-d', strtotime('-1 month')), $due, 'active', $owner]
    );
    $contractId = (int) Model::db()->lastInsertId();
    $installmentIds = [];
    for ($i = 1; $i <= $installmentCount; $i++) {
        $installmentDue = date('Y-m-d', strtotime($due . ' +' . ($i - 1) . ' month'));
        Model::execute(
            "INSERT INTO installments (contract_id,installment_number,due_date,base_amount,paid_amount,remaining_amount,status,created_at) VALUES (?,?,?,?,0,?,'overdue',NOW())",
            [$contractId, $i, $installmentDue, $amount, $amount]
        );
        $installmentIds[] = (int) Model::db()->lastInsertId();
    }
    return ['id' => $contractId, 'installments' => $installmentIds, 'customer_id' => $customer, 'number' => $number];
};
$task = static function (int $contractId, int $customerId, ?int $owner, string $key, string $action = 'call_customer', ?string $due = null) use ($admin): int {
    Model::execute(
        "INSERT INTO collection_tasks (contract_id,customer_id,assigned_to,stage,status,due_at,dedupe_key,created_at,action_type,priority,created_by) VALUES (?,?,?,'operator_task','open',?,?,NOW(),?,'normal',?)",
        [$contractId, $customerId, $owner, $due ?: date('Y-m-d 23:59:59', strtotime('+1 day')), $key, $action, $admin]
    );
    return (int) Model::db()->lastInsertId();
};
$followup = static function (array $contract, int $actor, string $role = 'operator', array $options = []): int {
    return CollectionCommandCenter::recordFollowup(array_merge([
        'contract_id' => (int) $contract['id'], 'installment_id' => (int) ($contract['installments'][0] ?? 0),
        'action_type' => 'customer_call', 'contact_target' => 'customer', 'result_code' => 'contacted',
        'next_action_type' => 'call_customer', 'next_followup_date' => date('Y-m-d', strtotime('+1 day')),
        'notes' => 'آزمون یکپارچگی وصول',
    ], $options), $actor, $role);
};
$overdueCount = static function (int $contractId, ?int $operator = null): int {
    $criteria = new OverdueFilterCriteria(); $criteria->contractId = $contractId; $criteria->perPage = 25;
    return (int) OverdueAggregationService::paginated($criteria, $operator, false)['total'];
};
$postAllocatedPayment = static function (array $contract, int $installmentId, int $amount, string $paidAt, bool $corrected = false, ?int $principalApplied = null, int $normalApplied = 0) use ($customer, $admin, $uuid): int {
    $principalApplied = $principalApplied ?? $amount;
    $groupNumber = 'QA-G-' . strtoupper($uuid());
    Model::execute(
        "INSERT INTO payment_groups (group_number,contract_id,customer_id,created_by,requested_amount,allocated_amount,method,status,idempotency_key,allocation_scope,description,created_at,completed_at) VALUES (?,?,?,?,?,?,'manual','paid',?,'selected','QA collection acceptance',NOW(),NOW())",
        [$groupNumber, $contract['id'], $customer, $admin, $amount, $amount, 'qa-' . $uuid()]
    );
    $groupId = (int) Model::db()->lastInsertId();
    Model::execute(
        'INSERT INTO payments (installment_id,payment_group_id,contract_id,user_id,amount,method,status,description,payment_date,principal_applied,normal_penalty_applied,legal_penalty_applied,payment_type,is_corrected,paid_at,created_at) VALUES (?,?,?,?,?,\'manual\',\'paid\',\'QA allocation\',?,?,?,? ,\'installment\',?,?,NOW())',
        [$installmentId, $groupId, $contract['id'], $customer, $amount, substr($paidAt, 0, 10), $principalApplied, $normalApplied, 0, (int) $corrected, $paidAt]
    );
    $paymentId = (int) Model::db()->lastInsertId();
    Model::execute(
        "INSERT INTO payment_allocations (payment_group_id,payment_id,contract_id,installment_id,allocated_amount,principal_applied,normal_penalty_applied,remaining_before,remaining_after,status_after,created_at) VALUES (?,?,?,?,?,?,?,?,?,'partial',NOW())",
        [$groupId, $paymentId, $contract['id'], $installmentId, $amount, $principalApplied, $normalApplied, $amount, 0]
    );
    if (!$corrected) {
        $row = Model::fetch('SELECT base_amount,paid_amount FROM installments WHERE id=?', [$installmentId]);
        $paid = min((int) $row['base_amount'], (int) $row['paid_amount'] + $principalApplied);
        $status = $paid >= (int) $row['base_amount'] ? 'paid' : 'partial';
        Model::execute('UPDATE installments SET paid_amount=?,remaining_amount=?,status=?,last_payment_date=? WHERE id=?', [$paid, max(0, (int) $row['base_amount'] - $paid), $status, substr($paidAt, 0, 10), $installmentId]);
    }
    return $paymentId;
};

// T01–T03: contract-level grouping and explicit unassigned-owner queue.
$one = $makeContract($operatorA, 1, 100000, 15);
$many = $makeContract($operatorA, 3, 100000, 15);
$assert($overdueCount($one['id']) === 1, 'T01: a single overdue installment must produce one contract work item.');
$assert($overdueCount($many['id']) === 1, 'T02: multiple overdue installments must remain one contract work item.');
$unassigned = $makeContract(null, 1, 100000, 15);
$unassignedCriteria = new OverdueFilterCriteria(); $unassignedCriteria->operatorId = 0; $unassignedCriteria->perPage = 100;
$unassignedQueue = OverdueAggregationService::paginated($unassignedCriteria, null, false);
$assert(in_array($unassigned['id'], array_column($unassignedQueue['items'], 'contract_id'), true), 'T03: an unassigned overdue contract must be visible in the unassigned queue.');
$pass('T01 one overdue installment yields one contract'); $pass('T02 several overdue installments remain one contract'); $pass('T03 missing owner is surfaced for assignment');

// T04: changing owner synchronizes open work and writes an audit event.
$assignable = $makeContract(null, 1, 100000, 18);
$assignmentTask = $task($assignable['id'], $customer, null, 'qa-assignment-' . $uuid());
CollectionCommandCenter::assignContract($assignable['id'], $operatorA, $admin);
$assert((int) Model::fetch('SELECT assigned_operator_id FROM contracts WHERE id=?', [$assignable['id']])['assigned_operator_id'] === $operatorA, 'T04: contract owner was not updated.');
$assert((int) Model::fetch('SELECT assigned_to FROM collection_tasks WHERE id=?', [$assignmentTask])['assigned_to'] === $operatorA, 'T04: open task owner was not synchronized.');
$assert((int) (Model::fetch("SELECT COUNT(*) AS n FROM audit_logs WHERE event_type='collection_assignment' AND contract_id=?", [$assignable['id']])['n'] ?? 0) > 0, 'T04: ownership change was not audited.');
$pass('T04 owner reassignment syncs tasks and audit history');

// T05–T08: both legacy entry routes converge on one append-only service and enforce next action.
$operatorCall = $makeContract($operatorA, 1, 100000, 20);
$callId = $followup($operatorCall, $operatorA);
$call = Model::fetch('SELECT * FROM operator_calls WHERE id=?', [$callId]);
$assert(($call['result_code'] ?? '') === 'contacted' && !empty($call['next_followup_date']), 'T05: operator follow-up was not structured or lost its next date.');
$nextTask = Model::fetch("SELECT * FROM collection_tasks WHERE contract_id=? AND status='open' AND dedupe_key=?", [$operatorCall['id'], 'collection:next-action:' . $operatorCall['id']]);
$assert($nextTask && (int) $nextTask['related_followup_id'] === $callId, 'T07: follow-up did not create/link its next action.');
$legacyRoute = $makeContract($operatorA, 1, 100000, 22);
$legacyCallId = $followup($legacyRoute, $operatorA, 'operator', ['result_code' => '', 'call_result' => 'نتیجهٔ قدیمی نامشخص']);
$legacyCall = Model::fetch('SELECT result_code,call_result,next_followup_date FROM operator_calls WHERE id=?', [$legacyCallId]);
$assert($legacyCall['result_code'] === null && $legacyCall['call_result'] === 'نتیجهٔ قدیمی نامشخص' && !empty($legacyCall['next_followup_date']), 'T06: an unclassified call must preserve text and the explicit next date.');
$expectFailure(static fn () => CollectionCommandCenter::completeTask((int) $nextTask['id'], $admin, true, ['result' => 'action_completed']), 'T08 completion without a next action');
CollectionCommandCenter::completeTask((int) $nextTask['id'], $admin, true, ['result' => 'action_completed', 'next_action_type' => 'call_customer', 'next_followup_date' => date('Y-m-d', strtotime('+2 days'))]);
$assert((int) (Model::fetch("SELECT COUNT(*) AS n FROM collection_tasks WHERE contract_id=? AND status='open' AND action_type='call_customer'", [$operatorCall['id']])['n'] ?? 0) === 1, 'T08: completing an open-debt action did not leave a next action.');
$pass('T05 operator contact uses shared structured history'); $pass('T06 overdue legacy route retains text, classification stays null'); $pass('T07 next action is linked to the follow-up'); $pass('T08 open debt cannot be left without a next action');

// T09–T11: promise lifecycle is append-only and uses the current event rather than MAX(date).
$promiseActive = $makeContract($operatorA, 1, 100000, 24);
$promiseId = $followup($promiseActive, $operatorA, 'operator', ['result_code' => 'promise_given', 'promise_payment_date' => date('Y-m-d', strtotime('+2 days')), 'promise_amount_toman' => '40000']);
$promiseCriteria = new OverdueFilterCriteria(); $promiseCriteria->contractId = $promiseActive['id'];
$promiseOverview = CollectionCommandCenter::overview($operatorA, $promiseCriteria);
$activeRow = $promiseOverview['worklist']['items'][0] ?? [];
$assert(($activeRow['active_promise']['derived_status'] ?? '') === 'active', 'T09: current promise state was not shown as active.');
$promiseExpired = $makeContract($operatorA, 1, 100000, 26);
$expiredPromiseId = $followup($promiseExpired, $operatorA, 'operator', ['result_code' => 'promise_given', 'promise_payment_date' => date('Y-m-d', strtotime('+2 days')), 'promise_amount_toman' => '50000']);
Model::execute('UPDATE operator_calls SET promise_payment_date=?,next_followup_date=? WHERE id=?', [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), $expiredPromiseId]);
$expiryOverview = CollectionCommandCenter::overview($operatorA, (static function () use ($promiseExpired) { $c = new OverdueFilterCriteria(); $c->contractId = $promiseExpired['id']; return $c; })());
$assert(($expiryOverview['worklist']['items'][0]['active_promise']['derived_status'] ?? '') === 'broken', 'T10: matured promise without eligible payment should be broken.');
CollectionCommandCenter::updatePromise($promiseId, $operatorA, 'operator', 'reschedule', date('Y-m-d', strtotime('+5 days')), null, 'درخواست مهلت ثبت‌شده در آزمون');
$timelinePromise = CollectionCommandCenter::timeline($promiseActive['id']);
$assert((int) ($timelinePromise['active_promise']['id'] ?? 0) !== $promiseId && (int) (Model::fetch('SELECT COUNT(*) AS n FROM operator_calls WHERE id=?', [$promiseId])['n'] ?? 0) === 1, 'T11: rescheduling must append a successor and retain the original promise.');
$pass('T09 active promise is visible'); $pass('T10 matured unpaid promise is broken'); $pass('T11 rescheduling appends an event and retains prior promise');

// T12–T17: cohort, valid allocation, timing, correction, partial payment, duplicate calls, actor-time attribution.
$cohortContracts = [];
foreach (['after' => $operatorC, 'before' => $operatorC, 'corrected' => $operatorC, 'partial' => $operatorC, 'multi' => $operatorC] as $case => $owner) {
    $contract = $makeContract($owner, 1, 100000, 30);
    $firstCall = $followup($contract, $owner);
    $eventTime = date('Y-m-d H:i:s', strtotime('-10 days'));
    Model::execute('UPDATE operator_calls SET created_at=? WHERE id=?', [$eventTime, $firstCall]);
    $cohortContracts[$case] = ['contract' => $contract, 'first_call' => $firstCall, 'event_time' => $eventTime];
}
$afterPaymentId = $postAllocatedPayment($cohortContracts['after']['contract'], $cohortContracts['after']['contract']['installments'][0], 100000, date('Y-m-d H:i:s', strtotime('-8 days')));
$postAllocatedPayment($cohortContracts['before']['contract'], $cohortContracts['before']['contract']['installments'][0], 10000, date('Y-m-d H:i:s', strtotime('-11 days')));
$postAllocatedPayment($cohortContracts['corrected']['contract'], $cohortContracts['corrected']['contract']['installments'][0], 100000, date('Y-m-d H:i:s', strtotime('-8 days')), true);
$postAllocatedPayment($cohortContracts['partial']['contract'], $cohortContracts['partial']['contract']['installments'][0], 25000, date('Y-m-d H:i:s', strtotime('-8 days')));
$secondCall = $followup($cohortContracts['multi']['contract'], $operatorC);
Model::execute('UPDATE operator_calls SET created_at=? WHERE id=?', [date('Y-m-d H:i:s', strtotime('-9 days')), $secondCall]);
$postAllocatedPayment($cohortContracts['multi']['contract'], $cohortContracts['multi']['contract']['installments'][0], 10000, date('Y-m-d H:i:s', strtotime('-8 days')));
$reportFrom = date('Y-m-d', strtotime('-30 days')); $reportTo = date('Y-m-d');
$cohortReport = CollectionPerformanceService::report($reportFrom, $reportTo, $operatorC, 7);
$assert($cohortReport['collection']['matured_cohort'] === 5, 'T12–T16: expected five mature first-follow-up cohorts.');
$assert($cohortReport['collection']['collected_contracts'] === 3, 'T12/T13/T14/T15: payment time and correction filters were not applied.');
$assert($cohortReport['collection']['gross_collected_toman'] === 135000, 'T15/T16: unique allocated amount was double-counted or lost.');
$assert($cohortReport['collection']['rate'] === 0.6, 'T12–T16: contract collection rate is incorrect.');
$afterEvidence = array_values(array_filter($cohortReport['evidence']['items'] ?? [], static fn (array $row): bool => (int) ($row['followup_id'] ?? 0) === (int) $cohortContracts['after']['first_call']))[0] ?? [];
$assert((int) ($afterEvidence['collected_toman'] ?? 0) === 100000 && (int) ($afterEvidence['payments'][0]['payment_id'] ?? 0) === $afterPaymentId, 'K4/K5 evidence must expose the exact eligible payment and amount.');
$operatorKpi = $cohortReport['operators'][0] ?? [];
$assert(($cohortReport['followups']['contracts'] ?? 0) === 5 && ($operatorKpi['calls_registered'] ?? 0) >= 6 && ($operatorKpi['active_contracts'] ?? 0) >= 5, 'K1/K8: classified unique contracts, registered calls, or active operator portfolio are missing.');
CollectionCommandCenter::assignContract($cohortContracts['after']['contract']['id'], $operatorB, $admin);
$operatorReport = CollectionPerformanceService::report($reportFrom, $reportTo, $operatorC, 7);
$assert(($operatorReport['operators'][0]['operator_id'] ?? 0) === $operatorC && ($operatorReport['operators'][0]['matured_cohort'] ?? 0) === 5 && ($operatorReport['operators'][0]['active_contracts'] ?? 0) < ($operatorKpi['active_contracts'] ?? 0), 'T17: performance must stay attributed to the action-time operator after reassignment while current portfolio changes.');
$pass('T12 valid post-follow-up payment joins the cohort'); $pass('T13 pre-follow-up payment is excluded'); $pass('T14 corrected payment is excluded'); $pass('T15 partial payment contributes only allocated amount'); $pass('T16 repeated calls do not duplicate a contract cohort'); $pass('T17 reassignment preserves action-time operator attribution');

// T18–T21: repeated automation, settlement, reopened debt and legal state preservation.
$automationContract = $makeContract($operatorA, 1, 80000, 35);
$firstCreated = CollectionCommandCenter::runStageAutomation($admin);
$openBefore = (int) (Model::fetch("SELECT COUNT(*) AS n FROM collection_tasks WHERE contract_id=? AND status='open'", [$automationContract['id']])['n'] ?? 0);
$secondCreated = CollectionCommandCenter::runStageAutomation($admin);
$openAfter = (int) (Model::fetch("SELECT COUNT(*) AS n FROM collection_tasks WHERE contract_id=? AND status='open'", [$automationContract['id']])['n'] ?? 0);
$assert($openBefore === 1 && $openAfter === 1, 'T18: repeated automation created a duplicate active task.');
$settledTask = $task($automationContract['id'], $customer, $operatorA, 'qa-settled-' . $uuid());
$settlementSnapshot = FinancialDebtEngine::overdueBreakdown($automationContract['id'], date('Y-m-d'));
$settlementTotal = (int) $settlementSnapshot['total_overdue_debt'];
$installmentBase = (int) Model::fetch('SELECT base_amount FROM installments WHERE id=?', [$automationContract['installments'][0]])['base_amount'];
$settlementPaymentId = $postAllocatedPayment($automationContract, $automationContract['installments'][0], $settlementTotal, date('Y-m-d H:i:s'), false, $installmentBase, max(0, $settlementTotal - $installmentBase));
CollectionCommandCenter::runStageAutomation($admin);
$assert((Model::fetch('SELECT status,completion_result FROM collection_tasks WHERE id=?', [$settledTask])['completion_result'] ?? '') === 'contract_settled', 'T19: settled-contract tasks were not cancelled with a reason.');
$assert($overdueCount($automationContract['id']) === 0, 'T19: settled contract remained in the overdue queue.');
Model::execute("UPDATE payments SET is_corrected=1,status='corrected',corrected_at=NOW(),corrected_by=?,correction_reason='QA reverse' WHERE id=?", [$admin, $settlementPaymentId]);
Model::execute("UPDATE installments SET paid_amount=0,remaining_amount=base_amount,status='overdue' WHERE id=?", [$automationContract['installments'][0]]);
CollectionCommandCenter::runStageAutomation($admin);
$assert((int) (Model::fetch("SELECT COUNT(*) AS n FROM collection_tasks WHERE contract_id=? AND status='open'", [$automationContract['id']])['n'] ?? 0) === 1, 'T20: reopened overdue debt did not regain an active task.');
$legal = $makeContract($operatorA, 1, 50000, 35);
Model::execute("UPDATE contracts SET legal_status='under_legal_review' WHERE id=?", [$legal['id']]);
CollectionCommandCenter::runStageAutomation($admin);
$assert((Model::fetch('SELECT legal_status FROM contracts WHERE id=?', [$legal['id']])['legal_status'] ?? '') === 'under_legal_review', 'T21: collection automation changed existing legal state.');
$pass('T18 automation is idempotent'); $pass('T19 settlement closes queue tasks and preserves records'); $pass('T20 reopened debt gets a new task'); $pass('T21 legal state is preserved without automatic referral');

// T22–T26: ownership authorization, zero denominator, incomplete window, 1000-row paging, amountless promise review.
$foreign = $makeContract($operatorB, 1, 75000, 10);
$denied = $expectFailure(static fn () => $followup($foreign, $operatorA), 'T22 cross-operator follow-up');
$assert((int) $denied->getCode() === 403, 'T22: unauthorized operator was not rejected as forbidden.');
$emptyReport = CollectionPerformanceService::report('2020-01-01', '2020-01-02', $operatorE, 7);
$assert($emptyReport['collection']['rate'] === null && $emptyReport['tasks']['rate'] === null, 'T23 zero denominator must be represented as no data.');
$immatureContract = $makeContract($operatorD, 1, 90000, 12);
$followup($immatureContract, $operatorD);
$immatureReport = CollectionPerformanceService::report(date('Y-m-d'), date('Y-m-d'), $operatorD, 7);
$assert(($immatureReport['collection']['pending_window'] ?? 0) >= 1 && $immatureReport['collection']['rate'] === null, 'T24 immature cohort was reported as a final rate.');
$stressPrefix = 'STRESS-' . strtoupper(substr($uuid(), 0, 6)) . '-';
for ($i = 1; $i <= 1000; $i++) $makeContract($operatorA, 1, 10000, 3, $stressPrefix . str_pad((string) $i, 4, '0', STR_PAD_LEFT));
$stressCriteria = new OverdueFilterCriteria(); $stressCriteria->search = $stressPrefix; $stressCriteria->perPage = 100; $stressCriteria->page = 10;
$stressStart = microtime(true);
$stressPage = OverdueAggregationService::paginated($stressCriteria, null, false);
$stressSeconds = microtime(true) - $stressStart;
$assert($stressPage['total'] === 1000 && count($stressPage['items']) === 100 && $stressPage['page'] === 10, 'T25: 1000-contract worklist did not paginate on the server.');
$assert($stressSeconds < 30, 'T25: 100-contract hydrated page exceeded 30 seconds.');
$amountless = $makeContract($operatorD, 1, 90000, 25);
OperatorCall::insertStructured(['operator_id' => $operatorD, 'customer_id' => $customer, 'contract_id' => $amountless['id'], 'call_result' => 'قول ثبت شد', 'notes' => '', 'next_followup_date' => null, 'promise_payment_date' => date('Y-m-d', strtotime('-1 day')), 'action_type' => 'customer_call', 'contact_target' => 'customer', 'result_code' => null, 'promise_amount_toman' => null, 'promise_event_type' => 'registered', 'related_promise_call_id' => null, 'next_action_type' => null, 'baseline_overdue_amount_toman' => null, 'baseline_snapshot_status' => null]);
$amountlessCriteria = new OverdueFilterCriteria(); $amountlessCriteria->contractId = $amountless['id'];
$amountlessOverview = CollectionCommandCenter::overview($operatorD, $amountlessCriteria);
$assert(($amountlessOverview['worklist']['items'][0]['active_promise']['derived_status'] ?? '') === 'needs_review', 'T26: a promise without an amount must not be declared fully fulfilled or broken.');
$promiseReport = CollectionPerformanceService::report($reportFrom, $reportTo, $operatorA, 7, 1, 'promise');
$expiredPromiseEvidence = array_values(array_filter($promiseReport['evidence']['items'] ?? [], static fn (array $row): bool => (int) ($row['promise_id'] ?? 0) === $expiredPromiseId))[0] ?? [];
$assert(($expiredPromiseEvidence['status'] ?? '') === 'broken' && ($promiseReport['promises']['broken'] ?? 0) >= 1, 'K7 evidence must expose the matured promise outcome.');
$pass('T22 unauthorized operator is rejected'); $pass('T23 zero KPI denominators return null'); $pass('T24 incomplete observation window stays pending'); $pass('T25 1000 contracts paginate with bounded visible hydration in ' . number_format($stressSeconds, 2) . 's'); $pass('T26 amountless promise remains needs-review');

// T27: hold a contract lock while a completion request waits; settle first and let the request re-evaluate.
$race = $makeContract($operatorA, 1, 60000, 12);
$raceTask = $task($race['id'], $customer, $operatorA, 'qa-race-' . $uuid());
$raceSnapshot = FinancialDebtEngine::overdueBreakdown($race['id'], date('Y-m-d'));
$raceBase = (int) Model::fetch('SELECT base_amount FROM installments WHERE id=?', [$race['installments'][0]])['base_amount'];
$raceAmount = (int) $raceSnapshot['total_overdue_debt'];
$dsnForWorker = $dsn; $userForWorker = (string) (getenv('PROMA_TEST_DB_USER') ?: 'root'); $passwordForWorker = (string) getenv('PROMA_TEST_DB_PASSWORD');
$temporaryDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proma-collection-worker-' . $uuid();
mkdir($temporaryDir);
$workerScript = $temporaryDir . DIRECTORY_SEPARATOR . 'complete.php'; $readyFile = $temporaryDir . DIRECTORY_SEPARATOR . 'ready';
$workerCode = '<?php putenv(' . var_export('PROMA_DB_DSN=' . $dsnForWorker, true) . '); putenv(' . var_export('PROMA_DB_USER=' . $userForWorker, true) . '); putenv(' . var_export('PROMA_DB_PASSWORD=' . $passwordForWorker, true) . '); $_SERVER["HTTP_HOST"]="qa.local"; $_SERVER["SCRIPT_NAME"]="/index.php"; $_SERVER["REQUEST_METHOD"]="CLI"; require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . '; file_put_contents(' . var_export($readyFile, true) . ',"ready"); try { CollectionCommandCenter::completeTask(' . (int) $raceTask . ',' . (int) $admin . ',true,["result"=>"action_completed"]); echo "COMPLETED"; } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()); exit(1); }';
file_put_contents($workerScript, $workerCode);
$racePdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$racePdo->beginTransaction(); $lock = $racePdo->prepare('SELECT id FROM contracts WHERE id=? FOR UPDATE'); $lock->execute([$race['id']]); $lock->fetch();
$pipes = [];
$process = proc_open([PHP_BINARY, $workerScript], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__));
$assert(is_resource($process), 'T27 worker could not start.');
fclose($pipes[0]);
$readyDeadline = microtime(true) + 10;
while (!is_file($readyFile) && microtime(true) < $readyDeadline) usleep(20000);
$assert(is_file($readyFile), 'T27 worker did not start before the lock timeout.');
usleep(250000);
$raceGroupNumber = 'QA-RACE-G-' . strtoupper($uuid());
$racePdo->prepare("INSERT INTO payment_groups (group_number,contract_id,customer_id,created_by,requested_amount,allocated_amount,method,status,idempotency_key,allocation_scope,description,created_at,completed_at) VALUES (?,?,?,?,?,?,'manual','paid',?,'selected','QA race payment',NOW(),NOW())")->execute([$raceGroupNumber, $race['id'], $customer, $admin, $raceAmount, $raceAmount, 'qa-race-' . $uuid()]);
$raceGroupId = (int) $racePdo->lastInsertId();
$raceDate = date('Y-m-d'); $raceAt = date('Y-m-d H:i:s'); $racePenalty = max(0, $raceAmount - $raceBase);
$racePdo->prepare("INSERT INTO payments (installment_id,payment_group_id,contract_id,user_id,amount,method,status,description,payment_date,principal_applied,normal_penalty_applied,legal_penalty_applied,payment_type,is_corrected,paid_at,created_at) VALUES (?,?,?,?,?,'manual','paid','QA race payment',?,?,?,?,'installment',0,?,NOW())")->execute([$race['installments'][0], $raceGroupId, $race['id'], $customer, $raceAmount, $raceDate, $raceBase, $racePenalty, 0, $raceAt]);
$racePaymentId = (int) $racePdo->lastInsertId();
$racePdo->prepare("INSERT INTO payment_allocations (payment_group_id,payment_id,contract_id,installment_id,allocated_amount,principal_applied,normal_penalty_applied,remaining_before,remaining_after,status_after,created_at) VALUES (?,?,?,?,?,?,?,?,?,'paid',NOW())")->execute([$raceGroupId, $racePaymentId, $race['id'], $race['installments'][0], $raceAmount, $raceBase, $racePenalty, $raceAmount, 0]);
$racePdo->prepare("UPDATE installments SET paid_amount=base_amount,remaining_amount=0,status='paid',last_payment_date=? WHERE id=?")->execute([$raceDate, $race['installments'][0]]);
$racePdo->commit();
$workerOutput = stream_get_contents($pipes[1]); $workerError = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$workerExit = proc_close($process);
$assert($workerExit === 0 && strpos($workerOutput, 'COMPLETED') !== false, 'T27 concurrent completion failed after settlement: ' . $workerError);
$assert((Model::fetch('SELECT status FROM collection_tasks WHERE id=?', [$raceTask])['status'] ?? '') === 'completed', 'T27 task status did not settle consistently.');
@unlink($readyFile);
@unlink($workerScript);
@rmdir($temporaryDir);
$pass('T27 concurrent payment settlement and task completion serialize consistently');

// T28: an essential audit write failure rolls back the follow-up and its task side effects.
$auditFailure = $makeContract($operatorA, 1, 100000, 18);
$beforeCalls = (int) (Model::fetch('SELECT COUNT(*) AS n FROM operator_calls WHERE contract_id=?', [$auditFailure['id']])['n'] ?? 0);
$pdo->exec('RENAME TABLE audit_logs TO audit_logs_collection_qa_disabled');
$auditError = $expectFailure(static fn () => $followup($auditFailure, $operatorA), 'T28 mandatory audit failure');
$pdo->exec('RENAME TABLE audit_logs_collection_qa_disabled TO audit_logs');
$afterCalls = (int) (Model::fetch('SELECT COUNT(*) AS n FROM operator_calls WHERE contract_id=?', [$auditFailure['id']])['n'] ?? 0);
$assert($afterCalls === $beforeCalls, 'T28: follow-up committed despite its audit record failing.');
$pass('T28 mandatory audit failure rolls back follow-up and task writes');

echo "INTEGRATION_COLLECTION_OPERATIONS_V704_OK\n";
foreach ($passes as $label) echo "PASS {$label}\n";
