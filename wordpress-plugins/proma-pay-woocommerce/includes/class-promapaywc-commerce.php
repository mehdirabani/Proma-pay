<?php

declare(strict_types=1);

/** WooCommerce checkout, product estimate, deposit-order, and API reconciliation flow. */
final class PromaPay_WC_Commerce
{
    private const NAMESPACE = 'promapaywc/v1';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        PromaPay_WC_BakalaCompatibilityAdapter::register();
        add_action('woocommerce_payment_complete', [self::class, 'payment_complete'], 10, 1);
        add_action('promapaywc_sync_deposit', [self::class, 'sync_paid_deposit'], 10, 1);
        add_action('promapaywc_poll_order', [self::class, 'poll_remote_order'], 10, 1);
        add_action('woocommerce_product_options_general_product_data', [self::class, 'render_product_eligibility_field']);
        add_action('woocommerce_process_product_meta', [self::class, 'save_product_eligibility_field']);
        add_action('woocommerce_variation_options_pricing', [self::class, 'render_variation_eligibility_field'], 10, 3);
        add_action('woocommerce_save_product_variation', [self::class, 'save_variation_eligibility_field'], 10, 2);
        add_filter('woocommerce_payment_gateways', [self::class, 'add_gateway']);
        add_action('before_woocommerce_init', [self::class, 'declare_hpos_compatibility']);
        add_action('woocommerce_blocks_loaded', [self::class, 'register_blocks_payment_method']);
    }

    public static function add_gateway(array $gateways): array
    {
        $gateways[] = PromaPay_WC_Gateway::class;
        return $gateways;
    }

    public static function checkout_connections(): array
    {
        $options = [];
        foreach ((new PromaPay_WC_Connection_Repository())->enabled() as $connection) {
            $gateway = PromaPay_WC_Payment_Adapter::gateway((string) ($connection['prepayment_gateway_id'] ?? ''));
            if ($gateway !== null) {
                $options[] = [
                    'id' => (string) $connection['connection_uuid'],
                    'label' => (string) $connection['display_name'] . ' — ' . (string) ($gateway->title ?? $connection['prepayment_gateway_id']),
                ];
            }
        }
        return $options;
    }

    public static function widget_connection_uuid(): string
    {
        $connections = (new PromaPay_WC_Connection_Repository())->enabled();
        foreach ($connections as $connection) {
            if ((int) $connection['is_default'] === 1
                && PromaPay_WC_Payment_Adapter::gateway((string) ($connection['prepayment_gateway_id'] ?? '')) !== null) {
                return (string) $connection['connection_uuid'];
            }
        }
        foreach ($connections as $connection) {
            if (PromaPay_WC_Payment_Adapter::gateway((string) ($connection['prepayment_gateway_id'] ?? '')) !== null) {
                return (string) $connection['connection_uuid'];
            }
        }
        return '';
    }

    public static function product_widget_enabled(): bool
    {
        $settings = get_option('woocommerce_promapaywc_installments_settings', []);
        return is_array($settings)
            && ($settings['enabled'] ?? 'no') === 'yes'
            && ($settings['product_widget_enabled'] ?? 'yes') === 'yes';
    }

    public static function declare_hpos_compatibility(): void
    {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PROMA_PAY_WC_FILE, true);
        }
    }

    public static function register_blocks_payment_method(): void
    {
        if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\PaymentMethodRegistry')) {
            return;
        }
        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry): void {
            if (is_object($registry) && method_exists($registry, 'register')) {
                $registry->register(new PromaPay_WC_Blocks_Integration());
            }
        });
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/quote', [
            'methods' => 'POST',
            'callback' => [self::class, 'rest_quote'],
            'permission_callback' => '__return_true',
            'args' => [
                'purpose' => ['required' => true, 'type' => 'string'],
                'connection_uuid' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public static function rest_quote(WP_REST_Request $request)
    {
        if (!self::allow_public_quote()) {
            return new WP_Error('promapaywc_quote_rate_limited', __('Please wait before requesting another installment estimate.', 'proma-pay-woocommerce'), ['status' => 429]);
        }
        try {
            $purpose = sanitize_key((string) $request->get_param('purpose'));
            $connectionUuid = strtolower(sanitize_text_field((string) $request->get_param('connection_uuid')));
            if (!PromaPay_WC_Connection_Repository::valid_uuid($connectionUuid)) {
                throw new InvalidArgumentException('promapaywc_connection_invalid');
            }
            $repository = new PromaPay_WC_Connection_Repository();
            $connection = $repository->get($connectionUuid);
            if ($connection === null || (int) $connection['enabled'] !== 1) {
                throw new RuntimeException('promapaywc_connection_unavailable');
            }
            $currencyDivisor = (int) ($connection['currency_divisor'] ?? 1);
            $decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 0;
            $quoteService = new PromaPay_WC_Quote_Service($repository, new PromaPay_WC_Api_Client($repository));

            if ($purpose === 'product') {
                $productId = absint($request->get_param('product_id'));
                $variationId = absint($request->get_param('variation_id'));
                $quantity = max(1, min(99, absint($request->get_param('quantity') ?: 1)));
                if ($variationId < 1 && function_exists('wc_get_product')) {
                    $parentProduct = wc_get_product($productId);
                    if (is_object($parentProduct) && method_exists($parentProduct, 'is_type') && $parentProduct->is_type('variable')) {
                        throw new InvalidArgumentException('promapaywc_variation_selection_required');
                    }
                }
                $product = $variationId > 0 ? wc_get_product($variationId) : wc_get_product($productId);
                if (!self::eligible_product($product) || ($variationId > 0 && (int) $product->get_parent_id() !== $productId)) {
                    throw new InvalidArgumentException('promapaywc_product_not_eligible');
                }
                $unitToman = PromaPay_WC_Quote_Service::to_toman($product->get_price(), $currencyDivisor, $decimals);
                if ($unitToman > intdiv(PHP_INT_MAX, $quantity)) {
                    throw new OverflowException('promapaywc_product_amount_overflow');
                }
                $cashPrice = $unitToman * $quantity;
            } elseif ($purpose === 'checkout') {
                if (!function_exists('WC') || !WC() || !WC()->cart) {
                    throw new RuntimeException('promapaywc_cart_unavailable');
                }
                if (!self::eligible_cart(WC()->cart)) {
                    throw new InvalidArgumentException('promapaywc_cart_contains_ineligible_product');
                }
                WC()->cart->calculate_totals();
                $cashPrice = PromaPay_WC_Quote_Service::to_toman(WC()->cart->get_total('edit'), $currencyDivisor, $decimals);
            } else {
                throw new InvalidArgumentException('promapaywc_quote_purpose_invalid');
            }

            $policyResult = (new PromaPay_WC_Policy_Service($repository, new PromaPay_WC_Api_Client($repository)))->refresh($connectionUuid);
            $policy = $policyResult['policy'] ?? null;
            if (empty($policyResult['ok']) || !PromaPay_WC_Policy_Service::valid_policy($policy)) {
                throw new RuntimeException((string) ($policyResult['error_code'] ?? 'promapaywc_policy_unavailable'));
            }
            $limits = $policy['document']['limits'] ?? [];
            $minDown = max(
                (int) ($limits['min_down_payment_toman'] ?? 0),
                self::ceil_percent($cashPrice, (int) ($limits['min_down_payment_bps'] ?? 0))
            );
            $months = (int) ($limits['max_months'] ?? 6);
            $method = (string) ($policy['document']['methods'][0] ?? '');
            $downPayment = $purpose === 'checkout'
                ? self::posted_integer($request->get_param('down_payment_toman'))
                : $minDown;
            $months = $purpose === 'checkout'
                ? self::posted_integer($request->get_param('months'))
                : $months;
            $method = $purpose === 'checkout'
                ? sanitize_key((string) $request->get_param('interest_method'))
                : $method;
            $quote = $quoteService->quote($connectionUuid, $cashPrice, $downPayment, $months, $method);
            $gateway = PromaPay_WC_Payment_Adapter::gateway((string) $connection['prepayment_gateway_id']);
            $schedule = $quote['calculation']['installment_schedule'];
            return rest_ensure_response([
                'ok' => true,
                'quote_uuid' => $quote['quote_uuid'],
                'cash_price_toman' => $cashPrice,
                'down_payment_toman' => $downPayment,
                'total_installments_toman' => $quote['calculation']['total_installments'],
                'total_customer_payable_toman' => $quote['calculation']['total_customer_payable'],
                'installments' => array_map(static fn (array $row): array => [
                    'number' => $row['installment_number'],
                    'amount_toman' => $row['amount'],
                    'due_date' => $row['due_date'],
                ], $schedule),
                'first_installment_due_date' => $quote['calculation']['first_installment_due_date'],
                'interest_method' => $quote['calculation']['interest_method'],
                'monthly_rate' => $quote['calculation']['monthly_interest_rate'],
                'cheque_amount_rial' => $quote['guarantee']['calculation']['cheque_amount_rial'],
                'cheque_due_date' => $quote['guarantee']['calculation']['cheque_due_date'],
                'beneficiary_legal_name' => $quote['guarantee']['document']['beneficiary_legal_name'],
                'beneficiary_identifier' => $quote['guarantee']['document']['beneficiary_identifier'],
                'prepayment_gateway_title' => (string) ($gateway->title ?? $connection['prepayment_gateway_id']),
                'policy_version_uuid' => $quote['policy_version_uuid'],
                'expires_at' => $quote['expires_at'],
            ]);
        } catch (Throwable $error) {
            return new WP_Error('promapaywc_quote_unavailable', __('A current installment estimate is unavailable. Review the store settings or try again.', 'proma-pay-woocommerce'), ['status' => 422]);
        }
    }

    private static function allow_public_quote(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'promapaywc_quote_rl_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $count = (int) get_transient($key);
        if ($count >= 40) {
            return false;
        }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    public static function render_product_widget(): void
    {
        global $product;
        if (!self::product_widget_enabled() || !self::eligible_product($product) || self::widget_connection_uuid() === '') {
            return;
        }
        echo '<div class="promapaywc-product-widget" data-product-id="' . esc_attr((string) $product->get_id())
            . '" data-connection-uuid="' . esc_attr(self::widget_connection_uuid())
            . '" data-variable="' . esc_attr($product->is_type('variable') ? '1' : '0') . '" dir="rtl" aria-live="polite">'
            . '<strong>' . esc_html__('خرید اقساطی با پروما پی', 'proma-pay-woocommerce') . '</strong>'
            . '<span class="promapaywc-product-message">' . esc_html__('در حال دریافت پیشنهاد معتبر از پروما پی…', 'proma-pay-woocommerce') . '</span>'
            . '<details><summary>' . esc_html__('جزئیات طرح', 'proma-pay-woocommerce') . '</summary><div class="promapaywc-product-details"></div></details></div>';
    }

    public static function render_product_eligibility_field(): void
    {
        if (!function_exists('woocommerce_wp_checkbox')) {
            return;
        }
        woocommerce_wp_checkbox([
            'id' => '_promapaywc_installment_excluded',
            'label' => __('Exclude from Proma Pay installments', 'proma-pay-woocommerce'),
            'description' => __('Do not display or accept installment checkout for this product.', 'proma-pay-woocommerce'),
        ]);
    }

    public static function save_product_eligibility_field(int $productId): void
    {
        if (!current_user_can('edit_product', $productId)
            || !isset($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-product_' . $productId)) {
            return;
        }
        update_post_meta($productId, '_promapaywc_installment_excluded', isset($_POST['_promapaywc_installment_excluded']) ? 'yes' : 'no');
    }

    public static function render_variation_eligibility_field($loop, $variationData, $variation): void
    {
        if (!function_exists('woocommerce_wp_checkbox') || !is_object($variation)) {
            return;
        }
        woocommerce_wp_checkbox([
            'id' => '_promapaywc_installment_excluded[' . (int) $loop . ']',
            'name' => '_promapaywc_installment_excluded[' . (int) $loop . ']',
            'value' => get_post_meta((int) $variation->ID, '_promapaywc_installment_excluded', true),
            'label' => __('Exclude from Proma Pay installments', 'proma-pay-woocommerce'),
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    public static function save_variation_eligibility_field(int $variationId, int $index): void
    {
        if (!current_user_can('edit_product', $variationId)) {
            return;
        }
        $values = isset($_POST['_promapaywc_installment_excluded']) && is_array($_POST['_promapaywc_installment_excluded'])
            ? wp_unslash($_POST['_promapaywc_installment_excluded']) : [];
        update_post_meta($variationId, '_promapaywc_installment_excluded', isset($values[$index]) ? 'yes' : 'no');
    }

    public static function process_checkout(int $orderId, array $posted): array
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order || !self::eligible_order($order)) {
            wc_add_notice(__('One or more items in this order cannot use installment payment.', 'proma-pay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }
        if ((string) ($posted['promapaywc_terms_accept'] ?? '') !== '1') {
            wc_add_notice(__('Confirm the Proma Pay request terms before continuing.', 'proma-pay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }
        $connectionUuid = strtolower(sanitize_text_field(wp_unslash((string) ($posted['promapaywc_connection_uuid'] ?? ''))));
        $downPayment = self::posted_integer($posted['promapaywc_down_payment_toman'] ?? null);
        $months = self::posted_integer($posted['promapaywc_months'] ?? null);
        $method = sanitize_key(wp_unslash((string) ($posted['promapaywc_interest_method'] ?? '')));
        $repository = new PromaPay_WC_Connection_Repository();
        $connection = $repository->get($connectionUuid);
        if ($connection === null || (int) $connection['enabled'] !== 1) {
            wc_add_notice(__('The selected Proma Pay service is not available.', 'proma-pay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }
        $lock = 'promapaywc_order_lock_' . $orderId;
        if (!add_option($lock, time(), '', false)) {
            $created = (int) get_option($lock, 0);
            if ($created > time() - 180 || !delete_option($lock) || !add_option($lock, time(), '', false)) {
                wc_add_notice(__('This installment request is already being processed. Please wait and refresh the order.', 'proma-pay-woocommerce'), 'error');
                return ['result' => 'failure'];
            }
        }
        try {
            $cashPrice = PromaPay_WC_Quote_Service::to_toman(
                $order->get_total('edit'),
                (int) ($connection['currency_divisor'] ?? 1),
                (int) wc_get_price_decimals()
            );
            $quote = (new PromaPay_WC_Quote_Service($repository, new PromaPay_WC_Api_Client($repository)))
                ->quote($connectionUuid, $cashPrice, $downPayment, $months, $method);
            $existingChildId = (int) $order->get_meta('_promapaywc_deposit_order_id', true);
            if ($existingChildId > 0) {
                $savedPlan = [
                    (string) $order->get_meta('_promapaywc_connection_uuid', true),
                    (int) $order->get_meta('_promapaywc_cash_price_toman', true),
                    (int) $order->get_meta('_promapaywc_down_payment_toman', true),
                    (int) $order->get_meta('_promapaywc_months', true),
                    (string) $order->get_meta('_promapaywc_interest_method', true),
                ];
                if ($savedPlan !== [$connectionUuid, $cashPrice, $downPayment, $months, $method]) {
                    throw new RuntimeException('promapaywc_existing_payment_plan_conflict');
                }
                $child = wc_get_order($existingChildId);
                if (!$child instanceof WC_Order) {
                    throw new RuntimeException('promapaywc_deposit_order_missing');
                }
            } else {
                $depositAmount = PromaPay_WC_Quote_Service::from_toman(
                    $downPayment,
                    (int) ($connection['currency_divisor'] ?? 1),
                    (int) wc_get_price_decimals()
                );
                $child = self::create_deposit_order($order, $connection, $depositAmount, $downPayment, $quote);
                if (!$child instanceof WC_Order) {
                    throw new RuntimeException('promapaywc_deposit_order_create_failed');
                }
                $order->update_meta_data('_promapaywc_connection_uuid', $connectionUuid);
                $order->update_meta_data('_promapaywc_cash_price_toman', $cashPrice);
                $order->update_meta_data('_promapaywc_down_payment_toman', $downPayment);
                $order->update_meta_data('_promapaywc_months', $months);
                $order->update_meta_data('_promapaywc_interest_method', $method);
                $order->update_meta_data('_promapaywc_policy_version_uuid', $quote['policy_version_uuid']);
                $order->update_meta_data('_promapaywc_quote_uuid', $quote['quote_uuid']);
                $order->update_meta_data('_promapaywc_guarantee_policy_version_uuid', $quote['guarantee']['policy_version_uuid']);
                $order->update_meta_data('_promapaywc_quote_snapshot', wp_json_encode([
                    'quote_uuid' => $quote['quote_uuid'],
                    'policy_sha256' => $quote['policy_sha256'],
                    'total_installments_toman' => $quote['calculation']['total_installments'],
                    'first_installment_due_date' => $quote['calculation']['first_installment_due_date'],
                    'cheque_amount_rial' => $quote['guarantee']['calculation']['cheque_amount_rial'],
                    'cheque_due_date' => $quote['guarantee']['calculation']['cheque_due_date'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $order->update_meta_data('_promapaywc_customer_reference', 'wc-customer-' . strtolower(wp_generate_uuid4()));
                $order->update_meta_data('_promapaywc_prepayment_gateway_id', (string) $connection['prepayment_gateway_id']);
                $order->update_meta_data('_promapaywc_checkout_state', 'awaiting_deposit');
                $order->update_meta_data('_promapaywc_deposit_order_id', (int) $child->get_id());
                $order->save();
            }

            $depositId = (int) $child->get_id();
            if ($child->is_paid()) {
                $order->update_status('on-hold', __('Deposit payment was received. The contract and cheque guarantee await Proma Pay review.', 'proma-pay-woocommerce'));
                self::enqueue_sync($depositId, 0);
                return ['result' => 'success', 'redirect' => self::return_url($order)];
            }
            $order->update_status('on-hold', __('Awaiting payment of the separate installment deposit order.', 'proma-pay-woocommerce'));
            $payment = PromaPay_WC_Payment_Adapter::process_payment((string) $connection['prepayment_gateway_id'], $depositId);
            if (($payment['result'] ?? '') !== 'success') {
                $child->update_status('failed', __('The selected deposit gateway did not start payment.', 'proma-pay-woocommerce'));
                $order->update_status('pending', __('The deposit payment did not start. No contract request was sent.', 'proma-pay-woocommerce'));
                wc_add_notice(__('The selected bank gateway could not start the deposit payment.', 'proma-pay-woocommerce'), 'error');
                return ['result' => 'failure'];
            }
            return ['result' => 'success', 'redirect' => (string) ($payment['redirect'] ?: self::return_url($child))];
        } catch (Throwable $error) {
            wc_add_notice(__('The current Proma Pay quote or deposit order is unavailable. No installment request was submitted.', 'proma-pay-woocommerce'), 'error');
            return ['result' => 'failure'];
        } finally {
            delete_option($lock);
        }
    }

    private static function create_deposit_order(WC_Order $parent, array $connection, string $amount, int $downPaymentToman, array $quote): ?WC_Order
    {
        $child = wc_create_order([
            'customer_id' => (int) $parent->get_customer_id(),
            'created_via' => 'promapaywc_deposit',
            'status' => 'pending',
        ]);
        if (is_wp_error($child) || !$child instanceof WC_Order) {
            return null;
        }
        $child->set_currency($parent->get_currency());
        $child->set_address($parent->get_address('billing'), 'billing');
        $child->set_address($parent->get_address('shipping'), 'shipping');
        $child->set_billing_email($parent->get_billing_email());
        $child->set_billing_phone($parent->get_billing_phone());
        $child->set_parent_id((int) $parent->get_id());
        $child->set_payment_method((string) $connection['prepayment_gateway_id']);
        $child->set_payment_method_title((string) ($connection['prepayment_gateway_id']));
        $item = new WC_Order_Item_Fee();
        $item->set_name(__('Proma Pay installment deposit', 'proma-pay-woocommerce'));
        $item->set_amount($amount);
        $item->set_total($amount);
        $item->set_tax_status('none');
        $child->add_item($item);
        $child->calculate_totals(false);
        $child->update_meta_data('_promapaywc_role', 'deposit');
        $child->update_meta_data('_promapaywc_parent_order_id', (int) $parent->get_id());
        $child->update_meta_data('_promapaywc_connection_uuid', (string) $connection['connection_uuid']);
        $child->update_meta_data('_promapaywc_down_payment_toman', $downPaymentToman);
        $child->update_meta_data('_promapaywc_quote_uuid', (string) $quote['quote_uuid']);
        $child->save();
        return $child;
    }

    private static function return_url(WC_Order $order): string
    {
        if (function_exists('wc_get_checkout_order_received_url')) {
            return wc_get_checkout_order_received_url($order);
        }
        return function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
    }

    public static function payment_complete(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order && (string) $order->get_meta('_promapaywc_role', true) === 'deposit') {
            self::enqueue_sync($orderId, 0);
        }
    }

    private static function enqueue_sync(int $depositOrderId, int $delay): void
    {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + max(0, $delay), 'promapaywc_sync_deposit', [$depositOrderId], 'promapaywc', true);
        } else {
            wp_schedule_single_event(time() + max(5, $delay), 'promapaywc_sync_deposit', [$depositOrderId]);
        }
    }

    public static function sync_paid_deposit(int $depositOrderId): void
    {
        $deposit = wc_get_order((int) $depositOrderId);
        if (!$deposit instanceof WC_Order || (string) $deposit->get_meta('_promapaywc_role', true) !== 'deposit' || !$deposit->is_paid()) {
            return;
        }
        $parent = wc_get_order((int) $deposit->get_meta('_promapaywc_parent_order_id', true));
        if (!$parent instanceof WC_Order) {
            return;
        }
        $connectionUuid = (string) $parent->get_meta('_promapaywc_connection_uuid', true);
        $connection = (new PromaPay_WC_Connection_Repository())->get($connectionUuid);
        if ($connection === null) {
            self::sync_failed($parent, 'connection_not_found');
            return;
        }
        $client = new PromaPay_WC_Api_Client(new PromaPay_WC_Connection_Repository());
        $externalOrderId = self::external_order_id((int) $parent->get_id());
        $orderKey = 'wc-order-' . (int) $parent->get_id() . '-create-v1';
        $orderResponse = $client->request_json($connection, 'POST', '/orders', [
            'external_order_id' => $externalOrderId,
            'quote_uuid' => (string) $parent->get_meta('_promapaywc_quote_uuid', true),
            'cash_price_toman' => (int) $parent->get_meta('_promapaywc_cash_price_toman', true),
            'down_payment_toman' => (int) $parent->get_meta('_promapaywc_down_payment_toman', true),
            'months' => (int) $parent->get_meta('_promapaywc_months', true),
            'interest_method' => (string) $parent->get_meta('_promapaywc_interest_method', true),
        ], $orderKey);
        $orderUuid = (string) ($orderResponse['data']['order_uuid'] ?? '');
        if (!$orderResponse['ok'] || !PromaPay_WC_Connection_Repository::valid_uuid($orderUuid)) {
            self::sync_failed($parent, (string) $orderResponse['error_code']);
            return;
        }
        $parent->update_meta_data('_promapaywc_remote_order_uuid', $orderUuid);
        $parent->update_meta_data('_promapaywc_remote_order_status', (string) ($orderResponse['data']['status'] ?? 'pending_review'));
        $parent->update_meta_data('_promapaywc_sync_state', 'order_created');
        $parent->save();

        $contractKey = 'wc-order-' . (int) $parent->get_id() . '-contract-v1';
        $reference = (string) $parent->get_meta('_promapaywc_customer_reference', true);
        $contractResponse = $client->request_json($connection, 'POST', '/contracts', [
            'order_uuid' => $orderUuid,
            'external_customer_reference' => $reference,
        ], $contractKey);
        if (!$contractResponse['ok'] || !PromaPay_WC_Connection_Repository::valid_uuid((string) ($contractResponse['data']['contract_request_uuid'] ?? ''))) {
            self::sync_failed($parent, (string) $contractResponse['error_code']);
            return;
        }
        $parent->update_meta_data('_promapaywc_contract_request_uuid', (string) $contractResponse['data']['contract_request_uuid']);
        $parent->update_meta_data('_promapaywc_contract_request_status', (string) ($contractResponse['data']['status'] ?? 'pending_review'));
        $parent->update_meta_data('_promapaywc_sync_state', 'pending_review');
        $parent->update_meta_data('_promapaywc_sync_attempts', 0);
        $parent->add_order_note(__('Deposit payment was confirmed. The Proma Pay order and contract request await central review; the main order is not paid.', 'proma-pay-woocommerce'));
        $parent->save();
        $parent->update_status('on-hold');
        self::enqueue_poll((int) $parent->get_id(), 300);
    }

    private static function sync_failed(WC_Order $parent, string $errorCode): void
    {
        $attempts = (int) $parent->get_meta('_promapaywc_sync_attempts', true) + 1;
        $parent->update_meta_data('_promapaywc_sync_attempts', $attempts);
        $parent->update_meta_data('_promapaywc_sync_state', 'retry_pending');
        $parent->add_order_note(__('Proma Pay synchronization is pending retry. Safe error code: ', 'proma-pay-woocommerce') . sanitize_key($errorCode));
        $parent->save();
        if ($attempts <= 8) {
            $delay = min(900, 15 * (2 ** ($attempts - 1)));
            $depositId = (int) $parent->get_meta('_promapaywc_deposit_order_id', true);
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay, 'promapaywc_sync_deposit', [$depositId], 'promapaywc', true);
            } else {
                wp_schedule_single_event(time() + $delay, 'promapaywc_sync_deposit', [$depositId]);
            }
        }
    }

    private static function enqueue_poll(int $orderId, int $delay): void
    {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + $delay, 'promapaywc_poll_order', [$orderId], 'promapaywc', true);
        } else {
            wp_schedule_single_event(time() + $delay, 'promapaywc_poll_order', [$orderId]);
        }
    }

    public static function poll_remote_order(int $orderId): void
    {
        $order = wc_get_order((int) $orderId);
        if (!$order instanceof WC_Order || (string) $order->get_meta('_promapaywc_sync_state', true) !== 'pending_review') {
            return;
        }
        $attempt = (int) $order->get_meta('_promapaywc_status_poll_count', true) + 1;
        $orderUuid = (string) $order->get_meta('_promapaywc_remote_order_uuid', true);
        $connection = (new PromaPay_WC_Connection_Repository())->get((string) $order->get_meta('_promapaywc_connection_uuid', true));
        if ($connection === null || !PromaPay_WC_Connection_Repository::valid_uuid($orderUuid)) {
            return;
        }
        $client = new PromaPay_WC_Api_Client(new PromaPay_WC_Connection_Repository());
        $contract = $client->request($connection, 'GET', '/contracts/' . $orderUuid);
        $guarantee = $client->request($connection, 'GET', '/guarantees/' . $orderUuid);
        if ($contract['ok'] && is_array($contract['data']['contract'] ?? null)) {
            $status = $contract['data']['contract'];
            $order->update_meta_data('_promapaywc_contract_request_status', sanitize_key((string) ($status['contract_request_status'] ?? '')));
            $order->update_meta_data('_promapaywc_contract_status', sanitize_key((string) ($status['contract_status'] ?? '')));
        }
        if ($guarantee['ok'] && is_array($guarantee['data']['guarantee'] ?? null)) {
            $status = $guarantee['data']['guarantee'];
            $order->update_meta_data('_promapaywc_guarantee_status', sanitize_key((string) ($status['sayad_status'] ?? $status['status'] ?? '')));
            $order->update_meta_data('_promapaywc_guarantee_physical_status', sanitize_key((string) ($status['physical_status'] ?? '')));
            if (!empty($status['cheque_due_date'])) {
                $order->update_meta_data('_promapaywc_cheque_due_date', sanitize_text_field((string) $status['cheque_due_date']));
            }
        }
        $order->update_meta_data('_promapaywc_status_poll_count', $attempt);
        $order->save();
        $contractState = (string) $order->get_meta('_promapaywc_contract_request_status', true);
        $contractFinal = in_array($contractState, ['approved', 'active', 'rejected', 'cancelled', 'expired'], true);
        $guaranteeState = (string) $order->get_meta('_promapaywc_guarantee_status', true);
        $guaranteeFinal = in_array($guaranteeState, ['confirmed', 'rejected', 'cancelled', 'released'], true);
        $reviewComplete = in_array($contractState, ['rejected', 'cancelled', 'expired'], true)
            || (in_array($contractState, ['approved', 'active'], true) && $guaranteeFinal);
        if ($attempt < 120 && !$reviewComplete) {
            self::enqueue_poll($orderId, 300);
        }
    }

    private static function eligible_product($product): bool
    {
        if (!is_object($product) || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()
            || !method_exists($product, 'is_in_stock') || !$product->is_in_stock()) {
            return false;
        }
        $productId = (int) $product->get_id();
        if (get_post_meta($productId, '_promapaywc_installment_excluded', true) === 'yes') {
            return false;
        }
        $parentId = method_exists($product, 'get_parent_id') ? (int) $product->get_parent_id() : 0;
        return $parentId < 1 || get_post_meta($parentId, '_promapaywc_installment_excluded', true) !== 'yes';
    }

    private static function eligible_cart($cart): bool
    {
        $items = $cart->get_cart();
        if (!is_array($items) || !$items) {
            return false;
        }
        foreach ($items as $item) {
            if (!self::eligible_product($item['data'] ?? null) || (int) ($item['quantity'] ?? 0) < 1) {
                return false;
            }
        }
        return true;
    }

    private static function eligible_order(WC_Order $order): bool
    {
        foreach ($order->get_items('line_item') as $item) {
            if (!self::eligible_product($item->get_product())) {
                return false;
            }
        }
        return count($order->get_items('line_item')) > 0;
    }

    private static function posted_integer($value): int
    {
        if (is_array($value) || is_object($value) || !preg_match('/\A[1-9]\d{0,14}\z/D', trim((string) $value))) {
            throw new InvalidArgumentException('promapaywc_checkout_integer_invalid');
        }
        return (int) $value;
    }

    private static function ceil_percent(int $amount, int $basisPoints): int
    {
        if ($amount < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('promapaywc_policy_invalid');
        }
        $whole = intdiv($amount, 10000);
        $remainder = $amount % 10000;
        if ($basisPoints > 0 && $whole > intdiv(PHP_INT_MAX, $basisPoints)) {
            throw new OverflowException('promapaywc_policy_amount_overflow');
        }
        return ($whole * $basisPoints) + ($basisPoints === 0 ? 0 : intdiv(($remainder * $basisPoints) + 9999, 10000));
    }

    private static function external_order_id(int $orderId): string
    {
        return 'wc-' . substr(hash('sha256', home_url('/')), 0, 16) . '-' . $orderId;
    }
}
