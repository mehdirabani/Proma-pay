<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', $dsn)
    || trim((string) getenv('PROMA_TEST_ALLOW_DESTRUCTIVE')) !== '1') {
    fwrite(STDERR, "This integration test is restricted to the authorized disposable proma_qa_v204 database.\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/core/Model.php';
require_once $root . '/helpers/SchemaGuard.php';
require_once $root . '/helpers/MerchantMembershipPolicy.php';
require_once $root . '/models/MerchantDashboardRepository.php';
$pdo = new PDO(
    $dsn,
    trim((string) (getenv('PROMA_TEST_DB_USER') ?: 'root')),
    (string) getenv('PROMA_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$reflection = new ReflectionProperty(Model::class, 'pdo');
$reflection->setAccessible(true);
$reflection->setValue(null, $pdo);
SchemaGuard::requireTables([
    'users','merchant_accounts','merchant_memberships','merchant_api_order_requests',
    'merchant_contract_links','contracts','merchant_cheque_guarantees','merchant_settlement_obligations',
    'merchant_financial_reconciliations','merchant_api_webhook_outbox','merchant_audit_events',
]);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$expectForbidden = static function (callable $action): void {
    try {
        $action();
    } catch (DomainException $error) {
        if ($error->getMessage() === 'merchant_dashboard_forbidden') return;
        throw $error;
    }
    throw new RuntimeException('Expected an out-of-tenant merchant dashboard request to be forbidden.');
};

$pdo->beginTransaction();
try {
    $suffix = strtolower(bin2hex(random_bytes(6)));
    Model::execute(
        'INSERT INTO users (role,username,full_name,password_hash,created_at) VALUES (?,?,?,?,NOW())',
        ['admin','qa-merchant-dashboard-' . $suffix,'Merchant dashboard QA',password_hash(random_bytes(20), PASSWORD_DEFAULT)]
    );
    $ownerId = (int) Model::lastInsertId();
    Model::execute(
        'INSERT INTO users (role,username,full_name,password_hash,created_at) VALUES (?,?,?,?,NOW())',
        ['operator','qa-merchant-dashboard-viewer-' . $suffix,'Merchant dashboard viewer',password_hash(random_bytes(20), PASSWORD_DEFAULT)]
    );
    $viewerId = (int) Model::lastInsertId();
    Model::execute(
        "INSERT INTO merchant_accounts (merchant_uuid,merchant_code,display_name,status,created_by)
         VALUES (?,?,?,'active',?)",
        [sprintf('%08x-%04x-4%03x-a%03x-%012x', random_int(0,0xffffffff), random_int(0,0xffff), random_int(0,0xfff), random_int(0,0xfff), random_int(0,0xffffffffffff)), 'dash-' . $suffix, 'Dashboard QA ' . $suffix, $ownerId]
    );
    $merchantA = (int) Model::lastInsertId();
    Model::execute(
        "INSERT INTO merchant_accounts (merchant_uuid,merchant_code,display_name,status,created_by)
         VALUES (?,?,?,'active',?)",
        [sprintf('%08x-%04x-4%03x-a%03x-%012x', random_int(0,0xffffffff), random_int(0,0xffff), random_int(0,0xfff), random_int(0,0xfff), random_int(0,0xffffffffffff)), 'dash-other-' . $suffix, 'Other Dashboard QA ' . $suffix, $ownerId]
    );
    $merchantB = (int) Model::lastInsertId();
    Model::execute(
        "INSERT INTO merchant_memberships (merchant_id,user_id,membership_role,status,granted_by)
         VALUES (?,?,?,'active',?)",
        [$merchantA,$ownerId,'owner',$ownerId]
    );
    Model::execute(
        "INSERT INTO merchant_memberships (merchant_id,user_id,membership_role,status,granted_by)
         VALUES (?,?,?,'active',?)",
        [$merchantA,$viewerId,'operator',$ownerId]
    );

    $ownerMerchants = MerchantDashboardRepository::membershipsForUser($ownerId);
    $assert(count($ownerMerchants) === 1 && (int) $ownerMerchants[0]['merchant_id'] === $merchantA,
        'Merchant list leaked an unassigned merchant.');
    $ownerDashboard = MerchantDashboardRepository::dashboardForUser($ownerId, $merchantA);
    $assert((int) $ownerDashboard['orders']['total'] === 0
        && (int) $ownerDashboard['active_contracts'] === 0
        && $ownerDashboard['can_settlement'] === true,
        'Owner dashboard did not return correctly scoped empty metrics.');
    $viewerDashboard = MerchantDashboardRepository::dashboardForUser($viewerId, $merchantA);
    $assert($viewerDashboard['can_settlement'] === false
        && (int) $viewerDashboard['active_contracts'] === 0,
        'Read-only merchant role received settlement permission.');
    $expectForbidden(static fn() => MerchantDashboardRepository::dashboardForUser($ownerId, $merchantB));
    $expectForbidden(static fn() => MerchantDashboardRepository::dashboardForUser($viewerId, $merchantB));

    echo "MERCHANT_DASHBOARD_PHASE9_INTEGRATION_OK\n";
} finally {
    $pdo->rollBack();
}
