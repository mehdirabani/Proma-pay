<?php

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8134';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
putenv('PROMA_ZARINPAL_SECRET_KEY=' . base64_encode(str_repeat('Z', 32)));

require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$pdo->exec("INSERT INTO users (role, username, full_name, national_id, mobile, email, password_hash, status, created_at)
            VALUES ('customer','zp-customer','مشتری تست زرین پال','0012345678','09120000000','customer@example.test','test','active',NOW())");
$customerId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, created_at)
            VALUES ({$customerId},'ZP-TEST-1','ZP',1,600000,0,'simple',3,CURDATE(),CURDATE(),'active',NOW())");
$contractId = (int) $pdo->lastInsertId();
for ($number = 1; $number <= 3; $number++) {
    $pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
                VALUES ({$contractId},{$number},DATE_ADD(CURDATE(), INTERVAL {$number} MONTH),200000,0,200000,'pending',NOW())");
}
$installmentIds = array_map('intval', $pdo->query("SELECT id FROM installments WHERE contract_id={$contractId} ORDER BY installment_number")->fetchAll(PDO::FETCH_COLUMN));

$manager = PluginManager::instance();
$manager->install('proma-zarinpal', $customerId);
$manager->activate('proma-zarinpal', $customerId);
if (!PluginManager::isActive('proma-zarinpal')) {
    throw new RuntimeException('Plugin activation failed.');
}

$settings = new Proma\Plugins\Zarinpal\Services\ZarinpalSettingsService();
$settings->save([
    'enabled' => '1',
    'environment' => 'sandbox',
    'sandbox_merchant_id' => '123e4567-e89b-42d3-a456-426614174000',
    'currency' => 'IRT',
    'gateway_title' => 'زرین پال تست',
    'description_template' => 'پرداخت اقساط قرارداد {{contract_number}}',
    'send_mobile' => '1',
    'send_email' => '1',
    'send_order_id' => '1',
    'is_default' => '1',
    'allow_customer_selection' => '1',
    'minimum_amount_toman' => '1000',
    'maximum_amount_toman' => '10000000',
    'connect_timeout' => '5',
    'response_timeout' => '15',
    'callback_base_url' => 'https://127.0.0.1:8134',
    'technical_logging' => '1',
    'show_technical_errors_admin' => '1',
    'sandbox_financial_effects' => '1',
], $customerId);

$authorities = [str_repeat('S', 36), 'S' . str_repeat('B', 35), 'S' . str_repeat('C', 35), 'S' . str_repeat('D', 35)];
$requestIndex = 0;
$verifyCode = 100;
$transport = static function ($url, array $payload) use (&$authorities, &$requestIndex, &$verifyCode) {
    if (substr($url, -12) === 'request.json') {
        $authority = $authorities[$requestIndex++] ?? str_repeat('C', 36);
        return ['http_status' => 200, 'body' => ['data' => ['code' => 100, 'authority' => $authority, 'fee_type' => 'Merchant', 'fee' => 0], 'errors' => []]];
    }
    return ['http_status' => 200, 'body' => ['data' => ['code' => $verifyCode, 'ref_id' => 900000 + $requestIndex, 'card_pan' => '621986******8080', 'card_hash' => hash('sha256', 'test-card'), 'fee_type' => 'Merchant', 'fee' => 0, 'amount' => (int) $payload['amount']], 'errors' => []]];
};
$clientFactory = static function (array $config) use ($transport) {
    return new Proma\Plugins\Zarinpal\Services\ZarinpalClient((int) $config['connect_timeout'], (int) $config['response_timeout'], $transport);
};
$repository = new Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository();
$logs = new Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository();
$requestService = new Proma\Plugins\Zarinpal\Services\ZarinpalRequestService($settings, $repository, $logs, $clientFactory);
$transactionService = new Proma\Plugins\Zarinpal\Services\ZarinpalTransactionService($repository, $logs);
$verification = new Proma\Plugins\Zarinpal\Services\ZarinpalVerificationService($settings, $repository, $logs, $transactionService, $clientFactory);
$callback = new Proma\Plugins\Zarinpal\Services\ZarinpalCallbackService($repository, $logs, $verification);

