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
$manifest = json_decode($read('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$package = json_decode($read('package.json'), true, 512, JSON_THROW_ON_ERROR);
$worker = $read('service-worker.js');
$layout = $read('views/layouts/app.php');
$contractView = $read('views/contracts/show.php');
$contractJs = $read('assets/js/app.js');
$navigationJs = $read('assets/js/v2-system.js');
$styles = $read('assets/css/components/v2-system.css');
$quill = $read('html/RTL/assets/js/editors/quill.js');
$quillCss = $read('html/RTL/assets/css/vendors/quill.snow.css');
$contractModel = $read('models/Contract.php');
$userModel = $read('models/User.php');
$documentModel = $read('models/ContractDocument.php');
$guarantorService = $read('models/ContractGuarantorService.php');
$integration = $read('tests/integration_guarantor_document_v159.php');

$assert(($version['application'] ?? '') === '2.0.2' && ($version['display'] ?? '') === 'V2.0.2', 'Core version metadata is inconsistent.');
$assert(($settings['asset_version'] ?? '') === '2.0.2' && ($manifest['version'] ?? '') === 'v2.0.2' && ($package['version'] ?? '') === '2.0.2', 'V2.0.2 asset or package cache metadata is inconsistent.');
$assert(strpos($worker, 'proma-pay-v2-0-2-') !== false, 'Service worker cache prefix was not invalidated.');
$assert(strpos($layout, 'proma-v2 proma-v2-role-') !== false && strpos($layout, 'data-navigation-toggle') !== false, 'Role-aware responsive shell is missing.');
$assert(strpos($layout, 'class="proma-nav-toggle" data-navigation-toggle aria-label="بستن منو"') !== false && strpos($layout, 'proma_icon(\'close\')') !== false && strpos($styles, 'body.proma-v2 .proma-nav-toggle { display: none; }') !== false, 'The desktop close-X regression returned.');
$assert(strpos($navigationJs, "querySelectorAll('[data-navigation-toggle]')") !== false, 'Responsive navigation toggle is not bound.');
$assert(strpos($contractView, 'data-contract-tabs-root') !== false && strpos($contractView, 'id="contract-installments" data-contract-tab-panel="installments"') !== false, 'Contract tab workspace or installment anchor is missing.');
$assert(strpos($contractView, 'id="contract-settlement" data-contract-tab-panel="settlement"') !== false && strpos($contractView, 'data-contract-tab-link="installments"') !== false, 'Contract quick-action links are not connected to their own tabs.');
$assert(strpos($contractJs, "querySelectorAll('[data-contract-tab-link]')") !== false && strpos($contractJs, 'panel.scrollIntoView') !== false, 'Contract quick-action tab navigation is not wired.');
$assert(strpos($contractView, 'data-contract-tab-panel="files" hidden') !== false && strpos($contractView, 'data-contract-tab-panel="payments" hidden') !== false, 'Contract panels are not isolated by tab.');
$assert(strpos($contractModel, 'ContractGuarantorService::customerIds') !== false && strpos($guarantorService, 'User::create') !== false, 'New guarantors are not linked to a customer account transactionally.');
$assert(strpos($guarantorService, 'FOR UPDATE') !== false && strpos($userModel, 'substr($mobile, -4)') !== false, 'Guarantor account resolution or customer password compatibility is missing.');
$assert(strpos($documentModel, 'to_english_digits($person[\'national_id\']') !== false && strpos($documentModel, 'to_english_digits($account[\'national_id\']') !== false, 'Guarantor documents do not deduplicate linked and historical snapshots.');
$assert(strpos($integration, 'GUARANTOR_ACCOUNT_V202_OK') !== false && strpos($integration, 'orphan customer') !== false, 'Guarantor account integration coverage is missing.');
$assert(strpos($quill, 'ql-white-space-normal') !== false && strpos($quill, 'style="white-space: normal;"') === false && strpos($quillCss, '.ql-editor.ql-white-space-normal') !== false, 'Quill uses CSP-blocked inline styles.');

echo "STATIC_V202_WORKSPACE_GUARANTORS_OK\n";
