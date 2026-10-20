CREATE TABLE IF NOT EXISTS web_push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  p256dh VARCHAR(120) NOT NULL,
  auth_secret VARCHAR(120) NOT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_success_at DATETIME DEFAULT NULL,
  last_error_code VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_push_endpoint_hash (endpoint_hash),
  KEY idx_web_push_user_active (user_id,is_active,id),
  CONSTRAINT fk_web_push_subscription_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_push_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id BIGINT UNSIGNED NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  http_status SMALLINT UNSIGNED DEFAULT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  error_code VARCHAR(80) DEFAULT NULL,
  delivered_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_push_delivery_event (subscription_id,event_key),
  KEY idx_web_push_delivery_status (status,updated_at),
  CONSTRAINT fk_web_push_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES web_push_subscriptions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key,setting_value,is_secret) VALUES
('web_push_enabled','0',0),
('web_push_event_types_json','["payment","payment_receipt","legal","identity","profile","overdue","calendar","message","bot","repair","installment","due_soon","due_today","followup","system"]',0);

UPDATE settings SET setting_value=JSON_ARRAY_APPEND(setting_value,'$','due_soon')
WHERE setting_key='notification_event_types_json' AND JSON_VALID(setting_value)
  AND JSON_SEARCH(setting_value,'one','due_soon') IS NULL;
UPDATE settings SET setting_value=JSON_ARRAY_APPEND(setting_value,'$','due_today')
WHERE setting_key='notification_event_types_json' AND JSON_VALID(setting_value)
  AND JSON_SEARCH(setting_value,'one','due_today') IS NULL;
UPDATE settings SET setting_value=JSON_ARRAY_APPEND(setting_value,'$','followup')
WHERE setting_key='notification_event_types_json' AND JSON_VALID(setting_value)
  AND JSON_SEARCH(setting_value,'one','followup') IS NULL;
