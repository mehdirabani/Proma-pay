<?php

namespace Proma\Plugins\SignConnect\Controllers;

use Proma\Plugins\SignConnect\Providers\BaleSafirProvider;
use Proma\Plugins\SignConnect\Services\BaleAccountService;
use Proma\Plugins\SignConnect\Services\IranianPhoneNumberNormalizer;
use Proma\Plugins\SignConnect\Services\ProviderEventMappingService;

final class BaleController extends \Controller
{
    public function save(): void
    {
        $this->onlyPost();
        try {
            $id = (new BaleAccountService())->save($_POST, (int) \Auth::id());
            \set_flash('success', 'تنظیمات بازوی بله ذخیره شد.');
            \redirect('plugin/sign-connect/settings/bale', ['edit' => $id]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \ErrorHandler::log('sign_connect.bale_settings_save', $e, 500);
            \set_flash('error', 'ذخیره تنظیمات انجام نشد. کد پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/bale');
    }

    public function permissions($accountId): void
    {
        $this->onlyPost();
        try {
            $count = (new ProviderEventMappingService())->saveForProviderAccount(
                'bale',
                (int) $accountId,
                is_array($_POST['events'] ?? null) ? $_POST['events'] : [],
                (int) \Auth::id()
            );
            \set_flash('success', $count . ' دسترسی رویداد برای این بازو ذخیره شد.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \ErrorHandler::log('sign_connect.bale_permissions_save', $e, 500);
            \set_flash('error', 'ذخیره دسترسی‌ها انجام نشد. کد پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/bale', ['edit' => (int) $accountId]);
    }

    public function test($accountId): void
    {
        $this->onlyPost();
        $service = new BaleAccountService();
        try {
            $account = $service->find((int) $accountId, true);
            if (!$account || empty($account['api_key'])) {
                throw new \RuntimeException('توکن وب‌سرویس بله ذخیره نشده است.');
            }
            $requestId = preg_match('/^[0-9a-f-]{36}$/i', (string) ($_POST['request_id'] ?? ''))
                ? (string) $_POST['request_id']
                : $this->uuid();
            $message = trim((string) ($_POST['test_message'] ?? 'این یک پیام آزمایشی از سامانه پرما پی است.'));
            if ($message === '' || mb_strlen($message, 'UTF-8') > 500) {
                throw new \InvalidArgumentException('متن آزمایشی باید بین ۱ تا ۵۰۰ نویسه باشد.');
            }
            $type = in_array((string) ($_POST['message_type'] ?? 'text'), ['text', 'secure'], true)
                ? (string) ($_POST['message_type'] ?? 'text')
                : 'text';
            $mobile = IranianPhoneNumberNormalizer::normalize((string) ($_POST['test_mobile'] ?? ''));
            $provider = new BaleSafirProvider([
                'api_key' => $account['api_key'],
                'bot_id' => $account['external_bot_id'],
                'base_url' => $account['api_base_url'],
            ]);
            $result = $provider->send([
                'type' => $type,
                'to' => $mobile,
                'text' => $message,
                'request_id' => $requestId,
            ]);
            \Model::execute(
                'INSERT INTO proma_connect_bale_attempts
                 (bale_account_id,attempt_uuid,request_id,bot_id,masked_recipient,message_type,message_id,status,
                  provider_error_name,is_retryable,duration_ms,attempted_at,completed_at,response_snapshot_redacted)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),?)',
                [
                    (int) $accountId,
                    $this->uuid(),
                    $requestId,
                    $account['external_bot_id'],
                    IranianPhoneNumberNormalizer::mask($mobile),
                    $type,
                    $result['external_id'] ?? null,
                    !empty($result['ok']) ? 'accepted' : 'failed',
                    $result['error']['name'] ?? null,
                    !empty($result['error']['retryable']) ? 1 : 0,
                    $result['duration_ms'] ?? null,
                    json_encode($result['response'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
            $service->markTest((int) $accountId, $result, (int) \Auth::id());
            \set_flash(
                !empty($result['ok']) ? 'success' : 'error',
                !empty($result['ok'])
                    ? 'پیام آزمایشی پذیرفته شد؛ اکنون می‌توانید بازو را فعال کنید.'
                    : $this->safeTestError($result)
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $service->markTest((int) $accountId, ['ok' => false, 'error' => 'test_failed'], (int) \Auth::id());
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \ErrorHandler::log('sign_connect.bale_test', $e, 500);
            $service->markTest((int) $accountId, ['ok' => false, 'error' => 'unexpected'], (int) \Auth::id());
            \set_flash('error', 'ارسال آزمایشی انجام نشد. کد پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/bale', ['edit' => (int) $accountId]);
    }

    public function state($accountId): void
    {
        $this->onlyPost();
        try {
            (new BaleAccountService())->setState((int) $accountId, (string) ($_POST['action'] ?? ''), (int) \Auth::id());
            \set_flash('success', 'وضعیت بازوی بله تغییر کرد.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \ErrorHandler::log('sign_connect.bale_state', $e, 500);
            \set_flash('error', 'تغییر وضعیت انجام نشد. کد پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/bale');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 15) | 64);
        $data[8] = chr((ord($data[8]) & 63) | 128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function safeTestError(array $result): string
    {
        $category = (string) ($result['error']['category'] ?? '');
        $messages = [
            'invalid_payload' => 'ساختار درخواست ارسال پیام معتبر نیست.',
            'invalid_recipient' => 'شماره آزمایشی معتبر نیست.',
            'channel_unavailable' => 'این شماره عضو بله نیست یا هنوز با بازو گفتگو نکرده است.',
            'provider_credit_problem' => 'اعتبار سرویس بله کافی نیست.',
            'provider_account_limit' => 'محدودیت حساب سرویس بله فعال شده است.',
            'provider_authentication_failed' => 'توکن یا دسترسی بازوی بله از سوی سرویس پذیرفته نشد؛ توکن و Bot ID را بررسی کنید.',
            'provider_access_denied' => 'این بازو اجازهٔ ارسال در سرویس بله ندارد.',
            'provider_endpoint_not_found' => 'نشانی API بله پیدا نشد؛ تنظیمات سرور و نسخهٔ API را بررسی کنید.',
            'retryable_after_delay' => 'محدودیت موقت ارسال سرویس بله فعال شده است؛ کمی بعد دوباره تلاش کنید.',
            'retryable' => 'ارتباط با سرویس بله موقتاً ناموفق بود؛ اتصال HTTPS خروجی سرور را بررسی کنید.',
        ];
        if (isset($messages[$category])) return $messages[$category];
        $name = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) ($result['error']['name'] ?? ''));
        return $name !== ''
            ? 'سرویس بله درخواست را نپذیرفت. کد سرویس: ' . $name
            : 'سرویس بله پیام آزمایشی را نپذیرفت.';
    }
}
