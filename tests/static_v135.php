<?php

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';
if (version_compare((string) ($version['application'] ?? '0.0.0'), '1.3.5', '<')) {
    throw new RuntimeException('Core version must be 1.3.5 or newer.');
}
if (!preg_match('/^V\d+\.\d+\.\d+(?:-rc\.\d+)?$/', (string) ($version['display'] ?? ''))) {
    throw new RuntimeException('Display version must use semantic Vx.y.z format.');
}

$files = [
    'database/migrations/2026_07_13_payment_reliability_v135.sql',
    'models/SystemOutbox.php',
    'models/PaymentRequest.php',
    'docs/releases/V1.3.5.md',
    'docs/reports/PROMA_PAY_V1_3_5_PAYMENT_RELIABILITY_REPORT.md',
];
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Missing V1.3.5 file: ' . $file);
    }
}

$payment = file_get_contents($root . '/models/Payment.php');
$installmentsController = file_get_contents($root . '/controllers/InstallmentsController.php');
$paymentGroup = file_get_contents($root . '/helpers/PaymentGroupService.php');
$paymentsController = file_get_contents($root . '/controllers/PaymentsController.php');
$paymentReceipt = file_get_contents($root . '/models/PaymentReceipt.php');
$build = file_get_contents($root . '/scripts/build_release.php');
$installmentsView = file_get_contents($root . '/views/installments/index.php');
$overdueView = file_get_contents($root . '/views/overdue/index.php');
$contractView = file_get_contents($root . '/views/contracts/show.php');
$installSql = file_get_contents($root . '/database/proma-pay-install.sql');

$checks = [
    ['Payment uses outbox', strpos($payment, 'SystemOutbox::safeEnqueuePluginHook') !== false && strpos($payment, 'recordPaymentAudit') !== false],
    ['Manual payment uses idempotency', strpos($installmentsController, 'payment_request_uuid') !== false && strpos($installmentsController, 'PaymentRequest::beginRequest') !== false],
    ['Manual payment isolates side effects', strpos($installmentsController, 'PaymentRequest::complete') !== false && strpos($installmentsController, 'safeEnqueueNotification') !== false],
    ['Gateway path uses outbox', strpos($paymentsController, 'safeEnqueueNotification') !== false && strpos($paymentsController, 'SystemOutbox::processPending') !== false],
    ['Group payments use outbox', strpos($paymentGroup, 'safeEnqueuePluginHook') !== false && strpos($paymentGroup, 'SystemOutbox::processPending') !== false],
    ['Receipts use outbox', strpos($paymentReceipt, 'safeEnqueueNotification') !== false && strpos($paymentReceipt, 'SystemOutbox::processPending') !== false],
    ['Build script inventories changed migrations', strpos($build, "diff --name-only --diff-filter=ACMRT") !== false && strpos($build, "database/migrations/") !== false],
    ['Forms include request UUID', strpos($installmentsView, 'payment_request_uuid') !== false && strpos($overdueView, 'payment_request_uuid') !== false && strpos($contractView, 'payment_request_uuid') !== false],
    ['Install SQL includes new tables', strpos($installSql, 'CREATE TABLE `payment_requests`') !== false && strpos($installSql, 'CREATE TABLE `system_outbox`') !== false],
];

foreach ($checks as [$label, $ok]) {
    if (!$ok) {
        throw new RuntimeException('Failed static check: ' . $label);
    }
}

echo "STATIC_V135_OK\n";
