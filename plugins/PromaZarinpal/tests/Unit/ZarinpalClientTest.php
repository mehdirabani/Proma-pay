<?php

require_once dirname(__DIR__, 2) . '/src/Services/ZarinpalClient.php';

use Proma\Plugins\Zarinpal\Services\ZarinpalClient;

$calls = [];
$transport = static function ($url, array $payload, $connectTimeout, $responseTimeout) use (&$calls) {
    $calls[] = compact('url', 'payload', 'connectTimeout', 'responseTimeout');
    return ['http_status' => 200, 'body' => ['data' => ['code' => 100, 'authority' => str_repeat('A', 36)], 'errors' => []]];
};
$client = new ZarinpalClient(8, 25, $transport);
$response = $client->request('production', ['merchant_id' => 'test', 'amount' => 1000, 'description' => 'test', 'callback_url' => 'https://example.com/callback']);
if (empty($response['ok']) || count($calls) !== 1) {
    throw new RuntimeException('Request transport failed.');
}
if ($calls[0]['url'] !== 'https://payment.zarinpal.com/pg/v4/payment/request.json') {
    throw new RuntimeException('Production endpoint mismatch.');
}
if ($client->startUrl('production', str_repeat('A', 36)) !== 'https://payment.zarinpal.com/pg/StartPay/' . str_repeat('A', 36)) {
    throw new RuntimeException('Production redirect mismatch.');
}
try {
    $client->startUrl('production', 'https://evil.example');
    throw new RuntimeException('Unsafe authority was accepted.');
} catch (InvalidArgumentException $expected) {
}
try {
    $client->startUrl('production', str_repeat('S', 36));
    throw new RuntimeException('Sandbox authority was accepted in production.');
} catch (InvalidArgumentException $expected) {
}

echo "ZARINPAL_CLIENT_OK\n";
