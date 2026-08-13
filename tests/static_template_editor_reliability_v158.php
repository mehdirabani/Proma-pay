<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$templateView = (string) file_get_contents($root . '/views/settings/contracts.php');
$adapter = (string) file_get_contents($root . '/assets/js/contract-template-editor.js');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$pluginManager = (string) file_get_contents($root . '/core/PluginManager.php');
$templateService = (string) file_get_contents($root . '/models/ContractTemplateService.php');
$templateRenderer = (string) file_get_contents($root . '/models/ContractTemplateRenderer.php');
$settingsController = (string) file_get_contents($root . '/controllers/SettingsController.php');

$routeScope = '$needsContractTemplateEditor = $route === \'settings/contracts/template\';';
$adapterScript = '<?php if ($needsContractTemplateEditor): ?><script src="<?= e(asset_url(\'assets/js/contract-template-editor.js\')) ?>"></script><?php endif; ?>';
$assert(strpos($layout, $routeScope) !== false, 'Contract-template route is not explicitly identified in the layout.');
$assert(strpos($layout, $adapterScript) !== false, 'Contract-template adapter is still loaded globally.');
$assert(strpos($layout, '$needsContractTemplateEditor') < strpos($layout, "asset_url('assets/js/contract-template-editor.js')"), 'Route-scoped adapter declaration must precede its script tag.');
$assert(strpos($layout, '$needsCharts = in_array($route') !== false, 'Chart loading must be route-scoped.');
$assert(strpos($layout, '<?php if ($needsCharts): ?><script src="<?= e(asset_url(\'assets/vendor/chart.umd.min.js\')) ?>"') !== false, 'Chart.js is still loaded on unrelated pages.');
$assert(strpos($appJs, 'textarea[data-rich-editor]:not([data-contract-template-editor])') !== false, 'Global rich-editor initializer still owns the contract-template textarea.');

foreach (['SOURCE_MODE', 'VISUAL_MODE', 'data-template-editor-status', 'data-template-find-form', 'localStorage', 'syncVisualToSource', 'data-template-quill-retry', 'setLifecycleState', 'degraded_source_only', 'asset_error', 'visual_ready', 'source_ready'] as $needle) {
    $assert(strpos($adapter, $needle) !== false, 'Template editor adapter is missing: ' . $needle);
}
$assert(strpos($adapter, 'Rehydrate Quill immediately before revealing it') !== false && strpos($adapter, 'sourceToHtml(editor.value)') !== false, 'Source edits are not synchronized into Quill before visual mode opens.');
foreach (['window.alert', 'window.prompt', 'window.confirm'] as $forbidden) {
    $assert(strpos($adapter, $forbidden) === false, 'Native browser dialog remains in template editor adapter: ' . $forbidden);
}
$assert(strpos($templateView, 'data-template-mode="simple" role="tab" aria-selected="false"') !== false, 'Visual template tab must not be server-rendered as active.');
$assert(strpos($templateView, 'data-template-mode="source" role="tab" aria-selected="true"') !== false, 'Source template tab must be the safe initial state.');
$assert(strpos($templateView, 'data-template-find-form') !== false, 'Find/replace does not use a core modal form.');
$assert(strpos($templateView, 'data-template-confirm-visual-conversion') !== false, 'Plain-text conversion has no explicit visual-editor confirmation.');
$assert(strpos($templateView, 'editor_conversion_source') !== false, 'Template conversion source is not submitted for audit.');
$assert(strpos($settingsController, "ContractTemplateService::audit('template_format_converted'") !== false, 'Confirmed template conversion is not written to the audit trail.');

$assert(strpos($pluginManager, 'protected $failedRouteClaims = [];') !== false, 'Plugin failure route-claim inventory is missing.');
$assert(strpos($pluginManager, 'registerFailedRouteClaims') !== false, 'Plugin boot failures do not retain route claims.');
$assert(strpos($pluginManager, 'افزونه مسئول این مسیر آماده نیست') !== false, 'Failed plugin routes do not return a controlled 503 response.');
$assert(strpos($pluginManager, "ErrorHandler::respond(500, 'اجرای این بخش از افزونه کامل نشد") !== false, 'Plugin controller exceptions can still fall through to the core router.');

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $ddl) {
    $assert(stripos($templateService, $ddl) === false, 'ContractTemplateService contains request-time DDL: ' . $ddl);
}
$assert(strpos($templateRenderer, 'sanitizeFallback') === false, 'Structured HTML must not fall back to regex-only sanitization.');
$assert(strpos($templateService, "format === ContractTemplateRenderer::FORMAT_HTML && !class_exists('DOMDocument')") !== false, 'HTML validation must reject environments without DOM sanitization.');

echo "STATIC_TEMPLATE_EDITOR_RELIABILITY_V158_OK\n";
