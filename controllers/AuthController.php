<?php

class AuthController extends Controller
{
    public function login()
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
        if (is_post()) {
            Csrf::verify();
            if (Auth::unifiedLogin($_POST['identifier'] ?? '', $_POST['password'] ?? '')) {
                if (Auth::role() === 'operator') {
                    redirect('overdue');
                }
                redirect('dashboard');
            }
            set_flash('error', 'اطلاعات ورود درست نیست یا حساب کاربری فعال نیست.');
            redirect('auth/login');
        }
        $this->render('auth/login', ['title' => 'ورود به سامانه'], 'auth');
    }

    public function forgotPassword()
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        if (is_post()) {
            Csrf::verify();
            $settings = Settings::allKeyed();
            if (($settings['password_reset_enabled'] ?? '1') !== '1') {
                set_flash('error', 'بازیابی رمز عبور در حال حاضر فعال نیست.');
                redirect('auth/forgotPassword');
            }

            $user = User::findForPasswordReset($_POST['identifier'] ?? '');
            if (!$user || trim((string) ($user['mobile'] ?? '')) === '') {
                set_flash('error', 'حساب فعالی با این شناسه و موبایل قابل ارسال پیدا نشد.');
                redirect('auth/forgotPassword');
            }

            $code = (string) random_int(100000, 999999);
            PasswordReset::cleanup();
            $resetId = PasswordReset::createForUser($user, $code);
            $client = new IppanelClient($settings);
            $result = $client->sendPasswordReset($user['mobile'], $code);
            if (!($result['ok'] ?? false)) {
                set_flash('error', $result['message'] ?? 'ارسال پیامک بازیابی رمز ناموفق بود.');
                redirect('auth/forgotPassword');
            }

            $_SESSION['password_reset_id'] = $resetId;
            $_SESSION['password_reset_mobile_hint'] = self::maskMobile($user['mobile']);
            set_flash('success', 'کد بازیابی رمز برای شماره ثبت‌شده ارسال شد.');
            redirect('auth/resetPassword');
        }

        $this->render('auth/forgot_password', ['title' => 'فراموشی رمز عبور'], 'auth');
    }

    public function resetPassword()
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        $resetId = (int) ($_SESSION['password_reset_id'] ?? 0);
        if ($resetId <= 0) {
            redirect('auth/forgotPassword');
        }

        if (is_post()) {
            Csrf::verify();
            $code = trim(to_english_digits($_POST['code'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');

            if (!preg_match('/^\d{6}$/', $code)) {
                set_flash('error', 'کد بازیابی باید ۶ رقم باشد.');
                redirect('auth/resetPassword');
            }
            if (mb_strlen($password, 'UTF-8') < 8) {
                set_flash('error', 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
                redirect('auth/resetPassword');
            }
            if ($password !== $confirm) {
                set_flash('error', 'تکرار رمز عبور با رمز جدید یکسان نیست.');
                redirect('auth/resetPassword');
            }

            $verified = PasswordReset::verifyCode($resetId, $code);
            if (!($verified['ok'] ?? false)) {
                set_flash('error', $verified['message'] ?? 'کد بازیابی معتبر نیست.');
                redirect('auth/resetPassword');
            }
            if (!PasswordReset::complete($resetId, $password)) {
                set_flash('error', 'بازیابی رمز کامل نشد. دوباره درخواست کد کنید.');
                redirect('auth/forgotPassword');
            }

            unset($_SESSION['password_reset_id'], $_SESSION['password_reset_mobile_hint']);
            set_flash('success', 'رمز عبور با موفقیت تغییر کرد. حالا می‌توانید وارد شوید.');
            redirect('auth/login');
        }

        $this->render('auth/reset_password', [
            'title' => 'ثبت رمز جدید',
            'mobileHint' => $_SESSION['password_reset_mobile_hint'] ?? '',
        ], 'auth');
    }

    public function logout()
    {
        $this->onlyPost();
        Auth::logout();
        if (is_file(__DIR__ . '/../installed.lock')) {
            redirect('auth/login');
        }
        header('Location: install.php');
        exit;
    }

    protected static function maskMobile($mobile)
    {
        $mobile = to_english_digits((string) $mobile);
        if (strlen($mobile) < 7) {
            return $mobile;
        }
        return substr($mobile, 0, 4) . '***' . substr($mobile, -4);
    }
}
