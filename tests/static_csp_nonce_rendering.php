<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ErrorHandler.php';
$errorHandler = (string) file_get_contents($root . '/core/ErrorHandler.php');
$controller = (string) file_get_contents($root . '/core/Controller.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($errorHandler, "script-src 'self' 'nonce-"), 'CSP must allow only same-origin scripts and nonce-authorized inline scripts.');
$assert(str_contains($errorHandler, "style-src 'self' 'unsafe-inline'"), 'CSP must allow the app stylesheet attributes and inline print rules.');
$assert(!preg_match("/script-src[^;]*'unsafe-inline'/", $errorHandler), 'CSP must not enable arbitrary inline JavaScript.');
$assert(str_contains($errorHandler, 'function nonceInlineScripts('), 'Trusted rendered inline scripts must receive the per-response nonce.');
$assert(substr_count($controller, 'ErrorHandler::nonceInlineScripts(') === 2, 'Both standalone and layout views must receive inline script nonces.');
$nonce = ErrorHandler::cspNonce();
$rendered = ErrorHandler::nonceInlineScripts('<script>window.ready=true;</script><script src="/app.js"></script><script nonce="existing">window.existing=true;</script>');
$assert((bool) preg_match('/<script nonce="' . preg_quote($nonce, '/') . '">window\.ready=true;<\/script>/', $rendered), 'An inline script must receive the response nonce.');
$assert(str_contains($rendered, '<script src="/app.js"></script>'), 'Same-origin external scripts must remain unchanged.');
$assert(str_contains($rendered, '<script nonce="existing">'), 'An explicitly nonced script must not have its nonce replaced.');

echo "CSP_NONCE_RENDERING_STATIC_OK\n";
