<?php

require __DIR__ . '/bootstrap.php';

$requestedRoute = trim((string) ($_GET['route'] ?? ''), '/');
if (!is_file(__DIR__ . '/installed.lock') && !in_array($requestedRoute, ['health/live', 'health/ready'], true)) {
    header('Location: ' . (is_file(__DIR__ . '/installer.php') ? 'installer.php' : 'install.php'));
    exit;
}

$router = new Router();
$router->dispatch();
