<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/MoneyMath.php';
require_once __DIR__ . '/../helpers/InstallmentFinancialCalculator.php';
require_once __DIR__ . '/../helpers/MerchantApiCredentialToken.php';
require_once __DIR__ . '/../helpers/MerchantAccessContext.php';
require_once __DIR__ . '/../core/HttpException.php';
require_once __DIR__ . '/../helpers/MerchantApiGateway.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$rejects = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (Throwable $e) {
        return;
    }
    throw new RuntimeException($message);
};

$key = 'phase5-idempotency-0001';
$assert(MerchantApiGateway::normalizeIdempotencyKey($key) === $key, 'Valid idempotency key changed.');
$rejects(static function (): void {
    MerchantApiGateway::normalizeIdempotencyKey('short');
}, 'Short idempotency key was accepted.');
$payloadA = ['external_order_id' => 'WC-100', 'quote' => ['months' => 6, 'cash_price_toman' => 1000000]];
$payloadB = ['quote' => ['cash_price_toman' => 1000000, 'months' => 6], 'external_order_id' => 'WC-100'];
$assert(
    hash_equals(
        MerchantApiGateway::canonicalRequestHash('POST', '/orders', $payloadA),
        MerchantApiGateway::canonicalRequestHash('POST', '/orders', $payloadB)
    ),
    'Canonical request hashing depends on associative key order.'
);

$policy = [
    'monthly_rate_bps' => 250,
    'methods' => ['flat', 'compound'],
    'limits' => ['min_order_toman' => 100000, 'max_order_toman' => 10000000, 'max_months' => 12, 'min_down_payment_bps' => 1000],
];
$quote = MerchantApiGateway::calculateQuote($policy, [
    'cash_price_toman' => 1000000,
    'down_payment_toman' => 250000,
    'months' => 3,
    'interest_method' => 'flat',
]);
$assert($quote['financed_principal'] === 750000, 'Quote used the wrong financed principal.');
$assert($quote['total_interest'] === 56250, 'Quote applied the policy rate incorrectly.');
$assert($quote['total_installments'] === 806250, 'Installment schedule total is incorrect.');
$assert($quote['total_customer_payable'] === 1056250, 'Customer payable total is incorrect.');

$rejects(static function () use ($policy): void {
    MerchantApiGateway::calculateQuote($policy, [
        'cash_price_toman' => 1000000, 'down_payment_toman' => 250000, 'months' => 3,
        'interest_method' => 'flat', 'monthly_rate_bps' => 999,
    ]);
}, 'Client override of the published interest rate was accepted.');
$rejects(static function () use ($policy): void {
    MerchantApiGateway::calculateQuote($policy, [
        'cash_price_toman' => 1000000, 'down_payment_toman' => 1, 'months' => 3, 'interest_method' => 'flat',
    ]);
}, 'Down payment below published policy was accepted.');
$rejects(static function () use ($policy): void {
    MerchantApiGateway::calculateQuote($policy, [
        'cash_price_toman' => 1000000, 'down_payment_toman' => 250000, 'months' => 13, 'interest_method' => 'flat',
    ]);
}, 'Months outside published policy were accepted.');
$rejects(static function () use ($policy): void {
    MerchantApiGateway::calculateQuote($policy, [
        'cash_price_toman' => 1000000, 'down_payment_toman' => 250000, 'months' => 3.0, 'interest_method' => 'flat',
    ]);
}, 'Floating-point money or term input was accepted.');
$rejects(static function (): void {
    MerchantApiGateway::calculateQuote([
        'monthly_rate_bps' => 10000,
        'methods' => ['compound'],
        'limits' => ['min_order_toman' => 1, 'max_order_toman' => PHP_INT_MAX, 'max_months' => 480],
    ], [
        'cash_price_toman' => PHP_INT_MAX, 'down_payment_toman' => 0, 'months' => 480, 'interest_method' => 'compound',
    ]);
}, 'Overflow-sized financial quote was accepted.');

$assert(
    MerchantApiGateway::requestPath(['REQUEST_URI' => '/api/v1/orders']) === '/orders',
    'Pretty API path was not normalized.'
);
$rejects(static function (): void {
    MerchantApiGateway::requestPath(['REQUEST_URI' => '/api/v1/../install.php']);
}, 'API path traversal was accepted.');
$oversizedResponse = MerchantApiGateway::handle(
    ['REQUEST_METHOD' => 'GET','REQUEST_URI' => '/api/v1/connection','REMOTE_ADDR' => '127.0.0.1'],
    str_repeat('x', MerchantApiGateway::MAX_BODY_BYTES + 1),
    []
);
$assert($oversizedResponse['status'] === 413, 'Oversized request body was accepted.');
$httpResponse = MerchantApiGateway::handle(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/connection', 'REMOTE_ADDR' => '203.0.113.10'],
    '',
    []
);
$assert($httpResponse['status'] === 426 && $httpResponse['body']['error']['code'] === 'https_required', 'Public HTTP transport was not rejected.');
$loopbackResponse = MerchantApiGateway::handle(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/connection', 'REMOTE_ADDR' => '127.0.0.1'],
    '',
    []
);
$assert($loopbackResponse['status'] === 401, 'Loopback request did not proceed to authentication.');

echo "MERCHANT_PUBLIC_API_PHASE5_OK\n";
