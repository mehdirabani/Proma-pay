SET @generated_title_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE generated_contract_documents ADD COLUMN rendered_title VARCHAR(190) NULL AFTER contract_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'generated_contract_documents'
      AND COLUMN_NAME = 'rendered_title'
);
PREPARE stmt FROM @generated_title_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @generated_header_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE generated_contract_documents ADD COLUMN rendered_header TEXT NULL AFTER rendered_title',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'generated_contract_documents'
      AND COLUMN_NAME = 'rendered_header'
);
PREPARE stmt FROM @generated_header_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO settings (setting_key, setting_value, is_secret) VALUES
('contract_document_title', 'قرارداد اجاره به شرط تملیک / امانت‌داری', 0),
('contract_document_header', '{{company_name}}
شماره قرارداد: {{contract_number}} | تاریخ: {{contract_date}}
امانت‌دار: {{customer_full_name}} | کد ملی: {{customer_national_id}}', 0),
('monthly_penalty_rate', '10', 0),
('legal_monthly_penalty_rate', '20', 0)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

UPDATE settings SET setting_value = '10' WHERE setting_key = 'monthly_penalty_rate' AND setting_value IN ('', '0', '2');
UPDATE settings SET setting_value = '20' WHERE setting_key = 'legal_monthly_penalty_rate' AND setting_value IN ('', '0', '4');
