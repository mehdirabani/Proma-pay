<?php

$root = dirname(__DIR__, 2);
$auth = file_get_contents($root . '/core/Auth.php');
$user = file_get_contents($root . '/models/User.php');
$controller = file_get_contents($root . '/controllers/AuthController.php');
$reset = file_get_contents($root . '/models/PasswordReset.php');
$profileController = file_get_contents($root . '/controllers/ProfileController.php');
$profileView = file_get_contents($root . '/views/profile/index.php');
$registrationView = file_get_contents($root . '/views/auth/register.php');
$resetView = file_get_contents($root . '/views/auth/reset_password.php');
$policy = file_get_contents($root . '/docs/security/CUSTOMER_LOGIN_POLICY.md');
$failures = [];

if (strpos($auth, 'public static function verifyPasswordForUser(array $user, $password)') === false
    || strpos($auth, "(\$user['customer_password_mode'] ?? 'pin') === 'custom'") === false
    || strpos($auth, 'password_verify($submittedPassword, $passwordHash)') === false
    || strpos($auth, 'hash_equals($customerPassword, $normalizedPassword)') === false) {
    $failures[] = 'Customer login must preserve default PIN compatibility and verify custom password hashes.';
}
if (strpos($auth, 'User::syncCustomerLoginDefaults((int) $user[\'id\'], $user)') === false
    || strpos($auth, 'syncCustomerLoginDefaults((int) $user[\'id\'], $user, true)') !== false) {
    $failures[] = 'Customer login must preserve old password hashes and avoid forced password changes.';
}
$legacyLoginStart = strpos($auth, 'public static function customerLogin(');
$legacyLoginEnd = strpos($auth, 'protected static function setSession(', $legacyLoginStart);
$legacyLogin = substr($auth, $legacyLoginStart, $legacyLoginEnd - $legacyLoginStart);
if (strpos($legacyLogin, 'return self::unifiedLogin($identifier, $password);') === false) {
    $failures[] = 'The legacy customer login entry point must preserve the same PIN policy.';
}
if (strpos($user, 'public static function customerPasswordFromMobile($mobile)') === false
    || strpos($user, 'substr($mobile, -4)') === false
    || strpos($user, '$data[\'password\'] = $customerPassword;') === false
    || strpos($user, 'password_hash(($data[\'password\'] ?? \'\')') === false) {
    $failures[] = 'Customer account creation and password changes must hash the registered mobile suffix.';
}
if (strpos($controller, '$password = User::customerPasswordFromMobile($mobile);') === false
    || strpos($controller, '\'password\' => $password') === false
    || strpos($controller, 'Auth::unifiedLogin($nationalId, $password)') === false) {
    $failures[] = 'Public registration must initialize and verify the customer mobile PIN.';
}
if (strpos($registrationView, 'name="password"') !== false
    || strpos($registrationView, 'name="password_confirmation"') !== false
    || strpos($registrationView, 'چهار رقم آخر شماره موبایل') === false) {
    $failures[] = 'The registration form must explain the mobile PIN and avoid collecting a different password.';
}
if (strpos($controller, 'PasswordReset::userForReset($resetId)') === false
    || strpos($reset, 'User::customerPasswordFromMobile($user[\'mobile\'] ?? \'\')') === false
    || strpos($resetView, 'چهار رقم آخر شماره موبایل') === false) {
    $failures[] = 'Customer password recovery must restore the mobile PIN after code verification.';
}
if (strpos($policy, 'چهار رقم آخر شماره موبایل') === false
    || strpos($policy, 'رمز شخصی') === false
    || strpos($policy, 'قفل ۱۵ دقیقه‌ای') === false) {
    $failures[] = 'The customer credential policy must document the PIN default and optional personal-password mode.';
}
if (strpos($user, 'public static function setCustomCustomerPassword(') === false
    || strpos($user, "'custom'") === false
    || strpos($profileController, "public function changePassword()") === false
    || strpos($profileController, "\$this->requireRole('customer')") === false
    || strpos($profileController, 'Auth::verifyPasswordForUser($user, $current)') === false
    || strpos($profileView, "url('profile/changePassword')") === false) {
    $failures[] = 'Customer self-service password change must require customer role, current-password verification, and a hashed personal password.';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "CUSTOMER_CREDENTIAL_POLICY_STATIC_OK\n";
