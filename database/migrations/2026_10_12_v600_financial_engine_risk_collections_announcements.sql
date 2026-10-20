-- V6 additive schema. Financial journal rows are append-only at the application boundary.
CREATE TABLE IF NOT EXISTS financial_ledger_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_uuid CHAR(36) NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id BIGINT UNSIGNED DEFAULT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  effective_at DATETIME NOT NULL,
  posted_by BIGINT UNSIGNED DEFAULT NULL,
  description VARCHAR(500) DEFAULT NULL,
  reverses_event_id BIGINT UNSIGNED DEFAULT NULL,
  metadata_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_financial_ledger_event_uuid (event_uuid),
  UNIQUE KEY uq_financial_ledger_idempotency (idempotency_key),
  KEY idx_financial_ledger_contract (contract_id, id),
  KEY idx_financial_ledger_customer (customer_id, id),
  KEY idx_financial_ledger_source (source_type, source_id),
  KEY idx_financial_ledger_effective_at (effective_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_ledger_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  component VARCHAR(32) NOT NULL,
  delta_toman BIGINT NOT NULL,
  source_line_id BIGINT UNSIGNED DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_financial_ledger_line_event (event_id, id),
  KEY idx_financial_ledger_line_component (component, event_id),
  KEY idx_financial_ledger_source_line (source_line_id),
  CONSTRAINT fk_financial_ledger_line_event FOREIGN KEY (event_id) REFERENCES financial_ledger_events (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  audience_roles_json TEXT NOT NULL,
  local_url VARCHAR(255) DEFAULT NULL,
  published_by BIGINT UNSIGNED NOT NULL,
  published_at DATETIME NOT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_system_announcements_published (published_at, id),
  KEY idx_system_announcements_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_announcement_recipients (
  announcement_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (announcement_id, user_id),
  KEY idx_announcement_recipient_user (user_id, is_read, announcement_id),
  CONSTRAINT fk_announcement_recipient_announcement FOREIGN KEY (announcement_id) REFERENCES system_announcements (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_recipient_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
