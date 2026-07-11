CREATE TABLE IF NOT EXISTS medal_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    title VARCHAR(190) NOT NULL,
    short_description VARCHAR(255) NULL,
    full_description TEXT NULL,
    how_to_earn TEXT NULL,
    icon_key VARCHAR(50) NOT NULL DEFAULT 'award',
    icon_path VARCHAR(255) NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#f59e0b',
    category VARCHAR(30) NOT NULL DEFAULT 'activity',
    points INT NOT NULL DEFAULT 0,
    award_type VARCHAR(30) NOT NULL DEFAULT 'automatic',
    criteria_type VARCHAR(50) NULL,
    criteria_json LONGTEXT NULL,
    is_repeatable TINYINT(1) NOT NULL DEFAULT 0,
    maximum_awards INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    archived_at DATETIME NULL,
    UNIQUE KEY uq_medal_definition_slug (slug),
    KEY idx_medal_definitions_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_medals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    medal_definition_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'automatic',
    note TEXT NULL,
    related_contract_id BIGINT UNSIGNED NULL,
    related_payment_id BIGINT UNSIGNED NULL,
    awarded_by BIGINT UNSIGNED NULL,
    awarded_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_by BIGINT UNSIGNED NULL,
    revoke_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_user_medals_user_active (user_id, revoked_at),
    KEY idx_user_medals_definition (medal_definition_id),
    KEY idx_user_medals_awarded (awarded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_medal_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_medal_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    reason TEXT NULL,
    performed_by BIGINT UNSIGNED NULL,
    snapshot_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_user_medal_history_medal (user_medal_id),
    KEY idx_user_medal_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO medal_definitions (slug, title, short_description, full_description, how_to_earn, icon_key, color, category, points, criteria_type, criteria_json, sort_order, created_at)
VALUES
('first-contract', 'اولین قرارداد', 'اولین قرارداد اقساطی شما', 'اولین قرارداد ثبت‌شده برای مشتری.', 'یک قرارداد فعال یا تسویه‌شده ثبت کنید.', 'file-text', '#2563eb', 'contract', 10, 'contract_count', '{"minimum":1}', 10, NOW()),
('first-payment', 'اولین پرداخت موفق', 'اولین پرداخت موفق ثبت شد', 'اولین پرداخت تاییدشده مشتری.', 'یک پرداخت موفق ثبت کنید.', 'check-circle', '#16a34a', 'payment', 15, 'payment_count', '{"minimum":1}', 20, NOW()),
('on-time-payment', 'پرداخت به‌موقع', 'پرداخت در موعد انجام شد', 'پرداخت قسط در تاریخ سررسید یا زودتر.', 'حداقل یک قسط را به‌موقع پرداخت کنید.', 'clock', '#0f766e', 'early_payment', 20, 'on_time_count', '{"minimum":1}', 30, NOW()),
('five-on-time-payments', '۵ پرداخت به‌موقع', 'پنج پرداخت خوش‌حسابانه', 'پنج پرداخت در موعد یا زودتر.', 'پنج قسط را به‌موقع پرداخت کنید.', 'award', '#d97706', 'early_payment', 50, 'on_time_count', '{"minimum":5}', 40, NOW()),
('first-settlement', 'تسویه اولین قرارداد', 'اولین قرارداد تسویه شد', 'اولین قرارداد به‌صورت کامل تسویه شده است.', 'یک قرارداد را کامل تسویه کنید.', 'shield-check', '#7c3aed', 'settlement', 60, 'completed_contract_count', '{"minimum":1}', 50, NOW()),
('early-payment', 'پرداخت زودهنگام', 'پرداخت پیش از سررسید', 'یک قسط پیش از سررسید پرداخت شده است.', 'یک قسط را پیش از تاریخ سررسید پرداخت کنید.', 'zap', '#0891b2', 'early_payment', 25, 'early_payment_count', '{"minimum":1}', 35, NOW()),
('five-early-payments', '۵ قسط زودتر از موعد', 'پنج پرداخت زودهنگام', 'پنج قسط پیش از سررسید پرداخت شده است.', 'پنج قسط را پیش از سررسید پرداخت کنید.', 'trending-up', '#0284c7', 'early_payment', 55, 'early_payment_count', '{"minimum":5}', 45, NOW()),
('ten-on-time-payments', '۱۰ پرداخت به‌موقع', 'ده پرداخت خوش‌حسابانه', 'ده پرداخت در موعد یا زودتر.', 'ده قسط را به‌موقع پرداخت کنید.', 'star', '#ca8a04', 'early_payment', 90, 'on_time_count', '{"minimum":10}', 47, NOW()),
('early-settlement', 'تسویه زودهنگام قرارداد', 'تسویه پیش از موعد', 'قرارداد پیش از سررسید نهایی تسویه شده است.', 'یک قرارداد را پیش از موعد کامل تسویه کنید.', 'fast-forward', '#9333ea', 'settlement', 90, 'early_settlement_count', '{"minimum":1}', 55, NOW()),
('three-successful-contracts', '۳ قرارداد موفق', 'سه قرارداد غیرلغوشده', 'سه قرارداد موفق برای مشتری ثبت شده است.', 'سه قرارداد غیرلغوشده داشته باشید.', 'layers', '#4f46e5', 'contract', 45, 'contract_count', '{"minimum":3}', 58, NOW()),
('ten-successful-contracts', '۱۰ قرارداد موفق', 'ده قرارداد غیرلغوشده', 'ده قرارداد موفق برای مشتری ثبت شده است.', 'ده قرارداد غیرلغوشده داشته باشید.', 'briefcase', '#3730a3', 'contract', 120, 'contract_count', '{"minimum":10}', 59, NOW()),
('no-overdue', 'بدون معوقه', 'اقساط معوق ندارید', 'هیچ قسط معوق فعالی وجود ندارد.', 'همه اقساط فعال را در موعد پرداخت کنید.', 'shield', '#16a34a', 'activity', 35, 'overdue_count', '{"maximum":0}', 65, NOW()),
('special-customer', 'مشتری ویژه', 'امتیاز ویژه مشتری', 'مدال ویژه برای مشتریان منتخب.', 'توسط مدیر سامانه انتخاب شوید.', 'crown', '#be123c', 'special', 150, NULL, '{}', 70, NOW()),
('loyal-customer', 'مشتری وفادار', 'پنج قرارداد موفق', 'مشتری با سابقه‌ی پنج قرارداد غیرلغوشده.', 'پنج قرارداد غیرلغوشده داشته باشید.', 'heart', '#db2777', 'loyalty', 80, 'contract_count', '{"minimum":5}', 60, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), short_description = VALUES(short_description), full_description = VALUES(full_description), how_to_earn = VALUES(how_to_earn), icon_key = VALUES(icon_key), color = VALUES(color), points = VALUES(points), criteria_type = VALUES(criteria_type), criteria_json = VALUES(criteria_json), updated_at = NOW();

SET @avatar_category_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_category');
SET @avatar_category_sql := IF(@avatar_category_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_category VARCHAR(40) NULL AFTER avatar_key', 'SELECT 1');
PREPARE avatar_category_stmt FROM @avatar_category_sql;
EXECUTE avatar_category_stmt;
DEALLOCATE PREPARE avatar_category_stmt;
SET @avatar_source_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_source');
SET @avatar_source_sql := IF(@avatar_source_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_source VARCHAR(30) NOT NULL DEFAULT ''fallback'' AFTER avatar_category', 'SELECT 1');
PREPARE avatar_source_stmt FROM @avatar_source_sql;
EXECUTE avatar_source_stmt;
DEALLOCATE PREPARE avatar_source_stmt;
SET @avatar_locked_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_locked');
SET @avatar_locked_sql := IF(@avatar_locked_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar_source', 'SELECT 1');
PREPARE avatar_locked_stmt FROM @avatar_locked_sql;
EXECUTE avatar_locked_stmt;
DEALLOCATE PREPARE avatar_locked_stmt;
SET @avatar_reason_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_suggestion_reason');
SET @avatar_reason_sql := IF(@avatar_reason_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_suggestion_reason VARCHAR(255) NULL AFTER avatar_locked', 'SELECT 1');
PREPARE avatar_reason_stmt FROM @avatar_reason_sql;
EXECUTE avatar_reason_stmt;
DEALLOCATE PREPARE avatar_reason_stmt;
