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
$healthRoute = trim((string) ($_GET['route'] ?? ''), '/');
if (!in_array($healthRoute, ['health/live', 'health/ready'], true)) {
    Auth::start();
}
