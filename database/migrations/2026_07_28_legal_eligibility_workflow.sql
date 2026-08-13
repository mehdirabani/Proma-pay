-- Proma Pay V1.5.0: versioned legal eligibility and safe internal-document workflow.
-- All DDL is migration-only; no page request performs runtime schema changes.

CREATE TABLE IF NOT EXISTS legal_policy_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_code VARCHAR(64) NOT NULL DEFAULT 'default',
  version_number INT UNSIGNED NOT NULL,
  policy_json LONGTEXT NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_policy_version (policy_code, version_number),
  KEY idx_legal_policy_active (policy_code, is_active, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_legal_policy_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  policy_version_id BIGINT UNSIGNED NULL,
  policy_version_number INT UNSIGNED NOT NULL DEFAULT 0,
  policy_json LONGTEXT NOT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'legacy_review_required',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contract_legal_policy_snapshot (contract_id),
  KEY idx_contract_legal_policy_version (policy_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_case_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_uuid VARCHAR(64) NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  legal_case_id BIGINT UNSIGNED NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_case_request_uuid (request_uuid),
  KEY idx_legal_case_request_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_uuid VARCHAR(64) NOT NULL,
  request_uuid VARCHAR(64) NOT NULL,
  legal_case_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body LONGTEXT NOT NULL,
  document_status VARCHAR(50) NOT NULL DEFAULT 'internal_draft',
  deadline_date DATE NULL,
  policy_snapshot_json LONGTEXT NULL,
  debt_snapshot_json LONGTEXT NULL,
  external_reference VARCHAR(190) NULL,
  external_authority VARCHAR(190) NULL,
  external_submitted_at DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  externally_confirmed_by BIGINT UNSIGNED NULL,
  externally_confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_document_uuid (document_uuid),
  UNIQUE KEY uq_legal_document_request (request_uuid),
  KEY idx_legal_document_case (legal_case_id, document_status),
  KEY idx_legal_document_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @add_legal_case_request_uuid := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE legal_cases ADD COLUMN request_uuid VARCHAR(64) NULL AFTER notes',
  'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'request_uuid');
PREPARE stmt FROM @add_legal_case_request_uuid; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_legal_case_referred_at := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE legal_cases ADD COLUMN legal_referred_at DATETIME NULL AFTER request_uuid',
  'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'legal_referred_at');
PREPARE stmt FROM @add_legal_case_referred_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_legal_case_archived_at := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE legal_cases ADD COLUMN archived_at DATETIME NULL AFTER legal_referred_at',
  'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND COLUMN_NAME = 'archived_at');
PREPARE stmt FROM @add_legal_case_archived_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @add_legal_case_request_index := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE legal_cases ADD UNIQUE KEY uq_legal_case_request_uuid (request_uuid)',
  'SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND INDEX_NAME = 'uq_legal_case_request_uuid');
PREPARE stmt FROM @add_legal_case_request_index; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
 ('legal_delay_value', '30', 0),
 ('legal_delay_unit', 'day', 0),
 ('legal_overdue_count_threshold', '1', 0),
 ('legal_overdue_amount_enabled', '0', 0),
 ('legal_overdue_amount_threshold', '0', 0),
 ('legal_eligibility_operator', 'delay_only', 0),
 ('legal_warning_before_days', '0', 0),
 ('legal_allow_self_initiation', '1', 0);

INSERT IGNORE INTO legal_policy_versions (policy_code, version_number, policy_json, is_active, created_at)
VALUES ('default', 1, '{"delay_value":30,"delay_unit":"day","overdue_count_threshold":1,"overdue_amount_enabled":0,"overdue_amount_threshold":0,"eligibility_operator":"delay_only","warning_before_days":0,"allow_self_initiation":1,"legal_referred_at_mode":"persisted_referral_confirmation"}', 1, NOW());
