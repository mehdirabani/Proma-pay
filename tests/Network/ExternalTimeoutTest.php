<?php

$root = dirname(__DIR__, 2);
$files = [
    'helpers/ZibalClient.php' => [5, 15],
    'helpers/IppanelClient.php' => [5, 15],
    'helpers/OpenRouterClient.php' => [5, 30],
];
$failures = [];
foreach ($files as $file => $limits) {
    $source = file_get_contents($root . '/' . $file);
    if (!preg_match('/CURLOPT_CONNECTTIMEOUT\s*=>\s*(\d+)/', $source, $connect) || (int) $connect[1] > $limits[0]) $failures[] = $file . ' connect timeout';
    if (!preg_match('/CURLOPT_TIMEOUT\s*=>\s*(\d+)/', $source, $total) || (int) $total[1] > $limits[1]) $failures[] = $file . ' total timeout';
    if (strpos($source, 'RequestTelemetry::recordExternal') === false) $failures[] = $file . ' telemetry';
}
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PASSED ExternalTimeoutTest\n";
