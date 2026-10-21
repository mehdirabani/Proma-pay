-- V4.0.0 restores current runtime fields missing from older/inconsistent backups.
-- Existing values and audit rows are preserved; legacy column names remain intact.

SET @payment_request_updated_at_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'updated_at'
);
SET @payment_request_updated_at_sql := IF(
  @payment_request_updated_at_exists = 0,
  'ALTER TABLE payment_requests ADD COLUMN updated_at DATETIME NULL',
  'SELECT 1'
);
PREPARE payment_request_updated_at_stmt FROM @payment_request_updated_at_sql;
EXECUTE payment_request_updated_at_stmt;
DEALLOCATE PREPARE payment_request_updated_at_stmt;

SET @payment_request_error_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'error_message'
);
SET @payment_request_error_sql := IF(
  @payment_request_error_exists = 0,
  'ALTER TABLE payment_requests ADD COLUMN error_message TEXT NULL',
  'SELECT 1'
);
PREPARE payment_request_error_stmt FROM @payment_request_error_sql;
EXECUTE payment_request_error_stmt;
DEALLOCATE PREPARE payment_request_error_stmt;

SET @payment_request_response_type := (
  SELECT DATA_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'response_code'
  LIMIT 1
);
SET @payment_request_response_type_sql := IF(
  @payment_request_response_type IS NOT NULL AND @payment_request_response_type <> 'varchar',
  'ALTER TABLE payment_requests MODIFY COLUMN response_code VARCHAR(60) NULL',
  'SET @migration_noop = 1'
);
PREPARE payment_request_response_type_stmt FROM @payment_request_response_type_sql;
EXECUTE payment_request_response_type_stmt;
DEALLOCATE PREPARE payment_request_response_type_stmt;

SET @outbox_event_type_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'event_type'
);
SET @outbox_event_type_sql := IF(
  @outbox_event_type_exists = 0,
  'ALTER TABLE system_outbox ADD COLUMN event_type VARCHAR(80) NULL',
  'SELECT 1'
);
PREPARE outbox_event_type_stmt FROM @outbox_event_type_sql;
EXECUTE outbox_event_type_stmt;
DEALLOCATE PREPARE outbox_event_type_stmt;

SET @outbox_event_name_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'event_name'
);
SET @outbox_event_backfill_sql := IF(
  @outbox_event_name_exists > 0,
  'UPDATE system_outbox SET event_type = COALESCE(NULLIF(event_type, ''''), NULLIF(event_name, ''''), ''legacy'') WHERE event_type IS NULL OR event_type = ''''',
  'UPDATE system_outbox SET event_type = ''legacy'' WHERE event_type IS NULL OR event_type = '''''
);
PREPARE outbox_event_backfill_stmt FROM @outbox_event_backfill_sql;
EXECUTE outbox_event_backfill_stmt;
DEALLOCATE PREPARE outbox_event_backfill_stmt;

SET @outbox_event_uuid_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'event_uuid'
);
SET @outbox_event_uuid_sql := IF(
  @outbox_event_uuid_exists > 0,
  'ALTER TABLE system_outbox MODIFY COLUMN event_uuid VARCHAR(64) NULL',
  'SET @migration_noop = 1'
);
PREPARE outbox_event_uuid_stmt FROM @outbox_event_uuid_sql;
EXECUTE outbox_event_uuid_stmt;
DEALLOCATE PREPARE outbox_event_uuid_stmt;

SET @outbox_legacy_event_name_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'event_name'
);
SET @outbox_legacy_event_name_sql := IF(
  @outbox_legacy_event_name_exists > 0,
  'ALTER TABLE system_outbox MODIFY COLUMN event_name VARCHAR(255) NULL',
  'SET @migration_noop = 1'
);
PREPARE outbox_legacy_event_name_stmt FROM @outbox_legacy_event_name_sql;
EXECUTE outbox_legacy_event_name_stmt;
DEALLOCATE PREPARE outbox_legacy_event_name_stmt;

ALTER TABLE system_outbox MODIFY event_type VARCHAR(80) NOT NULL;

SET @outbox_max_attempts_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'max_attempts'
);
SET @outbox_max_attempts_sql := IF(
  @outbox_max_attempts_exists = 0,
  'ALTER TABLE system_outbox ADD COLUMN max_attempts INT UNSIGNED NOT NULL DEFAULT 5',
  'SELECT 1'
);
PREPARE outbox_max_attempts_stmt FROM @outbox_max_attempts_sql;
EXECUTE outbox_max_attempts_stmt;
DEALLOCATE PREPARE outbox_max_attempts_stmt;

SET @outbox_next_attempt_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'next_attempt_at'
);
SET @outbox_next_attempt_sql := IF(
  @outbox_next_attempt_exists = 0,
  'ALTER TABLE system_outbox ADD COLUMN next_attempt_at DATETIME NULL',
  'SELECT 1'
);
PREPARE outbox_next_attempt_stmt FROM @outbox_next_attempt_sql;
EXECUTE outbox_next_attempt_stmt;
DEALLOCATE PREPARE outbox_next_attempt_stmt;

SET @outbox_available_at_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'available_at'
);
SET @outbox_retry_time_backfill_sql := IF(
  @outbox_available_at_exists > 0,
  'UPDATE system_outbox SET next_attempt_at = available_at WHERE next_attempt_at IS NULL AND available_at IS NOT NULL',
  'SELECT 1'
);
PREPARE outbox_retry_time_backfill_stmt FROM @outbox_retry_time_backfill_sql;
EXECUTE outbox_retry_time_backfill_stmt;
DEALLOCATE PREPARE outbox_retry_time_backfill_stmt;

SET @outbox_legacy_available_at_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'available_at'
);
SET @outbox_legacy_available_at_sql := IF(
  @outbox_legacy_available_at_exists > 0,
  'ALTER TABLE system_outbox MODIFY COLUMN available_at DATETIME NULL',
  'SET @migration_noop = 1'
);
PREPARE outbox_legacy_available_at_stmt FROM @outbox_legacy_available_at_sql;
EXECUTE outbox_legacy_available_at_stmt;
DEALLOCATE PREPARE outbox_legacy_available_at_stmt;

SET @outbox_updated_at_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND COLUMN_NAME = 'updated_at'
);
SET @outbox_updated_at_sql := IF(
  @outbox_updated_at_exists > 0,
  'ALTER TABLE system_outbox MODIFY COLUMN updated_at DATETIME NULL',
  'SET @migration_noop = 1'
);
PREPARE outbox_updated_at_stmt FROM @outbox_updated_at_sql;
EXECUTE outbox_updated_at_stmt;
DEALLOCATE PREPARE outbox_updated_at_stmt;

SET @outbox_retry_index_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_outbox' AND INDEX_NAME = 'idx_system_outbox_next_attempt'
);
SET @outbox_retry_index_sql := IF(
  @outbox_retry_index_exists = 0,
  'ALTER TABLE system_outbox ADD INDEX idx_system_outbox_next_attempt (status, next_attempt_at, id)',
  'SELECT 1'
);
PREPARE outbox_retry_index_stmt FROM @outbox_retry_index_sql;
EXECUTE outbox_retry_index_stmt;
DEALLOCATE PREPARE outbox_retry_index_stmt;
