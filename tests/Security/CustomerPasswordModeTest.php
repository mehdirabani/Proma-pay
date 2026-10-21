<?php

$root = dirname(__DIR__, 2);
function to_english_digits($value)
{
    return strtr((string) $value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}
class User
{
    public static function customerPasswordFromMobile($mobile)
    {
        $digits = preg_replace('/\D+/', '', to_english_digits((string) $mobile));
        return strlen($digits) >= 4 ? substr($digits, -4) : '';
    }
}
require_once $root . '/core/Auth.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pinHash = password_hash('5678', PASSWORD_DEFAULT);
$customHash = password_hash('customer-pass-2026', PASSWORD_DEFAULT);
$assert(Auth::verifyPasswordForUser(['role'=>'customer','mobile'=>'0912345678','password_hash'=>$pinHash], '۵۶۷۸'), 'Legacy/default customer PIN mode must keep last-four-mobile login.');
$assert(!Auth::verifyPasswordForUser(['role'=>'customer','mobile'=>'0912345678','customer_password_mode'=>'custom','password_hash'=>$customHash], '5678'), 'Custom mode must reject the predictable mobile PIN.');
$assert(Auth::verifyPasswordForUser(['role'=>'customer','mobile'=>'0912345678','customer_password_mode'=>'custom','password_hash'=>$customHash], 'customer-pass-2026'), 'Custom mode must verify the stored password hash.');
$persianDigitCustomHash = password_hash('۱۲۳۴۵۶۷۸', PASSWORD_DEFAULT);
$assert(!Auth::verifyPasswordForUser(['role'=>'customer','mobile'=>'0912345678','customer_password_mode'=>'custom','password_hash'=>$persianDigitCustomHash], '12345678'), 'Custom passwords must be verified exactly without PIN digit normalization.');
$assert(Auth::verifyPasswordForUser(['role'=>'admin','password_hash'=>password_hash('staff-pass-2026', PASSWORD_DEFAULT)], 'staff-pass-2026'), 'Staff login must continue to verify password hashes.');

$controller = file_get_contents($root . '/controllers/ProfileController.php');
$userModel = file_get_contents($root . '/models/User.php');
$view = file_get_contents($root . '/views/profile/index.php');
$assert(strpos($controller, "public function changePassword()") !== false && strpos($controller, "\$this->requireRole('customer')") !== false, 'Password changes must be restricted to an authenticated customer.');
$assert(strpos($controller, 'Auth::verifyPasswordForUser($user, $current)') !== false && strpos($controller, '$this->onlyPost();') !== false, 'Password changes must verify the current credential and use the protected POST flow.');
$assert(strpos($userModel, "password_hash((string) \$password, PASSWORD_DEFAULT), 'custom'") !== false, 'Custom passwords must be hashed and explicitly marked as custom mode.');
$assert(strpos($view, 'name="current_password"') !== false && strpos($view, 'name="password_confirmation"') !== false, 'The customer profile must expose current and confirmation password fields.');

echo "CUSTOMER_PASSWORD_MODE_OK\n";
