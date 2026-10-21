CREATE TABLE IF NOT EXISTS generated_contract_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    template_version_id BIGINT UNSIGNED NULL,
    template_status VARCHAR(30) NOT NULL DEFAULT 'legacy',
    rendered_title VARCHAR(190) NULL,
    rendered_header TEXT NULL,
    rendered_body LONGTEXT NOT NULL,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_generated_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @generated_title_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'rendered_title'
);
SET @generated_title_sql := IF(
    @generated_title_exists = 0,
    'ALTER TABLE generated_contract_documents ADD COLUMN rendered_title VARCHAR(190) NULL AFTER contract_id',
    'SELECT 1'
);
PREPARE generated_title_stmt FROM @generated_title_sql;
EXECUTE generated_title_stmt;
DEALLOCATE PREPARE generated_title_stmt;

SET @generated_header_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'rendered_header'
);
SET @generated_header_sql := IF(
    @generated_header_exists = 0,
    'ALTER TABLE generated_contract_documents ADD COLUMN rendered_header TEXT NULL AFTER rendered_title',
    'SELECT 1'
);
PREPARE generated_header_stmt FROM @generated_header_sql;
EXECUTE generated_header_stmt;
DEALLOCATE PREPARE generated_header_stmt;

SET @generated_template_version_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'template_version_id'
);
SET @generated_template_version_sql := IF(
    @generated_template_version_exists = 0,
    'ALTER TABLE generated_contract_documents ADD COLUMN template_version_id BIGINT UNSIGNED NULL AFTER contract_id',
    'SELECT 1'
);
PREPARE generated_template_version_stmt FROM @generated_template_version_sql;
EXECUTE generated_template_version_stmt;
DEALLOCATE PREPARE generated_template_version_stmt;

SET @generated_template_status_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'template_status'
);
SET @generated_template_status_sql := IF(
    @generated_template_status_exists = 0,
    "ALTER TABLE generated_contract_documents ADD COLUMN template_status VARCHAR(30) NOT NULL DEFAULT 'legacy' AFTER template_version_id",
    'SELECT 1'
);
PREPARE generated_template_status_stmt FROM @generated_template_status_sql;
EXECUTE generated_template_status_stmt;
DEALLOCATE PREPARE generated_template_status_stmt;

SET @custom_flag_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'is_custom'
);
SET @custom_flag_sql := IF(
    @custom_flag_exists = 0,
    'ALTER TABLE installments ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE custom_flag_stmt FROM @custom_flag_sql;
EXECUTE custom_flag_stmt;
DEALLOCATE PREPARE custom_flag_stmt;

SET @custom_title_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'custom_title'
);
SET @custom_title_sql := IF(
    @custom_title_exists = 0,
    'ALTER TABLE installments ADD COLUMN custom_title VARCHAR(190) NULL',
    'SELECT 1'
);
PREPARE custom_title_stmt FROM @custom_title_sql;
EXECUTE custom_title_stmt;
DEALLOCATE PREPARE custom_title_stmt;

SET @custom_description_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'custom_description'
);
SET @custom_description_sql := IF(
    @custom_description_exists = 0,
    'ALTER TABLE installments ADD COLUMN custom_description TEXT NULL',
    'SELECT 1'
);
PREPARE custom_description_stmt FROM @custom_description_sql;
EXECUTE custom_description_stmt;
DEALLOCATE PREPARE custom_description_stmt;

SET @custom_internal_note_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'internal_note'
);
SET @custom_internal_note_sql := IF(
    @custom_internal_note_exists = 0,
    'ALTER TABLE installments ADD COLUMN internal_note TEXT NULL',
    'SELECT 1'
);
PREPARE custom_internal_note_stmt FROM @custom_internal_note_sql;
EXECUTE custom_internal_note_stmt;
DEALLOCATE PREPARE custom_internal_note_stmt;

SET @custom_visible_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'customer_visible'
);
SET @custom_visible_sql := IF(
    @custom_visible_exists = 0,
    'ALTER TABLE installments ADD COLUMN customer_visible TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE custom_visible_stmt FROM @custom_visible_sql;
EXECUTE custom_visible_stmt;
DEALLOCATE PREPARE custom_visible_stmt;

SET @custom_reason_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'created_reason'
);
SET @custom_reason_sql := IF(
    @custom_reason_exists = 0,
    'ALTER TABLE installments ADD COLUMN created_reason VARCHAR(100) NULL',
    'SELECT 1'
);
PREPARE custom_reason_stmt FROM @custom_reason_sql;
EXECUTE custom_reason_stmt;
DEALLOCATE PREPARE custom_reason_stmt;

UPDATE installments
SET custom_description = notes
WHERE COALESCE(is_custom, 0) = 1
  AND (custom_description IS NULL OR TRIM(custom_description) = '')
  AND notes IS NOT NULL
  AND TRIM(notes) <> '';

UPDATE installments
SET customer_visible = 1
WHERE COALESCE(is_custom, 0) = 1
  AND customer_visible IS NULL;

INSERT INTO settings (setting_key, setting_value, is_secret)
VALUES ('footer_text', 'پروما پی سامانه جامع پرداخت', 0)
ON DUPLICATE KEY UPDATE
  setting_value = CASE
    WHEN TRIM(COALESCE(setting_value, '')) = ''
      OR setting_value = 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani'
    THEN VALUES(setting_value)
    ELSE setting_value
  END;

UPDATE settings SET setting_value = '4' WHERE setting_key = 'contract_print_margin_top' AND setting_value = '5';
UPDATE settings SET setting_value = '7' WHERE setting_key IN ('contract_print_margin_right', 'contract_print_margin_left') AND setting_value = '8';
UPDATE settings SET setting_value = '7' WHERE setting_key = 'contract_print_body_font_size' AND setting_value = '6';
UPDATE settings SET setting_value = '8' WHERE setting_key IN ('contract_print_important_font_size', 'contract_print_heading_font_size') AND setting_value = '7';
UPDATE settings SET setting_value = '1.18' WHERE setting_key IN ('contract_print_body_line_height', 'contract_print_important_line_height') AND setting_value = '1.22';
UPDATE settings SET setting_value = '1.16' WHERE setting_key = 'contract_print_heading_line_height' AND setting_value = '1.2';
UPDATE settings SET setting_value = '.5' WHERE setting_key IN ('contract_print_paragraph_spacing', 'contract_print_section_bottom_spacing', 'contract_print_table_cell_vertical_padding') AND setting_value = '1';
UPDATE settings SET setting_value = '1' WHERE setting_key IN ('contract_print_section_top_spacing', 'contract_print_header_bottom_spacing', 'contract_print_header_divider_spacing', 'contract_print_table_margin') AND setting_value = '2';
UPDATE settings SET setting_value = '32' WHERE setting_key = 'contract_print_logo_width' AND setting_value = '30';
UPDATE settings SET setting_value = '12' WHERE setting_key = 'contract_print_logo_height' AND setting_value = '11';
UPDATE settings SET setting_value = '6.2' WHERE setting_key = 'contract_print_table_font_size' AND setting_value = '5.5';
UPDATE settings SET setting_value = '6.7' WHERE setting_key = 'contract_print_table_heading_font_size' AND setting_value = '6';
UPDATE settings SET setting_value = '1.12' WHERE setting_key = 'contract_print_table_line_height' AND setting_value = '1.15';
UPDATE settings SET setting_value = '5' WHERE setting_key = 'contract_print_signature_top_spacing' AND setting_value = '6';
