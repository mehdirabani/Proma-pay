ALTER TABLE ecommerce_installment_requests
    ADD COLUMN review_note TEXT NULL,
    ADD COLUMN reviewed_by INT UNSIGNED NULL,
    ADD COLUMN reviewed_at DATETIME NULL;
