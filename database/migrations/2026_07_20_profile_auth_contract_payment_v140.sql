CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_type VARCHAR(20) NOT NULL,
    scope_hash CHAR(64) NOT NULL,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_failed_at DATETIME NULL,
    last_failed_at DATETIME NULL,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_auth_login_attempt_scope (scope_type, scope_hash),
    KEY idx_auth_login_attempt_lock (locked_until),
    KEY idx_auth_login_attempt_last_failure (last_failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_uuid VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    request_hash CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'processing',
    contract_id BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_contract_requests_uuid (request_uuid),
    KEY idx_contract_requests_contract (contract_id),
    KEY idx_contract_requests_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @contract_document_versions_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_document_versions'
);
SET @document_template_version_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_document_versions' AND COLUMN_NAME = 'template_version_id'
);
SET @document_template_version_sql := IF(
    @contract_document_versions_exists = 1 AND @document_template_version_exists = 0,
    'ALTER TABLE contract_document_versions ADD COLUMN template_version_id BIGINT UNSIGNED NULL AFTER contract_id',
    'SELECT 1'
);
PREPARE document_template_version_stmt FROM @document_template_version_sql;
EXECUTE document_template_version_stmt;
DEALLOCATE PREPARE document_template_version_stmt;

SET @contract_template_versions_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions'
);

SET @template_archived_at_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_at'
);
SET @template_archived_at_sql := IF(
    @contract_template_versions_exists = 1 AND @template_archived_at_exists = 0,
    'ALTER TABLE contract_template_versions ADD COLUMN archived_at DATETIME NULL AFTER superseded_at',
    'SELECT 1'
);
PREPARE template_archived_at_stmt FROM @template_archived_at_sql;
EXECUTE template_archived_at_stmt;
DEALLOCATE PREPARE template_archived_at_stmt;

SET @template_archived_by_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_by'
);
SET @template_archived_by_sql := IF(
    @contract_template_versions_exists = 1 AND @template_archived_by_exists = 0,
    'ALTER TABLE contract_template_versions ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at',
    'SELECT 1'
);
PREPARE template_archived_by_stmt FROM @template_archived_by_sql;
EXECUTE template_archived_by_stmt;
DEALLOCATE PREPARE template_archived_by_stmt;

UPDATE profile_update_requests
SET payload_json = JSON_REMOVE(payload_json, '$.password', '$.avatar_key')
WHERE status = 'pending' AND JSON_VALID(payload_json) = 1;