$single = $requestService->create([
    'type' => 'single', 'installment_id' => $installmentIds[0], 'installment_number' => 1,
    'contract_id' => $contractId, 'contract_number' => 'ZP-TEST-1', 'customer_id' => $customerId,
    'customer_name' => 'مشتری تست زرین پال', 'customer_mobile' => '09120000000', 'customer_email' => 'customer@example.test',
    'amount_toman' => 200000, 'idempotency_key' => 'gateway:single:test:' . str_repeat('1', 40),
]);
if (empty($single['ok']) || strpos($single['redirect_url'], 'https://sandbox.zarinpal.com/pg/StartPay/') !== 0) {
    throw new RuntimeException('Sandbox single request failed.');
}
$singleDuplicateRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $installmentIds[0], 'installment_number' => 1,
    'contract_id' => $contractId, 'contract_number' => 'ZP-TEST-1', 'customer_id' => $customerId,
    'customer_name' => 'مشتری تست زرین پال', 'customer_mobile' => '09120000000', 'customer_email' => 'customer@example.test',
    'amount_toman' => 200000, 'idempotency_key' => 'gateway:single:test:' . str_repeat('1', 40),
]);
if (($singleDuplicateRequest['transaction_id'] ?? 0) !== ($single['transaction_id'] ?? -1) || $requestIndex !== 1) {
    throw new RuntimeException('Duplicate request idempotency failed.');
}
$first = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => str_repeat('S', 36), 'Status' => 'OK']);
$duplicate = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => str_repeat('S', 36), 'Status' => 'OK']);
$lateNok = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => str_repeat('S', 36), 'Status' => 'NOK']);
$singlePaymentCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE installment_id={$installmentIds[0]} AND status='paid'")->fetchColumn();
if (empty($first['ok']) || empty($duplicate['ok']) || empty($lateNok['ok']) || $singlePaymentCount !== 1) {
    throw new RuntimeException('Single callback idempotency failed.');
}

$verifyCode = 101;
$group = $requestService->create([
    // Only one installment is selected; the verified overpayment must be
    // allocated to the next eligible installment by the core service.
    'type' => 'group', 'installment_ids' => [$installmentIds[1]],
    'contract_id' => $contractId, 'contract_number' => 'ZP-TEST-1', 'customer_id' => $customerId,
    'customer_name' => 'مشتری تست زرین پال', 'customer_mobile' => '09120000000', 'customer_email' => 'customer@example.test',
    'amount_toman' => 400000, 'idempotency_key' => 'gateway:group:test:' . str_repeat('2', 40),
]);
$groupFirst = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => 'S' . str_repeat('B', 35), 'Status' => 'OK']);
$groupDuplicate = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => 'S' . str_repeat('B', 35), 'Status' => 'OK']);
$groupId = (int) $pdo->query("SELECT id FROM payment_groups WHERE method='zarinpal' LIMIT 1")->fetchColumn();
$allocationCount = (int) $pdo->query("SELECT COUNT(*) FROM payment_allocations WHERE payment_group_id={$groupId}")->fetchColumn();
$trackCount = (int) $pdo->query("SELECT COUNT(DISTINCT gateway_track_id) FROM payments WHERE payment_group_id={$groupId}")->fetchColumn();
if (empty($group['ok']) || empty($groupFirst['ok']) || empty($groupDuplicate['ok']) || $allocationCount !== 2 || $trackCount !== 2) {
    throw new RuntimeException('Group callback, code 101 recovery, or allocation idempotency failed.');
}

$invalidMerchantRejected = false;
try {
    $invalidSettings = $settings->all(false);
    $invalidSettings['sandbox_merchant_id'] = 'not-a-zarinpal-merchant';
    $settings->save($invalidSettings, $customerId);
} catch (InvalidArgumentException $expected) {
    $invalidMerchantRejected = true;
}
if (!$invalidMerchantRejected) {
    throw new RuntimeException('Invalid Merchant ID was accepted.');
}

$externalCallbackRejected = false;
try {
    $invalidSettings = $settings->all(false);
    $invalidSettings['callback_base_url'] = 'https://attacker.example.test';
    $settings->save($invalidSettings, $customerId);
} catch (InvalidArgumentException $expected) {
    $externalCallbackRejected = true;
}
$maskedSettings = $settings->all(false);
if (!$externalCallbackRejected || strpos((string) ($maskedSettings['sandbox_merchant_id'] ?? ''), '****') === false
    || strpos((string) ($maskedSettings['sandbox_merchant_id'] ?? ''), '123e4567-e89b') !== false) {
    throw new RuntimeException('Settings validation or Merchant masking failed.');
}

$pdo->exec("INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, created_at)
            VALUES ({$customerId},'ZP-TEST-NOK','ZP',2,100000,0,'simple',1,CURDATE(),CURDATE(),'active',NOW())");
$cancelContractId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
            VALUES ({$cancelContractId},1,CURDATE(),100000,0,100000,'pending',NOW())");
$cancelInstallmentId = (int) $pdo->lastInsertId();
$cancelRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $cancelInstallmentId, 'installment_number' => 1,
    'contract_id' => $cancelContractId, 'contract_number' => 'ZP-TEST-NOK', 'customer_id' => $customerId,
    'customer_name' => 'مشتری تست زرین پال', 'customer_mobile' => '09120000000', 'customer_email' => 'customer@example.test',
    'amount_toman' => 100000, 'idempotency_key' => 'gateway:single:nok:' . str_repeat('3', 40),
]);
$cancelled = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => 'S' . str_repeat('C', 35), 'Status' => 'NOK']);
$cancelPaidCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE installment_id={$cancelInstallmentId} AND status='paid'")->fetchColumn();
if (empty($cancelRequest['ok']) || !empty($cancelled['ok']) || $cancelPaidCount !== 0) {
    throw new RuntimeException('NOK callback changed financial state.');
}

