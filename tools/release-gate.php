<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$releaseChannel = strtolower(trim((string) ($versionInfo['release_channel'] ?? 'stable')));
$localQaOnly = in_array('--local', $argv, true);
$staticOnly = in_array('--static-only', $argv, true);
if (!$localQaOnly && $releaseChannel === 'stable' && version_compare((string) ($versionInfo['application'] ?? '0.0.0'), '1.4.2', '>=')) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/availability-gate.php') . ' --stable', $availabilityCode);
    if ($availabilityCode !== 0) {
        fwrite(STDERR, "RELEASE_GATE_FAILED\nProduction availability evidence is missing or incomplete.\n");
        exit(1);
    }
}
$staticTests = [
    'tests/backup_restore_validation_v404.php',
    'tests/backup_integrity_v405.php',
    'tests/backup_restore_copy_failure.php',
    'tests/backup_log_failure.php',
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
    'tests/financial_v501.php',
    'tests/static_v501.php',
    'tests/static_v502.php',
    'tests/error_response_v136.php',
    'tests/static_v136.php',
    'tests/static_v137.php',
    'tests/static_v138.php',
    'tests/static_v139.php',
    'tests/static_v140.php',
    'tests/static_v141.php',
    'tests/static_v143.php',
    'tests/static_v144.php',
    'tests/static_v145.php',
    'tests/session_v144.php',
    'tests/update_v144.php',
    'tests/update_preflight.php',
    'tests/update_archive_limits.php',
    'tests/update_file_rollback.php',
    'tests/update_retired_file_finalize.php',
    'tests/update_retired_file_rollback.php',
    'tests/retired_modules_invariants.php',
    'tests/update_migration_recovery.php',
    'tests/request_flood_guard_v148.php',
    'tests/contract_preview_storm_v149.php',
    'tests/installment_settlement_engine_v150.php',
    'tests/static_v150.php',
    'tests/static_v151.php',
    'tests/static_ui_panels_v153.php',
    'tests/static_v154.php',
    'tests/static_v155.php',
    'tests/legal_penalty_projection_v156.php',
    'tests/static_v156.php',
    'tests/static_v157.php',
    'tests/static_artifact_layout.php',
    'tests/static_v158.php',
    'tests/static_v159_guarantor_document.php',
    'tests/static_v1510_template_editor.php',
    'tests/static_v1513_role_redesign.php',
    'tests/static_v1514_ui_operations.php',
    'tests/static_v200_ui_redesign.php',
    'tests/static_v202_workspace_guarantors.php',
    'tests/static_v204_payment_scope_and_signature.php',
    'tests/static_v600_financial_risk_collections.php',
    'tests/static_v204_mobile_customer_installments.php',
    'tests/static_csp_customer_progress.php',
    'tests/static_accounting_update_2014.php',
    'plugins/PromaAccounting/tests/static.php',
    'plugins/PromaAccounting/tests/v110.php',
    'plugins/PromaAccounting/tests/v120.php',
    'plugins/PromaAccounting/tests/v1216.php',
    'plugins/PromaAccounting/tests/Unit/AccountBalanceIntegerInvariantTest.php',
    'tests/promarepair_domain_smoke.php',
    'tests/promarepair_release_checks.php',
    'tests/Plugins/PluginManifestIntegrityTest.php',
    'tests/Plugins/RepairMoneyIntegerInvariantTest.php',
    'tests/Plugins/TechnicianFinanceRouteGuardTest.php',
    'tests/Plugins/RepairSecretRevealTest.php',
    'tests/Plugins/SignConnectCapabilityIntegrityTest.php',
    'tests/Plugins/AffiliateRewardTriggerTest.php',
    'tests/Plugins/AffiliateAccountingConsistencyTest.php',
    'tests/Plugins/PluginMigrationRetrySafetyTest.php',
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
    'tests/Security/CustomerCredentialPolicyTest.php',
    'tests/Security/AuthPasswordChangeRevokesSessionsTest.php',
];
$integrationTests = [
    'tests/integration_zarinpal_v134.php',
    'tests/integration_v138_file_registry.php',
    'tests/integration_v139_contract_print_schema.php',
    'tests/integration_v139_custom_installments.php',
    'tests/integration_v140_core_workflows.php',
    'tests/integration_v141_release_blockers.php',
    'tests/integration_v143_release.php',
    'tests/integration_payment_scope_migration_v204.php',
    'tests/integration_payment_overflow_allocation_v204.php',
    'tests/integration_gateway_reconciliation_v204.php',
    'tests/integration_customer_document_query_v204.php',
    'tests/integration_outbox_delivery.php',
    'tests/integration_customer_trend_batch.php',
    'tests/integration_plugin_role_extension.php',
    'tests/integration_plugin_runtime_path_reconcile_v4.php',
    'tests/integration_settlement_migration_v151.php',
    'tests/integration_installment_settlement_v150.php',
    'tests/integration_installment_settlement_concurrency_v150.php',
    'tests/integration_legal_workflow_v150.php',
    'tests/integration_legal_penalty_projection_v156.php',
    'tests/integration_contract_installment_management_v157.php',
    'tests/integration_legal_financial_summary_v158.php',
    'tests/integration_v600_features.php',
    'tests/integration_guarantor_document_v159.php',
    'tests/integration_overdue_aggregation_v153.php',
    'tests/http_v141_role_smoke.php',
    'tests/http_v143_modal_smoke.php',
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
    if (preg_match('#^(?:\.git|dist|html|storage|tmp|skill)/#', $relative)
        || preg_match('#(?:^|/)(?:node_modules|vendor)/#', $relative)) {
        continue;
    }
    try {
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            throw new RuntimeException('could not read file');
        }
        token_get_all($source, TOKEN_PARSE);
    } catch (ParseError | RuntimeException $error) {
        $failed[] = $relative . ' (PHP lint: ' . $error->getMessage() . ')';
    }
    $linted++;
}
echo "[OK] PHP lint files={$linted}\n";

