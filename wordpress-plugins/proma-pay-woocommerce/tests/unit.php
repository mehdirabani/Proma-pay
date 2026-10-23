<?php

declare(strict_types=1);

$GLOBALS['promapaywc_test_salt'] = 'a-local-qa-salt-that-is-never-a-production-key';
function wp_salt(string $scheme = 'auth'): string { return (string) $GLOBALS['promapaywc_test_salt']; }
function wp_json_encode($value, int $flags = 0, int $depth = 512) { return json_encode($value, $flags, $depth); }
function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
function is_wp_error($value): bool { return $value instanceof PromaPay_WC_Test_Error; }
function current_time(string $type, bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); }

final class PromaPay_WC_Test_Error
{
    public function __construct(private string $code) {}
    public function get_error_code(): string { return $this->code; }
}

final class PromaPay_WC_Test_WPDB
{
    public string $prefix = 'wp_';
    public array $inserted = [];
    public ?array $cachedPolicyRow = null;
    public ?array $connectionRow = null;

    public function insert(string $table, array $data): int { $this->inserted[] = [$table, $data]; return 1; }
    public function update(string $table, array $data, array $where): int { return 1; }
    public function replace(string $table, array $data): int { $this->cachedPolicyRow = $data; return 1; }
    public function prepare(string $sql, ...$args): array { return [$sql, $args]; }
    public function get_row($prepared, $format = null): ?array
    {
        return str_contains((string) $prepared[0], 'wp_proma_pay_connections') ? $this->connectionRow : $this->cachedPolicyRow;
    }
    public function get_var(string $sql) { return 'd71e105a-21af-4af0-bfb7-84da35da3ab6'; }
    public function query(string $sql): int { return 1; }
    public function delete(string $table, array $where): int { $this->cachedPolicyRow = null; return 1; }
}

$GLOBALS['wpdb'] = new PromaPay_WC_Test_WPDB();
require_once dirname(__DIR__) . '/includes/class-promapaywc-crypto.php';
require_once dirname(__DIR__) . '/includes/class-promapaywc-installer.php';
require_once dirname(__DIR__) . '/includes/class-promapaywc-connection-repository.php';
require_once dirname(__DIR__) . '/includes/class-promapaywc-api-client.php';
require_once dirname(__DIR__) . '/includes/class-promapaywc-policy-service.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $error) {
        return;
    }
    $assert(false, $message);
};

$token = 'pmp_test_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$ciphertext = PromaPay_WC_Crypto::encrypt($token);
$assert(!str_contains($ciphertext, $token), 'Encrypted API credential contains its plaintext token.');
$assert(PromaPay_WC_Crypto::decrypt($ciphertext) === $token, 'Encrypted API credential did not round-trip.');
$throws(static fn () => PromaPay_WC_Crypto::decrypt(substr($ciphertext, 0, -2) . 'xx'), 'Authenticated encryption accepted tampered ciphertext.');
$GLOBALS['promapaywc_test_salt'] = 'rotated-local-qa-salt';
$throws(static fn () => PromaPay_WC_Crypto::decrypt($ciphertext), 'Credential ciphertext remained decryptable after the site key changed.');
$GLOBALS['promapaywc_test_salt'] = 'a-local-qa-salt-that-is-never-a-production-key';

$assert(PromaPay_WC_Api_Client::normalize_base_url('https://merchant-api.example.test/proma') === 'https://merchant-api.example.test/proma', 'HTTPS API URL was not normalized.');
foreach (['http://127.0.0.1:8080','http://api.example.test','ftp://api.example.test','https://user:pass@api.example.test','https://api.example.test/path/../internal','https://[::1]:8080'] as $unsafeUrl) {
    $throws(static fn () => PromaPay_WC_Api_Client::normalize_base_url($unsafeUrl), 'Unsafe API endpoint was accepted: ' . $unsafeUrl);
}

