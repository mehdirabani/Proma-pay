CREATE TABLE IF NOT EXISTS proma_connect_provider_test_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id CHAR(36) NOT NULL,
  provider_key VARCHAR(40) NOT NULL,
  recipient_hash CHAR(64) NOT NULL,
  send_mode VARCHAR(30) NOT NULL,
  external_message_id VARCHAR(190) NULL,
  status VARCHAR(30) NOT NULL,
  duration_ms INT UNSIGNED NULL,
  error_code VARCHAR(100) NULL,
  tested_by BIGINT UNSIGNED NOT NULL,
  tested_at DATETIME NOT NULL,
  UNIQUE KEY uq_connect_provider_test_request (request_id),
  KEY idx_connect_provider_test (provider_key,status,tested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
