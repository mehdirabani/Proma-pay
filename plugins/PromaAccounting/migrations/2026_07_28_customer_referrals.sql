CREATE TABLE IF NOT EXISTS customer_referral_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(32) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_customer_referral_code (referral_code), UNIQUE KEY uq_customer_referral_customer (customer_id), KEY idx_customer_referral_active (customer_id,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_referrals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_customer_id BIGINT UNSIGNED NOT NULL,
  referred_customer_id BIGINT UNSIGNED NOT NULL,
  referral_code_id BIGINT UNSIGNED NOT NULL,
  attributed_at DATETIME NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_referred_customer (referred_customer_id), KEY idx_referrer_customer (referrer_customer_id), CONSTRAINT chk_customer_referral_self CHECK (referrer_customer_id <> referred_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_referral_commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referral_id BIGINT UNSIGNED NOT NULL,
  referrer_customer_id BIGINT UNSIGNED NOT NULL,
  referred_customer_id BIGINT UNSIGNED NOT NULL,
  source_event_uuid CHAR(36) NOT NULL,
  amount DECIMAL(20,2) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  holding_until DATETIME NULL,
  ledger_entry_id BIGINT UNSIGNED NULL,
  rule_snapshot_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_referral_event (source_event_uuid), KEY idx_referral_commission_status (status,holding_until), KEY idx_referral_commission_referrer (referrer_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_referral_payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_customer_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(20,2) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  idempotency_key VARCHAR(96) NOT NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_referral_payout_idempotency (idempotency_key), KEY idx_referral_payout_customer (referrer_customer_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
