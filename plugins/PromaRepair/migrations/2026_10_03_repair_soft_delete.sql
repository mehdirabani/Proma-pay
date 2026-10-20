-- Retry-safe soft-delete metadata for repair orders.
SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repairs' AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE proma_repairs ADD COLUMN deleted_at DATETIME NULL AFTER updated_at',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repairs' AND COLUMN_NAME = 'deleted_by') = 0,
  'ALTER TABLE proma_repairs ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repairs' AND INDEX_NAME = 'idx_proma_repair_deleted') = 0,
  'ALTER TABLE proma_repairs ADD KEY idx_proma_repair_deleted (deleted_at, id)',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

