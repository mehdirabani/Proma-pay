<?php

class Settings extends Model
{
    public static function defaults()
    {
        return [
            'system_name' => 'پرما پرداخت',
            'logo_text' => 'پرما پرداخت',
            'footer_text' => 'پنل مدیریت مالی راست‌چین',
            'contract_prefix' => 'Pr',
            'contract_next_serial' => '1000',
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
