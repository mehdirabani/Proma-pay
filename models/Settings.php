<?php

class Settings extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE settings MODIFY setting_value LONGTEXT NULL');
        } catch (Throwable $e) {
        }
        try {
            self::execute(
                "UPDATE settings
                 SET setting_value = 'پروما'
                 WHERE setting_key IN ('system_name', 'logo_text')
                 AND setting_value IN ('پرما پرداخت', 'پرما ابزار')"
            );
        } catch (Throwable $e) {
        }
        self::seedCalendarDefaults();
        self::$schemaReady = true;
    }

    public static function defaults()
    {
        $jalaliYear = substr(to_english_digits(jdate(date('Y-m-d'))), 0, 4) ?: '1404';
        return [
            'system_name' => 'پروما',
            'logo_text' => 'پروما',
            'logo_path' => '',
            'logo_icon_path' => '',
            'favicon_path' => '',
            'footer_text' => 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani',
            'company_name' => 'موبایل پروما',
            'company_representative_name' => '',
            'company_representative_national_id' => '',
            'company_address' => '',
            'company_postal_code' => '',
            'company_phone' => '',
            'contract_prefix' => 'PR',
            'contract_next_serial' => '1',
            'contract_year' => $jalaliYear,
            'contract_number_format' => 'PR-{SERIAL:6}',
            'contract_template_body' => '',
            'monthly_penalty_rate' => '2',
            'legal_monthly_penalty_rate' => '4',
            'late_penalty_grace_days' => '0',
            'contract_legal_penalty_clause' => 'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت یا ارجاع پرونده حقوقی، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ از زمان ورود قرارداد به مرحله حقوقی یا شکایت، جریمه دیرکرد با نرخ حقوقی ماهانه محاسبه خواهد شد.',
            'monthly_reward_rate' => '1',
            'zibal_enabled' => '1',
            'zibal_test_mode' => '0',
            'zibal_merchant' => '',
            'callback_base_url' => '',
            'card_transfer_enabled' => '1',
            'card_transfer_bank_name' => '',
            'card_transfer_bank_logo_text' => '',
            'card_transfer_account_name' => '',
            'card_transfer_card_number' => '',
            'card_transfer_sheba' => '',
            'card_transfer_account_number' => '',
            'card_transfer_primary_color' => '#7366ff',
            'card_transfer_secondary_color' => '#16c7f9',
            'card_transfer_show_sheba' => '1',
            'card_transfer_show_account_number' => '0',
            'card_transfer_qr_text' => '',
            'notifications_sound_enabled' => '1',
            'notifications_sound_volume' => '0.45',
            'chat_file_auto_delete_days' => '7',
            'password_reset_enabled' => '1',
            'ippanel_api_key' => '',
            'ippanel_from_number' => '',
            'ippanel_password_reset_pattern_code' => '',
            'ippanel_password_reset_pattern_key' => 'code',
            'openrouter_api_key' => '',
            'openrouter_model' => 'openai/gpt-4.1-mini',
            'calendar_notifications_enabled' => '1',
            'calendar_default_reminder_type' => '1_day',
            'calendar_notify_admin_without_user' => '1',
            'calendar_due_day_repeat_enabled' => '1',
            'calendar_cron_token' => '',
            'social_instagram_url' => '',
            'social_telegram_url' => '',
            'social_whatsapp_url' => '',
            'social_facebook_url' => '',
            'social_x_url' => '',
            'social_youtube_url' => '',
            'social_linkedin_url' => '',
            'social_website_url' => '',
        ];
    }

    public static function allKeyed()
    {
        self::ensureSchema();
        $settings = self::defaults();
        try {
            $rows = self::fetchAll('SELECT setting_key, setting_value FROM settings');
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            return $settings;
        }
        return $settings;
    }

    public static function get($key, $default = null)
    {
        $settings = self::allKeyed();
        return $settings[$key] ?? $default;
    }

    public static function set($key, $value, $secret = false)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret)',
            [$key, (string) $value, $secret ? 1 : 0]
        );
    }

    public static function saveMany(array $values)
    {
        foreach ($values as $key => $value) {
            self::set($key, $value, in_array($key, self::secretKeys(), true));
        }
    }

    public static function seedDefaults()
    {
        foreach (self::defaults() as $key => $value) {
            if ($key === 'calendar_cron_token' && $value === '') {
                $value = self::generateToken();
            }
            self::execute(
                'INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)',
                [$key, $value, in_array($key, self::secretKeys(), true) ? 1 : 0]
            );
        }
    }

    public static function secretKeys()
    {
        return ['openrouter_api_key', 'zibal_merchant', 'calendar_cron_token', 'ippanel_api_key'];
    }

    protected static function seedCalendarDefaults()
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }
        $seeded = true;
        $defaults = [
            'calendar_notifications_enabled' => '1',
            'calendar_default_reminder_type' => '1_day',
            'calendar_notify_admin_without_user' => '1',
            'calendar_due_day_repeat_enabled' => '1',
            'calendar_cron_token' => self::generateToken(),
        ];
        foreach ($defaults as $key => $value) {
            try {
                self::execute(
                    'INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)',
                    [$key, $value, $key === 'calendar_cron_token' ? 1 : 0]
                );
            } catch (Throwable $e) {
            }
        }
        try {
            self::execute(
                "UPDATE settings SET setting_value = ?, is_secret = 1 WHERE setting_key = 'calendar_cron_token' AND (setting_value IS NULL OR setting_value = '')",
                [self::generateToken()]
            );
        } catch (Throwable $e) {
        }
    }

    protected static function generateToken()
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (Throwable $e) {
            return hash('sha256', __DIR__ . microtime(true) . mt_rand());
        }
    }
}
