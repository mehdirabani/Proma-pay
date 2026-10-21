<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require dirname(__DIR__) . '/bootstrap.php';

final class FakeZibalClientForMoneyTest extends ZibalClient
{
    public $nextBody = [];
    public $lastPayload = null;

    protected function postJson($url, array $payload)
    {
        $this->lastPayload = $payload;
        return ['ok' => true, 'body' => $this->nextBody];
    }
}

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$client = new FakeZibalClientForMoneyTest('qa-merchant');
$client->nextBody = ['result' => 100, 'trackId' => '12345'];
$requested = $client->request('3,000,001', 'https://qa.example.test/callback', 'qa');
$assert($requested['ok'] && $client->lastPayload['amount'] === 30000010, 'Toman to Rial conversion changed the exact payment amount.');
$invalidRequest = $client->request((string) PHP_INT_MAX, 'https://qa.example.test/callback', 'qa');
$assert(!$invalidRequest['ok'], 'Overflowing Rial amount was accepted.');

$client->nextBody = ['result' => 100, 'amount' => '30000010', 'refNumber' => 'qa-ref'];
$verified = $client->verify('12345');
$assert($verified['ok'] && $verified['amount_toman'] === 3000001, 'Verified Rial amount was not converted exactly.');
foreach (['30000011', '9223372036854775808', '3.5', null] as $badAmount) {
    $client->nextBody['amount'] = $badAmount;
    $result = $client->verify('12345');
    $assert(!$result['ok'], 'Invalid verified amount was accepted.');
}
$client->nextBody = ['result' => 100, 'amount' => '30000010'];
$missingReference = $client->verify('12345');
$assert(!$missingReference['ok'] && !empty($missingReference['gateway_verified']), 'Captured gateway money without reference was not flagged for reconciliation.');

echo "ZIBAL_CLIENT_EXACT_MONEY_OK\n";
