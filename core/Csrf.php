<?php

class Csrf
{
    public static function token()
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validate($token)
    {
        return is_string($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    public static function verify()
    {
        if (!self::validate($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            echo 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.';
            exit;
        }
    }
}
