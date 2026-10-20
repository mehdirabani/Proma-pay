<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.60';
require dirname(__DIR__) . '/bootstrap.php';
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setValue(null, $pdo);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 12);
$createUser = static function (string $role, string $name) use ($suffix): int {
    $nationalId = (string) random_int(1000000000, 9999999999);
    $mobile = '09' . substr((string) random_int(1000000000, 9999999999), 0, 9);
    return User::create([
        'role' => $role,
        'username' => 'qa-v600-' . $role . '-' . $suffix,
        'full_name' => $name,
        'national_id' => $nationalId,
        'mobile' => $mobile,
        'email' => 'qa-v600-' . $role . '-' . $suffix . '@example.test',
        'password' => $role === 'customer' ? substr($mobile, -4) : 'QA#V600-' . $suffix,
        'status' => 'active',
    ]);
};
$adminId = $createUser('admin', 'مدیر آزمون V6');
$customerId = $createUser('customer', 'مشتری آزمون V6');

$announcementId = SystemAnnouncement::create('اعلان آزمون V6', 'پیام اختصاصی نقش مشتری', ['customer'], 'dashboard', $adminId);
$recipients = Model::fetchAll('SELECT r.user_id,u.role FROM system_announcement_recipients r JOIN users u ON u.id=r.user_id WHERE r.announcement_id = ?', [$announcementId]);
$recipientIds = array_map('intval', array_column($recipients, 'user_id'));
$assert(in_array($customerId, $recipientIds, true) && !in_array($adminId, $recipientIds, true) && count(array_filter($recipients, static fn (array $row): bool => ($row['role'] ?? '') !== 'customer')) === 0, 'Role-targeted global announcement recipient selection is incorrect.');
$announcementNotificationId = SystemAnnouncement::NOTIFICATION_ID_OFFSET + $announcementId;
$assert(Notification::findForUser($announcementNotificationId, $customerId) !== null, 'Recipient could not read the global announcement in the notification inbox.');
$assert(Notification::markRead($announcementNotificationId, $customerId) > 0, 'Announcement read state was not saved for the recipient.');
$assert(SystemAnnouncement::unreadCount($customerId) === 0, 'Announcement unread count did not update after it was read.');

$startDate = date('Y-m-d', strtotime('-40 days'));
$dueDate = date('Y-m-d', strtotime('-35 days'));
$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 100000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 1,
    'start_date' => $startDate,
    'first_due_date' => $dueDate,
    'created_by' => $adminId,
]);
Model::execute("UPDATE installments SET base_amount = 100000, paid_amount = 0, remaining_amount = 100000, due_date = ?, status = 'pending' WHERE contract_id = ?", [$dueDate, $contractId]);

$profile = CreditRiskEngine::profile($customerId);
$assert((int) ($profile['score'] ?? 0) >= 100 && (int) ($profile['score'] ?? 0) <= 1000, 'Proma Score is outside its documented range.');
$assert(!empty($profile['reasons']) && !empty($profile['advisory_only']) && array_key_exists('credit_limit_toman', $profile['recommendation'] ?? []), 'Risk profile is missing explainable evidence or an advisory recommendation.');
$assert(($profile['metrics']['overdue_debt_toman'] ?? 0) > 0, 'Risk profile did not include the customer’s overdue debt.');

CollectionCommandCenter::runStageAutomation($adminId);
$tasks = Model::fetchAll('SELECT * FROM collection_tasks WHERE contract_id = ? AND status = \'open\'', [$contractId]);
$assert(count($tasks) === 1 && ($tasks[0]['stage'] ?? '') === 'legal_review', 'Overdue contract did not create its legal-review collection task.');
CollectionCommandCenter::runStageAutomation($adminId);
$taskCount = Model::fetch('SELECT COUNT(*) AS total FROM collection_tasks WHERE contract_id = ?', [$contractId]);
$assert((int) ($taskCount['total'] ?? 0) === 1, 'Repeated stage automation created a duplicate collection task.');

echo "INTEGRATION_V600_FEATURES_OK\n";
