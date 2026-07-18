<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(($version['application'] ?? '') === '1.3.6', 'Core version must be 1.3.6.');
$assert(($version['display'] ?? '') === 'V1.3.6', 'Display version must be V1.3.6.');

foreach ([
    'core/ErrorHandler.php',
    'core/HttpException.php',
    'helpers/MoneyMath.php',
    'views/errors/system.php',
    'assets/css/error-pages.css',
    'assets/css/components/responsive.css',
    'static-errors/500.html',
    'static-errors/503.html',
    'database/migrations/2026_07_18_runtime_schema_gate_v136.sql',
    'tools/release-gate.php',
] as $file) {
    $assert(is_file($root . '/' . $file), 'Missing V1.3.6 file: ' . $file);
}

$financialModels = [
    'models/Contract.php',
    'models/Installment.php',
    'models/Payment.php',
    'models/PaymentReceipt.php',
    'models/ContractDocument.php',
];
foreach ($financialModels as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(stripos($source, 'CREATE TABLE') === false && stripos($source, 'ALTER TABLE') === false, 'Runtime schema DDL remains in ' . $file . '.');
}

$errorHandler = (string) file_get_contents($root . '/core/ErrorHandler.php');
$modalScript = (string) file_get_contents($root . '/assets/js/app.js');
$receipt = (string) file_get_contents($root . '/models/PaymentReceipt.php');
$contract = (string) file_get_contents($root . '/models/Contract.php');
$installment = (string) file_get_contents($root . '/models/Installment.php');
$assert(strpos($errorHandler, 'set_exception_handler') !== false && strpos($errorHandler, 'register_shutdown_function') !== false, 'Central exception and fatal handling are incomplete.');
$assert(strpos($errorHandler, "header_remove('X-Powered-By')") !== false && strpos($errorHandler, 'Content-Security-Policy') !== false, 'Error response hardening headers are incomplete.');
$assert(strpos($modalScript, "aria-modal") !== false && strpos($modalScript, "proma-modal-open") !== false && strpos($modalScript, "tabindex") !== false, 'Shared accessible modal behavior is incomplete.');
$assert(strpos($receipt, 'SystemOutbox::safeEnqueueNotification') !== false && strpos($receipt, 'SystemOutbox::processPending') !== false, 'Receipt notifications are not isolated through the outbox.');
$assert(strpos($contract, 'SystemOutbox::safeEnqueuePluginHook') !== false && strpos($installment, 'SystemOutbox::safeEnqueuePluginHook') !== false, 'Contract or installment plugin hooks are still synchronous.');

foreach (['views/layouts/app.php', 'views/layouts/auth.php', 'views/layouts/public.php'] as $layout) {
    $assert(strpos((string) file_get_contents($root . '/' . $layout), 'assets/css/components/responsive.css') !== false, 'Responsive CSS is missing from ' . $layout . '.');
}

echo "STATIC_V136_OK\n";
