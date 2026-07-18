<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/core/HttpException.php';
require_once $root . '/core/ErrorHandler.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ([400, 401, 403, 404, 405, 408, 409, 410, 413, 415, 419, 422, 423, 429, 500, 501, 502, 503, 504] as $status) {
    [$title, $message] = ErrorHandler::statusMeta($status);
    $payload = ErrorHandler::payload($status);
    $assert($title !== '' && $message !== '', 'Missing status metadata for HTTP ' . $status . '.');
    $assert(($payload['status'] ?? 0) === $status, 'Payload status is incorrect for HTTP ' . $status . '.');
    $assert(!empty($payload['error']['request_id']), 'Request ID is missing for HTTP ' . $status . '.');
}

$payload = ErrorHandler::payload(500, 'Database failure at /var/www/private.php');
$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$assert(strpos($encoded, 'trace') === false, 'JSON error payload exposes a stack trace field.');
$assert(strpos((string) $encoded, 'SQLSTATE') === false, 'JSON error payload exposes SQL details.');

$view = (string) file_get_contents($root . '/views/errors/system.php');
$assert(strpos($view, 'onclick=') === false, 'Error page must not depend on inline JavaScript.');
$assert(strpos($view, 'dir="rtl"') !== false, 'Error page must declare RTL direction.');

echo "ERROR_RESPONSE_V136_OK\n";
