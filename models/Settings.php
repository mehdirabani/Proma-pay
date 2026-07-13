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
            'contract_document_title' => 'قرارداد اجاره به شرط تملیک / امانت‌داری',
            'contract_document_header' => "{{company_name}}\nشماره قرارداد: {{contract_number}} | تاریخ: {{contract_date}}\nامانت‌دار: {{customer_full_name}} | کد ملی: {{customer_national_id}}",
            'contract_template_body' => '',
            'contract_print_preset' => 'official_compact',
            'contract_print_page_size' => 'A4',
            'contract_print_orientation' => 'portrait',
            'contract_print_margin_top' => '5',
            'contract_print_margin_right' => '8',
            'contract_print_margin_bottom' => '7',
            'contract_print_margin_left' => '8',
            'contract_print_body_font_size' => '6',
            'contract_print_important_font_size' => '7',
            'contract_print_heading_font_size' => '7',
            'contract_print_body_line_height' => '1.22',
            'contract_print_important_line_height' => '1.22',
            'contract_print_heading_line_height' => '1.2',
            'contract_print_paragraph_spacing' => '1',
            'contract_print_section_top_spacing' => '2',
            'contract_print_section_bottom_spacing' => '1',
            'contract_print_list_spacing' => '1',
            'contract_print_list_indent' => '10',
            'contract_print_header_top_spacing' => '0',
            'contract_print_header_bottom_spacing' => '2',
            'contract_print_header_divider_spacing' => '2',
            'contract_print_logo_width' => '30',
            'contract_print_logo_height' => '11',
            'contract_print_table_font_size' => '5.5',
            'contract_print_table_heading_font_size' => '6',
            'contract_print_table_line_height' => '1.15',
            'contract_print_table_cell_vertical_padding' => '1',
            'contract_print_table_cell_horizontal_padding' => '2',
            'contract_print_table_margin' => '2',
            'contract_print_signature_top_spacing' => '6',
            'contract_print_signature_box_height' => '15',
            'contract_print_show_footer' => '0',
            'contract_print_show_customer_header' => '1',
            'contract_print_show_contract_title' => '1',
            'contract_print_color_mode' => 'color',
            'monthly_penalty_rate' => '10',
            'legal_monthly_penalty_rate' => '20',
            'late_penalty_grace_days' => '0',
            'contract_legal_penalty_clause' => 'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت یا ارجاع پرونده حقوقی، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ از زمان ورود قرارداد به مرحله حقوقی یا شکایت، جریمه دیرکرد با نرخ حقوقی ماهانه محاسبه خواهد شد.',
            'monthly_reward_rate' => '1',
            'zibal_enabled' => '1',
            'zibal_test_mode' => '0',
            'zibal_merchant' => '',
            'payment_default_gateway' => 'zibal',
            'payment_allow_gateway_selection' => '1',
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
            'ecommerce_enabled' => '1',
            'landing_enabled' => '1',
            'landing_kicker' => 'فروشگاه {{system_name}}',
            'landing_title' => 'خرید نقدی و اقساطی با {{system_name}}',
            'landing_subtitle' => 'محصولات منتخب را ببینید، سفارش نقدی ثبت کنید یا درخواست خرید اقساطی بفرستید.',
            'landing_primary_cta' => 'مشاهده محصولات',
            'landing_secondary_cta' => 'درخواست خرید اقساطی',
            'landing_featured_eyebrow' => 'پیشنهادهای فروشگاه',
            'landing_featured_title' => 'محصولات آماده خرید',
            'landing_steps_eyebrow' => 'مسیر خرید',
            'landing_steps_title' => 'از انتخاب محصول تا پیگیری سفارش',
            'email_enabled' => '0',
            'email_transport' => 'mail',
            'email_from_name' => 'پروما',
            'email_from_address' => '',
            'email_reply_to' => '',
            'email_header_note' => 'برخی توضیحات',
            'email_footer_address' => '',
            'email_order_subject' => 'سفارش {{order_number}} با موفقیت ثبت شد',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
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
        return ['openrouter_api_key', 'zibal_merchant', 'calendar_cron_token', 'ippanel_api_key', 'smtp_password'];
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
