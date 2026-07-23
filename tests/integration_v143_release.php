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
$_SERVER['REMOTE_ADDR'] = '198.51.100.143';

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

$suffix = substr((string) hrtime(true), -8);
$adminId = User::create([
    'role' => 'admin',
    'username' => 'qa-v143-admin-' . $suffix,
    'full_name' => 'مدیر آزمون ۱۴۳',
    'national_id' => '43' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
    'mobile' => '0935' . str_pad($suffix, 7, '0', STR_PAD_LEFT),
    'password' => 'Admin#V143',
    'status' => 'active',
]);
$customerId = User::create([
    'role' => 'customer',
    'username' => 'qa-v143-customer-' . $suffix,
    'full_name' => 'مشتری آزمون ۱۴۳',
    'national_id' => '44' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
    'mobile' => '0936' . str_pad($suffix, 7, '0', STR_PAD_LEFT),
    'password' => 'V143#Customer',
    'status' => 'active',
]);

$createContract = static function () use ($customerId, $adminId): int {
    return Contract::createWithInstallments([
        'customer_id' => $customerId,
        'principal_amount' => 5000000,
        'down_payment_amount' => 0,
        'monthly_interest_rate' => 0,
        'interest_type' => 'simple',
        'months' => 2,
        'start_date' => '2026-07-22',
        'first_due_date' => '2026-08-22',
        'created_by' => $adminId,
    ]);
};

$contractId = $createContract();
$contract = Contract::find($contractId);
$installment = Model::fetch('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number LIMIT 1', [$contractId]);
$paymentId = Payment::record((int) $installment['id'], $contractId, $customerId, 100000, 'manual', 'paid', null, null, 'QA purge payment', '2026-07-22');
$preview = Contract::deletionPreview($contractId);
$assert(empty($preview['eligible_for_permanent_delete']), 'Paid contract must require dependency purge.');
$assert(in_array('payment_count', $preview['blocking_dependencies'], true), 'Payment blocker is not reported.');

$blocked = false;
try {
    Contract::deleteContractSafely($contractId, $adminId, 'آزمون جلوگیری از حذف بدون تأیید', $contract['contract_number']);
} catch (InvalidArgumentException $e) {
    $blocked = true;
}
$assert($blocked, 'Contract with payment history was deleted without explicit purge confirmation.');

$result = Contract::deleteContractSafely($contractId, $adminId, 'آزمون حذف کامل قرارداد و سابقه مالی', $contract['contract_number'], true, false);
$assert(!empty($result['deleted']) && !empty($result['history_purged']), 'Confirmed contract-history purge failed.');
$assert(!Model::fetch('SELECT id FROM contracts WHERE id = ?', [$contractId]), 'Contract remained after confirmed purge.');
$assert(!Model::fetch('SELECT id FROM payments WHERE id = ?', [$paymentId]), 'Payment remained after confirmed purge.');
$archive = Model::fetch('SELECT * FROM contract_deletion_archives WHERE contract_id = ? ORDER BY id DESC LIMIT 1', [$contractId]);
$assert($archive && strpos((string) $archive['snapshot_json'], 'QA purge payment') !== false, 'Deletion archive does not contain the financial snapshot.');

$gatewayContractId = $createContract();
$gatewayContract = Contract::find($gatewayContractId);
$gatewayInstallment = Model::fetch('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number LIMIT 1', [$gatewayContractId]);
Payment::record((int) $gatewayInstallment['id'], $gatewayContractId, $customerId, 100000, 'zibal', 'paid', 'qa-zibal-' . $suffix, 'qa-ref-' . $suffix, 'QA gateway purge', '2026-07-22');
$gatewayBlocked = false;
try {
    Contract::deleteContractSafely($gatewayContractId, $adminId, 'آزمون هشدار درگاه', $gatewayContract['contract_number'], true, false);
} catch (InvalidArgumentException $e) {
    $gatewayBlocked = true;
}
$assert($gatewayBlocked, 'Gateway history was deleted without the bank-refund warning confirmation.');
$gatewayResult = Contract::deleteContractSafely($gatewayContractId, $adminId, 'آزمون حذف با تأیید هشدار درگاه', $gatewayContract['contract_number'], true, true);
$assert(!empty($gatewayResult['deleted']), 'Gateway contract was not deleted after explicit warning confirmation.');

$sharedIp = '198.51.100.143';
for ($attempt = 0; $attempt < 5; $attempt++) {
    LoginThrottle::recordFailure('blocked-user-' . $suffix, $sharedIp);
}
$assert(!empty(LoginThrottle::inspect('blocked-user-' . $suffix, $sharedIp)['blocked']), 'Repeated identifier failures are not blocked.');
$assert(empty(LoginThrottle::inspect('innocent-user-' . $suffix, $sharedIp)['blocked']), 'One account blocked another account on the same shared IP.');

$profileId = ProfileRequest::createRequest($customerId, ['address' => 'نشانی جدید آزمون ۱۴۳']);
$page = ProfileRequest::paginated(['status' => 'pending', 'user_id' => $customerId], 1, 6);
$assert($profileId && (int) $page['total'] >= 1, 'Independent profile-review pagination omitted the pending request.');
$assert((int) (ProfileRequest::statusSummary()['pending'] ?? 0) >= 1, 'Profile-review status summary is incorrect.');

echo "INTEGRATION_V143_RELEASE_OK\n";
