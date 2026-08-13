-- Proma Pay V1.5.7: immutable installment change/void audit trail.
CREATE TABLE IF NOT EXISTS installment_change_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(48) NOT NULL,
  installment_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  request_type VARCHAR(24) NOT NULL,
  status VARCHAR(24) NOT NULL,
  before_snapshot_json LONGTEXT NOT NULL,
  requested_snapshot_json LONGTEXT NOT NULL,
  dependency_snapshot_json LONGTEXT NULL,
  reason TEXT NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  affected_installment_ids_json TEXT NULL,
  calculation_version VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  applied_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installment_change_request (request_id),
  KEY idx_installment_change_contract_status (contract_id, status),
  KEY idx_installment_change_installment (installment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_voids (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  installment_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  previous_amount BIGINT NOT NULL,
  previous_due_date DATE NOT NULL,
  void_reason TEXT NOT NULL,
  voided_by BIGINT UNSIGNED NOT NULL,
  request_id VARCHAR(48) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installment_void_once (installment_id),
  KEY idx_installment_void_contract (contract_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
