<?php

require_once dirname(__DIR__, 2) . '/src/Services/ZarinpalClient.php';
require_once dirname(__DIR__, 2) . '/src/Services/ZarinpalErrorMapper.php';

use Proma\Plugins\Zarinpal\Services\ZarinpalClient;
use Proma\Plugins\Zarinpal\Services\ZarinpalErrorMapper;

$sequence = 0;
$transport = static function ($url, array $payload) use (&$sequence) {
    $sequence++;
    if ($sequence === 1) {
        foreach (['merchant_id', 'amount', 'description', 'callback_url'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new RuntimeException('Missing request payload: ' . $key);
            }
        }
        return ['http_status' => 200, 'body' => ['data' => ['code' => 100, 'message' => 'Success', 'authority' => str_repeat('A', 36), 'fee_type' => 'Merchant', 'fee' => 0], 'errors' => []]];
    }
    foreach (['merchant_id', 'amount', 'authority'] as $key) {
        if (!array_key_exists($key, $payload)) {
            throw new RuntimeException('Missing verify payload: ' . $key);
        }
    }
    return ['http_status' => 200, 'body' => ['data' => ['code' => 101, 'message' => 'Verified', 'ref_id' => 12345, 'card_pan' => '621986******8080', 'card_hash' => hash('sha256', 'card'), 'fee_type' => 'Merchant', 'fee' => 0], 'errors' => []]];
};
$client = new ZarinpalClient(10, 30, $transport);
$request = $client->request('production', ['merchant_id' => '123e4567-e89b-42d3-a456-426614174000', 'amount' => 100000, 'currency' => 'IRT', 'description' => 'test', 'callback_url' => 'https://example.com/callback']);
$verify = $client->verify('production', ['merchant_id' => '123e4567-e89b-42d3-a456-426614174000', 'amount' => 100000, 'authority' => str_repeat('A', 36)]);
if (ZarinpalErrorMapper::safeCode($request['body']) !== 100 || ZarinpalErrorMapper::safeCode($verify['body']) !== 101) {
    throw new RuntimeException('Official response mapping failed.');
}

echo "ZARINPAL_OFFICIAL_PROTOCOL_OK\n";
