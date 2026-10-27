-- Add the stable finding kind to installations that already ran the initial
-- Financial Reconciliation Center migration during the v7.0.4 development cycle.
SET @fin_recon_issue_type_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'financial_reconciliation_discrepancies'
     AND COLUMN_NAME = 'issue_type'
);
SET @fin_recon_issue_type_sql := IF(
  @fin_recon_issue_type_exists = 0,
  'ALTER TABLE financial_reconciliation_discrepancies ADD COLUMN issue_type VARCHAR(80) NOT NULL DEFAULT ''unknown'' AFTER rule_version',
  'SELECT 1'
);
PREPARE fin_recon_issue_type_stmt FROM @fin_recon_issue_type_sql;
EXECUTE fin_recon_issue_type_stmt;
DEALLOCATE PREPARE fin_recon_issue_type_stmt;
