CREATE TABLE IF NOT EXISTS proma_affiliate_sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  member_user_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(24) NOT NULL,
  status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending',
  reward_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  payment_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  qualified_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_affiliate_sale_contract (contract_id),
  KEY idx_proma_affiliate_sale_member (member_user_id,status,created_at),
  KEY idx_proma_affiliate_sale_customer (customer_id,created_at),
  CONSTRAINT fk_proma_affiliate_sale_member FOREIGN KEY (member_user_id) REFERENCES users(id),
  CONSTRAINT fk_proma_affiliate_sale_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
