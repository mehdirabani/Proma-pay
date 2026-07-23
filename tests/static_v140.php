<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = require $root . '/config/version.php';
$runtimeSettings = require $root . '/config/settings.php';
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.4.0', '>='), 'Core version must be 1.4.0 or newer.');
$applicationVersion = (string) ($version['application'] ?? '');
$manifest = json_decode((string) file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$package = json_decode((string) file_get_contents($root . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
$serviceWorker = (string) file_get_contents($root . '/service-worker.js');
$assert(($version['display'] ?? '') === 'V' . $applicationVersion, 'Display version is not synchronized with the application version.');
$assert(($manifest['version'] ?? '') === 'v' . $applicationVersion, 'Web manifest version is not synchronized.');
$assert(($package['version'] ?? '') === $applicationVersion, 'Package version is not synchronized.');
$assert(($runtimeSettings['asset_version'] ?? '') === $applicationVersion, 'Asset cache version is not synchronized.');
$assert(strpos($serviceWorker, 'proma-pay-v' . str_replace('.', '-', $applicationVersion) . '-') !== false, 'Service-worker cache version is not synchronized.');

foreach ([
    'models/ContractRequest.php',
    'models/LoginThrottle.php',
    'database/migrations/2026_07_20_profile_auth_contract_payment_v140.sql',
    'tests/integration_v140_core_workflows.php',
    'docs/releases/V1.4.0.md',
    'docs/qa/V1_4_0_TEST_RESULTS.md',
    'docs/qa/BUG_REGISTER.md',
    'docs/security/CUSTOMER_LOGIN_POLICY.md',
    'tools/verify-release.php',
] as $file) {
    $assert(is_file($root . '/' . $file), 'Missing V1.4.0 file: ' . $file);
}

$paymentRequest = (string) file_get_contents($root . '/models/PaymentRequest.php');
$installmentsController = (string) file_get_contents($root . '/controllers/InstallmentsController.php');
$contractRequest = (string) file_get_contents($root . '/models/ContractRequest.php');
$contractsController = (string) file_get_contents($root . '/controllers/ContractsController.php');
$contractModel = (string) file_get_contents($root . '/models/Contract.php');
$contractView = (string) file_get_contents($root . '/views/contracts/index.php');
$contractDetail = (string) file_get_contents($root . '/views/contracts/show.php');
$profileController = (string) file_get_contents($root . '/controllers/ProfileController.php');
$profileRequest = (string) file_get_contents($root . '/models/ProfileRequest.php');
$profileView = (string) file_get_contents($root . '/views/profile/index.php');
$usersView = (string) file_get_contents($root . '/views/users/index.php');
$profileReviewsController = (string) file_get_contents($root . '/controllers/ProfileReviewsController.php');
$profileReviewsView = (string) file_get_contents($root . '/views/profile-reviews/index.php');
$appLayout = (string) file_get_contents($root . '/views/layouts/app.php');
$authController = (string) file_get_contents($root . '/controllers/AuthController.php');
$auth = (string) file_get_contents($root . '/core/Auth.php');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$migration = (string) file_get_contents($root . '/database/migrations/2026_07_20_profile_auth_contract_payment_v140.sql');
$installSql = (string) file_get_contents($root . '/database/proma-pay-install.sql');
$releaseBuilder = (string) file_get_contents($root . '/scripts/build_release.php');

$assert(strpos($paymentRequest, 'public static function beginRequest') !== false, 'Payment request API was not renamed safely.');
$assert(strpos($paymentRequest, 'public static function begin(') === false, 'PaymentRequest still overrides Model::begin with an incompatible signature.');
$assert(strpos($installmentsController, 'PaymentRequest::beginRequest') !== false, 'Manual payment does not use the corrected request API.');
$assert(strpos($contractDetail, 'data-payment-preview') !== false && strpos($contractDetail, 'data-disable-on-submit') !== false, 'Contract-detail payment form lacks preview or duplicate-submit protection.');

foreach (['beginRequest', 'request_hash', "status = 'completed'", "status = 'failed'"] as $needle) {
    $assert(strpos($contractRequest, $needle) !== false, 'Contract idempotency model is missing ' . $needle . '.');
}
$assert(strpos($contractsController, 'ContractRequest::beginRequest') !== false, 'Contract creation does not acquire an idempotency request.');
$assert(strpos($contractModel, 'ContractRequest::complete') !== false, 'Contract creation does not complete idempotency inside its transaction.');
$assert(strpos($contractView, 'name="contract_request_uuid"') !== false && strpos($contractView, 'data-disable-on-submit') !== false, 'Contract form lacks duplicate-submit protection.');
$assert(strpos($contractView, 'proma-card-select') === false && strpos($contractView, 'contract_ids[]') === false && strpos($contractView, 'bulk-contracts-modal') === false, 'Contract selector UI was not completely removed.');

$assert(strpos($profileController, 'public function updateAvatar') !== false, 'Direct avatar update endpoint is missing.');
$assert(strpos($profileView, "url('profile/updateAvatar')") !== false, 'Profile avatar form is not wired to the direct endpoint.');
$assert(strpos($profileRequest, 'array_intersect_key($payload, self::fieldLabels())') !== false, 'Profile approval does not restrict allowed fields.');
$assert(strpos($usersView, 'proma-profile-review-card') === false, 'Profile review must not remain embedded in the users list.');
$assert(strpos($profileReviewsController, 'ProfileRequest::paginated') !== false, 'Independent profile-review controller is missing pagination.');
$assert(strpos($profileReviewsView, 'تأیید اصلاح مشخصات') !== false && strpos($profileReviewsView, 'تاریخچه این حساب') !== false, 'Independent management profile-review workspace is missing.');
$assert(strpos($appLayout, "['profile-reviews', 'تأیید اصلاح مشخصات'") !== false, 'Profile-review workspace is not available from the admin sidebar.');
$assert(strpos($profileController, "'password' =>") === false, 'Profile requests must not persist a plaintext password.');

$assert(strpos($authController, 'LoginThrottle::inspect') !== false && strpos($authController, 'LoginThrottle::recordFailure') !== false, 'Login rate limiting is not enforced.');
$assert(substr_count($authController, 'اطلاعات ورود صحیح نیست یا امکان ورود موقتاً محدود شده است.') >= 2, 'Login failure messages are not generic.');
$assert(strpos($auth, 'substr($mobileDigits, -4)') !== false, 'Customer last-four mobile login compatibility was changed.');
$assert(strpos($auth, 'session_regenerate_id(true)') !== false, 'Successful login does not regenerate the session identifier.');
$assert(strpos($appJs, 'event.stopImmediatePropagation()') !== false, 'Duplicate form submissions are not blocked client-side.');

foreach (['auth_login_attempts', 'contract_requests', 'archived_at', 'JSON_REMOVE'] as $needle) {
    $assert(strpos($migration, $needle) !== false, 'V1.4.0 migration is missing ' . $needle . '.');
}
$assert(!preg_match('/\b(drop\s+table|truncate\s+table)\b/i', $migration), 'V1.4.0 update migration contains a destructive schema statement.');
$assert(strpos($installSql, 'CREATE TABLE `auth_login_attempts`') !== false && strpos($installSql, 'CREATE TABLE `contract_requests`') !== false, 'Clean-install SQL omits V1.4.0 tables.');
$assert(strpos($installSql, '`template_version_id` bigint(20) unsigned DEFAULT NULL') !== false, 'Clean-install SQL omits the contract document template version column.');
$assert(strpos($migration, 'document_template_version_sql') !== false, 'V1.4.0 migration does not repair the contract document template version column.');
foreach (['--diff-filter=ACMRT', 'database/proma-pay-install.sql', '2026_07_21_v1_4_1_interaction_state.sql', 'tools/release-gate.php', 'PromaPay-Update-v', 'SHA256SUMS.txt'] as $needle) {
    $assert(strpos($releaseBuilder, $needle) !== false, 'Release builder omits required differential release behavior: ' . $needle . '.');
}

echo "STATIC_V140_OK\n";
