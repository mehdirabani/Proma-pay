<?php

class SettingsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('settings/index', [
            'title' => 'تنظیمات',
            'settings' => Settings::allKeyed(),
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $values = [];
        foreach (Settings::defaults() as $key => $default) {
            if (array_key_exists($key, $_POST)) {
                $values[$key] = in_array($key, ['monthly_penalty_rate', 'monthly_reward_rate', 'contract_next_serial'], true)
                    ? to_english_digits($_POST[$key])
                    : trim((string) $_POST[$key]);
            }
        }
        Settings::saveMany($values);
        set_flash('success', 'تنظیمات ذخیره شد.');
        redirect('settings');
    }
}
