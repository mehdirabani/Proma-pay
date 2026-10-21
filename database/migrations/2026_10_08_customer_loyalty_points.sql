CREATE TABLE IF NOT EXISTS customer_point_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_key VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    source_type VARCHAR(20) NOT NULL DEFAULT 'system',
    points INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_customer_point_rules_activity (activity_key),
    KEY idx_customer_point_rules_active (is_active, source_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_point_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NULL,
    activity_key VARCHAR(80) NOT NULL,
    activity_title VARCHAR(150) NOT NULL,
    points INT NOT NULL,
    source_type VARCHAR(20) NOT NULL DEFAULT 'system',
    note TEXT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(191) NOT NULL,
    awarded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_customer_point_ledger_idempotency (idempotency_key),
    KEY idx_customer_point_ledger_customer_time (customer_id, created_at, id),
    KEY idx_customer_point_ledger_reference (reference_type, reference_id),
    KEY idx_customer_point_ledger_activity (activity_key, source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_loyalty_tiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(100) NOT NULL,
    medal_title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    min_points INT UNSIGNED NOT NULL,
    icon_key VARCHAR(50) NOT NULL DEFAULT 'award',
    color VARCHAR(20) NOT NULL DEFAULT '#d97706',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_customer_loyalty_tiers_slug (slug),
    KEY idx_customer_loyalty_tiers_threshold (is_active, min_points, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_loyalty_tier_awards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    tier_id BIGINT UNSIGNED NOT NULL,
    tier_title VARCHAR(100) NOT NULL,
    medal_title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    icon_key VARCHAR(50) NOT NULL DEFAULT 'award',
    color VARCHAR(20) NOT NULL DEFAULT '#d97706',
    points_at_award INT UNSIGNED NOT NULL DEFAULT 0,
    awarded_at DATETIME NOT NULL,
    KEY idx_customer_loyalty_awards_customer (customer_id, awarded_at),
    UNIQUE KEY uq_customer_loyalty_awards_tier (customer_id, tier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO customer_point_rules
    (activity_key, title, description, source_type, points, is_active, sort_order, created_at)
VALUES
    ('contract_created', 'ثبت قرارداد', 'برای ثبت قرارداد غیرلغوشده به مشتری امتیاز داده می‌شود.', 'system', 20, 1, 10, NOW()),
    ('payment_completed', 'پرداخت موفق', 'برای هر پرداخت موفق و تأییدشده، یک‌بار امتیاز داده می‌شود.', 'system', 5, 1, 20, NOW()),
    ('payment_on_time', 'تکمیل قسط به‌موقع', 'وقتی قسط به‌طور کامل تا تاریخ سررسید پرداخت شود.', 'system', 10, 1, 30, NOW()),
    ('payment_early', 'تکمیل زودهنگام قسط', 'وقتی قسط به‌طور کامل پیش از سررسید پرداخت شود.', 'system', 15, 1, 40, NOW()),
    ('contract_completed', 'تسویه کامل قرارداد', 'برای تکمیل موفق قرارداد، یک‌بار امتیاز داده می‌شود.', 'system', 50, 1, 50, NOW()),
    ('identity_verified', 'تأیید هویت', 'پس از تأیید هر دو مدرک هویتی مشتری.', 'system', 30, 1, 60, NOW());

INSERT IGNORE INTO customer_loyalty_tiers
    (slug, title, medal_title, description, min_points, icon_key, color, sort_order, is_active, created_at)
VALUES
    ('bronze', 'برنزی', 'مدال برنز', 'شروع مسیر وفاداری و دریافت اولین مدال.', 100, 'award', '#b45309', 10, 1, NOW()),
    ('silver', 'نقره‌ای', 'مدال نقره', 'سطح مشتری نقره‌ای باشگاه وفاداری.', 300, 'star', '#64748b', 20, 1, NOW()),
    ('gold', 'طلایی', 'مدال طلا', 'سطح مشتری طلایی باشگاه وفاداری.', 700, 'award', '#ca8a04', 30, 1, NOW()),
    ('platinum', 'پلاتینی', 'مدال پلاتین', 'سطح مشتری پلاتینی باشگاه وفاداری.', 1500, 'shield', '#0f766e', 40, 1, NOW()),
    ('diamond', 'الماسی', 'مدال الماس', 'بالاترین سطح فعلی باشگاه وفاداری.', 3000, 'crown', '#6d28d9', 50, 1, NOW());
