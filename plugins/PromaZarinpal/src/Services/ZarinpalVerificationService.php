<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\DTO\PaymentVerifyResult;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;

class ZarinpalVerificationService
{
    private $settings;
    private $transactions;
    private $logs;
    private $transactionService;
    private $clientFactory;

    public function __construct(ZarinpalSettingsService $settings = null, ZarinpalTransactionRepository $transactions = null, ZarinpalLogRepository $logs = null, ZarinpalTransactionService $transactionService = null, callable $clientFactory = null)
    {
        $this->settings = $settings ?: new ZarinpalSettingsService();
        $this->transactions = $transactions ?: new ZarinpalTransactionRepository();
        $this->logs = $logs ?: new ZarinpalLogRepository();
        $this->transactionService = $transactionService ?: new ZarinpalTransactionService($this->transactions, $this->logs);
        $this->clientFactory = $clientFactory;
    }

    public function verify($transactionId, $manualRetry = false)
    {
        $transaction = $this->transactions->find((int) $transactionId);
        if (!$transaction) {
            return $this->failure('تراکنش زرین‌پال پیدا نشد.');
        }
        if (in_array(($transaction['status'] ?? ''), ['paid', 'sandbox_verified'], true)) {
            return ['ok' => true, 'status' => 'success', 'message' => ($transaction['status'] ?? '') === 'sandbox_verified' ? 'پرداخت آزمایشی قبلاً تأیید شده و اثر مالی نداشته است.' : 'این پرداخت قبلاً تأیید شده است.', 'transaction' => $transaction];
        }
        if (($transaction['status'] ?? '') === 'verifying' && !$this->isStale($transaction)) {
            return ['ok' => false, 'status' => 'pending', 'message' => 'وضعیت پرداخت در حال بررسی است.', 'transaction' => $transaction];
        }
        $allowed = ['pending', 'callback_received', 'verification_failed', 'verifying'];
        if ($manualRetry) {
            $allowed[] = 'request_uncertain';
            $allowed[] = 'manual_review';
        }
        if (!in_array(($transaction['status'] ?? ''), $allowed, true)) {
            return $this->failure('این تراکنش در وضعیت قابل تأیید نیست.', $transaction);
        }
        \Model::begin();
        try {
            $locked = $this->transactions->lock((int) $transaction['id']);
            if (in_array(($locked['status'] ?? ''), ['paid', 'sandbox_verified'], true)) {
                \Model::commit();
                return ['ok' => true, 'status' => 'success', 'message' => ($locked['status'] ?? '') === 'sandbox_verified' ? 'پرداخت آزمایشی قبلاً تأیید شده و اثر مالی نداشته است.' : 'این پرداخت قبلاً تأیید شده است.', 'transaction' => $locked];
            }
            if (($locked['status'] ?? '') === 'verifying' && !$this->isStale($locked)) {
                \Model::commit();
                return ['ok' => false, 'status' => 'pending', 'message' => 'وضعیت پرداخت در حال بررسی است.', 'transaction' => $locked];
            }
            $this->transactions->update((int) $locked['id'], [
                'status' => 'verifying',
                'verifying_at' => date('Y-m-d H:i:s'),
                'retry_count' => (int) $locked['retry_count'] + ($manualRetry ? 1 : 0),
            ]);
            \Model::commit();
            $transaction = array_merge($locked, ['status' => 'verifying']);
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }

        $config = $this->settings->all(true);
        $merchant = $this->settings->decryptMerchantSnapshot($transaction['merchant_snapshot_encrypted']);
        if (!hash_equals((string) $transaction['merchant_fingerprint'], hash('sha256', $transaction['environment'] . '|' . strtolower($merchant)))) {
            return $this->verificationFailure($transaction, 0, 'merchant_snapshot_mismatch', 'تنظیمات تراکنش قابل اعتبارسنجی نیست.');
        }
        $client = $this->client($config);
        $response = $client->verify($transaction['environment'], [
            'merchant_id' => $merchant,
            'amount' => (int) $transaction['gateway_amount'],
            'authority' => (string) $transaction['authority'],
        ]);
        if (empty($response['ok'])) {
            $message = !empty($response['network_uncertain']) ? 'پاسخ قطعی تأیید از زرین‌پال دریافت نشد.' : 'ارتباط تأیید زرین‌پال ناموفق بود.';
            return $this->verificationFailure($transaction, 0, $response['error_code'] ?? 'verify_transport', $message, !empty($response['network_uncertain']));
        }
        $body = $response['body'];
        $code = ZarinpalErrorMapper::safeCode($body);
        if (!in_array($code, [100, 101], true)) {
            return $this->verificationFailure($transaction, $code, 'provider_' . $code, ZarinpalErrorMapper::customerMessage($code));
        }
        if (isset($body['data']['amount']) && (int) $body['data']['amount'] !== (int) $transaction['gateway_amount']) {
            return $this->verificationFailure($transaction, $code, 'amount_mismatch', 'مبلغ تأییدشده با مبلغ ذخیره‌شده یکسان نیست.');
        }
        $data = $body['data'] ?? [];
        $verified = new PaymentVerifyResult([
            'ok' => true,
            'code' => $code,
            'message' => ZarinpalErrorMapper::customerMessage($code),
            'referenceId' => (string) ($data['ref_id'] ?? ''),
            'cardPan' => (string) ($data['card_pan'] ?? ''),
            'cardHash' => (string) ($data['card_hash'] ?? ''),
            'fee' => (int) ($data['fee'] ?? 0),
            'feeType' => (string) ($data['fee_type'] ?? ''),
            'alreadyVerified' => $code === 101,
        ]);
        if ($verified->referenceId === '') {
            return $this->verificationFailure($transaction, $code, 'missing_ref_id', 'شناسه مرجع معتبر از زرین‌پال دریافت نشد.');
        }
        try {
            return $this->transactionService->finalize((int) $transaction['id'], $verified);
        } catch (\Throwable $e) {
            $this->transactions->update((int) $transaction['id'], ['status' => 'verification_failed', 'error_code' => 'core_finalize_failed', 'verify_code' => $code, 'verify_message' => 'ثبت مالی نتیجه درگاه انجام نشد.']);
            $this->logs->record('payment.finalize_failed', 'ثبت مالی نتیجه زرین‌پال انجام نشد.', (int) $transaction['id'], ['verify_code' => $code], 'error', $code);
            return $this->failure('تأیید درگاه دریافت شد اما ثبت مالی نیازمند بررسی مدیریت است.', $transaction);
        }
    }

