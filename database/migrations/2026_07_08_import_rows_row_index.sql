-- Defensive migration for import row numbering.
-- Safe to rerun on upgraded databases.

SET @sql := (
    SELECT IF(COUNT(*) = 1,
        'ALTER TABLE import_rows CHANGE COLUMN row_number row_index INT NOT NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'import_rows'
      AND COLUMN_NAME = 'row_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE import_rows ADD COLUMN row_index INT NOT NULL AFTER batch_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'import_rows'
      AND COLUMN_NAME = 'row_index'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
