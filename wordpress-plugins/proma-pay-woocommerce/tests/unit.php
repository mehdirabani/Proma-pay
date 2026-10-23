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
require_once dirname(__DIR__) . '/includes/class-promapaywc-payment-adapter.php';
require_once dirname(__DIR__) . '/includes/class-promapaywc-quote-service.php';

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

$assert(PromaPay_WC_Quote_Service::to_toman('100', 1, 0) === 100, 'Toman currency conversion failed.');
$assert(PromaPay_WC_Quote_Service::to_toman('1000', 10, 0) === 100, 'Rial to toman conversion failed.');
$assert(PromaPay_WC_Quote_Service::to_toman('1000.00', 10, 2) === 100, 'Decimal rial conversion failed.');
$assert(PromaPay_WC_Quote_Service::from_toman(100, 10, 0) === '1000', 'Toman to rial conversion failed.');
$assert(PromaPay_WC_Quote_Service::from_toman(100, 1, 2) === '100.00', 'Toman decimal formatting failed.');
$throws(static fn () => PromaPay_WC_Quote_Service::to_toman('1001', 10, 0), 'Fractional-toman value was rounded silently.');

$guaranteeDocument = [
    'guarantee_type' => 'sayadi_cheque',
    'required_cheque_count' => 1,
    'guarantee_base' => 'scheduled_installments',
    'multiplier_units' => 20000,
    'multiplier_scale' => 10000,
    'central_minimum_multiplier_units' => 15000,
    'central_maximum_multiplier_units' => 25000,
    'beneficiary_legal_name' => 'Proma Pay Test Legal Entity',
    'beneficiary_identifier' => '1234567890',
    'central_policy_uuid' => 'c6da1a65-1186-48e7-85f8-f86b5452b65f',
    'central_policy_version_no' => 1,
];
$guaranteePolicyHash = hash('sha256', json_encode($guaranteeDocument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
$quote = [
    'quote_uuid' => 'd3c8f845-c90e-4545-a94f-52e81d132504',
    'policy_version_uuid' => 'c3bc78aa-50fb-4e64-9e26-b48c6a32c55a',
    'policy_sha256' => str_repeat('a', 64),
    'expires_at' => gmdate('c', time() + 600),
    'calculation' => [
        'currency_unit' => 'toman', 'cash_price' => 1100000, 'down_payment' => 200000,
        'months' => 2, 'interest_method' => 'flat', 'total_installments' => 900000,
        'total_customer_payable' => 1100000, 'first_installment_due_date' => '2027-01-20',
        'installment_schedule' => [
            ['installment_number' => 1, 'amount' => 450000, 'due_date' => '2027-01-20'],
            ['installment_number' => 2, 'amount' => 450000, 'due_date' => '2027-02-20'],
        ],
    ],
    'guarantee' => [
        'policy_version_uuid' => 'e33b27c2-6ac6-4f5e-aedb-d2a7d7e865c4',
        'source_policy_sha256' => str_repeat('c', 64),
        'policy_sha256' => $guaranteePolicyHash,
        'document' => $guaranteeDocument,
        'calculation' => [
            'guarantee_type' => 'sayadi_cheque', 'required_cheque_count' => 1,
            'guarantee_base' => 'scheduled_installments', 'scheduled_installments_toman' => 900000,
            'multiplier_units' => 20000, 'multiplier_scale' => 10000,
            'cheque_amount_rial' => 18000000, 'first_installment_due_date' => '2027-01-20',
            'cheque_due_date' => '2027-01-20', 'rounding_policy' => 'nearest_rial_half_up',
        ],
    ],
];
$assert(PromaPay_WC_Quote_Service::valid_checkout_quote($quote, 1100000, 200000, 2, 'flat'), 'Valid financial and guarantee quote was rejected.');
$badCheque = $quote;
$badCheque['guarantee']['calculation']['cheque_due_date'] = '2027-02-20';
$assert(!PromaPay_WC_Quote_Service::valid_checkout_quote($badCheque, 1100000, 200000, 2, 'flat'), 'Quote accepted a cheque due date that differs from the first installment.');
$badSum = $quote;
$badSum['calculation']['total_customer_payable']++;
$assert(!PromaPay_WC_Quote_Service::valid_checkout_quote($badSum, 1100000, 200000, 2, 'flat'), 'Quote accepted a payable total inconsistent with deposit plus installments.');
$badAmount = $quote;
$badAmount['guarantee']['calculation']['cheque_amount_rial']++;
$assert(!PromaPay_WC_Quote_Service::valid_checkout_quote($badAmount, 1100000, 200000, 2, 'flat'), 'Quote accepted a cheque amount inconsistent with the signed multiplier.');

echo "PROMAPAY_WC_PHASE7_UNIT_OK\n";
