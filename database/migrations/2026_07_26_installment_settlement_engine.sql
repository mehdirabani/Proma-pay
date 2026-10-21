-- Canonical installment settlement engine.  Every statement is idempotent so
-- the migration is safe for the shared-host installer and existing tenants.

CREATE TABLE IF NOT EXISTS settlement_quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    quote_uuid VARCHAR(64) NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    scope VARCHAR(20) NOT NULL DEFAULT 'selected',
    selected_installment_ids_json LONGTEXT NOT NULL,
    calculation_date DATE NOT NULL,
    calculated_at DATETIME NOT NULL,
    principal_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    normal_penalty_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    legal_penalty_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    reward_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    final_payable DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    snapshot_hash CHAR(64) NOT NULL,
    calculation_version VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    payment_group_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settlement_quotes_uuid (quote_uuid),
    KEY idx_settlement_quotes_contract_status (contract_id, status, expires_at),
    KEY idx_settlement_quotes_customer (customer_id, created_at),
    KEY idx_settlement_quotes_payment_group (payment_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @installment_settlement_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'effective_settlement_at');
SET @installment_settlement_at_sql := IF(@installment_settlement_at_exists = 0, 'ALTER TABLE installments ADD COLUMN effective_settlement_at DATETIME NULL AFTER last_payment_date', 'SELECT 1');
PREPARE installment_settlement_at_stmt FROM @installment_settlement_at_sql;
EXECUTE installment_settlement_at_stmt;
DEALLOCATE PREPARE installment_settlement_at_stmt;

SET @payment_request_installment_nullable := (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'installment_id' LIMIT 1);
SET @payment_request_installment_sql := IF(@payment_request_installment_nullable = 'NO', 'ALTER TABLE payment_requests MODIFY COLUMN installment_id BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE payment_request_installment_stmt FROM @payment_request_installment_sql;
EXECUTE payment_request_installment_stmt;
DEALLOCATE PREPARE payment_request_installment_stmt;

SET @payment_request_contract_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'contract_id');
SET @payment_request_contract_sql := IF(@payment_request_contract_exists = 0, 'ALTER TABLE payment_requests ADD COLUMN contract_id BIGINT UNSIGNED NULL AFTER installment_id', 'SELECT 1');
PREPARE payment_request_contract_stmt FROM @payment_request_contract_sql;
EXECUTE payment_request_contract_stmt;
DEALLOCATE PREPARE payment_request_contract_stmt;
SET @payment_request_group_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'payment_group_id');
SET @payment_request_group_sql := IF(@payment_request_group_exists = 0, 'ALTER TABLE payment_requests ADD COLUMN payment_group_id BIGINT UNSIGNED NULL AFTER payment_id', 'SELECT 1');
PREPARE payment_request_group_stmt FROM @payment_request_group_sql;
EXECUTE payment_request_group_stmt;
DEALLOCATE PREPARE payment_request_group_stmt;
SET @payment_request_quote_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'quote_uuid');
SET @payment_request_quote_sql := IF(@payment_request_quote_exists = 0, 'ALTER TABLE payment_requests ADD COLUMN quote_uuid VARCHAR(64) NULL AFTER payment_group_id', 'SELECT 1');
PREPARE payment_request_quote_stmt FROM @payment_request_quote_sql;
EXECUTE payment_request_quote_stmt;
DEALLOCATE PREPARE payment_request_quote_stmt;
SET @payment_request_scope_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_requests' AND COLUMN_NAME = 'selection_json');
SET @payment_request_scope_sql := IF(@payment_request_scope_exists = 0, 'ALTER TABLE payment_requests ADD COLUMN selection_json LONGTEXT NULL AFTER quote_uuid', 'SELECT 1');
PREPARE payment_request_scope_stmt FROM @payment_request_scope_sql;
EXECUTE payment_request_scope_stmt;
DEALLOCATE PREPARE payment_request_scope_stmt;

