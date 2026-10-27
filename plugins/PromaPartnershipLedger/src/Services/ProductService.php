<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\Money;

final class ProductService
{
    public static function create(array $data, $actorId)
    {
        $name = trim((string) ($data['name'] ?? ''));
        $tracking = trim((string) ($data['tracking_mode'] ?? 'quantity'));
        $productType = trim((string) ($data['product_type'] ?? 'other'));
        if ($name === '' || !in_array($tracking, ['quantity', 'serial'], true)
            || !in_array($productType, ['mobile', 'tablet', 'digital', 'accessory', 'other'], true)) {
            throw new \InvalidArgumentException('نام، نوع و روش ردیابی کالا معتبر نیست.');
        }
        $brand = self::catalogValue('brand', $data['brand_id'] ?? '', $data['brand'] ?? '');
        $category = self::catalogValue('category', $data['category_id'] ?? '', $data['category'] ?? '');
        if (empty($category['id'])) {
            throw new \InvalidArgumentException('دسته‌بندی کالا را از فهرست ثبت‌شده انتخاب کنید.');
        }
        $model = trim((string) ($data['model'] ?? ''));
        $color = trim((string) ($data['color'] ?? ''));
        $storage = trim((string) ($data['storage'] ?? $data['capacity'] ?? ''));
        $ram = trim((string) ($data['ram'] ?? ''));
        $purchase = Money::fromToman($data['reference_purchase_toman'] ?? '0');
        $market = Money::fromToman($data['market_toman'] ?? '0');
        $sale = Money::fromToman($data['sale_toman'] ?? '0');
        $minimum = Money::fromToman($data['minimum_sale_toman'] ?? '0');
        if ($minimum > 0 && $sale > 0 && $minimum > $sale) {
            throw new \InvalidArgumentException('حداقل قیمت مجاز فروش نمی‌تواند از قیمت فروش پیشنهادی بیشتر باشد.');
        }
        $barcode = trim((string) ($data['barcode'] ?? ''));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') { $idempotencyKey = 'product-' . bin2hex(random_bytes(16)); }
        $hash = hash('sha256', json_encode([
            'name' => $name, 'type' => $productType, 'category_id' => (int) $category['id'], 'brand_id' => (int) ($brand['id'] ?? 0),
            'model' => $model, 'color' => $color, 'storage' => $storage, 'ram' => $ram, 'tracking' => $tracking,
            'barcode' => $barcode, 'purchase' => Money::db($purchase), 'market' => Money::db($market), 'sale' => Money::db($sale),
            'minimum_sale' => Money::db($minimum), 'minimum_stock' => min(1000000, Money::quantity($data['minimum_stock'] ?? '0')),
            'technical_specs' => trim((string) ($data['technical_specs'] ?? '')), 'description' => trim((string) ($data['description'] ?? '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $started = !\Model::db()->inTransaction();
        if ($started) { \Model::begin(); }
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_products WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('کلید تکرار کالا با اطلاعات متفاوت استفاده شده است.');
                }
                if ($started) { \Model::commit(); }
                return (int) $existing['id'];
            }
            $sku = self::generateSku([
                'type' => $productType, 'brand' => $brand['name'] ?? '', 'category' => $category['name'] ?? '',
                'name' => $name, 'model' => $model !== '' ? $model : $name, 'color' => $color, 'storage' => $storage, 'ram' => $ram,
            ]);
            \Model::execute(
                'INSERT INTO plugin_inventory_products
                 (sku, barcode, name, product_type, category, category_id, brand, brand_id, model, technical_specs, color, capacity, ram, tracking_mode, status, description, reference_purchase_price_rial, market_price_rial, sale_price_rial, minimum_sale_price_rial, minimum_stock, request_hash, idempotency_key, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $sku, $barcode !== '' ? substr($barcode, 0, 100) : null, substr($name, 0, 190), $productType,
                    self::nullable($category['name'] ?? ''), $category['id'] ?? null, self::nullable($brand['name'] ?? ''), $brand['id'] ?? null,
                    self::nullable($model), trim((string) ($data['technical_specs'] ?? '')) ?: null,
                    self::nullable($color), self::nullable($storage), self::nullable($ram), $tracking,
                    trim((string) ($data['description'] ?? '')) ?: null, Money::db($purchase), Money::db($market), Money::db($sale), Money::db($minimum),
                    min(1000000, Money::quantity($data['minimum_stock'] ?? '0')), $hash, $idempotencyKey, $actorId ? (int) $actorId : null,
                ]
            );
            $productId = (int) \Model::lastInsertId();
            AuditService::record('product_created', 'product', $productId, $actorId, [], ['sku' => $sku, 'name' => $name, 'product_type' => $productType]);
            if ($started) { \Model::commit(); }
            return $productId;
        } catch (\Throwable $error) {
            if ($started) { \Model::rollBack(); }
            throw $error;
        }
    }

    public static function updatePrices($productId, array $data, $reason, $actorId)
    {
        $productId = (int) $productId;
        $reason = trim((string) $reason);
        if ($productId <= 0 || $reason === '') {
            throw new \InvalidArgumentException('کالا و دلیل تغییر قیمت الزامی است.');
        }
        $map = [
            'reference_purchase_price_rial' => 'reference_purchase_toman',
            'market_price_rial' => 'market_toman',
            'sale_price_rial' => 'sale_toman',
            'minimum_sale_price_rial' => 'minimum_sale_toman',
        ];
        \Model::begin();
        try {
            $product = \Model::fetch('SELECT * FROM plugin_inventory_products WHERE id = ? LIMIT 1 FOR UPDATE', [$productId]);
            if (!$product) {
                throw new \InvalidArgumentException('کالا پیدا نشد.');
            }
            $changes = [];
            foreach ($map as $column => $key) {
                if (!array_key_exists($key, $data) || trim((string) $data[$key]) === '') {
                    continue;
                }
                $next = Money::fromToman($data[$key]);
                $before = Money::integer($product[$column]);
                if ($next === $before) {
                    continue;
                }
                $changes[$column] = $next;
                \Model::execute(
                    'INSERT INTO plugin_inventory_price_history (product_id, price_type, before_rial, after_rial, reason, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                    [$productId, $column, Money::db($before), Money::db($next), substr($reason, 0, 255), $actorId ? (int) $actorId : null]
                );
            }
            $nextSale = $changes['sale_price_rial'] ?? Money::integer($product['sale_price_rial']);
            $nextMinimum = $changes['minimum_sale_price_rial'] ?? Money::integer($product['minimum_sale_price_rial']);
            if ($nextMinimum > 0 && $nextSale > 0 && $nextMinimum > $nextSale) {
                throw new \InvalidArgumentException('حداقل قیمت مجاز فروش نمی‌تواند از قیمت فروش پیشنهادی بیشتر باشد.');
            }
            if ($changes) {
                $sets = [];
                $params = [];
                foreach ($changes as $column => $amount) {
                    $sets[] = $column . ' = ?';
                    $params[] = Money::db($amount);
                }
                $sets[] = 'updated_at = NOW()';
                $params[] = $productId;
                \Model::execute('UPDATE plugin_inventory_products SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
                AuditService::record('product_prices_changed', 'product', $productId, $actorId, [], ['fields' => array_keys($changes), 'reason' => $reason]);
            }
            \Model::commit();
            return count($changes);
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function update($productId, array $data, $actorId)
    {
        $productId = (int) $productId;
        $name = trim((string) ($data['name'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'active'));
        if ($productId <= 0 || $name === '' || !in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException('اطلاعات کالا معتبر نیست.');
        }
        \Model::begin();
        try {
            $before = \Model::fetch('SELECT id, name, product_type, status, category, category_id, brand, brand_id, model, technical_specs, color, capacity, ram, description FROM plugin_inventory_products WHERE id = ? LIMIT 1 FOR UPDATE', [$productId]);
            if (!$before) {
                throw new \InvalidArgumentException('کالا پیدا نشد.');
            }
            $brand = array_key_exists('brand_id', $data) ? self::catalogValue('brand', $data['brand_id'], $data['brand'] ?? '') : ['id' => $before['brand_id'], 'name' => $before['brand']];
            $category = array_key_exists('category_id', $data) ? self::catalogValue('category', $data['category_id'], $data['category'] ?? '') : ['id' => $before['category_id'], 'name' => $before['category']];
            $productType = trim((string) ($data['product_type'] ?? $before['product_type']));
            if (!in_array($productType, ['mobile', 'tablet', 'digital', 'accessory', 'other'], true)) {
                throw new \InvalidArgumentException('نوع کالا معتبر نیست.');
            }
            \Model::execute('UPDATE plugin_inventory_products SET name = ?, product_type = ?, category = ?, category_id = ?, brand = ?, brand_id = ?, model = ?, technical_specs = ?, color = ?, capacity = ?, ram = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?', [
                substr($name, 0, 190),
                $productType,
                self::nullable($category['name'] ?? ''), $category['id'] ?: null,
                self::nullable($brand['name'] ?? ''), $brand['id'] ?: null,
                array_key_exists('model', $data) ? self::nullable($data['model']) : $before['model'],
                array_key_exists('technical_specs', $data) && trim((string) $data['technical_specs']) !== '' ? trim((string) $data['technical_specs']) : $before['technical_specs'],
                array_key_exists('color', $data) ? self::nullable($data['color']) : $before['color'],
                array_key_exists('storage', $data) ? self::nullable($data['storage']) : (array_key_exists('capacity', $data) ? self::nullable($data['capacity']) : $before['capacity']),
                array_key_exists('ram', $data) ? self::nullable($data['ram']) : $before['ram'],
                array_key_exists('description', $data) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : $before['description'],
                $status, $productId,
            ]);
            AuditService::record('product_updated', 'product', $productId, $actorId, $before, ['name' => $name, 'status' => $status]);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function createWarehouse(array $data, $actorId)
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $managerRaw = trim((string) ($data['manager_user_id'] ?? '0'));
        $managerId = $managerRaw === '' ? 0 : Money::quantity($managerRaw);
        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code) || $name === '') {
            throw new \InvalidArgumentException('شناسه و نام انبار معتبر الزامی است.');
        }
        if ($managerId > 0 && !\Model::fetch("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1", [$managerId])) {
            throw new \InvalidArgumentException('مدیر انبار فعال پیدا نشد.');
        }
        \Model::execute('INSERT INTO plugin_inventory_warehouses (code, name, location, manager_user_id, status, created_by, created_at) VALUES (?, ?, ?, ?, \'active\', ?, NOW())', [
            $code, substr($name, 0, 190), self::nullable($data['location'] ?? ''), $managerId ?: null, $actorId ? (int) $actorId : null,
        ]);
        $id = (int) \Model::lastInsertId();
        AuditService::record('warehouse_created', 'warehouse', $id, $actorId, [], ['code' => $code, 'name' => $name]);
        return $id;
    }

    public static function createSupplier(array $data, $actorId)
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('نام تأمین‌کننده الزامی است.');
        }
        \Model::execute('INSERT INTO plugin_inventory_suppliers (name, phone, identity_no, notes, status, created_by, created_at) VALUES (?, ?, ?, ?, \'active\', ?, NOW())', [
            substr($name, 0, 190), self::nullable($data['phone'] ?? ''), self::nullable($data['identity_no'] ?? ''), trim((string) ($data['notes'] ?? '')) ?: null, $actorId ? (int) $actorId : null,
        ]);
        $id = (int) \Model::lastInsertId();
        AuditService::record('supplier_created', 'supplier', $id, $actorId, [], ['name' => $name]);
        return $id;
    }

    public static function nullable($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 120);
    }

    public static function validateSkuPattern($pattern)
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '' || strlen($pattern) > 180 || !preg_match('/^[A-Za-z0-9_{}.-]+$/', $pattern)) {
            throw new \InvalidArgumentException('الگوی SKU فقط می‌تواند شامل حروف لاتین، عدد، نقطه، خط تیره، زیرخط و متغیرهای مجاز باشد.');
        }
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        $allowed = ['type', 'brand', 'category', 'name', 'product', 'model', 'color', 'storage', 'ram'];
        foreach ($matches[1] as $token) {
            if (!in_array($token, $allowed, true)) {
                throw new \InvalidArgumentException('متغیر {' . $token . '} در الگوی SKU پشتیبانی نمی‌شود.');
            }
        }
        if (!$matches[1]) {
            throw new \InvalidArgumentException('الگوی SKU باید حداقل یک متغیر کالا داشته باشد.');
        }
        return $pattern;
    }

