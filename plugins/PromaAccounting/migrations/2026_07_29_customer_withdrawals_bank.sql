CREATE TABLE IF NOT EXISTS accounting_referral_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_bank_information (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  cardholder_name VARCHAR(160) NOT NULL,
  bank_name VARCHAR(120) NULL,
  card_number_encrypted TEXT NULL,
  card_last4 CHAR(4) NULL,
  account_number_encrypted TEXT NULL,
  account_last4 CHAR(4) NULL,
  iban_encrypted TEXT NULL,
  iban_last4 CHAR(4) NULL,
  preferred_method VARCHAR(32) NOT NULL DEFAULT 'bank_transfer',
  verification_status VARCHAR(24) NOT NULL DEFAULT 'unverified',
  verified_by BIGINT NULL,
  verified_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id), KEY idx_bank_customer_active (customer_id,is_active), KEY idx_bank_verification (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_referral_withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  withdrawal_uuid CHAR(36) NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  requested_amount_toman DECIMAL(20,2) NOT NULL,
  approved_amount_toman DECIMAL(20,2) NULL,
  reserved_amount_toman DECIMAL(20,2) NOT NULL DEFAULT 0,
  available_balance_snapshot_toman DECIMAL(20,2) NOT NULL,
  bank_information_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'requested',
  customer_note VARCHAR(1000) NULL,
  admin_note VARCHAR(1000) NULL,
  rejection_reason VARCHAR(1000) NULL,
  payment_method VARCHAR(32) NULL,
  payment_reference VARCHAR(160) NULL,
  request_id VARCHAR(96) NULL,
  requested_at DATETIME NOT NULL,
  reviewed_by BIGINT NULL,
  reviewed_at DATETIME NULL,
  paid_by BIGINT NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_withdrawal_uuid (withdrawal_uuid), UNIQUE KEY uq_withdrawal_request (request_id), KEY idx_withdrawal_customer_status (customer_id,status), KEY idx_withdrawal_status_date (status,requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
