<?php

declare(strict_types=1);

// Uses only a disposable QA database. It writes and removes its own rows.
$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/', $dsn)
    || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Use the disposable proma_qa_v204 database with PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.220';
require dirname(__DIR__) . '/bootstrap.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$user = getenv('PROMA_TEST_DB_USER') ?: 'root';
$password = getenv('PROMA_TEST_DB_PASSWORD') ?: '';
$worker = new PDO($dsn, $user, $password, $options);
$competitor = new PDO($dsn, $user, $password, $options);
$property = new ReflectionProperty(Model::class, 'pdo');

$property->setValue(null, $worker);

$recipient = $worker->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();
if (!$recipient) {
    throw new RuntimeException('Disposable QA database needs a user fixture.');
}
$aggregateId = random_int(100000, 999999);
$eventId = SystemOutbox::enqueue('notification.create', [
    'user_id' => (int) $recipient['id'],
    'title' => 'QA outbox delivery',
    'body' => 'Retry and concurrency proof',
    'type' => 'system',
], 'qa_outbox', $aggregateId);
$dedupeKey = 'outbox:' . $eventId;
$database = (string) $worker->query('SELECT DATABASE()')->fetchColumn();
$lockName = 'proma_outbox_' . substr(hash('sha256', $database), 0, 32);
$lock = $competitor->prepare('SELECT GET_LOCK(?, 0)');
$unlock = $competitor->prepare('SELECT RELEASE_LOCK(?)');
$medalEventId = null;

try {
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire competing worker lock.');
    }
    try {
        $busy = SystemOutbox::processPending(1, 'qa_outbox', $aggregateId);
        if (($busy['skipped'] ?? '') !== 'worker_busy') {
            throw new RuntimeException('Second worker did not defer while the first held the lock.');
        }
    } finally {
        $unlock->execute([$lockName]);
    }

    $result = SystemOutbox::processPending(1, 'qa_outbox', $aggregateId);
    if (($result['processed'] ?? 0) < 1) {
        throw new RuntimeException('Notification event was not delivered.');
    }
    $count = $worker->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND dedupe_key = ?');
    $count->execute([(int) $recipient['id'], $dedupeKey]);
    if ((int) $count->fetchColumn() !== 1) {
        throw new RuntimeException('Notification was not created exactly once.');
    }

    // Simulate a crash after delivery but before the outbox status was saved.
    $reset = $worker->prepare("UPDATE system_outbox SET status = 'failed', processed_at = NULL, next_attempt_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?");
    $reset->execute([$eventId]);
    $retry = SystemOutbox::processPending(1, 'qa_outbox', $aggregateId);
    $count->execute([(int) $recipient['id'], $dedupeKey]);
    if (($retry['processed'] ?? 0) < 1 || (int) $count->fetchColumn() !== 1) {
        throw new RuntimeException('Retry created a duplicate notification or failed.');
    }
    $customer = $worker->query("SELECT id FROM users WHERE role = 'customer' ORDER BY id ASC LIMIT 1")->fetch();
    if (!$customer) {
        throw new RuntimeException('Disposable QA database needs a customer fixture.');
    }
    $medalEventId = SystemOutbox::safeEnqueueMedalEvaluation((int) $customer['id']);
    if (!$medalEventId) {
        throw new RuntimeException('Medal evaluation was not queued.');
    }
    SystemOutbox::processPending(100, 'customer', (int) $customer['id']);
    $medalStatus = $worker->prepare('SELECT status FROM system_outbox WHERE id = ?');
    $medalStatus->execute([$medalEventId]);
    if ($medalStatus->fetchColumn() !== 'processed') {
        throw new RuntimeException('Queued medal evaluation was not processed.');
    }
    echo "INTEGRATION_OUTBOX_DELIVERY_OK\n";
} finally {
    $unlock->execute([$lockName]);
    $deleteNotification = $worker->prepare('DELETE FROM notifications WHERE user_id = ? AND dedupe_key = ?');
    $deleteNotification->execute([(int) $recipient['id'], $dedupeKey]);
    $deleteEvent = $worker->prepare('DELETE FROM system_outbox WHERE id = ?');
    $deleteEvent->execute([$eventId]);
    if ($medalEventId) {
        $deleteEvent->execute([$medalEventId]);
    }
}
