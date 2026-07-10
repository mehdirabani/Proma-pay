CREATE TABLE IF NOT EXISTS backup_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    file_name VARCHAR(255) NULL,
    status VARCHAR(40) NOT NULL,
    message TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_backup_logs_user (user_id),
    INDEX idx_backup_logs_action (action)
);
