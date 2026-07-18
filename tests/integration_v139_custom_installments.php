<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8139';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';

require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$installmentId = 0;
try {
    $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['is_custom', 'custom_title', 'custom_description', 'internal_note', 'customer_visible', 'created_reason'] as $column) {
        $assert(in_array($column, $columns, true), 'Installments column is missing: ' . $column);
    }

    $contract = $pdo->query("SELECT id FROM contracts WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
    $assert($contract && (int) $contract['id'] > 0, 'An active QA contract is required.');

    $description = 'هزینه خدمات اضافه طبق توافق مشتری';
    $note = 'یادداشت داخلی QA؛ فقط برای مدیریت';
    $installmentId = Installment::createCustom((int) $contract['id'], '2026-12-20', 1234567, $description, 'QA-G-139', 'هزینه خدمات اضافه', $note, true);
    $assert($installmentId > 0, 'Custom installment did not return its identifier.');

    $created = Installment::find($installmentId);
    $assert((int) ($created['is_custom'] ?? 0) === 1, 'Custom installment flag was not persisted.');
    $assert(($created['custom_title'] ?? '') === 'هزینه خدمات اضافه', 'Custom installment title was not persisted.');
    $assert(($created['custom_description'] ?? '') === $description, 'Customer description was not persisted.');
    $assert(($created['internal_note'] ?? '') === $note, 'Internal note was not persisted separately.');
    $assert((int) ($created['customer_visible'] ?? 0) === 1, 'Customer visibility was not persisted.');

    Installment::updateInstallment($installmentId, [
        'due_date' => '2026-12-21',
        'base_amount' => 1400000,
        'customer_description' => 'اصلاح برنامه پرداخت با توافق طرفین',
        'custom_title' => 'اصلاحیه خدمات',
        'internal_note' => 'یادداشت داخلی اصلاح‌شده QA',
        'customer_visible' => 0,
    ]);

    $updated = Installment::find($installmentId);
    $assert((float) ($updated['base_amount'] ?? 0) === 1400000.0, 'Custom installment amount update failed.');
    $assert(($updated['custom_description'] ?? '') === 'اصلاح برنامه پرداخت با توافق طرفین', 'Customer description update failed.');
    $assert(($updated['internal_note'] ?? '') === 'یادداشت داخلی اصلاح‌شده QA', 'Internal note update failed.');
    $assert((int) ($updated['customer_visible'] ?? 1) === 0, 'Customer visibility update failed.');

    echo "INTEGRATION_V139_CUSTOM_INSTALLMENTS_OK\n";
} finally {
    if ($installmentId > 0) {
        $pdo->prepare('DELETE FROM installments WHERE id = ?')->execute([$installmentId]);
    }
}
