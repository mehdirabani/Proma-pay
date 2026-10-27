<?php

declare(strict_types=1);

/** Delegates a separately-priced deposit order to an enabled merchant gateway. */
final class PromaPay_WC_Payment_Adapter
{
    public static function available_gateways(): array
    {
        if (!function_exists('WC') || !WC() || !method_exists(WC(), 'payment_gateways')) {
            return [];
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (!is_array($gateways)) {
            return [];
        }
        $available = [];
        foreach ($gateways as $id => $gateway) {
            $id = (string) $id;
            if ($id === 'promapaywc_installments' || in_array($id, ['bacs', 'cheque', 'cod'], true)
                || !is_object($gateway) || !method_exists($gateway, 'process_payment')
                || (string) ($gateway->enabled ?? 'no') !== 'yes') {
                continue;
            }
            $available[$id] = (string) ($gateway->title ?? $id);
        }
        return $available;
    }

    public static function gateway(string $id)
    {
        if ($id === '' || $id === 'promapaywc_installments' || !function_exists('WC') || !WC()) {
            return null;
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = is_array($gateways) ? ($gateways[$id] ?? null) : null;
        if (!is_object($gateway) || !method_exists($gateway, 'process_payment')
            || (string) ($gateway->enabled ?? 'no') !== 'yes') {
            return null;
        }
        return $gateway;
    }

    public static function process_payment(string $gatewayId, int $depositOrderId): array
    {
        $gateway = self::gateway($gatewayId);
        $order = function_exists('wc_get_order') ? wc_get_order($depositOrderId) : null;
        if ($gateway === null || !is_object($order) || !method_exists($order, 'set_payment_method')) {
            return ['result' => 'failure'];
        }
        $order->set_payment_method($gateway);
        $order->save();
        try {
            $result = $gateway->process_payment($depositOrderId);
        } catch (Throwable $error) {
            return ['result' => 'failure'];
        }
        if (!is_array($result) || ($result['result'] ?? '') !== 'success') {
            return ['result' => 'failure'];
        }
        $redirect = (string) ($result['redirect'] ?? '');
        if ($redirect !== '') {
            $parts = wp_parse_url($redirect);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
                || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass'])) {
                return ['result' => 'failure'];
            }
            $redirect = esc_url_raw($redirect, ['http', 'https']);
            if ($redirect === '') {
                return ['result' => 'failure'];
            }
        }
        return ['result' => 'success', 'redirect' => $redirect];
    }

    public static function process_refund(string $gatewayId, int $depositOrderId, $amount, string $reason = '')
    {
        $gateway = self::gateway($gatewayId);
        $order = function_exists('wc_get_order') ? wc_get_order($depositOrderId) : null;
        if ($gateway === null || !is_object($order) || !method_exists($gateway, 'process_refund')) {
            return new WP_Error('promapaywc_refund_unsupported', __('The selected deposit gateway does not support automatic refunds.', 'proma-pay-woocommerce'));
        }
        try {
            return $gateway->process_refund($depositOrderId, $amount, sanitize_text_field($reason));
        } catch (Throwable $error) {
            return new WP_Error('promapaywc_refund_failed', __('The deposit gateway could not complete the refund.', 'proma-pay-woocommerce'));
        }
    }
}
