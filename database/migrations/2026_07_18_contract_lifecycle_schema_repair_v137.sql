-- Proma Pay V1.3.7: idempotent repair for installations that skipped lifecycle migrations.
-- This migration is executed only by the updater, never during ordinary requests.

SET @contracts_cancelled_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'cancelled_at');
SET @contracts_cancelled_at_sql := IF(@contracts_cancelled_at_exists = 0, 'ALTER TABLE contracts ADD COLUMN cancelled_at DATETIME NULL AFTER status', 'SELECT 1');
PREPARE contracts_cancelled_at_stmt FROM @contracts_cancelled_at_sql;
EXECUTE contracts_cancelled_at_stmt;
DEALLOCATE PREPARE contracts_cancelled_at_stmt;

SET @contracts_cancelled_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'cancelled_by');
SET @contracts_cancelled_by_sql := IF(@contracts_cancelled_by_exists = 0, 'ALTER TABLE contracts ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at', 'SELECT 1');
PREPARE contracts_cancelled_by_stmt FROM @contracts_cancelled_by_sql;
EXECUTE contracts_cancelled_by_stmt;
DEALLOCATE PREPARE contracts_cancelled_by_stmt;

SET @contracts_cancellation_reason_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'cancellation_reason');
SET @contracts_cancellation_reason_sql := IF(@contracts_cancellation_reason_exists = 0, 'ALTER TABLE contracts ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_by', 'SELECT 1');
PREPARE contracts_cancellation_reason_stmt FROM @contracts_cancellation_reason_sql;
EXECUTE contracts_cancellation_reason_stmt;
DEALLOCATE PREPARE contracts_cancellation_reason_stmt;

SET @contracts_previous_status_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'previous_status');
SET @contracts_previous_status_sql := IF(@contracts_previous_status_exists = 0, 'ALTER TABLE contracts ADD COLUMN previous_status VARCHAR(30) NULL AFTER cancellation_reason', 'SELECT 1');
PREPARE contracts_previous_status_stmt FROM @contracts_previous_status_sql;
EXECUTE contracts_previous_status_stmt;
DEALLOCATE PREPARE contracts_previous_status_stmt;

SET @contracts_cancellation_metadata_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'cancellation_metadata_json');
SET @contracts_cancellation_metadata_sql := IF(@contracts_cancellation_metadata_exists = 0, 'ALTER TABLE contracts ADD COLUMN cancellation_metadata_json LONGTEXT NULL AFTER previous_status', 'SELECT 1');
PREPARE contracts_cancellation_metadata_stmt FROM @contracts_cancellation_metadata_sql;
EXECUTE contracts_cancellation_metadata_stmt;
DEALLOCATE PREPARE contracts_cancellation_metadata_stmt;

SET @installments_cancelled_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'cancelled_at');
SET @installments_cancelled_at_sql := IF(@installments_cancelled_at_exists = 0, 'ALTER TABLE installments ADD COLUMN cancelled_at DATETIME NULL AFTER status', 'SELECT 1');
PREPARE installments_cancelled_at_stmt FROM @installments_cancelled_at_sql;
EXECUTE installments_cancelled_at_stmt;
DEALLOCATE PREPARE installments_cancelled_at_stmt;

SET @installments_cancelled_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'cancelled_by');
SET @installments_cancelled_by_sql := IF(@installments_cancelled_by_exists = 0, 'ALTER TABLE installments ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at', 'SELECT 1');
PREPARE installments_cancelled_by_stmt FROM @installments_cancelled_by_sql;
EXECUTE installments_cancelled_by_stmt;
DEALLOCATE PREPARE installments_cancelled_by_stmt;

SET @installments_cancellation_reason_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'cancellation_reason');
SET @installments_cancellation_reason_sql := IF(@installments_cancellation_reason_exists = 0, 'ALTER TABLE installments ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_by', 'SELECT 1');
PREPARE installments_cancellation_reason_stmt FROM @installments_cancellation_reason_sql;
EXECUTE installments_cancellation_reason_stmt;
DEALLOCATE PREPARE installments_cancellation_reason_stmt;

