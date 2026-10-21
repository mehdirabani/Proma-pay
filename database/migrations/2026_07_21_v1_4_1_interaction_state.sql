CREATE TABLE IF NOT EXISTS chat_channel_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_chat_channel_read_user (channel_id, user_id),
    KEY idx_chat_channel_reads_user (user_id, last_read_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_duplicate_repairs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canonical_contract_id BIGINT UNSIGNED NOT NULL,
    duplicate_contract_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    confidence VARCHAR(20) NOT NULL,
    snapshot_json LONGTEXT NULL,
    repaired_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_contract_duplicate_repair (duplicate_contract_id),
    KEY idx_contract_duplicate_canonical (canonical_contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @template_archived_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_at');
SET @template_archived_at_sql := IF(@template_archived_at_exists = 0, 'ALTER TABLE contract_template_versions ADD COLUMN archived_at DATETIME NULL AFTER superseded_at', 'SELECT 1');
PREPARE template_archived_at_stmt FROM @template_archived_at_sql;
EXECUTE template_archived_at_stmt;
DEALLOCATE PREPARE template_archived_at_stmt;

SET @template_archived_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_versions' AND COLUMN_NAME = 'archived_by');
SET @template_archived_by_sql := IF(@template_archived_by_exists = 0, 'ALTER TABLE contract_template_versions ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at', 'SELECT 1');
PREPARE template_archived_by_stmt FROM @template_archived_by_sql;
EXECUTE template_archived_by_stmt;
DEALLOCATE PREPARE template_archived_by_stmt;

SET @template_audit_ip_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_template_audit_logs' AND COLUMN_NAME = 'ip_address');
SET @template_audit_ip_sql := IF(@template_audit_ip_exists = 0, 'ALTER TABLE contract_template_audit_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER reason', 'SELECT 1');
PREPARE template_audit_ip_stmt FROM @template_audit_ip_sql;
EXECUTE template_audit_ip_stmt;
DEALLOCATE PREPARE template_audit_ip_stmt;

SET @avatar_path_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path');
SET @avatar_path_sql := IF(@avatar_path_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_path VARCHAR(500) NULL AFTER avatar_key', 'SELECT 1');
PREPARE avatar_path_stmt FROM @avatar_path_sql;
EXECUTE avatar_path_stmt;
DEALLOCATE PREPARE avatar_path_stmt;

SET @avatar_version_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_version');
SET @avatar_version_sql := IF(@avatar_version_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER avatar_path', 'SELECT 1');
PREPARE avatar_version_stmt FROM @avatar_version_sql;
EXECUTE avatar_version_stmt;
DEALLOCATE PREPARE avatar_version_stmt;

SET @avatar_updated_at_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_updated_at');
SET @avatar_updated_at_sql := IF(@avatar_updated_at_exists = 0, 'ALTER TABLE users ADD COLUMN avatar_updated_at DATETIME NULL AFTER avatar_version', 'SELECT 1');
PREPARE avatar_updated_at_stmt FROM @avatar_updated_at_sql;
EXECUTE avatar_updated_at_stmt;
DEALLOCATE PREPARE avatar_updated_at_stmt;

SET @notification_delivered_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'delivered_at');
SET @notification_delivered_sql := IF(@notification_delivered_exists = 0, 'ALTER TABLE notifications ADD COLUMN delivered_at DATETIME NULL AFTER is_read', 'SELECT 1');
PREPARE notification_delivered_stmt FROM @notification_delivered_sql;
EXECUTE notification_delivered_stmt;
DEALLOCATE PREPARE notification_delivered_stmt;

SET @notification_seen_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'seen_at');
SET @notification_seen_sql := IF(@notification_seen_exists = 0, 'ALTER TABLE notifications ADD COLUMN seen_at DATETIME NULL AFTER delivered_at', 'SELECT 1');
PREPARE notification_seen_stmt FROM @notification_seen_sql;
EXECUTE notification_seen_stmt;
DEALLOCATE PREPARE notification_seen_stmt;

SET @notification_archived_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'archived_at');
SET @notification_archived_sql := IF(@notification_archived_exists = 0, 'ALTER TABLE notifications ADD COLUMN archived_at DATETIME NULL AFTER read_at', 'SELECT 1');
PREPARE notification_archived_stmt FROM @notification_archived_sql;
EXECUTE notification_archived_stmt;
DEALLOCATE PREPARE notification_archived_stmt;

SET @notification_deleted_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'deleted_at');
SET @notification_deleted_sql := IF(@notification_deleted_exists = 0, 'ALTER TABLE notifications ADD COLUMN deleted_at DATETIME NULL AFTER archived_at', 'SELECT 1');
PREPARE notification_deleted_stmt FROM @notification_deleted_sql;
EXECUTE notification_deleted_stmt;
DEALLOCATE PREPARE notification_deleted_stmt;

