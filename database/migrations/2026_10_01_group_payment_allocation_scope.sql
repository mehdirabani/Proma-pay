-- Persist whether a multi-installment payment was quoted against selected
-- rows only or against the full eligible contract schedule (overflow policy).
SET @payment_group_allocation_scope_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_groups' AND COLUMN_NAME = 'allocation_scope'
);
SET @payment_group_allocation_scope_sql := IF(
    @payment_group_allocation_scope_exists = 0,
    'ALTER TABLE payment_groups ADD COLUMN allocation_scope VARCHAR(20) NOT NULL DEFAULT ''selected'' AFTER payment_request_uuid',
    'SELECT 1'
);
PREPARE payment_group_allocation_scope_stmt FROM @payment_group_allocation_scope_sql;
EXECUTE payment_group_allocation_scope_stmt;
DEALLOCATE PREPARE payment_group_allocation_scope_stmt;
