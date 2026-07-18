<?php

// Backward-compatible entry point. Installation is performed only by install.php.
$root = __DIR__;
ini_set('expose_php', '0');
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

if (is_file($root . '/installed.lock')) {
    header('Location: index.php?route=auth/login', true, 302);
    exit;
}

if (is_file($root . '/install.php')) {
    header('Location: install.php', true, 302);
    exit;
}

http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>فایل نصب پیدا نشد</title></head>
<body>
  <main>
    <h1>فایل نصب استاندارد پیدا نشد</h1>
    <p>برای نصب تازه، بستهٔ کامل و معتبر پرما پی را دوباره بارگذاری کنید.</p>
  </main>
</body>
</html>
