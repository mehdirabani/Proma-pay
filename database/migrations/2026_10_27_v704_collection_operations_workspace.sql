-- Collection workspace extensions retain the existing call and task ledgers.
-- NULL on historical rows means the original action/promise cannot be safely
-- classified or assigned a reconstructed financial snapshot.

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='action_type')=0,
  'ALTER TABLE operator_calls ADD COLUMN action_type VARCHAR(40) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='contact_target')=0,
  'ALTER TABLE operator_calls ADD COLUMN contact_target VARCHAR(32) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='result_code')=0,
  'ALTER TABLE operator_calls ADD COLUMN result_code VARCHAR(40) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='promise_amount_toman')=0,
  'ALTER TABLE operator_calls ADD COLUMN promise_amount_toman BIGINT UNSIGNED NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='promise_event_type')=0,
  'ALTER TABLE operator_calls ADD COLUMN promise_event_type VARCHAR(20) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='related_promise_call_id')=0,
  'ALTER TABLE operator_calls ADD COLUMN related_promise_call_id BIGINT UNSIGNED NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='next_action_type')=0,
  'ALTER TABLE operator_calls ADD COLUMN next_action_type VARCHAR(40) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='baseline_overdue_amount_toman')=0,
  'ALTER TABLE operator_calls ADD COLUMN baseline_overdue_amount_toman BIGINT UNSIGNED NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND COLUMN_NAME='baseline_snapshot_status')=0,
  'ALTER TABLE operator_calls ADD COLUMN baseline_snapshot_status VARCHAR(24) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='action_type')=0,
  'ALTER TABLE collection_tasks ADD COLUMN action_type VARCHAR(40) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='priority')=0,
  'ALTER TABLE collection_tasks ADD COLUMN priority VARCHAR(16) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='created_by')=0,
  'ALTER TABLE collection_tasks ADD COLUMN created_by BIGINT UNSIGNED NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='completion_result')=0,
  'ALTER TABLE collection_tasks ADD COLUMN completion_result VARCHAR(40) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='related_followup_id')=0,
  'ALTER TABLE collection_tasks ADD COLUMN related_followup_id BIGINT UNSIGNED NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND COLUMN_NAME='completion_note')=0,
  'ALTER TABLE collection_tasks ADD COLUMN completion_note VARCHAR(500) NULL', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND INDEX_NAME='idx_operator_calls_contract_created')=0,
  'ALTER TABLE operator_calls ADD KEY idx_operator_calls_contract_created (contract_id,created_at,id)', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_calls' AND INDEX_NAME='idx_operator_calls_promise')=0,
  'ALTER TABLE operator_calls ADD KEY idx_operator_calls_promise (contract_id,promise_payment_date,id)', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND INDEX_NAME='idx_collection_task_contract_due')=0,
  'ALTER TABLE collection_tasks ADD KEY idx_collection_task_contract_due (contract_id,status,due_at,id)', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;

SET @collection_workspace_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collection_tasks' AND INDEX_NAME='idx_collection_task_related_followup')=0,
  'ALTER TABLE collection_tasks ADD KEY idx_collection_task_related_followup (related_followup_id)', 'SELECT 1'
);
PREPARE collection_workspace_stmt FROM @collection_workspace_sql;
EXECUTE collection_workspace_stmt;
DEALLOCATE PREPARE collection_workspace_stmt;
