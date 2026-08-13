CREATE TABLE IF NOT EXISTS proma_connect_settings (
  setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value LONGTEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_connect_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(120) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  locale VARCHAR(10) NOT NULL DEFAULT 'fa',
  subject VARCHAR(190) NULL,
  body TEXT NOT NULL,
  provider_template_id VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_connect_template (template_key,channel,locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_connect_otp_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  mobile_hash CHAR(64) NOT NULL,
  purpose VARCHAR(80) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_otp_public (public_id),
  KEY idx_otp_mobile_purpose (mobile_hash,purpose,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_connect_channel_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(30) NOT NULL,
  external_id VARCHAR(150) NOT NULL,
  verified_at DATETIME NOT NULL,
  disconnected_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_channel_external (channel,external_id),
  KEY idx_channel_user (user_id,channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_connect_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idempotency_key CHAR(64) NOT NULL,
  provider_key VARCHAR(40) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  recipient_hash CHAR(64) NOT NULL,
  template_key VARCHAR(120) NULL,
  payload_json LONGTEXT NULL,
  provider_message_id VARCHAR(190) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NULL,
  last_error_code VARCHAR(80) NULL,
  last_error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  delivered_at DATETIME NULL,
  UNIQUE KEY uq_delivery_idempotency (idempotency_key),
  KEY idx_delivery_worker (status,next_attempt_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_sign_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  document_version_id BIGINT UNSIGNED NOT NULL,
  document_hash CHAR(64) NOT NULL,
  mode VARCHAR(40) NOT NULL DEFAULT 'internal_acceptance',
  workflow VARCHAR(20) NOT NULL DEFAULT 'sequential',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  expires_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_sign_public (public_id),
  KEY idx_sign_contract (contract_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_sign_signers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  signer_role VARCHAR(40) NOT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  full_name_snapshot VARCHAR(190) NOT NULL,
  mobile_hash CHAR(64) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  signed_at DATETIME NULL,
  rejected_at DATETIME NULL,
  UNIQUE KEY uq_request_signer (request_id,signer_role,user_id),
  KEY idx_signer_state (request_id,status,sequence_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_sign_evidence (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  signer_id BIGINT UNSIGNED NOT NULL,
  document_hash CHAR(64) NOT NULL,
  evidence_json LONGTEXT NOT NULL,
  evidence_hash CHAR(64) NOT NULL,
  previous_evidence_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_evidence_signer (request_id,signer_id),
  UNIQUE KEY uq_evidence_public (public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_sign_artifacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  checksum CHAR(64) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_artifact_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proma_connect_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_key VARCHAR(40) NOT NULL,
  external_event_id VARCHAR(150) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_webhook_replay (provider_key,external_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
