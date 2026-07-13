<?php

namespace Proma\Plugins\Zarinpal\Services;

use Proma\Plugins\Zarinpal\DTO\PaymentRequestResult;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;
use Proma\Plugins\Zarinpal\Support\AmountConverter;

class ZarinpalRequestService
{
    private $settings;
    private $transactions;
    private $logs;
    private $clientFactory;

    public function __construct(ZarinpalSettingsService $settings = null, ZarinpalTransactionRepository $transactions = null, ZarinpalLogRepository $logs = null, callable $clientFactory = null)
    {
        $this->settings = $settings ?: new ZarinpalSettingsService();
        $this->transactions = $transactions ?: new ZarinpalTransactionRepository();
        $this->logs = $logs ?: new ZarinpalLogRepository();
        $this->clientFactory = $clientFactory;
    }

    public function create(array $context)
    {
        $config = $this->settings->all(true);
        if (($config['enabled'] ?? '0') !== '1') {
            throw new \InvalidArgumentException('درگاه زرین‌پال غیرفعال است.');
        }
        $environment = in_array(($config['environment'] ?? ''), ['production', 'sandbox'], true) ? $config['environment'] : 'production';
        $merchant = $this->settings->merchantFor($environment);
        if ($merchant === '') {
            throw new \InvalidArgumentException('Merchant ID محیط انتخاب‌شده تنظیم نشده است.');
        }
        $amountToman = (int) ($context['amount_toman'] ?? 0);
        $minimum = (int) ($config['minimum_amount_toman'] ?? 1);
        $maximum = (int) ($config['maximum_amount_toman'] ?? PHP_INT_MAX);
        if ($amountToman < $minimum || $amountToman > $maximum) {
            throw new \InvalidArgumentException('مبلغ پرداخت خارج از محدوده مجاز زرین‌پال است.');
        }
        $currency = strtoupper((string) ($config['currency'] ?? 'IRT'));
        $gatewayAmount = AmountConverter::toGateway($amountToman, $currency);
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            throw new \InvalidArgumentException('شناسه یکتای درخواست پرداخت معتبر نیست.');
        }
        $existing = $this->transactions->findByIdempotency($idempotencyKey);
        if ($existing) {
            if (($existing['status'] ?? '') === 'pending' && ZarinpalClient::validAuthority($existing['authority'] ?? '')) {
                $client = $this->client($config);
                return (new PaymentRequestResult(true, 'درخواست پرداخت قبلاً ساخته شده است.', [
                    'authority' => $existing['authority'],
                    'redirectUrl' => $client->startUrl($existing['environment'], $existing['authority']),
                    'transactionId' => (int) $existing['id'],
                    'code' => (int) ($existing['request_code'] ?? 100),
                ]))->toArray();
            }
            throw new \RuntimeException('این درخواست پرداخت قبلاً ثبت شده و وضعیت آن باید بررسی شود.');
        }

