SET @contract_seller_user_id_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'seller_user_id'
);
SET @contract_seller_user_id_sql := IF(
  @contract_seller_user_id_exists = 0,
  'ALTER TABLE contracts ADD COLUMN seller_user_id BIGINT UNSIGNED NULL AFTER assigned_operator_id',
  'SELECT 1'
);
PREPARE contract_seller_user_id_stmt FROM @contract_seller_user_id_sql;
EXECUTE contract_seller_user_id_stmt;
DEALLOCATE PREPARE contract_seller_user_id_stmt;

SET @contract_marketer_user_id_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'marketer_user_id'
);
SET @contract_marketer_user_id_sql := IF(
  @contract_marketer_user_id_exists = 0,
  'ALTER TABLE contracts ADD COLUMN marketer_user_id BIGINT UNSIGNED NULL AFTER seller_user_id',
  'SELECT 1'
);
PREPARE contract_marketer_user_id_stmt FROM @contract_marketer_user_id_sql;
EXECUTE contract_marketer_user_id_stmt;
DEALLOCATE PREPARE contract_marketer_user_id_stmt;

SET @contract_seller_index_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND INDEX_NAME = 'idx_contract_seller'
);
SET @contract_seller_index_sql := IF(
  @contract_seller_index_exists = 0,
  'ALTER TABLE contracts ADD KEY idx_contract_seller (seller_user_id)',
  'SELECT 1'
);
PREPARE contract_seller_index_stmt FROM @contract_seller_index_sql;
EXECUTE contract_seller_index_stmt;
DEALLOCATE PREPARE contract_seller_index_stmt;

SET @contract_marketer_index_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND INDEX_NAME = 'idx_contract_marketer'
);
SET @contract_marketer_index_sql := IF(
  @contract_marketer_index_exists = 0,
  'ALTER TABLE contracts ADD KEY idx_contract_marketer (marketer_user_id)',
  'SELECT 1'
);
PREPARE contract_marketer_index_stmt FROM @contract_marketer_index_sql;
EXECUTE contract_marketer_index_stmt;
DEALLOCATE PREPARE contract_marketer_index_stmt;
