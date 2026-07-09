CREATE TABLE IF NOT EXISTS identity_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    review_note TEXT NULL,
    uploaded_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    INDEX idx_identity_documents_user (user_id),
    INDEX idx_identity_documents_status (status),
    CONSTRAINT fk_identity_documents_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    review_note TEXT NULL,
    reviewed_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_chat_attachments_message (message_id),
    INDEX idx_chat_attachments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
