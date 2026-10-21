-- Retry-safe repair technician and expense approval schema updates.
SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'role_active') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN role_active TINYINT(1) NOT NULL DEFAULT 1 AFTER active',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'specialty') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN specialty VARCHAR(160) NULL AFTER display_name',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'phone') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN phone VARCHAR(32) NULL AFTER specialty',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'internal_code') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN internal_code VARCHAR(40) NULL AFTER phone',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'commission_model') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN commission_model VARCHAR(30) NOT NULL DEFAULT ''fixed'' AFTER internal_code',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'commission_value') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN commission_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER commission_model',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_technicians' AND COLUMN_NAME = 'notes') = 0,
  'ALTER TABLE proma_repair_technicians ADD COLUMN notes TEXT NULL AFTER commission_value',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT ''approved'' AFTER amount',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'expense_date') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN expense_date DATE NULL AFTER status',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'invoice_reference') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN invoice_reference VARCHAR(120) NULL AFTER expense_date',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'note') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN note VARCHAR(500) NULL AFTER invoice_reference',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'submitted_by') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN submitted_by BIGINT UNSIGNED NULL AFTER created_by',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'submitted_at') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN submitted_at DATETIME NULL AFTER submitted_by',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'approved_by') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER submitted_at',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'approved_at') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'rejected_by') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN rejected_by BIGINT UNSIGNED NULL AFTER approved_at',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'rejected_at') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'manager_note') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN manager_note VARCHAR(500) NULL AFTER rejected_at',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'accounting_transaction_id') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN accounting_transaction_id BIGINT UNSIGNED NULL AFTER manager_note',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'settlement_transaction_id') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN settlement_transaction_id BIGINT UNSIGNED NULL AFTER accounting_transaction_id',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND COLUMN_NAME = 'idempotency_key') = 0,
  'ALTER TABLE proma_repair_expenses ADD COLUMN idempotency_key VARCHAR(120) NULL AFTER settlement_transaction_id',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND INDEX_NAME = 'uq_proma_repair_expense_idempotency') = 0,
  'ALTER TABLE proma_repair_expenses ADD UNIQUE KEY uq_proma_repair_expense_idempotency (idempotency_key)',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

SET @proma_repair_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_repair_expenses' AND INDEX_NAME = 'idx_proma_repair_expense_status') = 0,
  'ALTER TABLE proma_repair_expenses ADD KEY idx_proma_repair_expense_status (status, expense_date)',
  'SELECT 1'
);
PREPARE proma_repair_migration_stmt FROM @proma_repair_migration_ddl;
EXECUTE proma_repair_migration_stmt;
DEALLOCATE PREPARE proma_repair_migration_stmt;

UPDATE proma_repair_expenses SET expense_date = DATE(created_at) WHERE expense_date IS NULL;

CREATE TABLE IF NOT EXISTS proma_repair_expense_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  expense_id BIGINT UNSIGNED NOT NULL,
  repair_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(32) NOT NULL,
  previous_amount DECIMAL(18,2) NULL,
  final_amount DECIMAL(18,2) NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_expense_event (expense_id, id),
  KEY idx_proma_repair_expense_event_repair (repair_id, id),
  CONSTRAINT fk_proma_repair_expense_event_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

