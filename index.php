<?php

require __DIR__ . '/bootstrap.php';

if (!is_file(__DIR__ . '/installed.lock')) {
    header('Location: install.php');
    exit;
}

$router = new Router();
$router->dispatch();
