<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$applicationVersion = (string) ($version['application'] ?? '');
$assert(version_compare($applicationVersion, '1.4.3', '>='), 'Core version must be V1.4.3 or newer.');
$assert(($version['release_channel'] ?? '') === 'stable', 'V1.4.3 release channel must be stable.');

foreach ([
    'controllers/ProfileReviewsController.php',
    'views/profile-reviews/index.php',
    'database/migrations/2026_07_22_release_v143.sql',
    'tests/integration_v143_release.php',
    'tests/http_v143_modal_smoke.php',
    'docs/releases/V1.4.3.md',
    'docs/qa/V1_4_3_TEST_RESULTS.md',
] as $requiredFile) {
    $assert(is_file($root . '/' . $requiredFile), 'Required V1.4.3 file is missing: ' . $requiredFile);
}

$contract = (string) file_get_contents($root . '/models/Contract.php');
$controller = (string) file_get_contents($root . '/controllers/ContractsController.php');
$contractIndex = (string) file_get_contents($root . '/views/contracts/index.php');
$contractShow = (string) file_get_contents($root . '/views/contracts/show.php');
foreach (['purgeContractHistory', 'contractDeletionSnapshot', 'gateway_warning_accepted', 'contract_deletion_archives'] as $token) {
    $assert(strpos($contract, $token) !== false, 'Safe contract purge is missing token: ' . $token);
}
$assert(strpos($controller, "\$_POST['purge_contract_history']") !== false, 'Contract purge option is not passed by the controller.');
$assert(strpos($controller, "\$_POST['confirm_gateway_warning']") !== false, 'Gateway warning confirmation is not passed by the controller.');
foreach ([$contractIndex, $contractShow] as $deleteView) {
    $assert(strpos($deleteView, 'name="purge_contract_history"') !== false, 'Contract delete modal lacks the explicit history purge option.');
    $assert(strpos($deleteView, 'name="confirm_gateway_warning"') !== false, 'Contract delete modal lacks the gateway no-refund warning.');
    $assert(!preg_match('/name="(?:reason|typed_contract_number|purge_contract_history|confirm_gateway_warning)"[^>]*\bdisabled\b/i', $deleteView), 'Contract delete confirmation controls must not be disabled.');
}

$loginThrottle = (string) file_get_contents($root . '/models/LoginThrottle.php');
$assert(strpos($loginThrottle, "'scope_type' => 'ip'") === false, 'Pure IP login lockout remains enabled.');
$assert(strpos($loginThrottle, "'type' => 'combined'") !== false, 'Identifier and IP scoped throttling is missing.');

$profileController = (string) file_get_contents($root . '/controllers/ProfileReviewsController.php');
$profileModel = (string) file_get_contents($root . '/models/ProfileRequest.php');
$profileView = (string) file_get_contents($root . '/views/profile-reviews/index.php');
$layout = (string) file_get_contents($root . '/views/layouts/app.php');
$assert(strpos($profileController, 'ProfileRequest::paginated') !== false, 'Profile-review workspace is not paginated.');
$assert(strpos($profileModel, 'statusSummary') !== false && strpos($profileModel, 'reviewer_name') !== false, 'Profile review audit hydration is incomplete.');
$assert(strpos($profileView, 'تاریخچه این حساب') !== false && strpos($profileView, 'selection_submitted') !== false, 'Profile-review history or safe partial selection is missing.');
$assert(strpos($layout, "['profile-reviews', 'تأیید اصلاح مشخصات'") !== false, 'Profile review is not an independent sidebar destination.');

$appCss = (string) file_get_contents($root . '/assets/css/app.css');
$formsCss = (string) file_get_contents($root . '/assets/css/components/forms.css');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
foreach (['.proma-progress-avatar', 'conic-gradient', '.proma-progress-avatar__value', '.proma-medal-card', 'overflow: hidden', '.proma-health-service-grid'] as $cssToken) {
    $assert(strpos($appCss, $cssToken) !== false, 'Required V1.4.3 UI rule is missing: ' . $cssToken);
}
foreach (['.proma-review-summary', '.proma-profile-review-workspace', '@media (max-width: 575.98px)', 'min-width: 0'] as $cssToken) {
    $assert(strpos($formsCss, $cssToken) !== false, 'Responsive profile-review rule is missing: ' . $cssToken);
}
$assert(substr_count($formsCss, '@media (max-width:') >= 2, 'Form system lacks tablet and mobile responsive coverage.');
$assert(strpos($formsCss, 'grid-auto-rows: max-content') !== false, 'Modal fields must grow with labels, controls, and help text.');
$assert(strpos($formsCss, '.modal-content form') !== false && strpos($formsCss, 'overflow: hidden') !== false, 'Modal form flex containment is missing.');
$assert(strpos($appJs, 'body.scrollTop = 0') !== false, 'Modal body scroll position must reset when opened.');
$assert(strpos($appJs, 'fieldLabelNormalized') !== false, 'Legacy form labels are not normalized into the shared label structure.');
$assert(strpos($appJs, "modal.addEventListener('proma:modal-opened', update)") !== false, 'Payment previews must be lazy-loaded when their modal opens.');
$assert(strpos($appJs, 'fetchJsonCached') !== false && strpos($appJs, 'jsonGetCache.size > 120') !== false, 'Bounded GET request deduplication is missing.');
$assert(strpos($appJs, 'initSubmitGuards();') !== false && strpos($appJs, 'disableSubmitBound') !== false, 'Duplicate-submit guards are not safely reusable.');
$assert(strpos($contractShow, 'action="<?= e(url(\'installments/store\')) ?>" data-disable-on-submit') !== false, 'Custom installment form lacks duplicate-submit protection.');
$assert(substr_count($contractShow, '<span class="proma-form-label">') >= 7, 'Custom installment modal does not use explicit field labels.');

$model = (string) file_get_contents($root . '/core/Model.php');
foreach (['PROMA_DB_DSN', 'PROMA_DB_HOST', 'PROMA_DB_NAME', 'PROMA_DB_USER', 'PROMA_DB_PASSWORD'] as $environmentKey) {
    $assert(strpos($model, $environmentKey) !== false, 'Database environment override is missing: ' . $environmentKey);
}

$migration = (string) file_get_contents($root . '/database/migrations/2026_07_22_release_v143.sql');
$assert(strpos($migration, "scope_type = 'ip'") !== false, 'V1.4.3 migration does not remove legacy pure-IP locks.');
$assert(strpos($migration, 'idx_profile_request_status_created') !== false, 'V1.4.3 migration lacks profile-review index repair.');
$assert(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $migration), 'V1.4.3 migration contains a destructive table operation.');

echo "STATIC_V143_OK\n";
