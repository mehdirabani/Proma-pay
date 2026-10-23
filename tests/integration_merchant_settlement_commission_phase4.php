<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', $dsn)) {
    fwrite(STDERR, "This integration test is restricted to the disposable proma_qa_v204 database.\n");
    exit(2);
}

$root = dirname(__DIR__);
foreach ([
    'helpers/functions.php', 'helpers/MoneyMath.php', 'core/Model.php', 'helpers/SchemaGuard.php',
    'helpers/MerchantApiCredentialToken.php', 'helpers/MerchantAccessContext.php',
    'helpers/MerchantAuditMetadata.php', 'models/MerchantAuditLog.php',
    'helpers/MerchantSettlementCalculator.php', 'helpers/MerchantSettlementPolicy.php',
    'helpers/MerchantCommissionPolicy.php', 'helpers/MerchantSettlementCalendar.php',
    'models/MerchantLiquidityRepository.php', 'models/MerchantSettlementRepository.php',
    'models/MerchantCommissionRepository.php',
] as $file) {
    require $root . '/' . $file;
}
class Auth
{
    public static $actorId;
    public static function id() { return self::$actorId; }
}
class PluginManager
{
    public static function requirePermission(string $permission): void
    {
        if ($permission === '') throw new RuntimeException('Empty permission name.');
    }
}

$pdo = new PDO(
    $dsn,
    trim((string) (getenv('PROMA_TEST_DB_USER') ?: 'root')),
    (string) getenv('PROMA_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec("SET time_zone = '+03:30'");
$reflection = new ReflectionProperty(Model::class, 'pdo');
$reflection->setAccessible(true);
$reflection->setValue(null, $pdo);
SchemaGuard::requireTables([
    'merchant_financial_control_locks', 'merchant_liquidity_accounts', 'merchant_liquidity_events',
    'merchant_settlement_obligations', 'merchant_settlement_adjustments', 'merchant_settlement_batches',
    'merchant_settlement_lines', 'merchant_settlement_events', 'merchant_commission_entries',
    'merchant_reward_entries', 'merchant_financial_reconciliations',
]);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$suffix = strtoupper(bin2hex(random_bytes(5)));
Model::execute(
    'INSERT INTO users (role,username,full_name,password_hash,created_at) VALUES (?,?,?,?,NOW())',
    ['admin', 'qa-phase4-admin-' . strtolower($suffix), 'Phase 4 QA administrator', password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]
);
$adminId = (int) Model::lastInsertId();
Model::execute(
    'INSERT INTO users (role,username,full_name,password_hash,created_at) VALUES (?,?,?,?,NOW())',
    ['customer', 'qa-phase4-customer-' . strtolower($suffix), 'Phase 4 QA customer', password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]
);
$customerId = (int) Model::lastInsertId();
Auth::$actorId = $adminId;

Model::execute(
    "INSERT INTO merchant_accounts (merchant_uuid,merchant_code,display_name,status,created_by) VALUES (?,?,?,'active',?)",
    [MerchantAuditLog::newUuid(), 'qa-' . strtolower($suffix), 'فروشگاه آزمایشی ' . $suffix, $adminId]
);
$merchantId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_sites (merchant_id,site_uuid,site_code,display_name,site_origin,verification_status,status,verified_at,created_by)
     VALUES (?,?,?,?,?,'verified','active',NOW(),?)",
    [$merchantId, MerchantAuditLog::newUuid(), 'site-' . strtolower($suffix), 'سایت آزمایشی', 'https://qa-' . strtolower($suffix) . '.example.test', $adminId]
);
$siteId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO merchant_connections (merchant_id,site_id,connection_uuid,connection_code,display_name,status,created_by,activated_by,activated_at)
     VALUES (?,?,?,?,?,'active',?,?,NOW())",
    [$merchantId, $siteId, MerchantAuditLog::newUuid(), 'conn-' . strtolower($suffix), 'اتصال آزمایشی', $adminId, $adminId]
);
$connectionId = (int) Model::lastInsertId();
$context = new MerchantAccessContext($merchantId, $siteId, $connectionId, [
    'settlement:write', 'settlement:review', 'settlement:batch:create', 'settlement:payout:initiate',
    'settlement:payout:confirm', 'settlement:payout:fail', 'settlement:reconcile', 'settlement:read',
    'commission:record', 'commission:review', 'commission:reverse', 'reward:record',
]);