SET @payment_principal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'principal_applied');
SET @payment_principal_sql := IF(@payment_principal_exists = 0, 'ALTER TABLE payments ADD COLUMN principal_applied DECIMAL(18,2) NULL AFTER calculated_reward', 'SELECT 1');
PREPARE payment_principal_stmt FROM @payment_principal_sql;
EXECUTE payment_principal_stmt;
DEALLOCATE PREPARE payment_principal_stmt;
SET @payment_normal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'normal_penalty_applied');
SET @payment_normal_sql := IF(@payment_normal_exists = 0, 'ALTER TABLE payments ADD COLUMN normal_penalty_applied DECIMAL(18,2) NULL AFTER principal_applied', 'SELECT 1');
PREPARE payment_normal_stmt FROM @payment_normal_sql;
EXECUTE payment_normal_stmt;
DEALLOCATE PREPARE payment_normal_stmt;
SET @payment_legal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'legal_penalty_applied');
SET @payment_legal_sql := IF(@payment_legal_exists = 0, 'ALTER TABLE payments ADD COLUMN legal_penalty_applied DECIMAL(18,2) NULL AFTER normal_penalty_applied', 'SELECT 1');
PREPARE payment_legal_stmt FROM @payment_legal_sql;
EXECUTE payment_legal_stmt;
DEALLOCATE PREPARE payment_legal_stmt;
SET @payment_reward_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'reward_applied');
SET @payment_reward_sql := IF(@payment_reward_exists = 0, 'ALTER TABLE payments ADD COLUMN reward_applied DECIMAL(18,2) NULL AFTER legal_penalty_applied', 'SELECT 1');
PREPARE payment_reward_stmt FROM @payment_reward_sql;
EXECUTE payment_reward_stmt;
DEALLOCATE PREPARE payment_reward_stmt;
SET @payment_quote_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'settlement_quote_uuid');
SET @payment_quote_sql := IF(@payment_quote_exists = 0, 'ALTER TABLE payments ADD COLUMN settlement_quote_uuid VARCHAR(64) NULL AFTER payment_group_id', 'SELECT 1');
PREPARE payment_quote_stmt FROM @payment_quote_sql;
EXECUTE payment_quote_stmt;
DEALLOCATE PREPARE payment_quote_stmt;

SET @group_quote_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'quote_uuid');
SET @group_quote_sql := IF(@group_quote_exists = 0, 'ALTER TABLE payment_groups ADD COLUMN quote_uuid VARCHAR(64) NULL AFTER idempotency_key', 'SELECT 1');
PREPARE group_quote_stmt FROM @group_quote_sql;
EXECUTE group_quote_stmt;
DEALLOCATE PREPARE group_quote_stmt;
SET @group_request_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'payment_request_uuid');
SET @group_request_sql := IF(@group_request_exists = 0, 'ALTER TABLE payment_groups ADD COLUMN payment_request_uuid VARCHAR(64) NULL AFTER quote_uuid', 'SELECT 1');
PREPARE group_request_stmt FROM @group_request_sql;
EXECUTE group_request_stmt;
DEALLOCATE PREPARE group_request_stmt;
SET @group_allocation_json_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'allocation_json');
SET @group_allocation_json_sql := IF(@group_allocation_json_exists = 0, 'ALTER TABLE payment_groups ADD COLUMN allocation_json LONGTEXT NULL AFTER selection_json', 'SELECT 1');
PREPARE group_allocation_json_stmt FROM @group_allocation_json_sql;
EXECUTE group_allocation_json_stmt;
DEALLOCATE PREPARE group_allocation_json_stmt;

