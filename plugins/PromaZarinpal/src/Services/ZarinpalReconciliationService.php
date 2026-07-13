<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;

class ZarinpalReconciliationService
{
    private $transactions;
    private $verification;

    public function __construct(ZarinpalTransactionRepository $transactions = null, ZarinpalVerificationService $verification = null)
    {
        $this->transactions = $transactions ?: new ZarinpalTransactionRepository();
        $this->verification = $verification ?: new ZarinpalVerificationService(null, $this->transactions);
    }

    public function retry($transactionId)
    {
        $transaction = $this->transactions->find((int) $transactionId);
        if (!$transaction || !ZarinpalClient::validAuthority($transaction['authority'] ?? '')) {
            throw new \InvalidArgumentException('این تراکنش Authority معتبر برای بررسی مجدد ندارد.');
        }
        if ((int) ($transaction['retry_count'] ?? 0) >= 5) {
            throw new \InvalidArgumentException('حداکثر تعداد بررسی مجدد این تراکنش انجام شده است.');
        }
        return $this->verification->verify((int) $transactionId, true);
    }
}