echo "[PHASE] Static and unit tests\n";
foreach ($staticTests as $test) {
    $run($test);
}

if ($staticOnly) {
    if ($failed) {
        fwrite(STDERR, "RELEASE_GATE_FAILED\n" . implode("\n", $failed) . "\n");
        exit(1);
    }
    echo $localQaOnly
        ? "RELEASE_GATE_LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED\n"
        : "RELEASE_GATE_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED\n";
    exit(0);
}

foreach (['PROMA_TEST_DB_DSN', 'PROMA_QA_BASE_URL', 'PROMA_TEST_ALLOW_DESTRUCTIVE'] as $requiredEnvironment) {
    if (trim((string) getenv($requiredEnvironment)) === '') {
        $failed[] = $requiredEnvironment . ' (required for real release integration tests)';
    }
}
$testDsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($testDsn !== '' && !preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', $testDsn)) {
    $failed[] = 'PROMA_TEST_DB_DSN (release integration tests are restricted to the disposable proma_qa_v204 database)';
}
if (trim((string) getenv('PROMA_TEST_ALLOW_DESTRUCTIVE')) !== '1') {
    $failed[] = 'PROMA_TEST_ALLOW_DESTRUCTIVE (set to 1 only for the disposable proma_qa_v204 database)';
}
$qaBaseUrl = trim((string) getenv('PROMA_QA_BASE_URL'));
if ($qaBaseUrl !== '') {
    $qaHost = strtolower(trim((string) parse_url($qaBaseUrl, PHP_URL_HOST), '[]'));
    if (!in_array($qaHost, ['localhost', '127.0.0.1', '::1'], true)) {
        $failed[] = 'PROMA_QA_BASE_URL (release HTTP tests are restricted to localhost/loopback)';
    }
}

if (!$failed) {
    echo "[PHASE] Database and HTTP integration tests\n";
    // A few integration tests intentionally exercise plugin state. Rebuild the
    // isolated QA fixture immediately before the HTTP suite so its result is
    // independent of the static-test execution order.
    $run('tests/qa_seed_roles_v150.php');
    if (!$failed) {
        foreach ($integrationTests as $test) {
            $run($test);
        }
    }
}

if ($failed) {
    fwrite(STDERR, "RELEASE_GATE_FAILED\n" . implode("\n", $failed) . "\n");
    exit(1);
}

if ($localQaOnly) {
    echo "RELEASE_GATE_LOCAL_QA_OK_NOT_PRODUCTION_CERTIFIED\n";
} elseif ($releaseChannel === 'stable') {
    echo "RELEASE_GATE_PRODUCTION_OK\n";
} else {
    echo "RELEASE_GATE_CANDIDATE_QA_OK_NOT_PRODUCTION_CERTIFIED\n";
}
