<?php

class Auth
{
    protected static $releasedFlash = [];
    protected static $sessionReleased = false;

    public static function start()
    {
        $settings = app_config();
        if (session_status() === PHP_SESSION_NONE) {
            self::ensureWritableSessionPath();
            session_name($settings['session_name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => is_https_request(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
            self::$sessionReleased = false;
        }
    }

    protected static function ensureWritableSessionPath()
    {
        $configuredPath = trim((string) getenv('PROMA_SESSION_SAVE_PATH'));
        if ($configuredPath !== '' && is_dir($configuredPath) && is_writable($configuredPath)) {
            ini_set('session.save_path', $configuredPath);
            return;
        }

        $currentPath = trim((string) ini_get('session.save_path'));
        if (strpos($currentPath, ';') !== false) {
            $parts = explode(';', $currentPath);
            $currentPath = trim((string) end($parts));
        }
        if ($currentPath !== '' && is_dir($currentPath) && is_writable($currentPath)) {
            return;
        }

        $fallbackPath = dirname(__DIR__) . '/storage/cache/sessions';
        if ((is_dir($fallbackPath) || mkdir($fallbackPath, 0775, true)) && is_writable($fallbackPath)) {
            ini_set('session.save_path', $fallbackPath);
        }
    }

    public static function releaseSessionLock()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        Csrf::token();
        self::$releasedFlash = array_merge(
            self::$releasedFlash,
            is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : []
        );
        unset($_SESSION['_flash']);
        session_write_close();
        self::$sessionReleased = true;
        return true;
    }

    public static function ensureSessionWritable()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (session_status() === PHP_SESSION_DISABLED) {
            return false;
        }

        self::ensureWritableSessionPath();
        if (!@session_start()) {
            return false;
        }
        self::$sessionReleased = false;
        return true;
    }


    public static function commitSessionWrite()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        session_write_close();
        self::$sessionReleased = true;
        return true;
    }

    public static function sessionWasReleased()
    {
        return self::$sessionReleased;
    }

    public static function takeReleasedFlash($key)
    {
        if (!array_key_exists($key, self::$releasedFlash)) {
            return null;
        }
        $value = self::$releasedFlash[$key];
        unset(self::$releasedFlash[$key]);
        return $value;
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
             WHERE status = 'active' AND archived_at IS NULL
             AND (username = :username OR mobile = :mobile OR national_id = :national_id OR email = :email)
             LIMIT 1",
            [
                'username' => $identifier,
                'mobile' => $identifier,
                'national_id' => $identifier,
                'email' => $identifier,
            ]
        );
        if (!$user) {
            return false;
        }
        if (!self::verifyPasswordForUser($user, (string) $password)
            || !PluginManager::roleCanLogin($user['role'] ?? '', $user)) {
            return false;
        }
        if (($user['role'] ?? '') === 'customer') {
            // Preserve the customer PIN rule without rewriting existing password hashes during login.
            User::syncCustomerLoginDefaults((int) $user['id'], $user);
        }
        self::setSession($user);
        return true;
    }

    public static function verifyPasswordForUser(array $user, $password)
    {
        $submittedPassword = (string) $password;
        $normalizedPassword = to_english_digits($submittedPassword);
        if (($user['role'] ?? '') === 'customer') {
            if (($user['customer_password_mode'] ?? 'pin') === 'custom') {
                $passwordHash = (string) ($user['password_hash'] ?? '');
                return $passwordHash !== '' && password_verify($submittedPassword, $passwordHash);
            }
            $customerPassword = User::customerPasswordFromMobile($user['mobile'] ?? '');
            return $customerPassword !== '' && hash_equals($customerPassword, $normalizedPassword);
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        return $passwordHash !== '' && (
            password_verify($submittedPassword, $passwordHash)
            || ($normalizedPassword !== $submittedPassword && password_verify($normalizedPassword, $passwordHash))
        );
    }

    /** Keep the current session valid after a verified self-service password change. */
    public static function refreshPasswordFingerprint()
    {
        if (!self::ensureSessionWritable() || empty($_SESSION['user_id'])) {
            return false;
        }
        $user = Model::fetch('SELECT password_hash FROM users WHERE id = ? LIMIT 1', [(int) $_SESSION['user_id']]);
        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash === '') {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['auth_password_fingerprint'] = hash('sha256', $passwordHash);
        return true;
    }

    public static function unifiedLogin($identifier, $password)
    {
        $identifier = trim(to_english_digits($identifier));
        $password = (string) $password;
        if ($identifier === '' || $password === '') {
            return false;
        }
        return self::login($identifier, $password);
    }

    public static function customerLogin($identifier, $password)
    {
        return self::unifiedLogin($identifier, $password);
    }

    protected static function setSession(array $user)
    {
        if (!self::ensureSessionWritable()) {
            throw new RuntimeException('نشست کاربری قابل نوشتن نیست.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $authState = Model::fetch('SELECT password_hash FROM users WHERE id = ? LIMIT 1', [(int) $user['id']]);
        if (!$authState || trim((string) ($authState['password_hash'] ?? '')) === '') {
            throw new RuntimeException('وضعیت امنیتی حساب کاربری قابل بارگذاری نیست.');
        }
        $_SESSION['auth_password_fingerprint'] = hash('sha256', (string) $authState['password_hash']);
        Model::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    }

    public static function logout()
    {
        self::ensureSessionWritable();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin()
    {
        if (!empty($_SESSION['user_id']) && !PluginManager::roleAvailable(self::role())) {
            self::logout();
            ErrorHandler::abort(403);
        }
        if (!self::check()) {
            if (is_post()) {
                ErrorHandler::abort(419);
            }
            redirect('auth/login');
        }

        $user = Model::fetch(
            'SELECT role, status, archived_at, password_hash FROM users WHERE id = ? LIMIT 1',
            [(int) $_SESSION['user_id']]
        );
        $passwordHash = (string) ($user['password_hash'] ?? '');
        $sessionFingerprint = $_SESSION['auth_password_fingerprint'] ?? '';
        $passwordSessionValid = $passwordHash !== ''
            && is_string($sessionFingerprint)
            && hash_equals(hash('sha256', $passwordHash), $sessionFingerprint);
        if (!$user || ($user['status'] ?? '') !== 'active' || !empty($user['archived_at'])
            || ($user['role'] ?? '') !== (string) ($_SESSION['role'] ?? '')
            || !$passwordSessionValid) {
            self::logout();
            ErrorHandler::abort(403);
        }
    }

    public static function requireRole($roles)
    {
        self::requireLogin();
        $roles = (array) $roles;
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            ErrorHandler::abort(403);
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
            ErrorHandler::abort(403);
        }
    }

    public static function canViewUsers()
    {
        return current_user_can_view_users();
    }
}
