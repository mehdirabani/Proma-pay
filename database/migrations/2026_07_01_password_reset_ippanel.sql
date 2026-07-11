CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    mobile VARCHAR(30) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_password_resets_user (user_id, used_at, expires_at),
    KEY idx_password_resets_expiry (expires_at, used_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, is_secret) VALUES
('password_reset_enabled', '1', 0),
('ippanel_api_key', '', 1),
('ippanel_from_number', '', 0),
('ippanel_password_reset_pattern_code', '', 0),
('ippanel_password_reset_pattern_key', 'code', 0)
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
