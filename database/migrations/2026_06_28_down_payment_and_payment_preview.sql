ALTER TABLE contracts
  ADD COLUMN down_payment_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER principal_amount;

ALTER TABLE installments
  ADD COLUMN remaining_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER paid_amount,
  ADD COLUMN last_payment_date DATE NULL AFTER remaining_amount;

UPDATE installments
SET remaining_amount = GREATEST(base_amount - paid_amount, 0)
WHERE remaining_amount = 0 AND paid_amount < base_amount;

ALTER TABLE payments
  DROP FOREIGN KEY fk_payment_installment;

ALTER TABLE payments
  MODIFY installment_id BIGINT UNSIGNED NULL,
  ADD COLUMN payment_date DATE NULL AFTER description,
  ADD COLUMN calculated_penalty DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER payment_date,
  ADD COLUMN calculated_reward DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER calculated_penalty,
  ADD COLUMN remaining_before_payment DECIMAL(18,2) NULL AFTER calculated_reward,
  ADD COLUMN remaining_after_payment DECIMAL(18,2) NULL AFTER remaining_before_payment,
  ADD COLUMN payment_type VARCHAR(40) NOT NULL DEFAULT 'installment' AFTER remaining_after_payment;

UPDATE payments
SET payment_date = DATE(COALESCE(paid_at, created_at)),
    payment_type = 'installment'
WHERE payment_date IS NULL;

ALTER TABLE payments
  ADD CONSTRAINT fk_payment_installment FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE SET NULL;
