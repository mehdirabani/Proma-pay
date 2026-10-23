<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', $dsn)
    || trim((string) getenv('PROMA_TEST_ALLOW_DESTRUCTIVE')) !== '1') {
    fwrite(STDERR, "This integration test is restricted to the authorized disposable proma_qa_v204 database.\n");
    exit(2);
}

$root = dirname(__DIR__);
foreach ([
    'helpers/functions.php', 'helpers/MoneyMath.php', 'helpers/InstallmentFinancialCalculator.php',
    'core/HttpException.php', 'core/Model.php', 'helpers/SchemaGuard.php',
    'helpers/MerchantApiCredentialToken.php', 'helpers/MerchantAccessContext.php',
    'helpers/MerchantAuditMetadata.php', 'helpers/MerchantGuaranteeCalculator.php',
    'helpers/MerchantGuaranteePolicy.php', 'helpers/MerchantApiGateway.php',
    'helpers/MerchantApiPluginIntegration.php',
    'models/MerchantAuditLog.php', 'models/MerchantApiCredentialRepository.php',
    'models/MerchantCentralGuaranteePolicyRepository.php',
    'models/MerchantApiGatewayRepository.php',
    'models/SystemOutbox.php',
] as $file) {
    require_once $root . '/' . $file;
}
class Auth
{
    public static function id() { return null; }
}

