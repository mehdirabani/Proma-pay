<?php

$root = dirname(__DIR__, 2);
$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};
$telemetry = file_get_contents($root . '/core/RequestTelemetry.php');
$bootstrap = file_get_contents($root . '/bootstrap.php');
$model = file_get_contents($root . '/core/Model.php');
$assert(strpos($bootstrap, 'RequestTelemetry::boot()') !== false, 'Request telemetry is not booted globally.');
$assert(strpos($telemetry, 'X-Request') === false, 'Telemetry must not duplicate response header ownership.');
foreach (['request_id', 'duration_ms', 'client_ip', 'memory_peak_bytes', 'slowest', 'external_calls'] as $token) {
    $assert(strpos($telemetry, "'{$token}'") !== false, 'Telemetry field is missing: ' . $token);
}
$assert(strpos($model, 'RequestTelemetry::recordQuery') !== false, 'Database query profiler is not connected.');
$assert(strpos($telemetry, '$currentBytes >= ($maxBytes * 2)') !== false, 'Telemetry has no hard daily file-size ceiling for incident traffic.');
$assert(strpos($telemetry, 'password') === false && strpos($telemetry, 'session cookies') === false, 'Telemetry source contains a forbidden secret field.');
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASSED RequestTelemetryTest\n";
