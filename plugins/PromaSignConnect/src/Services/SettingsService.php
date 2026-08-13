<?php
namespace Proma\Plugins\SignConnect\Services;
final class SettingsService
{
    public static function defaults(): array { return [
        'otp_login_enabled'=>'0','otp_length'=>'6','otp_ttl_seconds'=>'180','otp_resend_seconds'=>'60',
        'otp_max_attempts'=>'5','otp_hourly_limit'=>'5','otp_daily_limit'=>'15','otp_lock_seconds'=>'900',
        'otp_primary_provider'=>'ippanel','otp_fallback_provider'=>'smsir','otp_send_mode'=>'default_text',
        'otp_default_text'=>"کد ورود شما به سامانه پرما پی:\n{otp}\n\nاعتبار این کد:\n{expiry_minutes} دقیقه\n\nاین کد را در اختیار دیگران قرار ندهید.",
        'otp_pattern_id'=>'','otp_variables'=>'otp,expiry_minutes',
        'provider_priority'=>'ippanel,smsir,bale,in_app','provider_ippanel_enabled'=>'0','provider_smsir_enabled'=>'0',
        'provider_bale_enabled'=>'0','provider_telegram_enabled'=>'0','provider_in_app_enabled'=>'1',
        'provider_ippanel_capabilities'=>'','provider_smsir_capabilities'=>'',
        'provider_bale_capabilities'=>'','provider_telegram_capabilities'=>'',
        'ippanel_from_number'=>'','ippanel_connect_timeout'=>'5','ippanel_timeout'=>'10','ippanel_retry_count'=>'3',
        'smsir_sender_line'=>'','smsir_connect_timeout'=>'5','smsir_timeout'=>'10','smsir_retry_count'=>'3',
        'telegram_webhook_secret'=>'','telegram_bot_id'=>'','telegram_bot_username'=>'','telegram_bot_name'=>'',
        'telegram_bot_validated_at'=>'','telegram_link_ttl'=>'600','telegram_parse_mode'=>'HTML',
        'signature_hash_algorithm'=>'sha256','signature_canonicalization'=>'json-c14n-v1','integrity_scan_enabled'=>'1'
    ]; }
    public function all(bool $includeSecrets = false): array
    {
        $settings = self::defaults();
        foreach (\Model::fetchAll('SELECT setting_key, setting_value, is_secret FROM proma_connect_settings') as $row) {
            $value = (string)$row['setting_value'];
            if ((int)$row['is_secret'] === 1) $value = $includeSecrets ? SecretCipher::decrypt($value) : ($value === '' ? '' : '••••••••');
            $settings[$row['setting_key']] = $value;
        }
        return $settings;
    }
    public function save(array $input, ?int $actorId): void
    {
        $allowed = array_keys(self::defaults());
        foreach (['ippanel_api_key','ippanel_from_number','smsir_api_key','telegram_bot_token'] as $key) $allowed[] = $key;
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input) || (str_ends_with($key, '_key') || str_ends_with($key, '_token')) && $input[$key] === '••••••••') continue;
            $secret = str_ends_with($key, '_key') || str_ends_with($key, '_token') || $key === 'telegram_webhook_secret';
            $value = (string)$input[$key];
            if ($secret && $value !== '') $value = SecretCipher::encrypt($value);
            \Model::execute('INSERT INTO proma_connect_settings (setting_key,setting_value,is_secret,updated_by,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_secret=VALUES(is_secret),updated_by=VALUES(updated_by),updated_at=NOW()', [$key,$value,$secret?1:0,$actorId]);
        }
    }

    public function saveSection(string $section, array $input, int $actorId): void
    {
        $schema = [
            'general' => ['provider_in_app_enabled','provider_priority'],
            'ippanel' => ['provider_ippanel_enabled','provider_ippanel_capabilities','ippanel_api_key','ippanel_from_number','ippanel_connect_timeout','ippanel_timeout','ippanel_retry_count'],
            'smsir' => ['provider_smsir_enabled','provider_smsir_capabilities','smsir_api_key','smsir_sender_line','smsir_connect_timeout','smsir_timeout','smsir_retry_count'],
            'telegram' => ['provider_telegram_enabled','provider_telegram_capabilities','telegram_link_ttl','telegram_parse_mode'],
            'otp' => ['otp_login_enabled','otp_length','otp_ttl_seconds','otp_resend_seconds','otp_max_attempts','otp_hourly_limit','otp_daily_limit','otp_lock_seconds','otp_primary_provider','otp_fallback_provider','otp_send_mode','otp_default_text','otp_pattern_id','otp_variables'],
            'signature' => ['signature_hash_algorithm','signature_canonicalization','integrity_scan_enabled'],
            'routing' => ['provider_priority'],
            'security' => ['integrity_scan_enabled'],
        ];
        if (!isset($schema[$section])) {
            throw new \InvalidArgumentException('این بخش تنظیمات قابل ذخیره عمومی نیست.');
        }
        $filtered = [];
        foreach ($schema[$section] as $key) if (array_key_exists($key, $input)) $filtered[$key] = $input[$key];
        if (in_array($section, ['ippanel','smsir','telegram'], true)) {
            $key = 'provider_' . $section . '_capabilities';
            $filtered[$key] = implode(',', ProviderCapabilityCatalog::normalize($section, $input['capabilities'] ?? []));
        }
        $this->validate($section, $filtered);
        $this->save($filtered, $actorId);
    }

    private function validate(string $section, array $values): void
    {
        foreach (['provider_ippanel_enabled','provider_smsir_enabled','provider_telegram_enabled','provider_in_app_enabled','otp_login_enabled','integrity_scan_enabled'] as $key) {
            if (isset($values[$key]) && !in_array((string)$values[$key], ['0','1'], true)) {
                throw new \InvalidArgumentException('مقدار وضعیت فعال‌سازی معتبر نیست.');
            }
        }
        $ranges = [
            'otp_length'=>[4,8], 'otp_ttl_seconds'=>[60,600], 'otp_resend_seconds'=>[30,3600],
            'otp_max_attempts'=>[3,10], 'otp_hourly_limit'=>[1,30], 'otp_daily_limit'=>[1,100],
            'otp_lock_seconds'=>[60,86400], 'ippanel_connect_timeout'=>[2,10],
            'ippanel_timeout'=>[3,30], 'ippanel_retry_count'=>[0,5],
            'smsir_connect_timeout'=>[2,10], 'smsir_timeout'=>[3,30], 'smsir_retry_count'=>[0,5],
            'telegram_link_ttl'=>[60,3600],
        ];
        foreach ($ranges as $key => [$min,$max]) {
            if (isset($values[$key]) && (!ctype_digit((string)$values[$key]) || (int)$values[$key] < $min || (int)$values[$key] > $max)) {
                throw new \InvalidArgumentException('مقدار عددی «' . $key . '» خارج از محدوده مجاز است.');
            }
        }
        if (isset($values['provider_priority'])) {
            $allowed = ['in_app','ippanel','smsir','bale','telegram'];
            foreach (array_filter(array_map('trim', explode(',', (string)$values['provider_priority']))) as $provider) {
                if (!in_array($provider, $allowed, true)) throw new \InvalidArgumentException('اولویت ارائه‌دهنده معتبر نیست.');
            }
        }
        if (isset($values['telegram_parse_mode']) && !in_array((string)$values['telegram_parse_mode'], ['HTML','MarkdownV2'], true)) {
            throw new \InvalidArgumentException('حالت قالب‌بندی تلگرام معتبر نیست.');
        }
        if ($section === 'otp') {
            $providers = ['ippanel','smsir','bale','telegram','in_app'];
            foreach (['otp_primary_provider','otp_fallback_provider'] as $key) {
                if (isset($values[$key]) && !in_array((string)$values[$key], $providers, true)) {
                    throw new \InvalidArgumentException('ارائه‌دهنده OTP معتبر نیست.');
                }
            }
            $modes = ['default_text','custom_text','ippanel_pattern','smsir_verify','bale_otp','telegram'];
            if (isset($values['otp_send_mode']) && !in_array((string)$values['otp_send_mode'], $modes, true)) {
                throw new \InvalidArgumentException('حالت ارسال OTP معتبر نیست.');
            }
            if (isset($values['otp_default_text']) && strpos((string)$values['otp_default_text'], '{otp}') === false) {
                throw new \InvalidArgumentException('متن OTP باید متغیر {otp} داشته باشد.');
            }
        }
    }
}
