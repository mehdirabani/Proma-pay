CREATE TABLE IF NOT EXISTS accounting_analytics_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_type VARCHAR(40) NOT NULL,
    analytics_direction VARCHAR(20) NOT NULL DEFAULT 'neutral',
    label VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_accounting_analytics_entry_type (entry_type),
    KEY idx_accounting_analytics_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @analytics_snapshot_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_ledger_entries' AND COLUMN_NAME = 'analytics_direction'
);
SET @analytics_snapshot_sql := IF(@analytics_snapshot_exists = 0, 'ALTER TABLE accounting_ledger_entries ADD COLUMN analytics_direction VARCHAR(20) NOT NULL DEFAULT ''neutral'' AFTER entry_type', 'SELECT 1');
PREPARE analytics_snapshot_stmt FROM @analytics_snapshot_sql; EXECUTE analytics_snapshot_stmt; DEALLOCATE PREPARE analytics_snapshot_stmt;

CREATE TABLE IF NOT EXISTS user_salary_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    amount_toman DECIMAL(18,2) NOT NULL,
    payment_day_jalali TINYINT UNSIGNED NOT NULL DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    invalid_day_policy VARCHAR(20) NOT NULL DEFAULT 'last_day',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    automatic_posting_enabled TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_user_salary_rules_user_status (user_id, status),
    KEY idx_user_salary_rules_due (automatic_posting_enabled, status, start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_salary_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salary_rule_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    salary_year SMALLINT UNSIGNED NOT NULL,
    salary_month TINYINT UNSIGNED NOT NULL,
    amount_toman DECIMAL(18,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ledger_entry_id BIGINT UNSIGNED NULL,
    source_event_uuid CHAR(36) NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_user_salary_period (salary_rule_id, salary_year, salary_month),
    KEY idx_user_salary_period_user_month (user_id, salary_year, salary_month),
    KEY idx_user_salary_period_status (status, salary_year, salary_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO accounting_analytics_categories (entry_type, analytics_direction, label, sort_order, created_at) VALUES
 ('commission', 'income', 'کمیسیون', 10, NOW()),
 ('fixed_salary', 'income', 'حقوق ثابت', 20, NOW()),
 ('bonus', 'income', 'پاداش', 30, NOW()),
 ('expense', 'income', 'هزینه قابل پرداخت به کاربر', 40, NOW()),
 ('deduction', 'expense', 'کسری', 50, NOW()),
 ('payment_to_user', 'expense', 'پرداخت به کاربر', 60, NOW()),
 ('receipt_from_user', 'neutral', 'دریافت از کاربر', 70, NOW()),
 ('manual_adjustment', 'neutral', 'اصلاح دستی', 80, NOW()),
 ('reversal', 'neutral', 'سند معکوس', 90, NOW());
