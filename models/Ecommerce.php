<?php

class Ecommerce extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }

        self::execute(
            "CREATE TABLE IF NOT EXISTS ecommerce_products (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::execute(
            "CREATE TABLE IF NOT EXISTS ecommerce_orders (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::execute(
            "CREATE TABLE IF NOT EXISTS ecommerce_order_items (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::execute(
            "CREATE TABLE IF NOT EXISTS ecommerce_installment_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NULL,
                product_id INT UNSIGNED NULL,
                full_name VARCHAR(190) NOT NULL,
                mobile VARCHAR(30) NOT NULL,
                product_needed VARCHAR(255) NOT NULL,
                notes TEXT NULL,
                review_note TEXT NULL,
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'new',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                KEY idx_ecommerce_installment_requests_customer (customer_id),
                KEY idx_ecommerce_installment_requests_product (product_id),
                KEY idx_ecommerce_installment_requests_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ([
            'review_note' => 'TEXT NULL',
            'reviewed_by' => 'INT UNSIGNED NULL',
            'reviewed_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE ecommerce_installment_requests ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }

        self::$schemaReady = true;
    }

    public static function products(array $filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $needle = '%' . to_english_digits($filters['q']) . '%';
            $where[] = '(title LIKE ? OR sku LIKE ? OR category LIKE ? OR brand LIKE ? OR short_description LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = ?';
            $params[] = $filters['category'];
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) ((self::fetch("SELECT COUNT(*) AS total FROM ecommerce_products{$whereSql}", $params)['total'] ?? 0));
        $perPage = max(8, min(60, (int) ($filters['per_page'] ?? 24)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $items = self::fetchAll(
            "SELECT * FROM ecommerce_products{$whereSql}
             ORDER BY is_featured DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => array_map(function ($item) {
                return self::withDisplayPrice($item);
            }, $items),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public static function activeProducts(array $filters = [])
    {
        $filters['status'] = 'active';
        return self::products($filters);
    }

    public static function activeCategories($limit = 12)
    {
        self::ensureSchema();
        $limit = max(1, min(40, (int) $limit));
        return self::fetchAll(
            "SELECT category, COUNT(*) AS products_count
             FROM ecommerce_products
             WHERE status = 'active' AND category IS NOT NULL AND category != ''
             GROUP BY category
             ORDER BY products_count DESC, category ASC
             LIMIT {$limit}"
        );
    }

    public static function findProduct($identifier)
    {
        self::ensureSchema();
        $identifier = trim((string) $identifier);
        $row = ctype_digit($identifier)
            ? self::fetch('SELECT * FROM ecommerce_products WHERE id = ?', [(int) $identifier])
            : self::fetch('SELECT * FROM ecommerce_products WHERE slug = ?', [$identifier]);
        return $row ? self::withDisplayPrice($row) : null;
    }

    public static function saveProduct(array $data, array $upload = null, $userId = null)
    {
        self::ensureSchema();
        $id = max(0, (int) ($data['id'] ?? 0));
        $existing = $id ? self::findProduct($id) : null;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('نام محصول الزامی است.');
        }

        $price = (int) normalize_money($data['price'] ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('قیمت محصول معتبر نیست.');
        }
        $salePrice = normalize_money($data['sale_price'] ?? '');
        $salePrice = $salePrice > 0 ? (int) $salePrice : null;
        if ($salePrice !== null && $salePrice >= $price) {
            $salePrice = null;
        }

        $imagePath = $existing['image_path'] ?? null;
        if ($upload && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagePath = UploadHelper::storePublicImage($upload, 'products', UploadHelper::IMAGE_EXTENSIONS);
        }

        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive', 'draft'], true) ? $data['status'] : 'active';
        $payload = [
            'title' => $title,
            'slug' => self::uniqueSlug($title, $id),
            'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
            'category' => trim((string) ($data['category'] ?? '')) ?: null,
            'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
            'price' => $price,
            'sale_price' => $salePrice,
            'stock_quantity' => max(0, (int) to_english_digits($data['stock_quantity'] ?? 0)),
            'short_description' => trim((string) ($data['short_description'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'image_path' => $imagePath,
            'status' => $status,
            'is_featured' => !empty($data['is_featured']) ? 1 : 0,
            'created_by' => $userId ? (int) $userId : null,
        ];

        if ($id && $existing) {
            $updatePayload = $payload;
            unset($updatePayload['created_by']);
            $updatePayload['id'] = $id;
            self::execute(
                "UPDATE ecommerce_products
                 SET title = :title, slug = :slug, sku = :sku, category = :category, brand = :brand,
                     price = :price, sale_price = :sale_price, stock_quantity = :stock_quantity,
                     short_description = :short_description, description = :description, image_path = :image_path,
                     status = :status, is_featured = :is_featured, updated_at = NOW()
                 WHERE id = :id",
                $updatePayload
            );
            return $id;
        }

        self::execute(
            "INSERT INTO ecommerce_products
             (title, slug, sku, category, brand, price, sale_price, stock_quantity, short_description, description, image_path, status, is_featured, created_by, created_at)
             VALUES
             (:title, :slug, :sku, :category, :brand, :price, :sale_price, :stock_quantity, :short_description, :description, :image_path, :status, :is_featured, :created_by, NOW())",
            $payload
        );
        return (int) self::lastInsertId();
    }

    public static function orders($limit = 100)
    {
        self::ensureSchema();
        $limit = max(1, min(300, (int) $limit));
        return self::fetchAll(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM ecommerce_order_items oi WHERE oi.order_id = o.id) AS item_count,
                    COALESCE((SELECT SUM(oi.quantity) FROM ecommerce_order_items oi WHERE oi.order_id = o.id), 0) AS quantity_total
             FROM ecommerce_orders o
             ORDER BY o.id DESC
             LIMIT {$limit}"
        );
    }

    public static function ordersForCustomer($customerId, $limit = 50)
    {
        self::ensureSchema();
        $limit = max(1, min(200, (int) $limit));
        return self::fetchAll(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM ecommerce_order_items oi WHERE oi.order_id = o.id) AS item_count,
                    COALESCE((SELECT SUM(oi.quantity) FROM ecommerce_order_items oi WHERE oi.order_id = o.id), 0) AS quantity_total
             FROM ecommerce_orders o
             WHERE o.customer_id = ?
             ORDER BY o.id DESC
             LIMIT {$limit}",
            [(int) $customerId]
        );
    }

    public static function orderStatusOptions()
    {
        return [
            'pending' => 'در انتظار بررسی',
            'processing' => 'در حال پردازش',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
    }

    public static function paymentStatusOptions()
    {
        return [
            'pending' => 'در انتظار پرداخت',
            'partial' => 'پرداخت جزئی',
            'paid' => 'پرداخت شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده',
        ];
    }

    public static function installmentRequestStatusOptions()
    {
        return [
            'new' => 'جدید',
            'reviewing' => 'در حال بررسی',
            'approved' => 'تأیید شده',
            'rejected' => 'رد شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
    }

    public static function updateOrder($id, array $data)
    {
        self::ensureSchema();
        $id = (int) $id;
        $order = self::findOrder($id);
        if (!$order) {
            throw new InvalidArgumentException('سفارش پیدا نشد.');
        }

        $orderStatus = (string) ($data['order_status'] ?? $order['order_status']);
        $paymentStatus = (string) ($data['payment_status'] ?? $order['payment_status']);
        if (!array_key_exists($orderStatus, self::orderStatusOptions())) {
            throw new InvalidArgumentException('وضعیت سفارش معتبر نیست.');
        }
        if (!array_key_exists($paymentStatus, self::paymentStatusOptions())) {
            throw new InvalidArgumentException('وضعیت پرداخت معتبر نیست.');
        }

        self::execute(
            "UPDATE ecommerce_orders
             SET order_status = ?, payment_status = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $orderStatus,
                $paymentStatus,
                trim((string) ($data['notes'] ?? $order['notes'] ?? '')) ?: null,
                $id,
            ]
        );
        return true;
    }

    public static function findOrder($id)
    {
        self::ensureSchema();
        $order = self::fetch('SELECT * FROM ecommerce_orders WHERE id = ?', [(int) $id]);
        if (!$order) {
            return null;
        }
        $order['items'] = self::fetchAll(
            "SELECT oi.*, p.image_path, p.slug
             FROM ecommerce_order_items oi
             LEFT JOIN ecommerce_products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id",
            [(int) $id]
        );
        return $order;
    }

    public static function installmentRequests($limit = 100)
    {
        self::ensureSchema();
        $limit = max(1, min(300, (int) $limit));
        return self::fetchAll(
            "SELECT r.*, p.title AS product_title, u.full_name AS customer_name, reviewer.full_name AS reviewer_name
             FROM ecommerce_installment_requests r
             LEFT JOIN ecommerce_products p ON p.id = r.product_id
             LEFT JOIN users u ON u.id = r.customer_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
             ORDER BY r.id DESC
             LIMIT {$limit}"
        );
    }

    public static function updateInstallmentRequest($id, array $data, $reviewerId = null)
    {
        self::ensureSchema();
        $id = (int) $id;
        $request = self::fetch('SELECT * FROM ecommerce_installment_requests WHERE id = ?', [$id]);
        if (!$request) {
            throw new InvalidArgumentException('درخواست خرید اقساطی پیدا نشد.');
        }

        $status = (string) ($data['status'] ?? $request['status']);
        if (!array_key_exists($status, self::installmentRequestStatusOptions())) {
            throw new InvalidArgumentException('وضعیت درخواست معتبر نیست.');
        }

        self::execute(
            "UPDATE ecommerce_installment_requests
             SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [
                $status,
                trim((string) ($data['review_note'] ?? '')) ?: null,
                $reviewerId ? (int) $reviewerId : null,
                $id,
            ]
        );
        return true;
    }

    public static function createInstallmentRequest(array $data, $customerId = null)
    {
        self::ensureSchema();
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $mobile = preg_replace('/\D+/', '', to_english_digits((string) ($data['mobile'] ?? '')));
        $productNeeded = trim((string) ($data['product_needed'] ?? ''));
        if ($fullName === '' || $mobile === '' || $productNeeded === '') {
            throw new InvalidArgumentException('نام، شماره تماس و محصول مورد نیاز الزامی است.');
        }

        self::execute(
            "INSERT INTO ecommerce_installment_requests
             (customer_id, product_id, full_name, mobile, product_needed, notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())",
            [
                $customerId ? (int) $customerId : null,
                !empty($data['product_id']) ? (int) $data['product_id'] : null,
                $fullName,
                $mobile,
                $productNeeded,
                trim((string) ($data['notes'] ?? '')) ?: null,
            ]
        );
        return (int) self::lastInsertId();
    }

    public static function cart()
    {
        return $_SESSION['proma_ecommerce_cart'] ?? [];
    }

    public static function addToCart($productId, $quantity = 1)
    {
        $product = self::findProduct((int) $productId);
        if (!$product || ($product['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('محصول برای خرید در دسترس نیست.');
        }
        $quantity = max(1, min(99, (int) $quantity));
        $cart = self::cart();
        $current = (int) ($cart[(int) $product['id']] ?? 0);
        $cart[(int) $product['id']] = min(99, $current + $quantity);
        $_SESSION['proma_ecommerce_cart'] = $cart;
    }

    public static function updateCart(array $quantities)
    {
        $cart = [];
        foreach ($quantities as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = max(0, min(99, (int) to_english_digits($quantity)));
            if ($productId > 0 && $quantity > 0 && self::findProduct($productId)) {
                $cart[$productId] = $quantity;
            }
        }
        $_SESSION['proma_ecommerce_cart'] = $cart;
    }

    public static function adjustCart($productId, $delta)
    {
        $productId = (int) $productId;
        $delta = (int) $delta;
        $cart = self::cart();
        if ($productId <= 0 || !array_key_exists($productId, $cart)) {
            return;
        }
        $next = max(0, min(99, (int) $cart[$productId] + $delta));
        if ($next <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $next;
        }
        $_SESSION['proma_ecommerce_cart'] = $cart;
    }

    public static function removeFromCart($productId)
    {
        $cart = self::cart();
        unset($cart[(int) $productId]);
        $_SESSION['proma_ecommerce_cart'] = $cart;
    }

    public static function clearCart()
    {
        unset($_SESSION['proma_ecommerce_cart']);
    }

    public static function cartSummary()
    {
        self::ensureSchema();
        $cart = self::cart();
        if (!$cart) {
            return ['items' => [], 'subtotal' => 0, 'total' => 0, 'quantity' => 0];
        }
        $ids = array_map('intval', array_keys($cart));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $products = self::fetchAll("SELECT * FROM ecommerce_products WHERE id IN ({$placeholders}) AND status = 'active'", $ids);
        $items = [];
        $subtotal = 0;
        $quantityTotal = 0;
        foreach ($products as $product) {
            $product = self::withDisplayPrice($product);
            $qty = max(1, (int) ($cart[(int) $product['id']] ?? 1));
            $lineTotal = $qty * (int) $product['display_price'];
            $product['quantity'] = $qty;
            $product['line_total'] = $lineTotal;
            $items[] = $product;
            $subtotal += $lineTotal;
            $quantityTotal += $qty;
        }
        return ['items' => $items, 'subtotal' => $subtotal, 'total' => $subtotal, 'quantity' => $quantityTotal];
    }

    public static function createOrderFromCart($customerId, array $data)
    {
        self::ensureSchema();
        $summary = self::cartSummary();
        if (empty($summary['items'])) {
            throw new InvalidArgumentException('سبد خرید خالی است.');
        }
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $mobile = preg_replace('/\D+/', '', to_english_digits((string) ($data['mobile'] ?? '')));
        if ($fullName === '' || $mobile === '') {
            throw new InvalidArgumentException('نام و شماره تماس برای ثبت سفارش الزامی است.');
        }

        self::begin();
        try {
            $orderNumber = self::uniqueOrderNumber();
            self::execute(
                "INSERT INTO ecommerce_orders
                 (order_number, customer_id, full_name, mobile, email, address, payment_method, payment_status, order_status, subtotal_amount, total_amount, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, NOW())",
                [
                    $orderNumber,
                    $customerId ? (int) $customerId : null,
                    $fullName,
                    $mobile,
                    trim((string) ($data['email'] ?? '')) ?: null,
                    trim((string) ($data['address'] ?? '')) ?: null,
                    in_array(($data['payment_method'] ?? 'manual'), ['manual', 'card_transfer', 'gateway'], true) ? $data['payment_method'] : 'manual',
                    (int) $summary['subtotal'],
                    (int) $summary['total'],
                    trim((string) ($data['notes'] ?? '')) ?: null,
                ]
            );
            $orderId = (int) self::lastInsertId();
            foreach ($summary['items'] as $item) {
                self::execute(
                    "INSERT INTO ecommerce_order_items
                     (order_id, product_id, product_title, product_sku, quantity, unit_price, total_price, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $orderId,
                        (int) $item['id'],
                        $item['title'],
                        $item['sku'] ?? null,
                        (int) $item['quantity'],
                        (int) $item['display_price'],
                        (int) $item['line_total'],
                    ]
                );
            }
            self::commit();
            self::clearCart();
            try {
                EmailService::sendOrderConfirmation(self::findOrder($orderId));
            } catch (Throwable $mailException) {
            }
            return $orderId;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    protected static function withDisplayPrice(array $product)
    {
        $sale = isset($product['sale_price']) ? (int) $product['sale_price'] : 0;
        $price = (int) ($product['price'] ?? 0);
        $product['display_price'] = $sale > 0 && $sale < $price ? $sale : $price;
        $product['has_discount'] = $sale > 0 && $sale < $price;
        return $product;
    }

    protected static function uniqueSlug($title, $excludeId = 0)
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(to_english_digits($title), 'UTF-8')), '-');
        if ($base === '') {
            $base = 'product';
        }
        $slug = mb_substr($base, 0, 190, 'UTF-8');
        $counter = 2;
        while (self::fetch('SELECT id FROM ecommerce_products WHERE slug = ? AND id != ? LIMIT 1', [$slug, (int) $excludeId])) {
            $suffix = '-' . $counter++;
            $slug = mb_substr($base, 0, 190 - strlen($suffix), 'UTF-8') . $suffix;
        }
        return $slug;
    }

    protected static function uniqueOrderNumber()
    {
        do {
            $number = 'EC-' . date('Ymd') . '-' . random_int(1000, 9999);
        } while (self::fetch('SELECT id FROM ecommerce_orders WHERE order_number = ? LIMIT 1', [$number]));
        return $number;
    }
}
