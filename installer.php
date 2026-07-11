<?php

require __DIR__ . '/helpers/functions.php';

session_name(app_config('session_name', 'proma_pay_session'));
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['installer_token'])) {
    $_SESSION['installer_token'] = bin2hex(random_bytes(24));
}

$dumpPath = __DIR__ . '/database/proma-pay-install.sql';
$installedLock = __DIR__ . '/installed.lock';

if (is_file($installedLock)) {
    echo installer_page('نصب انجام شده است', '<div class="notice error">سامانه قبلاً نصب شده است. برای نصب دوباره ابتدا از فایل‌ها و دیتابیس بکاپ بگیرید و سپس installed.lock را حذف کنید.</div><a class="btn" href="index.php">ورود به سامانه</a>');
    exit;
}

$message = '';

if (is_post()) {
    try {
        installer_check_token();
        $db = [
            'host' => trim((string) ($_POST['host'] ?? 'localhost')),
            'database' => trim((string) ($_POST['database'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'charset' => trim((string) ($_POST['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
        ];
        if ($db['database'] === '' || $db['username'] === '') {
            throw new RuntimeException('نام دیتابیس و نام کاربری دیتابیس الزامی است.');
        }
        if (!is_file($dumpPath) || filesize($dumpPath) < 100) {
            throw new RuntimeException('فایل انتقال دیتابیس پیدا نشد. فایل database/proma-pay-install.sql باید کنار بسته نصبی وجود داشته باشد. برای نصب تازه می‌توانید از install.php استفاده کنید.');
        }

        installer_ensure_writable();
        $pdo = installer_pdo($db);
        installer_import_dump($pdo, $dumpPath);
        installer_run_migrations($pdo);
        installer_seed_runtime_defaults($pdo);

        $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
        if (!empty($_POST['update_callback_base_url']) && $siteUrl !== '') {
            installer_save_setting($pdo, 'callback_base_url', rtrim($siteUrl, '/'), false);
        }

        installer_maybe_create_admin($pdo, $_POST);
        installer_write_database_config($db);
        if (file_put_contents($installedLock, "installed=" . date('c') . "\ninstaller=proma-pay\n") === false) {
            throw new RuntimeException('ساخت فایل installed.lock انجام نشد.');
        }
        unset($_SESSION['installer_token']);
        echo installer_page('نصب کامل شد', '<div class="notice success">انتقال فایل‌ها و دیتابیس با موفقیت انجام شد. اکنون می‌توانید وارد سامانه شوید.</div><div class="notice warning">برای امنیت، پس از اطمینان از ورود، فایل installer.php و فایل database/proma-pay-install.sql را از هاست حذف کنید.</div><a class="btn" href="index.php?route=auth/login">ورود به سامانه</a>');
        exit;
    } catch (Throwable $e) {
        installer_log_error($e);
        $message = '<div class="notice error">' . e($e->getMessage()) . '</div>';
    }
}

$dumpInfo = is_file($dumpPath)
    ? '<span class="ok">آماده</span><small>' . e(basename($dumpPath)) . ' - ' . e(installer_human_size(filesize($dumpPath))) . '</small>'
    : '<span class="bad">پیدا نشد</span><small>برای انتقال کامل دیتابیس، فایل database/proma-pay-install.sql باید داخل بسته باشد.</small>';
$requirements = installer_requirements_html();
$detectedUrl = detected_base_url();

echo installer_page('بسته نصبی آسان Proma Pay', $message . '
    <div class="hero">
        <div>
            <h1>بسته نصبی آسان Proma Pay</h1>
            <p>این نصب‌کننده فایل انتقال دیتابیس را import می‌کند، migrationهای نسخه فعلی را اجرا می‌کند و تنظیمات اتصال را روی هاست جدید می‌سازد.</p>
        </div>
        <div class="status-box">' . $dumpInfo . '</div>
    </div>
    ' . $requirements . '
    <form method="post" class="install-form">
        ' . installer_token_field() . '
        <fieldset>
            <legend>اتصال پایگاه داده</legend>
            <label>میزبان دیتابیس<input name="host" value="localhost" required dir="ltr"></label>
            <label>نام دیتابیس<input name="database" required dir="ltr"></label>
            <label>نام کاربری دیتابیس<input name="username" required dir="ltr"></label>
            <label>رمز عبور دیتابیس<input name="password" type="password" dir="ltr"></label>
            <label>کدبندی<input name="charset" value="utf8mb4" required dir="ltr"></label>
        </fieldset>

        <fieldset>
            <legend>تنظیم دامنه و SSL</legend>
            <label class="full">نشانی فعلی سایت<input name="site_url" value="' . e($detectedUrl) . '" dir="ltr" placeholder="https://example.com"></label>
            <label class="check full"><input type="checkbox" name="update_callback_base_url" value="1" checked><span>نشانی بازگشت درگاه پرداخت با همین دامنه به‌روزرسانی شود</span></label>
            <div class="notice info full">اگر هاست SSL دارد، نشانی را با https وارد کنید. برنامه برای فایل‌ها و لینک‌های داخلی از مسیر نسبی استفاده می‌کند تا خطای mixed content ایجاد نشود.</div>
        </fieldset>

        <fieldset>
            <legend>مدیر اضطراری اختیاری</legend>
            <label>نام کامل مدیر<input name="admin_full_name" placeholder="در صورت نیاز"></label>
            <label>نام کاربری مدیر<input name="admin_username" dir="ltr" placeholder="admin"></label>
            <label>رمز عبور مدیر<input name="admin_password" type="password" placeholder="حداقل ۸ کاراکتر"></label>
            <label>موبایل مدیر<input name="admin_mobile" inputmode="tel"></label>
            <label class="full">ایمیل مدیر<input name="admin_email" type="email" dir="ltr"></label>
            <div class="notice warning full">اگر نام کاربری و رمز وارد شود، یک مدیر فعال ساخته می‌شود یا رمز همان مدیر به‌روزرسانی می‌شود. اگر خالی بماند، مدیران موجود در دیتابیس منتقل‌شده حفظ می‌شوند.</div>
        </fieldset>

        <button class="btn" type="submit">شروع انتقال و نصب</button>
        <a class="btn secondary" href="install.php">نصب تازه بدون دیتای انتقالی</a>
    </form>');

function installer_pdo(array $db)
{
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['database'] . ';charset=' . $db['charset'];
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }
    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
    }
    $pdo = new PDO($dsn, $db['username'], $db['password'], $options);
    $pdo->exec("SET NAMES " . preg_replace('/[^a-z0-9_]/i', '', $db['charset']));
    return $pdo;
}

function installer_import_dump(PDO $pdo, $path)
{
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('فایل SQL انتقالی قابل خواندن نیست.');
    }
    foreach (installer_split_sql($sql) as $statement) {
        installer_execute_sql($pdo, $statement);
    }
}

function installer_run_migrations(PDO $pdo)
{
    $dir = __DIR__ . '/database/migrations';
    if (!is_dir($dir)) {
        return;
    }
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            continue;
        }
        foreach (installer_split_sql($sql) as $statement) {
            try {
                installer_execute_sql($pdo, $statement);
            } catch (PDOException $e) {
                if (!installer_is_tolerable_migration_error($e)) {
                    throw new RuntimeException('اجرای migration ' . basename($file) . ' ناموفق بود: ' . $e->getMessage(), 0, $e);
                }
            }
        }
    }
}

function installer_execute_sql(PDO $pdo, $statement)
{
    $statement = trim((string) $statement);
    if ($statement === '') {
        return;
    }
    $stmt = $pdo->query($statement);
    if ($stmt instanceof PDOStatement) {
        installer_drain_statement($stmt);
    }
}

function installer_drain_statement(PDOStatement $stmt)
{
    do {
        if ($stmt->columnCount() > 0) {
            $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        try {
            $hasMore = $stmt->nextRowset();
        } catch (Throwable $e) {
            $hasMore = false;
        }
    } while ($hasMore);
    $stmt->closeCursor();
}

function installer_split_sql($sql)
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', (string) $sql);
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]))) {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        if ($quote === null && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        // Keep MySQL executable comments such as /*!40101 SET ... */; dumps
        // use them for foreign-key and session settings during import.
        if ($quote === null && $char === '/' && $next === '*' && substr($sql, $i, 3) !== '/*!') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            continue;
        }

        if (($char === "'" || $char === '"' || $char === '`')) {
            if ($quote === null) {
                $quote = $char;
            } elseif ($quote === $char) {
                if ($char !== '`' && $next === $char) {
                    $buffer .= $char . $next;
                    $i++;
                    continue;
                }
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $quote = null;
                }
            }
        }

        if ($quote === null && $char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function installer_is_tolerable_migration_error(PDOException $e)
{
    $driverCode = (int) ($e->errorInfo[1] ?? 0);
    return in_array($driverCode, [1050, 1060, 1061, 1068, 1091, 1826], true);
}

function installer_seed_runtime_defaults(PDO $pdo)
{
    installer_save_setting($pdo, 'system_name', 'پروما', false, true);
    installer_save_setting($pdo, 'logo_text', 'پروما', false, true);
    installer_save_setting($pdo, 'footer_text', 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani', false, true);
    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, is_secret) VALUES ('calendar_cron_token', ?, 1)
        ON DUPLICATE KEY UPDATE setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value), is_secret = 1");
    $stmt->execute([$token]);
}

