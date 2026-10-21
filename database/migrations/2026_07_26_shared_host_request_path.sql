-- Proma Pay V1.4.7: shared-host request-path hardening.
-- All statements are idempotent and preserve operational data.

SET @legal_contract_status_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'legal_cases'
      AND INDEX_NAME = 'idx_legal_contract_status'
);
SET @legal_contract_status_index_sql := IF(
    @legal_contract_status_index_exists = 0,
    'ALTER TABLE legal_cases ADD INDEX idx_legal_contract_status (contract_id, status)',
    'SELECT 1'
);
PREPARE legal_contract_status_index_stmt FROM @legal_contract_status_index_sql;
EXECUTE legal_contract_status_index_stmt;
DEALLOCATE PREPARE legal_contract_status_index_stmt;

SET @payment_contract_status_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND INDEX_NAME = 'idx_payment_contract_status'
);
SET @payment_contract_status_index_sql := IF(
    @payment_contract_status_index_exists = 0,
    'ALTER TABLE payments ADD INDEX idx_payment_contract_status (contract_id, status, is_corrected, id)',
    'SELECT 1'
);
PREPARE payment_contract_status_index_stmt FROM @payment_contract_status_index_sql;
EXECUTE payment_contract_status_index_stmt;
DEALLOCATE PREPARE payment_contract_status_index_stmt;

-- These values were previously normalized during ordinary page rendering.
-- Move that one-time work into the upgrade so read requests stay read-only.
UPDATE settings
SET setting_value = 'پروما'
WHERE setting_key IN ('system_name', 'logo_text')
  AND setting_value IN ('پرما پرداخت', 'پرما ابزار');

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
    ('calendar_notifications_enabled', '1', 0),
    ('calendar_default_reminder_type', '1_day', 0),
    ('calendar_notify_admin_without_user', '1', 0),
    ('calendar_due_day_repeat_enabled', '1', 0),
    ('calendar_cron_token', SHA2(CONCAT(UUID(), RAND(), NOW(6)), 256), 1);

UPDATE settings
SET setting_value = SHA2(CONCAT(UUID(), RAND(), NOW(6)), 256), is_secret = 1
WHERE setting_key = 'calendar_cron_token'
  AND (setting_value IS NULL OR setting_value = '');
