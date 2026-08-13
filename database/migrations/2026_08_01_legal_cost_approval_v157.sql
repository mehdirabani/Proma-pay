CREATE TABLE IF NOT EXISTS legal_case_costs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cost_uuid VARCHAR(64) NOT NULL,
  legal_case_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  source_legal_log_id BIGINT UNSIGNED NULL,
  category VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  amount_toman BIGINT NOT NULL,
  cost_date DATE NOT NULL,
  payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  approval_status VARCHAR(30) NOT NULL DEFAULT 'pending_approval',
  paid_by BIGINT UNSIGNED NULL,
  chargeable_to_customer TINYINT(1) NOT NULL DEFAULT 1,
  description TEXT NULL,
  external_reference VARCHAR(190) NULL,
  attachment_path VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  reversed_at DATETIME NULL,
  reversal_reason TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_case_cost_uuid (cost_uuid),
  UNIQUE KEY uq_legal_case_cost_source_log (source_legal_log_id),
  KEY idx_legal_case_cost_contract_status (contract_id, approval_status, payment_status),
  KEY idx_legal_case_cost_case_date (legal_case_id, cost_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE legal_case_costs
  ADD COLUMN IF NOT EXISTS paid_amount_toman BIGINT NOT NULL DEFAULT 0 AFTER amount_toman;

CREATE TABLE IF NOT EXISTS legal_cost_payment_allocations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_group_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  legal_case_cost_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  allocated_amount_toman BIGINT NOT NULL,
  reversal_of_allocation_id BIGINT UNSIGNED NULL,
  is_reversal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_legal_cost_payment_group (payment_group_id),
  KEY idx_legal_cost_payment_cost (legal_case_cost_id, is_reversal),
  UNIQUE KEY uq_legal_cost_payment_source (payment_id, legal_case_cost_id, is_reversal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO legal_case_costs
  (cost_uuid, legal_case_id, contract_id, source_legal_log_id, category, title, amount_toman, cost_date, payment_status, approval_status, chargeable_to_customer, description, created_by, created_at, approved_at)
SELECT
  CONCAT('LEGACY-LOG-', l.id), l.legal_case_id, l.contract_id, l.id,
  COALESCE(NULLIF(l.cost_type, ''), 'سایر'), COALESCE(NULLIF(l.action_title, ''), 'هزینه حقوقی'),
  ROUND(l.cost_amount), l.action_date, 'paid', 'approved', 1, l.description, l.registered_by,
  l.created_at, l.created_at
FROM legal_case_logs l
LEFT JOIN legal_case_costs existing_cost ON existing_cost.source_legal_log_id = l.id
WHERE l.cost_amount > 0 AND existing_cost.id IS NULL;

INSERT INTO legal_case_costs
  (cost_uuid, legal_case_id, contract_id, source_legal_log_id, category, title, amount_toman, cost_date, payment_status, approval_status, chargeable_to_customer, description, created_at, approved_at)
SELECT
  CONCAT('LEGACY-CASE-', lc.id), lc.id, lc.contract_id, NULL,
  COALESCE(NULLIF(lc.expense_reason, ''), 'سایر'), 'هزینه ثبت‌شده پرونده حقوقی',
  ROUND(lc.expense_amount), COALESCE(lc.updated_at, lc.created_at, CURDATE()), 'paid', 'approved', 1, lc.notes,
  COALESCE(lc.created_at, NOW()), COALESCE(lc.created_at, NOW())
FROM legal_cases lc
LEFT JOIN legal_case_costs existing_cost ON existing_cost.cost_uuid = CONCAT('LEGACY-CASE-', lc.id)
WHERE lc.expense_amount > 0 AND existing_cost.id IS NULL;
