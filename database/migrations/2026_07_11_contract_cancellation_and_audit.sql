ALTER TABLE contracts ADD COLUMN cancelled_at DATETIME NULL AFTER status;
ALTER TABLE contracts ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at;
ALTER TABLE contracts ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_by;

ALTER TABLE installments ADD COLUMN cancelled_at DATETIME NULL AFTER status;
ALTER TABLE installments ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at;
ALTER TABLE installments ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_by;

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
    old_values JSON NULL,
    new_values JSON NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(20) NULL,
    request_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_audit_logs_audit_number (audit_number),
    KEY idx_audit_logs_event_type (event_type),
    KEY idx_audit_logs_event_action (event_action),
    KEY idx_audit_logs_related (related_type, related_id),
    KEY idx_audit_logs_contract_id (contract_id),
    KEY idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
