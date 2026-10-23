<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/MerchantSiteOrigin.php';
require_once __DIR__ . '/../helpers/MerchantPolicyDocument.php';
require_once __DIR__ . '/../helpers/MerchantApiCredentialToken.php';
require_once __DIR__ . '/../helpers/MerchantAccessContext.php';
require_once __DIR__ . '/../helpers/MerchantMembershipPolicy.php';
require_once __DIR__ . '/../helpers/MerchantAuditMetadata.php';
require_once __DIR__ . '/../models/MerchantAuditLog.php';

function merchantPhase2Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function merchantPhase2Rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        return;
    }
    throw new RuntimeException($message);
}

merchantPhase2Assert(
    MerchantSiteOrigin::normalize(' HTTPS://Store.Example.ir:443/ ') === 'https://store.example.ir',
    'Merchant site origin was not normalized.'
);
foreach ([
    'http://store.example.ir',
    'https://user:pass@store.example.ir',
    'https://store.example.ir/path',
    'https://store.example.ir?next=example.ir',
    'https://localhost',
    'https://127.0.0.1',
    'https://store.example.ir:8443',
    'javascript://store.example.ir',
] as $invalidOrigin) {
    merchantPhase2Rejects(
        static function () use ($invalidOrigin): void {
            MerchantSiteOrigin::normalize($invalidOrigin);
        },
        'Unsafe merchant origin was accepted: ' . $invalidOrigin
    );
}

$policyA = MerchantPolicyDocument::canonicalize('financial', [
    'monthly_rate_bps' => 250,
    'methods' => ['flat', 'compound'],
    'limits' => ['max_months' => 6, 'min_order_toman' => 1000000],
]);
$policyB = MerchantPolicyDocument::canonicalize('financial', [
    'limits' => ['min_order_toman' => 1000000, 'max_months' => 6],
    'methods' => ['flat', 'compound'],
    'monthly_rate_bps' => 250,
]);
merchantPhase2Assert($policyA['json'] === $policyB['json'], 'Policy JSON is not canonical.');
merchantPhase2Assert($policyA['sha256'] === $policyB['sha256'], 'Canonical policy hashes differ.');
merchantPhase2Rejects(
    static function (): void {
        MerchantPolicyDocument::canonicalize('unknown', ['x' => 1]);
    },
    'Unknown policy type was accepted.'
);

$issued = MerchantApiCredentialToken::issue('test');
merchantPhase2Assert(
    (bool) preg_match('/^pmp_test_[A-Za-z0-9_-]{43}$/D', $issued['token']),
    'Test credential format is invalid.'
);
merchantPhase2Assert(
    hash_equals($issued['hash'], MerchantApiCredentialToken::hashForLookup($issued['token'])),
    'Stored credential digest does not match the one-time token.'
);
merchantPhase2Assert(
    !hash_equals($issued['hash'], MerchantApiCredentialToken::issue('test')['hash']),
    'Credential generator repeated a token.'
);
merchantPhase2Assert(
    MerchantApiCredentialToken::prefix($issued['token']) === $issued['prefix'],
    'Credential prefix mismatch.'
);
$scopes = MerchantApiCredentialToken::normalizeScopes(['quote:create', 'merchant.read', 'quote:create']);
merchantPhase2Assert($scopes === ['merchant.read', 'quote:create'], 'Scopes were not normalized deterministically.');
merchantPhase2Rejects(
    static function (): void {
        MerchantApiCredentialToken::normalizeScopes(['*']);
    },
    'Wildcard API scope was accepted.'
);
merchantPhase2Rejects(
    static function (): void {
        MerchantApiCredentialToken::hashForLookup('pmp_test_not-a-real-token');
    },
    'Malformed API credential was accepted.'
);

$context = new MerchantAccessContext(10, 20, 30, ['quote:create', 'merchant.read']);
merchantPhase2Assert($context->merchantId() === 10 && $context->siteId() === 20, 'Tenant context IDs changed.');
merchantPhase2Assert($context->allows('quote:create'), 'Granted API scope was rejected.');
merchantPhase2Assert(!$context->allows('quote:write'), 'Unlisted API scope was granted.');
merchantPhase2Rejects(
    static function () use ($context): void {
        $context->requireScope('quote:write');
    },
    'Missing API scope did not fail closed.'
);

merchantPhase2Assert(MerchantMembershipPolicy::allows('finance', 'settlement.read'), 'Finance read permission is missing.');
merchantPhase2Assert(!MerchantMembershipPolicy::allows('finance', 'connection.manage'), 'Finance role can manage connections.');
merchantPhase2Assert(!MerchantMembershipPolicy::allows('owner', 'settlement.approve'), 'Unspecified financial approval was granted.');

$auditJson = MerchantAuditMetadata::encode(['scopes' => ['merchant.read', 'quote:create'], 'version' => 1]);
merchantPhase2Assert(strpos($auditJson, 'merchant.read') !== false, 'Safe list metadata was rejected.');
merchantPhase2Rejects(
    static function (): void {
        MerchantAuditMetadata::encode(['access_token' => 'must-not-be-stored']);
    },
    'Raw credential data was accepted in audit metadata.'
);
$rawToken = $issued['token'];
merchantPhase2Rejects(
    static function () use ($rawToken): void {
        MerchantAuditMetadata::encode(['details' => ['values' => ['credential observed: ' . $rawToken]]]);
    },
    'Raw credential text was accepted under an innocent nested metadata key.'
);
merchantPhase2Assert(
    (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', MerchantAuditLog::newUuid()),
    'Audit UUID is not an RFC 4122 v4 UUID.'
);

echo "MERCHANT_CORE_PHASE2_OK\n";
