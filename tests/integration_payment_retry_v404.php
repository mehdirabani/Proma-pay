<?php
declare(strict_types=1);
if (!preg_match('/(?:^|;)dbname=(?:proma_qa_v404_20261008|proma_qa_backup_roundtrip_v405_20261008)(?:;|$)/i', getenv('PROMA_TEST_DB_DSN') ?: '')) {
    fwrite(STDERR, "Use the owned disposable v404 QA database only.\n"); exit(2);
}
require __DIR__ . '/integration_payment_overflow_allocation_v204.php';
[$retryContract, $retryRows] = $seedSchedule();
$retryKey = 'qa-retry-v404-' . $suffix;
$counts = static function () use ($retryContract) {
    return [Model::fetch('SELECT COUNT(*) AS total FROM payment_groups WHERE contract_id = ?', [$retryContract])['total'], Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id = ?', [$retryContract])['total']];
};
$before = $counts();
$failed = false;
Model::db()->exec("CREATE TRIGGER qa_v404_allocation_failure BEFORE INSERT ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'QA allocation failure'");
try {
    PaymentGroupService::create($retryContract, [(int) $retryRows[0]['id']], 250000, $adminId, 'manual', '', false, $retryKey, null, '2026-10-01', '10:00', 'schedule');
} catch (PDOException $e) { $failed = true; }
finally { Model::db()->exec('DROP TRIGGER qa_v404_allocation_failure'); }
$assert($failed && $before === $counts(), 'Failed allocation committed a payment or group');
$request = Model::fetch('SELECT status FROM payment_requests WHERE request_uuid = ?', [$retryKey]);
$assert(($request['status'] ?? '') === 'failed', 'Failed request was not recorded');
$retry = PaymentGroupService::create($retryContract, [(int) $retryRows[0]['id']], 250000, $adminId, 'manual', '', false, $retryKey, null, '2026-10-01', '10:00', 'schedule');
$repeat = PaymentGroupService::create($retryContract, [(int) $retryRows[0]['id']], 250000, $adminId, 'manual', '', false, $retryKey, null, '2026-10-01', '10:00', 'schedule');
$assert((int) $retry['id'] === (int) $repeat['id'] && $counts() === [1,1], 'Retry did not create exactly one payment');
$sum = Model::fetch('SELECT SUM(allocated_amount) AS total FROM payment_allocations WHERE payment_group_id = ?', [(int) $retry['id']]);
$assert(normalize_money($sum['total']) === 250000, 'Partial payment allocation total changed');
foreach (['actor', 'amount', 'time'] as $variation) {
    $rejected = false;
    try {
        PaymentGroupService::create($retryContract, [(int) $retryRows[0]['id']], $variation === 'amount' ? 250001 : 250000, $variation === 'actor' ? $customerId : $adminId, 'manual', '', false, $retryKey, null, '2026-10-01', $variation === 'time' ? '11:00' : '10:00', 'schedule');
    } catch (InvalidArgumentException $e) { $rejected = true; }
    $assert($rejected, 'Changed ' . $variation . ' reused a completed request');
}
echo "INTEGRATION_PAYMENT_RETRY_ROLLBACK_ACTOR_TIME_V404_OK\n";
[$uiContract, $uiRows] = $seedSchedule();
Model::execute('UPDATE contracts SET contract_number = ? WHERE id = ?', ['QA-V404-UI', $uiContract]);
$fixtureApp = str_contains(getenv('PROMA_TEST_DB_DSN'), 'proma_qa_backup_roundtrip_v405_20261008') ? 'C:/laragon/www/proma-qa-backup-roundtrip-v405-20261008' : 'C:/laragon/www/proma-qa-v404-20261008';
file_put_contents($fixtureApp . '/storage/qa-v404-fixture.json', json_encode(['contract_id' => $uiContract, 'installment_id' => (int) $uiRows[0]['id']]));
echo 'UI_FIXTURE_INSTALLMENT_ID=' . (int) $uiRows[0]['id'] . "\n";
