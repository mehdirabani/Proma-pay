<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$releaseChannel = strtolower(trim((string) ($versionInfo['release_channel'] ?? 'stable')));
if ($releaseChannel === 'stable' && version_compare((string) ($versionInfo['application'] ?? '0.0.0'), '1.4.2', '>=')) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/availability-gate.php') . ' --stable', $availabilityCode);
    if ($availabilityCode !== 0) {
        fwrite(STDERR, "RELEASE_GATE_FAILED\nProduction availability evidence is missing or incomplete.\n");
        exit(1);
    }
}
$staticTests = [
    'tests/static_v126.php',
    'tests/static_v128.php',
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
    'tests/static_v137.php',
    'tests/static_v138.php',
    'tests/static_v139.php',
    'tests/static_v140.php',
    'tests/static_v141.php',
    'tests/static_accounting_update_2014.php',
    'plugins/PromaAccounting/tests/static.php',
    'plugins/PromaAccounting/tests/v110.php',
    'plugins/PromaAccounting/tests/v120.php',
    'plugins/PromaZarinpal/tests/static.php',
    'plugins/PromaZarinpal/tests/Unit/AmountConverterTest.php',
    'plugins/PromaZarinpal/tests/Unit/ZarinpalClientTest.php',
    'plugins/PromaZarinpal/tests/Integration/OfficialProtocolTest.php',
    'plugins/PromaZarinpal/tests/Sandbox/SandboxProtocolTest.php',
    'plugins/PromaZarinpal/tests/Security/SecretCipherTest.php',
    'plugins/PromaZarinpal/tests/Security/StaticSecurityTest.php',
    'tests/Availability/RequestTelemetryTest.php',
    'tests/Performance/AccountingDashboardBoundedTest.php',
    'tests/Plugins/AccountingFailureIsolationTest.php',
    'tests/Database/RuntimeDdlAndLockTest.php',
    'tests/Network/ExternalTimeoutTest.php',
    'tests/Security/RateLimitAndSecretsTest.php',
    'tests/E2E/PollingAndHealthTest.php',
    'tests/Availability/SessionLockConcurrencyTest.php',
];
$integrationTests = [
    'tests/integration_zarinpal_v134.php',
    'tests/integration_v138_file_registry.php',
    'tests/integration_v139_contract_print_schema.php',
    'tests/integration_v139_custom_installments.php',
    'tests/integration_v140_core_workflows.php',
    'tests/integration_v141_release_blockers.php',
    'tests/http_v141_role_smoke.php',
    'tests/Performance/AccountingDashboardMariaDbTest.php',
    'tests/E2E/AccountingDashboardHttpTest.php',
    'tests/Plugins/AccountingFailureHttpTest.php',
];

$failed = [];
$run = static function (string $test) use ($root, &$failed): void {
    $path = $root . '/' . $test;
    if (!is_file($path)) {
        $failed[] = $test . ' (missing)';
        return;
    }
    echo "[RUN] {$test}\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        $failed[] = $test . ' (exit ' . $code . ')';
    }
};

echo "[PHASE] PHP lint\n";
$linted = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('#^(?:\.git|dist|html|storage|tmp)/#', $relative)) {
        continue;
    }
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $failed[] = $relative . ' (PHP lint: ' . implode(' ', $output) . ')';
    }
    $output = [];
    $linted++;
}
echo "[OK] PHP lint files={$linted}\n";

echo "[PHASE] Static and unit tests\n";
foreach ($staticTests as $test) {
    $run($test);
}

foreach (['PROMA_TEST_DB_DSN', 'PROMA_QA_BASE_URL'] as $requiredEnvironment) {
    if (trim((string) getenv($requiredEnvironment)) === '') {
        $failed[] = $requiredEnvironment . ' (required for real release integration tests)';
    }
}

if (!$failed) {
    echo "[PHASE] Database and HTTP integration tests\n";
    foreach ($integrationTests as $test) {
        $run($test);
    }
}

if ($failed) {
    fwrite(STDERR, "RELEASE_GATE_FAILED\n" . implode("\n", $failed) . "\n");
    exit(1);
}

echo "RELEASE_GATE_LOCAL_QA_OK\n";
