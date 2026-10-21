CREATE TABLE IF NOT EXISTS accounting_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_uuid VARCHAR(160) NOT NULL,
    plugin_id VARCHAR(100) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    account_user_id BIGINT UNSIGNED NOT NULL,
    operation_type VARCHAR(40) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    ledger_entry_id BIGINT UNSIGNED NULL,
    response_code SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    last_error TEXT NULL,
    UNIQUE KEY uq_accounting_requests_uuid (request_uuid),
    KEY idx_accounting_requests_status_created (status, created_at),
    KEY idx_accounting_requests_ledger (ledger_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
