-- Proma Pay V1.4.3: shared-IP login isolation and profile-review query support.
-- Legacy IP-scoped attempts are intentionally retained. The runtime no longer
-- reads them, and update migrations must not remove production audit history.

SET @profile_review_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'profile_update_requests'
      AND INDEX_NAME = 'idx_profile_request_status_created'
);
SET @profile_review_index_sql := IF(
    @profile_review_index_exists = 0,
    'ALTER TABLE profile_update_requests ADD INDEX idx_profile_request_status_created (status, created_at, id)',
    'SELECT 1'
);
PREPARE profile_review_index_stmt FROM @profile_review_index_sql;
EXECUTE profile_review_index_stmt;
DEALLOCATE PREPARE profile_review_index_stmt;
