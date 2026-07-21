<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.141';

require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$initialAccountingStatus = (string) ((PluginRegistry::find('proma-accounting')['status'] ?? ''));

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$suffix = substr((string) hrtime(true), -7);
$createUser = static function ($role, $prefix) use ($suffix) {
    $mobile = '09' . substr(hash('sha256', $prefix . $suffix), 0, 9);
    $mobile = preg_replace('/[^0-9]/', '1', $mobile);
    $nationalId = substr(preg_replace('/[^0-9]/', '2', hash('sha256', $suffix . $prefix)), 0, 10);
    return [
        User::create([
            'role' => $role,
            'username' => $role === 'customer' ? $nationalId : 'qa-' . $prefix . '-' . $suffix,
            'full_name' => 'کاربر آزمون ' . $prefix,
            'national_id' => $nationalId,
            'mobile' => $mobile,
            'email' => 'qa-' . $prefix . '-' . $suffix . '@example.test',
            'password' => $role === 'customer' ? substr($mobile, -4) : 'Admin#V141',
            'status' => 'active',
        ]),
        $mobile,
        $nationalId,
    ];
};

[$adminId] = $createUser('admin', 'admin');
[$operatorId] = $createUser('operator', 'operator');
[$lawyerId] = $createUser('lawyer', 'lawyer');
[$customerId, $customerMobile, $customerNationalId] = $createUser('customer', 'customer');

$assert(Auth::unifiedLogin($customerNationalId, substr($customerMobile, -4)), 'Customer last-four mobile login policy regressed.');
$assert((int) Auth::id() === (int) $customerId, 'Customer login resolved another account.');
$_SESSION = [];

$profileRequestId = ProfileRequest::createRequest($customerId, [
    'full_name' => 'نام تایید نشده ' . $suffix,
    'email' => 'partial-' . $suffix . '@example.test',
]);
$profileResult = ProfileRequest::approveFields($profileRequestId, $adminId, ['email'], 'فقط ایمیل تایید شد.');
$profileUser = User::find($customerId);
$assert(($profileResult['status'] ?? '') === 'partial', 'Profile request did not enter partial state.');
$assert(($profileUser['email'] ?? '') === 'partial-' . $suffix . '@example.test', 'Approved profile field was not applied.');
$assert(($profileUser['full_name'] ?? '') !== 'نام تایید نشده ' . $suffix, 'Rejected profile field was applied.');

$newCustomerMobile = '0935' . str_pad(substr($suffix, -7), 7, '0', STR_PAD_LEFT);
$mobileRequestId = ProfileRequest::createRequest($customerId, ['mobile' => $newCustomerMobile]);
$mobileApproval = ProfileRequest::approveFields($mobileRequestId, $adminId, ['mobile'], 'شماره موبایل تایید شد.');
$assert(($mobileApproval['status'] ?? '') === 'approved', 'Customer mobile profile change was not approved.');
$assert(Auth::customerLogin($customerNationalId, substr($newCustomerMobile, -4)), 'Customer could not log in with the last four digits of the approved mobile.');
$_SESSION = [];

$avatarFixture = dirname(__DIR__) . '/assets/images/avatars/avatar-2.png';
$avatarDirectory = dirname(__DIR__) . '/storage/secure_uploads/avatars/' . $customerId . '/qa';
if (!is_dir($avatarDirectory)) {
    mkdir($avatarDirectory, 0755, true);
}
$avatarPath = 'storage/secure_uploads/avatars/' . $customerId . '/qa/avatar-' . $suffix . '.png';
$avatarAbsolutePath = dirname(__DIR__) . '/' . $avatarPath;
copy($avatarFixture, $avatarAbsolutePath);
$avatarValidation = UploadHelper::validateAvatarImage($avatarAbsolutePath, filesize($avatarAbsolutePath));
$assert(($avatarValidation['width'] ?? 0) === 390 && ($avatarValidation['mime'] ?? '') === 'image/png', 'Avatar MIME or dimension validation failed.');
FileRecord::registerStored($avatarPath, ['name' => 'qa-avatar.png'], [
    'category' => 'avatar',
    'visibility' => 'private',
    'uploader_user_id' => $customerId,
    'uploader_role' => 'customer',
    'related_entity_type' => 'user',
    'related_entity_id' => $customerId,
    'relation_type' => 'avatar',
]);
User::updateUploadedAvatar($customerId, $avatarPath);
$uploadedAvatarUser = User::find($customerId);
$assert(($uploadedAvatarUser['avatar_path'] ?? '') === $avatarPath && (int) ($uploadedAvatarUser['avatar_version'] ?? 0) > 0, 'Uploaded avatar was not applied immediately.');
$assert(strpos(user_avatar_asset_url($uploadedAvatarUser), 'profile%2FavatarFile%2F' . $customerId) !== false, 'Uploaded avatar URL does not use the protected avatar route.');
User::removeUploadedAvatar($customerId);
$removedAvatarUser = User::find($customerId);
$assert(empty($removedAvatarUser['avatar_path']), 'Uploaded avatar was not removed.');
$archivedAvatarRecord = Model::fetch('SELECT status FROM files WHERE storage_path = ? LIMIT 1', [$avatarPath]);
$assert(($archivedAvatarRecord['status'] ?? '') === 'archived', 'Removed avatar was not archived in the central file registry.');
if (is_file($avatarAbsolutePath)) {
    unlink($avatarAbsolutePath);
}

