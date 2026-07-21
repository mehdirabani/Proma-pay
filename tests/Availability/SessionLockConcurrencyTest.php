<?php

declare(strict_types=1);

if (!function_exists('proc_open')) {
    fwrite(STDERR, "proc_open is required.\n");
    exit(2);
}
$directory = sys_get_temp_dir() . '/proma-session-lock-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create session test directory.');
}
$sessionId = 'promaqa' . bin2hex(random_bytes(8));
session_save_path($directory);
session_id($sessionId);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
session_write_close();

$worker = __DIR__ . '/session_lock_worker.php';
$processes = [];
for ($index = 0; $index < 2; $index++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, $worker, $directory, $sessionId, (string) $index], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start session lock worker.');
    }
    $processes[] = [$process, $pipes];
}
$barrierDeadline = microtime(true) + 3;
while (count(glob($directory . '/ready-*') ?: []) < 2 && microtime(true) < $barrierDeadline) {
    usleep(10000);
}
$readyWorkers = count(glob($directory . '/ready-*') ?: []);
file_put_contents($directory . '/go', 'go', LOCK_EX);
$outputs = [];
foreach ($processes as [$process, $pipes]) {
    $outputs[] = trim((string) stream_get_contents($pipes[1]));
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Session worker failed: ' . trim($error));
    }
}

if ($readyWorkers !== 2) {
    throw new RuntimeException('Session lock prevented both workers from reaching the post-release barrier.');
}
$timings = array_map(static function (string $output): array {
    $decoded = json_decode($output, true);
    if (!is_array($decoded) || !isset($decoded['work_started_at'], $decoded['finished_at'])) {
        throw new RuntimeException('Session worker returned invalid timing output.');
    }
    return $decoded;
}, $outputs);
$overlapSeconds = min((float) $timings[0]['finished_at'], (float) $timings[1]['finished_at'])
    - max((float) $timings[0]['work_started_at'], (float) $timings[1]['work_started_at']);

foreach (glob($directory . '/*') ?: [] as $file) {
    if (is_file($file)) unlink($file);
}
rmdir($directory);

if ($overlapSeconds < 0.35) {
    throw new RuntimeException('Post-release worker intervals did not overlap sufficiently.');
}
echo json_encode([
    'status' => 'PASSED',
    'workers' => 2,
    'overlap_ms' => round($overlapSeconds * 1000, 2),
], JSON_PRETTY_PRINT) . PHP_EOL;
