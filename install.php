<?php

require __DIR__ . '/helpers/functions.php';

session_name(app_config('session_name'));
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_file(__DIR__ . '/installed.lock')) {
    echo installer_page('نصب انجام شده است', '<div class="notice error">سامانه قبلاً نصب شده است و نصب دوباره مجاز نیست.</div><a class="btn" href="index.php">ورود به سامانه</a>');
    exit;
}

$step = (int) ($_GET['step'] ?? 1);
$message = '';

if (is_post() && $step === 1) {
    $db = [
        'host' => trim($_POST['host'] ?? 'localhost'),
        'database' => trim($_POST['database'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
        'charset' => trim($_POST['charset'] ?? 'utf8mb4'),
    ];
    try {
        installer_pdo($db);
        $_SESSION['install_db'] = $db;
        header('Location: install.php?step=2');
        exit;
    } catch (Throwable $e) {
        $message = '<div class="notice error">اتصال به پایگاه داده برقرار نشد. اطلاعات را بررسی کنید.</div>';
    }
}

if (is_post() && $step === 2) {
    $db = $_SESSION['install_db'] ?? null;
    if (!$db) {
        header('Location: install.php');
        exit;
    }
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if ($fullName === '' || $username === '' || mb_strlen($password, 'UTF-8') < 8) {
        $message = '<div class="notice error">نام، نام کاربری و رمز عبور حداقل هشت‌حرفی الزامی است.</div>';
    } else {
        try {
            $pdo = installer_pdo($db);
            create_schema($pdo);
            write_database_config($db);
            $stmt = $pdo->prepare("INSERT INTO users (role, username, full_name, national_id, mobile, email, password_hash, status, created_at)
                VALUES ('admin', ?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([
                $username,
                $fullName,
                to_english_digits($_POST['national_id'] ?? '') ?: null,
                to_english_digits($_POST['mobile'] ?? '') ?: null,
                trim($_POST['email'] ?? '') ?: null,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            seed_settings($pdo);
            file_put_contents(__DIR__ . '/installed.lock', 'installed=' . date('c'));
            unset($_SESSION['install_db']);
            echo installer_page('نصب کامل شد', '<div class="notice success">سامانه با موفقیت نصب شد و حساب مدیر ساخته شد.</div><a class="btn" href="index.php?route=auth/login">ورود به سامانه</a>');
            exit;
        } catch (Throwable $e) {
            $message = '<div class="notice error">نصب کامل نشد. دسترسی نوشتن فایل‌ها و اطلاعات پایگاه داده را بررسی کنید.</div>';
        }
    }
}

if ($step === 2 && !empty($_SESSION['install_db'])) {
    echo installer_page('ساخت مدیر نخست', $message . '
        <form method="post" class="install-form">
            <label>نام کامل مدیر<input name="full_name" required></label>
            <label>نام کاربری<input name="username" required dir="ltr"></label>
            <label>رمز عبور<input name="password" type="password" required></label>
            <label>کد ملی<input name="national_id" inputmode="numeric"></label>
            <label>موبایل<input name="mobile" inputmode="tel"></label>
            <label>ایمیل<input name="email" type="email" dir="ltr"></label>
            <button class="btn" type="submit">ایجاد پایگاه داده و مدیر</button>
        </form>');
    exit;
}

echo installer_page('تنظیم پایگاه داده', $message . '
    <form method="post" class="install-form">
        <label>میزبان پایگاه داده<input name="host" value="localhost" required dir="ltr"></label>
        <label>نام پایگاه داده<input name="database" required dir="ltr"></label>
        <label>نام کاربری پایگاه داده<input name="username" required dir="ltr"></label>
        <label>رمز عبور پایگاه داده<input name="password" type="password" dir="ltr"></label>
        <label>کدبندی<input name="charset" value="utf8mb4" required dir="ltr"></label>
        <button class="btn" type="submit">آزمایش اتصال</button>
    </form>');

function installer_pdo(array $db)
{
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['database'] . ';charset=' . ($db['charset'] ?: 'utf8mb4');
    return new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function write_database_config(array $db)
{
    $content = "<?php\n\nreturn [\n"
        . "    'host' => " . var_export($db['host'], true) . ",\n"
        . "    'database' => " . var_export($db['database'], true) . ",\n"
        . "    'username' => " . var_export($db['username'], true) . ",\n"
        . "    'password' => " . var_export($db['password'], true) . ",\n"
        . "    'charset' => " . var_export($db['charset'], true) . ",\n"
        . "];\n";
    file_put_contents(__DIR__ . '/config/database.php', $content);
}

function create_schema(PDO $pdo)
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(30) NOT NULL,
            username VARCHAR(100) NULL,
            full_name VARCHAR(190) NOT NULL,
            national_id VARCHAR(20) NULL,
            mobile VARCHAR(20) NULL,
            secondary_phone VARCHAR(30) NULL,
            email VARCHAR(190) NULL,
            address TEXT NULL,
            avatar_key VARCHAR(40) NULL,
            department VARCHAR(40) NULL,
            is_department_manager TINYINT(1) NOT NULL DEFAULT 0,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            tour_completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_national_id (national_id),
            UNIQUE KEY uq_users_mobile (mobile),
            KEY idx_users_role_status (role, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contracts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            contract_number VARCHAR(80) NOT NULL,
            prefix VARCHAR(30) NOT NULL,
            serial BIGINT UNSIGNED NOT NULL,
            principal_amount DECIMAL(18,2) NOT NULL,
            down_payment_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
            monthly_interest_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
            interest_type VARCHAR(30) NOT NULL,
            months INT NOT NULL,
            start_date DATE NOT NULL,
            first_due_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            assigned_operator_id BIGINT UNSIGNED NULL,
            legal_status VARCHAR(30) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_contract_number (contract_number),
            UNIQUE KEY uq_prefix_serial (prefix, serial),
            KEY idx_contract_customer (customer_id),
            KEY idx_contract_operator (assigned_operator_id),
            CONSTRAINT fk_contract_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_contract_operator FOREIGN KEY (assigned_operator_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contract_guarantors (
            contract_id BIGINT UNSIGNED NOT NULL,
            guarantor_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (contract_id, guarantor_id),
            CONSTRAINT fk_guarantor_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            CONSTRAINT fk_guarantor_user FOREIGN KEY (guarantor_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS installments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            installment_number INT NOT NULL,
            due_date DATE NOT NULL,
            base_amount DECIMAL(18,2) NOT NULL,
            paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            remaining_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            last_payment_date DATE NULL,
            manual_penalty_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0,
            manual_reward_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0,
            penalty_discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_contract_installment (contract_id, installment_number),
            KEY idx_due_status (due_date, status),
            CONSTRAINT fk_installment_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            installment_id BIGINT UNSIGNED NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            amount DECIMAL(18,2) NOT NULL,
            method VARCHAR(30) NOT NULL,
            status VARCHAR(30) NOT NULL,
            gateway_track_id VARCHAR(100) NULL,
            gateway_ref_id VARCHAR(100) NULL,
            description VARCHAR(255) NULL,
            payment_date DATE NULL,
            calculated_penalty DECIMAL(18,2) NOT NULL DEFAULT 0,
            calculated_reward DECIMAL(18,2) NOT NULL DEFAULT 0,
            remaining_before_payment DECIMAL(18,2) NULL,
            remaining_after_payment DECIMAL(18,2) NULL,
            payment_type VARCHAR(40) NOT NULL DEFAULT 'installment',
            is_corrected TINYINT(1) NOT NULL DEFAULT 0,
            correction_reason TEXT NULL,
            corrected_at DATETIME NULL,
            corrected_by BIGINT UNSIGNED NULL,
            correction_snapshot_json LONGTEXT NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_gateway_track (gateway_track_id),
            KEY idx_payment_paid_at (paid_at),
            KEY idx_payment_contract (contract_id),
            CONSTRAINT fk_payment_installment FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payment_corrections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_id BIGINT UNSIGNED NOT NULL,
            installment_id BIGINT UNSIGNED NOT NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            reason TEXT NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            corrected_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_payment_correction (payment_id),
            KEY idx_payment_correction_installment (installment_id),
            CONSTRAINT fk_correction_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
            CONSTRAINT fk_correction_admin FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS penalties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            installment_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            percent DECIMAL(8,4) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_penalty_installment FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE CASCADE,
            CONSTRAINT fk_penalty_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS operator_calls (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operator_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            call_result VARCHAR(190) NOT NULL,
            notes TEXT NULL,
            next_followup_date DATE NULL,
            created_at DATETIME NOT NULL,
            KEY idx_operator_calls_operator (operator_id),
            CONSTRAINT fk_call_operator FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_call_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_call_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS legal_cases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lawyer_id BIGINT UNSIGNED NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            stage VARCHAR(190) NOT NULL,
            complaint_number VARCHAR(100) NULL,
            expense_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            expense_reason TEXT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_legal_lawyer (lawyer_id),
            CONSTRAINT fk_legal_lawyer FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_legal_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_legal_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id BIGINT UNSIGNED NOT NULL,
            receiver_id BIGINT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_message_pair (sender_id, receiver_id, id),
            KEY idx_message_unread (receiver_id, is_read),
            CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            type VARCHAR(40) NOT NULL,
            url VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_notification_user (user_id, is_read),
            CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL,
            setting_value TEXT NULL,
            is_secret TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS medals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            points INT NOT NULL DEFAULT 0,
            code VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_medals_user_code (user_id, code),
            CONSTRAINT fk_medal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            event_date DATE NOT NULL,
            description TEXT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'primary',
            created_at DATETIME NOT NULL,
            KEY idx_events_date (event_date),
            CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS profile_update_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL,
            review_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_profile_request_status (status),
            KEY idx_profile_request_user (user_id),
            CONSTRAINT fk_profile_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_profile_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS import_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL,
            raw_path VARCHAR(255) NULL,
            parsed_json LONGTEXT NULL,
            error_summary TEXT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_import_batch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS import_rows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id BIGINT UNSIGNED NOT NULL,
            row_number INT NOT NULL,
            raw_json LONGTEXT NOT NULL,
            parsed_json LONGTEXT NULL,
            status VARCHAR(40) NOT NULL,
            errors TEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_import_row_batch (batch_id),
            CONSTRAINT fk_import_row_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS ai_action_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            instruction TEXT NOT NULL,
            response_json LONGTEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'previewed',
            applied_summary TEXT NULL,
            created_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            KEY idx_ai_action_user (user_id),
            KEY idx_ai_action_status (status),
            CONSTRAINT fk_ai_action_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

function seed_settings(PDO $pdo)
{
    $defaults = [
        'system_name' => 'پرما پرداخت',
        'logo_text' => 'پرما پرداخت',
        'footer_text' => 'پنل مدیریت مالی راست‌چین',
        'contract_prefix' => 'Pr',
        'contract_next_serial' => '1000',
        'monthly_penalty_rate' => '2',
        'monthly_reward_rate' => '1',
        'zibal_merchant' => '',
        'callback_base_url' => '',
        'openrouter_api_key' => '',
        'openrouter_model' => 'openai/gpt-4.1-mini',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value, in_array($key, ['zibal_merchant', 'openrouter_api_key'], true) ? 1 : 0]);
    }
}

function installer_page($title, $body)
{
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title>'
        . '<link rel="stylesheet" href="' . e(template_asset_url('css/font-awesome.css')) . '">'
        . '<link rel="stylesheet" href="' . e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) . '">'
        . '<link rel="stylesheet" href="' . e(template_asset_url('css/style.css')) . '">'
        . '<link rel="stylesheet" href="' . e(template_asset_url('css/responsive.css')) . '">'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/css/app.css')) . '">'
        . '</head><body class="installer-template"><main class="install-shell"><section class="install-panel"><span class="auth-logo-mark">پ</span><h1>' . e($title) . '</h1><p>راه‌اندازی سامانه مدیریت قرارداد و اقساط</p>' . $body . '</section></main><script src="' . e(asset_url('assets/js/app.js')) . '"></script></body></html>';
}
