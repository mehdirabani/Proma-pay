<?php

$root = dirname(__DIR__, 2);
$auth = file_get_contents($root . '/controllers/AuthController.php');
$forgotView = file_get_contents($root . '/views/auth/forgot_password.php');
$resetView = file_get_contents($root . '/views/auth/reset_password.php');
$passwordReset = file_get_contents($root . '/models/PasswordReset.php');
$failures = [];

$registerStart = strpos($auth, 'public function register()');
$forgotStart = strpos($auth, 'public function forgotPassword()');
$resetStart = strpos($auth, 'public function resetPassword()');
$registerBody = substr($auth, $registerStart, $forgotStart - $registerStart);
$forgotBody = substr($auth, $forgotStart, $resetStart - $forgotStart);
$resetBody = substr($auth, $resetStart);

if (strpos($registerBody, '$duplicate[\'full_name\']') !== false) $failures[] = 'Registration exposes a duplicate account full name.';
if (strpos($registerBody, '$e->getMessage()') !== false) $failures[] = 'Registration displays internal exception messages.';
if (strpos($forgotBody, '$_SESSION[\'password_reset_mobile_hint\'] =') !== false) $failures[] = 'Password reset stores an account-specific mobile hint in the session.';
if (substr_count($forgotBody, "redirect('auth/forgotPassword')") !== 1) $failures[] = 'Account-dependent outcomes do not converge on the same response route.';
if (substr_count($forgotBody, "redirect('auth/resetPassword')") !== 1) $failures[] = 'Password reset outcomes do not converge on one response route.';
if (strpos($forgotBody, "set_flash('success', 'اگر حساب فعالی با این شناسه وجود داشته باشد، در صورت امکان کد بازیابی ارسال می‌شود.')") === false) $failures[] = 'Password reset is missing its neutral response message.';
if (strpos($resetBody, "if (\$resetId <= 0) {\n            redirect('auth/forgotPassword');") !== false) $failures[] = 'Reset form redirects differently when no account reset is pending.';
if (strpos($resetView, '$mobileHint') !== false || strpos($forgotView, 'کد بازیابی برای موبایل ثبت‌شده ارسال شود') !== false) $failures[] = 'Password reset views reveal whether a registered mobile number exists.';
if (strpos($passwordReset, 'public static function recordRequestAttempt($ipAddress)') === false || strpos($passwordReset, 'public static function userOnCooldown($userId)') === false) $failures[] = 'Password reset lacks IP and account request throttles.';
if (strpos($passwordReset, 'return true;') === false || strpos($passwordReset, 'password_reset.rate_limit') === false) $failures[] = 'Password reset rate limiting does not fail closed and log operational errors.';

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASSWORD_RESET_PRIVACY_STATIC_OK\n";
