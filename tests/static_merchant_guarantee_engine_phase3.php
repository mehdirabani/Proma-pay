<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if (!is_string($contents)) {
        throw new RuntimeException('Required Phase 3 file is missing: ' . $path);
    }
    return $contents;
};

$migration = $read('database/migrations/2026_10_23_guarantee_engine_phase3.sql');
$freshSchema = $read('database/proma-pay-install.sql');
$tables = [
    'merchant_central_guarantee_policy_versions',
    'merchant_central_guarantee_policy_head',
    'merchant_central_guarantee_policy_events',
    'merchant_contract_links',
    'merchant_cheque_guarantees',
    'merchant_cheque_guarantee_events',
];
foreach ($tables as $table) {
    $create = '/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/') . '\s*\(/i';
    $assert((bool) preg_match($create, $migration), 'Migration omits ' . $table . '.');
    $assert((bool) preg_match($create, $freshSchema), 'Fresh schema omits ' . $table . '.');
}
$assert(strpos($migration, 'FOREIGN KEY (connection_id,merchant_id,site_id)') !== false, 'Order binding does not enforce the full connection/site owner tuple.');
$assert(strpos($migration, 'FOREIGN KEY (contract_link_id,merchant_id,site_id,connection_id,contract_id)') !== false, 'Guarantee is not tenant-bound to its Woo order and contract.');
$assert(strpos($migration, 'FOREIGN KEY (policy_version_id,connection_id,policy_type)') !== false, 'Guarantee snapshot may point at another connection policy.');
$assert(strpos($migration, 'customer_id BIGINT UNSIGNED NOT NULL') !== false, 'Guarantee record does not retain the contract customer reference.');
$assert(strpos($migration, "sayad_status ENUM('pending','registered','confirmed','rejected') NOT NULL DEFAULT 'pending'") !== false, 'Sayadi review does not start pending.');
$assert(strpos($migration, "physical_status ENUM('pending','received','returned','released','legal_followup') NOT NULL DEFAULT 'pending'") !== false, 'Physical cheque custody states are missing.');
$assert(strpos($migration, 'registration_evidence_sha256 CHAR(64)') !== false && strpos($migration, 'confirmation_evidence_sha256 CHAR(64)') !== false, 'Registration and confirmation evidence hashes are not retained.');
$assert(strpos($migration, 'legal_approval_reference VARCHAR(190)') !== false, 'Central policy does not require an approval reference.');
$assert(!preg_match('/^\s*(?:DROP|TRUNCATE|DELETE|UPDATE)\s+/im', $migration), 'Migration destructively changes existing guarantee records.');
$assert(strpos($migration, 'ALTER TABLE contract_guarantees') === false, 'WooCommerce guarantee migration alters the direct-sales guarantee table.');

$calculator = $read('helpers/MerchantGuaranteeCalculator.php');
foreach (["'scheduled_installments'", "'nearest_rial_half_up'", "'cheque_due_date' => \$normalized[0]['due_date']", 'PHP_INT_MAX'] as $guard) {
    $assert(strpos($calculator, $guard) !== false, 'Integer guarantee calculation invariant missing: ' . $guard);
}
$guaranteeRepository = $read('models/MerchantChequeGuaranteeRepository.php');
foreach ([
    "'guarantee:create'",
    'l.order_status = \'contracted\'',
    "\$guarantee['sayad_status'] !== 'confirmed'",
    "merchant_cheque_due_date_must_match_first_installment",
    "merchant_guarantee_amendment_required",
    'PluginManager::requirePermission($permission)',
    'FOR UPDATE',
] as $guard) {
    $assert(strpos($guaranteeRepository, $guard) !== false, 'Guarantee state/tenant guard missing: ' . $guard);
}
$linkRepository = $read('models/MerchantContractLinkRepository.php');
$assert(strpos($linkRepository, 'context->connectionId()') !== false && strpos($linkRepository, 'context->merchantId()') !== false, 'Woo contract link is not scoped to the authenticated connection.');
$policyRepository = $read('models/MerchantPolicyRepository.php');
$assert(strpos($policyRepository, "'policy.manage'") !== false, 'Connection policy publishing lacks a merchant permission check.');
$centralRepository = $read('models/MerchantCentralGuaranteePolicyRepository.php');
$assert(strpos($centralRepository, "PluginManager::requirePermission('merchant.guarantee_policy.manage')") !== false, 'Central policy publishing lacks a system-admin check.');

$contractModel = $read('models/Contract.php');
$assert(strpos($contractModel, "SchemaGuard::tableExists('merchant_contract_links')") !== false, 'Contract edits do not guard only WooCommerce-linked schedules.');
$assert(strpos($contractModel, 'assertScheduleDateChangeAllowed') !== false, 'First due date edits do not check Sayadi status.');
$assert(strpos($contractModel, 'assertCurrentScheduleMatchesConfirmedGuarantee') !== false, 'Changed Woo schedule totals can bypass guarantee snapshot validation.');
$gate = $read('tools/release-gate.php');
foreach (['tests/merchant_guarantee_engine_phase3.php', 'tests/static_merchant_guarantee_engine_phase3.php'] as $test) {
    $assert(strpos($gate, "'" . $test . "'") !== false, 'Release gate omits ' . $test . '.');
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "STATIC_MERCHANT_GUARANTEE_ENGINE_PHASE3_OK\n";
