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
            $identifier = $_POST['identifier'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $throttle = LoginThrottle::inspect($identifier, $ipAddress);
            if (!empty($throttle['blocked'])) {
                LoginThrottle::progressiveDelay($throttle['failures'] ?? 0);
                ErrorHandler::respond(429, 'اطلاعات ورود صحیح نیست یا امکان ورود موقتاً محدود شده است.', [], ['Retry-After' => (string) (LoginThrottle::LOCK_MINUTES * 60)]);
            }
            if (Auth::unifiedLogin($identifier, $_POST['password'] ?? '')) {
                LoginThrottle::clearSuccessful($identifier, $ipAddress);
                if (Auth::role() === 'operator') {
                    redirect('overdue');
                }
                redirect('dashboard');
            }
            $throttle = LoginThrottle::recordFailure($identifier, $ipAddress);
            LoginThrottle::progressiveDelay($throttle['failures'] ?? 1);
            if (!empty($throttle['blocked'])) {
                ErrorHandler::respond(429, 'اطلاعات ورود صحیح نیست یا امکان ورود موقتاً محدود شده است.', [], ['Retry-After' => (string) (LoginThrottle::LOCK_MINUTES * 60)]);
            }
            set_flash('error', 'اطلاعات ورود صحیح نیست یا امکان ورود موقتاً محدود شده است.');
            redirect('auth/login');
        }
        $this->render('auth/login', ['title' => 'ورود به سامانه'], 'auth');
    }

    public function register()
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        if (is_post()) {
            Csrf::verify();
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $nationalId = trim(to_english_digits($_POST['national_id'] ?? ''));
            $mobile = preg_replace('/\D+/', '', to_english_digits((string) ($_POST['mobile'] ?? '')));
            if (strlen($mobile) === 10 && strpos($mobile, '9') === 0) {
                $mobile = '0' . $mobile;
            }
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = User::customerPasswordFromMobile($mobile);

            if ($fullName === '' || $nationalId === '' || $mobile === '') {
                set_flash('error', 'نام، کد ملی و شماره تماس برای ثبت‌نام الزامی است.');
                redirect('auth/register');
            }
            if (!preg_match('/^\d{8,12}$/', $nationalId)) {
                set_flash('error', 'کد ملی واردشده معتبر نیست.');
                redirect('auth/register');
            }
            if (!preg_match('/^09\d{9}$/', $mobile)) {
                set_flash('error', 'شماره تماس باید با قالب 09xxxxxxxxx وارد شود.');
                redirect('auth/register');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('error', 'ایمیل واردشده معتبر نیست.');
                redirect('auth/register');
            }
            $payload = [
                'role' => 'customer',
                'username' => $nationalId,
                'full_name' => $fullName,
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'email' => $email,
                'password' => $password,
                'status' => 'active',
            ];

            try {
                if ($duplicate = User::findDuplicateCustomer($payload)) {
                    set_flash('error', 'حسابی با این مشخصات قبلاً ثبت شده است. از صفحه ورود یا بازیابی رمز استفاده کنید.');
                    redirect('auth/register');
                }
                $customerId = (int) User::create($payload);
                $referralCode = trim((string) ($_POST['referral_code'] ?? ''));
                if ($referralCode !== '' && class_exists('PluginManager') && PluginManager::isActive('proma-affiliate')) {
                    PluginHooks::dispatch('affiliate.customer.created', ['customer_id' => $customerId, 'referral_code' => $referralCode]);
                }
                if (Auth::unifiedLogin($nationalId, $password)) {
                    set_flash('success', 'ثبت‌نام شما انجام شد. خوش آمدید.');
                    redirect('dashboard');
                }
                set_flash('success', 'ثبت‌نام انجام شد. برای ورود، کد ملی و رمز عبوری را که انتخاب کرده‌اید وارد کنید.');
                redirect('auth/login');
            } catch (Throwable $e) {
                ErrorHandler::log('auth.register', $e, 500);
                set_flash('error', 'ثبت‌نام انجام نشد. اطلاعات را بررسی کنید و دوباره تلاش کنید.');
                redirect('auth/register');
            }
        }

        $this->render('auth/register', ['title' => 'ثبت‌نام مشتری', 'referralCode' => trim((string) ($_GET['ref'] ?? ''))], 'auth');
    }

    public function forgotPassword()
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        if (is_post()) {
            Csrf::verify();
            unset($_SESSION['password_reset_id'], $_SESSION['password_reset_mobile_hint']);
            $settings = Settings::allKeyed();
            if (($settings['password_reset_enabled'] ?? '1') !== '1') {
                set_flash('error', 'بازیابی رمز عبور در حال حاضر فعال نیست.');
                redirect('auth/forgotPassword');
            }

            $identifier = trim((string) ($_POST['identifier'] ?? ''));
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $rateLimited = PasswordReset::recordRequestAttempt($ipAddress);
            $user = $rateLimited ? null : User::findForPasswordReset($identifier);
            if ($user && trim((string) ($user['mobile'] ?? '')) !== '' && !PasswordReset::userOnCooldown((int) $user['id'])) {
                $code = (string) random_int(100000, 999999);
                PasswordReset::cleanup();
                $resetId = PasswordReset::createForUser($user, $code);
                $client = new IppanelClient($settings);
                $result = $client->sendPasswordReset($user['mobile'], $code);
                if ($result['ok'] ?? false) {
                    $_SESSION['password_reset_id'] = $resetId;
                } else {
                    PasswordReset::cancel($resetId);
                }
            }

            set_flash('success', 'اگر حساب فعالی با این شناسه وجود داشته باشد، در صورت امکان کد بازیابی ارسال می‌شود.');
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

        if (is_post()) {
            Csrf::verify();
            $code = trim(to_english_digits($_POST['code'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');

            if (!preg_match('/^\d{6}$/', $code)) {
                set_flash('error', 'کد بازیابی باید ۶ رقم باشد.');
                redirect('auth/resetPassword');
            }
            if ($resetId <= 0) {
                set_flash('error', 'کد بازیابی معتبر نیست یا منقضی شده است.');
                redirect('auth/resetPassword');
            }

            $verified = PasswordReset::verifyCode($resetId, $code);
            if (!($verified['ok'] ?? false)) {
                set_flash('error', $verified['message'] ?? 'کد بازیابی معتبر نیست.');
                redirect('auth/resetPassword');
            }

            $resetUser = PasswordReset::userForReset($resetId);
            if (!$resetUser) {
                set_flash('error', 'کد بازیابی معتبر نیست یا منقضی شده است.');
                redirect('auth/resetPassword');
            }
            if (($resetUser['role'] ?? '') === 'customer') {
                $password = User::customerPasswordFromMobile($resetUser['mobile'] ?? '');
                if ($password === '') {
                    set_flash('error', 'شماره موبایل حساب برای بازیابی رمز معتبر نیست.');
                    redirect('auth/resetPassword');
                }
            } else {
                if ($password !== $confirm) {
                    set_flash('error', 'تکرار رمز عبور با رمز جدید یکسان نیست.');
                    redirect('auth/resetPassword');
                }
                if (mb_strlen($password, 'UTF-8') < 8 || mb_strlen($password, 'UTF-8') > 128) {
                    set_flash('error', 'رمز عبور باید بین ۸ تا ۱۲۸ نویسه باشد.');
                    redirect('auth/resetPassword');
                }
            }
            if (!PasswordReset::complete($resetId, $password)) {
                set_flash('error', 'بازیابی رمز کامل نشد. دوباره درخواست کد کنید.');
                redirect('auth/forgotPassword');
            }

            unset($_SESSION['password_reset_id'], $_SESSION['password_reset_mobile_hint']);
            set_flash('success', 'رمز عبور با موفقیت تغییر کرد. حالا می‌توانید وارد شوید.');
            redirect('auth/login');
        }

        $resetUser = $resetId > 0 ? PasswordReset::userForReset($resetId) : null;
        $this->render('auth/reset_password', [
            'title' => 'ثبت رمز جدید',
            'isCustomerReset' => ($resetUser['role'] ?? '') === 'customer',
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

}
