<?php

$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__, 2) . '/bootstrap.php';

$type = preg_replace('/[^a-z]/', '', (string) ($_GET['type'] ?? 'settings'));
$settings = Settings::defaults();
$printProfile = ContractPrintProfile::defaults();

if (in_array($type, ['print', 'table'], true)) {
    $profile = $printProfile;
    if ($type === 'table') {
        $rows = '';
        for ($index = 1; $index <= 80; $index++) {
            $rows .= '<tr><td>' . to_persian_digits($index) . '</td><td>ردیف آزمایشی ' . to_persian_digits($index) . '</td><td>۱۴۰۵/۰۵/۲۲</td></tr>';
        }
        $body = '<h2 class="contract-section-title">آزمون ادامه جدول در چند صفحه</h2><table class="contract-print-table"><thead><tr><th>ردیف</th><th>شرح</th><th>سررسید</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    } else {
        $body = ContractTemplateRenderer::render(
            ContractDocument::defaultTemplate(),
            ContractTemplateRenderer::FORMAT_PLAIN,
            ContractTemplateService::sampleReplacements()
        );
    }
    $body = '<div class="contract-document-body">' . $body . '</div>';
    $template = ContractTemplateService::getDefaultTemplate();
    require dirname(__DIR__, 2) . '/views/settings/contract-preview.php';
    exit;
}

$section = preg_replace('/[^a-z]/', '', (string) ($_GET['section'] ?? 'dashboard')) ?: 'dashboard';
$effectiveTemplate = ContractTemplateService::getDefaultTemplate();
$draftTemplate = null;
$editorTemplate = $effectiveTemplate;
$templateVersions = [];
$templateStats = ['stale_documents' => 12, 'documents' => 148, 'has_logo' => false];
$printPresets = ContractPrintProfile::presets();
$variableCatalog = ContractTemplateService::variableCatalog();
$latestRebuildJob = ['id' => 1, 'status' => 'running', 'total_documents' => 12, 'processed_documents' => 5, 'failed_documents' => 0];
$contractPreviewUrl = '/tests/fixtures/contract_ui.php?type=print';

ob_start();
require dirname(__DIR__, 2) . '/views/settings/contracts.php';
$content = ob_get_clean();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../../html/RTL/assets/css/vendors/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="../../html/RTL/assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/app.css">
  <link rel="stylesheet" href="../../assets/css/components/forms.css">
  <link rel="stylesheet" href="../../assets/css/components/layout.css">
  <link rel="stylesheet" href="../../assets/css/components/contract-settings.css">
  <style>body{padding:24px;background:#f6f7fb}.fixture{max-width:1440px;margin:auto}</style>
</head>
<body><main class="fixture proma-page-content"><?= $content ?></main></body>
</html>
