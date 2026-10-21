<?php

namespace Proma\Plugins\Zarinpal\Controllers;

use Proma\Plugins\Zarinpal\Services\ZarinpalSettingsService;

class SettingsController extends \Controller
{
    public function index()
    {
        $service = new ZarinpalSettingsService();
        $this->render('plugin:proma-zarinpal/settings/index', [
            'title' => 'تنظیمات درگاه زرین‌پال',
            'settings' => $service->all(false),
            'callbackUrl' => $service->callbackUrl(),
        ], 'app');
    }

    public function save()
    {
        $this->onlyPost();
        try {
            $before = (new ZarinpalSettingsService())->all(false);
            $after = (new ZarinpalSettingsService())->save($_POST, \Auth::id());
            \AuditLog::record('payment_gateway', 'zarinpal_settings_changed', 'plugin', 0, [
                'actor_user_id' => \Auth::id(),
                'description' => 'تنظیمات درگاه زرین‌پال تغییر کرد.',
                'old_values' => $this->safeAudit($before),
                'new_values' => $this->safeAudit($after),
            ]);
            \set_flash('success', 'تنظیمات زرین‌پال ذخیره شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'ذخیره تنظیمات زرین‌پال انجام نشد.');
        }
        \redirect('plugin/zarinpal/settings');
    }

    private function safeAudit(array $settings)
    {
        unset($settings['production_merchant_id'], $settings['sandbox_merchant_id']);
        return $settings;
    }
}
