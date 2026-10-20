<?php

// Regression: standard core route slugs resolve, while path-like controller
// segments never reach the dynamic require path (including Windows separators).
require dirname(__DIR__) . '/core/Router.php';

$resolver = new ReflectionMethod(Router::class, 'controllerTarget');

$router = new Router();

$safe = $resolver->invoke($router, 'auth');
if (!is_array($safe) || $safe[0] !== 'AuthController' || basename($safe[1]) !== 'AuthController.php') {
    fwrite(STDERR, "A valid core route did not resolve to its controller.\n");
    exit(1);
}

foreach (['../auth', '..\\..\\core\\Auth', 'plugin/sign-connect'] as $unsafe) {
    if ($resolver->invoke($router, $unsafe) !== null) {
        fwrite(STDERR, "An unsafe controller path was accepted: " . $unsafe . "\n");
        exit(1);
    }
}

echo "Controller route path regression passed.\n";
