<?php

class ZibalGatewayProvider implements PaymentGatewayProviderInterface
{
    public function getId()
    {
        return 'zibal';
    }

    public function getName()
    {
        return 'زیبال';
    }

    public function getDescription()
    {
        return 'پرداخت امن اقساط از درگاه زیبال';
    }

    public function isEnabled()
    {
        $settings = Settings::allKeyed();
        $testMode = (string) ($settings['zibal_test_mode'] ?? '0') === '1';
        return (string) ($settings['zibal_enabled'] ?? '1') === '1'
            && ($testMode || trim((string) ($settings['zibal_merchant'] ?? '')) !== '');
    }

    public function supportsSinglePayment()
    {
        return true;
    }

    public function supportsPaymentGroups()
    {
        return true;
    }

    public function createPayment(array $context)
    {
        if (!$this->isEnabled()) {
            throw new InvalidArgumentException('درگاه زیبال فعال یا تنظیم نشده است.');
        }
        $settings = Settings::allKeyed();
        $testMode = (string) ($settings['zibal_test_mode'] ?? '0') === '1';
        $base = rtrim((string) ($settings['callback_base_url'] ?: detected_base_url()), '/');
        $callback = $base . '/index.php?route=payments/callback';
        $client = new ZibalClient($settings['zibal_merchant'] ?? '', $testMode);
        $request = $client->request(
            (int) ($context['amount_toman'] ?? 0),
            $callback,
            trim((string) ($context['description'] ?? 'پرداخت اقساط'))
        );
        if (empty($request['ok'])) {
            throw new RuntimeException($request['message'] ?? 'درخواست پرداخت زیبال انجام نشد.');
        }
        if (($context['type'] ?? 'single') === 'group') {
            $group = PaymentGroupService::createPendingGateway(
                (int) ($context['contract_id'] ?? 0),
                (array) ($context['installment_ids'] ?? []),
                (int) ($context['amount_toman'] ?? 0),
                (int) ($context['customer_id'] ?? 0),
                (string) $request['track_id'],
                trim((string) ($context['idempotency_key'] ?? '')) ?: null,
                'zibal'
            );
            return ['ok' => true, 'redirect_url' => $request['start_url'], 'reference' => (string) $request['track_id'], 'payment_group_id' => (int) $group['id']];
        }
        $paymentId = Payment::createPendingGatewayFor(
            'zibal',
            (int) ($context['installment_id'] ?? 0),
            (int) ($context['contract_id'] ?? 0),
            (int) ($context['customer_id'] ?? 0),
            (int) ($context['amount_toman'] ?? 0),
            (string) $request['track_id']
        );
        return ['ok' => true, 'redirect_url' => $request['start_url'], 'reference' => (string) $request['track_id'], 'payment_id' => $paymentId];
    }

    public function getRedirectUrl($reference)
    {
        $reference = to_english_digits(trim((string) $reference));
        if (!preg_match('/^\d+$/', $reference)) {
            throw new InvalidArgumentException('شناسه انتقال زیبال معتبر نیست.');
        }
        return 'https://gateway.zibal.ir/start/' . $reference;
    }

    public function parseCallback(array $query)
    {
        return ['track_id' => to_english_digits($query['trackId'] ?? $query['trackid'] ?? '')];
    }

    public function verifyPayment(array $transaction)
    {
        $settings = Settings::allKeyed();
        $client = new ZibalClient($settings['zibal_merchant'] ?? '', (string) ($settings['zibal_test_mode'] ?? '0') === '1');
        return $client->verify($transaction['track_id'] ?? '');
    }

    public function healthCheck()
    {
        return $this->isEnabled();
    }

    public function getSettingsSchema()
    {
        return [];
    }

    public function getEnvironmentLabel()
    {
        return (string) Settings::get('zibal_test_mode', '0') === '1' ? 'آزمایشی' : 'عملیاتی';
    }
}
