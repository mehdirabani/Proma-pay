CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    event_type VARCHAR(40) NOT NULL DEFAULT 'general',
    description TEXT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'primary',
    reminder_type VARCHAR(40) NULL,
    reminder_at DATETIME NULL,
    reminder_sent_at DATETIME NULL,
    due_day_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_events_date (event_date),
    KEY idx_events_assigned (assigned_user_id),
    KEY idx_events_reminder (reminder_at, reminder_sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @add_assigned_user := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER user_id', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'assigned_user_id'
);
PREPARE stmt FROM @add_assigned_user; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_event_time := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN event_time TIME NULL AFTER event_date', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'event_time'
);
PREPARE stmt FROM @add_event_time; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_event_type := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT ''general'' AFTER event_time', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'event_type'
);
PREPARE stmt FROM @add_event_type; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_reminder_type := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN reminder_type VARCHAR(40) NULL AFTER color', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'reminder_type'
);
PREPARE stmt FROM @add_reminder_type; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_reminder_at := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN reminder_at DATETIME NULL AFTER reminder_type', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'reminder_at'
);
PREPARE stmt FROM @add_reminder_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_reminder_sent := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN reminder_sent_at DATETIME NULL AFTER reminder_at', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'reminder_sent_at'
);
PREPARE stmt FROM @add_reminder_sent; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_due_day_sent := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD COLUMN due_day_sent_at DATETIME NULL AFTER reminder_sent_at', 'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'due_day_sent_at'
);
PREPARE stmt FROM @add_due_day_sent; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE events
SET assigned_user_id = user_id
WHERE assigned_user_id IS NULL AND user_id IS NOT NULL;

SET @add_idx_assigned := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD INDEX idx_events_assigned (assigned_user_id)', 'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_assigned'
);
PREPARE stmt FROM @add_idx_assigned; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_idx_reminder := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE events ADD INDEX idx_events_reminder (reminder_at, reminder_sent_at)', 'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_reminder'
);
PREPARE stmt FROM @add_idx_reminder; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
('calendar_notifications_enabled', '1', 0),
('calendar_default_reminder_type', '1_day', 0),
('calendar_notify_admin_without_user', '1', 0),
('calendar_due_day_repeat_enabled', '1', 0),
('calendar_cron_token', REPLACE(UUID(), '-', ''), 1);
