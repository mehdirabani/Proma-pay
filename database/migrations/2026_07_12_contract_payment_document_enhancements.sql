CREATE TABLE IF NOT EXISTS contract_deletion_archives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(80) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    deletion_reason TEXT NOT NULL,
    gateway_warning TEXT NULL,
    corrected_payment_count INT UNSIGNED NOT NULL DEFAULT 0,
    snapshot_json LONGTEXT NOT NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_contract_deletion_archive (contract_id, created_at),
    KEY idx_contract_deletion_archives_customer (customer_id),
    KEY idx_contract_deletion_archives_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    rendered_title VARCHAR(190) NULL,
    rendered_header TEXT NULL,
    rendered_body LONGTEXT NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'generated',
    checksum CHAR(64) NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_contract_document_version (contract_id, version_number),
    KEY idx_contract_document_versions_published (contract_id, is_published, version_number),
    KEY idx_contract_document_versions_finalized (contract_id, is_finalized, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_number VARCHAR(80) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    requested_amount DECIMAL(18,2) NOT NULL,
    allocated_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    method VARCHAR(30) NOT NULL DEFAULT 'manual',
    status VARCHAR(30) NOT NULL DEFAULT 'paid',
    gateway_track_id VARCHAR(100) NULL,
    idempotency_key VARCHAR(120) NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_payment_groups_number (group_number),
    UNIQUE KEY uq_payment_groups_idempotency (idempotency_key),
    KEY idx_payment_groups_contract (contract_id),
    KEY idx_payment_groups_customer (customer_id),
    KEY idx_payment_groups_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_group_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(18,2) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_payment_allocation_installment (payment_group_id, installment_id),
    KEY idx_payment_allocations_payment (payment_id),
    KEY idx_payment_allocations_contract (contract_id),
    CONSTRAINT fk_payment_allocation_group FOREIGN KEY (payment_group_id) REFERENCES payment_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_bulk_operations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_number VARCHAR(80) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    operation_type VARCHAR(40) NOT NULL,
    installment_ids_json LONGTEXT NOT NULL,
    old_snapshot_json LONGTEXT NULL,
    new_snapshot_json LONGTEXT NULL,
    reason TEXT NOT NULL,
    performed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_installment_bulk_operation_number (operation_number),
    KEY idx_installment_bulk_operations_contract (contract_id),
    KEY idx_installment_bulk_operations_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