SET @notification_actioned_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'actioned_at');
SET @notification_actioned_sql := IF(@notification_actioned_exists = 0, 'ALTER TABLE notifications ADD COLUMN actioned_at DATETIME NULL AFTER deleted_at', 'SELECT 1');
PREPARE notification_actioned_stmt FROM @notification_actioned_sql;
EXECUTE notification_actioned_stmt;
DEALLOCATE PREPARE notification_actioned_stmt;

SET @notification_dedupe_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'dedupe_key');
SET @notification_dedupe_sql := IF(@notification_dedupe_exists = 0, 'ALTER TABLE notifications ADD COLUMN dedupe_key VARCHAR(190) NULL AFTER actioned_at', 'SELECT 1');
PREPARE notification_dedupe_stmt FROM @notification_dedupe_sql;
EXECUTE notification_dedupe_stmt;
DEALLOCATE PREPARE notification_dedupe_stmt;

SET @profile_reviewed_fields_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile_update_requests' AND COLUMN_NAME = 'reviewed_fields_json');
SET @profile_reviewed_fields_sql := IF(@profile_reviewed_fields_exists = 0, 'ALTER TABLE profile_update_requests ADD COLUMN reviewed_fields_json LONGTEXT NULL AFTER review_notes', 'SELECT 1');
PREPARE profile_reviewed_fields_stmt FROM @profile_reviewed_fields_sql;
EXECUTE profile_reviewed_fields_stmt;
DEALLOCATE PREPARE profile_reviewed_fields_stmt;

SET @profile_rejected_fields_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile_update_requests' AND COLUMN_NAME = 'rejected_fields_json');
SET @profile_rejected_fields_sql := IF(@profile_rejected_fields_exists = 0, 'ALTER TABLE profile_update_requests ADD COLUMN rejected_fields_json LONGTEXT NULL AFTER reviewed_fields_json', 'SELECT 1');
PREPARE profile_rejected_fields_stmt FROM @profile_rejected_fields_sql;
EXECUTE profile_rejected_fields_stmt;
DEALLOCATE PREPARE profile_rejected_fields_stmt;

SET @profile_snapshot_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile_update_requests' AND COLUMN_NAME = 'current_snapshot_json');
SET @profile_snapshot_sql := IF(@profile_snapshot_exists = 0, 'ALTER TABLE profile_update_requests ADD COLUMN current_snapshot_json LONGTEXT NULL AFTER rejected_fields_json', 'SELECT 1');
PREPARE profile_snapshot_stmt FROM @profile_snapshot_sql;
EXECUTE profile_snapshot_stmt;
DEALLOCATE PREPARE profile_snapshot_stmt;

SET @profile_response_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile_update_requests' AND COLUMN_NAME = 'customer_response');
SET @profile_response_sql := IF(@profile_response_exists = 0, 'ALTER TABLE profile_update_requests ADD COLUMN customer_response TEXT NULL AFTER current_snapshot_json', 'SELECT 1');
PREPARE profile_response_stmt FROM @profile_response_sql;
EXECUTE profile_response_stmt;
DEALLOCATE PREPARE profile_response_stmt;

UPDATE notifications SET delivered_at = COALESCE(delivered_at, created_at) WHERE delivered_at IS NULL;

SET @event_created_by_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'created_by');
SET @event_created_by_sql := IF(@event_created_by_exists = 0, 'ALTER TABLE events ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER assigned_user_id', 'SELECT 1');
PREPARE event_created_by_stmt FROM @event_created_by_sql;
EXECUTE event_created_by_stmt;
DEALLOCATE PREPARE event_created_by_stmt;

