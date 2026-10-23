<?php

declare(strict_types=1);

if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
    return;
}

final class PromaPay_WC_Blocks_Integration extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    protected $name = 'promapaywc_installments';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_promapaywc_installments_settings', []);
    }

    public function is_active(): bool
    {
        return (string) ($this->settings['enabled'] ?? 'no') === 'yes'
            && (new PromaPay_WC_Gateway())->is_available();
    }

    public function get_payment_method_script_handles(): array
    {
        $handle = 'promapaywc-blocks-checkout';
        wp_register_script(
            $handle,
            PROMA_PAY_WC_URL . 'assets/js/checkout-blocks.js',
            ['wp-element', 'wc-blocks-registry', 'wc-settings'],
            PROMA_PAY_WC_VERSION,
            true
        );
        return [$handle];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title' => (string) ($this->settings['title'] ?? __('خرید اقساطی پروما پی', 'proma-pay-woocommerce')),
            'description' => (string) ($this->settings['description'] ?? ''),
            'connections' => PromaPay_WC_Commerce::checkout_connections(),
            'methods' => ['flat', 'compound', 'reducing_balance'],
            'endpoint' => rest_url('promapaywc/v1/quote'),
            'nonce' => wp_create_nonce('wp_rest'),
            'supports' => ['products', 'refunds'],
        ];
    }
}
