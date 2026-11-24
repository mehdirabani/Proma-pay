<?php

$catalog = require __DIR__ . '/../config/design_system_catalog.php';
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(count($catalog['languages']) === 7, 'all seven documented code languages are available');
$assert(count($catalog['categories']) === 3, 'foundations, components, and page patterns have separate roots');
$assert($catalog['counts']['components'] === count($catalog['components']), 'component metric comes from the component catalog');
$assert($catalog['counts']['patterns'] === count($catalog['templates']), 'page pattern metric comes from the template catalog');
$assert($catalog['counts']['codeExamples'] === count($catalog['all']) * count($catalog['languages']), 'snippet metric matches generated language examples');

$ids = [];
foreach ($catalog['all'] as $item) {
    $assert(!in_array($item['id'], $ids, true), 'catalog item IDs are unique: ' . $item['id']);
    $ids[] = $item['id'];
    foreach (['name', 'english', 'family', 'variant', 'overview', 'usage', 'props', 'events', 'states', 'accessibility', 'responsive', 'do', 'dont', 'related'] as $field) {
        $assert(array_key_exists($field, $item), 'item ' . $item['id'] . ' has field ' . $field);
    }
    $assert(count($item['props']) > 0, 'item ' . $item['id'] . ' documents at least one property');
    $assert(count($item['states']) > 0, 'item ' . $item['id'] . ' documents states');
}

$expectedTemplates = [
    'Admin Dashboard', 'Customer Dashboard', 'Customer List', 'Customer Detail',
    'Contract List', 'Contract Detail', 'Create Contract', 'Installment Management',
    'Payment Management', 'Financial Dashboard', 'Ledger', 'Overdue Management',
    'Legal Case Management', 'User Management', 'Settings', 'Reports',
];
$actualTemplates = array_column($catalog['templates'], 'english');
foreach ($expectedTemplates as $template) {
    $assert(in_array($template, $actualTemplates, true), 'required template exists: ' . $template);
}

$requiredFamilies = [
    'buttons', 'forms', 'date-time', 'overlays', 'navigation', 'data-display', 'feedback',
    'advanced', 'financial', 'installments', 'contracts', 'payments', 'accounting', 'collection-legal',
];
$actualFamilies = array_unique(array_column($catalog['components'], 'family'));
foreach ($requiredFamilies as $family) {
    $assert(in_array($family, $actualFamilies, true), 'required component family exists: ' . $family);
}

$controller = file_get_contents(__DIR__ . '/../controllers/DesignSystemController.php');
$view = file_get_contents(__DIR__ . '/../views/design-system/index.php');
$js = file_get_contents(__DIR__ . '/../assets/js/design-system.js');
$html = file_get_contents(__DIR__ . '/../design-system.html');
$exporter = file_get_contents(__DIR__ . '/../scripts/build_design_system_html.php');
$assert(is_string($controller) && strpos($controller, "Auth::requireRole(['admin'])") !== false, 'design-system route requires administrator access');
$assert(is_string($controller) && strpos($controller, 'PaymentController') === false && strpos($controller, 'Database::') === false, 'design-system controller has no payment or database operation');
$assert(is_string($view) && strpos($view, 'lang="fa" dir="rtl"') !== false, 'standalone view is native Persian RTL');
$assert(is_string($view) && strpos($view, 'ppds-catalog-data') !== false, 'view serializes its local catalog for the browser');
$assert(is_string($html) && strpos($html, 'PROMA PAY DESIGN SYSTEM') !== false, 'generated standalone HTML page exists');
$assert(is_string($html) && strpos($html, 'assets/js/design-system.js') !== false && !preg_match('~(?:href|src)="/(?:assets|html/RTL/assets)/~', $html), 'static HTML uses relative local assets');
$assert(is_string($exporter) && strpos($exporter, 'design-system.html') !== false, 'HTML export has a repeatable build script');
$assert(is_string($js) && strpos($js, 'proma-design-system-theme') !== false, 'theme preference is scoped to the documentation page');
$assert(is_string($js) && strpos($js, "elements.languageSelect.addEventListener('change', updateCode)") !== false, 'language selection refreshes the matching code example');
$assert(is_string($js) && strpos($js, 'document.querySelector') !== false, 'playground code preview is text only and is not evaluated');
$assert(is_string($js) && strpos($js, 'eval(') === false && strpos($js, 'new Function(') === false, 'no user-provided code is executed');

fwrite(STDOUT, 'PASS: ' . count($catalog['all']) . ' documented entries, ' . count($catalog['components']) . ' components, ' . count($catalog['templates']) . " templates\n");