$notificationId = Notification::create($customerId, 'اعلان آزمون', 'متن اعلان', 'qa', url('profile'), 'qa-v141-' . $suffix);
$assert(Notification::unreadCount($customerId) >= 1, 'Unread notification badge did not increase.');
Notification::markRead($notificationId, $customerId, true);
$notification = Notification::findForUser($notificationId, $customerId);
$assert((int) ($notification['is_read'] ?? 0) === 1 && !empty($notification['actioned_at']), 'Notification click state was not persisted.');
Notification::archive($notificationId, $customerId);
$assert(!Notification::findForUser($notificationId, $operatorId), 'Notification isolation failed.');

$contractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 1200000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 1,
    'start_date' => date('Y-m-d'),
    'first_due_date' => date('Y-m-d'),
    'assigned_operator_id' => $operatorId,
    'created_by' => $adminId,
]);
$installment = Model::fetch('SELECT * FROM installments WHERE contract_id = ? LIMIT 1', [$contractId]);
$assert($installment, 'Contract installment was not created.');
Payment::record((int) $installment['id'], $contractId, $adminId, (float) $installment['base_amount'], 'manual', 'paid', null, null, 'QA settlement', date('Y-m-d'));
$settled = Installment::find((int) $installment['id']);
$assert(($settled['status'] ?? '') === 'paid', 'Fully paid installment is not paid.');
$assert((float) ($settled['remaining_amount'] ?? -1) === 0.0, 'Fully paid installment has remaining balance.');
$assert((float) ($settled['payable'] ?? -1) === 0.0 && empty($settled['payment_allowed']), 'Fully paid installment remains payable.');
$assert((float) ($settled['penalty'] ?? -1) === 0.0, 'Penalty continued after settlement.');
$duplicatePaymentRejected = false;
try {
    Payment::record((int) $installment['id'], $contractId, $adminId, 1000, 'manual', 'paid', null, null, 'Duplicate QA payment', date('Y-m-d'));
} catch (InvalidArgumentException $e) {
    $duplicatePaymentRejected = $e->getCode() === 409 && $e->getMessage() === InstallmentSettlementService::SETTLED_MESSAGE;
}
$assert($duplicatePaymentRejected, 'Duplicate payment was not rejected with the settled-installment contract.');
$assert((Contract::find($contractId)['status'] ?? '') === 'completed', 'Contract did not automatically complete after its last installment.');

$repairContractId = Contract::createWithInstallments([
    'customer_id' => $customerId,
    'principal_amount' => 900000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 1,
    'start_date' => date('Y-m-d'),
    'first_due_date' => date('Y-m-d'),
    'created_by' => $adminId,
]);
$repairInstallment = Model::fetch('SELECT * FROM installments WHERE contract_id = ? LIMIT 1', [$repairContractId]);
Model::execute('UPDATE installments SET paid_amount = base_amount, remaining_amount = base_amount, status = ? WHERE id = ?', ['pending', (int) $repairInstallment['id']]);
$issues = array_values(array_filter(InstallmentReconciliationService::scan(), static function ($issue) use ($repairInstallment) {
    return (int) ($issue['id'] ?? 0) === (int) $repairInstallment['id'];
}));
$assert(count($issues) === 1, 'Dry-run reconciliation did not detect a mismatched installment.');
InstallmentReconciliationService::apply($issues);
$repaired = Model::fetch('SELECT paid_amount, remaining_amount, status FROM installments WHERE id = ?', [(int) $repairInstallment['id']]);
$assert((float) $repaired['paid_amount'] === 0.0 && (float) $repaired['remaining_amount'] === (float) $repairInstallment['base_amount'], 'Reconciliation did not derive state from effective payments.');

