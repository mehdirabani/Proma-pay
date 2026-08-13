<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__) . '/bootstrap.php';

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required for this destructive integration test.\n");
    exit(2);
}
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$customer = Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', ['8000001504']);
$assert($customer, 'QA customer must exist before the overdue aggregation test.');
$suffix = (string) random_int(100000, 999999);
$prefix = 'QA153' . $suffix;
$numbers = [$prefix . '-A', $prefix . '-B'];
$contractIds = [];

try {
    foreach ($numbers as $index => $number) {
        Model::execute(
            "INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, 'simple', 3, CURDATE(), DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'active', NOW())",
            [(int) $customer['id'], $number, $prefix, (int) $suffix + $index, 3000000]
        );
        $contractIds[] = (int) Model::lastInsertId();
    }
    foreach ([1, 2, 3] as $number) {
        Model::execute(
            "INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
             VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), 1000000, 0, 1000000, 'pending', NOW())",
            [$contractIds[0], $number, $number * 9]
        );
    }
    Model::execute(
        "INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
         VALUES (?, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 900000, 0, 900000, 'pending', NOW())",
        [$contractIds[1]]
    );

    $criteria = OverdueFilterCriteria::fromRequest([
        'q' => $prefix,
        'sort' => 'overdue_count_desc',
        'per_page' => '۲۵',
        'page' => '1',
    ]);
    $result = OverdueAggregationService::paginated($criteria);
    $items = array_values(array_filter($result['items'], static fn (array $item): bool => strpos((string) $item['contract_number'], $prefix) === 0));
    $assert(count($items) === 2, 'Each overdue contract must appear exactly once.');
    $assert((int) $items[0]['contract_id'] === $contractIds[0], 'Most-overdue sort must use aggregate overdue_count.');
    $assert((int) $items[0]['overdue_count'] === 3, 'Aggregate overdue count must equal three.');
    $assert(count($items[0]['installment_ids'] ?? []) === 3, 'Contract drill-down must retain all three overdue installment ids.');

    Model::execute(
        "INSERT INTO legal_cases (customer_id, contract_id, status, stage, created_at, updated_at)
         VALUES (?, ?, 'filed', 'qa-exclusion', NOW(), NOW())",
        [(int) $customer['id'], $contractIds[1]]
    );
    $excludedLegalCriteria = OverdueFilterCriteria::fromRequest(['q' => $prefix, 'exclude_legal_cases' => 'on']);
    $excludedLegalResult = OverdueAggregationService::paginated($excludedLegalCriteria);
    $assert((int) $excludedLegalResult['total'] === 1, 'Active legal cases must be excluded when the queue checkbox is enabled.');
    $assert((int) $excludedLegalResult['items'][0]['contract_id'] === $contractIds[0], 'Legal-case queue exclusion returned an incorrect contract.');

    $installmentResult = Installment::filtered([
        'search' => $prefix,
        'payment_state' => 'overdue',
        'exclude_legal_cases' => true,
        'page' => 1,
        'per_page' => 25,
        'sort' => 'due_asc',
    ]);
    $assert((int) $installmentResult['total'] === 3, 'Installment legal-case exclusion must remove every row of a complained contract.');
    foreach ($installmentResult['items'] as $item) {
        $assert((int) $item['contract_id'] === $contractIds[0], 'Installment legal-case exclusion returned a complained contract.');
    }

    $persianCriteria = OverdueFilterCriteria::fromRequest(['q' => $prefix, 'min_overdue_count' => '۳', 'sort' => 'delay_desc']);
    $persianResult = OverdueAggregationService::paginated($persianCriteria);
    $assert((int) $persianResult['total'] === 1, 'Persian numeric aggregate filter must be normalized server-side.');
    $assert((int) $persianResult['items'][0]['contract_id'] === $contractIds[0], 'Combined aggregate filter returned an incorrect contract.');

    $invalidCriteria = OverdueFilterCriteria::fromRequest(['q' => $prefix, 'sort' => 'estimated_payable DESC; DROP TABLE installments', 'direction' => 'sideways', 'page' => '999999']);
    $assert($invalidCriteria->sort === 'oldest', 'Invalid sort must safely fall back to the whitelist default.');
    $invalidResult = OverdueAggregationService::paginated($invalidCriteria);
    $assert((int) $invalidResult['page'] === 1, 'Out-of-range page must normalize to the last valid page.');
} finally {
    foreach ($contractIds as $contractId) {
        Model::execute('DELETE FROM contracts WHERE id = ?', [$contractId]);
    }
}

echo "INTEGRATION_OVERDUE_AGGREGATION_V153_OK\n";
