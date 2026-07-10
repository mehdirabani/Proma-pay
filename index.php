<?php

require __DIR__ . '/bootstrap.php';

if (!is_file(__DIR__ . '/installed.lock')) {
    header('Location: ' . (is_file(__DIR__ . '/installer.php') ? 'installer.php' : 'install.php'));
    exit;
}

$router = new Router();
$router->dispatch();