SET @event_priority_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'priority');
SET @event_priority_sql := IF(@event_priority_exists = 0, 'ALTER TABLE events ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER event_type', 'SELECT 1');
PREPARE event_priority_stmt FROM @event_priority_sql;
EXECUTE event_priority_stmt;
DEALLOCATE PREPARE event_priority_stmt;

SET @event_status_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'status');
SET @event_status_sql := IF(@event_status_exists = 0, 'ALTER TABLE events ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''scheduled'' AFTER priority', 'SELECT 1');
PREPARE event_status_stmt FROM @event_status_sql;
EXECUTE event_status_stmt;
DEALLOCATE PREPARE event_status_stmt;

DELETE duplicate_notification
FROM notifications duplicate_notification
JOIN notifications original_notification
  ON original_notification.user_id = duplicate_notification.user_id
 AND original_notification.dedupe_key = duplicate_notification.dedupe_key
 AND original_notification.id < duplicate_notification.id
WHERE duplicate_notification.dedupe_key IS NOT NULL;

SET @notification_dedupe_index_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'uq_notification_recipient_dedupe');
SET @notification_dedupe_index_sql := IF(@notification_dedupe_index_exists = 0, 'ALTER TABLE notifications ADD UNIQUE KEY uq_notification_recipient_dedupe (user_id, dedupe_key)', 'SELECT 1');
PREPARE notification_dedupe_index_stmt FROM @notification_dedupe_index_sql;
EXECUTE notification_dedupe_index_stmt;
DEALLOCATE PREPARE notification_dedupe_index_stmt;

SET @medal_behavior_type_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medal_definitions' AND COLUMN_NAME = 'behavior_type');
SET @medal_behavior_type_sql := IF(@medal_behavior_type_exists = 0, 'ALTER TABLE medal_definitions ADD COLUMN behavior_type VARCHAR(30) NOT NULL DEFAULT ''permanent'' AFTER award_type', 'SELECT 1');
PREPARE medal_behavior_type_stmt FROM @medal_behavior_type_sql;
EXECUTE medal_behavior_type_stmt;
DEALLOCATE PREPARE medal_behavior_type_stmt;

SET @medal_revocation_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medal_definitions' AND COLUMN_NAME = 'revocation_behavior');
SET @medal_revocation_sql := IF(@medal_revocation_exists = 0, 'ALTER TABLE medal_definitions ADD COLUMN revocation_behavior VARCHAR(30) NOT NULL DEFAULT ''never'' AFTER behavior_type', 'SELECT 1');
PREPARE medal_revocation_stmt FROM @medal_revocation_sql;
EXECUTE medal_revocation_stmt;
DEALLOCATE PREPARE medal_revocation_stmt;

SET @medal_reactivation_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medal_definitions' AND COLUMN_NAME = 'reactivation_behavior');
SET @medal_reactivation_sql := IF(@medal_reactivation_exists = 0, 'ALTER TABLE medal_definitions ADD COLUMN reactivation_behavior VARCHAR(30) NOT NULL DEFAULT ''restore'' AFTER revocation_behavior', 'SELECT 1');
PREPARE medal_reactivation_stmt FROM @medal_reactivation_sql;
EXECUTE medal_reactivation_stmt;
DEALLOCATE PREPARE medal_reactivation_stmt;

SET @medal_last_evaluated_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medal_definitions' AND COLUMN_NAME = 'last_evaluated_at');
SET @medal_last_evaluated_sql := IF(@medal_last_evaluated_exists = 0, 'ALTER TABLE medal_definitions ADD COLUMN last_evaluated_at DATETIME NULL AFTER updated_at', 'SELECT 1');
PREPARE medal_last_evaluated_stmt FROM @medal_last_evaluated_sql;
EXECUTE medal_last_evaluated_stmt;
DEALLOCATE PREPARE medal_last_evaluated_stmt;

UPDATE medal_definitions
SET behavior_type = 'reversible', revocation_behavior = 'automatic', reactivation_behavior = 'restore'
WHERE slug = 'no-overdue';
