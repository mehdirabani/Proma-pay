<?php

declare(strict_types=1);

/** MariaDB acceptance test for versioned legal eligibility and safe documents. */
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.153';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 9);
$number = static function (string $prefix) use ($suffix): string { return substr(preg_replace('/[^0-9]/', '7', hash('sha256', $prefix . $suffix)), 0, 10); };

$adminId = User::create(['role' => 'admin', 'username' => 'qa-legal-admin-' . $suffix, 'full_name' => 'مدیر آزمون حقوقی', 'national_id' => $number('a'), 'mobile' => '0912' . substr($number('am'), 0, 7), 'email' => 'qa-legal-admin-' . $suffix . '@example.test', 'password' => 'Admin#Legal150', 'status' => 'active']);
$lawyerId = User::create(['role' => 'lawyer', 'username' => 'qa-legal-lawyer-' . $suffix, 'full_name' => 'وکیل آزمون حقوقی', 'national_id' => $number('l'), 'mobile' => '0935' . substr($number('lm'), 0, 7), 'email' => 'qa-legal-lawyer-' . $suffix . '@example.test', 'password' => 'Lawyer#Legal150', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => $number('c'), 'full_name' => 'مشتری آزمون حقوقی', 'national_id' => $number('c'), 'mobile' => '0991' . substr($number('cm'), 0, 7), 'email' => 'qa-legal-customer-' . $suffix . '@example.test', 'password' => '1234', 'status' => 'active']);

Settings::saveMany([
    'legal_delay_value' => '20', 'legal_delay_unit' => 'day', 'legal_overdue_count_threshold' => '1',
    'legal_overdue_amount_enabled' => '0', 'legal_overdue_amount_threshold' => '0', 'legal_eligibility_operator' => 'delay_only', 'legal_allow_self_initiation' => '1',
    'monthly_penalty_rate' => '0', 'legal_monthly_penalty_rate' => '0', 'monthly_reward_rate' => '0',
]);
$policy = LegalEligibilityService::publishFromSettings($adminId);
$assert((int) $policy['version'] >= 1, 'Legal policy was not versioned.');

$create = static function ($customerId, $adminId): int {
    return Contract::createWithInstallments([
        'customer_id' => $customerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0, 'monthly_interest_rate' => 0,
        'interest_type' => 'simple', 'months' => 1, 'start_date' => '2026-06-01', 'first_due_date' => '2026-06-01', 'created_by' => $adminId,
    ]);
};
$firstContract = $create($customerId, $adminId);
Model::execute("UPDATE installments SET base_amount = 1000000, paid_amount = 0, remaining_amount = 1000000, due_date = '2026-06-01', status = 'pending' WHERE contract_id = ?", [$firstContract]);
$first = LegalEligibilityService::forContract($firstContract, '2026-07-26');
$assert(!empty($first['eligible']) && (int) $first['delay_days'] === 55, 'Twenty-day legal threshold did not qualify the overdue contract.');
$firstPolicyVersion = (int) $first['policy_version'];

Settings::saveMany(['legal_delay_value' => '90']);
$laterPolicy = LegalEligibilityService::publishFromSettings($adminId);
$again = LegalEligibilityService::forContract($firstContract, '2026-07-26');
$assert(!empty($again['eligible']) && (int) $again['policy_version'] === $firstPolicyVersion, 'Historic contract policy snapshot changed after a global setting edit.');

$secondContract = $create($customerId, $adminId);
Model::execute("UPDATE installments SET base_amount = 1000000, paid_amount = 0, remaining_amount = 1000000, due_date = '2026-06-01', status = 'pending' WHERE contract_id = ?", [$secondContract]);
$second = LegalEligibilityService::forContract($secondContract, '2026-07-26');
$assert(empty($second['eligible']) && (int) $second['policy_version'] === (int) $laterPolicy['version'], 'New contract did not receive the newly published 90-day policy.');

$requestUuid = bin2hex(random_bytes(16));
$caseId = LegalCase::createSelfInitiated($lawyerId, $firstContract, 'QA internal review', $requestUuid);
$assert($caseId > 0, 'Eligible lawyer could not create an internal legal case.');
$sameCaseId = LegalCase::createSelfInitiated($lawyerId, $firstContract, 'QA internal review', $requestUuid);
$assert($sameCaseId === $caseId, 'Legal case request idempotency created a duplicate.');
$case = LegalCase::find($caseId);
$document = LegalDocumentService::createInternal($case, 'contractual_warning', $lawyerId, ['request_uuid' => bin2hex(random_bytes(16))]);
$assert(($document['document_status'] ?? '') === 'internal_draft', 'Internal legal warning was incorrectly marked official.');
$rejected = false;
try { LegalDocumentService::confirmExternal((int) $document['id'], $lawyerId, []); } catch (InvalidArgumentException $e) { $rejected = true; }
$assert($rejected, 'External status was accepted without explicit legal confirmation.');
$confirmed = LegalDocumentService::confirmExternal((int) $document['id'], $lawyerId, [
    'confirm_external_submission' => '1', 'external_authority' => 'مرجع آزمون', 'external_reference' => 'QA-' . $suffix, 'external_submitted_at' => '1405/05/04',
]);
$assert(($confirmed['document_status'] ?? '') === 'external_submission_confirmed' && !empty($confirmed['external_reference']), 'Confirmed external registration evidence was not stored.');

echo "INTEGRATION_LEGAL_WORKFLOW_V150_OK\n";
