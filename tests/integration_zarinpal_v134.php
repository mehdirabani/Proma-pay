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

$token = bin2hex(random_bytes(5));
$customerName = 'مشتری تست زرین پال';
$nationalId = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$mobile = '09' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$email = 'zp-' . $token . '@example.test';
$contractNumber = 'ZP-QA-' . $token . '-1';
$cancelContractNumber = 'ZP-QA-' . $token . '-2';
$sandboxContractNumber = 'ZP-QA-' . $token . '-3';
$idempotencyPrefix = 'gateway:qa:' . $token . ':';

$createUser = $pdo->prepare('INSERT INTO users (role, username, full_name, national_id, mobile, email, password_hash, status, created_at)
    VALUES (\'customer\', ?, ?, ?, ?, ?, \'test\', \'active\', NOW())');
$createUser->execute(['zp-customer-' . $token, $customerName, $nationalId, $mobile, $email]);
$customerId = (int) $pdo->lastInsertId();
$contractSerial = random_int(100000, 999999);
$createContract = $pdo->prepare('INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, created_at)
    VALUES (?, ?, \'ZP\', ?, ?, 0, \'simple\', ?, CURDATE(), CURDATE(), \'active\', NOW())');
$createContract->execute([$customerId, $contractNumber, $contractSerial, 600000, 3]);
$contractId = (int) $pdo->lastInsertId();
for ($number = 1; $number <= 3; $number++) {
    $pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
                VALUES ({$contractId},{$number},DATE_ADD(CURDATE(), INTERVAL {$number} MONTH),200000,0,200000,'pending',NOW())");
}
$installmentIds = array_map('intval', $pdo->query("SELECT id FROM installments WHERE contract_id={$contractId} ORDER BY installment_number")->fetchAll(PDO::FETCH_COLUMN));

$manager = PluginManager::instance();
$registered = PluginRegistry::find('proma-zarinpal');
if (!$registered || !in_array(PluginStatus::normalize($registered['status'] ?? ''), [
    PluginStatus::INSTALLED,
    PluginStatus::ACTIVE,
    PluginStatus::INACTIVE,
], true)) {
    $manager->install('proma-zarinpal', $customerId);
}
if (!PluginManager::isActive('proma-zarinpal')) {
    $manager->activate('proma-zarinpal', $customerId);
}
PluginManager::boot();
if (!PluginManager::isActive('proma-zarinpal')) {
    throw new RuntimeException('Plugin activation failed.');
}
// The release gate is repeatable on the same isolated QA database. Resolve only
// stale synthetic transactions left by an interrupted earlier test run.
$pdo->exec("UPDATE proma_zarinpal_transactions SET status='cancelled_by_customer' WHERE status IN ('created','request_uncertain','pending','callback_received','verifying','manual_review')");

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

$authorityStem = str_pad(strtoupper($token), 34, 'A');
$authorities = [
    'S' . $authorityStem . '1',
    'S' . $authorityStem . '2',
    'S' . $authorityStem . '3',
    'S' . $authorityStem . '4',
];
$singleAuthority = $authorities[0];
$groupAuthority = $authorities[1];
$cancelAuthority = $authorities[2];
$sandboxAuthority = $authorities[3];
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

$firstInstallment = Installment::find($installmentIds[0]);
$singleAmount = (int) round((float) ($firstInstallment['payable'] ?? 0));
if ($singleAmount <= 0) {
    throw new RuntimeException('Single payable fixture is invalid.');
}
$single = $requestService->create([
    'type' => 'single', 'installment_id' => $installmentIds[0], 'installment_number' => 1,
    'contract_id' => $contractId, 'contract_number' => $contractNumber, 'customer_id' => $customerId,
    'customer_name' => $customerName, 'customer_mobile' => $mobile, 'customer_email' => $email,
    'amount_toman' => $singleAmount, 'idempotency_key' => $idempotencyPrefix . 'single',
]);
if (empty($single['ok']) || strpos($single['redirect_url'], 'https://sandbox.zarinpal.com/pg/StartPay/') !== 0) {
    throw new RuntimeException('Sandbox single request failed.');
}
$singleDuplicateRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $installmentIds[0], 'installment_number' => 1,
    'contract_id' => $contractId, 'contract_number' => $contractNumber, 'customer_id' => $customerId,
    'customer_name' => $customerName, 'customer_mobile' => $mobile, 'customer_email' => $email,
    'amount_toman' => $singleAmount, 'idempotency_key' => $idempotencyPrefix . 'single',
]);
if (($singleDuplicateRequest['transaction_id'] ?? 0) !== ($single['transaction_id'] ?? -1) || $requestIndex !== 1) {
    throw new RuntimeException('Duplicate request idempotency failed.');
}
$first = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $singleAuthority, 'Status' => 'OK']);
$duplicate = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $singleAuthority, 'Status' => 'OK']);
$lateNok = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $singleAuthority, 'Status' => 'NOK']);
$singlePaymentCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE installment_id={$installmentIds[0]} AND status='paid'")->fetchColumn();
if (empty($first['ok']) || empty($duplicate['ok']) || empty($lateNok['ok']) || $singlePaymentCount !== 1) {
    throw new RuntimeException('Single callback idempotency failed.');
}

