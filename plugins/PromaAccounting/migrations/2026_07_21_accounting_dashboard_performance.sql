SET @ledger_created_index_columns := (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounting_ledger_entries'
      AND INDEX_NAME = 'idx_accounting_ledger_created_direction_type'
);
SET @ledger_created_index_sql := IF(
    @ledger_created_index_columns = 'created_at,direction,entry_type,amount',
    'SELECT 1',
    IF(
        @ledger_created_index_columns IS NULL,
        'ALTER TABLE accounting_ledger_entries ADD INDEX idx_accounting_ledger_created_direction_type (created_at, direction, entry_type, amount)',
        'ALTER TABLE accounting_ledger_entries DROP INDEX idx_accounting_ledger_created_direction_type, ADD INDEX idx_accounting_ledger_created_direction_type (created_at, direction, entry_type, amount)'
    )
);
PREPARE ledger_created_index_stmt FROM @ledger_created_index_sql;
EXECUTE ledger_created_index_stmt;
DEALLOCATE PREPARE ledger_created_index_stmt;

SET @commission_status_amount_index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'plugin_accounting_commissions'
      AND INDEX_NAME = 'idx_plugin_accounting_commission_status_amount'
);
SET @commission_status_amount_index_sql := IF(
    @commission_status_amount_index_exists = 0,
    'ALTER TABLE plugin_accounting_commissions ADD INDEX idx_plugin_accounting_commission_status_amount (status, calculated_amount)',
    'SELECT 1'
);
PREPARE commission_status_amount_index_stmt FROM @commission_status_amount_index_sql;
EXECUTE commission_status_amount_index_stmt;
DEALLOCATE PREPARE commission_status_amount_index_stmt;
