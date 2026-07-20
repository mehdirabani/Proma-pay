<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.3.9', '>='), 'Core version must be 1.3.9 or newer.');

foreach ([
    'database/migrations/2026_07_19_custom_installment_visibility_and_footer.sql',
    'docs/ui/FORM_CONTROL_STYLE_AUDIT.md',
    'docs/ui/BORDERLESS_FORM_DESIGN_SYSTEM.md',
    'docs/ui/FORM_CONTROL_COMPONENTS.md',
    'docs/ui/FORM_ACCESSIBILITY.md',
    'docs/ui/FORM_RESPONSIVE_RESULTS.md',
    'docs/ui/FORM_VISUAL_REGRESSION_RESULTS.md',
    'docs/plugins/PLUGIN_FORM_CONTROL_STANDARD.md',
    'docs/installments/CUSTOM_INSTALLMENT_DESCRIPTION_AUDIT.md',
    'docs/print/CONTRACT_PRINT_A4_STANDARD.md',
    'docs/settings/FOOTER_TEXT_SETTING.md',
    'docs/releases/V1.3.9.md',
    'docs/qa/V1_3_9_TEST_RESULTS.md',
    'docs/qa/FORM_CONTROL_TEST_RESULTS.md',
    'tests/integration_v139_custom_installments.php',
    'tests/integration_v139_contract_print_schema.php',
] as $file) {
    $assert(is_file($root . '/' . $file), 'Missing V1.3.9 file: ' . $file);
}

$forms = (string) file_get_contents($root . '/assets/css/components/forms.css');
foreach ([
    '--proma-field-height: 44px',
    '--proma-field-radius: 12px',
    'background: var(--proma-field-bg)',
    'background: var(--proma-field-bg-focus)',
    'box-shadow: inset 0 -2px 0 var(--proma-field-focus)',
    'input[type="file"]',
    'input[type="checkbox"], input[type="radio"]',
    ':not([type="submit"])',
    'label.required-field::before',
] as $needle) {
    $assert(strpos($forms, $needle) !== false, 'Shared form system is missing ' . $needle . '.');
}
$assert(preg_match('/(^|\n)\s*input\s*\{/m', $forms) !== 1, 'Shared form stylesheet must not use a bare input selector.');
$assert(strpos($forms, 'border: 1px solid transparent') !== false, 'Fields need a transparent border to prevent focus layout shift.');

$contracts = (string) file_get_contents($root . '/views/contracts/index.php');
$contractCss = (string) file_get_contents($root . '/assets/css/app.css');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
foreach (['proma-contract-card__identity', 'proma-contract-card__controls', 'proma-contract-card__meta', 'title="چاپ قرارداد"', 'title="چاپ دفترچه اقساط"', 'title="لغو قرارداد"'] as $needle) {
    $assert(strpos($contracts, $needle) !== false, 'Contract card is missing ' . $needle . '.');
}
$assert(strpos($contracts, 'proma-card-select') === false && strpos($contracts, 'contract_ids[]') === false, 'Removed contract selector returned to the contract list.');
$assert(strpos($appJs, "const interactiveSelector = 'a,button,input,select,textarea,label") !== false, 'Card navigation must exclude inner controls.');

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_19_custom_installment_visibility_and_footer.sql');
foreach (['custom_title', 'custom_description', 'internal_note', 'customer_visible', 'created_reason', 'generated_contract_documents', 'rendered_title', 'rendered_header', 'template_version_id', 'template_status', 'information_schema.COLUMNS', 'پروما پی سامانه جامع پرداخت'] as $needle) {
    $assert(strpos($migration, $needle) !== false, 'Custom-installment migration is missing ' . $needle . '.');
}
$assert(!preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $migration), 'Custom-installment migration contains a destructive statement.');

$installment = (string) file_get_contents($root . '/models/Installment.php');
$installmentView = (string) file_get_contents($root . '/views/installments/index.php');
$contractView = (string) file_get_contents($root . '/views/contracts/show.php');
foreach (['public static function createCustom', 'custom_description', 'internal_note', 'customer_visible', 'return (int) self::lastInsertId()'] as $needle) {
    $assert(strpos($installment, $needle) !== false, 'Custom installment model is missing ' . $needle . '.');
}
$assert(strpos($installmentView, '!$customerMode || !empty($item[\'customer_visible\'])') !== false, 'Customer portal does not guard hidden custom descriptions.');
$assert(strpos($contractView, '$canManageActiveContract && !empty($installment[\'internal_note\'])') !== false, 'Internal note visibility is not restricted to authorized management.');
$assert(strpos($installmentView, 'e($customInstallmentDescription)') !== false, 'Customer-facing custom description must be escaped.');

$printCss = (string) file_get_contents($root . '/assets/css/components/contract-print.css');
$assert(strpos($printCss, 'transform: scale') === false && strpos($printCss, 'zoom:') === false, 'Contract print must not use transform or zoom scaling.');
$assert(strpos($printCss, '--contract-body-font-size: 7px') !== false, 'Compact A4 profile body size is missing.');
$assert(strpos($printCss, '.proma-contract-letterhead') !== false, 'Structured print letterhead is missing.');

$settings = (string) file_get_contents($root . '/models/Settings.php');
$settingsController = (string) file_get_contents($root . '/controllers/SettingsController.php');
$assert(strpos($settings, "'footer_text' => 'پروما پی سامانه جامع پرداخت'") !== false, 'Footer default is incorrect.');
$assert(strpos($settingsController, '$key === \'footer_text\'') !== false && strpos($settingsController, 'strip_tags($rawValue)') !== false, 'Footer value must be sanitized server-side.');

foreach ([
    ['plugins/PromaAccounting/assets/css/accounting.css', '.proma-accounting'],
    ['plugins/PromaZarinpal/assets/css/zarinpal.css', '.proma-zp-page'],
] as [$file, $scope]) {
    $pluginCss = (string) file_get_contents($root . '/' . $file);
    $assert(strpos($pluginCss, $scope) !== false, $file . ' is not scoped to its plugin root.');
    $assert(strpos($pluginCss, 'var(--proma-field-bg') !== false, $file . ' does not consume the Core field tokens.');
    $assert(preg_match('/(^|\n)\s*(input|select|textarea)\s*\{/m', $pluginCss) !== 1, $file . ' contains a forbidden global form selector.');
}

$releaseBuilder = (string) file_get_contents($root . '/scripts/build_release.php');
$assert(strpos($releaseBuilder, '2026_07_19_custom_installment_visibility_and_footer.sql') !== false, 'Release builder omits the V1.3.9 migration.');
$assert(strpos($releaseBuilder, 'tests/static_v139.php') !== false, 'Release builder omits the V1.3.9 static test.');

$errorHandler = (string) file_get_contents($root . '/core/ErrorHandler.php');
$assert(strpos($errorHandler, "PHP_SAPI === 'cli'") !== false && strpos($errorHandler, 'exit(1);') !== false, 'CLI failures must return a non-zero status for the release gate.');

$zarinpalIntegration = (string) file_get_contents($root . '/tests/integration_zarinpal_v134.php');
foreach (['random_bytes(', 'PluginManager::boot()', '$idempotencyPrefix', '$singleAuthority'] as $needle) {
    $assert(strpos($zarinpalIntegration, $needle) !== false, 'Zarinpal integration test is not repeatable: missing ' . $needle . '.');
}

echo "STATIC_V139_OK\n";
