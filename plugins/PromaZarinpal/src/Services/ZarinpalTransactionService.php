<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\DTO\PaymentVerifyResult;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;

class ZarinpalTransactionService
{
    private $transactions;
    private $logs;
    private $settings;

    public function __construct(ZarinpalTransactionRepository $transactions = null, ZarinpalLogRepository $logs = null, ZarinpalSettingsService $settings = null)
    {
        $this->transactions = $transactions ?: new ZarinpalTransactionRepository();
        $this->logs = $logs ?: new ZarinpalLogRepository();
        $this->settings = $settings ?: new ZarinpalSettingsService();
    }

    public function finalize($transactionId, PaymentVerifyResult $verified)
    {
        \Model::begin();
        try {
            $transaction = $this->transactions->lock((int) $transactionId);
            if (!$transaction) {
                throw new \InvalidArgumentException('تراکنش زرین‌پال پیدا نشد.');
            }
            if (in_array(($transaction['status'] ?? ''), ['paid', 'sandbox_verified'], true)) {
                \Model::commit();
                return $this->result(
                    $transaction,
                    true,
                    ($transaction['status'] ?? '') === 'sandbox_verified'
                        ? 'پرداخت آزمایشی قبلاً تأیید شده و اثر مالی نداشته است.'
                        : 'این پرداخت قبلاً با موفقیت ثبت شده است.'
                );
            }
            if (($transaction['status'] ?? '') !== 'verifying') {
                throw new \InvalidArgumentException('وضعیت تراکنش برای ثبت نتیجه معتبر نیست.');
            }
            if (!$verified->ok || !in_array((int) $verified->code, [100, 101], true)) {
                throw new \InvalidArgumentException('تأیید معتبر زرین‌پال دریافت نشده است.');
            }
            $cardPan = $this->maskedCard($verified->cardPan);
            $verifiedValues = [
                'ref_id' => substr((string) $verified->referenceId, 0, 100),
                'card_pan_masked' => $cardPan ?: null,
                'card_hash' => $verified->cardHash ? substr((string) $verified->cardHash, 0, 191) : null,
                'fee' => max(0, (int) $verified->fee),
                'fee_type' => $verified->feeType ? substr((string) $verified->feeType, 0, 40) : null,
                'verify_code' => (int) $verified->code,
                'verify_message' => ZarinpalErrorMapper::customerMessage((int) $verified->code),
                'recovery_status' => (int) $verified->code === 101 ? 'already_verified_recovered' : null,
                'verified_at' => date('Y-m-d H:i:s'),
            ];
            $pluginSettings = $this->settings->all(true);
            $sandboxWithoutFinancialEffect = ($transaction['environment'] ?? '') === 'sandbox'
                && ($pluginSettings['sandbox_financial_effects'] ?? '0') !== '1';
            if ($sandboxWithoutFinancialEffect) {
                $this->failCore($transaction, 'تراکنش آزمایشی تأیید شد و اثر مالی ثبت نشد.');
                $this->transactions->update((int) $transaction['id'], $verifiedValues + ['status' => 'sandbox_verified']);
                if (class_exists('\\AuditLog')) {
                    \AuditLog::record('payment_gateway', 'zarinpal_sandbox_verified', 'zarinpal_transaction', (int) $transaction['id'], [
                        'actor_type' => 'gateway',
                        'customer_id' => (int) $transaction['customer_id'],
                        'contract_id' => (int) $transaction['contract_id'],
                        'new_values' => ['status' => 'sandbox_verified', 'financial_effect' => false, 'verify_code' => (int) $verified->code],
                    ]);
                }
                \Model::commit();
                $this->logs->record('payment.sandbox_verified', 'پرداخت sandbox بدون اثر مالی تأیید شد.', (int) $transaction['id'], ['verify_code' => (int) $verified->code]);
                $fresh = $this->transactions->find((int) $transaction['id']);
                return $this->result($fresh ?: $transaction, false, 'پرداخت آزمایشی تأیید شد و هیچ تغییری در مانده اقساط ایجاد نشد.');
            }

            $coreResult = null;
            if (!empty($transaction['payment_group_id'])) {
                $coreResult = \PaymentGroupService::completeGateway(
                    (int) $transaction['payment_group_id'],
                    (int) round((float) $transaction['internal_amount_toman']),
                    (string) $verified->referenceId,
                    (int) $transaction['customer_id']
                );
            } else {
                $coreResult = \Payment::completeGateway(
                    (string) $transaction['local_order_id'],
                    (string) $verified->referenceId,
                    (int) round((float) $transaction['internal_amount_toman']),
                    ['notify' => false]
                );
                if (empty($coreResult['ok'])) {
                    throw new \RuntimeException($coreResult['message'] ?? 'ثبت پرداخت در هسته انجام نشد.');
                }
            }
            $this->transactions->update((int) $transaction['id'], $verifiedValues + ['status' => 'paid']);
            if (class_exists('\\AuditLog')) {
                \AuditLog::record('payment_gateway', (int) $verified->code === 101 ? 'zarinpal_recovered' : 'zarinpal_verified', 'zarinpal_transaction', (int) $transaction['id'], [
                    'actor_type' => 'gateway',
                    'customer_id' => (int) $transaction['customer_id'],
                    'contract_id' => (int) $transaction['contract_id'],
                    'installment_id' => !empty($transaction['installment_id']) ? (int) $transaction['installment_id'] : null,
                    'new_values' => ['status' => 'paid', 'ref_id' => substr((string) $verified->referenceId, 0, 100), 'verify_code' => (int) $verified->code],
                ]);
            }
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }

        try {
            \Notification::create((int) $transaction['customer_id'], 'پرداخت زرین‌پال تأیید شد', 'پرداخت آنلاین شما با موفقیت ثبت شد.', 'payment', \url('installments/panel'));
        } catch (\Throwable $ignored) {
        }
        $this->logs->record('payment.finalized', 'پرداخت زرین‌پال در هسته نهایی شد.', (int) $transaction['id'], ['verify_code' => (int) $verified->code]);
        $fresh = $this->transactions->find((int) $transaction['id']);
        return $this->result($fresh ?: $transaction, false);
    }

    private function result(array $transaction, $alreadyPaid, $message = 'پرداخت با موفقیت انجام شد.')
    {
        return [
            'ok' => true,
            'status' => 'success',
            'message' => (string) $message,
            'already_paid' => (bool) $alreadyPaid,
            'transaction' => $transaction,
        ];
    }

    private function maskedCard($cardPan)
    {
        $card = preg_replace('/[^0-9*]/', '', (string) $cardPan);
        if ($card === '') {
            return '';
        }
        if (strpos($card, '*') === false && strlen($card) >= 10) {
            $card = substr($card, 0, 6) . str_repeat('*', max(4, strlen($card) - 10)) . substr($card, -4);
        }
        return substr($card, 0, 32);
    }

    private function failCore(array $transaction, $reason)
    {
        if (!empty($transaction['payment_group_id'])) {
            \PaymentGroupService::failGateway((int) $transaction['payment_group_id'], $reason);
            return;
        }
        \Payment::failGateway((string) $transaction['local_order_id'], $reason);
    }
}
