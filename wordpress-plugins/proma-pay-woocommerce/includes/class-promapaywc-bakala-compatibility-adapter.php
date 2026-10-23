<?php

declare(strict_types=1);

/** Adapts product-widget placement to Bakala while keeping WooCommerce hooks canonical. */
final class PromaPay_WC_BakalaCompatibilityAdapter
{
    private const PRODUCT_SUMMARY_HOOK = 'woocommerce_single_product_summary';
    private const PRODUCT_FALLBACK_HOOK = 'woocommerce_after_single_product_summary';
    private static array $renderedProducts = [];
    private static bool $fallbackLogged = false;

    public static function register(): void
    {
        add_action(self::PRODUCT_SUMMARY_HOOK, [self::class, 'render_product_widget'], 25);

        if (self::is_bakala()) {
            add_action(self::PRODUCT_FALLBACK_HOOK, [self::class, 'render_product_widget_fallback'], 5);
        }
    }

    public static function is_bakala(): bool
    {
        $theme = self::theme_info();
        return in_array($theme['template'], ['bakala', 'bakala-child'], true)
            || in_array($theme['stylesheet'], ['bakala', 'bakala-child'], true)
            || in_array($theme['text_domain'], ['bakala', 'bakala-child'], true);
    }

    /** Returns public theme metadata for diagnostics; it never reads or changes theme files. */
    public static function theme_info(): array
    {
        if (!function_exists('wp_get_theme')) {
            return ['name' => '', 'version' => '', 'template' => '', 'stylesheet' => '', 'text_domain' => ''];
        }

        $theme = wp_get_theme();
        if (!is_object($theme) || !method_exists($theme, 'get')) {
            return ['name' => '', 'version' => '', 'template' => '', 'stylesheet' => '', 'text_domain' => ''];
        }

        return [
            'name' => (string) $theme->get('Name'),
            'version' => (string) $theme->get('Version'),
            'template' => method_exists($theme, 'get_template') ? strtolower((string) $theme->get_template()) : '',
            'stylesheet' => method_exists($theme, 'get_stylesheet') ? strtolower((string) $theme->get_stylesheet()) : '',
            'text_domain' => strtolower((string) $theme->get('TextDomain')),
        ];
    }

    public static function render_product_widget(): void
    {
        self::render_once();
    }

    public static function render_product_widget_fallback(): void
    {
        global $product;
        $productId = self::product_id($product);
        if ($productId < 1 || isset(self::$renderedProducts[$productId])) {
            return;
        }

        self::log_fallback();
        self::render_once();
    }

    private static function render_once(): void
    {
        global $product;
        $productId = self::product_id($product);
        if ($productId < 1 || isset(self::$renderedProducts[$productId])
            || !class_exists('PromaPay_WC_Commerce')) {
            return;
        }

        self::$renderedProducts[$productId] = true;
        PromaPay_WC_Commerce::render_product_widget();
    }

    private static function product_id($product): int
    {
        return is_object($product) && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
    }

    private static function log_fallback(): void
    {
        if (self::$fallbackLogged || !function_exists('wc_get_logger')) {
            return;
        }
        $logger = wc_get_logger();
        if (!is_object($logger) || !method_exists($logger, 'warning')) {
            return;
        }

        $theme = self::theme_info();
        $logger->warning('Bakala did not render the standard product summary hook; using the WooCommerce summary fallback.', [
            'source' => 'promapaywc-bakala-compatibility',
            'theme_version' => $theme['version'],
            'hook' => self::PRODUCT_FALLBACK_HOOK,
        ]);
        self::$fallbackLogged = true;
    }
}
