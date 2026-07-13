-- Review center receipts, public chat channel, and channel-capable messages.
-- This migration is defensive so it can be rerun on partially upgraded databases.

CREATE TABLE IF NOT EXISTS chat_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'public',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_chat_channels_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO chat_channels (title, slug, type, is_pinned, is_system, created_at)
VALUES ('اطلاع‌رسانی عمومی', 'public-announcements', 'public', 1, 1, NOW());

SET @message_channel_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER receiver_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'channel_id'
);
PREPARE stmt FROM @message_channel_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @message_target_unit_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD COLUMN target_unit VARCHAR(80) NULL AFTER is_read',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'target_unit'
);
PREPARE stmt FROM @message_target_unit_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @message_is_system_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER target_unit',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'is_system'
);
PREPARE stmt FROM @message_is_system_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @receiver_fk := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'receiver_id'
      AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);
SET @drop_receiver_fk_sql := IF(@receiver_fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE messages DROP FOREIGN KEY ', @receiver_fk));
PREPARE stmt FROM @drop_receiver_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE messages MODIFY receiver_id BIGINT UNSIGNED NULL;

SET @receiver_fk_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD CONSTRAINT fk_message_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'receiver_id'
      AND REFERENCED_TABLE_NAME = 'users'
);
PREPARE stmt FROM @receiver_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @message_channel_index_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD INDEX idx_message_channel (channel_id, id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND INDEX_NAME = 'idx_message_channel'
);
PREPARE stmt FROM @message_channel_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @message_channel_fk_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE messages ADD CONSTRAINT fk_message_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND COLUMN_NAME = 'channel_id'
      AND REFERENCED_TABLE_NAME = 'chat_channels'
);
PREPARE stmt FROM @message_channel_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    receipt_path VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    KEY idx_payment_receipts_status (status),
    KEY idx_payment_receipts_customer (customer_id),
    CONSTRAINT fk_payment_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
