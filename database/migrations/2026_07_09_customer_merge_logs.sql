CREATE TABLE IF NOT EXISTS customer_merge_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    keep_customer_id BIGINT UNSIGNED NOT NULL,
    merged_customer_id BIGINT UNSIGNED NOT NULL,
    merged_by BIGINT UNSIGNED NULL,
    snapshot_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_customer_merge_keep (keep_customer_id),
    KEY idx_customer_merge_merged (merged_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