SET @payments_is_corrected_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'is_corrected');
SET @payments_is_corrected_sql := IF(@payments_is_corrected_exists = 0, 'ALTER TABLE payments ADD COLUMN is_corrected TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE payments_is_corrected_stmt FROM @payments_is_corrected_sql;
EXECUTE payments_is_corrected_stmt;
DEALLOCATE PREPARE payments_is_corrected_stmt;

SET @payments_correction_reason_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'correction_reason');
SET @payments_correction_reason_sql := IF(@payments_correction_reason_exists = 0, 'ALTER TABLE payments ADD COLUMN correction_reason TEXT NULL', 'SELECT 1');
PREPARE payments_correction_reason_stmt FROM @payments_correction_reason_sql;
EXECUTE payments_correction_reason_stmt;
DEALLOCATE PREPARE payments_correction_reason_stmt;

SET @payments_corrected_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'corrected_at');
SET @payments_corrected_at_sql := IF(@payments_corrected_at_exists = 0, 'ALTER TABLE payments ADD COLUMN corrected_at DATETIME NULL', 'SELECT 1');
PREPARE payments_corrected_at_stmt FROM @payments_corrected_at_sql;
EXECUTE payments_corrected_at_stmt;
DEALLOCATE PREPARE payments_corrected_at_stmt;

SET @payments_corrected_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'corrected_by');
SET @payments_corrected_by_sql := IF(@payments_corrected_by_exists = 0, 'ALTER TABLE payments ADD COLUMN corrected_by BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE payments_corrected_by_stmt FROM @payments_corrected_by_sql;
EXECUTE payments_corrected_by_stmt;
DEALLOCATE PREPARE payments_corrected_by_stmt;

SET @payments_correction_snapshot_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'correction_snapshot_json');
SET @payments_correction_snapshot_sql := IF(@payments_correction_snapshot_exists = 0, 'ALTER TABLE payments ADD COLUMN correction_snapshot_json LONGTEXT NULL', 'SELECT 1');
PREPARE payments_correction_snapshot_stmt FROM @payments_correction_snapshot_sql;
EXECUTE payments_correction_snapshot_stmt;
DEALLOCATE PREPARE payments_correction_snapshot_stmt;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_number VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_action VARCHAR(100) NOT NULL,
    event_result VARCHAR(50) NOT NULL DEFAULT 'success',
    severity VARCHAR(50) NOT NULL DEFAULT 'medium',
    actor_type VARCHAR(50) NOT NULL DEFAULT 'user',
    actor_user_id BIGINT UNSIGNED NULL,
    related_type VARCHAR(100) NOT NULL,
    related_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(20) NULL,
    request_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_audit_logs_audit_number (audit_number),
    KEY idx_audit_logs_contract_id (contract_id),
    KEY idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_change_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    change_type VARCHAR(80) NOT NULL,
    old_value_json LONGTEXT NULL,
    new_value_json LONGTEXT NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_contract_change_logs_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_contract_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    rendered_title VARCHAR(190) NULL,
    rendered_header TEXT NULL,
    rendered_body LONGTEXT NOT NULL,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_generated_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_corrections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    corrected_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_payment_correction (payment_id),
    KEY idx_payment_correction_contract (contract_id),
    KEY idx_payment_correction_installment (installment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_deletion_archives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(80) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    deletion_reason TEXT NOT NULL,
    gateway_warning TEXT NULL,
    corrected_payment_count INT UNSIGNED NOT NULL DEFAULT 0,
    snapshot_json LONGTEXT NOT NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_contract_deletion_archive (contract_id, created_at),
    KEY idx_contract_deletion_archives_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    rendered_title VARCHAR(190) NULL,
    rendered_header TEXT NULL,
    rendered_body LONGTEXT NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'generated',
    checksum CHAR(64) NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_contract_document_version (contract_id, version_number),
    KEY idx_contract_document_versions_published (contract_id, is_published, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    aggregate_type VARCHAR(80) NULL,
    aggregate_id BIGINT UNSIGNED NULL,
    payload_json LONGTEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    last_error TEXT NULL,
    next_attempt_at DATETIME NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_system_outbox_status (status, next_attempt_at, id),
    KEY idx_system_outbox_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
