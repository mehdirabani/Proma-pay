<?php

$root = dirname(__DIR__, 2);
$router = file_get_contents($root . '/core/Router.php');
$controller = file_get_contents($root . '/plugins/PromaAccounting/src/Controllers/AccountingController.php');
$failures = [];
$assert = static function ($condition, $message) use (&$failures) { if (!$condition) $failures[] = $message; };
$healthPosition = strpos($router, "if (\$route === 'health/live'");
$pluginPosition = strpos($router, "PluginManager::boot()");
$assert($healthPosition !== false && $pluginPosition !== false && $healthPosition < $pluginPosition, 'health/live must bypass plugin boot.');
$assert(strpos($controller, 'dashboardWidget') !== false && strpos($controller, 'catch (\\Throwable $e)') !== false, 'Accounting widgets do not degrade independently.');
$assert(strpos($controller, "ErrorHandler::requestId()") === false, 'Controller fallback should not expose implementation details directly.');
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PASSED AccountingFailureIsolationTest\n";
