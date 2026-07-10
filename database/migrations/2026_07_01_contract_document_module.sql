-- Contract document/template, pledged items, guarantees, guarantor people, and printable document storage.

ALTER TABLE settings MODIFY setting_value LONGTEXT NULL;

SET @add_user_father_name := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE users ADD COLUMN father_name VARCHAR(190) NULL AFTER full_name', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'father_name'
);
PREPARE stmt FROM @add_user_father_name;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_user_issued_from := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE users ADD COLUMN issued_from VARCHAR(190) NULL AFTER father_name', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'issued_from'
);
PREPARE stmt FROM @add_user_issued_from;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_installment_guarantee_serial := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE installments ADD COLUMN guarantee_serial VARCHAR(190) NULL AFTER notes', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'guarantee_serial'
);
PREPARE stmt FROM @add_installment_guarantee_serial;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS contract_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    product_model VARCHAR(190) NOT NULL,
    imei_1 VARCHAR(80) NULL,
    imei_2 VARCHAR(80) NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_contract_items_contract (contract_id),
    CONSTRAINT fk_contract_items_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_guarantees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    guarantee_type VARCHAR(50) NOT NULL,
    guarantee_count INT NOT NULL DEFAULT 1,
    guarantee_serial VARCHAR(190) NULL,
    guarantee_description TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_contract_guarantees_contract (contract_id),
    CONSTRAINT fk_contract_guarantees_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_guarantor_people (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    father_name VARCHAR(190) NULL,
    national_id VARCHAR(20) NULL,
    mobile VARCHAR(30) NULL,
    address TEXT NULL,
    relationship VARCHAR(100) NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_contract_guarantor_people_contract (contract_id),
    CONSTRAINT fk_contract_guarantor_people_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS generated_contract_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    rendered_body LONGTEXT NOT NULL,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_generated_contract (contract_id),
    CONSTRAINT fk_generated_contract_documents_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_change_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    change_type VARCHAR(80) NOT NULL,
    old_value_json LONGTEXT NULL,
    new_value_json LONGTEXT NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_contract_change_logs_contract (contract_id),
    CONSTRAINT fk_contract_change_logs_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE
);

INSERT INTO settings (setting_key, setting_value, is_secret) VALUES
('company_name', 'موبایل پروما', 0),
('company_representative_name', '', 0),
('company_representative_national_id', '', 0),
('company_address', '', 0),
('company_postal_code', '', 0),
('company_phone', '', 0),
('contract_year', '1404', 0),
('contract_template_body', '', 0)
ON DUPLICATE KEY UPDATE setting_value = setting_value;
