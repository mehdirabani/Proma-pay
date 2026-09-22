<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException('Missing file: ' . $path);
    }
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$settings = require $root . '/config/settings.php';
$layout = $read('views/layouts/app.php');
$css = $read('assets/css/components/v2-system.css');
$js = $read('assets/js/v2-system.js');
$model = $read('models/PortalBanner.php');
$controller = $read('controllers/PortalBannersController.php');
$adminView = $read('views/settings/customer-banners.php');
$customerView = $read('views/dashboard/customer.php');
$bannerPartial = $read('views/dashboard/partials/customer-banners.php');
$dashboardController = $read('controllers/DashboardController.php');
$migration = $read('database/migrations/2026_09_21_customer_portal_banners_v200.sql');
$install = $read('database/proma-pay-install.sql');
$upload = $read('helpers/UploadHelper.php');

$assert(($version['application'] ?? '') === '2.0.0', 'Core release must be exactly 2.0.0.');
$assert(($version['display'] ?? '') === 'V2.0.0', 'Display version must be V2.0.0.');
$assert(($settings['asset_version'] ?? '') === '2.0.0', 'Asset version must invalidate all pre-V2 UI caches.');
$assert(strpos($layout, 'proma-v2 proma-v2-role-') !== false, 'V2 shell role class is not mounted.');
$assert(strpos($layout, 'assets/css/components/v2-system.css') !== false && strpos($layout, 'assets/js/v2-system.js') !== false, 'V2 product assets are not loaded.');
$assert(strpos($layout, 'proma-v2-mobile-nav') !== false, 'Responsive role navigation is missing.');
$assert(strpos($css, '--pp-primary-500') !== false && strpos($css, '--pp-sidebar') !== false, 'V2 design tokens are missing.');
$assert(strpos($css, '@media (max-width: 1399px)') !== false && strpos($css, '@media (max-width: 991px)') !== false && strpos($css, '@media (max-width: 767px)') !== false && strpos($css, '@media (max-width: 430px)') !== false, 'Required responsive breakpoints are incomplete.');
$assert(strpos($css, '.proma-medal-grid') !== false && strpos($css, '.proma-contract-card-grid') !== false && strpos($css, '.proma-settings-shell') !== false, 'Core business page redesign coverage is incomplete.');
$assert(strpos($js, 'enhanceTables') !== false && strpos($js, 'dataset.label') !== false, 'Mobile table-to-card enhancement is missing.');
$assert(strpos($js, 'modalSafetyNet') !== false, 'Shared modal close safety net is missing.');
$assert(strpos($model, 'CREATE TABLE') === false && strpos($model, 'activeForCustomer') !== false && strpos($model, 'LIMIT {$limit}') !== false, 'Portal banner model is unbounded or performs runtime schema operations.');
$assert(strpos($controller, "requireRole('admin')") !== false && substr_count($controller, '$this->onlyPost();') >= 3, 'Portal banner mutations are not protected by backend authorization and CSRF.');
$assert(strpos($controller, 'storePortalBannerImage') !== false && strpos($upload, 'function storePortalBannerImage') !== false, 'Secure dedicated banner upload path is missing.');
$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS `portal_banners`') !== false && strpos($install, 'CREATE TABLE IF NOT EXISTS `portal_banners`') !== false, 'Portal banner schema is missing from migration or clean install.');
$assert(strpos($adminView, 'تصویر موبایل') !== false && strpos($adminView, 'شروع نمایش') !== false && strpos($adminView, 'portal-banners/toggle/') !== false, 'Banner management UI lacks required edit controls.');
$assert(strpos($dashboardController, 'PortalBanner::activeForCustomer()') !== false && strpos($customerView, 'partials/customer-banners.php') !== false, 'Customer dashboard does not consume managed banners.');
$assert(strpos($bannerPartial, '<picture') !== false && strpos($bannerPartial, 'max-width: 767px') !== false && strpos($bannerPartial, 'noopener noreferrer') !== false, 'Responsive or safe banner delivery is incomplete.');

echo "STATIC_V200_UI_REDESIGN_OK\n";
