SET @contract_legal_status_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE contracts ADD COLUMN legal_status VARCHAR(30) NULL AFTER assigned_operator_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contracts'
      AND COLUMN_NAME = 'legal_status'
);
PREPARE stmt FROM @contract_legal_status_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO settings (setting_key, setting_value, is_secret) VALUES
('legal_monthly_penalty_rate', '4', 0),
('contract_legal_penalty_clause', 'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت یا ارجاع پرونده حقوقی، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ از زمان ورود قرارداد به مرحله حقوقی یا شکایت، جریمه دیرکرد با نرخ حقوقی ماهانه محاسبه خواهد شد.', 0)
ON DUPLICATE KEY UPDATE setting_value = setting_value;
