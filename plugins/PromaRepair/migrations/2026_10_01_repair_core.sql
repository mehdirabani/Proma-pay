CREATE TABLE IF NOT EXISTS proma_repairs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_code VARCHAR(40) NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'received',
  customer_report TEXT NOT NULL,
  estimated_delivery_at DATETIME NULL,
  delivered_at DATETIME NULL,
  parent_repair_id BIGINT UNSIGNED NULL,
  is_warranty_return TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_repair_tracking_code (tracking_code),
  KEY idx_proma_repair_customer (customer_id, id),
  KEY idx_proma_repair_status (status, created_at),
  KEY idx_proma_repair_created (created_at),
  KEY idx_proma_repair_parent (parent_repair_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_devices (
  repair_id BIGINT UNSIGNED NOT NULL,
  device_type VARCHAR(40) NOT NULL DEFAULT 'mobile',
  brand VARCHAR(100) NOT NULL DEFAULT '',
  model VARCHAR(150) NOT NULL DEFAULT '',
  color VARCHAR(80) NULL,
  imei VARCHAR(40) NULL,
  serial_number VARCHAR(100) NULL,
  storage_capacity VARCHAR(40) NULL,
  ram_capacity VARCHAR(40) NULL,
  carrier VARCHAR(80) NULL,
  sim_number VARCHAR(40) NULL,
  accessories TEXT NULL,
  condition_flags TEXT NULL,
  condition_notes TEXT NULL,
  password_type VARCHAR(20) NULL,
  password_ciphertext LONGTEXT NULL,
  password_delete_after_days INT NOT NULL DEFAULT 0,
  password_delete_at DATETIME NULL,
  password_deleted_at DATETIME NULL,
  PRIMARY KEY (repair_id),
  KEY idx_proma_repair_device_imei (imei),
  KEY idx_proma_repair_device_serial (serial_number),
  KEY idx_proma_repair_device_model (brand, model),
  KEY idx_proma_repair_device_type (device_type),
  CONSTRAINT fk_proma_repair_device_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_technicians (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(150) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_repair_technician_user (user_id),
  KEY idx_proma_repair_technician_active (active, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  technician_user_id BIGINT UNSIGNED NOT NULL,
  assignment_role VARCHAR(20) NOT NULL DEFAULT 'primary',
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL,
  unassigned_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_assignment_repair (repair_id, unassigned_at),
  KEY idx_proma_repair_assignment_technician (technician_user_id, unassigned_at),
  CONSTRAINT fk_proma_repair_assignment_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_diagnoses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  technician_user_id BIGINT UNSIGNED NULL,
  diagnosis TEXT NOT NULL,
  proposed_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  estimated_hours DECIMAL(8,2) NULL,
  customer_decision VARCHAR(20) NULL,
  decision_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_diagnosis (repair_id, id),
  CONSTRAINT fk_proma_repair_diagnosis_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_charges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  charge_type VARCHAR(30) NOT NULL DEFAULT 'repair_service',
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_charge_order (repair_id, voided_at),
  CONSTRAINT fk_proma_repair_charge_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_expenses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  expense_type VARCHAR(30) NOT NULL DEFAULT 'miscellaneous',
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  technician_user_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_expense_order (repair_id, voided_at),
  KEY idx_proma_repair_expense_technician (technician_user_id),
  CONSTRAINT fk_proma_repair_expense_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  payment_type VARCHAR(20) NOT NULL DEFAULT 'deposit',
  amount DECIMAL(18,2) NOT NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
  reference VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  paid_at DATETIME NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_payment_order (repair_id, voided_at, paid_at),
  CONSTRAINT fk_proma_repair_payment_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  attachment_type VARCHAR(30) NOT NULL DEFAULT 'damage_detail',
  stored_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  uploader_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_attachment_order (repair_id, created_at),
  CONSTRAINT fk_proma_repair_attachment_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_history_order (repair_id, id),
  KEY idx_proma_repair_history_status (new_status, created_at),
  CONSTRAINT fk_proma_repair_history_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  note TEXT NOT NULL,
  visible_to_customer TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_note_order (repair_id, visible_to_customer, id),
  CONSTRAINT fk_proma_repair_note_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_warranties (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  warranty_days INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATE NULL,
  ends_at DATE NULL,
  conditions TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_repair_warranty_order (repair_id),
  KEY idx_proma_repair_warranty_end (enabled, ends_at),
  CONSTRAINT fk_proma_repair_warranty_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  repair_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  ip_address VARCHAR(45) NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_audit_order (repair_id, id),
  KEY idx_proma_repair_audit_actor (actor_user_id, created_at),
  CONSTRAINT fk_proma_repair_audit_order FOREIGN KEY (repair_id) REFERENCES proma_repairs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_setting_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  setting_value LONGTEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proma_repair_setting_version (setting_key, version_number),
  KEY idx_proma_repair_setting_history (setting_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_repair_tracking_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_proma_repair_tracking_bucket (bucket_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