    private static function catalogValue($kind, $rawId, $legacyName)
    {
        $id = trim((string) $rawId);
        if ($id !== '' && (int) $id > 0) {
            $row = CatalogService::findActive($kind, (int) $id);
            if (!$row) {
                throw new \InvalidArgumentException('برند یا دسته‌بندی انتخاب‌شده فعال نیست.');
            }
            return $row;
        }
        $name = trim((string) $legacyName);
        if ($name === '') { return ['id' => null, 'name' => '']; }
        $row = CatalogService::findByName($kind, $name);
        if (!$row) {
            throw new \InvalidArgumentException('برند و دسته‌بندی باید از فهرست ثبت‌شده انتخاب شوند.');
        }
        return $row;
    }

    private static function generateSku(array $values)
    {
        $setting = \Model::fetch("SELECT setting_value FROM plugin_inventory_settings WHERE setting_key = 'sku_pattern' LIMIT 1");
        $pattern = self::validateSkuPattern($setting['setting_value'] ?? '{type}_{brand}_{model}_{color}_{storage}_{ram}');
        $values['product'] = $values['name'];
        $sku = preg_replace_callback('/\{([a-z]+)\}/', static function ($match) use ($values) {
            return self::skuPart($values[$match[1]] ?? '');
        }, $pattern);
        $sku = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $sku);
        $sku = trim(preg_replace('/[-_.]{2,}/', '_', $sku), '._-');
        $sku = strtoupper(substr($sku !== '' ? $sku : 'PRODUCT', 0, 80));
        $base = $sku;
        for ($suffix = 2; \Model::fetch('SELECT id FROM plugin_inventory_products WHERE sku = ? LIMIT 1', [$sku]); $suffix++) {
            $tail = '-' . $suffix;
            $sku = substr($base, 0, 80 - strlen($tail)) . $tail;
        }
        return $sku;
    }

    private static function skuPart($value)
    {
        $value = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            'ا' => 'a', 'آ' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's', 'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z',
            'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v',
            'ه' => 'h', 'ی' => 'y', 'ي' => 'y', 'ئ' => 'y', 'ء' => '', '‌' => '-',
        ]);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }
}
