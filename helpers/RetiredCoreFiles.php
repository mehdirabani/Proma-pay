<?php

/**
 * Paths that may be removed by a Core update after the internal store and
 * abandoned Laravel application were retired. Keep this list narrow: update
 * manifests may not use file deletion for arbitrary application paths.
 */
final class RetiredCoreFiles
{
    private const PREFIXES = [
        'laravel/',
    ];

    private const FILES = [
        'controllers/EcommerceController.php',
        'models/Ecommerce.php',
        'views/ecommerce/add-product.php',
        'views/ecommerce/cart.php',
        'views/ecommerce/checkout.php',
        'views/ecommerce/installment-request.php',
        'views/ecommerce/landing.php',
        'views/ecommerce/my-orders.php',
        'views/ecommerce/orders.php',
        'views/ecommerce/payment.php',
        'views/ecommerce/product.php',
        'views/ecommerce/products.php',
        'views/ecommerce/shop.php',
        'database/migrations/2026_07_10_ecommerce_module.sql',
        'database/migrations/2026_07_11_ecommerce_feature_flags.sql',
        'database/migrations/2026_07_11_ecommerce_order_review_fields.sql',
    ];

    public static function allows($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if ($path === '' || $path[0] === '/' || preg_match('/^[a-z]:/i', $path)) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }

        foreach (self::PREFIXES as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        foreach (self::FILES as $file) {
            if ($path === $file) {
                return true;
            }
        }
        return false;
    }

    /** Return the deepest directory that may be pruned after a retired file is removed. */
    public static function pruneBoundary($path)
    {
        $path = strtolower(str_replace('\\', '/', (string) $path));
        if (strpos($path, 'laravel/') === 0) {
            return 'laravel';
        }
        if (strpos($path, 'views/ecommerce/') === 0) {
            return 'views/ecommerce';
        }
        return null;
    }
}
