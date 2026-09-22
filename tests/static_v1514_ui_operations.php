<?php

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($full)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$version = require $root . '/config/version.php';
$css = $read('assets/css/components/role-redesign.css');
$js = $read('assets/js/app.js');
$overdue = $read('views/overdue/index.php');
$legalCosts = $read('views/legal/costs.php');
$legalShow = $read('views/legal/show.php');
$legalIndex = $read('views/legal/index.php');
$legalController = $read('controllers/LegalController.php');
$contractShow = $read('views/contracts/show.php');
$fileManager = $read('views/file-manager/index.php');
$fileController = $read('controllers/FileManagerController.php');
$notifications = $read('views/notifications/index.php');
$notificationController = $read('controllers/NotificationsController.php');
$summary = $read('helpers/ContractFinancialSummaryService.php');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.5.14', '>='), 'version must retain V1.5.14 operations');
$assert(strpos($css, '.page-body .container-fluid') !== false && strpos($css, 'max-width: none;') !== false, 'global shell must not narrow all pages');
$assert(strpos($css, 'form:has(input, select)') === false, 'role redesign must not use broad form:has grid compression');
$assert(strpos($css, '.proma-medal-grid') !== false && strpos($css, 'writing-mode: horizontal-tb') !== false, 'medal card layout fix missing');
$assert(strpos($js, 'modalDelegatedBound') !== false, 'delegated modal open/close fallback missing');
$assert(strpos($js, "data-live-filter") !== false && strpos($js, 'load(true)') !== false, 'ajax filter fallback/navigation guard missing');
$assert(strpos($overdue, 'overdue-followup-') !== false && strpos($overdue, 'overdue-legal-') !== false, 'overdue follow-up/legal referral actions missing');
$assert(strpos($legalCosts, 'تأیید هزینه‌های حقوقی') !== false, 'legal cost approval page missing');
$assert(strpos($legalShow, 'legal-quick-update') !== false, 'legal quick update modal missing');
$assert(strpos($legalIndex, 'legal-quick-update-') !== false && strpos($legalIndex, 'legal/storeProgress/') !== false && strpos($legalIndex, 'legal/storeCost/') !== false && strpos($legalIndex, 'legal/storeAttachment/') !== false, 'legal list quick actions missing');
$assert(strpos($legalController, 'function storeProgress(') !== false, 'legal quick progress endpoint missing');
$assert(strpos($css, '.proma-quick-action-card--form') !== false, 'legal quick action forms are not normalized');
$assert(strpos($contractShow, 'proma-payment-log-table') !== false && strpos($contractShow, "\$payment['method'] ?? \$payment['payment_method']") !== false, 'contract customer payment log missing');
$assert(strpos($css, '.proma-contract-installments-card .table-wrap') !== false && strpos($css, '.page-body > .footer') !== false, 'contract overflow/footer containment missing');
$assert(strpos($fileManager, "file-manager/view/") !== false && strpos($fileController, 'function view($uuid)') !== false, 'file inline view action missing');
$assert(strpos($notifications, 'notifications-bulk-delete') !== false && strpos($notificationController, 'function bulkDelete()') !== false, 'bulk notification delete missing');
$assert(strpos($summary, 'projected_legal_penalty_available') !== false, 'projected legal penalty summary flag fix missing');

echo "static_v1514_ui_operations passed\n";
