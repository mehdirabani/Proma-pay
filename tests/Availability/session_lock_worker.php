<?php

declare(strict_types=1);

if ($argc < 4) {
    exit(2);
}
session_save_path($argv[1]);
session_id($argv[2]);
$_GET['route'] = 'health/live';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require dirname(__DIR__, 2) . '/bootstrap.php';
Auth::start();
Auth::releaseSessionLock();
$readyPath = $argv[1] . '/ready-' . preg_replace('/[^0-9]/', '', $argv[3]);
file_put_contents($readyPath, (string) microtime(true), LOCK_EX);
$deadline = microtime(true) + 5;
while (!is_file($argv[1] . '/go')) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Barrier timed out.\n");
        exit(3);
    }
    usleep(10000);
}
$workStartedAt = microtime(true);
usleep(500000);
echo json_encode([
    'work_started_at' => $workStartedAt,
    'finished_at' => microtime(true),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
