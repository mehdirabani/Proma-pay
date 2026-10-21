CREATE TABLE IF NOT EXISTS plugin_inventory_brands (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_brand_name (name),
  KEY idx_pip_brand_status_name (status, name),
  CONSTRAINT fk_pip_brand_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_categories (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_category_name (name),
  KEY idx_pip_category_status_name (status, name),
  CONSTRAINT fk_pip_category_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_expenses (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  expense_no varchar(50) NOT NULL,
  expense_type varchar(40) NOT NULL,
  expense_date date NOT NULL,
  amount_rial decimal(20,0) unsigned NOT NULL,
  payment_method varchar(40) NOT NULL,
  payment_reference varchar(120) DEFAULT NULL,
  note varchar(500) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'posted',
  request_hash char(64) NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  journal_id bigint(20) unsigned DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_expense_no (expense_no),
  UNIQUE KEY uq_pip_expense_idempotency (idempotency_key),
  KEY idx_pip_expense_status_date (status, expense_date, id),
  KEY idx_pip_expense_type_date (expense_type, expense_date),
  CONSTRAINT fk_pip_expense_journal FOREIGN KEY (journal_id) REFERENCES plugin_inventory_journals (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_expense_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_expense_partner_allocations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  expense_id bigint(20) unsigned NOT NULL,
  partner_id bigint(20) unsigned NOT NULL,
  share_bp smallint(5) unsigned NOT NULL,
  amount_rial decimal(20,0) unsigned NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_expense_partner (expense_id, partner_id),
  KEY idx_pip_expense_allocation_partner (partner_id, expense_id),
  CONSTRAINT fk_pip_expense_allocation_expense FOREIGN KEY (expense_id) REFERENCES plugin_inventory_expenses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_expense_allocation_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'product_type') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN product_type varchar(20) NOT NULL DEFAULT ''other'' AFTER name',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'request_hash') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN request_hash char(64) DEFAULT NULL AFTER minimum_stock',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'idempotency_key') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN idempotency_key varchar(120) DEFAULT NULL AFTER request_hash',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'brand_id') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN brand_id bigint(20) unsigned DEFAULT NULL AFTER brand',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'category_id') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN category_id bigint(20) unsigned DEFAULT NULL AFTER category',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND COLUMN_NAME = 'ram') = 0,
  'ALTER TABLE plugin_inventory_products ADD COLUMN ram varchar(40) DEFAULT NULL AFTER capacity',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

INSERT IGNORE INTO plugin_inventory_brands (name, status, created_at)
SELECT DISTINCT TRIM(brand), 'active', NOW() FROM plugin_inventory_products
WHERE brand IS NOT NULL AND TRIM(brand) <> '';
INSERT IGNORE INTO plugin_inventory_categories (name, status, created_at)
SELECT DISTINCT TRIM(category), 'active', NOW() FROM plugin_inventory_products
WHERE category IS NOT NULL AND TRIM(category) <> '';

UPDATE plugin_inventory_products p
JOIN plugin_inventory_brands b ON b.name = TRIM(p.brand)
SET p.brand_id = b.id
WHERE p.brand_id IS NULL AND p.brand IS NOT NULL AND TRIM(p.brand) <> '';
UPDATE plugin_inventory_products p
JOIN plugin_inventory_categories c ON c.name = TRIM(p.category)
SET p.category_id = c.id
WHERE p.category_id IS NULL AND p.category IS NOT NULL AND TRIM(p.category) <> '';

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'idx_pip_product_brand_status') = 0,
  'ALTER TABLE plugin_inventory_products ADD KEY idx_pip_product_brand_status (brand_id, status)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'idx_pip_product_category_status') = 0,
  'ALTER TABLE plugin_inventory_products ADD KEY idx_pip_product_category_status (category_id, status)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'idx_pip_product_type_status') = 0,
  'ALTER TABLE plugin_inventory_products ADD KEY idx_pip_product_type_status (product_type, status)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'idx_pip_product_model') = 0,
  'ALTER TABLE plugin_inventory_products ADD KEY idx_pip_product_model (model)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'uq_pip_product_idempotency') = 0,
  'ALTER TABLE plugin_inventory_products ADD UNIQUE KEY uq_pip_product_idempotency (idempotency_key)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND INDEX_NAME = 'idx_pip_product_status_name') = 0,
  'ALTER TABLE plugin_inventory_products ADD KEY idx_pip_product_status_name (status, name, id)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_sales' AND INDEX_NAME = 'idx_pip_sale_status_sold') = 0,
  'ALTER TABLE plugin_inventory_sales ADD KEY idx_pip_sale_status_sold (status, sold_at, id)',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND CONSTRAINT_NAME = 'fk_pip_product_brand') = 0,
  'ALTER TABLE plugin_inventory_products ADD CONSTRAINT fk_pip_product_brand FOREIGN KEY (brand_id) REFERENCES plugin_inventory_brands (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

SET @pip_migration_ddl := IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'plugin_inventory_products' AND CONSTRAINT_NAME = 'fk_pip_product_category') = 0,
  'ALTER TABLE plugin_inventory_products ADD CONSTRAINT fk_pip_product_category FOREIGN KEY (category_id) REFERENCES plugin_inventory_categories (id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE pip_migration_stmt FROM @pip_migration_ddl;
EXECUTE pip_migration_stmt;
DEALLOCATE PREPARE pip_migration_stmt;

INSERT IGNORE INTO plugin_inventory_settings (setting_key, setting_value, updated_at) VALUES
  ('sku_pattern', '{type}_{brand}_{model}_{color}_{storage}_{ram}', NOW()),
  ('fiscal_start_month', '1', NOW()),
  ('fiscal_start_day', '1', NOW());
