<?php
/**
 * Add a clearly marked, reversible presentation dataset to the local Proma Pay demo.
 * All writes are direct, transactional database inserts; no app hooks, gateways,
 * message providers, phone calls, or legal filing integrations are invoked.
 *
 * Usage: php tools/seed_presentation_demo.php            (dry run)
 *        php tools/seed_presentation_demo.php --apply    (create backup + seed)
 */

ob_start();
chdir('C:/laragon/www/proma');
require 'bootstrap.php';
ob_end_clean();

function demo_insert(PDO $db, string $table, array $row): int
{
    if (!preg_match('/\A[a-z0-9_]+\z/i', $table)) {
        throw new InvalidArgumentException('Invalid table name.');
    }
    $columns = array_keys($row);
    foreach ($columns as $column) {
        if (!preg_match('/\A[a-z0-9_]+\z/i', (string) $column)) {
            throw new InvalidArgumentException('Invalid column name.');
        }
    }
    $columnSql = implode(', ', array_map(static fn ($column) => '`' . $column . '`', $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $db->prepare("INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholders})");
    $stmt->execute(array_values($row));
    return (int) $db->lastInsertId();
}

function demo_count(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function demo_date(DateTimeImmutable $date, string $time = '12:00:00'): string
{
    return $date->format('Y-m-d') . ' ' . $time;
}

$db = Model::db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$version = function_exists('app_config') ? (string) app_config('version', '') : '';
if ($database !== 'proma_test' || $version !== '7.0.2') {
    fwrite(STDERR, "Stopped: expected local proma_test / Proma Pay 7.0.2; found {$database} / {$version}.\n");
    exit(2);
}

$requiredTables = [
    'users', 'contracts', 'installments', 'payments', 'contract_items', 'contract_guarantees',
    'legal_cases', 'legal_case_logs', 'operator_calls', 'notifications', 'messages', 'events',
    'customer_credit_profiles', 'proma_repairs', 'proma_repair_devices', 'proma_repair_technicians',
    'proma_repair_assignments', 'proma_repair_diagnoses', 'proma_repair_charges',
    'proma_repair_payments', 'proma_repair_status_history', 'proma_repair_notes',
    'proma_repair_warranties', 'proma_affiliate_members', 'proma_affiliate_referrals',
    'proma_affiliate_transactions',
];
$tableCheck = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach ($requiredTables as $table) {
    $tableCheck->execute([$table]);
    if ((int) $tableCheck->fetchColumn() !== 1) {
        fwrite(STDERR, "Stopped: required module table is missing: {$table}\n");
        exit(2);
    }
}

$existingDemo = [
    'users' => demo_count($db, "SELECT COUNT(*) FROM users WHERE username LIKE 'demo.%' OR national_id LIKE 'DEMO-%'"),
    'contracts' => demo_count($db, "SELECT COUNT(*) FROM contracts WHERE prefix='DEMO' OR contract_number LIKE 'DEMO-%'"),
    'repairs' => demo_count($db, "SELECT COUNT(*) FROM proma_repairs WHERE tracking_code LIKE 'DEMO-%'"),
    'affiliate' => demo_count($db, "SELECT COUNT(*) FROM proma_affiliate_members WHERE referral_code='DEMO1405'"),
];
if (array_sum($existingDemo) > 0) {
    fwrite(STDERR, 'Stopped: presentation seed markers already exist. No records were changed. ' . json_encode($existingDemo, JSON_UNESCAPED_UNICODE) . "\n");
    exit(3);
}

$applying = in_array('--apply', $argv, true);
$plan = [
    'demo customer accounts' => 10,
    'demo staff accounts (operator, lawyer, technician)' => 3,
    'contracts with schedules and items' => 9,
    'legal review examples (internal demo only)' => 2,
    'operator follow-ups' => 7,
    'chat messages' => 6,
    'in-app notifications' => 3,
    'calendar events' => 3,
    'repair orders' => 6,
    'affiliate referrals' => 2,
];
if (!$applying) {
    echo "Dry run only. No database changes made.\n";
    echo 'Database: ' . $database . ' | Application: ' . $version . "\n";
    foreach ($plan as $label => $count) echo '- ' . $label . ': ' . $count . "\n";
    echo "Run with --apply to create a database backup and insert the sample dataset.\n";
    exit(0);
}

if (!class_exists('BackupService')) {
    fwrite(STDERR, "Stopped: backup service is unavailable. No records were changed.\n");
    exit(2);
}
$backup = BackupService::create('before-demo-data');
if (empty($backup['path']) || !is_file($backup['path'])) {
    fwrite(STDERR, "Stopped: backup creation did not return a file. No records were changed.\n");
    exit(2);
}

$tz = new DateTimeZone('Asia/Tehran');
$today = new DateTimeImmutable('today', $tz);
$monthStart = new DateTimeImmutable($today->format('Y-m-01'), $tz);
$now = new DateTimeImmutable('now', $tz);
$nowSql = $now->format('Y-m-d H:i:s');
$dateOffset = static fn (int $days): DateTimeImmutable => $today->modify(($days >= 0 ? '+' : '') . $days . ' days');
$dueDateFor = static function (int $monthOffset, int $day) use ($monthStart): DateTimeImmutable {
    $month = $monthStart->modify(($monthOffset >= 0 ? '+' : '') . $monthOffset . ' months');
    return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day);
};

$staffData = [
    'operator' => ['role' => 'operator', 'username' => 'demo.operator', 'name' => 'آرزو صادقی', 'mobile' => '00000009001', 'national_id' => 'DEMO-S-001', 'email' => 'demo.operator@example.test'],
    'lawyer' => ['role' => 'lawyer', 'username' => 'demo.lawyer', 'name' => 'نادر قاسمی', 'mobile' => '00000009002', 'national_id' => 'DEMO-S-002', 'email' => 'demo.lawyer@example.test'],
    'technician' => ['role' => 'repair_technician', 'username' => 'demo.technician', 'name' => 'سروش حیدری', 'mobile' => '00000009003', 'national_id' => 'DEMO-S-003', 'email' => 'demo.technician@example.test'],
];
$customerNames = [
    'نازنین احمدی', 'سارا محمدی', 'امیرحسین رضایی', 'مریم کریمی', 'علی مرادی',
    'نگار حسینی', 'محمدرضا اکبری', 'ترانه رحیمی', 'پارسا جعفری', 'یگانه موسوی',
];
$passwordHash = password_hash('Demo@2026', PASSWORD_DEFAULT);

try {
    $db->beginTransaction();

    $users = [];
    $addUser = static function (array $data) use ($db, $passwordHash, $nowSql): int {
        $suggestion = function_exists('avatar_suggestion_for') ? avatar_suggestion_for($data['name'], $data['role']) : [];
        $row = [
            'role' => $data['role'],
            'username' => $data['username'],
            'full_name' => $data['name'],
            'national_id' => $data['national_id'],
            'mobile' => $data['mobile'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'customer_password_mode' => $data['role'] === 'customer' ? 'custom' : null,
            'status' => 'active',
            'address' => 'نشانی نمونه نمایشی - تهران',
            'avatar_key' => $suggestion['key'] ?? null,
            'avatar_category' => $suggestion['category'] ?? null,
            'avatar_source' => $suggestion['source'] ?? 'fallback',
            'avatar_locked' => 0,
            'avatar_suggestion_reason' => $suggestion['reason'] ?? 'حساب آزمایشی ارائه.',
            'created_at' => $nowSql,
            'updated_at' => $nowSql,
        ];
        return demo_insert($db, 'users', $row);
    };

    foreach ($staffData as $key => $data) $users[$key] = $addUser($data);
    foreach ($customerNames as $index => $name) {
        $number = $index + 1;
        $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $users[$suffix] = $addUser([
            'role' => 'customer',
            'username' => 'demo.customer' . $suffix,
            'name' => $name,
            'mobile' => '000000010' . $suffix,
            'national_id' => 'DEMO-C-' . $suffix,
            'email' => 'demo.customer' . $suffix . '@example.test',
        ]);
    }

    // The principal is the purchase price; down payment plus the equal installments match it.
    $contractPlans = [
        ['customer' => '01', 'code' => '001', 'price' => 96000000, 'down' => 12000000, 'months' => 12, 'installment' => 7000000, 'first_offset' => -3, 'day' => 25, 'paid' => [1, 2], 'partial' => [], 'status' => 'active', 'product' => 'Samsung Galaxy A55 256GB', 'guarantee' => 'سفته'],
        ['customer' => '02', 'code' => '002', 'price' => 70000000, 'down' => 10000000, 'months' => 6, 'installment' => 10000000, 'first_offset' => -5, 'day' => 10, 'paid' => [1, 2], 'partial' => [], 'status' => 'active', 'product' => 'iPhone 13 128GB', 'guarantee' => 'چک'],
        ['customer' => '03', 'code' => '003', 'price' => 48000000, 'down' => 8000000, 'months' => 8, 'installment' => 5000000, 'first_offset' => -2, 'day' => 12, 'paid' => [1, 2, 3], 'partial' => [], 'status' => 'active', 'product' => 'Xiaomi Redmi Note 13 Pro', 'guarantee' => 'سفته'],
        ['customer' => '04', 'code' => '004', 'price' => 36000000, 'down' => 6000000, 'months' => 6, 'installment' => 5000000, 'first_offset' => -9, 'day' => 15, 'paid' => [1, 2, 3, 4, 5, 6], 'partial' => [], 'status' => 'completed', 'product' => 'Samsung Galaxy A35 128GB', 'guarantee' => 'ضامن'],
        ['customer' => '05', 'code' => '005', 'price' => 84000000, 'down' => 14000000, 'months' => 10, 'installment' => 7000000, 'first_offset' => 0, 'day' => 27, 'paid' => [], 'partial' => [], 'status' => 'active', 'product' => 'MacBook Air M2 256GB', 'guarantee' => 'چک و سفته'],
        ['customer' => '06', 'code' => '006', 'price' => 60000000, 'down' => 0, 'months' => 12, 'installment' => 5000000, 'first_offset' => -1, 'day' => 18, 'paid' => [1], 'partial' => [2 => 2000000], 'status' => 'active', 'product' => 'Samsung Galaxy S23 FE', 'guarantee' => 'سفته'],
        ['customer' => '07', 'code' => '007', 'price' => 42000000, 'down' => 7000000, 'months' => 7, 'installment' => 5000000, 'first_offset' => -4, 'day' => 5, 'paid' => [], 'partial' => [], 'status' => 'active', 'product' => 'Lenovo Tab P12 128GB', 'guarantee' => 'ضامن'],
        ['customer' => '08', 'code' => '008', 'price' => 55000000, 'down' => 5000000, 'months' => 10, 'installment' => 5000000, 'first_offset' => 1, 'day' => 15, 'paid' => [], 'partial' => [], 'status' => 'active', 'product' => 'Xiaomi Pad 6 256GB', 'guarantee' => 'سفته'],
        ['customer' => '01', 'code' => '009', 'price' => 24000000, 'down' => 6000000, 'months' => 6, 'installment' => 3000000, 'first_offset' => -14, 'day' => 10, 'paid' => [1, 2, 3, 4, 5, 6], 'partial' => [], 'status' => 'completed', 'product' => 'Samsung Galaxy A24 128GB', 'guarantee' => 'سفته'],
    ];
    $contracts = [];
    $installments = [];
    $paymentTotal = 0;
    foreach ($contractPlans as $plan) {
        $firstDue = $dueDateFor((int) $plan['first_offset'], (int) $plan['day']);
        $startDate = $firstDue->modify('-1 month');
        $contractNumber = 'DEMO-1405-' . $plan['code'];
        $contractId = demo_insert($db, 'contracts', [
            'customer_id' => $users[$plan['customer']],
            'contract_number' => $contractNumber,
            'prefix' => 'DEMO',
            'serial' => 9000 + (int) $plan['code'],
            'principal_amount' => $plan['price'],
            'down_payment_amount' => $plan['down'],
            'monthly_interest_rate' => 0,
            'interest_type' => 'simple',
            'months' => $plan['months'],
            'start_date' => $startDate->format('Y-m-d'),
            'first_due_date' => $firstDue->format('Y-m-d'),
            'status' => $plan['status'],
            'assigned_operator_id' => $users['operator'],
            'seller_user_id' => $users['operator'],
            'legal_status' => null,
            'notes' => '[DEMO] قرارداد آزمایشی برای ارائه سامانه؛ فاقد اثر مالی واقعی.',
            'created_at' => demo_date($startDate, '09:00:00'),
            'updated_at' => $nowSql,
        ]);
        $contracts[$plan['code']] = $contractId;

        $itemIndex = 9000 + (int) $plan['code'];
        demo_insert($db, 'contract_items', [
            'contract_id' => $contractId,
            'product_model' => $plan['product'],
            'imei_1' => 'DEMO-IMEI-' . $itemIndex,
            'imei_2' => null,
            'description' => '[DEMO] کالای نمونه جهت نمایش سابقه خرید.',
            'created_at' => demo_date($startDate, '09:00:00'),
            'updated_at' => $nowSql,
        ]);
        if ($plan['guarantee'] !== '') {
            demo_insert($db, 'contract_guarantees', [
                'contract_id' => $contractId,
                'guarantee_type' => $plan['guarantee'],
                'guarantee_count' => 1,
                'guarantee_amount' => max(0, (int) $plan['price'] - (int) $plan['down']),
                'guarantee_serial' => 'DEMO-GUAR-' . $plan['code'],
                'guarantee_description' => 'نمونه نمایشی؛ شناسه و ضمانت واقعی نیست.',
                'created_at' => demo_date($startDate, '09:00:00'),
                'updated_at' => $nowSql,
            ]);
        }

        if ((int) $plan['down'] > 0) {
            demo_insert($db, 'payments', [
                'installment_id' => null,
                'contract_id' => $contractId,
                'user_id' => $users['operator'],
                'amount' => $plan['down'],
                'method' => 'manual',
                'status' => 'paid',
                'gateway_track_id' => null,
                'gateway_ref_id' => null,
                'description' => '[DEMO] پیش‌پرداخت نمونه؛ هیچ درگاهی فراخوانی نشده است.',
                'payment_date' => $startDate->format('Y-m-d'),
                'calculated_penalty' => 0,
                'calculated_reward' => 0,
                'principal_applied' => null,
                'normal_penalty_applied' => 0,
                'legal_penalty_applied' => 0,
                'reward_applied' => 0,
                'remaining_before_payment' => null,
                'remaining_after_payment' => null,
                'payment_type' => 'down_payment',
                'paid_at' => demo_date($startDate, '12:00:00'),
                'created_at' => demo_date($startDate, '12:00:00'),
            ]);
            $paymentTotal++;
        }

        for ($number = 1; $number <= (int) $plan['months']; $number++) {
            $dueDate = $firstDue->modify('+' . ($number - 1) . ' months');
            $baseAmount = (int) $plan['installment'];
            $paidAmount = in_array($number, $plan['paid'], true) ? $baseAmount : (int) ($plan['partial'][$number] ?? 0);
            $remaining = max(0, $baseAmount - $paidAmount);
            $status = $remaining <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : ($dueDate < $today ? 'overdue' : 'pending'));
            $paymentDate = $dueDate;
            if ($number === 3 && $plan['code'] === '003') $paymentDate = $dueDate->modify('+2 days');
            $installmentId = demo_insert($db, 'installments', [
                'contract_id' => $contractId,
                'installment_number' => $number,
                'due_date' => $dueDate->format('Y-m-d'),
                'base_amount' => $baseAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'last_payment_date' => $paidAmount > 0 ? $paymentDate->format('Y-m-d') : null,
                'effective_settlement_at' => $remaining === 0 ? demo_date($paymentDate) : null,
                'status' => $status,
                'notes' => '[DEMO] برنامه اقساط نمونه.',
                'internal_note' => '[DEMO] رکورد نمایشی؛ پرداخت واقعی ثبت نشده است.',
                'customer_visible' => 1,
                'created_at' => demo_date($startDate, '09:00:00'),
                'updated_at' => $nowSql,
            ]);
            $installments[$plan['code']][$number] = $installmentId;

            if ($paidAmount > 0) {
                $paymentId = demo_insert($db, 'payments', [
                    'installment_id' => $installmentId,
                    'contract_id' => $contractId,
                    'user_id' => $users['operator'],
                    'amount' => $paidAmount,
                    'method' => $number % 2 === 0 ? 'card_transfer' : 'manual',
                    'status' => 'paid',
                    'gateway_track_id' => null,
                    'gateway_ref_id' => null,
                    'description' => '[DEMO] پرداخت نمونه قسط ' . $number . '؛ تراکنش بانکی واقعی نیست.',
                    'payment_date' => $paymentDate->format('Y-m-d'),
                    'calculated_penalty' => 0,
                    'calculated_reward' => 0,
                    'principal_applied' => $paidAmount,
                    'normal_penalty_applied' => 0,
                    'legal_penalty_applied' => 0,
                    'reward_applied' => 0,
                    'remaining_before_payment' => $baseAmount,
                    'remaining_after_payment' => $remaining,
                    'payment_type' => 'installment',
                    'paid_at' => demo_date($paymentDate),
                    'created_at' => demo_date($paymentDate),
                ]);
                $paymentTotal++;
            }
        }
    }

    // One failed mock attempt is included only to demonstrate a status badge; it has no gateway reference.
    demo_insert($db, 'payments', [
        'installment_id' => $installments['005'][1],
        'contract_id' => $contracts['005'],
        'user_id' => $users['05'],
        'amount' => 7000000,
        'method' => 'manual',
        'status' => 'failed',
        'description' => '[DEMO] تلاش پرداخت ناموفق نمونه؛ درگاه واقعی فراخوانی نشده است.',
        'payment_type' => 'installment',
        'created_at' => demo_date($dateOffset(-1)),
    ]);
    $paymentTotal++;

    // Internal legal queue examples only. There is no complaint number, court date, or external filing.
    $legalCaseIds = [];
    foreach ([['002', '02'], ['007', '07']] as [$contractCode, $customerCode]) {
        $caseId = demo_insert($db, 'legal_cases', [
            'lawyer_id' => $users['lawyer'],
            'customer_id' => $users[$customerCode],
            'contract_id' => $contracts[$contractCode],
            'status' => 'open',
            'stage' => 'بررسی اولیه دمو - بدون اقدام قضایی واقعی',
            'notes' => '[DEMO] پرونده داخلی آزمایشی برای نمایش گردش کار؛ ارسال قانونی یا اقدام قضایی انجام نشده است.',
            'expense_amount' => 0,
            'created_at' => demo_date($dateOffset(-2)),
            'updated_at' => $nowSql,
        ]);
        $legalCaseIds[] = $caseId;
        demo_insert($db, 'legal_case_logs', [
            'contract_id' => $contracts[$contractCode],
            'legal_case_id' => $caseId,
            'action_stage' => 'demo_review',
            'action_title' => 'ثبت بررسی داخلی نمونه',
            'description' => '[DEMO] صرفاً داده نمایشی؛ هیچ مدرکی به مرجع قضایی ارسال نشده است.',
            'action_date' => $today->format('Y-m-d'),
            'registered_by' => $users['operator'],
            'assigned_lawyer_id' => $users['lawyer'],
            'next_status' => 'open',
            'cost_amount' => 0,
            'created_at' => $nowSql,
            'updated_at' => $nowSql,
        ]);
    }

    // Collection follow-ups and promises are internal, sample records; no outbound contact is made.
    $callRows = [
        ['002', '02', 5, 'عدم پاسخ', 'تماس نمونه؛ شماره تماس عمداً نامعتبر است.', -2, null],
        ['002', '02', 6, 'وعده پرداخت', 'وعده پرداخت نمونه برای نمایش پیگیری منقضی‌شده.', -1, -3],
        ['001', '01', 3, 'پیامک یادآوری ثبت شد', 'یادداشت آزمایشی؛ پیامکی ارسال نشده است.', 1, 2],
        ['003', '03', 3, 'پرداخت انجام شد', 'پرداخت نمونه ثبت‌شده در دمو.', null, null],
        ['006', '06', 2, 'وعده پرداخت', 'وعده پرداخت نمونه برای مانده قسط.', 2, 3],
        ['007', '07', 1, 'ارجاع داخلی به حقوقی', 'ثبت نمونه برای نمایش مسیر حقوقی؛ فاقد اثر واقعی.', 2, null],
        ['005', '05', 1, 'نیازمند پیگیری', 'نمونه پیگیری پیش از سررسید قسط.', 3, null],
    ];
    foreach ($callRows as [$contractCode, $customerCode, $installmentNumber, $result, $note, $nextDays, $promiseDays]) {
        demo_insert($db, 'operator_calls', [
            'operator_id' => $users['operator'],
            'customer_id' => $users[$customerCode],
            'contract_id' => $contracts[$contractCode],
            'installment_id' => $installments[$contractCode][$installmentNumber] ?? null,
            'call_result' => $result,
            'notes' => '[DEMO] ' . $note,
            'next_followup_date' => $nextDays === null ? null : $dateOffset($nextDays)->format('Y-m-d'),
            'promise_payment_date' => $promiseDays === null ? null : $dateOffset($promiseDays)->format('Y-m-d'),
            'created_at' => demo_date($dateOffset(-1)),
        ]);
    }

    // A short two-way conversation demonstrates chat without invoking a notification provider.
    $conversation = [
        ['01', 'operator', 'سلام، برای سررسید قسط بعدی راهنمایی می‌خواستم.'],
        ['operator', '01', 'سلام، قسط بعدی شما در بخش اقساط نمایش داده می‌شود. این پیام نمونه دمو است.'],
        ['01', 'operator', 'ممنون. امکان پرداخت کارت‌به‌کارت هم وجود دارد؟'],
        ['operator', '01', 'بله، روش پرداخت را از جزئیات همان قسط انتخاب کنید.'],
        ['01', 'operator', 'متوجه شدم، ممنون از راهنمایی.'],
        ['operator', '01', 'خواهش می‌کنم. این گفت‌وگو صرفاً برای ارائه سامانه ساخته شده است.'],
    ];
    foreach ($conversation as $index => [$sender, $recipient, $body]) {
        $senderId = $users[$sender];
        $recipientId = $users[$recipient];
        $messageDate = $dateOffset(-5 + $index);
        demo_insert($db, 'messages', [
            'sender_id' => $senderId,
            'receiver_id' => $recipientId,
            'channel_id' => null,
            'body' => $body,
            'is_read' => $index < 5 ? 1 : 0,
            'target_unit' => 'customer_service',
            'is_system' => 0,
            'read_at' => $index < 5 ? demo_date($messageDate, '12:05:00') : null,
            'created_at' => demo_date($messageDate, '12:00:00'),
        ]);
    }

    $notificationRows = [
        [$users['01'], 'یادآوری قسط نمونه', 'یک قسط آزمایشی برای نمایش وضعیت سررسید در حساب شما ثبت شده است.', 'installment', 'customer-installment'],
        [$users['01'], 'پرداخت ثبت شد', 'یک پرداخت نمونه در سابقه دمو نمایش داده می‌شود؛ تراکنش واقعی نیست.', 'payment', 'customer-payment'],
        [$users['operator'], 'پیگیری امروز', 'یک مورد پیگیری دمو برای بررسی صف وصول آماده است.', 'reminder', 'operator-followup'],
    ];
    foreach ($notificationRows as [$userId, $title, $body, $type, $suffix]) {
        demo_insert($db, 'notifications', [
            'user_id' => $userId,
            'title' => '[دمو] ' . $title,
            'body' => $body,
            'type' => $type,
            'url' => $type === 'installment' ? 'installments/panel' : ($type === 'payment' ? 'portal/history' : 'overdue'),
            'is_read' => 0,
            'dedupe_key' => 'demo-v702-' . $userId . '-' . $suffix,
            'created_at' => $nowSql,
        ]);
    }

    $eventRows = [
        ['یادآوری پیگیری قسط - دمو', 1, 'high'],
        ['جلسه بررسی قرارداد نمونه - دمو', 2, 'normal'],
        ['مرور پرونده داخلی دمو', 3, 'normal'],
    ];
    foreach ($eventRows as [$title, $days, $priority]) {
        demo_insert($db, 'events', [
            'user_id' => $users['operator'],
            'assigned_user_id' => $users['operator'],
            'created_by' => $users['operator'],
            'title' => $title,
            'event_date' => $dateOffset($days)->format('Y-m-d'),
            'event_time' => '10:30:00',
            'event_type' => 'general',
            'priority' => $priority,
            'status' => 'scheduled',
            'description' => '[DEMO] رویداد آزمایشی؛ جلسه یا پیگیری واقعی زمان‌بندی نشده است.',
            'color' => 'primary',
            'created_at' => $nowSql,
        ]);
    }

    // Affiliate demonstration: one qualified sample referral and one pending referral.
    $affiliateReferrer = $users['09'];
    $affiliateCode = 'DEMO1405';
    demo_insert($db, 'proma_affiliate_members', [
        'user_id' => $affiliateReferrer,
        'referral_code' => $affiliateCode,
        'reward_balance' => 100000,
        'purchase_credit' => 0,
        'lifetime_rewards' => 100000,
        'status' => 'active',
        'created_at' => $nowSql,
        'updated_at' => $nowSql,
    ]);
    $qualifiedReferral = demo_insert($db, 'proma_affiliate_referrals', [
        'referrer_user_id' => $affiliateReferrer,
        'referred_user_id' => $users['01'],
        'referral_code' => $affiliateCode,
        'status' => 'qualified',
        'reward_amount' => 100000,
        'created_at' => $nowSql,
    ]);
    demo_insert($db, 'proma_affiliate_referrals', [
        'referrer_user_id' => $affiliateReferrer,
        'referred_user_id' => $users['10'],
        'referral_code' => $affiliateCode,
        'status' => 'pending',
        'reward_amount' => 0,
        'created_at' => $nowSql,
    ]);
    demo_insert($db, 'proma_affiliate_transactions', [
        'member_user_id' => $affiliateReferrer,
        'referral_id' => $qualifiedReferral,
        'transaction_type' => 'referral_reward',
        'amount_toman' => 100000,
        'reward_balance_after' => 100000,
        'purchase_credit_after' => 0,
        'description' => '[DEMO] پاداش نمونه؛ قابل برداشت یا پرداخت واقعی نیست.',
        'idempotency_key' => 'demo-v702-affiliate-qualified-01',
        'created_by' => $users['operator'],
        'created_at' => $nowSql,
    ]);

    // Repair workflow examples. Device identifiers are intentionally invalid DEMO placeholders.
    demo_insert($db, 'proma_repair_technicians', [
        'user_id' => $users['technician'],
        'display_name' => 'سروش حیدری (دمو)',
        'specialty' => 'موبایل و تبلت',
        'phone' => '00000009003',
        'internal_code' => 'DEMO-TECH-01',
        'commission_model' => 'fixed',
        'commission_value' => 350000,
        'notes' => '[DEMO] حساب تعمیرکار نمونه.',
        'active' => 1,
        'role_active' => 1,
        'created_by' => $users['operator'],
        'created_at' => $nowSql,
    ]);
    $repairPlans = [
        ['01', 'received', 'روشن نشدن دستگاه پس از شارژ', 'Samsung', 'Galaxy A55', null, 0, 0, false],
        ['03', 'diagnosing', 'بررسی افت کیفیت تصویر و عملکرد باتری', 'Xiaomi', 'Redmi Note 13 Pro', 'بررسی اولیه نمایشگر و باتری؛ ادامه عیب‌یابی لازم است.', 0, 0, false],
        ['05', 'waiting_customer_approval', 'تعویض باتری و بررسی درگاه شارژ', 'Apple', 'iPhone 13', 'باتری نیازمند تعویض است؛ پیش از شروع هزینه باید تأیید شود.', 2400000, 0, false],
        ['06', 'repairing', 'تعویض سوکت شارژ', 'Samsung', 'Galaxy S23 FE', 'سوکت شارژ نیاز به تعویض دارد؛ هزینه نمونه تأیید شده است.', 1800000, 600000, false],
        ['08', 'ready_for_delivery', 'تعویض شیشه محافظ و تست نهایی', 'Xiaomi', 'Pad 6', 'تعمیر نمونه انجام شد و برای تحویل آماده است.', 3200000, 1600000, false],
        ['09', 'delivered', 'تعویض فلت شارژ و کنترل نهایی', 'Samsung', 'Galaxy A24', 'پرونده نمونه تحویل شده؛ ضمانت نمایشی ۹۰ روزه.', 1500000, 1500000, true],
    ];
    $repairPaths = [
        'received' => [['', 'received']],
        'diagnosing' => [['', 'received'], ['received', 'diagnosing']],
        'waiting_customer_approval' => [['', 'received'], ['received', 'diagnosing'], ['diagnosing', 'waiting_customer_approval']],
        'repairing' => [['', 'received'], ['received', 'diagnosing'], ['diagnosing', 'waiting_customer_approval'], ['waiting_customer_approval', 'approved'], ['approved', 'repairing']],
        'ready_for_delivery' => [['', 'received'], ['received', 'diagnosing'], ['diagnosing', 'waiting_customer_approval'], ['waiting_customer_approval', 'approved'], ['approved', 'repairing'], ['repairing', 'repair_completed'], ['repair_completed', 'quality_control'], ['quality_control', 'ready_for_delivery']],
        'delivered' => [['', 'received'], ['received', 'diagnosing'], ['diagnosing', 'waiting_customer_approval'], ['waiting_customer_approval', 'approved'], ['approved', 'repairing'], ['repairing', 'repair_completed'], ['repair_completed', 'quality_control'], ['quality_control', 'ready_for_delivery'], ['ready_for_delivery', 'delivered']],
    ];
    foreach ($repairPlans as $index => [$customerCode, $status, $report, $brand, $model, $diagnosis, $charge, $paid, $warranty]) {
        $repairNumber = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        $createdDate = $dateOffset(-max(1, 8 - $index));
        $repairId = demo_insert($db, 'proma_repairs', [
            'tracking_code' => 'DEMO-RPR-' . $repairNumber,
            'customer_id' => $users[$customerCode],
            'created_by' => $users['operator'],
            'status' => $status,
            'customer_report' => '[DEMO] ' . $report,
            'estimated_delivery_at' => in_array($status, ['repairing', 'ready_for_delivery'], true) ? demo_date($dateOffset(2)) : null,
            'delivered_at' => $status === 'delivered' ? demo_date($dateOffset(-1)) : null,
            'is_warranty_return' => 0,
            'created_at' => demo_date($createdDate, '09:30:00'),
            'updated_at' => $nowSql,
        ]);
        demo_insert($db, 'proma_repair_devices', [
            'repair_id' => $repairId,
            'device_type' => $brand === 'Apple' ? 'mobile' : ($brand === 'Xiaomi' && stripos($model, 'Pad') !== false ? 'tablet' : 'mobile'),
            'brand' => $brand,
            'model' => $model,
            'color' => 'مشکی',
            'imei' => 'DEMO-REPAIR-IMEI-' . $repairNumber,
            'serial_number' => 'DEMO-REPAIR-SN-' . $repairNumber,
            'storage_capacity' => '128GB',
            'ram_capacity' => '8GB',
            'accessories' => 'جعبه نمونه',
            'condition_flags' => 'بدون آسیب ظاهری ثبت‌شده (نمونه)',
            'condition_notes' => '[DEMO] اطلاعات دستگاه ساختگی است؛ رمز دستگاه ثبت نشده است.',
            'password_type' => null,
            'password_ciphertext' => null,
        ]);
        if ($status !== 'received') {
            demo_insert($db, 'proma_repair_assignments', [
                'repair_id' => $repairId,
                'technician_user_id' => $users['technician'],
                'assignment_role' => 'primary',
                'assigned_by' => $users['operator'],
                'assigned_at' => demo_date($createdDate, '10:00:00'),
            ]);
        }
        if ($diagnosis !== null) {
            demo_insert($db, 'proma_repair_diagnoses', [
                'repair_id' => $repairId,
                'technician_user_id' => $users['technician'],
                'diagnosis' => '[DEMO] ' . $diagnosis,
                'proposed_amount' => $charge,
                'estimated_hours' => $charge > 0 ? 2.5 : 1.0,
                'customer_decision' => in_array($status, ['repairing', 'ready_for_delivery', 'delivered'], true) ? 'approved' : null,
                'decision_at' => in_array($status, ['repairing', 'ready_for_delivery', 'delivered'], true) ? demo_date($dateOffset(-2)) : null,
                'created_at' => demo_date($createdDate, '10:30:00'),
            ]);
        }
        foreach ($repairPaths[$status] as $step => [$previous, $next]) {
            demo_insert($db, 'proma_repair_status_history', [
                'repair_id' => $repairId,
                'previous_status' => $previous !== '' ? $previous : null,
                'new_status' => $next,
                'user_id' => $users['operator'],
                'note' => '[DEMO] گردش کار نمونه.',
                'created_at' => demo_date($createdDate->modify('+' . $step . ' hours'), '10:00:00'),
            ]);
        }
        demo_insert($db, 'proma_repair_notes', [
            'repair_id' => $repairId,
            'user_id' => $users['operator'],
            'note' => '[DEMO] پرونده آزمایشی برای معرفی گردش کار تعمیرات.',
            'visible_to_customer' => 1,
            'created_at' => $nowSql,
        ]);
        if ($charge > 0) {
            demo_insert($db, 'proma_repair_charges', [
                'repair_id' => $repairId,
                'charge_type' => 'repair_service',
                'description' => '[DEMO] هزینه نمونه تعمیر',
                'amount' => $charge,
                'created_by' => $users['operator'],
                'created_at' => $nowSql,
            ]);
        }
        if ($paid > 0) {
            demo_insert($db, 'proma_repair_payments', [
                'repair_id' => $repairId,
                'payment_type' => $paid >= $charge ? 'final' : 'deposit',
                'amount' => $paid,
                'payment_method' => 'cash',
                'reference' => 'DEMO-REPAIR-PAY-' . $repairNumber,
                'note' => '[DEMO] پرداخت تعمیر ساختگی؛ تراکنش بانکی نیست.',
                'paid_at' => demo_date($dateOffset(-1)),
                'created_by' => $users['operator'],
                'created_at' => demo_date($dateOffset(-1)),
            ]);
        }
        if ($warranty) {
            demo_insert($db, 'proma_repair_warranties', [
                'repair_id' => $repairId,
                'enabled' => 1,
                'warranty_days' => 90,
                'starts_at' => $dateOffset(-1)->format('Y-m-d'),
                'ends_at' => $dateOffset(89)->format('Y-m-d'),
                'conditions' => '[DEMO] ضمانت نمایشی؛ تعهد واقعی ایجاد نمی‌کند.',
                'updated_by' => $users['operator'],
                'created_at' => $nowSql,
                'updated_at' => $nowSql,
            ]);
        }
    }

    // Persist explainable scores through the product's own read-only scoring rules.
    $riskProfiles = 0;
    $riskWarnings = [];
    if (class_exists('CreditRiskEngine')) {
        foreach (array_slice(array_values($users), 3) as $customerId) {
            try {
                CreditRiskEngine::profile((int) $customerId);
                $riskProfiles++;
            } catch (Throwable $e) {
                $riskWarnings[] = $e->getMessage();
            }
        }
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "Seed failed and all sample inserts were rolled back: " . $e->getMessage() . "\n");
    fwrite(STDERR, 'Backup kept at: ' . $backup['path'] . "\n");
    exit(1);
}

$result = [
    'customers' => demo_count($db, "SELECT COUNT(*) FROM users WHERE username LIKE 'demo.customer%'"),
    'staff' => demo_count($db, "SELECT COUNT(*) FROM users WHERE username IN ('demo.operator','demo.lawyer','demo.technician')"),
    'contracts' => demo_count($db, "SELECT COUNT(*) FROM contracts WHERE prefix='DEMO'"),
    'installments' => demo_count($db, "SELECT COUNT(*) FROM installments i JOIN contracts c ON c.id=i.contract_id WHERE c.prefix='DEMO'"),
    'payments' => demo_count($db, "SELECT COUNT(*) FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE c.prefix='DEMO'"),
    'legal_cases' => demo_count($db, "SELECT COUNT(*) FROM legal_cases WHERE notes LIKE '[DEMO]%'"),
    'followups' => demo_count($db, "SELECT COUNT(*) FROM operator_calls WHERE notes LIKE '[DEMO]%'"),
    'messages' => demo_count($db, "SELECT COUNT(*) FROM messages m JOIN users a ON a.id=m.sender_id LEFT JOIN users b ON b.id=m.receiver_id WHERE a.username LIKE 'demo.%' OR b.username LIKE 'demo.%'"),
    'repairs' => demo_count($db, "SELECT COUNT(*) FROM proma_repairs WHERE tracking_code LIKE 'DEMO-%'"),
    'affiliate_referrals' => demo_count($db, "SELECT COUNT(*) FROM proma_affiliate_referrals WHERE referral_code='DEMO1405'"),
    'risk_profiles' => $riskProfiles,
];
echo "Presentation dataset created successfully.\n";
echo 'Backup: ' . basename((string) $backup['path']) . "\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo "Demo login password: Demo@2026 (customers and demo staff)\n";
echo "Customer login: demo.customer01 | Operator: demo.operator | Lawyer: demo.lawyer | Technician: demo.technician\n";
echo "All demo accounts use non-routable phone placeholders; no gateway/provider call was made.\n";
if ($riskWarnings) echo 'Risk profile warnings: ' . count($riskWarnings) . "\n";
