<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $value = file_get_contents($root . '/' . $relative);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read plugin source: ' . $relative);
    }
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$main = $read('proma-pay-woocommerce.php');
$admin = $read('includes/class-promapaywc-admin.php');
$client = $read('includes/class-promapaywc-api-client.php');
$crypto = $read('includes/class-promapaywc-crypto.php');
$repository = $read('includes/class-promapaywc-connection-repository.php');
$installer = $read('includes/class-promapaywc-installer.php');
$policy = $read('includes/class-promapaywc-policy-service.php');
$gateway = $read('includes/class-promapaywc-gateway.php');
$commerce = $read('includes/class-promapaywc-commerce.php');
$blocks = $read('includes/class-promapaywc-blocks-integration.php');
$adapter = $read('includes/class-promapaywc-payment-adapter.php');
$quoteService = $read('includes/class-promapaywc-quote-service.php');
$blocksClient = $read('assets/js/checkout-blocks.js');
$assert(str_contains($main, 'Plugin Name: Proma Pay for WooCommerce') && str_contains($main, 'register_activation_hook'), 'Installable WordPress plugin entry point is missing.');
$assert(str_contains($main, "add_action('admin_init'") && str_contains($installer, "current_user_can('manage_proma_pay_connections')"), 'Schema upgrades are not restricted to an authorized site manager.');
$assert(str_contains($admin, 'current_user_can') && str_contains($admin, 'check_admin_referer'), 'Admin actions are missing capability and nonce checks.');
$assert(str_contains($admin, 'require_admin_tls') && str_contains($admin, 'is_ssl()') && str_contains($admin, "wp_get_environment_type() === 'local'"), 'Connection secrets can be submitted through non-TLS production administration.');
$assert(str_contains($admin, 'manage_proma_pay_connections') && str_contains($admin, 'admin_post_promapaywc_test_connection'), 'Scoped connection management or connection test is missing.');
$assert(str_contains($client, 'wp_safe_remote_request') && str_contains($client, "'redirection' => 0") && str_contains($client, "'sslverify' => true"), 'Outbound requests are missing SSRF validation, redirect blocking, or TLS verification.');
$assert(!str_contains($client, 'wp_remote_request(') && !str_contains($client, 'error_log('), 'Unsafe transport or secret-bearing diagnostic logging was added.');
$assert(str_contains($crypto, 'aes-256-gcm') && str_contains($crypto, 'wp_salt'), 'Credential and policy encryption is not tied to authenticated site secrets.');
$assert(str_contains($repository, 'token_ciphertext') && str_contains($repository, 'payload_ciphertext') && str_contains($repository, 'request_id'), 'Encrypted connection/policy persistence or minimized logs are missing.');
$assert(str_contains($repository, 'expires_at_epoch') && str_contains($repository, 'CACHE_TTL'), 'Policy cache has no bounded expiry.');
$assert(str_contains($installer, 'dbDelta') && !preg_match('/\b(?:DROP|TRUNCATE)\s+TABLE\b/i', $installer), 'Installer is not additive or contains a destructive table operation.');
$assert(str_contains($installer, 'schedule_cleanup') && str_contains($repository, 'INTERVAL 90 DAY') && str_contains($repository, 'OFFSET 49999'), 'Bounded log retention and policy cache cleanup are missing.');
$assert(str_contains($policy, "'/policies/financial'") && str_contains($policy, 'cache_policy'), 'Published financial policy sync is not wired.');
$assert(str_contains($gateway, 'extends WC_Payment_Gateway') && str_contains($commerce, "'woocommerce_payment_gateways'"), 'WooCommerce payment gateway is not registered.');
$assert(str_contains($commerce, 'declare_compatibility') && str_contains($commerce, 'custom_order_tables'), 'HPOS compatibility is not declared.');
$assert(str_contains($commerce, 'wc_get_order') && str_contains($commerce, 'wc_create_order') && str_contains($commerce, 'WC_Order_Item_Fee'), 'Deposit orders do not use WooCommerce CRUD.');
$assert(!preg_match('/\$wpdb\s*->\s*(?:query|get_results|get_row|get_var)\s*\(/', $commerce), 'Commerce order lifecycle bypasses WooCommerce CRUD.');
$assert(str_contains($blocks, 'AbstractPaymentMethodType') && str_contains($blocksClient, 'onPaymentSetup') && str_contains($blocksClient, 'paymentMethodData'), 'Checkout Blocks does not submit validated payment data.');
$assert(str_contains($adapter, "['bacs', 'cheque', 'cod']") && str_contains($adapter, 'process_payment'), 'Deposit adapter may select an offline or installment gateway.');
$assert(str_contains($commerce, 'wc_get_checkout_order_received_url') && str_contains($commerce, '$reviewComplete'), 'Deposit return or bounded contract/guarantee polling is incomplete.');
$assert(str_contains($gateway, 'promapaywc_partial_refund_unsupported') && str_contains($gateway, 'depositOrder->is_paid()'), 'Refund flow does not prevent partial or unpaid deposit refunds.');
$assert(str_contains($quoteService, 'valid_guarantee') && str_contains($quoteService, 'cheque_due_date') && str_contains($quoteService, 'total_customer_payable'), 'Checkout does not verify the quote, guarantee, and first due date.');
$assert(str_contains($gateway, 'product_widget_enabled') && str_contains($commerce, 'product_widget_enabled()'), 'Product estimate visibility is not configurable.');

echo "PROMAPAY_WC_PHASE7_STATIC_OK\n";