    private function verificationFailure(array $transaction, $code, $errorCode, $message, $uncertain = false)
    {
        $this->transactions->update((int) $transaction['id'], [
            'status' => 'verification_failed',
            'verify_code' => (int) $code,
            'verify_message' => substr((string) $message, 0, 255),
            'error_code' => substr((string) $errorCode, 0, 80),
            'failed_at' => $uncertain ? null : date('Y-m-d H:i:s'),
        ]);
        $this->logs->record('payment.verification_failed', 'تأیید زرین‌پال ناموفق بود.', (int) $transaction['id'], ['code' => (int) $code, 'uncertain' => (bool) $uncertain], 'warning', $code);
        return $this->failure($message, $transaction);
    }

    private function client(array $config)
    {
        if ($this->clientFactory) {
            return call_user_func($this->clientFactory, $config);
        }
        return new ZarinpalClient((int) ($config['connect_timeout'] ?? 10), (int) ($config['response_timeout'] ?? 30));
    }

    private function isStale(array $transaction)
    {
        $timestamp = strtotime((string) ($transaction['verifying_at'] ?? $transaction['updated_at'] ?? ''));
        return !$timestamp || $timestamp < time() - 120;
    }

    private function failure($message, array $transaction = null)
    {
        return ['ok' => false, 'status' => 'failure', 'message' => (string) $message, 'transaction' => $transaction];
    }
}
