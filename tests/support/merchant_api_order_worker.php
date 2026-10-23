<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$spec = json_decode((string) stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
if (!is_array($spec)
    || !preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', (string) ($spec['dsn'] ?? ''))
    || trim((string) ($spec['start_file'] ?? '')) === '') {
    fwrite(STDERR, "Concurrent API worker received invalid disposable test configuration.\n");
    exit(2);
}

foreach ([
    'helpers/functions.php', 'core/HttpException.php', 'core/Model.php', 'helpers/SchemaGuard.php',
    'helpers/MerchantApiCredentialToken.php', 'helpers/MerchantAccessContext.php', 'helpers/MerchantAuditMetadata.php',
    'helpers/MerchantGuaranteeCalculator.php', 'helpers/MerchantGuaranteePolicy.php', 'helpers/MerchantApiGateway.php',
    'helpers/MerchantApiPluginIntegration.php', 'models/MerchantAuditLog.php', 'models/MerchantApiCredentialRepository.php',
    'models/MerchantCentralGuaranteePolicyRepository.php', 'models/MerchantApiGatewayRepository.php', 'models/SystemOutbox.php',
] as $file) {
    require_once $root . '/' . $file;
}
class Auth
{
    public static function id() { return null; }
}

$pdo = new PDO(
    (string) $spec['dsn'],
    (string) ($spec['db_user'] ?? 'root'),
    (string) ($spec['db_password'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec("SET time_zone = '+03:30'");
$reflection = new ReflectionProperty(Model::class, 'pdo');
$reflection->setAccessible(true);
$reflection->setValue(null, $pdo);

$deadline = microtime(true) + 12;
while (!is_file((string) $spec['start_file']) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file((string) $spec['start_file'])) {
    fwrite(STDERR, "Concurrent API worker barrier timed out.\n");
    exit(3);
}

$response = MerchantApiGateway::handle(
    ['REQUEST_METHOD' => 'POST','REQUEST_URI' => '/api/v1/orders','HTTPS' => 'on','REMOTE_ADDR' => '203.0.113.5'],
    (string) ($spec['body'] ?? ''),
    ['Authorization' => 'Bearer ' . (string) ($spec['bearer'] ?? ''), 'Content-Type' => 'application/json', 'Idempotency-Key' => (string) ($spec['idempotency_key'] ?? '')]
);
fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