$repository = new PromaPay_WC_Connection_Repository();
$connection = [
    'connection_uuid' => 'd71e105a-21af-4af0-bfb7-84da35da3ab6',
    'base_url' => 'https://merchant-api.example.test',
    'token_ciphertext' => $ciphertext,
];
$captured = null;
$client = new PromaPay_WC_Api_Client($repository, static function (string $url, array $args) use (&$captured): array {
    $captured = [$url, $args];
    return [
        'response' => ['code' => 200],
        'body' => json_encode(['data' => ['connection' => ['environment' => 'test', 'api_version' => 'v1']]]),
    ];
});
$result = $client->request($connection, 'GET', '/connection');
$assert($result['ok'] === true && $result['data']['connection']['environment'] === 'test', 'API connection response was not accepted.');
$assert($captured[0] === 'https://merchant-api.example.test/api/v1/connection', 'API client constructed an unexpected URL.');
$assert($captured[1]['redirection'] === 0 && $captured[1]['sslverify'] === true && $captured[1]['timeout'] === 5, 'API client transport security limits are missing.');
$assert($captured[1]['headers']['Authorization'] === 'Bearer ' . $token, 'API client did not send the test credential.');
$assert(!str_contains(wp_json_encode($GLOBALS['wpdb']->inserted), $token), 'Communication log stored the API credential.');

$policy = [
    'policy_type' => 'financial',
    'policy_version_uuid' => 'c3bc78aa-50fb-4e64-9e26-b48c6a32c55a',
    'source_policy_sha256' => str_repeat('b', 64),
    'document' => ['monthly_rate_bps' => 250, 'methods' => ['compound','flat'], 'limits' => ['max_months' => 6]],
];
$canonicalPolicy = ['monthly_rate_bps'=>250,'methods'=>['compound','flat'],'limits'=>['max_months'=>6]];
$policy['policy_sha256'] = hash('sha256', json_encode($canonicalPolicy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
$assert(PromaPay_WC_Policy_Service::valid_policy($policy), 'Valid published financial policy was rejected.');
$invalidPolicy = $policy;
$invalidPolicy['document']['monthly_rate_bps'] = '250';
$assert(!PromaPay_WC_Policy_Service::valid_policy($invalidPolicy), 'Financial policy accepted a non-integer rate.');
$repository->cache_policy('d71e105a-21af-4af0-bfb7-84da35da3ab6', $policy);
$assert(!str_contains((string) $GLOBALS['wpdb']->cachedPolicyRow['payload_ciphertext'], 'monthly_rate_bps'), 'Financial policy cache was stored as plaintext.');
$assert($repository->cached_policy('d71e105a-21af-4af0-bfb7-84da35da3ab6') === $policy, 'Encrypted policy cache did not round-trip.');
$GLOBALS['wpdb']->cachedPolicyRow['expires_at_epoch'] = time() - 1;
$assert($repository->cached_policy('d71e105a-21af-4af0-bfb7-84da35da3ab6') === null, 'Expired financial policy cache was returned.');

$GLOBALS['wpdb']->connectionRow = $connection;
$refreshClient = new PromaPay_WC_Api_Client($repository, static function (string $url) use ($policy): array {
    $data = str_ends_with($url, '/connection')
        ? ['connection'=>['environment'=>'test','api_version'=>'v1']]
        : ['policy'=>$policy];
    return ['response'=>['code'=>200], 'body'=>json_encode(['data'=>$data], JSON_THROW_ON_ERROR)];
});
$refreshed = (new PromaPay_WC_Policy_Service($repository, $refreshClient))->refresh('d71e105a-21af-4af0-bfb7-84da35da3ab6');
$assert($refreshed['ok'] === true && $repository->cached_policy('d71e105a-21af-4af0-bfb7-84da35da3ab6') === $policy, 'Connection test did not refresh the encrypted policy cache.');

echo "PROMAPAY_WC_PHASE6_UNIT_OK\n";
