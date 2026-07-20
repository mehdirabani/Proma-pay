<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.3.8', '>='), 'Core version must be at least 1.3.8.');
$assert((bool) preg_match('/^V\d+\.\d+\.\d+$/', (string) ($version['display'] ?? '')), 'Display version must use semantic Vx.y.z format.');

foreach ([
    'models/FileRecord.php',
    'database/migrations/2026_07_18_file_registry_v138.sql',
    'docs/qa/core-redesign/CURRENT_SOURCE_AND_UI_AUDIT.md',
    'docs/debug/CONTRACT_CANCELLATION_ROOT_CAUSE.md',
    'docs/qa/modal/MODAL_INVENTORY.md',
    'docs/files/CURRENT_FILE_STORAGE_AUDIT.md',
    'docs/gamification/MEDAL_MANAGEMENT_UI_AUDIT.md',
    'docs/releases/V1.3.8.md',
    'docs/qa/V1_3_8_TEST_RESULTS.md',
] as $file) {
    $assert(is_file($root . '/' . $file), 'Missing V1.3.8 file: ' . $file);
}

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_18_file_registry_v138.sql');
foreach (['CREATE TABLE IF NOT EXISTS files', 'CREATE TABLE IF NOT EXISTS file_relations', 'CREATE TABLE IF NOT EXISTS file_audit_logs', 'storage_path', 'file_uuid'] as $needle) {
    $assert(strpos($migration, $needle) !== false, 'File registry migration is missing ' . $needle . '.');
}
$assert(!preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $migration), 'File registry migration contains a destructive statement.');

$uploadHelper = (string) file_get_contents($root . '/helpers/UploadHelper.php');
$assert(strpos($uploadHelper, 'registerManagedFile') !== false, 'UploadHelper does not register uploaded files.');
$assert(strpos($uploadHelper, 'FileRecord::archiveByPath') !== false, 'UploadHelper does not preserve managed files during lifecycle archival.');

$ecommerce = (string) file_get_contents($root . '/models/Ecommerce.php');
$assert(strpos($ecommerce, 'relateProductImage') !== false && strpos($ecommerce, "'product_image'") !== false, 'Product image records are not linked to their product.');

$fileController = (string) file_get_contents($root . '/controllers/FileManagerController.php');
foreach (['storeManagedUpload', 'updateMetadata', 'replace(', 'softDelete', 'backfillStorage'] as $needle) {
    $assert(strpos($fileController, $needle) !== false, 'File manager controller is missing ' . $needle . '.');
}
$fileView = (string) file_get_contents($root . '/views/file-manager/index.php');
$assert(strpos($fileView, 'storage_path') === false, 'File manager view must not expose storage paths.');

$event = (string) file_get_contents($root . '/models/Event.php');
$calendar = (string) file_get_contents($root . '/controllers/CalendarController.php');
$assert(strpos($event, "e.event_type != 'installment'") !== false, 'Calendar visibility does not exclude installment events server-side.');
$assert(strpos($calendar, "Auth::role() === 'customer'") !== false, 'Only customers should receive automatic installment calendar events.');

$overdue = (string) file_get_contents($root . '/views/overdue/index.php');
$assert(strpos($overdue, 'normalize_iran_phone') !== false && strpos($overdue, 'data-copy-phone') !== false, 'Overdue contact action is incomplete.');

$contracts = (string) file_get_contents($root . '/views/contracts/index.php');
$assert(strpos($contracts, 'لغو قطعی قرارداد') !== false, 'Cancellation modal primary action is not explicit.');

$medals = (string) file_get_contents($root . '/views/medals/index.php');
$assert(strpos($medals, 'بازبینی و همگام‌سازی مدال‌ها') !== false && strpos($medals, 'proma-medal-grid') !== false, 'Medal management redesign is incomplete.');

$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$assert(strpos($appJs, 'initContactActions') !== false && strpos($appJs, 'fallbackCopy') !== false, 'Contact copy behavior is missing.');
$assert(strpos($appJs, "initCardLinks();\n    initContactActions();") !== false, 'Contact copy behavior is not initialized on first page load.');

echo "STATIC_V138_OK\n";
