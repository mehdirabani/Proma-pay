<?php
namespace Proma\Plugins\SignConnect\Controllers;
use Proma\Plugins\SignConnect\Services\{SettingsService,TelegramLinkTokenService,SecretCipher,HttpClient};
final class TelegramController extends \Controller
{
    public function validate(): void
    {
        $this->onlyPost();
        $token = trim((string) ($_POST['bot_token'] ?? ''));
        if ($token === '') {
            \set_flash('error', 'توکن جدید الزامی است.');
            \redirect('plugin/sign-connect/settings/telegram');
        }
        try {
            $response = (new HttpClient())->request(
                'GET',
                'https://api.telegram.org/bot' . $token . '/getMe'
            );
            if (empty($response['body']['ok'])) throw new \RuntimeException('invalid_bot');
            $bot = $response['body']['result'];
            $settings = new SettingsService();
            $current = $settings->all(true);
            $tokenChanged = !hash_equals((string) ($current['telegram_bot_token'] ?? ''), $token);
            $updates = [
                'telegram_bot_token' => $token,
                'telegram_bot_id' => (string) $bot['id'],
                'telegram_bot_username' => (string) $bot['username'],
                'telegram_bot_name' => (string) ($bot['first_name'] ?? ''),
                'telegram_bot_validated_at' => date('Y-m-d H:i:s'),
                'telegram_webhook_secret' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            ];
            if ($tokenChanged) $updates['provider_telegram_enabled'] = '0';
            $settings->save($updates, \Auth::id());
            \set_flash(
                'success',
                $tokenChanged
                    ? 'توکن معتبر و ذخیره شد. ربات پس از تغییر توکن غیرفعال شد؛ تست ارسال موفق و فعال‌سازی دوباره لازم است.'
                    : 'توکن معتبر است و هویت ربات ذخیره شد.'
            );
        } catch (\Throwable $e) {
            \set_flash('error', 'توکن تلگرام معتبر نیست یا ارتباط برقرار نشد.');
        }
        \redirect('plugin/sign-connect/settings/telegram');
    }
    public function link(){$this->onlyPost();try{$s=(new SettingsService())->all(true);$result=(new TelegramLinkTokenService())->create((int)\Auth::id(),(string)\Auth::role(),(string)($s['telegram_bot_id']??''),(string)($s['telegram_bot_username']??''),(int)($s['telegram_link_ttl']??600));$_SESSION['proma_telegram_link']=$result;\set_flash('success','لینک یک‌بارمصرف اتصال تلگرام ساخته شد.');}catch(\Throwable$e){\set_flash('error',$e->getMessage());}\redirect('plugin/sign-connect/settings/telegram');}
    public function disconnect(){$this->onlyPost();\Model::execute("UPDATE proma_connect_telegram_links SET status='disconnected',disconnected_at=NOW(),disconnected_by=?,disconnect_reason='user_request' WHERE user_id=? AND status='active'",[\Auth::id(),\Auth::id()]);\set_flash('success','اتصال تلگرام قطع شد.');\redirect('plugin/sign-connect/settings/telegram');}
}
