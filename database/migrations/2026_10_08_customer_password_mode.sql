-- Preserve existing customer PIN behavior while allowing an explicit hashed
-- personal-password mode selected by the customer after reauthentication.
SET @customer_password_mode_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'customer_password_mode'
);
SET @customer_password_mode_sql := IF(
    @customer_password_mode_exists = 0,
    'ALTER TABLE users ADD COLUMN customer_password_mode VARCHAR(12) NULL DEFAULT NULL AFTER password_hash',
    'SELECT 1'
);
PREPARE customer_password_mode_stmt FROM @customer_password_mode_sql;
EXECUTE customer_password_mode_stmt;
DEALLOCATE PREPARE customer_password_mode_stmt;

UPDATE users
SET customer_password_mode = 'pin'
WHERE role = 'customer' AND customer_password_mode IS NULL;
