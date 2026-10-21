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

CREATE TABLE IF NOT EXISTS payment_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_uuid VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'processing',
    payment_id BIGINT UNSIGNED NULL,
    response_code VARCHAR(60) NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_payment_requests_uuid (request_uuid),
    KEY idx_payment_requests_installment (installment_id),
    KEY idx_payment_requests_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
