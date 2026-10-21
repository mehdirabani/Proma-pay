-- Retry-safe affiliate payout detail and withdrawal schema updates.
ALTER TABLE proma_affiliate_withdrawals
  MODIFY COLUMN iban VARCHAR(34) NULL,
  MODIFY COLUMN status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending';

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_members' AND COLUMN_NAME = 'payout_payee_name') = 0,
  'ALTER TABLE proma_affiliate_members ADD COLUMN payout_payee_name VARCHAR(190) NULL AFTER status',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_members' AND COLUMN_NAME = 'payout_card_number') = 0,
  'ALTER TABLE proma_affiliate_members ADD COLUMN payout_card_number VARCHAR(32) NULL AFTER payout_payee_name',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_members' AND COLUMN_NAME = 'payout_account_number') = 0,
  'ALTER TABLE proma_affiliate_members ADD COLUMN payout_account_number VARCHAR(40) NULL AFTER payout_card_number',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_members' AND COLUMN_NAME = 'payout_iban') = 0,
  'ALTER TABLE proma_affiliate_members ADD COLUMN payout_iban VARCHAR(34) NULL AFTER payout_account_number',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_withdrawals' AND COLUMN_NAME = 'card_number') = 0,
  'ALTER TABLE proma_affiliate_withdrawals ADD COLUMN card_number VARCHAR(32) NULL AFTER iban',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

SET @proma_affiliate_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proma_affiliate_withdrawals' AND COLUMN_NAME = 'account_number') = 0,
  'ALTER TABLE proma_affiliate_withdrawals ADD COLUMN account_number VARCHAR(40) NULL AFTER card_number',
  'SELECT 1'
);
PREPARE proma_affiliate_migration_stmt FROM @proma_affiliate_migration_ddl;
EXECUTE proma_affiliate_migration_stmt;
DEALLOCATE PREPARE proma_affiliate_migration_stmt;

INSERT IGNORE INTO proma_affiliate_settings (setting_key, setting_value)
VALUES ('minimum_withdrawal_toman', '100000');

