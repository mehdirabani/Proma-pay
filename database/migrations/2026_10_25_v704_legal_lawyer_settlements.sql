SET @reimburse_lawyer_column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_case_costs' AND COLUMN_NAME = 'reimburse_lawyer_id'
);
SET @reimburse_lawyer_column_sql := IF(
  @reimburse_lawyer_column_exists = 0,
  'ALTER TABLE legal_case_costs ADD COLUMN reimburse_lawyer_id BIGINT UNSIGNED NULL AFTER paid_by, ADD KEY idx_legal_cost_reimburse_lawyer (reimburse_lawyer_id, approval_status, cost_date)',
  'SET @migration_noop = 1'
);
PREPARE reimburse_lawyer_column_stmt FROM @reimburse_lawyer_column_sql;
EXECUTE reimburse_lawyer_column_stmt;
DEALLOCATE PREPARE reimburse_lawyer_column_stmt;

CREATE TABLE IF NOT EXISTS legal_lawyer_payment_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_uuid VARCHAR(64) NOT NULL,
  lawyer_id BIGINT UNSIGNED NOT NULL,
  amount_toman BIGINT NOT NULL,
  paid_at DATE NOT NULL,
  payment_reference VARCHAR(190) NULL,
  note TEXT NULL,
  idempotency_key VARCHAR(64) NOT NULL,
  request_fingerprint CHAR(64) NOT NULL,
  is_reversal TINYINT(1) NOT NULL DEFAULT 0,
  reversal_of_batch_id BIGINT UNSIGNED NULL,
  reversal_reason TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_lawyer_batch_uuid (batch_uuid),
  UNIQUE KEY uq_legal_lawyer_batch_request (idempotency_key),
  UNIQUE KEY uq_legal_lawyer_batch_reversal (reversal_of_batch_id),
  KEY idx_legal_lawyer_batch_lawyer_date (lawyer_id, paid_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_lawyer_payment_allocations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_batch_id BIGINT UNSIGNED NOT NULL,
  legal_case_cost_id BIGINT UNSIGNED NOT NULL,
  allocated_amount_toman BIGINT NOT NULL,
  reversal_of_allocation_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_lawyer_batch_cost (payment_batch_id, legal_case_cost_id),
  UNIQUE KEY uq_legal_lawyer_allocation_reversal (reversal_of_allocation_id),
  KEY idx_legal_lawyer_allocation_cost (legal_case_cost_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_lawyer_adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_uuid VARCHAR(64) NOT NULL,
  lawyer_id BIGINT UNSIGNED NOT NULL,
  entry_type VARCHAR(20) NOT NULL,
  signed_amount_toman BIGINT NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  request_fingerprint CHAR(64) NOT NULL,
  effective_at DATE NOT NULL,
  description TEXT NOT NULL,
  reversal_of_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_lawyer_adjustment_uuid (entry_uuid),
  UNIQUE KEY uq_legal_lawyer_adjustment_request (idempotency_key),
  UNIQUE KEY uq_legal_lawyer_adjustment_reversal (reversal_of_id),
  KEY idx_legal_lawyer_adjustment_lawyer_date (lawyer_id, effective_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
