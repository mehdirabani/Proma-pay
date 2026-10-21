<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$versionInfo = require $root . '/config/version.php';
$channel = strtolower(trim((string) ($versionInfo['release_channel'] ?? '')));
$stableCheck = in_array('--stable', $argv, true) || $channel === 'stable';
$requiredDocs = [
    'docs/incidents/TIMEOUT_IP_BLOCK_INCIDENT.md',
    'docs/incidents/NETWORK_AND_DNS_AUDIT.md',
    'docs/incidents/IP_BLOCK_SOURCE_AUDIT.md',
    'docs/incidents/WAF_MODSECURITY_AUDIT.md',
    'docs/incidents/ACCOUNTING_DASHBOARD_TIMEOUT_AUDIT.md',
    'docs/incidents/DATABASE_TIMEOUT_AND_LOCK_AUDIT.md',
    'docs/incidents/PHP_WEB_SERVER_CAPACITY_AUDIT.md',
    'docs/incidents/EXTERNAL_CALL_AUDIT.md',
    'docs/incidents/CRON_AND_JOB_OVERLAP_AUDIT.md',
    'docs/incidents/ROOT_CAUSE_AND_REMEDIATION.md',
    'docs/qa/AVAILABILITY_TEST_RESULTS.md',
    'docs/qa/IP_BLOCK_REGRESSION_RESULTS.md',
    'docs/qa/PERFORMANCE_TEST_RESULTS.md',
    'docs/qa/SOAK_TEST_RESULTS.md',
    'docs/qa/RELEASE_CHECKLIST.md',
    'docs/qa/TIMEOUT_BUG_REGISTER.md',
];

$missing = [];
foreach ($requiredDocs as $document) {
    if (!is_file($root . '/' . $document)) {
        $missing[] = $document;
    }
}
if ($missing) {
    fwrite(STDERR, "AVAILABILITY_GATE_FAILED\nMissing documents:\n" . implode("\n", $missing) . "\n");
    exit(1);
}

if (!$stableCheck) {
    fwrite(STDERR, "AVAILABILITY_GATE_BLOCKED\nRC source checks may continue, but stable publication requires production infrastructure evidence.\n");
    exit(2);
}

$evidencePath = trim((string) getenv('PROMA_PRODUCTION_AVAILABILITY_EVIDENCE'));
if ($evidencePath === '' || !is_file($evidencePath) || !is_readable($evidencePath)) {
    fwrite(STDERR, "AVAILABILITY_GATE_BLOCKED\nSet PROMA_PRODUCTION_AVAILABILITY_EVIDENCE to the protected production evidence JSON collected from hosting logs.\n");
    exit(2);
}

$evidence = json_decode((string) file_get_contents($evidencePath), true);
if (!is_array($evidence)) {
    fwrite(STDERR, "AVAILABILITY_GATE_FAILED\nProduction evidence is not valid JSON.\n");
    exit(1);
}

$requiredPassed = [
    'timeout_classification',
    'ip_block_source',
    'security_rule_identification',
    'dns',
    'ipv4',
    'ipv6',
    'tls',
    'accounting_dashboard',
    'database_lock',
    'worker_capacity',
    'load_test',
    'soak_test',
    'ip_block_regression',
];
$failed = [];
foreach ($requiredPassed as $field) {
    if (($evidence[$field] ?? null) !== 'PASSED') {
        $failed[] = $field . '=' . (string) ($evidence[$field] ?? 'MISSING');
    }
}
$sources = $evidence['evidence_sources'] ?? [];
if (!is_array($sources) || count($sources) < 5) {
    $failed[] = 'evidence_sources=INSUFFICIENT';
}
$collectedAt = strtotime((string) ($evidence['collected_at'] ?? ''));
if (!$collectedAt || $collectedAt < strtotime('-30 days')) {
    $failed[] = 'collected_at=STALE_OR_MISSING';
}
if (!preg_match('/^(?:\d{1,3}\.){3}0$|^[0-9a-f:]+::$/i', (string) ($evidence['affected_ip_masked'] ?? ''))) {
    $failed[] = 'affected_ip_masked=MISSING_OR_UNMASKED';
}

if ($failed) {
    fwrite(STDERR, "AVAILABILITY_GATE_BLOCKED\n" . implode("\n", $failed) . "\n");
    exit(2);
}

echo "AVAILABILITY_GATE_OK\n";
