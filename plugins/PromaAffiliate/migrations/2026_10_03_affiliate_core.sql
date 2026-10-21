CREATE TABLE IF NOT EXISTS proma_affiliate_members (
  user_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(24) NOT NULL,
  reward_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  purchase_credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  lifetime_rewards DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('active','paused') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_proma_affiliate_code (referral_code),
  CONSTRAINT fk_proma_affiliate_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_affiliate_referrals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_user_id BIGINT UNSIGNED NOT NULL,
  referred_user_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(24) NOT NULL,
  status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending',
  reward_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_affiliate_referred_user (referred_user_id),
  KEY idx_proma_affiliate_referrer_date (referrer_user_id, created_at),
  CONSTRAINT fk_proma_affiliate_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id),
  CONSTRAINT fk_proma_affiliate_referred FOREIGN KEY (referred_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_affiliate_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_user_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NULL,
  transaction_type ENUM('referral_reward','withdrawal_paid','purchase_conversion','admin_adjustment') NOT NULL,
  amount_toman DECIMAL(18,2) NOT NULL,
  reward_balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
  purchase_credit_after DECIMAL(18,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_affiliate_transaction_key (idempotency_key),
  KEY idx_proma_affiliate_tx_member_date (member_user_id, created_at),
  CONSTRAINT fk_proma_affiliate_tx_member FOREIGN KEY (member_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_affiliate_withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_user_id BIGINT UNSIGNED NOT NULL,
  amount_toman DECIMAL(18,2) NOT NULL,
  payee_name VARCHAR(190) NOT NULL,
  iban VARCHAR(34) NOT NULL,
  status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  request_note VARCHAR(500) NULL,
  review_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_proma_affiliate_withdrawal_status (status, created_at),
  KEY idx_proma_affiliate_withdrawal_member (member_user_id, created_at),
  CONSTRAINT fk_proma_affiliate_withdrawal_member FOREIGN KEY (member_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_affiliate_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO proma_affiliate_settings (setting_key, setting_value) VALUES
  ('program_enabled', '1'),
  ('reward_per_customer', '100000'),
  ('reward_trigger', 'first_purchase'),
  ('milestone_enabled', '1'),
  ('milestone_count', '10'),
  ('milestone_multiplier', '2');
