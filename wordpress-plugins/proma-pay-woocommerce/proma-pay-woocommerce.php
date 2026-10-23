<?php
/**
 * Plugin Name: Proma Pay for WooCommerce
 * Description: Connect a WordPress site to approved Proma Pay installment services.
 * Version: 0.8.0-rc.1
 * Requires PHP: 8.3
 * Text Domain: proma-pay-woocommerce
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('PROMA_PAY_WC_VERSION', '0.8.0-rc.1');
define('PROMA_PAY_WC_FILE', __FILE__);
define('PROMA_PAY_WC_PATH', plugin_dir_path(__FILE__));
define('PROMA_PAY_WC_URL', plugin_dir_url(__FILE__));

require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-crypto.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-installer.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-connection-repository.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-api-client.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-policy-service.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-admin.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-payment-adapter.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-quote-service.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-bakala-compatibility-adapter.php';
require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-commerce.php';

register_activation_hook(__FILE__, ['PromaPay_WC_Installer', 'activate']);
add_action('before_woocommerce_init', static function (): void {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PROMA_PAY_WC_FILE, true);
    }
});
PromaPay_WC_Commerce::register();
add_action('plugins_loaded', static function (): void {
    if (is_admin()) {
        PromaPay_WC_Admin::register();
        add_action('admin_init', static function (): void {
            if (current_user_can('manage_proma_pay_connections')) {
                PromaPay_WC_Installer::maybe_upgrade();
            }
        });
    }
    if (!class_exists('WooCommerce')) {
        return;
    }
    require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-gateway.php';
    if (class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        require_once PROMA_PAY_WC_PATH . 'includes/class-promapaywc-blocks-integration.php';
    }
});
add_action('promapaywc_daily_cleanup', static function (): void {
    (new PromaPay_WC_Connection_Repository())->cleanup_expired();
});
