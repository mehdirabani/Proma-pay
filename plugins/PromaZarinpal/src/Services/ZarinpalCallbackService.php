<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\DTO\GatewayCallbackData;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;

class ZarinpalCallbackService
{
    private $transactions;
    private $logs;
    private $verification;

    public function __construct(ZarinpalTransactionRepository $transactions = null, ZarinpalLogRepository $logs = null, ZarinpalVerificationService $verification = null)
    {
        $this->transactions = $transactions ?: new ZarinpalTransactionRepository();
        $this->logs = $logs ?: new ZarinpalLogRepository();
        $this->verification = $verification ?: new ZarinpalVerificationService(null, $this->transactions, $this->logs);
    }

    public function handle(array $query)
    {
        foreach (array_keys($query) as $key) {
            if (!in_array((string) $key, ['route', 'Authority', 'Status'], true)) {
                return $this->failure('پارامتر بازگشت درگاه معتبر نیست.');
            }
        }
        $callback = new GatewayCallbackData($query['Authority'] ?? '', $query['Status'] ?? '');
        if (!ZarinpalClient::validAuthority($callback->authority) || !in_array($callback->status, ['OK', 'NOK'], true)) {
            return $this->failure('اطلاعات بازگشت زرین‌پال کامل یا معتبر نیست.');
        }
        $transaction = $this->transactions->findByAuthority($callback->authority);
        if (!$transaction) {
            return $this->failure('تراکنش زرین‌پال پیدا نشد.');
        }
        if (in_array(($transaction['status'] ?? ''), ['paid', 'sandbox_verified'], true)) {
            $this->logs->record('payment.callback_duplicate', 'Callback تکراری برای تراکنش نهایی‌شده دریافت شد.', (int) $transaction['id'], ['status' => $callback->status]);
            return [
                'ok' => true,
                'status' => 'success',
                'message' => ($transaction['status'] ?? '') === 'sandbox_verified'
                    ? 'پرداخت آزمایشی قبلاً تأیید شده و اثر مالی نداشته است.'
                    : 'این پرداخت قبلاً با موفقیت تأیید شده است.',
                'transaction' => $transaction,
            ];
        }
        $this->transactions->update((int) $transaction['id'], ['callback_status' => $callback->status, 'callback_at' => date('Y-m-d H:i:s')]);
        $this->logs->record('payment.callback_received', 'Callback زرین‌پال دریافت شد.', (int) $transaction['id'], ['status' => $callback->status]);
        if ($callback->status === 'NOK') {
            if (($transaction['status'] ?? '') !== 'paid') {
                $this->transactions->update((int) $transaction['id'], ['status' => 'cancelled_by_customer', 'failed_at' => date('Y-m-d H:i:s'), 'error_code' => 'customer_cancelled']);
                if (!empty($transaction['payment_group_id'])) {
                    \PaymentGroupService::failGateway((int) $transaction['payment_group_id'], 'پرداخت توسط مشتری لغو شد.');
                } else {
                    \Payment::failGateway((string) $transaction['local_order_id'], 'پرداخت توسط مشتری لغو شد.');
                }
            }
            return ['ok' => false, 'status' => 'failure', 'message' => 'پرداخت انجام نشد یا توسط کاربر لغو شد.', 'transaction' => $transaction];
        }
        if (($transaction['status'] ?? '') !== 'paid') {
            $this->transactions->update((int) $transaction['id'], ['status' => 'callback_received']);
        }
        return $this->verification->verify((int) $transaction['id']);
    }

    private function failure($message)
    {
        return ['ok' => false, 'status' => 'failure', 'message' => (string) $message, 'transaction' => null];
    }
}
