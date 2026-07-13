<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\Contracts\PaymentGatewayProviderInterface;

class ZarinpalGateway implements PaymentGatewayProviderInterface
{
    private $settings;
    private $requests;

    public function __construct(ZarinpalSettingsService $settings = null, ZarinpalRequestService $requests = null)
    {
        $this->settings = $settings ?: new ZarinpalSettingsService();
        $this->requests = $requests ?: new ZarinpalRequestService($this->settings);
    }

    public function getId() { return 'zarinpal'; }
    public function getName() { return 'زرین‌پال'; }
    public function getDescription() { return 'پرداخت امن اقساط از درگاه زرین‌پال'; }
    public function isEnabled() { return $this->settings->isEnabled(); }
    public function supportsSinglePayment() { return true; }
    public function supportsPaymentGroups() { return true; }
    public function createPayment(array $context) { return $this->requests->create($context); }

    public function getRedirectUrl($reference)
    {
        $environment = strtoupper(substr(trim((string) $reference), 0, 1)) === 'S' ? 'sandbox' : 'production';
        return (new ZarinpalClient())->startUrl($environment, $reference);
    }

    public function parseCallback(array $query)
    {
        return ['authority' => trim((string) ($query['Authority'] ?? '')), 'status' => strtoupper(trim((string) ($query['Status'] ?? '')))];
    }

    public function verifyPayment(array $transaction)
    {
        return (new ZarinpalVerificationService())->verify((int) ($transaction['id'] ?? 0));
    }

    public function healthCheck()
    {
        try {
            \Model::fetch('SELECT id FROM proma_zarinpal_transactions LIMIT 1');
            \Model::fetch('SELECT id FROM proma_zarinpal_settings LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getSettingsSchema() { return ZarinpalSettingsService::defaults(); }

    public function getEnvironmentLabel()
    {
        $settings = $this->settings->all(true);
        return ($settings['environment'] ?? 'production') === 'sandbox' ? 'آزمایشی' : 'عملیاتی';
    }
}