function installer_save_setting(PDO $pdo, $key, $value, $secret = false, $insertOnly = false)
{
    if ($insertOnly) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)');
    } else {
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret)');
    }
    $stmt->execute([(string) $key, (string) $value, $secret ? 1 : 0]);
}

function installer_maybe_create_admin(PDO $pdo, array $input)
{
    $username = trim((string) ($input['admin_username'] ?? ''));
    $password = (string) ($input['admin_password'] ?? '');
    if ($username !== '' || $password !== '') {
        if ($username === '' || mb_strlen($password, 'UTF-8') < 8) {
            throw new RuntimeException('برای ساخت مدیر اضطراری، نام کاربری و رمز عبور حداقل ۸ کاراکتری لازم است.');
        }
        $fullName = trim((string) ($input['admin_full_name'] ?? 'مدیر سامانه'));
        $mobile = to_english_digits($input['admin_mobile'] ?? '');
        $email = trim((string) ($input['admin_email'] ?? ''));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (role, username, full_name, mobile, email, password_hash, status, created_at)
            VALUES ('admin', ?, ?, ?, ?, ?, 'active', NOW())
            ON DUPLICATE KEY UPDATE role = 'admin', full_name = VALUES(full_name), mobile = VALUES(mobile), email = VALUES(email), password_hash = VALUES(password_hash), status = 'active', updated_at = NOW()");
        $stmt->execute([$username, $fullName, $mobile ?: null, $email ?: null, $hash]);
        return;
    }

    $adminCount = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn() ?: 0);
    if ($adminCount < 1) {
        throw new RuntimeException('در دیتابیس منتقل‌شده مدیر فعال وجود ندارد. بخش مدیر اضطراری را تکمیل کنید.');
    }
}

