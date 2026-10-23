-- Additive test-environment public API gateway request, rate-limit, and webhook outbox schema.
-- API-created orders remain pending review and do not create or activate financial contracts.

CREATE TABLE IF NOT EXISTS merchant_api_rate_limits (
  connection_id BIGINT UNSIGNED NOT NULL,
  window_started_at DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (connection_id,window_started_at),
  KEY idx_merchant_api_rate_limit_cleanup (window_started_at),
  CONSTRAINT fk_merchant_api_rate_limit_connection FOREIGN KEY (connection_id) REFERENCES merchant_connections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_api_idempotency (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  connection_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_method VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_path VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state ENUM('processing','completed') NOT NULL DEFAULT 'processing',
  response_status SMALLINT UNSIGNED DEFAULT NULL,
  response_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_api_idempotency_key (connection_id,idempotency_key),
  KEY idx_merchant_api_idempotency_created (connection_id,created_at,id),
  CONSTRAINT fk_merchant_api_idempotency_connection FOREIGN KEY (connection_id) REFERENCES merchant_connections (id) ON DELETE RESTRICT,
  CONSTRAINT chk_merchant_api_idempotency_response CHECK (
    (state = 'processing' AND response_status IS NULL AND response_json IS NULL AND completed_at IS NULL)
    OR (state = 'completed' AND response_status BETWEEN 200 AND 599 AND response_json IS NOT NULL AND completed_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_api_order_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  external_order_id VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status ENUM('pending_review','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_review',
  contract_request_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  contract_request_status ENUM('not_requested','pending_review','approved','rejected') NOT NULL DEFAULT 'not_requested',
  external_customer_reference_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  financial_policy_version_id BIGINT UNSIGNED NOT NULL,
  financial_policy_type ENUM('financial','guarantee','commission','settlement') NOT NULL DEFAULT 'financial',
  financial_policy_version_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  financial_policy_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cash_price_toman BIGINT UNSIGNED NOT NULL,
  down_payment_toman BIGINT UNSIGNED NOT NULL,
  financed_principal_toman BIGINT UNSIGNED NOT NULL,
  months SMALLINT UNSIGNED NOT NULL,
  monthly_rate_bps SMALLINT UNSIGNED NOT NULL,
  interest_method ENUM('flat','compound','reducing_balance') NOT NULL,
  request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_json LONGTEXT NOT NULL,
  quote_json LONGTEXT NOT NULL,
  reviewed_by BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_api_order_uuid (order_uuid),
  UNIQUE KEY uq_merchant_api_order_scoped_uuid (order_uuid,merchant_id,site_id,connection_id),
  UNIQUE KEY uq_merchant_api_order_external (connection_id,external_order_id),
  UNIQUE KEY uq_merchant_api_contract_request_uuid (contract_request_uuid),
  KEY idx_merchant_api_order_review (merchant_id,status,created_at,id),
  KEY idx_merchant_api_contract_request (connection_id,contract_request_status,created_at,id),
  CONSTRAINT fk_merchant_api_order_connection FOREIGN KEY (connection_id,merchant_id,site_id) REFERENCES merchant_connections (id,merchant_id,site_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_order_site FOREIGN KEY (site_id,merchant_id) REFERENCES merchant_sites (id,merchant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_order_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_accounts (id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_order_policy FOREIGN KEY (financial_policy_version_id,connection_id,financial_policy_type) REFERENCES merchant_policy_versions (id,connection_id,policy_type) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_order_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_merchant_api_order_money CHECK (
    cash_price_toman > 0 AND down_payment_toman < cash_price_toman
    AND financed_principal_toman = cash_price_toman - down_payment_toman
    AND months BETWEEN 1 AND 480 AND monthly_rate_bps <= 10000 AND financial_policy_type = 'financial'
  ),
  CONSTRAINT chk_merchant_api_contract_request CHECK (
    (contract_request_status = 'not_requested' AND contract_request_uuid IS NULL AND external_customer_reference_hash IS NULL)
    OR (contract_request_status <> 'not_requested' AND contract_request_uuid IS NOT NULL AND external_customer_reference_hash IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_api_webhook_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  payload_json LONGTEXT NOT NULL,
  payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  delivery_status ENUM('queued','processing','delivered','dead_letter') NOT NULL DEFAULT 'queued',
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME DEFAULT NULL,
  last_http_status SMALLINT UNSIGNED DEFAULT NULL,
  last_response_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  last_error_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_api_webhook_event_uuid (event_uuid),
  UNIQUE KEY uq_merchant_api_webhook_source (connection_id,event_type,source_uuid),
  KEY idx_merchant_api_webhook_delivery (connection_id,delivery_status,next_attempt_at,id),
  CONSTRAINT fk_merchant_api_webhook_order FOREIGN KEY (source_uuid,merchant_id,site_id,connection_id) REFERENCES merchant_api_order_requests (order_uuid,merchant_id,site_id,connection_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_webhook_connection FOREIGN KEY (connection_id,merchant_id,site_id) REFERENCES merchant_connections (id,merchant_id,site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
