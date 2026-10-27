<?php

if (PHP_SAPI !== 'cli-server') {
    echo "QA_SERVER_ROUTER_ONLY\n";
    return;
}

$testDsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($testDsn === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PROMA_TEST_DB_DSN is required for the QA server.';
    exit;
}
putenv('PROMA_DB_DSN=' . $testDsn);
putenv('PROMA_DB_USER=' . (getenv('PROMA_TEST_DB_USER') ?: 'root'));
putenv('PROMA_DB_PASSWORD=' . (getenv('PROMA_TEST_DB_PASSWORD') ?: ''));
// The real guard is tested separately.  HTTP smoke tests are intentionally
// bursty and run through one local loopback address, unlike real clients.
putenv('PROMA_REQUEST_RATE_CAPACITY=1000');
putenv('PROMA_REQUEST_RATE_REFILL_PER_SECOND=1000');

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$candidate = realpath($root . $path);
if ($path !== '/index.php' && $candidate && strpos($candidate, $root) === 0 && is_file($candidate)) {
    return false;
}
require $root . '/index.php';