$account = MerchantLiquidityRepository::createAccount(
    'qa.' . strtolower($suffix), 'حساب آزمایشی فاز چهار', 'merchant',
    $merchantId, $siteId, $connectionId, 'QA-ACCOUNT-' . $suffix, $adminId
);
$evidenceHash = hash('sha256', 'phase4-' . $suffix);
$assert(MerchantLiquidityRepository::activateAccount(
    $account['account_uuid'], 'QA-ACCOUNT-VERIFICATION-' . $suffix, $evidenceHash, $adminId
), 'Liquidity account did not activate.');
$funding = MerchantLiquidityRepository::recordVerifiedFunding(
    $account['account_uuid'], 'funding.' . strtolower($suffix), 'QA-BANK-FUND-' . $suffix,
    2000000, 'qa:funding:' . strtolower($suffix), $evidenceHash, $adminId
);
$assert(!$funding['idempotent'], 'Initial funding unexpectedly existed.');
$assert(MerchantLiquidityRepository::recordVerifiedFunding(
    $account['account_uuid'], 'funding.' . strtolower($suffix), 'QA-BANK-FUND-' . $suffix,
    2000000, 'qa:funding:' . strtolower($suffix), $evidenceHash, $adminId
)['idempotent'], 'Funding retry was not idempotent.');

$publishPolicy = static function (string $type, array $document) use ($connectionId, $adminId): int {
    $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    Model::execute(
        "INSERT INTO merchant_policy_versions
            (connection_id,policy_version_uuid,policy_type,version_no,policy_json,content_sha256,status,published_by,published_at,created_by)
         VALUES (?,?,?,?,?,?,'published',?,NOW(),?)",
        [$connectionId, MerchantAuditLog::newUuid(), $type, 1, $json, hash('sha256', $json), $adminId, $adminId]
    );
    $id = (int) Model::lastInsertId();
    Model::execute(
        'INSERT INTO merchant_policy_heads (connection_id,policy_type,policy_version_id,selected_by) VALUES (?,?,?,?)',
        [$connectionId, $type, $id, $adminId]
    );
    return $id;
};
$publishPolicy('settlement', [
    'enabled' => true, 'currency_unit' => 'toman',
    'settlement_basis' => 'cash_product_price_less_verified_down_payment',
    'receivable_ownership_model' => 'merchant', 'funding_account_uuid' => $account['account_uuid'],
    'legal_contract_reference' => 'QA-LEGAL-CONTRACT-' . $suffix,
    'legal_approval_reference' => 'QA-LEGAL-APPROVAL-' . $suffix,
    'minimum_liquidity_reserve_toman' => 100000,
    'max_single_settlement_toman' => 1000000,
    'max_merchant_exposure_toman' => 10000000,
    'max_platform_exposure_toman' => 10000000,
    'allowed_deduction_codes' => ['delivery_fee'],
    'schedule' => ['type' => 'manual'],
]);
$guaranteePolicyId = $publishPolicy('guarantee', ['enabled' => true]);
$publishPolicy('commission', [
    'enabled' => true,
    'commission_rules' => [[
        'rule_code' => 'cash-sale', 'type' => 'cash_price_percent', 'rate_bps' => 100,
        'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
        'recognition_event' => 'contract_activated', 'payable_rule' => 'after_refund_window',
        'refund_window_days' => 2, 'reversal_policy' => 'proportional',
    ], [
        'rule_code' => 'installment-collected', 'type' => 'installments_collected_percent', 'rate_bps' => 500,
        'payer' => 'platform', 'payee_type' => 'merchant', 'payee_user_id' => null,
        'recognition_event' => 'installment_collected', 'payable_rule' => 'after_refund_window',
        'refund_window_days' => 2, 'reversal_policy' => 'proportional',
    ]],
    'reward_rules' => [],
]);
Model::execute(
    "INSERT INTO merchant_central_guarantee_policy_versions
        (policy_uuid,version_no,status,enabled,minimum_multiplier_units,maximum_multiplier_units,default_multiplier_units,
         beneficiary_legal_name,beneficiary_identifier,legal_approval_reference,created_by,published_by,published_at)
     VALUES (?,?,'published',1,10000,25000,20000,'Proma QA','1111111111',?,?,?,NOW())",
    [MerchantAuditLog::newUuid(), random_int(10000, 4000000000), 'QA-CENTRAL-GUARANTEE-' . $suffix, $adminId, $adminId]
);
$centralGuaranteePolicyId = (int) Model::lastInsertId();

