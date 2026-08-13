-- Proma Pay V1.5.6: auditable legal-penalty activation and comparison-only projection.
-- A legal rate is activated only by a persisted referral timestamp.  Internal
-- review records created by earlier versions are explicitly deactivated.

INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES
  ('show_projected_legal_penalty_to_customer', '1', 0),
  ('projected_legal_penalty_customer_message', 'جریمه حقوقی نمایش‌داده‌شده صرفاً برآورد مقایسه‌ای است و تا ثبت ارجاع رسمی، به مبلغ قابل پرداخت شما اضافه نمی‌شود.', 0);

-- V1.5.5 wrote this timestamp while merely opening an internal review file.
-- It was never a formal referral and must not create or preserve legal debt.
UPDATE legal_cases
SET legal_referred_at = NULL
WHERE status = 'under_legal_review'
  AND stage = 'تشکیل پرونده داخلی'
  AND legal_referred_at IS NOT NULL;

SET @add_legal_referral_lookup_index := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE legal_cases ADD KEY idx_legal_case_referral_lookup (contract_id, legal_referred_at)',
  'SELECT 1') FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legal_cases' AND INDEX_NAME = 'idx_legal_case_referral_lookup');
PREPARE stmt FROM @add_legal_referral_lookup_index; EXECUTE stmt; DEALLOCATE PREPARE stmt;
