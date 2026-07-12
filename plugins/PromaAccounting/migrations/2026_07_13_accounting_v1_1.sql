INSERT IGNORE INTO plugin_accounting_settings (setting_key, setting_value, updated_at) VALUES
    ('minimum_commission_enabled', '0', NOW()),
    ('maximum_commission_enabled', '0', NOW()),
    ('rounding_method', 'none', NOW()),
    ('rounding_unit', '1000', NOW()),
    ('setup_status', 'pending', NOW()),
    ('setup_step', '1', NOW());

UPDATE plugin_accounting_settings target
LEFT JOIN plugin_accounting_settings legacy ON legacy.setting_key = 'rounding_rule'
SET target.setting_value = CASE
    WHEN legacy.setting_value IN ('nearest_toman', 'nearest_1000') THEN 'nearest'
    WHEN legacy.setting_value IN ('floor_1000', 'down') THEN 'down'
    WHEN legacy.setting_value IN ('ceil_1000', 'up') THEN 'up'
    ELSE 'none'
END
WHERE target.setting_key = 'rounding_method'
  AND target.updated_by IS NULL;

SET @raw_commission_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'raw_commission');
SET @raw_commission_sql := IF(@raw_commission_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN raw_commission DECIMAL(18,2) NULL AFTER commission_value', 'SELECT 1');
PREPARE raw_commission_stmt FROM @raw_commission_sql;
EXECUTE raw_commission_stmt;
DEALLOCATE PREPARE raw_commission_stmt;

SET @minimum_adjustment_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'minimum_adjustment');
SET @minimum_adjustment_sql := IF(@minimum_adjustment_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN minimum_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER raw_commission', 'SELECT 1');
PREPARE minimum_adjustment_stmt FROM @minimum_adjustment_sql;
EXECUTE minimum_adjustment_stmt;
DEALLOCATE PREPARE minimum_adjustment_stmt;

SET @maximum_adjustment_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'maximum_adjustment');
SET @maximum_adjustment_sql := IF(@maximum_adjustment_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN maximum_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER minimum_adjustment', 'SELECT 1');
PREPARE maximum_adjustment_stmt FROM @maximum_adjustment_sql;
EXECUTE maximum_adjustment_stmt;
DEALLOCATE PREPARE maximum_adjustment_stmt;

SET @rounding_adjustment_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'rounding_adjustment');
SET @rounding_adjustment_sql := IF(@rounding_adjustment_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN rounding_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER maximum_adjustment', 'SELECT 1');
PREPARE rounding_adjustment_stmt FROM @rounding_adjustment_sql;
EXECUTE rounding_adjustment_stmt;
DEALLOCATE PREPARE rounding_adjustment_stmt;

SET @rule_source_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'rule_source');
SET @rule_source_sql := IF(@rule_source_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN rule_source VARCHAR(20) NOT NULL DEFAULT ''default'' AFTER rounding_adjustment', 'SELECT 1');
PREPARE rule_source_stmt FROM @rule_source_sql;
EXECUTE rule_source_stmt;
DEALLOCATE PREPARE rule_source_stmt;

SET @snapshot_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_accounting_commissions' AND COLUMN_NAME = 'calculation_snapshot_json');
SET @snapshot_sql := IF(@snapshot_exists = 0, 'ALTER TABLE plugin_accounting_commissions ADD COLUMN calculation_snapshot_json LONGTEXT NULL AFTER rule_source', 'SELECT 1');
PREPARE snapshot_stmt FROM @snapshot_sql;
EXECUTE snapshot_stmt;
DEALLOCATE PREPARE snapshot_stmt;
