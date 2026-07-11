<?php

class SettingsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        try {
            $importBatches = Model::fetchAll('SELECT * FROM import_batches ORDER BY id DESC LIMIT 20');
        } catch (Throwable $e) {
            $importBatches = [];
        }
        $this->render('settings/index', [
            'title' => 'تنظیمات',
            'settings' => Settings::allKeyed(),
            'activeTab' => preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab'] ?? 'general') ?: 'general',
            'backupLogs' => BackupService::logs(),
            'backupFiles' => BackupService::files(),
            'updatePackages' => ScriptUpdateService::packages(),
            'easyInstallPackages' => EasyInstallPackageService::packages(),
            'importBatches' => $importBatches,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $values = [];
        $digitFields = ['monthly_penalty_rate', 'legal_monthly_penalty_rate', 'late_penalty_grace_days', 'monthly_reward_rate', 'contract_next_serial', 'contract_year', 'company_representative_national_id', 'company_postal_code', 'company_phone', 'notifications_sound_volume', 'chat_file_auto_delete_days', 'card_transfer_card_number', 'card_transfer_account_number', 'smtp_port'];
        foreach (Settings::defaults() as $key => $default) {
            if (array_key_exists($key, $_POST)) {
                $rawValue = trim((string) $_POST[$key]);
                if (in_array($key, $digitFields, true)) {
                    $rawValue = to_english_digits($rawValue);
                }
                if ($key === 'zibal_merchant') {
                    $rawValue = preg_replace('/\s+/', '', $rawValue);
                } elseif ($key === 'callback_base_url') {
                    $rawValue = $this->normalizeBaseUrl($rawValue);
                } elseif (in_array($key, ['email_from_address', 'email_reply_to'], true)) {
                    $rawValue = $rawValue === '' || filter_var($rawValue, FILTER_VALIDATE_EMAIL) ? $rawValue : '';
                } elseif ($key === 'email_transport') {
                    $rawValue = in_array($rawValue, ['mail', 'smtp'], true) ? $rawValue : 'mail';
                } elseif ($key === 'smtp_encryption') {
                    $rawValue = in_array($rawValue, ['', 'tls', 'ssl'], true) ? $rawValue : 'tls';
                } elseif (strpos($key, 'social_') === 0) {
                    $rawValue = $this->normalizeSocialUrl($key, $rawValue);
                } elseif ($key === 'card_transfer_sheba') {
                    $rawValue = normalize_sheba($rawValue);
                } elseif ($key === 'card_transfer_card_number') {
                    $rawValue = normalize_card_number($rawValue);
                } elseif ($key === 'card_transfer_account_number') {
                    $rawValue = normalize_account_number($rawValue);
                }
                $values[$key] = $rawValue;
            }
        }
        foreach (['password_reset_enabled', 'calendar_notifications_enabled', 'calendar_notify_admin_without_user', 'calendar_due_day_repeat_enabled', 'notifications_sound_enabled', 'zibal_enabled', 'zibal_test_mode', 'card_transfer_enabled', 'card_transfer_show_sheba', 'card_transfer_show_account_number', 'email_enabled'] as $checkbox) {
            $values[$checkbox] = isset($_POST[$checkbox]) ? '1' : '0';
        }
        $values['notifications_sound_volume'] = (string) max(0, min(1, (float) ($values['notifications_sound_volume'] ?? '0.45')));
        $values['chat_file_auto_delete_days'] = (string) max(1, min(365, (int) ($values['chat_file_auto_delete_days'] ?? '7')));
        $values['late_penalty_grace_days'] = (string) max(0, min(365, (int) to_english_digits($values['late_penalty_grace_days'] ?? '0')));
        $values['card_transfer_primary_color'] = sanitize_hex_color($values['card_transfer_primary_color'] ?? '', '#7366ff');
        $values['card_transfer_secondary_color'] = sanitize_hex_color($values['card_transfer_secondary_color'] ?? '', '#16c7f9');
        $values['smtp_port'] = (string) max(1, min(65535, (int) to_english_digits($values['smtp_port'] ?? '587')));
        $currentSettings = Settings::allKeyed();
        if (array_key_exists('smtp_password', $values) && $values['smtp_password'] === '' && !empty($currentSettings['smtp_password'])) {
            unset($values['smtp_password']);
        }
        try {
            foreach (['logo_path' => 'logo_file', 'logo_icon_path' => 'logo_icon_file', 'favicon_path' => 'favicon_file'] as $settingKey => $fileKey) {
                if (!empty($_POST['delete_' . $settingKey])) {
                    if (!empty($currentSettings[$settingKey])) {
                        $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentSettings[$settingKey]);
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $values[$settingKey] = '';
                    continue;
                }
                if (!empty($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $extensions = $settingKey === 'favicon_path' ? UploadHelper::FAVICON_EXTENSIONS : UploadHelper::LOGO_EXTENSIONS;
                    $path = UploadHelper::storePublicImage($_FILES[$fileKey], 'logos', $extensions);
                    if ($path) {
                        if (!empty($currentSettings[$settingKey])) {
                            $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentSettings[$settingKey]);
                            if (is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $values[$settingKey] = $path;
                    }
                }
            }
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('settings', ['tab' => 'general']);
        }
        if (!array_key_exists($values['calendar_default_reminder_type'] ?? '', Event::reminderOptions()) || ($values['calendar_default_reminder_type'] ?? '') === '') {
            $values['calendar_default_reminder_type'] = '1_day';
        }
        if (empty($values['calendar_cron_token'])) {
            unset($values['calendar_cron_token']);
        }
        Settings::saveMany($values);
        try {
            Chat::ensureSchema();
        } catch (Throwable $e) {
        }
        set_flash('success', 'تنظیمات ذخیره شد.');
        $tab = preg_replace('/[^a-z0-9_-]/i', '', $_POST['_active_tab'] ?? 'general') ?: 'general';
        redirect('settings', ['tab' => $tab]);
    }

    protected function normalizeBaseUrl($value)
    {
        $value = rtrim(trim((string) $value), '/');
        if ($value === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    protected function normalizeSocialUrl($key, $value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
        }
        $username = ltrim($value, '@/ ');
        if ($username === '') {
            return '';
        }
        if ($key === 'social_whatsapp_url') {
            $digits = preg_replace('/\D+/', '', to_english_digits($value));
            return $digits !== '' ? 'https://wa.me/' . $digits : '';
        }
        if (preg_match('#[./]#', $username)) {
            $url = 'https://' . ltrim($value, '/');
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        }
        $baseMap = [
            'social_instagram_url' => 'https://instagram.com/',
            'social_telegram_url' => 'https://t.me/',
            'social_facebook_url' => 'https://facebook.com/',
            'social_x_url' => 'https://x.com/',
            'social_youtube_url' => 'https://youtube.com/',
            'social_linkedin_url' => 'https://linkedin.com/in/',
            'social_website_url' => 'https://',
        ];
        $url = ($baseMap[$key] ?? 'https://') . $username;
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    public function testEmail()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $to = trim((string) ($_POST['email_test_to'] ?? ''));
        $settings = Settings::allKeyed();
        foreach (Settings::defaults() as $key => $default) {
            if (array_key_exists($key, $_POST)) {
                $settings[$key] = trim((string) $_POST[$key]);
            }
        }
        foreach (['email_enabled'] as $checkbox) {
            $settings[$checkbox] = isset($_POST[$checkbox]) ? '1' : '0';
        }
        if (trim((string) ($settings['smtp_password'] ?? '')) === '') {
            $saved = Settings::allKeyed();
            $settings['smtp_password'] = $saved['smtp_password'] ?? '';
        }
        $result = EmailService::sendTest($to, $settings);
        set_flash(($result['ok'] ?? false) ? 'success' : 'error', $result['message'] ?? 'نتیجه تست ایمیل مشخص نیست.');
        redirect('settings', ['tab' => 'email']);
    }

    public function testAi()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $apiKey = trim((string) ($_POST['openrouter_api_key'] ?? ''));
        $model = trim((string) ($_POST['openrouter_model'] ?? ''));
        $client = new OpenRouterClient($apiKey, $model);
        $result = $client->testConnection();
        $this->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? ($result['error'] ?? 'خطا در تست اتصال'),
            'details' => $result['details'] ?? '',
            'type' => $result['type'] ?? '',
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function resetData()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('system_reset_data', $_POST['reset_confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف داده‌ها درست وارد نشده است.');
            }
            $password = (string) ($_POST['admin_password'] ?? '');
            $user = Auth::user();
            if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
                throw new RuntimeException('رمز عبور مدیر درست نیست.');
            }
            $result = SystemResetService::resetOperationalData(Auth::id());
            set_flash(
                'success',
                'داده‌های عملیاتی حذف شدند. بکاپ ایمنی: ' . $result['backup'] . '، جدول‌های پاک‌شده: ' . to_persian_digits(count($result['cleared_tables'])) . '.'
            );
        } catch (Throwable $e) {
            BackupService::log('data_reset', null, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'danger']);
    }
}
