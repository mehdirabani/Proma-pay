CREATE TABLE IF NOT EXISTS contract_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    current_version_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_contract_templates_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_template_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    body_source LONGTEXT NOT NULL,
    body_format VARCHAR(30) NOT NULL DEFAULT 'plain_text_v1',
    content_hash CHAR(64) NOT NULL,
    change_reason TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    published_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    superseded_at DATETIME NULL,
    UNIQUE KEY uq_contract_template_version (template_id, version_number),
    KEY idx_contract_template_versions_status (template_id, status, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_template_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(80) NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    version_id BIGINT UNSIGNED NULL,
    old_values_json LONGTEXT NULL,
    new_values_json LONGTEXT NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_contract_template_audit_created (created_at),
    KEY idx_contract_template_audit_template (template_id, version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_document_rebuild_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_template_version_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    total_documents INT UNSIGNED NOT NULL DEFAULT 0,
    processed_documents INT UNSIGNED NOT NULL DEFAULT 0,
    failed_documents INT UNSIGNED NOT NULL DEFAULT 0,
    last_contract_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    failure_report_json LONGTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_contract_rebuild_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contract_templates (id, name, description, status, created_at)
VALUES (1, 'قالب اصلی قرارداد', 'قالب پیش‌فرض اسناد قرارداد', 'active', NOW());

INSERT INTO contract_template_versions
    (template_id, version_number, body_source, body_format, content_hash, change_reason, status, created_at, published_at)
SELECT 1, 1, s.setting_value, 'plain_text_v1', SHA2(CONCAT('plain_text_v1', CHAR(0), s.setting_value), 256),
       'انتقال خودکار قالب قدیمی', 'published', NOW(), NOW()
FROM settings s
WHERE s.setting_key = 'contract_template_body'
  AND TRIM(COALESCE(s.setting_value, '')) <> ''
  AND NOT EXISTS (SELECT 1 FROM contract_template_versions WHERE template_id = 1);

UPDATE contract_templates
SET current_version_id = (SELECT id FROM contract_template_versions WHERE template_id = 1 AND status = 'published' ORDER BY version_number DESC LIMIT 1),
    updated_at = NOW()
WHERE id = 1 AND current_version_id IS NULL;

SET @generated_template_version_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'template_version_id');
SET @generated_template_version_sql := IF(@generated_template_version_exists = 0, 'ALTER TABLE generated_contract_documents ADD COLUMN template_version_id BIGINT UNSIGNED NULL AFTER contract_id', 'SELECT 1');
PREPARE generated_template_version_stmt FROM @generated_template_version_sql;
EXECUTE generated_template_version_stmt;
DEALLOCATE PREPARE generated_template_version_stmt;

SET @generated_template_status_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents' AND COLUMN_NAME = 'template_status');
SET @generated_template_status_sql := IF(@generated_template_status_exists = 0, "ALTER TABLE generated_contract_documents ADD COLUMN template_status VARCHAR(30) NOT NULL DEFAULT 'legacy' AFTER template_version_id", 'SELECT 1');
PREPARE generated_template_status_stmt FROM @generated_template_status_sql;
EXECUTE generated_template_status_stmt;
DEALLOCATE PREPARE generated_template_status_stmt;

SET @document_template_version_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_document_versions' AND COLUMN_NAME = 'template_version_id');
SET @document_template_version_sql := IF(@document_template_version_exists = 0, 'ALTER TABLE contract_document_versions ADD COLUMN template_version_id BIGINT UNSIGNED NULL AFTER contract_id', 'SELECT 1');
PREPARE document_template_version_stmt FROM @document_template_version_sql;
EXECUTE document_template_version_stmt;
DEALLOCATE PREPARE document_template_version_stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
('contract_print_preset', 'compact', 0),
('contract_print_page_size', 'A4', 0),
('contract_print_orientation', 'portrait', 0),
('contract_print_margin_top', '6', 0),
('contract_print_margin_right', '10', 0),
('contract_print_margin_bottom', '10', 0),
('contract_print_margin_left', '10', 0),
('contract_print_body_font_size', '10.5', 0),
('contract_print_body_line_height', '1.45', 0),
('contract_print_paragraph_spacing', '1.5', 0),
('contract_print_section_top_spacing', '2.5', 0),
('contract_print_section_bottom_spacing', '1.5', 0),
('contract_print_header_top_spacing', '0', 0),
('contract_print_header_bottom_spacing', '2.5', 0),
('contract_print_header_divider_spacing', '3', 0),
('contract_print_logo_width', '38', 0),
('contract_print_logo_height', '15', 0),
('contract_print_table_font_size', '9.5', 0),
('contract_print_table_cell_vertical_padding', '1.2', 0),
('contract_print_table_cell_horizontal_padding', '1.8', 0),
('contract_print_signature_top_spacing', '12', 0),
('contract_print_signature_box_height', '22', 0),
('contract_print_show_footer', '0', 0),
('contract_print_show_customer_header', '1', 0),
('contract_print_show_contract_title', '1', 0),
('contract_print_color_mode', 'color', 0);
