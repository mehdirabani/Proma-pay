<?php

class ErrorHandler
{
    protected static $installed = false;
    protected static $handling = false;
    protected static $requestId;

    public static function install()
    {
        if (self::$installed) {
            return;
        }

        self::$installed = true;
        self::requestId();
        ini_set('expose_php', '0');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        header('X-Request-Id: ' . self::$requestId);
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handlePhpError']);
        register_shutdown_function([self::class, 'handleShutdown']);

        if (!self::isDebug()) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }
    }

    public static function requestId()
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        try {
            self::$requestId = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            self::$requestId = dechex((int) microtime(true)) . dechex(mt_rand());
        }
        return self::$requestId;
    }

    public static function abort($status, $message = '', array $details = [], array $headers = [])
    {
        throw new HttpException($status, $message, $details, $headers);
    }

    public static function respond($status, $message = '', array $details = [], array $headers = [])
    {
        self::emit((int) $status, (string) $message, $details, $headers);
        exit;
    }

    public static function handleException(Throwable $exception)
    {
        if (self::$handling) {
            self::emergencyResponse();
            return;
        }

        self::$handling = true;
        $status = $exception instanceof HttpException ? $exception->status() : 500;
        $details = $exception instanceof HttpException ? $exception->details() : [];
        $headers = $exception instanceof HttpException ? $exception->headers() : [];
        $message = $exception instanceof HttpException ? $exception->getMessage() : '';

        if (!($exception instanceof HttpException) || $status >= 500) {
            self::log('exception', $exception, $status);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[PromaPay][%s][HTTP_%d] %s\n", self::requestId(), $status, self::sanitizeLogText($exception->getMessage())));
            exit(1);
        }

        self::emit($status, $message, $details, $headers);
    }

    public static function handlePhpError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::log('php_error', new ErrorException($message, 0, $severity, $file, $line), 500);
        return false;
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if (!$error || self::$handling) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatal, true)) {
            return;
        }

        self::$handling = true;
        self::log('fatal', new ErrorException(
            (string) ($error['message'] ?? 'Fatal error'),
            0,
            (int) $error['type'],
            (string) ($error['file'] ?? ''),
            (int) ($error['line'] ?? 0)
        ), 500);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[PromaPay][%s][HTTP_500] Fatal error\n", self::requestId()));
            exit(1);
        }

        self::emit(500);
    }

    public static function isJsonRequest()
    {
        if (is_ajax_request()) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false || strpos($accept, '+json') !== false;
    }

    public static function statusMeta($status)
    {
        $items = [
            400 => ['درخواست نامعتبر', 'اطلاعات ارسال‌شده معتبر نیست.'],
            401 => ['ورود لازم است', 'برای ادامه، ابتدا وارد حساب کاربری شوید.'],
            403 => ['دسترسی مجاز نیست', 'شما اجازه انجام این عملیات را ندارید.'],
            404 => ['صفحه پیدا نشد', 'نشانی مورد نظر وجود ندارد یا منتقل شده است.'],
            405 => ['روش ارسال درخواست مجاز نیست', 'این آدرس از روش استفاده‌شده پشتیبانی نمی‌کند. صفحه را دوباره باز کرده و عملیات را از مسیر اصلی انجام دهید.'],
            408 => ['زمان درخواست به پایان رسید', 'دوباره تلاش کنید.'],
            409 => ['تعارض اطلاعات', 'این عملیات با وضعیت فعلی اطلاعات سازگار نیست.'],
            410 => ['مورد در دسترس نیست', 'این مورد دیگر قابل استفاده نیست.'],
            413 => ['فایل یا درخواست بزرگ است', 'حجم داده ارسال‌شده بیش از حد مجاز است.'],
            415 => ['نوع فایل پشتیبانی نمی‌شود', 'نوع داده یا فایل ارسالی معتبر نیست.'],
            419 => ['نشست شما منقضی شده است', 'برای ادامه صفحه را تازه‌سازی و دوباره تلاش کنید.'],
            422 => ['اطلاعات نیاز به اصلاح دارد', 'فیلدهای مشخص‌شده را بررسی کنید.'],
            423 => ['حساب کاربری موقتاً قفل شده است', 'برای بررسی وضعیت حساب با پشتیبانی یا مدیر سامانه تماس بگیرید.'],
            429 => ['تلاش بیش از حد مجاز', 'چند دقیقه بعد دوباره تلاش کنید.'],
            500 => ['مشکل داخلی سامانه', 'در پردازش درخواست خطایی رخ داد. تیم پشتیبانی با شناسه پیگیری می‌تواند آن را بررسی کند.'],
            501 => ['این قابلیت در دسترس نیست', 'این قابلیت در نسخه فعلی سامانه فعال نیست.'],
            502 => ['ارتباط با سرویس برقرار نشد', 'سرویس واسط پاسخ معتبر ارسال نکرد.'],
            503 => ['سامانه موقتاً در دسترس نیست', 'سامانه در حال نگهداری است. چند دقیقه دیگر دوباره تلاش کنید.'],
            504 => ['پاسخ سرویس زمان‌بر شد', 'سرویس بیرونی در زمان مقرر پاسخ نداد.'],
        ];
        return $items[(int) $status] ?? $items[500];
    }

    public static function payload($status, $message = '', array $details = [])
    {
        [$title, $fallback] = self::statusMeta($status);
        $message = trim((string) $message) ?: $fallback;
        return [
            'ok' => false,
            'status' => (int) $status,
            'error' => [
                'code' => 'HTTP_' . (int) $status,
                'title' => $title,
                'message' => $message,
                'request_id' => self::requestId(),
                'details' => $details ?: null,
            ],
        ];
    }

    public static function log($kind, Throwable $exception, $status = 500)
    {
        $line = sprintf(
            '[PromaPay][%s][%s][HTTP_%d] %s: %s at %s:%d',
            self::requestId(),
            $kind,
            (int) $status,
            get_class($exception),
            self::sanitizeLogText($exception->getMessage()),
            basename(str_replace('\\', '/', $exception->getFile())),
            (int) $exception->getLine()
        );
        error_log($line);
    }

    protected static function emit($status, $message = '', array $details = [], array $headers = [])
    {
        $status = max(400, min(599, (int) $status));
        [$title, $fallback] = self::statusMeta($status);
        $message = trim((string) $message) ?: $fallback;

        if (!headers_sent()) {
            http_response_code($status);
            header('X-Request-Id: ' . self::requestId());
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            foreach ($headers as $name => $value) {
                if (is_string($name) && is_scalar($value)) {
                    header($name . ': ' . $value);
                }
            }
        }

        if (self::isJsonRequest()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(self::payload($status, $message, $details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        self::renderHtml($status, $title, $message, $details);
    }

    protected static function renderHtml($status, $title, $message, array $details)
    {
        $requestId = self::requestId();
        $view = dirname(__DIR__) . '/views/errors/system.php';
        if (is_file($view)) {
            require $view;
            return;
        }
        self::emergencyResponse();
    }

    protected static function emergencyResponse()
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>خطای سامانه</title><body><h1>سامانه موقتاً در دسترس نیست</h1><p>لطفاً چند دقیقه دیگر دوباره تلاش کنید.</p><p>شناسه پیگیری: ' . htmlspecialchars(self::requestId(), ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    }

    protected static function isDebug()
    {
        return in_array(strtolower((string) getenv('APP_ENV')), ['local', 'development', 'testing'], true)
            && filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
    }

    protected static function sanitizeLogText($message)
    {
        $message = preg_replace('/(password|secret|token|api[_-]?key)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', (string) $message);
        return mb_substr((string) $message, 0, 1000, 'UTF-8');
    }
}
