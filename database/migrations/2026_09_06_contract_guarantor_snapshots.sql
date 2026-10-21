CREATE TABLE IF NOT EXISTS contract_guarantor_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  guarantor_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  father_name VARCHAR(190) NULL,
  national_id VARCHAR(20) NULL,
  mobile VARCHAR(30) NULL,
  address TEXT NULL,
  relationship VARCHAR(100) NULL,
  description TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contract_guarantor_snapshot (contract_id, guarantor_id),
  KEY idx_contract_guarantor_snapshot_contract (contract_id),
  KEY idx_contract_guarantor_snapshot_guarantor (guarantor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contract_guarantor_snapshots
  (contract_id, guarantor_id, full_name, father_name, national_id, mobile, address, relationship, created_at)
SELECT cg.contract_id, u.id, u.full_name, u.father_name, u.national_id, u.mobile, u.address, 'ضامن قرارداد', NOW()
FROM contract_guarantors cg
JOIN users u ON u.id = cg.guarantor_id;
