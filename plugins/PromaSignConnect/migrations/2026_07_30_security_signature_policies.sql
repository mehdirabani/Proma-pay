CREATE TABLE IF NOT EXISTS proma_connect_module_policies (
  section_key VARCHAR(80) NOT NULL PRIMARY KEY,
  policy_version INT UNSIGNED NOT NULL DEFAULT 1,
  policy_json LONGTEXT NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_sign_signer_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_type VARCHAR(60) NOT NULL,
  signer_role VARCHAR(60) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  workflow VARCHAR(20) NOT NULL DEFAULT 'sequential',
  sequence_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  otp_required TINYINT(1) NOT NULL DEFAULT 1,
  drawn_signature_required TINYINT(1) NOT NULL DEFAULT 0,
  typed_name_required TINYINT(1) NOT NULL DEFAULT 1,
  expires_hours INT UNSIGNED NOT NULL DEFAULT 72,
  reminder_hours INT UNSIGNED NOT NULL DEFAULT 24,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_signer_policy (document_type,signer_role),
  KEY idx_signer_policy_active (document_type,is_active,sequence_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_sign_integrity_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NULL,
  run_type VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL,
  checked_count INT UNSIGNED NOT NULL DEFAULT 0,
  mismatch_count INT UNSIGNED NOT NULL DEFAULT 0,
  result_json LONGTEXT NULL,
  request_uuid CHAR(36) NOT NULL,
  requested_by BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_integrity_run_request (request_uuid),
  KEY idx_integrity_run_status (status,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_legal_holds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  released_by BIGINT UNSIGNED NULL,
  released_at DATETIME NULL,
  UNIQUE KEY uq_legal_hold (entity_type,entity_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_otp_security_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  challenge_public_id CHAR(36) NULL,
  user_id BIGINT UNSIGNED NULL,
  mobile_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  purpose VARCHAR(80) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_otp_security_mobile (mobile_hash,created_at),
  KEY idx_otp_security_ip (ip_hash,created_at),
  KEY idx_otp_security_user (user_id,created_at),
  KEY idx_otp_security_purpose (purpose,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO proma_connect_module_policies
(section_key,policy_version,policy_json,is_enabled,updated_by,updated_at)
VALUES
('mfa',1,'{"mode":"disabled","roles":[],"sensitive_actions":true,"trusted_device_days":30,"max_trusted_devices":5,"primary_channel":"smsir","fallback_channel":"ippanel","step_up_minutes":10}',0,NULL,NOW()),
('otp-security',1,'{"code_length":6,"ttl_seconds":180,"resend_seconds":60,"max_attempts":5,"hourly_limit":5,"daily_limit":15,"ip_hourly_limit":20,"active_challenge_limit":1,"lock_seconds":900,"invalidate_previous":true,"replay_protection":true}',1,NULL,NOW()),
('hashes',1,'{"algorithm":"sha256","hmac_algorithm":"sha256","canonicalization":"json-c14n-v1","evidence_schema":"1","active_key_version":"1","fingerprint_length":16,"scheduled_audit":true}',1,NULL,NOW()),
('document-versions',1,'{"auto_first_version":true,"new_on_relevant_change":true,"stale_detection":true,"duplicate_prevention":true,"protect_finalized":true,"customer_visible":"published","archive_drafts_days":90}',1,NULL,NOW()),
('evidence-retention',1,'{"otp_audit_days":180,"delivery_success_days":180,"delivery_failed_days":365,"webhook_days":90,"temporary_link_days":7,"draft_days":90,"archive_before_delete":true,"batch_size":100,"legal_hold_required":true}',1,NULL,NOW());

INSERT IGNORE INTO proma_sign_signer_policies
(document_type,signer_role,is_required,workflow,sequence_no,otp_required,drawn_signature_required,typed_name_required,expires_hours,reminder_hours,is_active,created_at)
VALUES
('contract','customer',1,'sequential',1,1,0,1,72,24,1,NOW()),
('contract','guarantor_1',0,'sequential',2,1,0,1,72,24,1,NOW()),
('contract','seller',1,'sequential',3,1,0,1,72,24,1,NOW());
