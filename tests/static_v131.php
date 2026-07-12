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
$assert(($version['application'] ?? '') === '1.3.1', 'Application version must be V1.3.1.');

$plain = "ماده ۱\n\nمتن عادی و **بند مهم** ادامه متن.\n\n**کل این بند مهم است**";
$rendered = ContractTemplateRenderer::render($plain, ContractTemplateRenderer::FORMAT_PLAIN);
$assert(substr_count($rendered, 'contract-important-clause') === 2, 'Important clause parser did not render both forms.');
$assert(strpos($rendered, 'contract-important-paragraph') !== false, 'Complete important paragraph class is missing.');
$assert(strpos($rendered, '**') === false, 'Balanced important markers leaked to print output.');

$html = '<p>قبل **مهم** بعد<img src=x onerror=alert(1)></p><p style="font-size:99px">متن</p>';
$safe = ContractTemplateRenderer::render($html, ContractTemplateRenderer::FORMAT_HTML);
$assert(stripos($safe, '<img') === false && stripos($safe, 'onerror') === false, 'Unsafe markup survived important parsing.');
$assert(stripos($safe, 'style=') === false, 'Inline print typography survived sanitization.');
$assert(strpos($safe, 'contract-important-clause') !== false, 'Structured HTML important marker was not parsed.');

$unclosed = ContractTemplateRenderer::render('**متن ناقص', ContractTemplateRenderer::FORMAT_PLAIN);
$assert(strpos($unclosed, '**') !== false && strpos($unclosed, '<strong') === false, 'Unclosed marker must remain ordinary escaped text.');
$validation = ContractTemplateService::validateTemplate('**متن ناقص', ContractTemplateRenderer::FORMAT_PLAIN);
$assert($validation['valid'] === true && in_array('یک علامت ** بدون جفت در قالب پیدا شد.', $validation['warnings'], true), 'Unclosed marker warning is missing.');
$blocked = ContractTemplateService::validateTemplate('<p style="line-height:9">متن</p>', ContractTemplateRenderer::FORMAT_HTML);
$assert($blocked['valid'] === false, 'Unsupported inline typography must block publishing.');

$compact = ContractPrintProfile::fromPreset('official_compact');
$assert((float) $compact['body_font_size'] === 6.0, 'Official body font must be 6px.');
$assert((float) $compact['heading_font_size'] === 7.0, 'Official heading font must be 7px.');
$assert((float) $compact['important_font_size'] === 7.0, 'Official important font must be 7px.');
$assert((float) $compact['body_line_height'] === 1.22, 'Official body line-height must be 1.22.');
$assert((float) $compact['margin_top'] === 5.0 && (float) $compact['margin_right'] === 8.0 && (float) $compact['margin_bottom'] === 7.0, 'Official A4 margins are wrong.');
$locked = ContractPrintProfile::validate(['preset' => 'official_compact', 'body_font_size' => 12, 'heading_font_size' => 14, 'important_font_size' => 15]);
$assert((float) $locked['body_font_size'] === 6.0 && (float) $locked['heading_font_size'] === 7.0 && (float) $locked['important_font_size'] === 7.0, 'Official maximum font lock failed.');
$variables = ContractPrintProfile::styleVariables($compact);
foreach (['--contract-body-font-size:6px', '--contract-heading-font-size:7px', '--contract-table-font-size:5.5px', '--contract-signature-box-height:15mm'] as $rule) {
    $assert(strpos($variables, $rule) !== false, 'Missing print profile variable: ' . $rule);
}

$css = (string) file_get_contents($root . '/assets/css/components/contract-print.css');
foreach (['font-size: var(--contract-body-font-size)', 'line-height: var(--contract-body-line-height)', 'display: table-header-group', 'padding: 0;', '.contract-important-paragraph'] as $rule) {
    $assert(strpos($css, $rule) !== false, 'Print CSS rule is missing: ' . $rule);
}
$assert(strpos($css, 'transform: scale(') === false && strpos($css, 'zoom:') === false, 'Actual print CSS must not scale the page.');

$service = (string) file_get_contents($root . '/models/ContractTemplateService.php');
foreach (['archiveVersion', 'deleteVersion', 'referenceCounts', 'template_version_deleted', 'template_version_archived'] as $needle) {
    $assert(strpos($service, $needle) !== false, 'Version retention service is missing: ' . $needle);
}
$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$assert(strpos($layout, '$pluginMenuGroups') !== false && strpos($layout, "'children' => []") !== false, 'Plugin sidebar grouping is missing.');
$migration = (string) file_get_contents($root . '/database/migrations/2026_07_13_contract_print_compact_version_retention.sql');
$assert(strpos($migration, 'archived_at') !== false && strpos($migration, 'ip_address') !== false, 'Retention migration is incomplete.');

echo "STATIC_V131_OK\n";