$sandboxSettings = $settings->all(false);
$sandboxSettings['sandbox_financial_effects'] = '0';
$settings->save($sandboxSettings, 1);
$pdo->exec("INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, created_at)
            VALUES ({$customerId},'ZP-SANDBOX-SAFE','ZP',3,150000,0,'simple',1,CURDATE(),CURDATE(),'active',NOW())");
$sandboxContractId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
            VALUES ({$sandboxContractId},1,CURDATE(),150000,0,150000,'pending',NOW())");
$sandboxInstallmentId = (int) $pdo->lastInsertId();
$verifyCode = 100;
$sandboxRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $sandboxInstallmentId, 'installment_number' => 1,
    'contract_id' => $sandboxContractId, 'contract_number' => 'ZP-SANDBOX-SAFE', 'customer_id' => $customerId,
    'customer_name' => 'مشتری تست زرین پال', 'customer_mobile' => '09120000000', 'customer_email' => 'customer@example.test',
    'amount_toman' => 150000, 'idempotency_key' => 'gateway:sandbox:safe:' . str_repeat('4', 40),
]);
$sandboxVerified = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => 'S' . str_repeat('D', 35), 'Status' => 'OK']);
$sandboxTransactionStatus = (string) $pdo->query("SELECT status FROM proma_zarinpal_transactions WHERE contract_id={$sandboxContractId} LIMIT 1")->fetchColumn();
$sandboxPaidCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE installment_id={$sandboxInstallmentId} AND status='paid'")->fetchColumn();
$sandboxInstallment = $pdo->query("SELECT paid_amount, remaining_amount, status FROM installments WHERE id={$sandboxInstallmentId}")->fetch();
if (empty($sandboxRequest['ok']) || empty($sandboxVerified['ok']) || $sandboxTransactionStatus !== 'sandbox_verified' || $sandboxPaidCount !== 0
    || (int) $sandboxInstallment['paid_amount'] !== 0 || (int) $sandboxInstallment['remaining_amount'] !== 150000 || $sandboxInstallment['status'] !== 'pending') {
    throw new RuntimeException('Sandbox payment changed production financial records without explicit permission.');
}

$cancelTransactionId = (int) $pdo->query("SELECT id FROM proma_zarinpal_transactions WHERE contract_id={$cancelContractId} LIMIT 1")->fetchColumn();
$pdo->exec("UPDATE proma_zarinpal_transactions SET status='manual_review' WHERE id={$cancelTransactionId}");
$reviewRows = $repository->paginate([
    'status' => 'needs_review',
    'date_from' => date('Y-m-d'),
    'date_to' => date('Y-m-d'),
    'amount_min' => 100000,
    'amount_max' => 100000,
    'page' => 1,
]);
if ((int) ($reviewRows['total'] ?? 0) !== 1 || (int) ($reviewRows['items'][0]['id'] ?? 0) !== $cancelTransactionId) {
    throw new RuntimeException('Needs-review transaction filters failed.');
}
$blocked = false;
try {
    $manager->deactivate('proma-zarinpal', $customerId);
} catch (RuntimeException $expected) {
    $blocked = true;
}
if (!$blocked || !PluginManager::isActive('proma-zarinpal')) {
    throw new RuntimeException('Unsafe deactivation was not blocked.');
}
$pdo->exec("UPDATE proma_zarinpal_transactions SET status='cancelled_by_customer' WHERE id={$cancelTransactionId}");
$manager->deactivate('proma-zarinpal', $customerId);
$manager->activate('proma-zarinpal', $customerId);

$merchantRows = $pdo->query("SELECT setting_value, encrypted_value FROM proma_zarinpal_settings WHERE setting_key='sandbox_merchant_id'")->fetch();
if (!empty($merchantRows['setting_value']) || strpos((string) $merchantRows['encrypted_value'], '123e4567') !== false) {
    throw new RuntimeException('Merchant storage is not encrypted.');
}
$rawCardCount = (int) $pdo->query("SELECT COUNT(*) FROM proma_zarinpal_transactions WHERE card_pan_masked NOT LIKE '%*%' AND card_pan_masked IS NOT NULL")->fetchColumn();
if ($rawCardCount !== 0) {
    throw new RuntimeException('Unmasked card data was stored.');
}

echo "ZARINPAL_DB_INTEGRATION_OK\n";
