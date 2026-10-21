<?php

require_once dirname(__DIR__, 2) . '/src/Services/ZarinpalClient.php';

use Proma\Plugins\Zarinpal\Services\ZarinpalClient;

$captured = [];
$transport = static function ($url, array $payload) use (&$captured) {
    $captured[] = ['url' => $url, 'payload' => $payload];
    if (substr($url, -11) === 'verify.json') {
        return ['http_status' => 200, 'body' => ['data' => ['code' => 101, 'ref_id' => 123456, 'card_pan' => '621986******8080'], 'errors' => []]];
    }
    return ['http_status' => 200, 'body' => ['data' => ['code' => 100, 'authority' => str_repeat('S', 36)], 'errors' => []]];
};
$client = new ZarinpalClient(5, 15, $transport);
$client->request('sandbox', ['merchant_id' => 'sandbox', 'amount' => 1000]);
$client->verify('sandbox', ['merchant_id' => 'sandbox', 'amount' => 1000, 'authority' => str_repeat('S', 36)]);
if ($captured[0]['url'] !== 'https://sandbox.zarinpal.com/pg/v4/payment/request.json' || $captured[1]['url'] !== 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json') {
    throw new RuntimeException('Sandbox endpoint isolation failed.');
}
if ($client->startUrl('sandbox', str_repeat('S', 36)) !== 'https://sandbox.zarinpal.com/pg/StartPay/' . str_repeat('S', 36)) {
    throw new RuntimeException('Sandbox redirect isolation failed.');
}

echo "ZARINPAL_SANDBOX_PROTOCOL_OK\n";
