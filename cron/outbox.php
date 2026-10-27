<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../bootstrap.php';

$limit = (int) (getenv('PROMA_OUTBOX_WORKER_LIMIT') ?: 50);
$limit = max(1, min(100, $limit));
$result = SystemOutbox::processPending($limit);
$result['ok'] = (int) ($result['failed'] ?? 0) === 0
    && !in_array($result['skipped'] ?? '', ['worker_error', 'not_worker_context', 'transaction_open'], true);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if (!$result['ok']) {
    exit(1);
}
