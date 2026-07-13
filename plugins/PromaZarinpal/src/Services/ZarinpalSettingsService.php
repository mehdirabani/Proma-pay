<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\Support\SecretCipher;

class ZarinpalSettingsService
{
    private $cipher;

    public function __construct(SecretCipher $cipher = null)
    {
        $this->cipher = $cipher ?: new SecretCipher();
    }

    public static function defaults()
    {
        return [
            'enabled' => '0',
            'environment' => 'production',
            'production_merchant_id' => '',
            'sandbox_merchant_id' => '',
            'currency' => 'IRT',
            'gateway_title' => 'زرین‌پال',
            'description_template' => 'پرداخت اقساط قرارداد {{contract_number}}',
            'send_mobile' => '1',
            'send_email' => '0',
            'send_order_id' => '1',
            'is_default' => '0',
            'allow_customer_selection' => '1',
            'minimum_amount_toman' => '1000',
            'maximum_amount_toman' => '1000000000',
            'connect_timeout' => '10',
            'response_timeout' => '30',
            'callback_base_url' => '',
            'technical_logging' => '1',
            'show_technical_errors_admin' => '1',
            'sandbox_financial_effects' => '0',
        ];
    }

    public function all($includeSecrets = false)
    {
        $values = self::defaults();
        $rows = \Model::fetchAll('SELECT setting_key, setting_value, encrypted_value, is_secret FROM proma_zarinpal_settings');
        foreach ($rows as $row) {
            if ((int) $row['is_secret'] === 1) {
                if ($includeSecrets && !empty($row['encrypted_value'])) {
                    $values[$row['setting_key']] = $this->cipher->decrypt($row['encrypted_value']);
                }
                continue;
            }
            $values[$row['setting_key']] = (string) $row['setting_value'];
        }
        if (!$includeSecrets) {
            foreach (['production_merchant_id', 'sandbox_merchant_id'] as $key) {
                $secret = $this->secret($key);
                $values[$key] = $secret !== '' ? self::maskMerchant($secret) : '';
                $values[$key . '_configured'] = $secret !== '' ? '1' : '0';
            }
        }
        return $values;
    }

