<?php

/** Release guard for the contract-template editor's safe visual default. */
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$view = $read('views/settings/contracts.php');
$editor = $read('assets/js/contract-template-editor.js');
$tabs = $read('assets/js/app.js');
$renderer = $read('models/ContractTemplateRenderer.php');

$assert(strpos($view, 'data-template-mode="visual"') !== false, 'Visual contract editor mode is missing.');
$assert(strpos($view, 'ویرایش بصری') !== false, 'Visual editor is not presented as the primary mode.');
$assert(strpos($view, 'data-template-mode="preview"') !== false && strpos($view, 'data-template-preview-frame') !== false, 'Local A4 preview is missing.');
$assert(strpos($view, 'data-contract-variable-search') !== false && strpos($view, 'data-contract-variable-list') !== false, 'Variable browser/search is missing.');
$assert(strpos($editor, "let mode = VISUAL_MODE") !== false, 'Editor does not start in visual mode.');
$assert(strpos($editor, 'registerVariableBlot') !== false && strpos($editor, 'data-contract-variable') !== false, 'Protected variable token handling is missing.');
$assert(strpos($editor, 'cleanPreviewMarkup') !== false && strpos($editor, 'sandbox') === false, 'Preview sanitizer is missing or server markup is incorrectly coupled.');
$assert(strpos($renderer, 'sanitizeHtml') !== false && strpos($renderer, 'DOMDocument') !== false, 'Server-side HTML sanitization is missing.');
$assert(strpos($tabs, "workspace.dataset.contractTabsBound = '1'") !== false, 'Contract tabs are not idempotently initialized.');
$assert(strpos($tabs, "root.querySelectorAll('[data-contract-tab-panel]')") !== false, 'Contract tab panels are not component-scoped.');

echo "STATIC_V1510_TEMPLATE_EDITOR_OK\n";
