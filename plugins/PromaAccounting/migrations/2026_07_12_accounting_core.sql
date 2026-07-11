CREATE TABLE IF NOT EXISTS plugin_accounting_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    seller_user_id BIGINT UNSIGNED NULL,
    sales_channel VARCHAR(30) NOT NULL DEFAULT 'other',
    principal_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    down_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    financed_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    registered_by BIGINT UNSIGNED NULL,
    registered_at DATETIME NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    metadata_json LONGTEXT NULL,
    UNIQUE KEY uq_plugin_accounting_sale_contract (contract_id),
    KEY idx_plugin_accounting_sales_seller (seller_user_id),
    KEY idx_plugin_accounting_sales_customer (customer_id),
    KEY idx_plugin_accounting_sales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_sales_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    seller_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    old_snapshot_json LONGTEXT NULL,
    new_snapshot_json LONGTEXT NULL,
    performed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY idx_plugin_accounting_sales_logs_sale (sale_id),
    KEY idx_plugin_accounting_sales_logs_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_commission_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    commission_type VARCHAR(20) NOT NULL,
    commission_value DECIMAL(18,4) NOT NULL DEFAULT 0,
    calculation_basis VARCHAR(30) NOT NULL DEFAULT 'financed_amount',
    minimum_amount DECIMAL(18,2) NULL,
    maximum_amount DECIMAL(18,2) NULL,
    effective_from DATE NULL,
    effective_to DATE NULL,
    calculation_timing VARCHAR(40) NOT NULL DEFAULT 'at_contract_creation',
    requires_approval TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_plugin_accounting_rules_user_active (user_id, is_active),
    KEY idx_plugin_accounting_rules_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    seller_user_id BIGINT UNSIGNED NULL,
    commission_rule_id BIGINT UNSIGNED NULL,
    basis_type VARCHAR(30) NOT NULL,
    basis_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    commission_type VARCHAR(20) NOT NULL,
    commission_value DECIMAL(18,4) NOT NULL DEFAULT 0,
    calculated_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    approved_amount DECIMAL(18,2) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    calculated_at DATETIME NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    posted_ledger_entry_id BIGINT UNSIGNED NULL,
    paid_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    reversal_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_plugin_accounting_commissions_sale (sale_id),
    KEY idx_plugin_accounting_commissions_seller (seller_user_id),
    KEY idx_plugin_accounting_commissions_status (status),
    KEY idx_plugin_accounting_commissions_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_plugin_accounting_account_user (user_id),
    UNIQUE KEY uq_plugin_accounting_account_number (account_number),
    KEY idx_plugin_accounting_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_ledger_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    entry_type VARCHAR(30) NOT NULL,
    direction VARCHAR(10) NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    reversal_of_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    performed_by BIGINT UNSIGNED NULL,
    metadata_json LONGTEXT NULL,
    idempotency_key VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_plugin_accounting_ledger_account_created (account_id, created_at, id),
    KEY idx_plugin_accounting_ledger_user (user_id),
    KEY idx_plugin_accounting_ledger_reference (reference_type, reference_id),
    UNIQUE KEY uq_plugin_accounting_ledger_idempotency (idempotency_key),
    UNIQUE KEY uq_plugin_accounting_ledger_reversal (reversal_of_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    category_type VARCHAR(30) NOT NULL DEFAULT 'expense',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_plugin_accounting_category_name_type (name, category_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_user_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_accounting_user_account_user (user_id),
    UNIQUE KEY uq_accounting_user_account_number (account_number),
    KEY idx_accounting_user_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    direction VARCHAR(10) NOT NULL,
    category_type VARCHAR(40) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_accounting_category_title_type (title, category_type),
    KEY idx_accounting_categories_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_ledger_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    entry_number VARCHAR(80) NOT NULL,
    entry_type VARCHAR(40) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    direction VARCHAR(10) NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    balance_before DECIMAL(18,2) NOT NULL,
    balance_after DECIMAL(18,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    commission_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    entry_date DATE NOT NULL,
    entry_time TIME NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    reversed_entry_id BIGINT UNSIGNED NULL,
    reversed_by BIGINT UNSIGNED NULL,
    reversed_at DATETIME NULL,
    reversal_reason TEXT NULL,
    metadata_json LONGTEXT NULL,
    idempotency_key VARCHAR(160) NULL,
    UNIQUE KEY uq_accounting_ledger_entry_number (entry_number),
    UNIQUE KEY uq_accounting_ledger_idempotency (idempotency_key),
    UNIQUE KEY uq_accounting_ledger_reversal (reversed_entry_id),
    KEY idx_accounting_ledger_account_date (account_id, entry_date, id),
    KEY idx_accounting_ledger_user (user_id),
    KEY idx_accounting_ledger_reference (reference_type, reference_id),
    KEY idx_accounting_ledger_contract (contract_id),
    KEY idx_accounting_ledger_commission (commission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_plugin_accounting_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_accounting_backfill_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_number VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'preview',
    cursor_contract_id BIGINT UNSIGNED NULL,
    processed_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_plugin_accounting_backfill_job_number (job_number),
    KEY idx_plugin_accounting_backfill_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO accounting_categories (title, direction, category_type, is_system, is_active, sort_order, created_at) VALUES
    ('کمیسیون فروش', 'increase', 'commission', 1, 1, 10, NOW()),
    ('هزینه قابل پرداخت', 'increase', 'expense', 1, 1, 20, NOW()),
    ('پاداش', 'increase', 'bonus', 1, 1, 30, NOW()),
    ('کسر از حساب', 'decrease', 'deduction', 1, 1, 40, NOW()),
    ('پرداخت وجه به کاربر', 'decrease', 'payment_to_user', 1, 1, 50, NOW()),
    ('دریافت وجه از کاربر', 'increase', 'receipt_from_user', 1, 1, 60, NOW()),
    ('اصلاح دستی', 'increase', 'manual_adjustment', 1, 1, 70, NOW()),
    ('معکوس کمیسیون', 'decrease', 'commission_reversal', 1, 1, 80, NOW()),
    ('اصلاحیه قرارداد', 'increase', 'contract_adjustment', 1, 1, 90, NOW());

INSERT IGNORE INTO plugin_accounting_settings (setting_key, setting_value, updated_at) VALUES
    ('default_commission_type', 'percentage', NOW()),
    ('default_commission_value', '1', NOW()),
    ('default_calculation_basis', 'financed_amount', NOW()),
    ('default_calculation_timing', 'at_contract_creation', NOW()),
    ('require_commission_approval', '0', NOW()),
    ('automatic_ledger_posting', '0', NOW()),
    ('minimum_commission', '0', NOW()),
    ('maximum_commission', '0', NOW()),
    ('rounding_rule', 'nearest_toman', NOW());

SET @commission_sale_unique_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND INDEX_NAME = 'uq_plugin_accounting_commission_sale');
SET @commission_sale_unique_sql := IF(@commission_sale_unique_exists > 0, 'ALTER TABLE plugin_accounting_commissions DROP INDEX uq_plugin_accounting_commission_sale', 'SELECT 1');
PREPARE commission_sale_unique_stmt FROM @commission_sale_unique_sql;
EXECUTE commission_sale_unique_stmt;
DEALLOCATE PREPARE commission_sale_unique_stmt;
