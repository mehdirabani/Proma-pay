CREATE TABLE IF NOT EXISTS customer_automation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_key VARCHAR(190) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    payload_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_automation_event_key (event_key),
    KEY idx_customer_automation_customer_date (customer_id, created_at, id),
    KEY idx_customer_automation_installment (installment_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @installment_due_index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND INDEX_NAME = 'idx_installments_due_status_contract'
);
SET @installment_due_index_sql := IF(@installment_due_index_exists = 0,
    'ALTER TABLE installments ADD INDEX idx_installments_due_status_contract (due_date, status, contract_id)', 'SELECT 1');
PREPARE installment_due_index_stmt FROM @installment_due_index_sql;
EXECUTE installment_due_index_stmt;
DEALLOCATE PREPARE installment_due_index_stmt;

SET @legal_customer_timeline_index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND INDEX_NAME = 'idx_legal_customer_updated_id'
);
SET @legal_customer_timeline_index_sql := IF(@legal_customer_timeline_index_exists = 0,
    'ALTER TABLE legal_cases ADD INDEX idx_legal_customer_updated_id (customer_id, updated_at, id)', 'SELECT 1');
PREPARE legal_customer_timeline_index_stmt FROM @legal_customer_timeline_index_sql;
EXECUTE legal_customer_timeline_index_stmt;
DEALLOCATE PREPARE legal_customer_timeline_index_stmt;
