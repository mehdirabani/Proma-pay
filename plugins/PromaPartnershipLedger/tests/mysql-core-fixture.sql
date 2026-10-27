-- Run only against a new isolated test database before the plugin migration.
CREATE TABLE IF NOT EXISTS users (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  full_name varchar(190) NOT NULL,
  mobile varchar(20) DEFAULT NULL,
  status varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  customer_id bigint(20) unsigned NOT NULL,
  principal_amount decimal(18,2) NOT NULL,
  status varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  KEY idx_pip_test_contract_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  contract_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  contract_id bigint(20) unsigned NOT NULL,
  installment_id bigint(20) unsigned DEFAULT NULL,
  amount decimal(18,2) NOT NULL,
  status varchar(30) NOT NULL,
  is_corrected tinyint(1) NOT NULL DEFAULT 0,
  payment_type varchar(40) NOT NULL DEFAULT 'installment',
  principal_applied decimal(18,2) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_test_payment_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
