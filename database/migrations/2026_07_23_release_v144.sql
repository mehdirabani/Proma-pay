-- Proma Pay V1.4.4: polling query indexes. Idempotent and data preserving.
SET @notification_active_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND INDEX_NAME = 'idx_notification_active_user'
);
SET @notification_active_index_sql := IF(
    @notification_active_index_exists = 0,
    'ALTER TABLE notifications ADD INDEX idx_notification_active_user (user_id, deleted_at, archived_at, id)',
    'SELECT 1'
);
PREPARE notification_active_index_stmt FROM @notification_active_index_sql;
EXECUTE notification_active_index_stmt;
DEALLOCATE PREPARE notification_active_index_stmt;

SET @message_reverse_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND INDEX_NAME = 'idx_message_reverse_pair'
);
SET @message_reverse_index_sql := IF(
    @message_reverse_index_exists = 0,
    'ALTER TABLE messages ADD INDEX idx_message_reverse_pair (receiver_id, sender_id, id)',
    'SELECT 1'
);
PREPARE message_reverse_index_stmt FROM @message_reverse_index_sql;
EXECUTE message_reverse_index_stmt;
DEALLOCATE PREPARE message_reverse_index_stmt;
