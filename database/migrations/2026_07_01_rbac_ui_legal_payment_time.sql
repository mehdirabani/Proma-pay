-- Incremental schema support for RBAC/UI/legal/payment-log refinements.
-- The application models also add these columns defensively at runtime.

SET @installment_notes_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE installments ADD COLUMN notes TEXT NULL AFTER status',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'installments'
      AND COLUMN_NAME = 'notes'
);
PREPARE stmt FROM @installment_notes_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @legal_expense_reason_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE legal_cases ADD COLUMN expense_reason TEXT NULL AFTER expense_amount',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'legal_cases'
      AND COLUMN_NAME = 'expense_reason'
);
PREPARE stmt FROM @legal_expense_reason_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO settings (setting_key, setting_value, is_secret)
VALUES ('footer_text', 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani', 0)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
