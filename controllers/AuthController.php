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
                redirect('dashboard');
            }
            set_flash('error', 'اطلاعات ورود درست نیست یا حساب کاربری فعال نیست.');
            redirect('auth/login');
        }
        $this->render('auth/login', ['title' => 'ورود به سامانه'], 'auth');
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
