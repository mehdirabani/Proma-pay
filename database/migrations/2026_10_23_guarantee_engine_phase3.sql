-- Additive WooCommerce guarantee policy, order-contract binding, and Sayadi audit records.
-- This schema does not rewrite contract_guarantees used by direct sales.
SET @add_merchant_connection_full_scope_key := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE merchant_connections ADD UNIQUE KEY uq_merchant_connection_full_scope (id,merchant_id,site_id)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'merchant_connections'
    AND INDEX_NAME = 'uq_merchant_connection_full_scope'
);
PREPARE merchant_connection_scope_stmt FROM @add_merchant_connection_full_scope_key;
EXECUTE merchant_connection_scope_stmt;
DEALLOCATE PREPARE merchant_connection_scope_stmt;

CREATE TABLE IF NOT EXISTS merchant_central_guarantee_policy_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  minimum_multiplier_units INT UNSIGNED NOT NULL,
  maximum_multiplier_units INT UNSIGNED NOT NULL,
  default_multiplier_units INT UNSIGNED NOT NULL,
  beneficiary_legal_name VARCHAR(190) NOT NULL,
  beneficiary_identifier VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  legal_approval_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  published_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_central_guarantee_uuid (policy_uuid),
  UNIQUE KEY uq_merchant_central_guarantee_version (version_no),
  CONSTRAINT fk_merchant_central_guarantee_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_central_guarantee_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_central_guarantee_policy_head (
  singleton_id TINYINT UNSIGNED NOT NULL,
  policy_version_id BIGINT UNSIGNED NOT NULL,
  selected_by BIGINT UNSIGNED DEFAULT NULL,
  selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (singleton_id),
  CONSTRAINT fk_merchant_central_guarantee_head_version FOREIGN KEY (policy_version_id) REFERENCES merchant_central_guarantee_policy_versions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_central_guarantee_head_selector FOREIGN KEY (selected_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_central_guarantee_policy_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_version_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  metadata_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_merchant_central_guarantee_events (policy_version_id,created_at,id),
  CONSTRAINT fk_merchant_central_guarantee_event_policy FOREIGN KEY (policy_version_id) REFERENCES merchant_central_guarantee_policy_versions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_central_guarantee_event_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_contract_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  external_order_id VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  external_order_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_status ENUM('contracted','cancelled','refunded') NOT NULL DEFAULT 'contracted',
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_contract_link_contract (contract_id),
  UNIQUE KEY uq_merchant_contract_link_order_id (connection_id,external_order_id),
  UNIQUE KEY uq_merchant_contract_link_order_uuid (connection_id,external_order_uuid),
  UNIQUE KEY uq_merchant_contract_link_scope (id,merchant_id,site_id,connection_id,contract_id),
  KEY idx_merchant_contract_link_scope (merchant_id,site_id,connection_id,order_status,id),
  CONSTRAINT fk_merchant_contract_link_connection FOREIGN KEY (connection_id,merchant_id,site_id) REFERENCES merchant_connections (id,merchant_id,site_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_contract_link_site FOREIGN KEY (site_id,merchant_id) REFERENCES merchant_sites (id,merchant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_contract_link_contract FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_contract_link_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_cheque_guarantees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guarantee_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  contract_link_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  policy_version_id BIGINT UNSIGNED NOT NULL,
  policy_type ENUM('financial','guarantee','commission','settlement') NOT NULL DEFAULT 'guarantee',
  central_policy_version_id BIGINT UNSIGNED NOT NULL,
  guarantee_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'sayadi_cheque',
  required_cheque_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cheque_number CHAR(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  bank_name VARCHAR(100) DEFAULT NULL,
  guarantee_base VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scheduled_installments_toman BIGINT UNSIGNED NOT NULL,
  multiplier_units INT UNSIGNED NOT NULL,
  multiplier_scale INT UNSIGNED NOT NULL DEFAULT 10000,
  cheque_amount_rial BIGINT UNSIGNED NOT NULL,
  first_installment_due_date DATE NOT NULL,
  cheque_due_date DATE NOT NULL,
  beneficiary_legal_name VARCHAR(190) NOT NULL,
  beneficiary_identifier VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sayad_status ENUM('pending','registered','confirmed','rejected') NOT NULL DEFAULT 'pending',
  physical_status ENUM('pending','received','returned','released','legal_followup') NOT NULL DEFAULT 'pending',
  registration_method ENUM('authorized_provider','manual_documented') DEFAULT NULL,
  registration_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  registration_evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  registered_at DATETIME DEFAULT NULL,
  confirmation_method ENUM('authorized_provider','manual_documented') DEFAULT NULL,
  confirmation_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  confirmation_evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  confirmed_by BIGINT UNSIGNED DEFAULT NULL,
  confirmed_at DATETIME DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_cheque_guarantee_uuid (guarantee_uuid),
  UNIQUE KEY uq_merchant_cheque_guarantee_contract_link (contract_link_id),
  UNIQUE KEY uq_merchant_cheque_guarantee_id_scope (id,merchant_id,site_id,connection_id),
  KEY idx_merchant_cheque_guarantee_tenant (merchant_id,site_id,connection_id,sayad_status,physical_status),
  CONSTRAINT fk_merchant_cheque_guarantee_link FOREIGN KEY (contract_link_id,merchant_id,site_id,connection_id,contract_id) REFERENCES merchant_contract_links (id,merchant_id,site_id,connection_id,contract_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_cheque_guarantee_policy FOREIGN KEY (policy_version_id,connection_id,policy_type) REFERENCES merchant_policy_versions (id,connection_id,policy_type) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_cheque_guarantee_central_policy FOREIGN KEY (central_policy_version_id) REFERENCES merchant_central_guarantee_policy_versions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_cheque_guarantee_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_cheque_guarantee_confirmer FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_cheque_guarantee_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_cheque_guarantee_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guarantee_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  verification_method ENUM('authorized_provider','manual_documented') DEFAULT NULL,
  evidence_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  metadata_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_merchant_cheque_guarantee_events (guarantee_id,created_at,id),
  KEY idx_merchant_cheque_guarantee_event_scope (merchant_id,site_id,connection_id,created_at,id),
  CONSTRAINT fk_merchant_cheque_guarantee_event_owner FOREIGN KEY (guarantee_id,merchant_id,site_id,connection_id) REFERENCES merchant_cheque_guarantees (id,merchant_id,site_id,connection_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_cheque_guarantee_event_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