$duplicatePayload = [
    'customer_id' => $customerId,
    'principal_amount' => 720000,
    'down_payment_amount' => 0,
    'monthly_interest_rate' => 0,
    'interest_type' => 'simple',
    'months' => 2,
    'start_date' => date('Y-m-d'),
    'first_due_date' => FinanceHelper::addMonths(date('Y-m-d'), 1),
    'assigned_operator_id' => $operatorId,
    'notes' => 'QA duplicate repair ' . $suffix,
    'created_by' => $adminId,
];
$canonicalDuplicateId = Contract::createWithInstallments($duplicatePayload);
$archivableDuplicateId = Contract::createWithInstallments($duplicatePayload);
$duplicateGroups = ContractDuplicateRepairService::scan(100, $customerId);
$duplicateGroup = null;
foreach ($duplicateGroups as $candidate) {
    $candidateIds = array_map('intval', array_column($candidate['duplicates'] ?? [], 'id'));
    if ((int) ($candidate['canonical']['id'] ?? 0) === $canonicalDuplicateId && in_array($archivableDuplicateId, $candidateIds, true)) {
        $duplicateGroup = $candidate;
        break;
    }
}
$assert($duplicateGroup && !empty($duplicateGroup['auto_repairable']), 'Duplicate contract dry-run did not identify a safe repair group.');
$archivedDuplicates = ContractDuplicateRepairService::archiveDuplicates($canonicalDuplicateId, [$archivableDuplicateId], $adminId);
$assert($archivedDuplicates === 1, 'Duplicate repair did not archive exactly one contract.');
$assert((Contract::find($canonicalDuplicateId)['status'] ?? '') === 'active', 'Duplicate repair changed the canonical contract.');
$assert((Contract::find($archivableDuplicateId)['status'] ?? '') === 'cancelled', 'Duplicate contract was not archived through cancellation.');
$activeDuplicateInstallments = (int) (Model::fetch("SELECT COUNT(*) AS total FROM installments WHERE contract_id = ? AND status != 'cancelled'", [$archivableDuplicateId])['total'] ?? 0);
$assert($activeDuplicateInstallments === 0, 'Archived duplicate still has active installments.');
$repairAuditCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM contract_duplicate_repairs WHERE canonical_contract_id = ? AND duplicate_contract_id = ?', [$canonicalDuplicateId, $archivableDuplicateId])['total'] ?? 0);
$assert($repairAuditCount === 1, 'Duplicate repair audit record is missing.');

