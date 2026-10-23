-- Additive, tenant-scoped storage for issued quote snapshots consumed by checkout orders.
CREATE TABLE IF NOT EXISTS merchant_api_quotes (
  quote_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  policy_version_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_json LONGTEXT NOT NULL,
  quote_json LONGTEXT NOT NULL,
  expires_at_epoch BIGINT UNSIGNED NOT NULL,
  consumed_order_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  consumed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (quote_uuid),
  UNIQUE KEY uq_merchant_api_quote_consumed_order (consumed_order_uuid),
  KEY idx_merchant_api_quote_scope (connection_id,merchant_id,site_id,expires_at_epoch),
  KEY idx_merchant_api_quote_expiry (expires_at_epoch),
  CONSTRAINT fk_merchant_api_quote_connection FOREIGN KEY (connection_id,merchant_id,site_id) REFERENCES merchant_connections (id,merchant_id,site_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_quote_site FOREIGN KEY (site_id,merchant_id) REFERENCES merchant_sites (id,merchant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_quote_merchant FOREIGN KEY (merchant_id) REFERENCES merchant_accounts (id) ON DELETE RESTRICT,
  CONSTRAINT chk_merchant_api_quote_consumption CHECK (
    (consumed_order_uuid IS NULL AND consumed_at IS NULL)
    OR (consumed_order_uuid IS NOT NULL AND consumed_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
