<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$settings = require $root . '/config/settings.php';
$applicationVersion = (string) ($version['application'] ?? '');
$assert(version_compare($applicationVersion, '1.5.5', '>='), 'Core version must retain V1.5.5 capabilities.');
$assert(version_compare((string) ($version['application'] ?? '0'), '1.5.5', '>='), 'Display release must retain V1.5.5 capabilities.');
$assert(version_compare((string) ($settings['asset_version'] ?? '0'), '1.5.5', '>='), 'Asset version must retain V1.5.5 capabilities.');

$contractModel = (string) file_get_contents($root . '/models/Contract.php');
$installmentsController = (string) file_get_contents($root . '/controllers/InstallmentsController.php');
$overdueController = (string) file_get_contents($root . '/controllers/OverdueController.php');
$installmentsView = (string) file_get_contents($root . '/views/installments/index.php');
$overdueView = (string) file_get_contents($root . '/views/overdue/index.php');
$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$booklet = (string) file_get_contents($root . '/views/contracts/booklet.php');
$bookletCss = (string) file_get_contents($root . '/assets/css/components/booklet-print.css');
$bookletJs = (string) file_get_contents($root . '/assets/js/booklet-print.js');
$panels = (string) file_get_contents($root . '/assets/css/components/panels.css');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$medals = (string) file_get_contents($root . '/views/medals/index.php');
$serviceWorker = (string) file_get_contents($root . '/service-worker.js');

$assert(strpos($contractModel, 'contactDirectoryForContracts') !== false, 'Contact directory must be built in the Contract model.');
$assert(strpos($contractModel, 'contract_guarantor_people') !== false && strpos($contractModel, 'contract_guarantors') !== false, 'Both guarantor sources must be included.');
$assert(strpos($installmentsController, 'contactDirectoryForContracts') !== false && strpos($overdueController, 'contactDirectoryForContracts') !== false, 'Contact data must be loaded in both operational controllers.');
$assert(strpos($installmentsView, 'data-contact-directory') !== false && strpos($overdueView, 'data-contact-directory') !== false, 'Installment and overdue actions must open the contact directory.');
$assert(strpos($layout, 'id="contact-directory"') !== false, 'Shared contact modal is missing.');
$assert(strpos($appJs, 'initContactActions') !== false && strpos($appJs, 'navigator.clipboard') !== false, 'Contact modal call/copy actions are missing.');
$assert(strpos($panels, '.proma-filter-toolbar') !== false && strpos($panels, '.proma-contact-modal') !== false, 'Shared filter and contact styles are missing.');
$assert(strpos($panels, '.proma-medal-card') !== false && strpos($panels, 'height: auto !important') !== false, 'Medal cards must resist stretched grid rows.');
$assert(strpos($booklet, '<style') === false && strpos($booklet, 'onclick=') === false, 'Booklet must not rely on CSP-blocked inline code.');
$assert(strpos($booklet, 'booklet-print.css') !== false && strpos($booklet, 'booklet-print.js') !== false, 'Booklet external assets are missing.');
$assert(strpos($bookletCss, '@media print') !== false && strpos($bookletCss, '@page') !== false, 'Booklet print stylesheet is incomplete.');
$assert(strpos($bookletJs, 'window.print') !== false, 'Booklet print action is missing.');
$assert(strpos($medals, 'style="--medal-color') === false, 'Medal cards must not emit CSP-blocked inline variables.');
$assert(strpos($serviceWorker, 'proma-pay-v1-5-') !== false, 'Service-worker cache must retain the V1.5 cache namespace.');

echo "STATIC_V155_OK\n";
