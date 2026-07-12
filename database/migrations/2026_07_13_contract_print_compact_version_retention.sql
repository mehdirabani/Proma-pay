SET @archived_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_at');
SET @archived_at_sql := IF(@archived_at_exists = 0, 'ALTER TABLE contract_template_versions ADD COLUMN archived_at DATETIME NULL AFTER superseded_at', 'SELECT 1');
PREPARE archived_at_stmt FROM @archived_at_sql;
EXECUTE archived_at_stmt;
DEALLOCATE PREPARE archived_at_stmt;

SET @archived_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_by');
SET @archived_by_sql := IF(@archived_by_exists = 0, 'ALTER TABLE contract_template_versions ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at', 'SELECT 1');
PREPARE archived_by_stmt FROM @archived_by_sql;
EXECUTE archived_by_stmt;
DEALLOCATE PREPARE archived_by_stmt;

SET @audit_ip_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_audit_logs' AND COLUMN_NAME = 'ip_address');
SET @audit_ip_sql := IF(@audit_ip_exists = 0, 'ALTER TABLE contract_template_audit_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER reason', 'SELECT 1');
PREPARE audit_ip_stmt FROM @audit_ip_sql;
EXECUTE audit_ip_stmt;
DEALLOCATE PREPARE audit_ip_stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
('contract_print_important_font_size', '7', 0),
('contract_print_heading_font_size', '7', 0),
('contract_print_important_line_height', '1.22', 0),
('contract_print_heading_line_height', '1.2', 0),
('contract_print_list_spacing', '1', 0),
('contract_print_list_indent', '10', 0),
('contract_print_table_heading_font_size', '6', 0),
('contract_print_table_line_height', '1.15', 0),
('contract_print_table_margin', '2', 0);

UPDATE settings SET setting_value = 'official_compact' WHERE setting_key = 'contract_print_preset' AND setting_value = 'compact';
UPDATE settings SET setting_value = '5' WHERE setting_key = 'contract_print_margin_top' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '8' WHERE setting_key IN ('contract_print_margin_right', 'contract_print_margin_left') AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '7' WHERE setting_key = 'contract_print_margin_bottom' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '6' WHERE setting_key = 'contract_print_body_font_size' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '1.22' WHERE setting_key = 'contract_print_body_line_height' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '1' WHERE setting_key IN ('contract_print_paragraph_spacing', 'contract_print_section_bottom_spacing', 'contract_print_table_cell_vertical_padding') AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '2' WHERE setting_key IN ('contract_print_section_top_spacing', 'contract_print_header_bottom_spacing', 'contract_print_header_divider_spacing', 'contract_print_table_cell_horizontal_padding') AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '30' WHERE setting_key = 'contract_print_logo_width' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '11' WHERE setting_key = 'contract_print_logo_height' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '5.5' WHERE setting_key = 'contract_print_table_font_size' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '6' WHERE setting_key = 'contract_print_signature_top_spacing' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
UPDATE settings SET setting_value = '15' WHERE setting_key = 'contract_print_signature_box_height' AND (SELECT setting_value FROM (SELECT * FROM settings) p WHERE p.setting_key = 'contract_print_preset' LIMIT 1) = 'official_compact';
