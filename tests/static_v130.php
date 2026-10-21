<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/ContractTemplateRenderer.php';
require_once $root . '/models/ContractPrintProfile.php';
require_once $root . '/models/ContractDocument.php';
require_once $root . '/models/ContractTemplateService.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.3.0', '>='), 'Application version must be V1.3.0 or newer.');

$plain = "ماده ۱ - عنوان\n\nمتن اول\nخط کنترل‌شده\n\n\n\n- مورد اول\n- مورد دوم";
$rendered = ContractTemplateRenderer::render($plain, ContractTemplateRenderer::FORMAT_PLAIN);
$assert(strpos($rendered, '<h2 class="contract-section-title">') !== false, 'Plain heading rendering failed.');
$assert(strpos($rendered, '<p class="contract-paragraph">') !== false, 'Plain paragraph rendering failed.');
$assert(strpos($rendered, '<ul class="contract-list">') !== false, 'Plain list rendering failed.');
$assert(strpos($rendered, '<br><br>') === false, 'Excessive blank lines were not normalized.');

$unsafe = '<p onclick="alert(1)" style="line-height:9">امن<script>alert(1)</script><span class="contract-note arbitrary" onload="x">متن</span></p>';
$safe = ContractTemplateRenderer::sanitizeHtml($unsafe);
$assert(stripos($safe, 'script') === false, 'Script tag survived sanitization.');
$assert(stripos($safe, 'onclick') === false && stripos($safe, 'onload') === false, 'Event attribute survived sanitization.');
$assert(stripos($safe, 'style=') === false, 'Inline style survived sanitization.');
$assert(strpos($safe, 'contract-note') !== false && strpos($safe, 'arbitrary') === false, 'Class allowlist failed.');

$validation = ContractTemplateService::validateTemplate('سلام {{unknown_variable}}', ContractTemplateRenderer::FORMAT_PLAIN);
$assert($validation['valid'] === true && count($validation['unknown_variables']) === 1, 'Unknown variable reporting failed.');
$blocked = ContractTemplateService::validateTemplate('<script>alert(1)</script>', ContractTemplateRenderer::FORMAT_HTML);
$assert($blocked['valid'] === false, 'Unsafe template validation failed.');

$compact = ContractPrintProfile::fromPreset('compact');
$assert((float) $compact['margin_top'] === 4.0, 'Current compact top margin must be 4mm.');
$assert((float) $compact['body_line_height'] === 1.18, 'Current compact line height must be 1.18.');
$clamped = ContractPrintProfile::validate(['margin_top' => -8, 'body_line_height' => 9, 'logo_width' => 900]);
$assert((float) $clamped['margin_top'] === 4.0, 'Top margin lower bound failed.');
$assert((float) $clamped['body_line_height'] === 1.3, 'Official compact line height upper bound failed.');
$assert((float) $clamped['logo_width'] === 60.0, 'Logo width upper bound failed.');
$assert(strpos(ContractPrintProfile::styleVariables($compact), '--contract-body-line-height:1.18') !== false, 'Print CSS variables missing.');

$printCss = (string) file_get_contents($root . '/assets/css/components/contract-print.css');
$assert(strpos($printCss, 'thead { display: table-header-group; }') !== false, 'Repeating table header rule missing.');
$assert(strpos($printCss, '.contract-print-page { width: auto; min-height: auto; margin: 0; padding: 0;') !== false, 'Duplicate print padding was not removed.');
$assert(strpos($printCss, 'line-height: 2') === false, 'Legacy global line height remains.');

$settingsView = (string) file_get_contents($root . '/views/settings/index.php');
$assert(strpos($settingsView, 'name="contract_template_body"') === false, 'Legacy giant settings form still submits the template.');
$contractView = (string) file_get_contents($root . '/views/settings/contracts.php');
foreach (['کپی متن قالب فعلی', 'ذخیره پیش‌نویس', 'تنظیمات ظاهر و چاپ', 'نسخه‌های قالب', 'پیش‌نمایش زنده A4'] as $label) {
    $assert(strpos($contractView, $label) !== false, 'Contract settings UI is missing: ' . $label);
}

echo "STATIC_V130_OK\n";
