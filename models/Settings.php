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
        self::$schemaReady = true;
    }

    public static function defaults()
    {
        $jalaliYear = substr(to_english_digits(jdate(date('Y-m-d'))), 0, 4) ?: '1404';
        return [
            'system_name' => 'پرما پرداخت',
            'logo_text' => 'پرما پرداخت',
            'footer_text' => 'پنل مدیریت مالی راست‌چین',
            'company_name' => 'موبایل پروما',
            'company_representative_name' => '',
            'company_representative_national_id' => '',
            'company_address' => '',
            'company_postal_code' => '',
            'company_phone' => '',
            'contract_prefix' => 'PR',
            'contract_next_serial' => '1001',
            'contract_year' => $jalaliYear,
            'contract_template_body' => '',
            'monthly_penalty_rate' => '2',
            'monthly_reward_rate' => '1',
            'zibal_merchant' => '',
            'callback_base_url' => '',
            'openrouter_api_key' => '',
            'openrouter_model' => 'openai/gpt-4.1-mini',
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
            self::set($key, $value, in_array($key, ['openrouter_api_key', 'zibal_merchant'], true));
        }
    }

    public static function seedDefaults()
    {
        foreach (self::defaults() as $key => $value) {
            self::execute(
                'INSERT IGNORE INTO settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)',
                [$key, $value, in_array($key, ['openrouter_api_key', 'zibal_merchant'], true) ? 1 : 0]
            );
        }
    }
}
