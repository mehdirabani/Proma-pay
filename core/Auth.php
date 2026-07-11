<?php

class Auth
{
    public static function start()
    {
        $settings = app_config();
        if (session_status() === PHP_SESSION_NONE) {
            session_name($settings['session_name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => is_https_request(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function user()
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return User::find((int) $_SESSION['user_id']);
    }

    public static function id()
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function check()
    {
        return !empty($_SESSION['user_id']);
    }

    public static function login($identifier, $password)
    {
        $identifier = trim(to_english_digits($identifier));
        $user = Model::fetch(
            "SELECT * FROM users
             WHERE status = 'active'
             AND role IN ('admin','operator','lawyer','customer')
             AND (username = :username OR mobile = :mobile OR national_id = :national_id OR email = :email)
             LIMIT 1",
            [
                'username' => $identifier,
                'mobile' => $identifier,
                'national_id' => $identifier,
                'email' => $identifier,
            ]
        );
        if (!$user || !password_verify((string) $password, $user['password_hash'])) {
            return false;
        }
        if (($user['role'] ?? '') === 'customer') {
            User::syncCustomerLoginDefaults((int) $user['id'], $user);
        }
        self::setSession($user);
        return true;
    }

    public static function unifiedLogin($identifier, $password)
    {
        $identifier = trim(to_english_digits($identifier));
        $password = trim(to_english_digits((string) $password));
        if ($identifier === '' || $password === '') {
            return false;
        }
        if (self::login($identifier, $password)) {
            return true;
        }
        return self::customerLogin($identifier, $password);
    }

    public static function customerLogin($nationalId, $mobileLast4)
    {
        $nationalId = trim(to_english_digits($nationalId));
        $identifierDigits = preg_replace('/\D+/', '', $nationalId);
        if (strlen($identifierDigits) < 4) {
            $identifierDigits = '__no_numeric_identifier__';
        }
        $mobileLast4 = preg_replace('/\D+/', '', to_english_digits($mobileLast4));
        if (strlen($mobileLast4) !== 4) {
            return false;
        }
        $users = Model::fetchAll(
            "SELECT * FROM users
             WHERE status = 'active' AND role = 'customer'
             AND (
                username = :identifier_username
                OR national_id = :identifier_national
                OR mobile = :identifier_mobile
                OR email = :identifier_email
                OR username = :digits_username
                OR national_id = :digits_national
                OR mobile = :digits_mobile
             )
             ORDER BY id DESC
             LIMIT 5",
            [
                'identifier_username' => $nationalId,
                'identifier_national' => $nationalId,
                'identifier_mobile' => $nationalId,
                'identifier_email' => $nationalId,
                'digits_username' => $identifierDigits,
                'digits_national' => $identifierDigits,
                'digits_mobile' => $identifierDigits,
            ]
        );
        foreach ($users as $user) {
            $mobileDigits = preg_replace('/\D+/', '', to_english_digits($user['mobile'] ?? ''));
            if (strlen($mobileDigits) < 4 || substr($mobileDigits, -4) !== $mobileLast4) {
                continue;
            }
            User::syncCustomerLoginDefaults((int) $user['id'], $user, true);
            self::setSession($user);
            return true;
        }
        return false;
    }

    protected static function setSession(array $user)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        Model::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    }

    public static function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            redirect('auth/login');
        }
    }

    public static function requireRole($roles)
    {
        self::requireLogin();
        $roles = (array) $roles;
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            $controller = new Controller();
            $controller->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            exit;
        }
    }

    public static function role()
    {
        return $_SESSION['role'] ?? null;
    }

    public static function isStaff()
    {
        return is_staff_role(self::role());
    }

    public static function requireStaff()
    {
        self::requireLogin();
        if (!self::isStaff()) {
            http_response_code(403);
            $controller = new Controller();
            $controller->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            exit;
        }
    }

    public static function canViewUsers()
    {
        return current_user_can_view_users();
    }
}
