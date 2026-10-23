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
    'helpers/MerchantAuditMetadata.php', 'helpers/MerchantApiGateway.php',
    'models/MerchantAuditLog.php', 'models/MerchantApiCredentialRepository.php',
    'models/MerchantApiGatewayRepository.php',
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
$reflection = new ReflectionProperty(Model::class, 'pdo');
$reflection->setAccessible(true);
$reflection->setValue(null, $pdo);
SchemaGuard::requireTables([
    'merchant_accounts','merchant_sites','merchant_connections','merchant_api_credentials',
    'merchant_policy_versions','merchant_policy_heads','merchant_audit_events',
    'merchant_api_rate_limits','merchant_api_idempotency','merchant_api_order_requests','merchant_api_webhook_outbox',
    'merchant_contract_links','merchant_cheque_guarantees','contracts','users',
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
    'limits' => ['min_order_toman' => 100000,'max_order_toman' => 10000000,'max_months' => 12,'min_down_payment_bps' => 1000],
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
$credential = MerchantApiCredentialRepository::issueForConnection(
    $connectionId,
    ['merchant.read','quote:create','order:create','contract:write','contract:read','guarantee:read','webhook:read','connection:manage'],
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
$quotePayload = ['cash_price_toman'=>1000000,'down_payment_toman'=>250000,'months'=>3,'interest_method'=>'flat'];
$quoteResponse = $send('POST','/quotes',$quotePayload);
$assert($quoteResponse['status'] === 200
    && $quoteResponse['body']['data']['quote']['calculation']['total_installments'] === 806250,
    'Published financial policy quote was incorrect.');

$orderPayload = $quotePayload + ['external_order_id'=>'WC-' . $suffix];
$orderKey = 'phase5-order-' . $suffix . '-0001';
$orderResponse = $send('POST','/orders',$orderPayload,$orderKey);
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
$guaranteeState = $send('GET','/guarantees/' . $orderUuid);
$assert($guaranteeState['status'] === 200 && $guaranteeState['body']['data']['guarantee']['status'] === 'not_created',
    'Guarantee endpoint returned an invalid pre-contract state.');
$webhooks = $send('GET','/webhooks');
$assert($webhooks['status'] === 200
    && $webhooks['body']['data']['webhooks']['queued'] === 2,
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