$protectedPayload = $duplicatePayload;
$protectedPayload['principal_amount'] = 660000;
$protectedPayload['notes'] = 'QA protected duplicate ' . $suffix;
$emptyProtectedId = Contract::createWithInstallments($protectedPayload);
$paidProtectedId = Contract::createWithInstallments($protectedPayload);
$paidProtectedInstallment = Model::fetch('SELECT * FROM installments WHERE contract_id = ? ORDER BY installment_number LIMIT 1', [$paidProtectedId]);
Payment::record((int) $paidProtectedInstallment['id'], $paidProtectedId, $adminId, 1000, 'manual', 'paid', null, null, 'Protected duplicate payment', date('Y-m-d'));
$paymentCountBeforeRejectedRepair = (int) (Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id = ?', [$paidProtectedId])['total'] ?? 0);
$paidDuplicateRejected = false;
try {
    ContractDuplicateRepairService::archiveDuplicates($emptyProtectedId, [$paidProtectedId], $adminId);
} catch (InvalidArgumentException $e) {
    $paidDuplicateRejected = strpos($e->getMessage(), 'پرداخت واقعی') !== false;
}
$paymentCountAfterRejectedRepair = (int) (Model::fetch('SELECT COUNT(*) AS total FROM payments WHERE contract_id = ?', [$paidProtectedId])['total'] ?? 0);
$assert($paidDuplicateRejected, 'Duplicate repair accepted a contract with a real payment.');
$assert($paymentCountBeforeRejectedRepair === $paymentCountAfterRejectedRepair && $paymentCountAfterRejectedRepair > 0, 'Rejected duplicate repair changed real payment records.');
$assert((Contract::find($paidProtectedId)['status'] ?? '') !== 'cancelled', 'Rejected duplicate repair cancelled the protected contract.');

$calendarEventTitle = 'جلسه آزمون تقویم ' . $suffix;
$calendarEventDate = date('Y-m-d', strtotime('+1 day'));
Event::createEvent([
    'assigned_user_id' => $operatorId,
    'created_by' => $adminId,
    'title' => $calendarEventTitle,
    'event_date' => $calendarEventDate,
    'event_time' => '09:00',
    'event_type' => 'meeting',
    'priority' => 'high',
    'reminder_type' => '1_day',
]);
$calendarEvent = Model::fetch('SELECT * FROM events WHERE title = ? ORDER BY id DESC LIMIT 1', [$calendarEventTitle]);
$assert($calendarEvent && (int) $calendarEvent['created_by'] === $adminId && (int) $calendarEvent['assigned_user_id'] === $operatorId, 'Calendar event ownership was not persisted.');
Model::execute(
    "INSERT INTO events (user_id, assigned_user_id, created_by, title, event_date, event_time, event_type, priority, status, description, color, reminder_type, created_at) VALUES (?, ?, ?, ?, ?, ?, 'installment', 'normal', 'scheduled', '', 'warning', '1_day', NOW())",
    [$customerId, $customerId, $adminId, 'رویداد قسط تکراری ' . $suffix, $calendarEventDate, '09:00:00']
);
$visibleCalendar = Event::allVisibleBetween(date('Y-m-d'), date('Y-m-d', strtotime('+2 days')), ['id' => $adminId, 'role' => 'admin']);
$visibleCalendarTitles = array_column($visibleCalendar, 'title');
$assert(in_array($calendarEventTitle, $visibleCalendarTitles, true), 'Admin calendar cannot see the explicit event.');
$assert(!in_array('رویداد قسط تکراری ' . $suffix, $visibleCalendarTitles, true), 'Stored installment event duplicated the generated installment calendar.');
$reminderNow = date('Y-m-d') . ' 10:00:00';
$firstReminderRun = Event::processDueReminders($reminderNow);
$secondReminderRun = Event::processDueReminders($reminderNow);
$assert((int) ($firstReminderRun['reminder_sent'] ?? 0) === 2, 'Calendar reminder was not delivered once to creator and assignee.');
$assert((int) ($secondReminderRun['reminder_sent'] ?? -1) === 0, 'Calendar reminder was sent again on the second scheduler run.');
foreach ([$adminId, $operatorId] as $recipientId) {
    $calendarNotificationCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND dedupe_key = ?', [$recipientId, 'calendar-reminder-' . (int) $calendarEvent['id']])['total'] ?? 0);
    $assert($calendarNotificationCount === 1, 'Calendar notification deduplication failed for user ' . $recipientId . '.');
}

$noOverdueDefinition = Model::fetch("SELECT * FROM medal_definitions WHERE slug = 'no-overdue' LIMIT 1");
$assert($noOverdueDefinition && ($noOverdueDefinition['behavior_type'] ?? '') === 'reversible', 'No-overdue medal is not configured as reversible.');
$existingNoOverdueMedals = Model::fetchAll('SELECT id FROM user_medals WHERE user_id = ? AND medal_definition_id = ?', [$customerId, (int) $noOverdueDefinition['id']]);
foreach ($existingNoOverdueMedals as $existingNoOverdueMedal) {
    Model::execute('DELETE FROM user_medal_history WHERE user_medal_id = ?', [(int) $existingNoOverdueMedal['id']]);
}
Model::execute('DELETE FROM user_medals WHERE user_id = ? AND medal_definition_id = ?', [$customerId, (int) $noOverdueDefinition['id']]);
Model::execute('UPDATE installments SET due_date = ?, status = ?, paid_amount = 0, remaining_amount = base_amount WHERE id = ?', [date('Y-m-d'), 'pending', (int) $repairInstallment['id']]);
$medalAwardResult = MedalReconciliationService::reconcileCustomer($customerId, $adminId);
$activeNoOverdueMedal = Model::fetch('SELECT * FROM user_medals WHERE user_id = ? AND medal_definition_id = ? LIMIT 1', [$customerId, (int) $noOverdueDefinition['id']]);
$assert((int) ($medalAwardResult['awarded'] ?? 0) >= 1 && $activeNoOverdueMedal && empty($activeNoOverdueMedal['revoked_at']), 'Reversible medal was not awarded when criteria matched.');
$noOverdueMedalId = (int) $activeNoOverdueMedal['id'];
Model::execute('UPDATE installments SET due_date = ?, status = ?, remaining_amount = base_amount WHERE id = ?', [date('Y-m-d', strtotime('-2 days')), 'overdue', (int) $repairInstallment['id']]);
$medalRevokeResult = MedalReconciliationService::reconcileCustomer($customerId, $adminId);
$revokedNoOverdueMedal = Model::fetch('SELECT * FROM user_medals WHERE id = ?', [$noOverdueMedalId]);
$assert((int) ($medalRevokeResult['revoked'] ?? 0) === 1 && !empty($revokedNoOverdueMedal['revoked_at']), 'Reversible medal was not revoked after an overdue installment appeared.');
Model::execute('UPDATE installments SET status = ?, paid_amount = base_amount, remaining_amount = 0 WHERE id = ?', ['paid', (int) $repairInstallment['id']]);
$medalRestoreResult = MedalReconciliationService::reconcileCustomer($customerId, $adminId);
$restoredNoOverdueMedal = Model::fetch('SELECT * FROM user_medals WHERE id = ?', [$noOverdueMedalId]);
$noOverdueMedalCount = (int) (Model::fetch('SELECT COUNT(*) AS total FROM user_medals WHERE user_id = ? AND medal_definition_id = ?', [$customerId, (int) $noOverdueDefinition['id']])['total'] ?? 0);
$medalHistoryActions = array_column(Model::fetchAll('SELECT action FROM user_medal_history WHERE user_medal_id = ? ORDER BY id', [$noOverdueMedalId]), 'action');
$assert((int) ($medalRestoreResult['restored'] ?? 0) === 1 && empty($restoredNoOverdueMedal['revoked_at']), 'Reversible medal was not restored after criteria matched again.');
$assert($noOverdueMedalCount === 1, 'Medal reconciliation created duplicate reversible medals.');
$assert($medalHistoryActions === ['awarded', 'revoked', 'restored'], 'Medal history does not preserve the complete reversible lifecycle.');

Chat::ensureSchema();
$channel = Chat::channelBySlug('public-announcements');
foreach ([$adminId, $operatorId, $lawyerId, $customerId] as $userId) {
    Chat::markChannelRead($userId, (int) $channel['id']);
}
Chat::sendToChannel($adminId, (int) $channel['id'], 'اعلان کانال QA ' . $suffix);
$assert(Chat::unreadCount($adminId) === 0, 'Channel sender received an unread badge for their own message.');
foreach ([$operatorId, $lawyerId, $customerId] as $userId) {
    $assert(Chat::unreadCount($userId) >= 1, 'Channel unread badge is missing for role user ' . $userId . '.');
    Chat::markChannelRead($userId, (int) $channel['id']);
}

Model::execute('DELETE FROM system_plugin_migrations WHERE plugin_id = ?', ['proma-accounting']);
Model::execute('DELETE FROM system_plugin_role_permissions WHERE plugin_id = ?', ['proma-accounting']);
Model::execute('DELETE FROM system_plugin_permissions WHERE plugin_id = ?', ['proma-accounting']);
Model::execute('DELETE FROM system_plugins WHERE plugin_id = ?', ['proma-accounting']);
$manager = PluginManager::instance();
$manager->rescan($adminId);
$manager->install('proma-accounting', $adminId);
$assert((PluginRegistry::find('proma-accounting')['status'] ?? '') === PluginStatus::INSTALLED, 'Accounting plugin install failed.');
$manager->activate('proma-accounting', $adminId);
$assert((PluginRegistry::find('proma-accounting')['status'] ?? '') === PluginStatus::ACTIVE, 'Accounting plugin activation failed.');
$assert(!empty($manager->healthCheck('proma-accounting')['ok']), 'Accounting plugin health check failed.');
$manager->deactivate('proma-accounting', $adminId);
$assert((PluginRegistry::find('proma-accounting')['status'] ?? '') === PluginStatus::INACTIVE, 'Accounting plugin deactivation failed.');
if ($initialAccountingStatus === PluginStatus::ACTIVE) {
    $manager->activate('proma-accounting', $adminId);
    $assert((PluginRegistry::find('proma-accounting')['status'] ?? '') === PluginStatus::ACTIVE, 'Accounting plugin status was not restored after the integration test.');
}

echo "INTEGRATION_V141_RELEASE_BLOCKERS_OK\n";
