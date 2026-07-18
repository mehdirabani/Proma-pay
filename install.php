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
            ensure_installer_writable();
            create_schema($pdo);
            $adminId = create_or_update_initial_admin($pdo, [
                'username' => $username,
                'full_name' => $fullName,
                'national_id' => to_english_digits($_POST['national_id'] ?? '') ?: null,
                'mobile' => to_english_digits($_POST['mobile'] ?? '') ?: null,
                'email' => trim($_POST['email'] ?? '') ?: null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            seed_system_announcements($pdo, $adminId);
            seed_settings($pdo);
            write_database_config($db);
            if (file_put_contents(__DIR__ . '/installed.lock', 'installed=' . date('c')) === false) {
                throw new RuntimeException('Cannot write installed.lock.');
            }
            unset($_SESSION['install_db']);
            echo installer_page('نصب کامل شد', '<div class="notice success">سامانه با موفقیت نصب شد و حساب مدیر ساخته شد.</div><a class="btn" href="index.php?route=auth/login">ورود به سامانه</a>');
            exit;
        } catch (Throwable $e) {
            installer_log_error($e);
            $message = '<div class="notice error">نصب کامل نشد. دسترسی نوشتن فایل‌ها و اطلاعات پایگاه داده را بررسی کنید. جزئیات خطا در storage/logs/install.log ثبت شد.</div>';
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
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }
    return new PDO($dsn, $db['username'], $db['password'], $options);
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
    if (file_put_contents(__DIR__ . '/config/database.php', $content) === false) {
        throw new RuntimeException('Cannot write database config.');
    }
}

function ensure_installer_writable()
{
    $configDir = __DIR__ . '/config';
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    if (!is_dir($configDir) || !is_writable($configDir)) {
        throw new RuntimeException('Config directory is not writable.');
    }
    if (!is_writable(__DIR__)) {
        throw new RuntimeException('Application root is not writable for installed.lock.');
    }
}

function installer_log_error(Throwable $e)
{
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($logDir . '/install.log', $line, FILE_APPEND);
    error_log($line);
}

function create_or_update_initial_admin(PDO $pdo, array $admin)
{
    $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $existing->execute([$admin['username']]);
    $adminId = (int) ($existing->fetchColumn() ?: 0);
    $existing->closeCursor();

    if ($adminId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET role = 'admin', full_name = ?, national_id = ?, mobile = ?, email = ?,
                 password_hash = ?, status = 'active', updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $admin['full_name'],
            $admin['national_id'],
            $admin['mobile'],
            $admin['email'],
            $admin['password_hash'],
            $adminId,
        ]);
        return $adminId;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users (role, username, full_name, national_id, mobile, email, password_hash, status, created_at)
         VALUES ('admin', ?, ?, ?, ?, ?, ?, 'active', NOW())"
    );
    $stmt->execute([
        $admin['username'],
        $admin['full_name'],
        $admin['national_id'],
        $admin['mobile'],
        $admin['email'],
        $admin['password_hash'],
    ]);
    return (int) $pdo->lastInsertId();
}

