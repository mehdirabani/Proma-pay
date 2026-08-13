SET @has_encrypted_payload := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'proma_connect_deliveries'
    AND COLUMN_NAME = 'encrypted_payload'
);
SET @add_encrypted_payload := IF(
  @has_encrypted_payload = 0,
  'ALTER TABLE proma_connect_deliveries ADD COLUMN encrypted_payload LONGTEXT NULL AFTER payload_json',
  'SELECT 1'
);
PREPARE stmt FROM @add_encrypted_payload;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_payload_purged := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'proma_connect_deliveries'
    AND COLUMN_NAME = 'payload_purged_at'
);
SET @add_payload_purged := IF(
  @has_payload_purged = 0,
  'ALTER TABLE proma_connect_deliveries ADD COLUMN payload_purged_at DATETIME NULL AFTER encrypted_payload',
  'SELECT 1'
);
PREPARE stmt FROM @add_payload_purged;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE proma_connect_deliveries
SET payload_json = NULL, payload_purged_at = COALESCE(payload_purged_at, NOW())
WHERE payload_json IS NOT NULL;