$createOrder = static function (string $orderSuffix, int $cashPrice, int $downPayment) use (
    $merchantId, $siteId, $connectionId, $customerId, $adminId, $guaranteePolicyId, $centralGuaranteePolicyId
): array {
    $serial = random_int(100000, 999999999);
    $contractNumber = 'QA-' . $orderSuffix . '-' . $serial;
    $firstDue = '2026-12-05';
    Model::execute(
        "INSERT INTO contracts
            (customer_id,contract_number,prefix,serial,principal_amount,down_payment_amount,monthly_interest_rate,
             interest_type,months,start_date,first_due_date,status,created_at)
         VALUES (?,?,?,?,?,?,0,'simple',1,CURDATE(),?,'active',NOW())",
        [$customerId, $contractNumber, 'QA-' . $orderSuffix, $serial, $cashPrice - $downPayment, $downPayment, $firstDue]
    );
    $contractId = (int) Model::lastInsertId();
    Model::execute(
        "INSERT INTO merchant_contract_links
            (merchant_id,site_id,connection_id,contract_id,external_order_id,external_order_uuid,order_status,created_by)
         VALUES (?,?,?,?,?,?,'contracted',?)",
        [$merchantId, $siteId, $connectionId, $contractId, 'WC-' . $orderSuffix, MerchantAuditLog::newUuid(), $adminId]
    );
    $linkId = (int) Model::lastInsertId();
    Model::execute(
        "INSERT INTO merchant_cheque_guarantees
            (guarantee_uuid,merchant_id,site_id,connection_id,contract_link_id,contract_id,customer_id,
             policy_version_id,central_policy_version_id,guarantee_base,scheduled_installments_toman,
             multiplier_units,cheque_amount_rial,first_installment_due_date,cheque_due_date,
             beneficiary_legal_name,beneficiary_identifier,sayad_status,registration_method,
             registration_reference,registration_evidence_sha256,registered_at,confirmation_method,
             confirmation_reference,confirmation_evidence_sha256,confirmed_by,confirmed_at,created_by)
         VALUES (?,?,?,?,?,?,?,?,?,'confirmed_schedule',1000000,20000,20000000,?,?,\n                 'Proma QA','1111111111','confirmed','manual_documented',?,?,?,?,?,?,?, ?,?)",
        [
            MerchantAuditLog::newUuid(), $merchantId, $siteId, $connectionId, $linkId, $contractId, $customerId,
            $guaranteePolicyId, $centralGuaranteePolicyId, $firstDue, $firstDue,
            'QA-CHEQUE-REGISTER-' . $orderSuffix, hash('sha256', 'register-' . $orderSuffix),
            '2026-10-23 00:00:00', 'manual_documented', 'QA-CHEQUE-CONFIRM-' . $orderSuffix,
            hash('sha256', 'confirm-' . $orderSuffix), $adminId, '2026-10-23 00:00:00', $adminId,
        ]
    );
    return ['contract_id' => $contractId, 'link_id' => $linkId];
};

$first = $createOrder($suffix . 'A', 1000000, 250000);
$obligation = MerchantSettlementRepository::createPendingObligation(
    $context, $first['link_id'], 1000000, 250000, 'QA-CASH-PRICE-' . $suffix,
    hash('sha256', 'cash-price-' . $suffix), $adminId
);
$assert(!$obligation['idempotent'], 'New settlement obligation unexpectedly existed.');
$assert(MerchantSettlementRepository::createPendingObligation(
    $context, $first['link_id'], 1000000, 250000, 'QA-CASH-PRICE-' . $suffix,
    hash('sha256', 'cash-price-' . $suffix), $adminId
)['idempotent'], 'Obligation retry was not idempotent.');
$assert(MerchantSettlementRepository::verifyDownPayment(
    $context, $obligation['obligation_uuid'], 250000, 'QA-DOWNPAYMENT-' . $suffix,
    hash('sha256', 'downpayment-' . $suffix), $adminId
), 'Down payment evidence did not verify.');
$adjustmentUuid = MerchantAuditLog::newUuid();
$adjustment = MerchantSettlementRepository::requestAdjustment(
    $context, $adjustmentUuid, $obligation['obligation_uuid'], 'approved_credit', 50000, null,
    'QA-LEGAL-CONTRACT-' . $suffix, 'QA-CREDIT-ADJUSTMENT-' . $suffix,
    hash('sha256', 'credit-adjustment-' . $suffix), null
);
$assert($adjustment['status'] === 'pending', 'Adjustment did not wait for review.');
$assert(MerchantSettlementRepository::reviewAdjustment(
    $context, $adjustmentUuid, true, 'QA-CREDIT-REVIEW-' . $suffix,
    hash('sha256', 'credit-review-' . $suffix), $adminId
) === 'approved', 'Adjustment review did not approve.');

