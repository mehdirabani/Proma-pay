CREATE TABLE IF NOT EXISTS plugin_inventory_settings (
  setting_key varchar(100) NOT NULL,
  setting_value text DEFAULT NULL,
  updated_by bigint(20) unsigned DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_pip_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_warehouses (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(40) NOT NULL,
  name varchar(190) NOT NULL,
  location varchar(255) DEFAULT NULL,
  manager_user_id bigint(20) unsigned DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_warehouse_code (code),
  KEY idx_pip_warehouse_status (status),
  CONSTRAINT fk_pip_warehouse_manager FOREIGN KEY (manager_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pip_warehouse_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_suppliers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  phone varchar(40) DEFAULT NULL,
  identity_no varchar(80) DEFAULT NULL,
  notes text DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_supplier_name (name),
  KEY idx_pip_supplier_phone (phone),
  CONSTRAINT fk_pip_supplier_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_partners (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_code varchar(40) NOT NULL,
  full_name varchar(190) NOT NULL,
  phone varchar(40) DEFAULT NULL,
  identity_no varchar(80) DEFAULT NULL,
  started_on date DEFAULT NULL,
  default_capital_share_bp smallint(5) unsigned NOT NULL DEFAULT 0,
  default_profit_share_bp smallint(5) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  notes text DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_partner_code (partner_code),
  UNIQUE KEY uq_pip_partner_identity (identity_no),
  KEY idx_pip_partner_status_name (status, full_name),
  KEY idx_pip_partner_phone (phone),
  CONSTRAINT fk_pip_partner_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_products (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sku varchar(80) NOT NULL,
  barcode varchar(100) DEFAULT NULL,
  name varchar(190) NOT NULL,
  category varchar(120) DEFAULT NULL,
  brand varchar(120) DEFAULT NULL,
  model varchar(120) DEFAULT NULL,
  technical_specs text DEFAULT NULL,
  color varchar(80) DEFAULT NULL,
  capacity varchar(80) DEFAULT NULL,
  tracking_mode varchar(20) NOT NULL DEFAULT 'quantity',
  status varchar(20) NOT NULL DEFAULT 'active',
  image_path varchar(255) DEFAULT NULL,
  description text DEFAULT NULL,
  reference_purchase_price_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  market_price_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  sale_price_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  minimum_sale_price_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  minimum_stock int(10) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_product_sku (sku),
  UNIQUE KEY uq_pip_product_barcode (barcode),
  KEY idx_pip_product_name (name),
  KEY idx_pip_product_status (status, category),
  CONSTRAINT fk_pip_product_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_purchase_headers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  purchase_no varchar(50) NOT NULL,
  supplier_id bigint(20) unsigned DEFAULT NULL,
  warehouse_id bigint(20) unsigned NOT NULL,
  invoice_no varchar(100) DEFAULT NULL,
  purchase_date date NOT NULL,
  expenses_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  total_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  request_hash char(64) NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'posted',
  note text DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_purchase_no (purchase_no),
  UNIQUE KEY uq_pip_purchase_idempotency (idempotency_key),
  KEY idx_pip_purchase_date (purchase_date, id),
  KEY idx_pip_purchase_supplier (supplier_id, purchase_date),
  CONSTRAINT fk_pip_purchase_supplier FOREIGN KEY (supplier_id) REFERENCES plugin_inventory_suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_purchase_warehouse FOREIGN KEY (warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_purchase_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_stock_lots (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lot_no varchar(60) NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  purchase_id bigint(20) unsigned DEFAULT NULL,
  supplier_id bigint(20) unsigned DEFAULT NULL,
  warehouse_id bigint(20) unsigned NOT NULL,
  entered_at datetime NOT NULL,
  initial_qty int(10) unsigned NOT NULL,
  on_hand_qty int(10) unsigned NOT NULL,
  reserved_qty int(10) unsigned NOT NULL DEFAULT 0,
  unit_purchase_cost_rial decimal(20,0) unsigned NOT NULL,
  unit_expense_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  unit_landed_cost_rial decimal(20,0) unsigned NOT NULL,
  cost_remainder_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  note text DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_lot_no (lot_no),
  KEY idx_pip_lot_product_warehouse (product_id, warehouse_id, status),
  KEY idx_pip_lot_purchase (purchase_id),
  CONSTRAINT fk_pip_lot_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_lot_purchase FOREIGN KEY (purchase_id) REFERENCES plugin_inventory_purchase_headers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_lot_supplier FOREIGN KEY (supplier_id) REFERENCES plugin_inventory_suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_lot_warehouse FOREIGN KEY (warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_lot_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_purchase_lines (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  purchase_id bigint(20) unsigned NOT NULL,
  lot_id bigint(20) unsigned NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  quantity int(10) unsigned NOT NULL,
  unit_purchase_cost_rial decimal(20,0) unsigned NOT NULL,
  allocated_expense_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  line_total_rial decimal(20,0) unsigned NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_purchase_line_lot (lot_id),
  KEY idx_pip_purchase_line_header (purchase_id),
  CONSTRAINT fk_pip_purchase_line_header FOREIGN KEY (purchase_id) REFERENCES plugin_inventory_purchase_headers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_purchase_line_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_purchase_line_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_lot_partner_shares (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lot_id bigint(20) unsigned NOT NULL,
  partner_id bigint(20) unsigned NOT NULL,
  capital_share_bp smallint(5) unsigned NOT NULL,
  profit_share_bp smallint(5) unsigned NOT NULL,
  initial_capital_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  remaining_capital_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_lot_partner (lot_id, partner_id),
  KEY idx_pip_lot_share_partner (partner_id, lot_id),
  CONSTRAINT fk_pip_lot_share_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_lot_share_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_serial_items (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  lot_id bigint(20) unsigned NOT NULL,
  warehouse_id bigint(20) unsigned NOT NULL,
  serial_no varchar(120) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'in_stock',
  sale_id bigint(20) unsigned DEFAULT NULL,
  received_at datetime NOT NULL,
  sold_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_serial_no (serial_no),
  KEY idx_pip_serial_product_state (product_id, status, warehouse_id),
  CONSTRAINT fk_pip_serial_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_serial_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_serial_warehouse FOREIGN KEY (warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_stock_movements (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  movement_uuid varchar(64) NOT NULL,
  movement_type varchar(30) NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  lot_id bigint(20) unsigned NOT NULL,
  from_warehouse_id bigint(20) unsigned DEFAULT NULL,
  to_warehouse_id bigint(20) unsigned DEFAULT NULL,
  quantity int(10) unsigned NOT NULL,
  unit_cost_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  reference_type varchar(40) DEFAULT NULL,
  reference_id bigint(20) unsigned DEFAULT NULL,
  reason varchar(255) DEFAULT NULL,
  actor_user_id bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_movement_uuid (movement_uuid),
  KEY idx_pip_movement_product_time (product_id, created_at, id),
  KEY idx_pip_movement_reference (reference_type, reference_id),
  CONSTRAINT fk_pip_movement_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_movement_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_movement_from_wh FOREIGN KEY (from_warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_movement_to_wh FOREIGN KEY (to_warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_movement_user FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_stock_adjustments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  adjustment_no varchar(50) NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  request_hash char(64) NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  lot_id bigint(20) unsigned DEFAULT NULL,
  warehouse_id bigint(20) unsigned NOT NULL,
  direction varchar(10) NOT NULL,
  quantity int(10) unsigned NOT NULL,
  unit_cost_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  total_cost_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  reason varchar(255) NOT NULL,
  journal_id bigint(20) unsigned DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'posted',
  actor_user_id bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_adjustment_no (adjustment_no),
  UNIQUE KEY uq_pip_adjustment_idempotency (idempotency_key),
  KEY idx_pip_adjustment_time (created_at, direction),
  CONSTRAINT fk_pip_adjustment_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_adjustment_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_adjustment_warehouse FOREIGN KEY (warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_adjustment_user FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_sales (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sale_no varchar(50) NOT NULL,
  sale_kind varchar(20) NOT NULL,
  contract_id bigint(20) unsigned DEFAULT NULL,
  customer_name varchar(190) DEFAULT NULL,
  customer_phone varchar(40) DEFAULT NULL,
  payment_method varchar(40) DEFAULT NULL,
  payment_reference varchar(120) DEFAULT NULL,
  sold_at datetime NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'posted',
  total_sale_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  total_cost_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  selling_expenses_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  net_profit_rial decimal(20,0) NOT NULL DEFAULT 0,
  collected_principal_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  request_hash char(64) NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  note text DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_sale_no (sale_no),
  UNIQUE KEY uq_pip_sale_idempotency (idempotency_key),
  UNIQUE KEY uq_pip_sale_contract (contract_id),
  KEY idx_pip_sale_date_status (sold_at, status),
  CONSTRAINT fk_pip_sale_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_sale_lines (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sale_id bigint(20) unsigned NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  lot_id bigint(20) unsigned NOT NULL,
  warehouse_id bigint(20) unsigned NOT NULL,
  quantity int(10) unsigned NOT NULL,
  unit_sale_price_rial decimal(20,0) unsigned NOT NULL,
  unit_landed_cost_rial decimal(20,0) unsigned NOT NULL,
  allocated_selling_expense_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  line_revenue_rial decimal(20,0) unsigned NOT NULL,
  line_cost_rial decimal(20,0) unsigned NOT NULL,
  line_net_profit_rial decimal(20,0) NOT NULL,
  returned_qty int(10) unsigned NOT NULL DEFAULT 0,
  returned_cost_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  cost_remainder_applied_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pip_sale_line_header (sale_id),
  KEY idx_pip_sale_line_lot (lot_id),
  CONSTRAINT fk_pip_sale_line_sale FOREIGN KEY (sale_id) REFERENCES plugin_inventory_sales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_sale_line_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_sale_line_lot FOREIGN KEY (lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_sale_line_warehouse FOREIGN KEY (warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_sale_partner_shares (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sale_line_id bigint(20) unsigned NOT NULL,
  partner_id bigint(20) unsigned NOT NULL,
  capital_share_bp smallint(5) unsigned NOT NULL,
  profit_share_bp smallint(5) unsigned NOT NULL,
  capital_amount_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  profit_amount_rial decimal(20,0) NOT NULL DEFAULT 0,
  capital_returned_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  profit_released_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_sale_line_partner (sale_line_id, partner_id),
  KEY idx_pip_sale_share_partner (partner_id, sale_line_id),
  CONSTRAINT fk_pip_sale_share_line FOREIGN KEY (sale_line_id) REFERENCES plugin_inventory_sale_lines (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_sale_share_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_sale_serial_items (
  sale_line_id bigint(20) unsigned NOT NULL,
  serial_item_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY (sale_line_id, serial_item_id),
  UNIQUE KEY uq_pip_sale_serial_item (serial_item_id),
  CONSTRAINT fk_pip_sale_serial_line FOREIGN KEY (sale_line_id) REFERENCES plugin_inventory_sale_lines (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_sale_serial_item FOREIGN KEY (serial_item_id) REFERENCES plugin_inventory_serial_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_payment_allocations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sale_id bigint(20) unsigned NOT NULL,
  source_payment_id bigint(20) unsigned NOT NULL,
  principal_rial decimal(20,0) unsigned NOT NULL,
  applied_capital_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  applied_profit_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  allocation_snapshot_json longtext DEFAULT NULL,
  state varchar(20) NOT NULL DEFAULT 'active',
  reversal_of_id bigint(20) unsigned DEFAULT NULL,
  idempotency_key varchar(120) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_source_payment (source_payment_id),
  UNIQUE KEY uq_pip_payment_idempotency (idempotency_key),
  KEY idx_pip_payment_sale_state (sale_id, state),
  CONSTRAINT fk_pip_payment_sale FOREIGN KEY (sale_id) REFERENCES plugin_inventory_sales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_payment_reversal FOREIGN KEY (reversal_of_id) REFERENCES plugin_inventory_payment_allocations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_returns (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  return_no varchar(50) NOT NULL,
  sale_id bigint(20) unsigned NOT NULL,
  reason varchar(255) NOT NULL,
  refund_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  refund_reference varchar(120) DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_return_no (return_no),
  KEY idx_pip_return_sale (sale_id, created_at),
  CONSTRAINT fk_pip_return_sale FOREIGN KEY (sale_id) REFERENCES plugin_inventory_sales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_return_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_return_lines (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  return_id bigint(20) unsigned NOT NULL,
  sale_line_id bigint(20) unsigned NOT NULL,
  quantity int(10) unsigned NOT NULL,
  revenue_rial decimal(20,0) unsigned NOT NULL,
  cost_rial decimal(20,0) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_return_line_header (return_id),
  CONSTRAINT fk_pip_return_line_header FOREIGN KEY (return_id) REFERENCES plugin_inventory_returns (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_return_line_sale_line FOREIGN KEY (sale_line_id) REFERENCES plugin_inventory_sale_lines (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_journals (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_key varchar(150) NOT NULL,
  source_type varchar(50) NOT NULL,
  source_id bigint(20) unsigned DEFAULT NULL,
  description varchar(255) NOT NULL,
  request_hash char(64) NOT NULL,
  reversal_of_id bigint(20) unsigned DEFAULT NULL,
  actor_user_id bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_journal_event (event_key),
  UNIQUE KEY uq_pip_journal_reversal (reversal_of_id),
  KEY idx_pip_journal_source (source_type, source_id),
  KEY idx_pip_journal_time (created_at, id),
  CONSTRAINT fk_pip_journal_reversal FOREIGN KEY (reversal_of_id) REFERENCES plugin_inventory_journals (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_journal_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_journal_lines (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  journal_id bigint(20) unsigned NOT NULL,
  account_code varchar(60) NOT NULL,
  partner_id bigint(20) unsigned DEFAULT NULL,
  debit_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  credit_rial decimal(20,0) unsigned NOT NULL DEFAULT 0,
  memo varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_journal_line_account_partner (account_code, partner_id, journal_id),
  CONSTRAINT fk_pip_journal_line_header FOREIGN KEY (journal_id) REFERENCES plugin_inventory_journals (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_journal_line_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_capital_contributions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  partner_id bigint(20) unsigned NOT NULL,
  amount_rial decimal(20,0) unsigned NOT NULL,
  received_at datetime NOT NULL,
  method varchar(40) NOT NULL,
  reference_no varchar(120) DEFAULT NULL,
  journal_id bigint(20) unsigned NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  note varchar(255) DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_capital_idempotency (idempotency_key),
  KEY idx_pip_capital_partner_date (partner_id, received_at),
  CONSTRAINT fk_pip_capital_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_capital_journal FOREIGN KEY (journal_id) REFERENCES plugin_inventory_journals (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_capital_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_settlement_requests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_no varchar(50) NOT NULL,
  partner_id bigint(20) unsigned NOT NULL,
  amount_rial decimal(20,0) unsigned NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'pending_approval',
  payout_method varchar(40) DEFAULT NULL,
  payment_reference varchar(120) DEFAULT NULL,
  reason varchar(255) DEFAULT NULL,
  requested_by bigint(20) unsigned DEFAULT NULL,
  approved_by bigint(20) unsigned DEFAULT NULL,
  paid_by bigint(20) unsigned DEFAULT NULL,
  approved_at datetime DEFAULT NULL,
  paid_at datetime DEFAULT NULL,
  journal_id bigint(20) unsigned DEFAULT NULL,
  idempotency_key varchar(120) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_settlement_no (request_no),
  UNIQUE KEY uq_pip_settlement_idempotency (idempotency_key),
  KEY idx_pip_settlement_partner_state (partner_id, status, created_at),
  CONSTRAINT fk_pip_settlement_partner FOREIGN KEY (partner_id) REFERENCES plugin_inventory_partners (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_settlement_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pip_settlement_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pip_settlement_payer FOREIGN KEY (paid_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pip_settlement_journal FOREIGN KEY (journal_id) REFERENCES plugin_inventory_journals (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_transfers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  transfer_no varchar(50) NOT NULL,
  from_warehouse_id bigint(20) unsigned NOT NULL,
  to_warehouse_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  requested_by bigint(20) unsigned DEFAULT NULL,
  received_by bigint(20) unsigned DEFAULT NULL,
  requested_at datetime NOT NULL,
  received_at datetime DEFAULT NULL,
  note varchar(255) DEFAULT NULL,
  request_hash char(64) NOT NULL,
  idempotency_key varchar(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_transfer_no (transfer_no),
  UNIQUE KEY uq_pip_transfer_idempotency (idempotency_key),
  KEY idx_pip_transfer_state_time (status, requested_at),
  CONSTRAINT fk_pip_transfer_from FOREIGN KEY (from_warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_transfer_to FOREIGN KEY (to_warehouse_id) REFERENCES plugin_inventory_warehouses (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_transfer_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pip_transfer_receiver FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_transfer_lines (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  transfer_id bigint(20) unsigned NOT NULL,
  source_lot_id bigint(20) unsigned NOT NULL,
  destination_lot_id bigint(20) unsigned DEFAULT NULL,
  quantity int(10) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_transfer_line_header (transfer_id),
  CONSTRAINT fk_pip_transfer_line_header FOREIGN KEY (transfer_id) REFERENCES plugin_inventory_transfers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_transfer_line_source FOREIGN KEY (source_lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_transfer_line_destination FOREIGN KEY (destination_lot_id) REFERENCES plugin_inventory_stock_lots (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_transfer_serial_items (
  transfer_line_id bigint(20) unsigned NOT NULL,
  serial_item_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY (transfer_line_id, serial_item_id),
  UNIQUE KEY uq_pip_transfer_serial_item (serial_item_id),
  CONSTRAINT fk_pip_transfer_serial_line FOREIGN KEY (transfer_line_id) REFERENCES plugin_inventory_transfer_lines (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_transfer_serial_item FOREIGN KEY (serial_item_id) REFERENCES plugin_inventory_serial_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_price_history (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  price_type varchar(30) NOT NULL,
  before_rial decimal(20,0) unsigned NOT NULL,
  after_rial decimal(20,0) unsigned NOT NULL,
  reason varchar(255) NOT NULL,
  changed_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pip_price_history_product_time (product_id, created_at, id),
  CONSTRAINT fk_pip_price_history_product FOREIGN KEY (product_id) REFERENCES plugin_inventory_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pip_price_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_inventory_audit_logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_uuid varchar(64) NOT NULL,
  action varchar(80) NOT NULL,
  object_type varchar(60) NOT NULL,
  object_id bigint(20) unsigned DEFAULT NULL,
  actor_user_id bigint(20) unsigned DEFAULT NULL,
  before_json longtext DEFAULT NULL,
  after_json longtext DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pip_audit_uuid (event_uuid),
  KEY idx_pip_audit_object_time (object_type, object_id, created_at),
  KEY idx_pip_audit_actor_time (actor_user_id, created_at),
  CONSTRAINT fk_pip_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
