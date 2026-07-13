<?php

namespace Proma\Plugins\Zarinpal\Controllers;

use Proma\Plugins\Zarinpal\Services\ZarinpalClient;
use Proma\Plugins\Zarinpal\Services\ZarinpalGateway;
use Proma\Plugins\Zarinpal\Services\ZarinpalSettingsService;

class SandboxController extends \Controller
{
    public function index()
    {
        $settings = (new ZarinpalSettingsService())->all(false);
        $confirmationCode = (string) random_int(100000, 999999);
        $_SESSION['zarinpal_sandbox_confirmation'] = $confirmationCode;
        $this->render('plugin:proma-zarinpal/sandbox/index', [
            'title' => 'محیط آزمایشی زرین‌پال',
            'settings' => $settings,
            'confirmationCode' => $confirmationCode,
            'endpoints' => [
                'request' => ZarinpalClient::endpointFor('sandbox', 'request'),
                'verify' => ZarinpalClient::endpointFor('sandbox', 'verify'),
                'start' => ZarinpalClient::endpointFor('sandbox', 'start'),
            ],
        ], 'app');
    }

    public function create()
    {
        $this->onlyPost();
        \PluginManager::requirePermission('plugin.proma-zarinpal.use_zarinpal_sandbox');
        $expected = (string) ($_SESSION['zarinpal_sandbox_confirmation'] ?? '');
        unset($_SESSION['zarinpal_sandbox_confirmation']);
        if ($expected === '' || !hash_equals($expected, trim(\to_english_digits((string) ($_POST['confirmation'] ?? ''))))) {
            \set_flash('error', 'کد تأیید تراکنش آزمایشی صحیح نیست.');
            \redirect('plugin/zarinpal/sandbox');
        }
        try {
            $settings = (new ZarinpalSettingsService())->all(true);
            if (($settings['environment'] ?? '') !== 'sandbox' || ($settings['enabled'] ?? '0') !== '1') {
                throw new \InvalidArgumentException('برای ساخت تراکنش آزمایشی، درگاه باید در محیط sandbox فعال باشد.');
            }
            $installment = \Installment::find((int) ($_POST['installment_id'] ?? 0));
            if (!$installment || (float) ($installment['payable'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('قسط فعال و قابل پرداخت پیدا نشد.');
            }
            $amount = (int) \normalize_money($_POST['amount'] ?? $installment['payable']);
            if ($amount <= 0 || $amount > (int) round((float) $installment['payable'])) {
                throw new \InvalidArgumentException('مبلغ آزمایشی باید در محدوده مانده قابل پرداخت قسط باشد.');
            }
            $customer = \User::find((int) $installment['customer_id']);
            $result = (new ZarinpalGateway())->createPayment([
                'type' => 'single',
                'installment_id' => (int) $installment['id'],
                'installment_number' => (int) $installment['installment_number'],
                'contract_id' => (int) $installment['contract_id'],
                'contract_number' => (string) $installment['contract_number'],
                'customer_id' => (int) $installment['customer_id'],
                'customer_name' => (string) ($installment['customer_name'] ?? ''),
                'customer_mobile' => (string) ($customer['mobile'] ?? ''),
                'customer_email' => (string) ($customer['email'] ?? ''),
                'amount_toman' => $amount,
                'idempotency_key' => 'sandbox:admin:' . (int) \Auth::id() . ':' . bin2hex(random_bytes(24)),
            ]);
            if (empty($result['redirect_url'])) {
                throw new \RuntimeException('نشانی انتقال sandbox دریافت نشد.');
            }
            \redirect_raw($result['redirect_url']);
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage() : 'ساخت تراکنش آزمایشی انجام نشد.');
            \redirect('plugin/zarinpal/sandbox');
        }
    }
}
