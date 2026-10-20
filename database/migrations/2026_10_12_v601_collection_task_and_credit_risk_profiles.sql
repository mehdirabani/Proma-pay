-- V6.0.1 compatibility repair for the V6 task queue and persisted credit scores.
-- Idempotent so a partially applied V6.0.0 migration can be safely repaired.
CREATE TABLE IF NOT EXISTS collection_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED DEFAULT NULL,
  stage VARCHAR(40) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  due_at DATETIME NOT NULL,
  dedupe_key VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME DEFAULT NULL,
  completed_by BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_collection_task_dedupe (dedupe_key),
  KEY idx_collection_task_assigned (assigned_to, status, due_at),
  KEY idx_collection_task_contract (contract_id, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_credit_profiles (
  customer_id BIGINT UNSIGNED NOT NULL,
  score SMALLINT UNSIGNED NOT NULL,
  risk_level VARCHAR(16) NOT NULL,
  metrics_json LONGTEXT NOT NULL,
  recommendation_json LONGTEXT NOT NULL,
  reasons_json LONGTEXT NOT NULL,
  rules_hash CHAR(64) NOT NULL,
  calculated_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (customer_id),
  KEY idx_customer_credit_profiles_rank (risk_level, score),
  KEY idx_customer_credit_profiles_calculated (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
