CREATE TABLE IF NOT EXISTS ai_action_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  instruction TEXT NOT NULL,
  response_json LONGTEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'previewed',
  applied_summary TEXT NULL,
  created_at DATETIME NOT NULL,
  confirmed_at DATETIME NULL,
  KEY idx_ai_action_user (user_id),
  KEY idx_ai_action_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
