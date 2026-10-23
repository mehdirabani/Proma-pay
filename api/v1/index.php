<?php

declare(strict_types=1);

if (!is_file(dirname(__DIR__, 2) . '/installed.lock')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"error":{"code":"application_not_installed"}}';
    exit;
}

if (!defined('PROMA_API_REQUEST')) {
    define('PROMA_API_REQUEST', true);
}
require_once dirname(__DIR__, 2) . '/bootstrap.php';
MerchantApiGateway::dispatch();
