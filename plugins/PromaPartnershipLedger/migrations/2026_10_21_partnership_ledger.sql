CREATE TABLE IF NOT EXISTS plugin_inventory_financial_policy_versions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  policy_name varchar(100) NOT NULL,
  version int(10) unsigned NOT NULL,
  effective_on date NOT NULL,
  policy_json mediumtext NOT NULL,
  policy_hash char(64) NOT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_policy_name_version (policy_name, version),
  KEY idx_pip_policy_effective (policy_name, effective_on),
  CONSTRAINT fk_pip_policy_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
