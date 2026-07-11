<?php

$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
if (PHP_SAPI === 'cli') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require __DIR__ . '/../bootstrap.php';

$result = Event::processDueReminders();

echo json_encode([
    'ok' => true,
    'message' => 'بررسی اعلان‌های تقویم انجام شد.',
    'result' => $result,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
