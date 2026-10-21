-- PATCH 12: workflow dates, medals metadata, notification/card-transfer settings.
-- Defensive statements: safe to rerun on upgraded databases.

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE legal_cases ADD COLUMN notice_date DATE NULL AFTER complaint_number', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'notice_date');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE legal_cases ADD COLUMN court_date DATE NULL AFTER notice_date', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'court_date');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE legal_cases ADD COLUMN hearing_date DATE NULL AFTER court_date', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'hearing_date');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE medals ADD COLUMN icon_key VARCHAR(40) NULL DEFAULT ''award'' AFTER code', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medals' AND COLUMN_NAME = 'icon_key');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE medals ADD COLUMN source VARCHAR(40) NOT NULL DEFAULT ''manual'' AFTER icon_key', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medals' AND COLUMN_NAME = 'source');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE medals ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER source', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medals' AND COLUMN_NAME = 'is_active');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE medals ADD COLUMN updated_at DATETIME NULL AFTER created_at', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medals' AND COLUMN_NAME = 'updated_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
('contract_number_format', 'PR-{SERIAL:6}', 0),
('card_transfer_enabled', '1', 0),
('card_transfer_account_name', '', 0),
('card_transfer_card_number', '', 0),
('card_transfer_sheba', '', 0),
('card_transfer_qr_text', '', 0),
('notifications_sound_enabled', '1', 0),
('notifications_sound_volume', '0.45', 0),
('chat_file_auto_delete_days', '7', 0);
