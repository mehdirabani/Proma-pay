<?php

namespace Proma\Plugins\Zarinpal\Controllers;

use Proma\Plugins\Zarinpal\Services\ZarinpalCallbackService;

class CallbackController extends \Controller
{
    public function handle()
    {
        try {
            $result = (new ZarinpalCallbackService())->handle($_GET);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'status' => 'failure', 'message' => 'بررسی نتیجه پرداخت انجام نشد. لطفاً با پشتیبانی تماس بگیرید.', 'transaction' => null];
        }
        $this->render('plugin:proma-zarinpal/callback/result', [
            'title' => 'نتیجه پرداخت زرین‌پال',
            'result' => $result,
        ], null);
    }
}
