<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    return $content;
};
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$contract = $read('models/Contract.php');
$contractController = $read('controllers/ContractsController.php');
$contractView = $read('views/contracts/index.php');
$profileModel = $read('models/ProfileRequest.php');
$profileController = $read('controllers/ProfileReviewsController.php');
$profileView = $read('views/profile-reviews/index.php');
$usersView = $read('views/users/index.php');
$layout = $read('views/layouts/app.php');
$medals = $read('views/medals/index.php');
$healthController = $read('controllers/SystemHealthController.php');
$healthView = $read('views/system-health/index.php');
$throttle = $read('models/LoginThrottle.php');
$appCss = $read('assets/css/app.css');
$formCss = $read('assets/css/components/forms.css');

$assert(strpos($contract, 'purgeContractHistory') !== false, 'Contract history purge service is missing.');
$assert(strpos($contract, 'contract_deletion_archives') !== false && strpos($contract, 'snapshot_json') !== false, 'Deletion archive is missing.');
$assert(strpos($contract, 'history_purged') !== false, 'Deletion audit does not record purge mode.');
$assert(strpos($contractController, 'confirm_history_purge') !== false, 'Second destructive confirmation is not enforced.');
$assert(strpos($contractView, 'purge_contract_history') !== false, 'Contract delete purge option is missing.');
$assert(!preg_match('/name="(?:deletion_reason|confirm_contract_number)"[^>]*disabled/', $contractView), 'Contract deletion inputs are disabled.');
$assert(strpos($contractView, 'blocking_dependency_labels') !== false, 'Internal dependency keys are still exposed.');
$assert(strpos($contract, 'scheduled_amount') !== false && strpos($contractView, 'paidAmount / $scheduledAmount') !== false, 'Amount-based contract progress is missing.');
$assert(strpos($contractView, 'proma-progress-avatar') !== false && strpos($appCss, '.proma-progress-avatar > small') !== false, 'Visible contract progress ring is incomplete.');

$assert(class_exists('ProfileReviewsController') || strpos($profileController, 'class ProfileReviewsController') !== false, 'Dedicated profile review controller is missing.');
$assert(strpos($profileModel, 'public static function paginated') !== false && strpos($profileModel, 'historiesForUsers') !== false, 'Profile review pagination or history is missing.');
$assert(strpos($profileView, 'proma-review-history') !== false && strpos($profileView, 'approved_fields[]') !== false, 'Profile review workflow is incomplete.');
$assert(strpos($layout, "['profile-reviews', 'تأیید اصلاح مشخصات'") !== false, 'Profile review sidebar entry is missing.');
$assert(strpos($usersView, 'proma-profile-review-card') === false, 'Duplicate profile review block remains in users page.');

$assert(strpos($medals, 'proma-medal-card__criterion') !== false, 'Medal criterion badge hook is missing.');
$assert(strpos($appCss, '.proma-medal-card__criterion') !== false && strpos($appCss, 'overflow-wrap: anywhere') !== false, 'Medal overflow protection is missing.');
$assert(strpos($healthController, 'public function diagnostics') !== false && strpos($healthController, 'login_ip_locking') !== false, 'System health diagnostics are incomplete.');
$assert(strpos($healthView, 'تشخیص اختلال IP و Timeout') !== false, 'Network diagnosis panel is missing.');
$assert(strpos($throttle, "$scope['type'] !== 'ip'") !== false && strpos($throttle, 'PHP_INT_MAX') !== false, 'Shared IP login lockout is still active.');
$assert(strpos($formCss, ':has(> input[required]') !== false, 'Required label marker alignment rule is missing.');
$assert(substr_count($appCss, '{') === substr_count($appCss, '}'), 'app.css braces are unbalanced.');
$assert(substr_count($formCss, '{') === substr_count($formCss, '}'), 'forms.css braces are unbalanced.');

echo "STATIC_V143_OK\n";
