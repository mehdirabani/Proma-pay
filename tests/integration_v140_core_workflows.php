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
$_SERVER['REMOTE_ADDR'] = '198.51.100.140';

require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$suffix = substr((string) hrtime(true), -7);
$customerMobile = '0914' . str_pad($suffix, 7, '0', STR_PAD_LEFT);
$customerNationalId = '14' . str_pad($suffix, 8, '0', STR_PAD_LEFT);
$adminId = User::create([
    'role' => 'admin',
    'username' => 'qa-admin-' . $suffix,
    'full_name' => 'مدیر آزمون ۱۴۰',
    'national_id' => '15' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
    'mobile' => '0915' . str_pad($suffix, 7, '0', STR_PAD_LEFT),
    'email' => 'qa-admin-' . $suffix . '@example.test',
    'password' => 'Admin#V140',
    'status' => 'active',
]);
$customerId = User::create([
    'role' => 'customer',
    'username' => $customerNationalId,
    'full_name' => 'مشتری آزمون ۱۴۰',
    'national_id' => $customerNationalId,
    'mobile' => $customerMobile,
    'email' => 'qa-customer-' . $suffix . '@example.test',
    'password' => substr($customerMobile, -4),
    'status' => 'active',
]);

$assert(Auth::unifiedLogin($customerNationalId, substr($customerMobile, -4)), 'Customer last-four mobile login failed.');
$assert((int) Auth::id() === (int) $customerId, 'Customer login opened the wrong account.');
$_SESSION = [];

User::updateAvatar($customerId, 'avatar-6');
$assert((User::find($customerId)['avatar_key'] ?? '') === 'avatar-6', 'Direct avatar update failed.');

$requestId = ProfileRequest::createRequest($customerId, [
    'full_name' => 'مشتری اصلاح شده ۱۴۰',
    'mobile' => $customerMobile,
    'secondary_phone' => '02112345678',
    'email' => 'approved-' . $suffix . '@example.test',
    'address' => 'نشانی آزمایشی',
    'avatar_key' => 'avatar-1',
    'password' => 'MustNeverBeStored',
]);
$request = ProfileRequest::latestForUser($customerId);
$requestPayload = json_decode((string) $request['payload_json'], true) ?: [];
$assert(!isset($requestPayload['avatar_key'], $requestPayload['password']), 'Direct or sensitive fields leaked into profile approval.');
$assert(ProfileRequest::approve($requestId, $adminId), 'Profile approval failed.');
$approvedUser = User::find($customerId);
$assert(($approvedUser['full_name'] ?? '') === 'مشتری اصلاح شده ۱۴۰', 'Approved profile changes were not applied.');
$assert(($approvedUser['avatar_key'] ?? '') === 'avatar-6', 'Profile approval overwrote the direct avatar choice.');

$contractToken = 'qa-contract-' . bin2hex(random_bytes(12));
$contractPost = [
    'customer_id' => $customerId,
    'principal_amount' => '9000000',
    'down_payment_amount' => '1000000',
    'monthly_interest_rate' => '1.5',
    'interest_type' => 'compound',
    'months' => '2',
    'start_date' => '2026-07-20',
    'first_due_date' => '2026-08-20',
    'contract_request_uuid' => $contractToken,
];
$contractHash = ContractRequest::hash($contractPost);
$firstRequest = ContractRequest::beginRequest($contractToken, $adminId, $contractHash);
$assert(($firstRequest['status'] ?? '') === 'new', 'First contract request was not accepted.');
$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 9000000,
    'down_payment_amount' => 1000000,
    'monthly_interest_rate' => 1.5,
    'interest_type' => 'compound',
    'months' => 2,
    'start_date' => '2026-07-20',
    'first_due_date' => '2026-08-20',
    'created_by' => $adminId,
    'request_uuid' => $contractToken,
]);
$duplicateRequest = ContractRequest::beginRequest($contractToken, $adminId, $contractHash);
$assert(($duplicateRequest['status'] ?? '') === 'completed', 'Duplicate contract request was not recognized.');
$assert((int) ($duplicateRequest['contract_id'] ?? 0) === (int) $contractId, 'Duplicate request did not return the original contract.');
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE id = ?');
$countStatement->execute([$contractId]);
$assert((int) $countStatement->fetchColumn() === 1, 'Duplicate contract was created.');

$installment = $pdo->query('SELECT * FROM installments WHERE contract_id = ' . (int) $contractId . ' ORDER BY installment_number LIMIT 1')->fetch();
$assert($installment, 'Created contract has no installment.');
$paymentToken = 'qa-payment-' . bin2hex(random_bytes(12));
$paymentHash = PaymentRequest::hash([
    'installment_id' => (int) $installment['id'],
    'amount' => 500000,
    'payment_date' => '2026-07-20',
    'payment_time' => '14:10',
    'description' => 'QA contract-detail payment',
    'actor_user_id' => $adminId,
]);
$paymentRequest = PaymentRequest::beginRequest($paymentToken, $adminId, (int) $installment['id'], $paymentHash);
$assert(($paymentRequest['status'] ?? '') === 'new', 'Payment request did not start.');
$paymentId = Payment::record((int) $installment['id'], $contractId, $adminId, 500000, 'manual', 'paid', null, null, 'QA contract-detail payment', '2026-07-20', 'installment', '14:10');
PaymentRequest::complete($paymentToken, $paymentId);
$duplicatePayment = PaymentRequest::beginRequest($paymentToken, $adminId, (int) $installment['id'], $paymentHash);
$assert(($duplicatePayment['status'] ?? '') === 'completed' && (int) $duplicatePayment['payment_id'] === (int) $paymentId, 'Duplicate payment request was not reconciled.');

$unknownIdentifier = 'unknown-' . $suffix;
for ($attempt = 0; $attempt < 5; $attempt++) {
    LoginThrottle::recordFailure($unknownIdentifier, '198.51.100.140');
}
$throttle = LoginThrottle::inspect($unknownIdentifier, '198.51.100.140');
$assert(!empty($throttle['blocked']), 'Repeated login failures were not temporarily locked.');

echo "INTEGRATION_V140_CORE_WORKFLOWS_OK\n";
