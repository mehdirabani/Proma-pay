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
        throw new RuntimeException('Required Phase 4 file is missing: ' . $path);
    }
    return $contents;
};

$migration = $read('database/migrations/2026_10_23_settlement_commission_phase4.sql');
$freshSchema = $read('database/proma-pay-install.sql');
$tables = [
    'merchant_financial_control_locks',
    'merchant_settlement_obligations', 'merchant_settlement_adjustments',
    'merchant_settlement_batches', 'merchant_settlement_lines',
    'merchant_settlement_events', 'merchant_liquidity_accounts',
    'merchant_liquidity_events', 'merchant_commission_entries',
    'merchant_reward_entries', 'merchant_financial_reconciliations',
];
foreach ($tables as $table) {
    $pattern = '/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/') . '\s*\(/i';
    $assert((bool) preg_match($pattern, $migration), 'Migration omits ' . $table . '.');
    $assert((bool) preg_match($pattern, $freshSchema), 'Fresh install omits ' . $table . '.');
}
$assert(strpos($migration, 'UNIQUE KEY uq_merchant_settlement_obligation_link (contract_link_id)') !== false, 'An order obligation can be created more than once.');
$assert(strpos($migration, 'UNIQUE KEY uq_merchant_settlement_line_obligation (obligation_id)') !== false, 'An obligation can be paid in more than one batch.');
$assert(strpos($migration, 'FOREIGN KEY (contract_link_id,merchant_id,site_id,connection_id,contract_id)') !== false, 'Settlement obligations are not scoped to a linked contract.');
$assert(strpos($migration, 'FOREIGN KEY (settlement_policy_version_id,connection_id,policy_type)') !== false, 'Settlement obligations do not retain their policy version.');
$assert(strpos($migration, 'cash_product_price_toman') !== false && strpos($migration, 'verified_down_payment_toman') !== false, 'Cash-price/down-payment inputs are not snapshotted.');
$assert(strpos($migration, 'legal_approval_reference') !== false && strpos($migration, 'funding_account_id') !== false, 'Legal and funding approval snapshots are missing.');
$assert(strpos($migration, 'bank_reference') !== false && strpos($migration, 'reconciliation_status') !== false, 'Bank payment and reconciliation references are missing.');
$assert(strpos($migration, 'merchant_reward_entries') !== false, 'Referral rewards do not have a separate ledger.');
$assert(strpos($migration, 'source_event_uuid') !== false, 'Commission/reward event idempotency is missing.');
$assert(strpos($migration, 'uq_merchant_commission_reference_rule') !== false
    && strpos($migration, 'uq_merchant_reward_reference_rule') !== false, 'Commission and reward references are not idempotent.');
$assert(strpos($migration, 'calculation_snapshot_json') !== false && strpos($migration, 'refund_window_days') !== false, 'Commission calculation/version lifecycle snapshots are missing.');
$assert(!preg_match('/CREATE TABLE IF NOT EXISTS contract_guarantees\b/i', $migration), 'Phase 4 modifies the direct-sale guarantee schema.');
$assert(strpos($read('models/MerchantPolicyRepository.php'), 'MerchantSettlementPolicy::resolve') !== false, 'Settlement policy is not validated before publication.');
$assert(strpos($read('models/MerchantPolicyRepository.php'), 'MerchantCommissionPolicy::resolve') !== false, 'Commission policy is not validated before publication.');
$assert(strpos($read('models/MerchantCommissionRepository.php'), 'recordVerifiedReferralReward') !== false, 'Verified referral reward ledger writer is missing.');
$assert(strpos($read('models/MerchantCommissionRepository.php'), "status = 'paid'") !== false, 'Commission source events do not verify paid source state.');
$assert(strpos($read('models/MerchantSettlementRepository.php'), 'adjustments_resolved') !== false, 'Pending settlement adjustments do not block eligibility.');
$assert(strpos($read('models/MerchantSettlementRepository.php'), 'failPayoutAndRelease') !== false, 'Verified payout failure does not release reserved cash.');
$assert(strpos($read('models/MerchantSettlementRepository.php'), 'lockPlatformExposureGuard') !== false, 'Global platform exposure checks are not serialized.');
$assert(strpos($read('models/MerchantSettlementRepository.php'), 'shared_platform_hidden') !== false, 'Shared platform liquidity is exposed through tenant reads.');
$assert(strpos($read('helpers/MerchantSettlementCalculator.php'), 'cash_product_price_toman') !== false, 'Settlement math does not start from cash product price.');
$assert(strpos($read('helpers/MerchantSettlementCalculator.php'), 'total_customer_payable') === false, 'Installment interest is mixed into seller receivable.');
$assert(strpos($read('tools/release-gate.php'), "'tests/merchant_settlement_commission_phase4.php'") !== false, 'Release gate omits Phase 4 unit test.');
$assert(strpos($read('tools/release-gate.php'), "'tests/static_merchant_settlement_commission_phase4.php'") !== false, 'Release gate omits Phase 4 static test.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "STATIC_MERCHANT_SETTLEMENT_COMMISSION_PHASE4_OK\n";