    public function save(array $input, $userId = null)
    {
        $current = $this->all(true);
        $values = [];
        $values['enabled'] = !empty($input['enabled']) ? '1' : '0';
        $values['environment'] = in_array(($input['environment'] ?? ''), ['production', 'sandbox'], true) ? $input['environment'] : 'production';
        $values['currency'] = in_array(strtoupper((string) ($input['currency'] ?? 'IRT')), ['IRT', 'IRR'], true) ? strtoupper((string) $input['currency']) : 'IRT';
        $values['gateway_title'] = mb_substr(strip_tags(trim((string) ($input['gateway_title'] ?? 'زرین‌پال'))), 0, 80, 'UTF-8');
        $values['description_template'] = $this->descriptionTemplate($input['description_template'] ?? self::defaults()['description_template']);
        foreach (['send_mobile', 'send_email', 'send_order_id', 'is_default', 'allow_customer_selection', 'technical_logging', 'show_technical_errors_admin', 'sandbox_financial_effects'] as $checkbox) {
            $values[$checkbox] = !empty($input[$checkbox]) ? '1' : '0';
        }
        if ($values['sandbox_financial_effects'] === '1' && !\PluginManager::can('plugin.proma-zarinpal.use_zarinpal_sandbox')) {
            throw new \InvalidArgumentException('مجوز فعال‌سازی اثر مالی محیط آزمایشی را ندارید.');
        }
        $values['minimum_amount_toman'] = (string) max(1, (int) \normalize_money($input['minimum_amount_toman'] ?? 1000));
        $values['maximum_amount_toman'] = (string) max((int) $values['minimum_amount_toman'], (int) \normalize_money($input['maximum_amount_toman'] ?? 1000000000));
        $values['connect_timeout'] = (string) max(3, min(30, (int) \to_english_digits($input['connect_timeout'] ?? 10)));
        $values['response_timeout'] = (string) max((int) $values['connect_timeout'], min(60, (int) \to_english_digits($input['response_timeout'] ?? 30)));
        $values['callback_base_url'] = $this->callbackBaseUrl($input['callback_base_url'] ?? '');

        $merchants = [];
        foreach (['production_merchant_id', 'sandbox_merchant_id'] as $key) {
            $candidate = preg_replace('/\s+/', '', trim((string) ($input[$key] ?? '')));
            if ($candidate === '' || strpos($candidate, '*') !== false) {
                $candidate = (string) ($current[$key] ?? '');
            }
            if ($candidate !== '' && !$this->validMerchant($candidate)) {
                throw new \InvalidArgumentException('Merchant ID زرین‌پال باید UUID معتبر ۳۶ کاراکتری باشد.');
            }
            $merchants[$key] = $candidate;
        }
        $activeMerchantKey = $values['environment'] === 'sandbox' ? 'sandbox_merchant_id' : 'production_merchant_id';
        if ($values['enabled'] === '1' && $merchants[$activeMerchantKey] === '') {
            throw new \InvalidArgumentException('برای فعال‌سازی زرین‌پال، Merchant ID محیط انتخاب‌شده الزامی است.');
        }

        \Model::begin();
        try {
            foreach ($values as $key => $value) {
                $this->upsert($key, $value, false, $userId);
            }
            foreach ($merchants as $key => $merchant) {
                if ($merchant !== '') {
                    $this->upsert($key, $this->cipher->encrypt($merchant), true, $userId);
                }
            }
            \Settings::set('payment_allow_gateway_selection', $values['allow_customer_selection']);
            if ($values['is_default'] === '1') {
                \Settings::set('payment_default_gateway', 'zarinpal');
            } elseif ((string) \Settings::get('payment_default_gateway', 'zibal') === 'zarinpal') {
                \Settings::set('payment_default_gateway', 'zibal');
            }
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        return $this->all(false);
    }

    public function isEnabled()
    {
        $settings = $this->all(true);
        return ($settings['enabled'] ?? '0') === '1' && $this->merchantFor($settings['environment'] ?? 'production') !== '';
    }

    public function merchantFor($environment)
    {
        $key = $environment === 'sandbox' ? 'sandbox_merchant_id' : 'production_merchant_id';
        return $this->secret($key);
    }

    public function encryptMerchantSnapshot($merchant)
    {
        return $this->cipher->encrypt((string) $merchant);
    }

    public function decryptMerchantSnapshot($encrypted)
    {
        return $this->cipher->decrypt((string) $encrypted);
    }

    public function callbackUrl()
    {
        $settings = $this->all(true);
        $base = rtrim((string) ($settings['callback_base_url'] ?: \detected_base_url()), '/');
        $this->assertCallbackHost($base);
        return $base . '/index.php?route=plugin/zarinpal/callback';
    }

    public static function maskMerchant($merchant)
    {
        $merchant = trim((string) $merchant);
        if ($merchant === '' || strlen($merchant) < 12) {
            return '';
        }
        return substr($merchant, 0, 8) . '-****-****-' . substr($merchant, -12);
    }

    private function secret($key)
    {
        $row = \Model::fetch('SELECT encrypted_value FROM proma_zarinpal_settings WHERE setting_key = ? AND is_secret = 1 LIMIT 1', [$key]);
        return $row && !empty($row['encrypted_value']) ? $this->cipher->decrypt($row['encrypted_value']) : '';
    }

    private function upsert($key, $value, $secret, $userId)
    {
        \Model::execute(
            'INSERT INTO proma_zarinpal_settings (setting_key, setting_value, encrypted_value, is_secret, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), encrypted_value = VALUES(encrypted_value),
             is_secret = VALUES(is_secret), updated_by = VALUES(updated_by), updated_at = NOW()',
            [$key, $secret ? null : (string) $value, $secret ? (string) $value : null, $secret ? 1 : 0, $userId ? (int) $userId : null]
        );
    }

    private function validMerchant($merchant)
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $merchant);
    }

    private function descriptionTemplate($template)
    {
        $template = trim(strip_tags((string) $template));
        $template = preg_replace('/\s+/u', ' ', $template);
        if ($template === '') {
            $template = self::defaults()['description_template'];
        }
        if (mb_strlen($template, 'UTF-8') > 255) {
            throw new \InvalidArgumentException('الگوی توضیح تراکنش حداکثر ۲۵۵ کاراکتر است.');
        }
        if (preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches)) {
            $allowed = ['contract_number', 'installment_number', 'customer_name', 'payment_group_number', 'application_name'];
            foreach ($matches[1] as $placeholder) {
                if (!in_array(trim((string) $placeholder), $allowed, true)) {
                    throw new \InvalidArgumentException('متغیر ناشناخته در الگوی توضیح تراکنش وجود دارد.');
                }
            }
        }
        return $template;
    }

    private function callbackBaseUrl($url)
    {
        $url = rtrim(trim((string) $url), '/');
        if ($url === '') {
            return '';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new \InvalidArgumentException('آدرس پایه Callback باید HTTPS معتبر باشد.');
        }
        $this->assertCallbackHost($url);
        return $url;
    }

    private function assertCallbackHost($url)
    {
        $configuredHost = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $applicationHost = strtolower((string) parse_url((string) \detected_base_url(), PHP_URL_HOST));
        if ($configuredHost === '' || $applicationHost === '' || !hash_equals($applicationHost, $configuredHost)) {
            throw new \InvalidArgumentException('دامنه Callback باید با دامنه فعلی سامانه یکسان باشد.');
        }
    }
}
