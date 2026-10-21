CREATE TABLE IF NOT EXISTS legal_cost_disbursements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  disbursement_uuid VARCHAR(64) NOT NULL,
  legal_case_cost_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  amount_toman BIGINT NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  payment_reference VARCHAR(190) NULL,
  paid_at DATE NOT NULL,
  note TEXT NULL,
  idempotency_key VARCHAR(64) NOT NULL,
  is_reversal TINYINT(1) NOT NULL DEFAULT 0,
  reversal_of_id BIGINT UNSIGNED NULL,
  reversal_reason TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_cost_disbursement_uuid (disbursement_uuid),
  UNIQUE KEY uq_legal_cost_disbursement_request (idempotency_key),
  UNIQUE KEY uq_legal_cost_disbursement_reversal (reversal_of_id),
  KEY idx_legal_cost_disbursement_cost (legal_case_cost_id, id),
  KEY idx_legal_cost_disbursement_contract (contract_id, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- In the legacy expense records, `payment_status = paid` described the
-- company's payment of the expense. Customer receipts are tracked separately
-- by legal_cost_payment_allocations and paid_amount_toman.
INSERT IGNORE INTO legal_cost_disbursements
  (disbursement_uuid, legal_case_cost_id, contract_id, amount_toman, recipient,
   payment_reference, paid_at, note, idempotency_key, created_by, created_at)
SELECT CONCAT('LCP-LEGACY-', c.id), c.id, c.contract_id, c.amount_toman,
       'پرداخت ثبت‌شده در اطلاعات قدیمی', c.external_reference, c.cost_date,
       'وضعیت قدیمی paid به‌عنوان پرداخت هزینه توسط مجموعه نگهداری شد؛ این رویداد بدهی مشتری را تسویه نمی‌کند.',
       CONCAT('legacy-cost-', c.id), c.created_by, COALESCE(c.created_at, NOW())
FROM legal_case_costs c
WHERE c.cost_uuid LIKE 'LEGACY-%'
  AND c.payment_status = 'paid'
  AND COALESCE(c.paid_amount_toman, 0) = 0
  AND NOT EXISTS (
    SELECT 1 FROM legal_cost_payment_allocations a
    WHERE a.legal_case_cost_id = c.id AND COALESCE(a.is_reversal, 0) = 0
  );

UPDATE legal_case_costs
SET payment_status = CASE
  WHEN COALESCE(paid_amount_toman, 0) >= amount_toman THEN 'paid'
  WHEN COALESCE(paid_amount_toman, 0) > 0 THEN 'partial'
  ELSE 'pending'
END
WHERE legal_case_costs.cost_uuid LIKE 'LEGACY-%'
  AND EXISTS (
    SELECT 1 FROM legal_cost_disbursements d
    WHERE d.legal_case_cost_id = legal_case_costs.id AND d.idempotency_key = CONCAT('legacy-cost-', legal_case_costs.id)
  );