$verifyCode = 101;
$secondInstallment = Installment::find($installmentIds[1]);
$thirdInstallment = Installment::find($installmentIds[2]);
$groupAmount = (int) round((float) ($secondInstallment['payable'] ?? 0) + (float) ($thirdInstallment['payable'] ?? 0));
if ($groupAmount <= 0) {
    throw new RuntimeException('Group payable fixture is invalid.');
}
$group = $requestService->create([
    // The selected set is authoritative. A group may allocate only inside
    // this set, so both installments are explicitly selected.
    'type' => 'group', 'installment_ids' => [$installmentIds[1], $installmentIds[2]],
    'contract_id' => $contractId, 'contract_number' => $contractNumber, 'customer_id' => $customerId,
    'customer_name' => $customerName, 'customer_mobile' => $mobile, 'customer_email' => $email,
    'amount_toman' => $groupAmount, 'idempotency_key' => $idempotencyPrefix . 'group',
]);
$groupFirst = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $groupAuthority, 'Status' => 'OK']);
$groupDuplicate = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $groupAuthority, 'Status' => 'OK']);
$groupId = (int) $pdo->query("SELECT id FROM payment_groups WHERE method='zarinpal' AND contract_id={$contractId} ORDER BY id DESC LIMIT 1")->fetchColumn();
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

$createContract->execute([$customerId, $cancelContractNumber, $contractSerial + 1, 100000, 1]);
$cancelContractId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
            VALUES ({$cancelContractId},1,CURDATE(),100000,0,100000,'pending',NOW())");
$cancelInstallmentId = (int) $pdo->lastInsertId();
$cancelRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $cancelInstallmentId, 'installment_number' => 1,
    'contract_id' => $cancelContractId, 'contract_number' => $cancelContractNumber, 'customer_id' => $customerId,
    'customer_name' => $customerName, 'customer_mobile' => $mobile, 'customer_email' => $email,
    'amount_toman' => 100000, 'idempotency_key' => $idempotencyPrefix . 'nok',
]);
$cancelled = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $cancelAuthority, 'Status' => 'NOK']);
$cancelPaidCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE installment_id={$cancelInstallmentId} AND status='paid'")->fetchColumn();
if (empty($cancelRequest['ok']) || !empty($cancelled['ok']) || $cancelPaidCount !== 0) {
    throw new RuntimeException('NOK callback changed financial state.');
}

$sandboxSettings = $settings->all(false);
$sandboxSettings['sandbox_financial_effects'] = '0';
$settings->save($sandboxSettings, 1);
$createContract->execute([$customerId, $sandboxContractNumber, $contractSerial + 2, 150000, 1]);
$sandboxContractId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
            VALUES ({$sandboxContractId},1,CURDATE(),150000,0,150000,'pending',NOW())");
$sandboxInstallmentId = (int) $pdo->lastInsertId();
$verifyCode = 100;
$sandboxRequest = $requestService->create([
    'type' => 'single', 'installment_id' => $sandboxInstallmentId, 'installment_number' => 1,
    'contract_id' => $sandboxContractId, 'contract_number' => $sandboxContractNumber, 'customer_id' => $customerId,
    'customer_name' => $customerName, 'customer_mobile' => $mobile, 'customer_email' => $email,
    'amount_toman' => 150000, 'idempotency_key' => $idempotencyPrefix . 'sandbox',
]);
$sandboxVerified = $callback->handle(['route' => 'plugin/zarinpal/callback', 'Authority' => $sandboxAuthority, 'Status' => 'OK']);
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
$rawCardCount = (int) $pdo->query("SELECT COUNT(*) FROM proma_zarinpal_transactions WHERE contract_id IN ({$contractId}, {$cancelContractId}, {$sandboxContractId}) AND card_pan_masked NOT LIKE '%*%' AND card_pan_masked IS NOT NULL")->fetchColumn();
if ($rawCardCount !== 0) {
    throw new RuntimeException('Unmasked card data was stored.');
}

echo "ZARINPAL_DB_INTEGRATION_OK\n";
