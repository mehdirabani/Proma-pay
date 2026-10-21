<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.1', '>='), 'Core version must include V1.5.1 settlement fix.');

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_26_installment_settlement_engine.sql');
$settlementDefinition = (string) preg_replace('/^.*?CREATE TABLE IF NOT EXISTS settlement_quotes \(/s', '', $migration);
$settlementDefinition = (string) preg_replace('/\) ENGINE=InnoDB.*$/s', '', $settlementDefinition);
$assert(substr_count($settlementDefinition, 'PRIMARY KEY') === 1, 'settlement_quotes must define exactly one primary key.');
$assert(strpos($settlementDefinition, 'id BIGINT UNSIGNED AUTO_INCREMENT,') !== false, 'settlement_quotes id must not carry an inline duplicate primary key.');

$paymentView = (string) file_get_contents($root . '/views/payments/index.php');
$assert(strpos($paymentView, 'proma-payment-row-actions') !== false, 'Payment actions require an inner alignment wrapper.');
$assert(strpos($paymentView, '<td class="actions">') === false, 'Payment table cell must retain native table layout.');

echo "STATIC_V151_OK\n";
