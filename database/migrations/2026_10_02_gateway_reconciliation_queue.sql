-- Verified external payments whose internal allocation cannot be committed
-- remain visible for manual financial reconciliation. No schema is created
-- during an ordinary request.
SET @gateway_verified_amount_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'gateway_verified_amount'
);
SET @gateway_verified_amount_sql := IF(@gateway_verified_amount_exists = 0,
    'ALTER TABLE payment_groups ADD COLUMN gateway_verified_amount BIGINT UNSIGNED DEFAULT NULL AFTER gateway_track_id', 'SELECT 1');
PREPARE gateway_verified_amount_stmt FROM @gateway_verified_amount_sql;
EXECUTE gateway_verified_amount_stmt;
DEALLOCATE PREPARE gateway_verified_amount_stmt;

SET @gateway_ref_id_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'gateway_ref_id'
);
SET @gateway_ref_id_sql := IF(@gateway_ref_id_exists = 0,
    'ALTER TABLE payment_groups ADD COLUMN gateway_ref_id VARCHAR(100) DEFAULT NULL AFTER gateway_verified_amount', 'SELECT 1');
PREPARE gateway_ref_id_stmt FROM @gateway_ref_id_sql;
EXECUTE gateway_ref_id_stmt;
DEALLOCATE PREPARE gateway_ref_id_stmt;

SET @reconciliation_reason_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'reconciliation_reason'
);
SET @reconciliation_reason_sql := IF(@reconciliation_reason_exists = 0,
    'ALTER TABLE payment_groups ADD COLUMN reconciliation_reason VARCHAR(255) DEFAULT NULL AFTER gateway_ref_id', 'SELECT 1');
PREPARE reconciliation_reason_stmt FROM @reconciliation_reason_sql;
EXECUTE reconciliation_reason_stmt;
DEALLOCATE PREPARE reconciliation_reason_stmt;

SET @payment_group_review_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND INDEX_NAME = 'idx_payment_groups_review_queue'
);
SET @payment_group_review_index_sql := IF(@payment_group_review_index_exists = 0,
    'ALTER TABLE payment_groups ADD INDEX idx_payment_groups_review_queue (status, created_at)', 'SELECT 1');
PREPARE payment_group_review_index_stmt FROM @payment_group_review_index_sql;
EXECUTE payment_group_review_index_stmt;
DEALLOCATE PREPARE payment_group_review_index_stmt;