$pdo = new PDO(
    $dsn,
    trim((string) (getenv('PROMA_TEST_DB_USER') ?: 'root')),
    (string) getenv('PROMA_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec("SET time_zone = '+03:30'");
$quoteTable = $pdo->query("SHOW TABLES LIKE 'merchant_api_quotes'")->fetchColumn();
if ($quoteTable !== 'merchant_api_quotes') {
    $quoteMigration = (string) file_get_contents($root . '/database/migrations/2026_10_23_checkout_quote_snapshot_phase7.sql');
    $quoteMigration = preg_replace('/^--.*$/m', '', $quoteMigration);
    foreach (array_filter(array_map('trim', explode(';', (string) $quoteMigration))) as $statement) {
        $pdo->exec($statement);
    }
}
$reflection = new ReflectionProperty(Model::class, 'pdo');
$reflection->setAccessible(true);
$reflection->setValue(null, $pdo);
SchemaGuard::requireTables([
    'merchant_accounts','merchant_sites','merchant_connections','merchant_api_credentials',
    'merchant_policy_versions','merchant_policy_heads','merchant_audit_events',
    'merchant_api_rate_limits','merchant_api_idempotency','merchant_api_order_requests','merchant_api_webhook_outbox','merchant_api_quotes',
    'merchant_contract_links','merchant_cheque_guarantees','contracts','users',
    'merchant_central_guarantee_policy_versions','merchant_central_guarantee_policy_head',
    'system_outbox',
]);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$suffix = strtolower(bin2hex(random_bytes(5)));
Model::execute(
    'INSERT INTO users (role,username,full_name,password_hash,created_at) VALUES (?,?,?,?,NOW())',
    ['admin','qa-api-' . $suffix,'Phase 5 API QA',password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]
);
$adminId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_accounts (merchant_uuid,merchant_code,display_name,status,created_by)
     VALUES (?,?,?,'active',?)",
    [MerchantAuditLog::newUuid(),'qa-api-' . $suffix,'API QA ' . $suffix,$adminId]
);
$merchantId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_sites
        (merchant_id,site_uuid,site_code,display_name,site_origin,verification_status,status,verified_at,created_by)
     VALUES (?,?,?,?,?,'verified','active',NOW(),?)",
    [$merchantId,MerchantAuditLog::newUuid(),'api-site-' . $suffix,'API QA Site','https://api-' . $suffix . '.example.test',$adminId]
);
$siteId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_connections
        (merchant_id,site_id,connection_uuid,connection_code,display_name,status,rate_limit_per_minute,created_by,activated_by,activated_at)
     VALUES (?,?,?,?,?,'active',100,?,?,NOW())",
    [$merchantId,$siteId,MerchantAuditLog::newUuid(),'api-conn-' . $suffix,'API QA Connection',$adminId,$adminId]
);
$connectionId = (int) Model::lastInsertId();
$policy = [
    'monthly_rate_bps' => 250,
    'methods' => ['flat','compound'],
    'limits' => [
        'min_order_toman' => 100000, 'max_order_toman' => 10000000,
        'min_months' => 2, 'max_months' => 12,
        'min_down_payment_bps' => 1000, 'min_down_payment_toman' => 100000,
        'max_down_payment_bps' => 8000, 'first_due_days' => 30,
    ],
    'internal_debug_marker' => 'must-not-be-returned-to-a-merchant',
];
$policyJson = json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$policyUuid = MerchantAuditLog::newUuid();
Model::execute(
    "INSERT INTO merchant_policy_versions
        (connection_id,policy_version_uuid,policy_type,version_no,policy_json,content_sha256,status,created_by,published_by,published_at)
     VALUES (?,?,'financial',1,?,?,'published',?,?,NOW())",
    [$connectionId,$policyUuid,$policyJson,hash('sha256',$policyJson),$adminId,$adminId]
);
$policyId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_policy_heads (connection_id,policy_type,policy_version_id,selected_by)
     VALUES (?,'financial',?,?)",
    [$connectionId,$policyId,$adminId]
);
$guaranteeDocument = [
    'guarantee_type' => 'sayadi_cheque',
    'required_cheque_count' => 1,
    'guarantee_base' => 'scheduled_installments',
    'cheque_multiplier' => '2.0000',
];
$guaranteeUuid = MerchantAuditLog::newUuid();
Model::execute(
    "INSERT INTO merchant_policy_versions
        (connection_id,policy_version_uuid,policy_type,version_no,policy_json,content_sha256,status,created_by,published_by,published_at)
     VALUES (?,?,'guarantee',1,?,?,'published',?,?,NOW())",
    [$connectionId,$guaranteeUuid,json_encode($guaranteeDocument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        hash('sha256',json_encode($guaranteeDocument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),$adminId,$adminId]
);
$guaranteePolicyId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_policy_heads (connection_id,policy_type,policy_version_id,selected_by)
     VALUES (?,'guarantee',?,?)",
    [$connectionId,$guaranteePolicyId,$adminId]
);
Model::execute("UPDATE merchant_central_guarantee_policy_versions SET status = 'retired' WHERE status = 'published'");
$centralPolicyUuid = MerchantAuditLog::newUuid();
$centralVersion = (int) (Model::fetch('SELECT COALESCE(MAX(version_no),0) AS version_no FROM merchant_central_guarantee_policy_versions')['version_no'] ?? 0) + 1;
Model::execute(
    "INSERT INTO merchant_central_guarantee_policy_versions
        (policy_uuid,version_no,status,enabled,minimum_multiplier_units,maximum_multiplier_units,default_multiplier_units,
         beneficiary_legal_name,beneficiary_identifier,legal_approval_reference,created_by,published_by,published_at)
     VALUES (?,?,'published',1,15000,25000,20000,?,'12345678901',?,?,?,NOW())",
    [$centralPolicyUuid,$centralVersion,'Proma Pay Test Beneficiary','QA-LEGAL-' . $suffix,$adminId,$adminId]
);
$centralPolicyId = (int) Model::lastInsertId();
Model::execute(
    'INSERT INTO merchant_central_guarantee_policy_head (singleton_id,policy_version_id,selected_by)
     VALUES (1,?,?) ON DUPLICATE KEY UPDATE policy_version_id = VALUES(policy_version_id), selected_by = VALUES(selected_by)',
    [$centralPolicyId,$adminId]
);
$credential = MerchantApiCredentialRepository::issueForConnection(
    $connectionId,
    ['merchant.read','policy:read','quote:create','order:create','order:cancel','contract:write','contract:read','guarantee:read','webhook:read','connection:manage'],
    $adminId,
    new DateTimeImmutable('+1 day'),
    'test'
);
$token = $credential['token'];
$send = static function (string $method, string $path, ?array $payload = null, ?string $key = null, ?string $bearer = null) use ($token): array {
    $headers = ['Authorization' => 'Bearer ' . ($bearer ?? $token)];
    $body = '';
    if ($payload !== null) {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    if ($key !== null) {
        $headers['Idempotency-Key'] = $key;
    }
    return MerchantApiGateway::handle(
        ['REQUEST_METHOD' => $method,'REQUEST_URI' => '/api/v1' . $path,'HTTPS' => 'on','REMOTE_ADDR' => '203.0.113.5'],
        $body,
        $headers
    );
};

$connectionResponse = $send('GET','/connection');
$assert($connectionResponse['status'] === 200
    && $connectionResponse['body']['data']['connection']['environment'] === 'test',
    'Authenticated connection lookup failed.');
$policyResponse = $send('GET','/policies/financial');
$publicPolicy = $policyResponse['body']['data']['policy'] ?? [];
require_once $root . '/wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-connection-repository.php';
require_once $root . '/wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-policy-service.php';
$assert($policyResponse['status'] === 200
    && ($publicPolicy['policy_type'] ?? '') === 'financial'
    && ($publicPolicy['document']['monthly_rate_bps'] ?? null) === 250
    && ($publicPolicy['document']['limits']['max_months'] ?? null) === 12
    && ($publicPolicy['document']['limits']['first_due_days'] ?? null) === 30
    && !isset($publicPolicy['document']['customer_phone'])
    && !isset($publicPolicy['document']['internal_debug_marker'])
    && PromaPay_WC_Policy_Service::valid_policy($publicPolicy),
    'The policy read route did not return the tenant-scoped, minimized financial policy.');
$quotePayload = ['cash_price_toman'=>1000000,'down_payment_toman'=>250000,'months'=>3,'interest_method'=>'flat'];
$quoteResponse = $send('POST','/quotes',$quotePayload);
$assert($quoteResponse['status'] === 200
    && $quoteResponse['body']['data']['quote']['calculation']['total_installments'] === 806250
    && count($quoteResponse['body']['data']['quote']['calculation']['installment_schedule'] ?? []) === 3
    && isset($quoteResponse['body']['data']['quote']['calculation']['first_installment_due_date'])
    && ($quoteResponse['body']['data']['quote']['guarantee']['calculation']['cheque_due_date'] ?? '')
        === ($quoteResponse['body']['data']['quote']['calculation']['first_installment_due_date'] ?? null)
    && ($quoteResponse['body']['data']['quote']['guarantee']['calculation']['cheque_amount_rial'] ?? 0) === 16125000,
    'Published financial policy quote was incorrect.');
$aboveMaximumDownPayment = $quotePayload;
$aboveMaximumDownPayment['down_payment_toman'] = 900000;
$assert($send('POST','/quotes',$aboveMaximumDownPayment)['status'] === 422,
    'A down payment above the published connection maximum was accepted.');
$belowMinimumTerm = $quotePayload;
$belowMinimumTerm['months'] = 1;
$assert($send('POST','/quotes',$belowMinimumTerm)['status'] === 422,
    'A term below the published connection minimum was accepted.');
$guaranteePolicyResponse = $send('GET','/policies/guarantee');
$publicGuarantee = $guaranteePolicyResponse['body']['data']['policy'] ?? [];
$assert($guaranteePolicyResponse['status'] === 200
    && ($publicGuarantee['policy_type'] ?? '') === 'guarantee'
    && ($publicGuarantee['document']['beneficiary_legal_name'] ?? '') === 'Proma Pay Test Beneficiary'
    && !isset($publicGuarantee['document']['legal_approval_reference'])
    && preg_match('/\A[a-f0-9]{64}\z/D', (string) ($publicGuarantee['policy_sha256'] ?? '')) === 1,
    'Guarantee disclosure did not return the minimized published terms.');

$quoteUuid = (string) ($quoteResponse['body']['data']['quote']['quote_uuid'] ?? '');
$orderPayload = $quotePayload + ['external_order_id'=>'WC-' . $suffix, 'quote_uuid'=>$quoteUuid];
$orderKey = 'phase5-order-' . $suffix . '-0001';
$orderWorkerSpec = [
    'dsn' => $dsn,
    'db_user' => trim((string) (getenv('PROMA_TEST_DB_USER') ?: 'root')),
    'db_password' => (string) getenv('PROMA_TEST_DB_PASSWORD'),
    'bearer' => $token,
    'idempotency_key' => $orderKey,
    'body' => json_encode($orderPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    'start_file' => sys_get_temp_dir() . '/proma-api-phase10-go-' . $suffix,
];
$workerPath = $root . '/tests/support/merchant_api_order_worker.php';
$workerProcesses = [];
try {
    if (!function_exists('proc_open') || !is_file($workerPath)) {
        throw new RuntimeException('Concurrent API idempotency test requires proc_open and the worker script.');
    }
    for ($workerIndex = 0; $workerIndex < 2; $workerIndex++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $workerPath],
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']],
            $pipes,
            $root,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent API order worker.');
        }
        fwrite($pipes[0], json_encode($orderWorkerSpec, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $workerProcesses[] = ['process' => $process, 'pipes' => $pipes];
    }
    $barrier = fopen($orderWorkerSpec['start_file'], 'wb');
    if (!is_resource($barrier)) {
        throw new RuntimeException('Unable to open concurrent API worker barrier.');
    }
    fclose($barrier);
    $workerResponses = [];
    foreach ($workerProcesses as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $exitCode = proc_close($worker['process']);
        if ($exitCode !== 0) {
            throw new RuntimeException('Concurrent API worker failed: ' . substr((string) $stderr, 0, 500));
        }
        $workerResponses[] = json_decode((string) $stdout, true, 64, JSON_THROW_ON_ERROR);
    }
    $createdResponses = array_values(array_filter($workerResponses, static fn (array $response): bool => (int) ($response['status'] ?? 0) === 201));
    $replayedResponses = array_values(array_filter($workerResponses, static fn (array $response): bool => (int) ($response['status'] ?? 0) === 200));
    $assert(count($createdResponses) === 1 && count($replayedResponses) === 1
        && ($replayedResponses[0]['body']['data']['idempotent'] ?? false) === true
        && ($createdResponses[0]['body']['data']['order_uuid'] ?? '') === ($replayedResponses[0]['body']['data']['order_uuid'] ?? ''),
        'Two concurrent requests created more than one order or did not replay the same idempotent response.');
    $orderResponse = $createdResponses[0];
} finally {
    foreach ($workerProcesses as $worker) {
        if (is_resource($worker['process'])) {
            @proc_terminate($worker['process']);
            foreach ([$worker['pipes'][1] ?? null, $worker['pipes'][2] ?? null] as $pipe) {
                if (is_resource($pipe)) @fclose($pipe);
            }
            @proc_close($worker['process']);
        }
    }
    if (is_file($orderWorkerSpec['start_file'])) unlink($orderWorkerSpec['start_file']);
}
$assert($orderResponse['status'] === 201
    && $orderResponse['body']['data']['status'] === 'pending_review',
    'Order did not remain pending review.');
$orderUuid = (string) $orderResponse['body']['data']['order_uuid'];
$orderRetry = $send('POST','/orders',$orderPayload,$orderKey);
$assert($orderRetry['status'] === 200
    && $orderRetry['body']['data']['idempotent'] === true
    && $orderRetry['body']['data']['order_uuid'] === $orderUuid,
    'Order retry did not return its stored response.');
$conflict = $send('POST','/orders',$orderPayload + ['ignored'=>'x'],$orderKey);
$assert($conflict['status'] === 422, 'Unknown or sensitive order fields were not rejected.');
$changedPayload = $orderPayload;
$changedPayload['cash_price_toman']++;
$idempotencyConflict = $send('POST','/orders',$changedPayload,$orderKey);
$assert($idempotencyConflict['status'] === 409, 'Reusing an idempotency key with a different payload was accepted.');
$sameOrderNewKey = $send('POST','/orders',$orderPayload,'phase5-order-' . $suffix . '-0002');
$assert($sameOrderNewKey['status'] === 200
    && $sameOrderNewKey['body']['data']['order_uuid'] === $orderUuid,
    'External order identity was not idempotent across request keys.');
Model::execute('UPDATE merchant_policy_versions SET policy_json = ? WHERE id = ?', ['{"changed":true}',$policyId]);
$oldPolicyRetry = $send('POST','/orders',$orderPayload,$orderKey);
$assert($oldPolicyRetry['status'] === 200
    && $oldPolicyRetry['body']['data']['order_uuid'] === $orderUuid,
    'Completed order retry was not replayed after the active policy changed.');
Model::execute('UPDATE merchant_policy_versions SET policy_json = ? WHERE id = ?', [$policyJson,$policyId]);

$contractPayload = ['order_uuid'=>$orderUuid,'external_customer_reference'=>'customer-' . $suffix . '-opaque'];
$contractKey = 'phase5-contract-' . $suffix . '-01';
$contractResponse = $send('POST','/contracts',$contractPayload,$contractKey);
$assert($contractResponse['status'] === 202
    && $contractResponse['body']['data']['status'] === 'pending_review',
    'Contract request did not remain pending review.');
$contractRetry = $send('POST','/contracts',$contractPayload,$contractKey);
$assert($contractRetry['status'] === 200 && $contractRetry['body']['data']['idempotent'] === true,
    'Contract request retry was not idempotent.');
$contractState = $send('GET','/contracts/' . $orderUuid);
$assert($contractState['status'] === 200
    && $contractState['body']['data']['contract']['contract_id'] === null
    && $contractState['body']['data']['contract']['contract_request_status'] === 'pending_review',
    'Contract status leaked or created an internal contract.');
$cancelQuoteResponse = $send('POST','/quotes',$quotePayload);
$cancelQuoteUuid = (string) ($cancelQuoteResponse['body']['data']['quote']['quote_uuid'] ?? '');
$cancelOrderPayload = $quotePayload + ['external_order_id'=>'WC-CANCEL-' . $suffix, 'quote_uuid'=>$cancelQuoteUuid];
$cancelOrder = $send('POST','/orders',$cancelOrderPayload,'phase5-cancel-order-' . $suffix);
$cancelOrderUuid = (string) ($cancelOrder['body']['data']['order_uuid'] ?? '');
$cancelPath = '/orders/' . $cancelOrderUuid . '/cancel';
$cancelPayload = ['reason_code'=>'payment_failed'];
$cancelKey = 'phase5-cancel-request-' . $suffix;
$cancelResponse = $send('POST',$cancelPath,$cancelPayload,$cancelKey);
$cancelRetry = $send('POST',$cancelPath,$cancelPayload,$cancelKey);
$cancelState = $send('GET','/contracts/' . $cancelOrderUuid);
$assert($cancelResponse['status'] === 202
    && ($cancelResponse['body']['data']['status'] ?? '') === 'cancelled'
    && $cancelRetry['status'] === 200 && ($cancelRetry['body']['data']['idempotent'] ?? false) === true
    && ($cancelState['body']['data']['contract']['request_status'] ?? '') === 'cancelled',
    'Pending external order cancellation did not remain tenant-scoped and idempotent.');
$cancelPluginEvents = Model::fetchAll(
    "SELECT payload_json FROM system_outbox WHERE event_type = 'plugin_hook' AND payload_json LIKE ? ORDER BY id ASC",
    ['%' . $cancelOrderUuid . '%']
);
$cancelEventNames = [];
foreach ($cancelPluginEvents as $row) {
    $eventDocument = json_decode((string) $row['payload_json'], true);
    $cancelEventNames[] = (string) ($eventDocument['event'] ?? '');
}
sort($cancelEventNames);
$assert($cancelEventNames === ['merchant_api.order.cancelled','merchant_api.order.pending_review'],
    'Pending order cancellation did not publish its lifecycle event idempotently.');
$guaranteeState = $send('GET','/guarantees/' . $orderUuid);
$assert($guaranteeState['status'] === 200 && $guaranteeState['body']['data']['guarantee']['status'] === 'not_created',
    'Guarantee endpoint returned an invalid pre-contract state.');
$webhooks = $send('GET','/webhooks');
$assert($webhooks['status'] === 200
    && $webhooks['body']['data']['webhooks']['queued'] === 4,
    'Order/contract request webhook events were not queued.');
$storedOrder = Model::fetch(
    'SELECT external_customer_reference_hash, request_json, quote_json
     FROM merchant_api_order_requests WHERE order_uuid = ? AND connection_id = ?',
    [$orderUuid,$connectionId]
);
$assert(hash_equals(hash('sha256',$contractPayload['external_customer_reference']), (string) $storedOrder['external_customer_reference_hash']),
    'External customer reference was not stored only as a digest.');
$assert(strpos((string) $storedOrder['request_json'], 'customer-' . $suffix) === false,
    'Customer reference leaked into the order request snapshot.');
$mainPluginEvents = Model::fetchAll(
    "SELECT payload_json FROM system_outbox WHERE event_type = 'plugin_hook' AND payload_json LIKE ? ORDER BY id ASC",
    ['%' . $orderUuid . '%']
);
$mainEventNames = [];
foreach ($mainPluginEvents as $row) {
    $eventDocument = json_decode((string) $row['payload_json'], true);
    $eventName = (string) ($eventDocument['event'] ?? '');
    $eventPayload = (array) ($eventDocument['payload'] ?? []);
    $mainEventNames[] = $eventName;
    $assert(($eventPayload['financial_posting_permitted'] ?? true) === false
        && !array_key_exists('external_order_id', $eventPayload)
        && !array_key_exists('external_customer_reference', $eventPayload)
        && !array_key_exists('cash_price_toman', $eventPayload),
        'Plugin lifecycle outbox leaked raw identifiers or enabled financial posting.');
}
sort($mainEventNames);
$assert($mainEventNames === ['merchant_api.contract_request.pending_review','merchant_api.order.pending_review'],
    'Order and contract request lifecycle events were not durably queued exactly once.');

Model::execute(
    "INSERT INTO merchant_accounts (merchant_uuid,merchant_code,display_name,status,created_by)
     VALUES (?,? ,?,'active',?)",
    [MerchantAuditLog::newUuid(),'qa-api-other-' . $suffix,'Other API QA ' . $suffix,$adminId]
);
$otherMerchantId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_sites
        (merchant_id,site_uuid,site_code,display_name,site_origin,verification_status,status,verified_at,created_by)
     VALUES (?,?,?,?,?,'verified','active',NOW(),?)",
    [$otherMerchantId,MerchantAuditLog::newUuid(),'other-site-' . $suffix,'Other API QA Site','https://other-api-' . $suffix . '.example.test',$adminId]
);
$otherSiteId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_connections
        (merchant_id,site_id,connection_uuid,connection_code,display_name,status,created_by,activated_by,activated_at)
     VALUES (?,?,?,?,?,'active',?,?,NOW())",
    [$otherMerchantId,$otherSiteId,MerchantAuditLog::newUuid(),'other-conn-' . $suffix,'Other API QA Connection',$adminId,$adminId]
);
$otherConnectionId = (int) Model::lastInsertId();
$otherCredential = MerchantApiCredentialRepository::issueForConnection(
    $otherConnectionId,['contract:read'],$adminId,new DateTimeImmutable('+1 day'),'test'
);
$crossTenantRead = $send('GET','/contracts/' . $orderUuid,null,null,$otherCredential['token']);
$assert($crossTenantRead['status'] === 404, 'A second merchant read an order from another tenant.');

$narrowToken = MerchantApiCredentialRepository::issueForConnection(
    $connectionId,['merchant.read'],$adminId,new DateTimeImmutable('+1 day'),'test'
);
$scopeDenied = $send('POST','/quotes',$quotePayload,null,$narrowToken['token']);
$assert($scopeDenied['status'] === 403, 'Missing API scope was accepted.');
$cancelScopeDenied = $send('POST',$cancelPath,$cancelPayload,'phase5-narrow-cancel-' . $suffix,$narrowToken['token']);
$assert($cancelScopeDenied['status'] === 403, 'Missing order:cancel scope was accepted.');
$policyScopeDenied = $send('GET','/policies/financial',null,null,$narrowToken['token']);
$assert($policyScopeDenied['status'] === 403, 'Financial policy was returned without policy:read scope.');
$badTransport = MerchantApiGateway::handle(
    ['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/api/v1/connection','REMOTE_ADDR'=>'203.0.113.10'],
    '',['Authorization'=>'Bearer ' . $token]
);
$assert($badTransport['status'] === 426, 'Non-TLS public request was accepted.');

Model::execute('UPDATE merchant_connections SET rate_limit_per_minute = 2 WHERE id = ?',[$connectionId]);
Model::execute('DELETE FROM merchant_api_rate_limits WHERE connection_id = ?',[$connectionId]);
$assert($send('GET','/connection')['status'] === 200, 'Rate-limit first request failed.');
$assert($send('GET','/connection')['status'] === 200, 'Rate-limit second request failed.');
$rateLimited = $send('GET','/connection');
$assert($rateLimited['status'] === 429 && isset($rateLimited['headers']['Retry-After']), 'Per-connection rate limit did not fail closed.');
Model::execute('UPDATE merchant_connections SET rate_limit_per_minute = 100 WHERE id = ?',[$connectionId]);
$disable = $send(
    'POST','/connection/emergency-disable',
    ['reason_code'=>'security_incident'],
    'phase5-disable-' . $suffix . '-01'
);
$assert($disable['status'] === 202
    && $disable['body']['data']['status'] === 'emergency_disabled',
    'Emergency disable did not suspend its own API connection.');
$assert($send('GET','/connection')['status'] === 401, 'Emergency-disabled connection accepted another API call.');

echo "INTEGRATION_MERCHANT_PUBLIC_API_PHASE5_OK\n";
