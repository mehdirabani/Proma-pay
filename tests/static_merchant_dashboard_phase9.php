<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) throw new RuntimeException('Unable to read merchant portal source: ' . $path);
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$controller = $read('controllers/MerchantPortalController.php');
$repository = $read('models/MerchantDashboardRepository.php');
$view = $read('views/merchant-portal/index.php');
$css = $read('assets/css/merchant-portal.css');
$assert(strpos($controller, 'Auth::requireLogin()') !== false
    && strpos($controller, 'MerchantDashboardRepository::membershipsForUser') !== false
    && strpos($controller, 'dashboardForUser') !== false,
    'Merchant dashboard route is not login- and membership-scoped.');
$assert(strpos($repository, 'mm.user_id = ? AND mm.merchant_id = ?') !== false
    && strpos($repository, "mm.status = 'active' AND m.status = 'active'") !== false
    && strpos($repository, "MerchantMembershipPolicy::allows") !== false,
    'Merchant report reads do not require an active, tenant-scoped membership.');
$assert(strpos($repository, 'gross_receivable_toman') !== false
    && strpos($repository, 'funding_account_id') !== false
    && strpos($repository, "'settlement.read'") !== false,
    'Settlement report is missing permission-aware funding and receivable fields.');
$assert(strpos($repository, 'last_error_code') !== false
    && strpos($repository, 'metadata_json') === false
    && strpos($repository, 'external_customer_reference') === false
    && strpos($repository, 'cheque_number') === false,
    'Merchant dashboard must not expose raw API payloads or customer/cheque identifiers.');
$assert(strpos($view, 'وجه نقد نیست') !== false
    && strpos($view, 'سررسید چک') !== false
    && strpos($view, 'مغایرت‌های مالی') !== false
    && strpos($view, 'خطاهای اتصال و رخدادهای API') !== false
    && strpos($view, 'htmlspecialchars') !== false,
    'Merchant report screens do not clearly label financial state or escape output.');
$assert(strpos($css, '.merchant-portal__cards') !== false, 'Merchant dashboard responsive styling is missing.');

echo "STATIC_MERCHANT_DASHBOARD_PHASE9_OK\n";