function installer_write_database_config(array $db)
{
    $content = "<?php\n\nreturn [\n"
        . "    'host' => " . var_export($db['host'], true) . ",\n"
        . "    'database' => " . var_export($db['database'], true) . ",\n"
        . "    'username' => " . var_export($db['username'], true) . ",\n"
        . "    'password' => " . var_export($db['password'], true) . ",\n"
        . "    'charset' => " . var_export($db['charset'], true) . ",\n"
        . "];\n";
    if (file_put_contents(__DIR__ . '/config/database.php', $content) === false) {
        throw new RuntimeException('نوشتن config/database.php انجام نشد. دسترسی نوشتن پوشه config را بررسی کنید.');
    }
}

function installer_ensure_writable()
{
    foreach ([__DIR__ . '/config', __DIR__ . '/storage', __DIR__ . '/storage/logs'] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException('ساخت پوشه ' . basename($dir) . ' انجام نشد.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('پوشه ' . basename($dir) . ' قابل نوشتن نیست.');
        }
    }
    if (!is_writable(__DIR__)) {
        throw new RuntimeException('ریشه پروژه برای ساخت installed.lock قابل نوشتن نیست.');
    }
}

function installer_requirements_html()
{
    $checks = [
        'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mbstring' => extension_loaded('mbstring'),
        'openssl' => extension_loaded('openssl'),
        'json' => extension_loaded('json'),
        'fileinfo' => extension_loaded('fileinfo'),
        'curl' => extension_loaded('curl'),
        'zip' => extension_loaded('zip'),
    ];
    $html = '<div class="requirements">';
    foreach ($checks as $label => $ok) {
        $html .= '<span class="' . ($ok ? 'ok' : 'bad') . '">' . e($label) . '</span>';
    }
    return $html . '</div>';
}

