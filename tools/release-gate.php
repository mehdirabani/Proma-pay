<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tests = [
    'tests/static_v126.php',
    'tests/static_v129.php',
    'tests/static_v130.php',
    'tests/static_v131.php',
    'tests/static_v132.php',
    'tests/static_v133.php',
    'tests/static_v134.php',
    'tests/static_v135.php',
    'tests/financial_precision_v136.php',
    'tests/error_response_v136.php',
    'tests/static_v136.php',
];

$failed = [];
foreach ($tests as $test) {
    $path = $root . '/' . $test;
    if (!is_file($path)) {
        $failed[] = $test . ' (missing)';
        continue;
    }
    echo "[RUN] {$test}\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        $failed[] = $test;
    }
}

if ($failed) {
    fwrite(STDERR, "RELEASE_GATE_FAILED\n" . implode("\n", $failed) . "\n");
    exit(1);
}

echo "RELEASE_GATE_STATIC_OK\n";
