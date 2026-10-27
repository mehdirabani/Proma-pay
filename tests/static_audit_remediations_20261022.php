<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) throw new RuntimeException('Missing file: ' . $path);
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$notifications = $read('views/notifications/index.php');
$legalCosts = $read('views/legal/costs.php');
$banners = $read('views/settings/customer-banners.php');
$notificationController = $read('controllers/NotificationsController.php');
$bannerModel = $read('models/PortalBanner.php');
$outboxWorker = $read('cron/outbox.php');
$pluginJs = $read('plugins/PromaPartnershipLedger/assets/js/inventory-partners-v111.js');
$qaRouter = $read('tests/qa_server_router_v150.php');
$historySeed = $read('tests/qa_seed_customer_history_v152.php');
$accountingProvider = $read('plugins/PromaAccounting/src/AccountingServiceProvider.php');

$assert(strpos($notifications, 'name="expires_at_date" data-jalali-input') !== false
    && strpos($notifications, 'name="expires_at_time" type="time"') !== false
    && strpos($notifications, 'name="expires_at" type="datetime-local"') === false,
    'Broadcast expiration must use the shared Jalali date picker and a separate time field.');
$assert(strpos($legalCosts, 'name="paid_at" data-jalali-input') !== false
    && strpos($legalCosts, 'name="paid_at" type="date"') === false,
    'Legal third-party payment date must use the shared Jalali date picker.');
$assert(strpos($banners, 'name="starts_at_date" data-jalali-input') !== false
    && strpos($banners, 'name="ends_at_date" data-jalali-input') !== false
    && strpos($banners, 'type="datetime-local"') === false,
    'Portal banner dates must use the shared Jalali picker.');
$assert(strpos($notificationController, 'parse_jalali_date($rawDate)') !== false
    && strpos($bannerModel, 'normalizeDateTimeInput') !== false,
    'Jalali date fields must be normalized before storage.');
$assert(strpos($outboxWorker, "if (PHP_SAPI !== 'cli')") !== false
    && strpos($outboxWorker, 'SystemOutbox::processPending($limit)') !== false,
    'Outbox CLI worker is missing its CLI-only guard or bounded processor.');
$assert(strpos($pluginJs, 'لطفاً این فیلد را تکمیل کنید.') !== false,
    'Partnership Ledger validation feedback must be Persian.');
$assert(strpos($qaRouter, 'PROMA_TEST_DB_DSN is required') !== false
    && strpos($qaRouter, 'port=33311') === false,
    'QA HTTP router must require an isolated configured database.');
$assert(strpos($historySeed, 'qa_seed_roles_v150.php') !== false,
    'Customer history QA seed must prepare its role/accounting prerequisites.');
$assert(strpos($accountingProvider, 'Services\\SalesService::recordFromContract') !== false
    && strpos($accountingProvider, 'Services\\CommissionService::refreshForContract') !== false,
    'Accounting payment/contract hooks must reference their actual Services namespace.');

echo "STATIC_AUDIT_REMEDIATIONS_OK\n";
