CREATE TABLE IF NOT EXISTS ecommerce_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    sku VARCHAR(100) NULL,
    category VARCHAR(120) NULL,
    brand VARCHAR(120) NULL,
    price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sale_price BIGINT UNSIGNED NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    short_description VARCHAR(500) NULL,
    description LONGTEXT NULL,
    image_path VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY ux_ecommerce_products_slug (slug),
    KEY idx_ecommerce_products_status (status),
    KEY idx_ecommerce_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NULL,
    full_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(30) NOT NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    order_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    subtotal_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY ux_ecommerce_orders_number (order_number),
    KEY idx_ecommerce_orders_customer (customer_id),
    KEY idx_ecommerce_orders_status (order_status, payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_title VARCHAR(190) NOT NULL,
    product_sku VARCHAR(100) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ecommerce_order_items_order (order_id),
    KEY idx_ecommerce_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_installment_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    full_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(30) NOT NULL,
    product_needed VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    KEY idx_ecommerce_installment_requests_customer (customer_id),
    KEY idx_ecommerce_installment_requests_product (product_id),
    KEY idx_ecommerce_installment_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
