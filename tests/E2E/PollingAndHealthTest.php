<?php

$root = dirname(__DIR__, 2);
$app = file_get_contents($root . '/assets/js/app.js');
$router = file_get_contents($root . '/core/Router.php');
$bootstrap = file_get_contents($root . '/bootstrap.php');
$health = file_get_contents($root . '/controllers/HealthController.php');
$failures = [];
foreach (['AbortController', 'document.hidden', 'visibilitychange', 'maxInterval', 'pagehide'] as $token) {
    if (strpos($app, $token) === false) $failures[] = 'Adaptive polling missing: ' . $token;
}
if (strpos($app, 'setInterval(poll, 5000)') !== false || strpos($app, 'setInterval(fetchFeed, 15000)') !== false) $failures[] = 'Fixed polling interval remains.';
if (strpos($bootstrap, "['health/live', 'health/ready']") === false) $failures[] = 'Health endpoints do not bypass session startup.';
if (strpos($router, "health/live") === false || strpos($router, 'PluginManager::boot') === false || strpos($router, "health/live") > strpos($router, 'PluginManager::boot')) $failures[] = 'Health endpoint does not bypass plugin boot.';
if (strpos($health, 'migrationReady') === false || strpos($health, 'queueReady') === false) $failures[] = 'health/ready does not verify migrations and queue readiness.';
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PASSED PollingAndHealthTest\n";