$manualDate = (new DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
$reviewOrder = static function (array $order, string $orderSuffix, string $due) use ($context, $adminId): array {
    return MerchantSettlementRepository::reviewEligibility($context, $order['obligation_uuid'], [
        'contract_approval_reference' => 'QA-CONTRACT-APPROVAL-' . $orderSuffix,
        'documents_evidence_reference' => 'QA-DOCUMENTS-' . $orderSuffix,
        'documents_evidence_sha256' => hash('sha256', 'documents-' . $orderSuffix),
        'delivery_evidence_reference' => 'QA-DELIVERY-' . $orderSuffix,
        'delivery_evidence_sha256' => hash('sha256', 'delivery-' . $orderSuffix),
        'risk_review_reference' => 'QA-RISK-' . $orderSuffix,
        'manual_due_date' => $due,
    ], $adminId);
};
$eligibility = $reviewOrder($obligation, $suffix . 'A', $manualDate);
$assert($eligibility['status'] === 'eligible' && $eligibility['settlement_due_date'] === $manualDate, 'Verified order did not become eligible.');
$batch = MerchantSettlementRepository::createDueBatch($context, $manualDate, $adminId);
$assert($batch['status'] === 'scheduled' && $batch['net_settlement_toman'] === 800000, 'Settlement amount is incorrect.');
$assert(MerchantSettlementRepository::createDueBatch($context, $manualDate, $adminId)['idempotent'], 'Batch retry was not idempotent.');
$transferEvidence = hash('sha256', 'transfer-init-' . $suffix);
$assert(MerchantSettlementRepository::markProcessing(
    $context, $batch['batch_uuid'], 'QA-TRANSFER-' . $suffix, 'QA-TRANSFER-EVIDENCE-' . $suffix,
    $transferEvidence, $adminId
), 'Payout did not enter processing.');
$assert(MerchantSettlementRepository::markProcessing(
    $context, $batch['batch_uuid'], 'QA-TRANSFER-' . $suffix, 'QA-TRANSFER-EVIDENCE-' . $suffix,
    $transferEvidence, $adminId
), 'Payout initiation retry failed.');
$bankReference = 'QA-BANK-PAID-' . $suffix;
$paidEvidence = hash('sha256', 'paid-evidence-' . $suffix);
$assert(MerchantSettlementRepository::confirmPaid(
    $context, $batch['batch_uuid'], $bankReference, 'QA-BANK-EVIDENCE-' . $suffix,
    $paidEvidence, $adminId
), 'Bank-evidenced payout did not complete.');
$assert(MerchantSettlementRepository::confirmPaid(
    $context, $batch['batch_uuid'], $bankReference, 'QA-BANK-EVIDENCE-' . $suffix,
    $paidEvidence, $adminId
), 'Paid batch retry failed.');
$reconcileHash = hash('sha256', 'statement-' . $suffix);
$reconciliation = MerchantSettlementRepository::reconcileBankStatement(
    $context, $batch['batch_uuid'], 'QA-STATEMENT-' . $suffix, $bankReference, 800000,
    $reconcileHash, $adminId
);
$assert($reconciliation['status'] === 'matched', 'Matching bank statement was not reconciled.');
$assert(MerchantSettlementRepository::reconcileBankStatement(
    $context, $batch['batch_uuid'], 'QA-STATEMENT-' . $suffix, $bankReference, 800000,
    $reconcileHash, $adminId
)['idempotent'], 'Reconciliation retry failed.');

$second = $createOrder($suffix . 'B', 500000, 100000);
$secondObligation = MerchantSettlementRepository::createPendingObligation(
    $context, $second['link_id'], 500000, 100000, 'QA-CASH-PRICE-2-' . $suffix,
    hash('sha256', 'cash-price-2-' . $suffix), $adminId
);
MerchantSettlementRepository::verifyDownPayment(
    $context, $secondObligation['obligation_uuid'], 100000, 'QA-DOWNPAYMENT-2-' . $suffix,
    hash('sha256', 'downpayment-2-' . $suffix), $adminId
);
$secondDate = (new DateTimeImmutable('today'))->modify('+4 days')->format('Y-m-d');
$assert($reviewOrder($secondObligation, $suffix . 'B', $secondDate)['status'] === 'eligible', 'Second order did not become eligible.');
$failedBatch = MerchantSettlementRepository::createDueBatch($context, $secondDate, $adminId);
MerchantSettlementRepository::markProcessing(
    $context, $failedBatch['batch_uuid'], 'QA-TRANSFER-2-' . $suffix, 'QA-TRANSFER-EVIDENCE-2-' . $suffix,
    hash('sha256', 'transfer-2-' . $suffix), $adminId
);
$failureEvidence = hash('sha256', 'failure-' . $suffix);
$assert(MerchantSettlementRepository::failPayoutAndRelease(
    $context, $failedBatch['batch_uuid'], 'QA-BANK-FAIL-' . $suffix,
    'QA-BANK-FAIL-EVIDENCE-' . $suffix, $failureEvidence, $adminId
), 'Verified payout failure did not release the reserve.');
$assert(MerchantSettlementRepository::failPayoutAndRelease(
    $context, $failedBatch['batch_uuid'], 'QA-BANK-FAIL-' . $suffix,
    'QA-BANK-FAIL-EVIDENCE-' . $suffix, $failureEvidence, $adminId
), 'Payout failure retry failed.');
$balances = Model::fetch(
    'SELECT cash_balance_toman,available_balance_toman,reserved_balance_toman
     FROM merchant_liquidity_accounts WHERE account_uuid = ?',
    [$account['account_uuid']]
);
$assert((int) $balances['cash_balance_toman'] === 1200000
    && (int) $balances['available_balance_toman'] === 1200000
    && (int) $balances['reserved_balance_toman'] === 0, 'Cash and reserve balances did not reconcile.');

$commission = MerchantCommissionRepository::recordEvent(
    $context, $first['link_id'], 'contract_activated', (string) $first['contract_id']
);
$assert(count($commission['entries']) === 1 && $commission['entries'][0]['amount_toman'] === 10000,
    'Cash-price commission amount is incorrect.');
$assert(MerchantCommissionRepository::recordEvent(
    $context, $first['link_id'], 'contract_activated', (string) $first['contract_id']
)['idempotent'], 'Commission event retry failed.');
$installmentDue = date('Y-m-d', strtotime('+30 days'));
Model::execute(
    "INSERT INTO installments (contract_id,installment_number,due_date,base_amount,remaining_amount,created_at)
     VALUES (?,1,?,250000,0,NOW())",
    [$first['contract_id'], $installmentDue]
);
$installmentId = (int) Model::lastInsertId();
Model::execute(
    "INSERT INTO payments (installment_id,contract_id,user_id,amount,method,status,payment_type,paid_at,created_at)
     VALUES (?,?,?,250000,'manual','paid','installment',NOW(),NOW())",
    [$installmentId, $first['contract_id'], $customerId]
);
$installmentPaymentId = (int) Model::lastInsertId();
$installmentCommission = MerchantCommissionRepository::recordEvent(
    $context, $first['link_id'], 'installment_collected', (string) $installmentPaymentId
);
$assert(count($installmentCommission['entries']) === 1
    && $installmentCommission['entries'][0]['amount_toman'] === 12500,
    'Collected-installment commission amount is incorrect.');
$assert(MerchantCommissionRepository::recordEvent(
    $context, $first['link_id'], 'installment_collected', (string) $installmentPaymentId
)['idempotent'], 'Collected-installment commission retry failed.');
$commissionUuid = $commission['entries'][0]['entry_uuid'];
$assert(MerchantCommissionRepository::reverseUnpaidEntry(
    $context, 'commission', $commissionUuid, 'QA test reversal before payout',
    'QA-COMMISSION-REVERSAL-' . $suffix, hash('sha256', 'commission-reversal-' . $suffix), $adminId
), 'Unpaid commission reversal failed.');

echo "INTEGRATION_MERCHANT_SETTLEMENT_COMMISSION_PHASE4_OK\n";
