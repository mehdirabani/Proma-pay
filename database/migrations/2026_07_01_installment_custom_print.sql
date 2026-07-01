ALTER TABLE installments
  ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER guarantee_serial;
