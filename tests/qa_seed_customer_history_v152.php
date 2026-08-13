<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require dirname(__DIR__) . '/bootstrap.php';

$customer = Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', ['8000001504']);
$admin = Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', ['qa150admin']);
if (!$customer || !$admin) {
    throw new RuntimeException('Run qa_seed_roles_v150.php before seeding customer history.');
}

$contract = Model::fetch('SELECT id FROM contracts WHERE contract_number = ? LIMIT 1', ['QA-HISTORY-152']);
if (!$contract) {
    $contractId = Contract::createWithInstallments([
        'customer_id' => (int) $customer['id'],
        'contract_number' => 'QA-HISTORY-152',
        'principal_amount' => 65000000,
        'down_payment_amount' => 20000000,
        'monthly_interest_rate' => 0,
        'interest_type' => 'simple',
        'months' => 8,
        'start_date' => '2026-05-06',
        'first_due_date' => '2026-05-15',
        'created_by' => (int) $admin['id'],
    ]);
    $contract = ['id' => $contractId];
}

$firstInstallment = Model::fetch('SELECT id, base_amount FROM installments WHERE contract_id = ? ORDER BY installment_number, id LIMIT 1', [(int) $contract['id']]);
if ($firstInstallment && !Model::fetch('SELECT id FROM payments WHERE installment_id = ? AND status = ? LIMIT 1', [(int) $firstInstallment['id'], 'paid'])) {
    Payment::record(
        (int) $firstInstallment['id'],
        (int) $contract['id'],
        (int) $admin['id'],
        (float) $firstInstallment['base_amount'],
        'card_transfer',
        'paid',
        null,
        'QA-HISTORY-152',
        'Visual QA history payment',
        '2026-05-06'
    );
}

echo "QA_CUSTOMER_HISTORY_READY\n";