        $localOrderId = $this->localOrderId();
        $description = $this->description($config['description_template'] ?? '', $context);
        $paymentId = null;
        $groupId = null;
        \Model::begin();
        try {
            if (($context['type'] ?? 'single') === 'group') {
                $group = \PaymentGroupService::createPendingGateway(
                    (int) ($context['contract_id'] ?? 0),
                    (array) ($context['installment_ids'] ?? []),
                    $amountToman,
                    (int) ($context['customer_id'] ?? 0),
                    $localOrderId,
                    $idempotencyKey,
                    'zarinpal'
                );
                $groupId = (int) $group['id'];
            } else {
                $paymentId = \Payment::createPendingGatewayFor(
                    'zarinpal',
                    (int) ($context['installment_id'] ?? 0),
                    (int) ($context['contract_id'] ?? 0),
                    (int) ($context['customer_id'] ?? 0),
                    $amountToman,
                    $localOrderId
                );
            }
            $transaction = $this->transactions->create([
                'uuid' => $this->uuid(),
                'local_order_id' => $localOrderId,
                'idempotency_key' => $idempotencyKey,
                'customer_id' => (int) ($context['customer_id'] ?? 0),
                'contract_id' => (int) ($context['contract_id'] ?? 0),
                'installment_id' => (int) ($context['installment_id'] ?? 0),
                'payment_group_id' => $groupId,
                'payment_id' => $paymentId,
                'environment' => $environment,
                'merchant_fingerprint' => hash('sha256', $environment . '|' . strtolower($merchant)),
                'merchant_snapshot_encrypted' => $this->settings->encryptMerchantSnapshot($merchant),
                'internal_amount_toman' => $amountToman,
                'gateway_amount' => $gatewayAmount,
                'gateway_currency' => $currency,
                'description' => $description,
                'status' => 'created',
            ]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }

        $metadata = [];
        $mobile = preg_replace('/\D+/', '', \to_english_digits($context['customer_mobile'] ?? ''));
        if (($config['send_mobile'] ?? '1') === '1' && preg_match('/^09\d{9}$/', $mobile)) {
            $metadata['mobile'] = $mobile;
        }
        if (($config['send_email'] ?? '0') === '1' && filter_var(($context['customer_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $metadata['email'] = trim((string) $context['customer_email']);
        }
        if (($config['send_order_id'] ?? '1') === '1') {
            $metadata['order_id'] = $localOrderId;
        }
        $payload = [
            'merchant_id' => $merchant,
            'amount' => $gatewayAmount,
            'currency' => $currency,
            'description' => $description,
            'callback_url' => $this->settings->callbackUrl(),
        ];
        if ($metadata) {
            $payload['metadata'] = $metadata;
        }
        $client = $this->client($config);
        $response = $client->request($environment, $payload);
        if (empty($response['ok'])) {
            $uncertain = !empty($response['network_uncertain']);
            $status = $uncertain ? 'request_uncertain' : 'request_failed';
            $this->transactions->update($transaction['id'], [
                'status' => $status,
                'error_code' => $response['error_code'] ?? 'request_failed',
                'request_message' => $uncertain ? 'پاسخ قطعی از درگاه دریافت نشد.' : 'درخواست پرداخت توسط درگاه پذیرفته نشد.',
                'requested_at' => date('Y-m-d H:i:s'),
                'failed_at' => $uncertain ? null : date('Y-m-d H:i:s'),
            ]);
            if (!$uncertain) {
                $this->failCore($transaction, 'درخواست زرین‌پال پذیرفته نشد.');
            }
            $this->log($config, 'payment.request_failed', 'درخواست زرین‌پال ناموفق بود.', $transaction['id'], ['network_uncertain' => $uncertain, 'error_code' => $response['error_code'] ?? null], 'error');
            throw new \RuntimeException($uncertain ? 'پاسخ قطعی از زرین‌پال دریافت نشد. برای جلوگیری از پرداخت تکراری، وضعیت را از مدیریت تراکنش‌ها بررسی کنید.' : 'زرین‌پال درخواست پرداخت را نپذیرفت.');
        }
        $body = $response['body'];
        $code = ZarinpalErrorMapper::safeCode($body);
        $authority = trim((string) ($body['data']['authority'] ?? ''));
        if ($code !== 100 || !ZarinpalClient::validAuthorityForEnvironment($authority, $environment)) {
            $message = ZarinpalErrorMapper::customerMessage($code);
            $this->transactions->update($transaction['id'], ['status' => 'request_failed', 'request_code' => $code, 'request_message' => $message, 'error_code' => 'provider_' . $code, 'requested_at' => date('Y-m-d H:i:s'), 'failed_at' => date('Y-m-d H:i:s')]);
            $this->failCore($transaction, $message);
            $this->log($config, 'payment.request_rejected', 'درخواست توسط زرین‌پال رد شد.', $transaction['id'], ['code' => $code], 'warning', $code);
            throw new \RuntimeException($message);
        }
        $this->transactions->update($transaction['id'], [
            'authority' => $authority,
            'status' => 'pending',
            'request_code' => 100,
            'request_message' => 'درخواست پرداخت پذیرفته شد.',
            'fee' => max(0, (int) ($body['data']['fee'] ?? 0)),
            'fee_type' => mb_substr(strip_tags((string) ($body['data']['fee_type'] ?? '')), 0, 40, 'UTF-8') ?: null,
            'requested_at' => date('Y-m-d H:i:s'),
            'redirected_at' => date('Y-m-d H:i:s'),
        ]);
        $this->log($config, 'payment.authority_received', 'Authority زرین‌پال دریافت شد.', $transaction['id'], ['environment' => $environment, 'amount' => $gatewayAmount]);
        $redirect = $client->startUrl($environment, $authority);
        return (new PaymentRequestResult(true, 'درخواست پرداخت آماده است.', [
            'authority' => $authority,
            'redirectUrl' => $redirect,
            'transactionId' => (int) $transaction['id'],
            'code' => 100,
        ]))->toArray();
    }

    private function client(array $config)
    {
        if ($this->clientFactory) {
            return call_user_func($this->clientFactory, $config);
        }
        return new ZarinpalClient((int) ($config['connect_timeout'] ?? 10), (int) ($config['response_timeout'] ?? 30));
    }

    private function description($template, array $context)
    {
        $replacements = [
            '{{contract_number}}' => (string) ($context['contract_number'] ?? ''),
            '{{installment_number}}' => (string) ($context['installment_number'] ?? ''),
            '{{customer_name}}' => (string) ($context['customer_name'] ?? ''),
            '{{payment_group_number}}' => (string) ($context['payment_group_number'] ?? ''),
            '{{application_name}}' => (string) \Settings::get('system_name', 'پروما'),
        ];
        $description = trim(strip_tags(strtr((string) $template, $replacements)));
        return mb_substr(preg_replace('/\s+/u', ' ', $description), 0, 255, 'UTF-8');
    }

    private function localOrderId()
    {
        return 'ZP-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function uuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function failCore(array $transaction, $reason)
    {
        if (!empty($transaction['payment_group_id'])) {
            \PaymentGroupService::failGateway((int) $transaction['payment_group_id'], $reason);
        } else {
            \Payment::failGateway((string) $transaction['local_order_id'], $reason);
        }
    }

    private function log(array $config, $event, $message, $transactionId, array $context = [], $level = 'info', $code = null)
    {
        if (($config['technical_logging'] ?? '1') === '1') {
            $this->logs->record($event, $message, $transactionId, $context, $level, $code);
        }
    }
}