function create_schema(PDO $pdo)
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(30) NOT NULL,
            username VARCHAR(100) NULL,
            full_name VARCHAR(190) NOT NULL,
            father_name VARCHAR(190) NULL,
            issued_from VARCHAR(190) NULL,
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
        "CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            mobile VARCHAR(30) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_password_resets_user (user_id, used_at, expires_at),
            KEY idx_password_resets_expiry (expires_at, used_at),
            CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS customer_merge_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            keep_customer_id BIGINT UNSIGNED NOT NULL,
            merged_customer_id BIGINT UNSIGNED NOT NULL,
            merged_by BIGINT UNSIGNED NULL,
            snapshot_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_customer_merge_keep (keep_customer_id),
            KEY idx_customer_merge_merged (merged_customer_id)
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
            cancelled_at DATETIME NULL,
            cancelled_by BIGINT UNSIGNED NULL,
            cancellation_reason TEXT NULL,
            previous_status VARCHAR(30) NULL,
            cancellation_metadata_json LONGTEXT NULL,
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
        "CREATE TABLE IF NOT EXISTS contract_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            product_model VARCHAR(190) NOT NULL,
            imei_1 VARCHAR(80) NULL,
            imei_2 VARCHAR(80) NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_contract_items_contract (contract_id),
            CONSTRAINT fk_contract_items_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contract_guarantees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            guarantee_type VARCHAR(50) NOT NULL,
            guarantee_count INT NOT NULL DEFAULT 1,
            guarantee_serial VARCHAR(190) NULL,
            guarantee_description TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_contract_guarantees_contract (contract_id),
            CONSTRAINT fk_contract_guarantees_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contract_guarantor_people (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            full_name VARCHAR(190) NOT NULL,
            father_name VARCHAR(190) NULL,
            national_id VARCHAR(20) NULL,
            mobile VARCHAR(30) NULL,
            address TEXT NULL,
            relationship VARCHAR(100) NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_contract_guarantor_people_contract (contract_id),
            CONSTRAINT fk_contract_guarantor_people_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS generated_contract_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            rendered_body LONGTEXT NOT NULL,
            generated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_generated_contract (contract_id),
            CONSTRAINT fk_generated_contract_documents_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contract_change_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            changed_by BIGINT UNSIGNED NULL,
            change_type VARCHAR(80) NOT NULL,
            old_value_json LONGTEXT NULL,
            new_value_json LONGTEXT NULL,
            reason TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_contract_change_logs_contract (contract_id),
            CONSTRAINT fk_contract_change_logs_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
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
            cancelled_at DATETIME NULL,
            cancelled_by BIGINT UNSIGNED NULL,
            cancellation_reason TEXT NULL,
            notes TEXT NULL,
            guarantee_serial VARCHAR(190) NULL,
            is_custom TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_contract_installment (contract_id, installment_number),
            KEY idx_due_status (due_date, status),
            CONSTRAINT fk_installment_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            installment_id BIGINT UNSIGNED NULL,
            payment_group_id BIGINT UNSIGNED NULL,
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
        "CREATE TABLE IF NOT EXISTS payment_receipts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_id BIGINT UNSIGNED NOT NULL,
            installment_id BIGINT UNSIGNED NOT NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            receipt_path VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            review_note TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            submitted_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            KEY idx_payment_receipts_status (status),
            KEY idx_payment_receipts_customer (customer_id),
            CONSTRAINT fk_payment_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_receipts_installment FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_receipts_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_receipts_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_receipts_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
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
            installment_id BIGINT UNSIGNED NULL,
            call_result VARCHAR(190) NOT NULL,
            notes TEXT NULL,
            next_followup_date DATE NULL,
            promise_payment_date DATE NULL,
            created_at DATETIME NOT NULL,
            KEY idx_operator_calls_operator (operator_id),
            CONSTRAINT fk_call_operator FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_call_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_call_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            CONSTRAINT fk_call_installment FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS legal_cases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lawyer_id BIGINT UNSIGNED NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            contract_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            stage VARCHAR(190) NOT NULL,
            complaint_number VARCHAR(100) NULL,
            notice_date DATE NULL,
            court_date DATE NULL,
            hearing_date DATE NULL,
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
        "CREATE TABLE IF NOT EXISTS legal_case_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id BIGINT UNSIGNED NOT NULL,
            legal_case_id BIGINT UNSIGNED NULL,
            action_stage VARCHAR(80) NOT NULL,
            action_title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            action_date DATE NOT NULL,
            action_time TIME NULL,
            registered_by BIGINT UNSIGNED NULL,
            assigned_lawyer_id BIGINT UNSIGNED NULL,
            next_status VARCHAR(80) NULL,
            attachment_path VARCHAR(255) NULL,
            cost_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            cost_type VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_legal_case_logs_contract (contract_id),
            INDEX idx_legal_case_logs_case (legal_case_id),
            INDEX idx_legal_case_logs_stage (action_stage),
            INDEX idx_legal_case_logs_date (action_date),
            CONSTRAINT fk_legal_case_logs_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            CONSTRAINT fk_legal_case_logs_case FOREIGN KEY (legal_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL,
            CONSTRAINT fk_legal_case_logs_registered_by FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_legal_case_logs_lawyer FOREIGN KEY (assigned_lawyer_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS chat_channels (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'public',
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_chat_channels_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id BIGINT UNSIGNED NOT NULL,
            receiver_id BIGINT UNSIGNED NULL,
            channel_id BIGINT UNSIGNED NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            target_unit VARCHAR(80) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_message_pair (sender_id, receiver_id, id),
            KEY idx_message_unread (receiver_id, is_read),
            KEY idx_message_channel (channel_id, id),
            CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS chat_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(40) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL,
            review_note TEXT NULL,
            reviewed_at DATETIME NULL,
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_chat_attachments_message (message_id),
            KEY idx_chat_attachments_status (status),
            CONSTRAINT fk_chat_attachment_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            CONSTRAINT fk_chat_attachment_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
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
        "CREATE TABLE IF NOT EXISTS system_plugins (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_id VARCHAR(100) NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            version VARCHAR(40) NOT NULL,
            path VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'discovered',
            installed_at DATETIME NULL,
            activated_at DATETIME NULL,
            deactivated_at DATETIME NULL,
            updated_at DATETIME NULL,
            installed_by BIGINT UNSIGNED NULL,
            last_error TEXT NULL,
            manifest_json LONGTEXT NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_system_plugins_plugin_id (plugin_id),
            KEY idx_system_plugins_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS system_plugin_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_id VARCHAR(100) NOT NULL,
            migration_name VARCHAR(190) NOT NULL,
            batch INT UNSIGNED NOT NULL DEFAULT 1,
            checksum CHAR(64) NOT NULL,
            executed_at DATETIME NULL,
            execution_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            error_message TEXT NULL,
            UNIQUE KEY uq_system_plugin_migration (plugin_id, migration_name),
            KEY idx_system_plugin_migrations_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS system_plugin_permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_id VARCHAR(100) NOT NULL,
            permission_key VARCHAR(190) NOT NULL,
            label VARCHAR(190) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_system_plugin_permission (plugin_id, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS system_plugin_role_permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_id VARCHAR(100) NOT NULL,
            permission_key VARCHAR(190) NOT NULL,
            role VARCHAR(40) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 0,
            granted_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_system_plugin_role_permission (plugin_id, permission_key, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS system_plugin_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_id VARCHAR(100) NOT NULL,
            log_level VARCHAR(20) NOT NULL,
            log_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_system_plugin_logs_plugin (plugin_id),
            KEY idx_system_plugin_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL,
            setting_value LONGTEXT NULL,
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
            icon_key VARCHAR(40) NULL DEFAULT 'award',
            source VARCHAR(40) NOT NULL DEFAULT 'manual',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_medals_user_code (user_id, code),
            CONSTRAINT fk_medal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NULL,
            event_type VARCHAR(40) NOT NULL DEFAULT 'general',
            description TEXT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'primary',
            reminder_type VARCHAR(40) NULL,
            reminder_at DATETIME NULL,
            reminder_sent_at DATETIME NULL,
            due_day_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_events_date (event_date),
            KEY idx_events_assigned (assigned_user_id),
            KEY idx_events_reminder (reminder_at, reminder_sent_at),
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
        "CREATE TABLE IF NOT EXISTS identity_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            document_type VARCHAR(40) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL,
            review_note TEXT NULL,
            uploaded_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            KEY idx_identity_documents_user (user_id),
            KEY idx_identity_documents_status (status),
            CONSTRAINT fk_identity_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_identity_documents_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
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
            row_index INT NOT NULL,
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
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            audit_number VARCHAR(50) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            event_action VARCHAR(100) NOT NULL,
            event_result VARCHAR(50) NOT NULL DEFAULT 'success',
            severity VARCHAR(50) NOT NULL DEFAULT 'medium',
            actor_type VARCHAR(50) NOT NULL DEFAULT 'user',
            actor_user_id BIGINT UNSIGNED NULL,
            related_type VARCHAR(100) NOT NULL,
            related_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            contract_id BIGINT UNSIGNED NULL,
            installment_id BIGINT UNSIGNED NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            request_method VARCHAR(20) NULL,
            request_path VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_audit_logs_audit_number (audit_number),
            KEY idx_audit_logs_event_type (event_type),
            KEY idx_audit_logs_event_action (event_action),
            KEY idx_audit_logs_related (related_type, related_id),
            KEY idx_audit_logs_contract_id (contract_id),
            KEY idx_audit_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
    $pdo->exec("INSERT IGNORE INTO system_plugin_permissions (plugin_id, permission_key, label, is_active, created_at) VALUES
        ('core', 'view_plugins', 'مشاهده پلاگین‌ها', 1, NOW()),
        ('core', 'manage_plugins', 'مدیریت پلاگین‌ها', 1, NOW()),
        ('core', 'install_plugins', 'نصب پلاگین‌ها', 1, NOW()),
        ('core', 'activate_plugins', 'فعال‌سازی پلاگین‌ها', 1, NOW()),
        ('core', 'deactivate_plugins', 'غیرفعال‌سازی پلاگین‌ها', 1, NOW()),
        ('core', 'update_plugins', 'بروزرسانی پلاگین‌ها', 1, NOW()),
        ('core', 'uninstall_plugins', 'حذف پلاگین‌ها', 1, NOW()),
        ('core', 'purge_plugin_data', 'حذف کامل داده پلاگین', 1, NOW())");
    create_v126_core_schema($pdo);
    ensure_install_schema_compatibility($pdo);
    $pdo->exec(
        "INSERT IGNORE INTO chat_channels (title, slug, type, is_pinned, is_system, created_at)
         VALUES ('پرما پرداخت', 'public-announcements', 'public', 1, 1, NOW())"
    );
}

function create_v126_core_schema(PDO $pdo)
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS contract_deletion_archives (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_id BIGINT UNSIGNED NOT NULL, contract_number VARCHAR(80) NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, deletion_reason TEXT NOT NULL, gateway_warning TEXT NULL, corrected_payment_count INT UNSIGNED NOT NULL DEFAULT 0, snapshot_json LONGTEXT NOT NULL, deleted_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_contract_deletion_archive (contract_id, created_at), KEY idx_contract_deletion_archives_customer (customer_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contract_document_versions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_id BIGINT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL, rendered_title VARCHAR(190) NULL, rendered_header TEXT NULL, rendered_body LONGTEXT NOT NULL, source VARCHAR(30) NOT NULL DEFAULT 'generated', checksum CHAR(64) NOT NULL, is_published TINYINT(1) NOT NULL DEFAULT 1, is_finalized TINYINT(1) NOT NULL DEFAULT 0, generated_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_contract_document_version (contract_id, version_number), KEY idx_contract_document_versions_published (contract_id, is_published, version_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payment_groups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, group_number VARCHAR(80) NOT NULL, contract_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NULL, requested_amount DECIMAL(18,2) NOT NULL, allocated_amount DECIMAL(18,2) NOT NULL DEFAULT 0, method VARCHAR(30) NOT NULL DEFAULT 'manual', status VARCHAR(30) NOT NULL DEFAULT 'paid', gateway_track_id VARCHAR(100) NULL, idempotency_key VARCHAR(120) NULL, description TEXT NULL, selection_json LONGTEXT NULL, created_at DATETIME NOT NULL, completed_at DATETIME NULL, UNIQUE KEY uq_payment_groups_number (group_number), UNIQUE KEY uq_payment_groups_idempotency (idempotency_key), KEY idx_payment_groups_contract (contract_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payment_allocations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payment_group_id BIGINT UNSIGNED NOT NULL, payment_id BIGINT UNSIGNED NOT NULL, contract_id BIGINT UNSIGNED NOT NULL, installment_id BIGINT UNSIGNED NOT NULL, allocated_amount DECIMAL(18,2) NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_payment_allocation_installment (payment_group_id, installment_id), KEY idx_payment_allocations_payment (payment_id), CONSTRAINT fk_payment_allocation_group FOREIGN KEY (payment_group_id) REFERENCES payment_groups(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS installment_bulk_operations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, operation_number VARCHAR(80) NOT NULL, contract_id BIGINT UNSIGNED NOT NULL, operation_type VARCHAR(40) NOT NULL, installment_ids_json LONGTEXT NOT NULL, old_snapshot_json LONGTEXT NULL, new_snapshot_json LONGTEXT NULL, reason TEXT NOT NULL, performed_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_installment_bulk_operation_number (operation_number), KEY idx_installment_bulk_operations_contract (contract_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS medal_definitions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(100) NOT NULL, title VARCHAR(190) NOT NULL, short_description VARCHAR(255) NULL, full_description TEXT NULL, how_to_earn TEXT NULL, icon_key VARCHAR(50) NOT NULL DEFAULT 'award', icon_path VARCHAR(255) NULL, color VARCHAR(20) NOT NULL DEFAULT '#f59e0b', category VARCHAR(30) NOT NULL DEFAULT 'activity', points INT NOT NULL DEFAULT 0, award_type VARCHAR(30) NOT NULL DEFAULT 'automatic', criteria_type VARCHAR(50) NULL, criteria_json LONGTEXT NULL, is_repeatable TINYINT(1) NOT NULL DEFAULT 0, maximum_awards INT UNSIGNED NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, archived_at DATETIME NULL, UNIQUE KEY uq_medal_definition_slug (slug), KEY idx_medal_definitions_active_sort (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_medals (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, medal_definition_id BIGINT UNSIGNED NOT NULL, source VARCHAR(30) NOT NULL DEFAULT 'automatic', note TEXT NULL, related_contract_id BIGINT UNSIGNED NULL, related_payment_id BIGINT UNSIGNED NULL, awarded_by BIGINT UNSIGNED NULL, awarded_at DATETIME NOT NULL, revoked_at DATETIME NULL, revoked_by BIGINT UNSIGNED NULL, revoke_reason TEXT NULL, created_at DATETIME NOT NULL, KEY idx_user_medals_user_active (user_id, revoked_at), KEY idx_user_medals_definition (medal_definition_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_medal_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_medal_id BIGINT UNSIGNED NOT NULL, action VARCHAR(30) NOT NULL, reason TEXT NULL, performed_by BIGINT UNSIGNED NULL, snapshot_json LONGTEXT NULL, created_at DATETIME NOT NULL, KEY idx_user_medal_history_medal (user_medal_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
    $pdo->exec("INSERT IGNORE INTO medal_definitions (slug, title, short_description, icon_key, color, category, points, criteria_type, criteria_json, sort_order, created_at) VALUES
        ('first-contract', 'اولین قرارداد', 'اولین قرارداد اقساطی شما', 'file-text', '#2563eb', 'contract', 10, 'contract_count', '{\"minimum\":1}', 10, NOW()),
        ('first-payment', 'اولین پرداخت موفق', 'اولین پرداخت موفق ثبت شد', 'check-circle', '#16a34a', 'payment', 15, 'payment_count', '{\"minimum\":1}', 20, NOW()),
        ('on-time-payment', 'پرداخت به‌موقع', 'پرداخت در موعد انجام شد', 'clock', '#0f766e', 'early_payment', 20, 'on_time_count', '{\"minimum\":1}', 30, NOW()),
        ('five-on-time-payments', '۵ پرداخت به‌موقع', 'پنج پرداخت خوش‌حسابانه', 'award', '#d97706', 'early_payment', 50, 'on_time_count', '{\"minimum\":5}', 40, NOW()),
        ('first-settlement', 'تسویه اولین قرارداد', 'اولین قرارداد تسویه شد', 'shield-check', '#7c3aed', 'settlement', 60, 'completed_contract_count', '{\"minimum\":1}', 50, NOW()),
        ('early-payment', 'پرداخت زودهنگام', 'پرداخت پیش از سررسید', 'zap', '#0891b2', 'early_payment', 25, 'early_payment_count', '{\"minimum\":1}', 35, NOW()),
        ('five-early-payments', '۵ قسط زودتر از موعد', 'پنج پرداخت زودهنگام', 'trending-up', '#0284c7', 'early_payment', 55, 'early_payment_count', '{\"minimum\":5}', 45, NOW()),
        ('ten-on-time-payments', '۱۰ پرداخت به‌موقع', 'ده پرداخت خوش‌حسابانه', 'star', '#ca8a04', 'early_payment', 90, 'on_time_count', '{\"minimum\":10}', 47, NOW()),
        ('early-settlement', 'تسویه زودهنگام قرارداد', 'تسویه پیش از موعد', 'fast-forward', '#9333ea', 'settlement', 90, 'early_settlement_count', '{\"minimum\":1}', 55, NOW()),
        ('three-successful-contracts', '۳ قرارداد موفق', 'سه قرارداد غیرلغوشده', 'layers', '#4f46e5', 'contract', 45, 'contract_count', '{\"minimum\":3}', 58, NOW()),
        ('ten-successful-contracts', '۱۰ قرارداد موفق', 'ده قرارداد غیرلغوشده', 'briefcase', '#3730a3', 'contract', 120, 'contract_count', '{\"minimum\":10}', 59, NOW()),
        ('no-overdue', 'بدون معوقه', 'اقساط معوق ندارید', 'shield', '#16a34a', 'activity', 35, 'overdue_count', '{\"maximum\":0}', 65, NOW()),
        ('special-customer', 'مشتری ویژه', 'امتیاز ویژه مشتری', 'crown', '#be123c', 'special', 150, NULL, '{}', 70, NOW()),
        ('loyal-customer', 'مشتری وفادار', 'پنج قرارداد موفق', 'heart', '#db2777', 'loyalty', 80, 'contract_count', '{\"minimum\":5}', 60, NOW())");
}

function ensure_install_schema_compatibility(PDO $pdo)
{
    foreach ([
        'avatar_category' => 'VARCHAR(40) NULL AFTER avatar_key',
        'avatar_source' => "VARCHAR(30) NOT NULL DEFAULT 'fallback' AFTER avatar_category",
        'avatar_locked' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar_source',
        'avatar_suggestion_reason' => 'VARCHAR(255) NULL AFTER avatar_locked',
    ] as $column => $definition) {
        if (!installer_column_exists($pdo, 'users', $column)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }
    foreach ([
        'channel_id' => 'BIGINT UNSIGNED NULL AFTER receiver_id',
        'target_unit' => 'VARCHAR(80) NULL AFTER is_read',
        'is_system' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER target_unit',
    ] as $column => $definition) {
        if (!installer_column_exists($pdo, 'messages', $column)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN {$column} {$definition}");
        }
    }

    foreach ([
        'previous_status' => 'VARCHAR(30) NULL AFTER cancellation_reason',
        'cancellation_metadata_json' => 'LONGTEXT NULL AFTER previous_status',
    ] as $column => $definition) {
        if (!installer_column_exists($pdo, 'contracts', $column)) {
            $pdo->exec("ALTER TABLE contracts ADD COLUMN {$column} {$definition}");
        }
    }

    try {
        $pdo->exec('ALTER TABLE messages MODIFY receiver_id BIGINT UNSIGNED NULL');
    } catch (Throwable $e) {
        $receiverFk = installer_foreign_key_name($pdo, 'messages', 'receiver_id', 'users');
        if (!$receiverFk) {
            throw $e;
        }
        $pdo->exec('ALTER TABLE messages DROP FOREIGN KEY ' . installer_quote_identifier($receiverFk));
        $pdo->exec('ALTER TABLE messages MODIFY receiver_id BIGINT UNSIGNED NULL');
        if (!installer_foreign_key_name($pdo, 'messages', 'receiver_id', 'users')) {
            $pdo->exec('ALTER TABLE messages ADD CONSTRAINT fk_message_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE');
        }
    }

    if (!installer_index_exists($pdo, 'messages', 'idx_message_channel')) {
        $pdo->exec('ALTER TABLE messages ADD INDEX idx_message_channel (channel_id, id)');
    }
    if (!installer_foreign_key_name($pdo, 'messages', 'channel_id', 'chat_channels')) {
        $pdo->exec('ALTER TABLE messages ADD CONSTRAINT fk_message_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE');
    }
}

function installer_column_exists(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([(string) $table, (string) $column]);
    $exists = (int) $stmt->fetchColumn() > 0;
    $stmt->closeCursor();
    return $exists;
}

function installer_index_exists(PDO $pdo, $table, $index)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([(string) $table, (string) $index]);
    $exists = (int) $stmt->fetchColumn() > 0;
    $stmt->closeCursor();
    return $exists;
}

function installer_foreign_key_name(PDO $pdo, $table, $column, $referencedTable)
{
    $stmt = $pdo->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
           AND REFERENCED_TABLE_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([(string) $table, (string) $column, (string) $referencedTable]);
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name ? (string) $name : null;
}

function installer_quote_identifier($identifier)
{
    return '`' . str_replace('`', '``', (string) $identifier) . '`';
}

function seed_system_announcements(PDO $pdo, $adminId)
{
    if (!$adminId) {
        return;
    }
    $channelStmt = $pdo->prepare("SELECT id, title FROM chat_channels WHERE slug = 'public-announcements' LIMIT 1");
    $channelStmt->execute();
    $channel = $channelStmt->fetch(PDO::FETCH_ASSOC);
    if (!$channel) {
        return;
    }
    $exists = $pdo->prepare('SELECT id FROM messages WHERE channel_id = ? AND is_system = 1 LIMIT 1');
    $exists->execute([(int) $channel['id']]);
    if ($exists->fetch(PDO::FETCH_ASSOC)) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO messages (sender_id, receiver_id, channel_id, body, is_read, target_unit, is_system, created_at)
         VALUES (?, NULL, ?, ?, 1, ?, 1, NOW())'
    );
    $stmt->execute([
        (int) $adminId,
        (int) $channel['id'],
        'به ' . $channel['title'] . ' خوش آمدید. اعلان‌های مهم سامانه در همین بخش منتشر می‌شود.',
        $channel['title'],
    ]);
}

function seed_settings(PDO $pdo)
{
    $defaults = [
        'system_name' => 'پرما پرداخت',
        'logo_text' => 'پرما پرداخت',
        'logo_path' => '',
        'logo_icon_path' => '',
        'favicon_path' => '',
        'footer_text' => 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani',
        'company_name' => 'موبایل پروما',
        'company_representative_name' => '',
        'company_representative_national_id' => '',
        'company_address' => '',
        'company_postal_code' => '',
        'company_phone' => '',
        'contract_prefix' => 'PR',
        'contract_next_serial' => '1',
        'contract_year' => substr(to_english_digits(jdate(date('Y-m-d'))), 0, 4) ?: '1404',
        'contract_number_format' => 'PR-{SERIAL:6}',
        'contract_template_body' => '',
        'monthly_penalty_rate' => '2',
        'legal_monthly_penalty_rate' => '4',
        'late_penalty_grace_days' => '0',
        'contract_legal_penalty_clause' => 'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت یا ارجاع پرونده حقوقی، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ از زمان ورود قرارداد به مرحله حقوقی یا شکایت، جریمه دیرکرد با نرخ حقوقی ماهانه محاسبه خواهد شد.',
        'monthly_reward_rate' => '1',
        'zibal_enabled' => '1',
        'zibal_test_mode' => '0',
        'zibal_merchant' => '',
        'callback_base_url' => '',
        'card_transfer_enabled' => '1',
        'card_transfer_account_name' => '',
        'card_transfer_card_number' => '',
        'card_transfer_sheba' => '',
        'card_transfer_qr_text' => '',
        'notifications_sound_enabled' => '1',
        'notifications_sound_volume' => '0.45',
        'chat_file_auto_delete_days' => '7',
        'password_reset_enabled' => '1',
        'ippanel_api_key' => '',
        'ippanel_from_number' => '',
        'ippanel_password_reset_pattern_code' => '',
        'ippanel_password_reset_pattern_key' => 'code',
        'openrouter_api_key' => '',
        'openrouter_model' => 'openai/gpt-4.1-mini',
        'calendar_notifications_enabled' => '1',
        'calendar_default_reminder_type' => '1_day',
        'calendar_notify_admin_without_user' => '1',
        'calendar_due_day_repeat_enabled' => '1',
        'calendar_cron_token' => bin2hex(random_bytes(24)),
        'social_instagram_url' => '',
        'social_telegram_url' => '',
        'social_whatsapp_url' => '',
        'social_facebook_url' => '',
        'social_x_url' => '',
        'social_youtube_url' => '',
        'social_linkedin_url' => '',
        'social_website_url' => '',
        'ecommerce_enabled' => '1',
        'landing_enabled' => '1',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value, in_array($key, ['zibal_merchant', 'openrouter_api_key', 'calendar_cron_token', 'ippanel_api_key'], true) ? 1 : 0]);
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
        . '</head><body class="installer-template"><main class="install-shell"><section class="install-panel"><h1>' . e($title) . '</h1><p>راه‌اندازی سامانه مدیریت قرارداد و اقساط</p>' . $body . '</section></main><script src="' . e(asset_url('assets/js/app.js')) . '"></script></body></html>';
}
