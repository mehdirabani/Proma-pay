<?php

declare(strict_types=1);

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

/** WooCommerce installment gateway. It keeps the full order unpaid and delegates only the deposit. */
final class PromaPay_WC_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'promapaywc_installments';
        $this->method_title = __('Proma Pay installments', 'proma-pay-woocommerce');
        $this->method_description = __('Submit an installment request and pay the initial deposit through the store’s selected bank gateway.', 'proma-pay-woocommerce');
        $this->has_fields = true;
        $this->supports = ['products', 'refunds'];
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option('enabled', 'no');
        $this->title = $this->get_option('title', __('خرید اقساطی پروما پی', 'proma-pay-woocommerce'));
        $this->description = $this->get_option('description', __('پیشنهاد و شرایط اقساط از سرویس مرکزی دریافت می‌شوند.', 'proma-pay-woocommerce'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'proma-pay-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Proma Pay installments', 'proma-pay-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'proma-pay-woocommerce'),
                'type' => 'text',
                'default' => __('خرید اقساطی پروما پی', 'proma-pay-woocommerce'),
            ],
            'description' => [
                'title' => __('Description', 'proma-pay-woocommerce'),
                'type' => 'textarea',
                'default' => __('پیشنهاد مالی از سرویس مرکزی دریافت می‌شود و پیش‌پرداخت با درگاه بانکی فروشگاه پرداخت خواهد شد.', 'proma-pay-woocommerce'),
            ],
            'product_widget_enabled' => [
                'title' => __('Product page estimate', 'proma-pay-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Show the live installment estimate on eligible product pages', 'proma-pay-woocommerce'),
                'default' => 'yes',
            ],
        ];
    }

    public function is_available(): bool
    {
        if (!parent::is_available()) {
            return false;
        }
        foreach ((new PromaPay_WC_Connection_Repository())->enabled() as $connection) {
            if ((string) ($connection['prepayment_gateway_id'] ?? '') !== ''
                && PromaPay_WC_Payment_Adapter::gateway((string) $connection['prepayment_gateway_id']) !== null) {
                return true;
            }
        }
        return false;
    }

    public function payment_fields(): void
    {
        if ($this->description !== '') {
            echo wpautop(wp_kses_post($this->description));
        }
        $connections = [];
        foreach ((new PromaPay_WC_Connection_Repository())->enabled() as $connection) {
            if (PromaPay_WC_Payment_Adapter::gateway((string) ($connection['prepayment_gateway_id'] ?? '')) !== null) {
                $connections[] = $connection;
            }
        }
        if (!$connections) {
            echo '<p>' . esc_html__('هیچ اتصال یا درگاه پیش‌پرداخت سازگاری فعال نیست.', 'proma-pay-woocommerce') . '</p>';
            return;
        }
        $default = '';
        foreach ($connections as $connection) {
            if ((int) $connection['is_default'] === 1) {
                $default = (string) $connection['connection_uuid'];
                break;
            }
        }
        if ($default === '') {
            $default = (string) $connections[0]['connection_uuid'];
        }
        echo '<fieldset class="promapaywc-checkout" dir="rtl"><p><label for="promapaywc-connection">'
            . esc_html__('سرویس اقساطی', 'proma-pay-woocommerce') . '</label><select id="promapaywc-connection" name="promapaywc_connection_uuid" required>';
        foreach ($connections as $connection) {
            echo '<option value="' . esc_attr((string) $connection['connection_uuid']) . '" '
                . selected($default, (string) $connection['connection_uuid'], false) . '>'
                . esc_html((string) $connection['display_name']) . '</option>';
        }
        echo '</select></p><p><label for="promapaywc-down-payment">' . esc_html__('مبلغ پیش‌پرداخت (تومان)', 'proma-pay-woocommerce')
            . '</label><input id="promapaywc-down-payment" type="number" name="promapaywc_down_payment_toman" min="1" step="1" inputmode="numeric" required></p>';
        echo '<p><label for="promapaywc-months">' . esc_html__('تعداد اقساط ماهانه', 'proma-pay-woocommerce')
            . '</label><input id="promapaywc-months" type="number" name="promapaywc_months" min="1" max="480" step="1" value="6" required></p>';
        echo '<p><label for="promapaywc-interest-method">' . esc_html__('روش محاسبه سود', 'proma-pay-woocommerce') . '</label><select id="promapaywc-interest-method" name="promapaywc_interest_method" required>';
        foreach (['flat' => __('ساده', 'proma-pay-woocommerce'), 'compound' => __('مرکب', 'proma-pay-woocommerce'), 'reducing_balance' => __('مانده‌کاهشی', 'proma-pay-woocommerce')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></p><div class="promapaywc-quote" aria-live="polite"></div>';
        echo '<p><label><input type="checkbox" name="promapaywc_terms_accept" value="1" required> '
            . esc_html__('می‌پذیرم درخواست اقساط و شرایط اعلام‌شده برای بررسی به پروما پی ارسال شود.', 'proma-pay-woocommerce') . '</label></p>';
        echo '<p class="promapaywc-deposit-note">' . esc_html__('پیش‌پرداخت با درگاه بانکی انتخاب‌شده برای این سرویس، در یک سفارش پرداخت مستقل دریافت می‌شود. سفارش اصلی تا تأیید قرارداد و ضمانت، پرداخت‌شده محسوب نمی‌شود.', 'proma-pay-woocommerce') . '</p></fieldset>';
    }

    public function validate_fields(): bool
    {
        return true;
    }

    public function process_payment($orderId): array
    {
        return PromaPay_WC_Commerce::process_checkout((int) $orderId, $_POST);
    }

    public function process_refund($orderId, $amount = null, $reason = '')
    {
        $order = wc_get_order((int) $orderId);
        if (!$order instanceof WC_Order) {
            return new WP_Error('promapaywc_order_missing', __('The Proma Pay order was not found.', 'proma-pay-woocommerce'));
        }
        $childId = (int) $order->get_meta('_promapaywc_deposit_order_id', true);
        $gatewayId = (string) $order->get_meta('_promapaywc_prepayment_gateway_id', true);
        if ($childId < 1 || $gatewayId === '') {
            return new WP_Error('promapaywc_deposit_missing', __('No deposit payment order is available to refund.', 'proma-pay-woocommerce'));
        }
        $depositOrder = wc_get_order($childId);
        if (!$depositOrder instanceof WC_Order || !$depositOrder->is_paid()) {
            return new WP_Error('promapaywc_deposit_unpaid', __('The deposit order is not a completed payment.', 'proma-pay-woocommerce'));
        }
        $refundAmount = $amount === null ? (string) $depositOrder->get_total() : (string) $amount;
        if (wc_format_decimal($refundAmount, wc_get_price_decimals()) !== wc_format_decimal((string) $depositOrder->get_total(), wc_get_price_decimals())) {
            return new WP_Error('promapaywc_partial_refund_unsupported', __('Only a full deposit refund is supported; partial refunds would make the installment request inconsistent.', 'proma-pay-woocommerce'));
        }
        $remoteOrderUuid = (string) $order->get_meta('_promapaywc_remote_order_uuid', true);
        if ($remoteOrderUuid !== '') {
            $connectionUuid = (string) $order->get_meta('_promapaywc_connection_uuid', true);
            $connection = (new PromaPay_WC_Connection_Repository())->get($connectionUuid);
            if ($connection === null) {
                return new WP_Error('promapaywc_refund_sync_unavailable', __('The remote order could not be safely cancelled before refund.', 'proma-pay-woocommerce'));
            }
            $client = new PromaPay_WC_Api_Client(new PromaPay_WC_Connection_Repository());
            $key = 'wc-order-' . (int) $order->get_id() . '-cancel-v1';
            $cancel = $client->request_json($connection, 'POST', '/orders/' . strtolower($remoteOrderUuid) . '/cancel',
                ['reason_code' => 'deposit_refunded'], $key);
            if (!$cancel['ok'] || (($cancel['data']['status'] ?? '') !== 'cancelled')) {
                return new WP_Error('promapaywc_refund_sync_failed', __('The remote request could not be cancelled; no deposit refund was started.', 'proma-pay-woocommerce'));
            }
            $order->add_order_note(__('Proma Pay pending request cancelled before deposit refund. Verify the refund with the deposit gateway.', 'proma-pay-woocommerce'));
            $order->update_meta_data('_promapaywc_remote_order_status', 'cancelled');
            $order->save();
        }
        return PromaPay_WC_Payment_Adapter::process_refund($gatewayId, $childId, $refundAmount, (string) $reason);
    }

    public function enqueue_assets(): void
    {
        if (!function_exists('is_checkout') || (!is_checkout() && !is_product())) {
            return;
        }
        wp_enqueue_style('promapaywc-commerce', PROMA_PAY_WC_URL . 'assets/css/commerce.css', [], PROMA_PAY_WC_VERSION);
        if (is_checkout()) {
            wp_enqueue_script('promapaywc-checkout', PROMA_PAY_WC_URL . 'assets/js/checkout.js', ['jquery'], PROMA_PAY_WC_VERSION, true);
            wp_localize_script('promapaywc-checkout', 'PromaPayWCCheckout', [
                'endpoint' => rest_url('promapaywc/v1/quote'),
                'nonce' => wp_create_nonce('wp_rest'),
                'currency' => get_woocommerce_currency(),
            ]);
        }
        if (is_product()) {
            wp_enqueue_script('promapaywc-product', PROMA_PAY_WC_URL . 'assets/js/product-widget.js', ['jquery'], PROMA_PAY_WC_VERSION, true);
            wp_localize_script('promapaywc-product', 'PromaPayWCProduct', [
                'endpoint' => rest_url('promapaywc/v1/quote'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }
}
