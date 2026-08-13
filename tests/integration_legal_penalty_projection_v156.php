<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.156';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 9);
$digits = static function (string $prefix) use ($suffix): string { return substr(preg_replace('/[^0-9]/', '7', hash('sha256', $prefix . $suffix)), 0, 10); };

Settings::saveMany([
    'monthly_penalty_rate' => '6', 'legal_monthly_penalty_rate' => '15', 'monthly_reward_rate' => '0',
    'late_penalty_grace_days' => '0', 'legal_delay_value' => '1', 'legal_delay_unit' => 'day',
    'legal_overdue_count_threshold' => '1', 'legal_overdue_amount_enabled' => '0', 'legal_eligibility_operator' => 'delay_only',
    'legal_allow_self_initiation' => '1', 'show_projected_legal_penalty_to_customer' => '1',
]);
LegalEligibilityService::publishFromSettings(null);
$adminId = User::create(['role' => 'admin', 'username' => 'qa-proj-admin-' . $suffix, 'full_name' => 'مدیر جریمه', 'national_id' => $digits('admin'), 'mobile' => '0912' . substr($digits('mobile-a'), 0, 7), 'email' => 'qa-proj-admin-' . $suffix . '@example.test', 'password' => 'Admin#156Pass', 'status' => 'active']);
$lawyerId = User::create(['role' => 'lawyer', 'username' => 'qa-proj-lawyer-' . $suffix, 'full_name' => 'وکیل جریمه', 'national_id' => $digits('lawyer'), 'mobile' => '0935' . substr($digits('mobile-l'), 0, 7), 'email' => 'qa-proj-lawyer-' . $suffix . '@example.test', 'password' => 'Lawyer#156Pass', 'status' => 'active']);
$customerId = User::create(['role' => 'customer', 'username' => $digits('customer'), 'full_name' => 'مشتری جریمه', 'national_id' => $digits('customer'), 'mobile' => '0991' . substr($digits('mobile-c'), 0, 7), 'email' => 'qa-proj-customer-' . $suffix . '@example.test', 'password' => 'Customer#156Pass', 'status' => 'active']);
$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1,
    'start_date' => '2026-01-01', 'first_due_date' => '2026-01-01', 'created_by' => $adminId,
]);
Model::execute("UPDATE installments SET base_amount = 1000000, paid_amount = 0, remaining_amount = 1000000, due_date = '2026-01-01', status = 'pending' WHERE contract_id = ?", [$contractId]);
$installmentId = (int) (Model::fetch('SELECT id FROM installments WHERE contract_id = ? LIMIT 1', [$contractId])['id'] ?? 0);

// A self-initiated internal review is deliberately not a financial referral.
$caseId = LegalCase::createSelfInitiated($lawyerId, $contractId, 'QA internal-only', bin2hex(random_bytes(16)));
$internalCase = LegalCase::find($caseId);
$assert(empty($internalCase['legal_referred_at']), 'Internal legal review persisted a referral timestamp.');
$beforeRaw = Installment::findRaw($installmentId);
$assert(empty($beforeRaw['legal_started_at']), 'Installment query treated internal review as actual referral.');
$before = InstallmentFinancialStateService::state($beforeRaw, [], Settings::allKeyed(), '2026-01-31');
$assert($before['legal_penalty_accrued'] === 0 && $before['projected_legal_penalty'] > 0, 'Pre-referral state is not comparison-only.');
$beforeQuote = PaymentAllocationService::quote([$beforeRaw], '2026-01-31', Settings::allKeyed());
$assert($beforeQuote['full_settlement_total'] === $before['final_payable'], 'Quote differs from the effective pre-referral debt.');

// The explicit referral action records the canonical timestamp.  Backdating
// here only makes the acceptance calculation deterministic.
LegalCase::createCase($lawyerId, $contractId, 'QA official referral', 'ارجاع رسمی QA');
Model::execute("UPDATE legal_cases SET legal_referred_at = '2026-01-16 10:00:00' WHERE id = ?", [$caseId]);
$afterRaw = Installment::findRaw($installmentId);
$assert(substr((string) $afterRaw['legal_started_at'], 0, 10) === '2026-01-16', 'Canonical referral timestamp was not loaded into the installment.');
$after = InstallmentFinancialStateService::state($afterRaw, [], Settings::allKeyed(), '2026-01-31');
$assert($after['normal_penalty_accrued'] === 30000 && $after['legal_penalty_accrued'] === 75000, 'Actual referral did not split rate accrual at the canonical timestamp.');
$assert($after['final_payable'] === 1105000 && $after['projected_legal_penalty'] === 0, 'Actual legal debt was duplicated by a projection.');
$afterQuote = PaymentAllocationService::quote([$afterRaw], '2026-01-31', Settings::allKeyed());
$assert($afterQuote['full_settlement_total'] === 1105000 && !array_key_exists('projected_legal_penalty_total', $afterQuote), 'Payment quote included an analytical projection.');

// Archive preserves the canonical timestamp; a case lifecycle transition must
// not silently remove actual accrued legal debt.
LegalCase::deleteCase($caseId);
$archivedRaw = Installment::findRaw($installmentId);
$archived = InstallmentFinancialStateService::state($archivedRaw, [], Settings::allKeyed(), '2026-01-31');
$assert($archived['legal_penalty_accrued'] === 75000 && $archived['final_payable'] === 1105000, 'Archiving erased actual legal penalty without an authorized correction.');

echo "INTEGRATION_LEGAL_PENALTY_PROJECTION_V156_OK\n";
