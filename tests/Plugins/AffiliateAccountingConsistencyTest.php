<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/plugins/PromaAffiliate/src/Services/AffiliateService.php');
$ledger = (string) file_get_contents($root . '/plugins/PromaAccounting/src/Services/LedgerService.php');
$provider = (string) file_get_contents($root . '/plugins/PromaAffiliate/src/AffiliateServiceProvider.php');
$manager = (string) file_get_contents($root . '/core/PluginManager.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$postAccountingStart = strpos($service, 'private static function postAccounting(');
$assert($postAccountingStart !== false && str_contains($service, 'public static function accountingReconciliation('), 'Affiliate projection and reconciliation source must exist.');
$postAccounting = substr($service, $postAccountingStart);
$assert(str_contains($postAccounting, 'throw new \\RuntimeException(') && str_contains($postAccounting, 'throw $e;'), 'Missing Accounting or ledger failure must abort the affiliate operation.');
$assert(str_contains($ledger, 'if (!\\Model::db()->inTransaction())') && str_contains($ledger, 'if ($started) {'), 'LedgerService must join the caller transaction when one is active.');
$assert(str_contains($service, 'accounting_ledger_entries le ON le.idempotency_key=tx.idempotency_key'), 'Reconciliation must compare idempotency keys.');
$assert(str_contains($service, "tx.transaction_type<>'purchase_conversion'"), 'Wallet-neutral conversions must be excluded from ledger reconciliation.');
$assert(str_contains($provider, 'AffiliateService::accountingReconciliation()'), 'Plugin health must run ledger reconciliation.');
$assert(str_contains($manager, 'foreach ((array) ($manifest[\'dependencies\'] ?? []) as $dependency)'), 'PluginManager must enforce declared dependencies.');

$installStart = strpos($manager, 'public function install(');
$activateStart = strpos($manager, 'public function activate(', $installStart ?: 0);
$healthStart = strpos($manager, 'public function healthCheck(');
$uploadStart = strpos($manager, 'public function upload(', $healthStart ?: 0);
$providerLoadStart = strpos($manager, 'protected function loadProvider(');
$providerFactoryStart = strpos($manager, 'protected function providerFor(', $providerLoadStart ?: 0);
$assert($installStart !== false && $activateStart !== false && $activateStart > $installStart, 'Plugin installation lifecycle methods must be present.');
$assert($healthStart !== false && $uploadStart !== false && $uploadStart > $healthStart, 'Plugin health lifecycle methods must be present.');
$assert($providerLoadStart !== false && $providerFactoryStart !== false && $providerFactoryStart > $providerLoadStart, 'Provider registration lifecycle must be present.');
$installLifecycle = substr($manager, $installStart, $activateStart - $installStart);
$healthLifecycle = substr($manager, $healthStart, $uploadStart - $healthStart);
$providerLifecycle = substr($manager, $providerLoadStart, $providerFactoryStart - $providerLoadStart);
$assert(str_contains($installLifecycle, '$this->loadProvider($registeredForInstall, false)'), 'Install must register the provider before install-time health checks.');
$assert(str_contains($healthLifecycle, '$this->loadProvider($registered, false)'), 'Health checks must register the provider before invoking plugin services.');
$assert(str_contains($providerLifecycle, '$instance->register($this, $manifest)'), 'Provider loading must invoke register() exactly before lifecycle hooks use its services.');

echo "AFFILIATE_ACCOUNTING_CONSISTENCY_STATIC_OK\n";
