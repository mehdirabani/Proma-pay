<?php

$root = dirname(__DIR__, 2);
$auth = file_get_contents($root . '/controllers/AuthController.php');
$errors = file_get_contents($root . '/core/ErrorHandler.php');
$telemetry = file_get_contents($root . '/core/RequestTelemetry.php');
$failures = [];
if (strpos($auth, 'ErrorHandler::respond(429') === false || strpos($auth, "'Retry-After'") === false) $failures[] = 'Login throttling does not return controlled HTTP 429.';
if (strpos($errors, "429 =>") === false) $failures[] = 'Core 429 error experience is missing.';
foreach (['HTTP_COOKIE', 'password_hash', 'national_id', 'HTTP_AUTHORIZATION'] as $secretToken) {
    if (strpos($telemetry, $secretToken) !== false) $failures[] = 'Telemetry reads forbidden field: ' . $secretToken;
}
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PASSED RateLimitAndSecretsTest\n";
