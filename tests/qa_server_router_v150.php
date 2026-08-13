<?php

if (!getenv('PROMA_DB_DSN')) putenv('PROMA_DB_DSN=mysql:host=127.0.0.1;port=33311;dbname=proma_v150_qa;charset=utf8mb4');
if (!getenv('PROMA_DB_USER')) putenv('PROMA_DB_USER=root');
if (getenv('PROMA_DB_PASSWORD') === false) putenv('PROMA_DB_PASSWORD=');
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