function installer_token_field()
{
    return '<input type="hidden" name="_token" value="' . e($_SESSION['installer_token'] ?? '') . '">';
}

function installer_check_token()
{
    if (!hash_equals((string) ($_SESSION['installer_token'] ?? ''), (string) ($_POST['_token'] ?? ''))) {
        throw new RuntimeException('توکن امنیتی نصب معتبر نیست. صفحه را دوباره بارگذاری کنید.');
    }
}

function installer_log_error(Throwable $e)
{
    $dir = __DIR__ . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($dir . '/installer.log', $line, FILE_APPEND);
}

function installer_human_size($bytes)
{
    $bytes = (float) $bytes;
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024 || $unit === 'GB') {
            return number_format($bytes, $unit === 'B' ? 0 : 1) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return number_format($bytes, 1) . ' GB';
}

function installer_page($title, $body)
{
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title>'
        . '<style>
        @font-face{font-family:YekanBakh;src:url("assets/fonts/woff2/YekanBakh-Regular.woff2") format("woff2");font-weight:400;font-style:normal;font-display:swap}
        @font-face{font-family:YekanBakh;src:url("assets/fonts/woff2/YekanBakh-Bold.woff2") format("woff2");font-weight:700;font-style:normal;font-display:swap}
        *{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#202335;font-family:YekanBakh,Tahoma,Arial,sans-serif;line-height:1.8}.wrap{max-width:1040px;margin:42px auto;padding:0 18px}.panel{background:#fff;border:1px solid #e9edf5;border-radius:14px;box-shadow:0 16px 45px rgba(31,42,68,.08);padding:24px}.hero{display:flex;justify-content:space-between;gap:18px;align-items:stretch;margin-bottom:18px}.hero h1{margin:0 0 8px;font-size:25px}.hero p{margin:0;color:#6c7282}.status-box{min-width:210px;border:1px dashed #cfd6e8;border-radius:12px;padding:14px;background:#fafbff;display:grid;align-content:center;gap:4px}.status-box small{color:#6c7282;direction:ltr}.requirements{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px}.requirements span,.ok,.bad{display:inline-flex;align-items:center;border-radius:999px;padding:4px 10px;font-size:12px}.ok{background:#eaf8ee;color:#218943}.bad{background:#ffecec;color:#c73232}.install-form{display:grid;gap:18px}.install-form fieldset{border:1px solid #e7eaf2;border-radius:12px;padding:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.install-form legend{font-weight:700;padding:0 8px}.install-form label{display:grid;gap:6px;font-size:13px;color:#4d5365}.install-form .full{grid-column:1/-1}.install-form input{width:100%;border:1px solid #d9deea;border-radius:9px;min-height:42px;padding:8px 11px;font:inherit;background:#fff}.check{display:flex!important;grid-template-columns:none!important;align-items:center;gap:8px}.check input{width:auto;min-height:auto}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:9px;padding:10px 18px;background:#7366ff;color:#fff;text-decoration:none;font:inherit;cursor:pointer;width:max-content}.btn.secondary{background:#eef1f7;color:#30384d}.notice{border-radius:10px;padding:11px 13px;margin:8px 0}.notice.error{background:#fff0f0;color:#b42318}.notice.success{background:#ecfdf3;color:#067647}.notice.warning{background:#fff8e6;color:#996500}.notice.info{background:#eef7ff;color:#175cd3}@media(max-width:760px){.hero{display:grid}.install-form fieldset{grid-template-columns:1fr}.btn{width:100%}}
        </style></head><body><main class="wrap"><section class="panel">' . $body . '</section></main></body></html>';
}