SET @allocation_principal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'principal_applied');
SET @allocation_principal_sql := IF(@allocation_principal_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN principal_applied DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER allocated_amount', 'SELECT 1');
PREPARE allocation_principal_stmt FROM @allocation_principal_sql;
EXECUTE allocation_principal_stmt;
DEALLOCATE PREPARE allocation_principal_stmt;
SET @allocation_normal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'normal_penalty_applied');
SET @allocation_normal_sql := IF(@allocation_normal_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN normal_penalty_applied DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER principal_applied', 'SELECT 1');
PREPARE allocation_normal_stmt FROM @allocation_normal_sql;
EXECUTE allocation_normal_stmt;
DEALLOCATE PREPARE allocation_normal_stmt;
SET @allocation_legal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'legal_penalty_applied');
SET @allocation_legal_sql := IF(@allocation_legal_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN legal_penalty_applied DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER normal_penalty_applied', 'SELECT 1');
PREPARE allocation_legal_stmt FROM @allocation_legal_sql;
EXECUTE allocation_legal_stmt;
DEALLOCATE PREPARE allocation_legal_stmt;
SET @allocation_reward_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'reward_applied');
SET @allocation_reward_sql := IF(@allocation_reward_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN reward_applied DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER legal_penalty_applied', 'SELECT 1');
PREPARE allocation_reward_stmt FROM @allocation_reward_sql;
EXECUTE allocation_reward_stmt;
DEALLOCATE PREPARE allocation_reward_stmt;
SET @allocation_before_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'remaining_before');
SET @allocation_before_sql := IF(@allocation_before_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN remaining_before DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER reward_applied', 'SELECT 1');
PREPARE allocation_before_stmt FROM @allocation_before_sql;
EXECUTE allocation_before_stmt;
DEALLOCATE PREPARE allocation_before_stmt;
SET @allocation_after_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'remaining_after');
SET @allocation_after_sql := IF(@allocation_after_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN remaining_after DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER remaining_before', 'SELECT 1');
PREPARE allocation_after_stmt FROM @allocation_after_sql;
EXECUTE allocation_after_stmt;
DEALLOCATE PREPARE allocation_after_stmt;
SET @allocation_status_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'status_after');
SET @allocation_status_sql := IF(@allocation_status_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN status_after VARCHAR(30) NULL AFTER remaining_after', 'SELECT 1');
PREPARE allocation_status_stmt FROM @allocation_status_sql;
EXECUTE allocation_status_stmt;
DEALLOCATE PREPARE allocation_status_stmt;
SET @allocation_quote_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'quote_uuid');
SET @allocation_quote_sql := IF(@allocation_quote_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN quote_uuid VARCHAR(64) NULL AFTER status_after', 'SELECT 1');
PREPARE allocation_quote_stmt FROM @allocation_quote_sql;
EXECUTE allocation_quote_stmt;
DEALLOCATE PREPARE allocation_quote_stmt;
SET @allocation_reversal_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'reversal_of_allocation_id');
SET @allocation_reversal_sql := IF(@allocation_reversal_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN reversal_of_allocation_id BIGINT UNSIGNED NULL AFTER quote_uuid', 'SELECT 1');
PREPARE allocation_reversal_stmt FROM @allocation_reversal_sql;
EXECUTE allocation_reversal_stmt;
DEALLOCATE PREPARE allocation_reversal_stmt;
SET @allocation_reversed_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND COLUMN_NAME = 'is_reversal');
SET @allocation_reversed_sql := IF(@allocation_reversed_exists = 0, 'ALTER TABLE payment_allocations ADD COLUMN is_reversal TINYINT(1) NOT NULL DEFAULT 0 AFTER reversal_of_allocation_id', 'SELECT 1');
PREPARE allocation_reversed_stmt FROM @allocation_reversed_sql;
EXECUTE allocation_reversed_stmt;
DEALLOCATE PREPARE allocation_reversed_stmt;

-- Historical schema allowed only one row per group/installment. Reversal is
-- an immutable opposite allocation, therefore it needs a second row.
-- Some older installations use the historical unique index as the implicit
-- supporting index for a foreign key on installment_id.  Add an explicit
-- non-unique index first so dropping the unique key cannot fail with MySQL
-- error 1553 ("needed in a foreign key constraint").
SET @allocation_installment_index_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND INDEX_NAME = 'idx_payment_allocations_installment');
SET @allocation_installment_index_sql := IF(@allocation_installment_index_exists = 0, 'ALTER TABLE payment_allocations ADD INDEX idx_payment_allocations_installment (installment_id)', 'SELECT 1');
PREPARE allocation_installment_index_stmt FROM @allocation_installment_index_sql;
EXECUTE allocation_installment_index_stmt;
DEALLOCATE PREPARE allocation_installment_index_stmt;
SET @allocation_group_index_support_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND INDEX_NAME = 'idx_payment_allocations_group');
SET @allocation_group_index_support_sql := IF(@allocation_group_index_support_exists = 0, 'ALTER TABLE payment_allocations ADD INDEX idx_payment_allocations_group (payment_group_id)', 'SELECT 1');
PREPARE allocation_group_index_support_stmt FROM @allocation_group_index_support_sql;
EXECUTE allocation_group_index_support_stmt;
DEALLOCATE PREPARE allocation_group_index_support_stmt;
SET @allocation_unique_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND INDEX_NAME = 'uq_payment_allocation_installment');
SET @allocation_unique_sql := IF(@allocation_unique_exists > 0, 'ALTER TABLE payment_allocations DROP INDEX uq_payment_allocation_installment', 'SELECT 1');
PREPARE allocation_unique_stmt FROM @allocation_unique_sql;
EXECUTE allocation_unique_stmt;
DEALLOCATE PREPARE allocation_unique_stmt;
SET @allocation_group_index_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND INDEX_NAME = 'idx_payment_allocations_group_installment');
SET @allocation_group_index_sql := IF(@allocation_group_index_exists = 0, 'ALTER TABLE payment_allocations ADD INDEX idx_payment_allocations_group_installment (payment_group_id, installment_id, is_reversal)', 'SELECT 1');
PREPARE allocation_group_index_stmt FROM @allocation_group_index_sql;
EXECUTE allocation_group_index_stmt;
DEALLOCATE PREPARE allocation_group_index_stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES ('settlement_quote_ttl_seconds', '300', 0);
