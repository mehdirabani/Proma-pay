-- Customer archives are logical state. Financial and identity records must
-- prevent a direct DELETE of their owning customer row.
-- Each FK replacement validates existing rows while the ALTER is atomic.

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'archived_at') = 0,
  'ALTER TABLE users ADD COLUMN archived_at DATETIME NULL',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'archived_by') = 0,
  'ALTER TABLE users ADD COLUMN archived_by BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'archive_reason') = 0,
  'ALTER TABLE users ADD COLUMN archive_reason TEXT NULL',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'restored_at') = 0,
  'ALTER TABLE users ADD COLUMN restored_at DATETIME NULL',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_role_archived') = 0,
  'ALTER TABLE users ADD KEY idx_users_role_archived (role, archived_at, id)',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

-- MySQL validates existing child rows before accepting each RESTRICT FK. No
-- row is rewritten or deleted; a mismatch aborts the migration for safe repair.
-- Conditional replacement also makes retry safe if a prior DDL statement
-- committed before a later table failed.
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND CONSTRAINT_NAME = 'fk_contract_customer' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE contracts DROP FOREIGN KEY fk_contract_customer',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND CONSTRAINT_NAME = 'fk_contract_customer') = 0,
  'ALTER TABLE contracts ADD CONSTRAINT fk_contract_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND CONSTRAINT_NAME = 'fk_legal_customer' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE legal_cases DROP FOREIGN KEY fk_legal_customer',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND CONSTRAINT_NAME = 'fk_legal_customer') = 0,
  'ALTER TABLE legal_cases ADD CONSTRAINT fk_legal_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND CONSTRAINT_NAME = 'fk_payment_receipts_customer' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE payment_receipts DROP FOREIGN KEY fk_payment_receipts_customer',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND CONSTRAINT_NAME = 'fk_payment_receipts_customer') = 0,
  'ALTER TABLE payment_receipts ADD CONSTRAINT fk_payment_receipts_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'identity_documents' AND CONSTRAINT_NAME = 'fk_identity_documents_user' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE identity_documents DROP FOREIGN KEY fk_identity_documents_user',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'identity_documents' AND CONSTRAINT_NAME = 'fk_identity_documents_user') = 0,
  'ALTER TABLE identity_documents ADD CONSTRAINT fk_identity_documents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'operator_calls' AND CONSTRAINT_NAME = 'fk_call_customer' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE operator_calls DROP FOREIGN KEY fk_call_customer',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'operator_calls' AND CONSTRAINT_NAME = 'fk_call_customer') = 0,
  'ALTER TABLE operator_calls ADD CONSTRAINT fk_call_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;

SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_guarantors' AND CONSTRAINT_NAME = 'fk_guarantor_user' AND DELETE_RULE <> 'RESTRICT') > 0,
  'ALTER TABLE contract_guarantors DROP FOREIGN KEY fk_guarantor_user',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
SET @customer_archive_sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_guarantors' AND CONSTRAINT_NAME = 'fk_guarantor_user') = 0,
  'ALTER TABLE contract_guarantors ADD CONSTRAINT fk_guarantor_user FOREIGN KEY (guarantor_id) REFERENCES users (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE customer_archive_stmt FROM @customer_archive_sql;
EXECUTE customer_archive_stmt;
DEALLOCATE PREPARE customer_archive_stmt;
