<?php

require_once __DIR__ . '/helpers/functions.php';

date_default_timezone_set(app_config('timezone', 'Asia/Tehran'));

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php',
        __DIR__ . '/helpers/' . $class . '.php',
        __DIR__ . '/controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

ErrorHandler::install();
RequestTelemetry::boot();
RequestFloodGuard::enforce();
try {
    RequestMaintenanceLock::enter();
} catch (RuntimeException $e) {
    if (PHP_SAPI === 'cli') throw $e;
    http_response_code(503);
    header('Retry-After: 10');
    echo 'سامانه موقتاً برای بازیابی بکاپ در دسترس نیست. چند لحظه بعد دوباره تلاش کنید.';
    exit;
}
$healthRoute = trim((string) ($_GET['route'] ?? ''), '/');
if (!defined('PROMA_API_REQUEST') && !in_array($healthRoute, ['health/live', 'health/ready'], true)) {
    Auth::start();
}
