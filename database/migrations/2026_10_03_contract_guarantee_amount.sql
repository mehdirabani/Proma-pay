SET @contract_guarantee_amount_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_guarantees'
    AND COLUMN_NAME = 'guarantee_amount'
);
SET @contract_guarantee_amount_sql := IF(
  @contract_guarantee_amount_exists = 0,
  'ALTER TABLE contract_guarantees ADD COLUMN guarantee_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER guarantee_count',
  'SELECT 1'
);
PREPARE contract_guarantee_amount_stmt FROM @contract_guarantee_amount_sql;
EXECUTE contract_guarantee_amount_stmt;
DEALLOCATE PREPARE contract_guarantee_amount_stmt;
