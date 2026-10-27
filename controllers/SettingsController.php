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
        $webPushKeyReady = WebPushService::keysReady();
        try { $webPushSubscriberCount = WebPushService::subscriberCount(); } catch (Throwable $e) { $webPushSubscriberCount = 0; }
        $this->render('settings/index', [
            'title' => 'تنظیمات',
            'settings' => Settings::allKeyed(),
            'activeTab' => preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab'] ?? 'general') ?: 'general',
            'backupLogs' => BackupService::logs(),
            'backupFiles' => BackupService::files(),
            'updatePackages' => ScriptUpdateService::packages(),
            'updateUploadLimits' => ScriptUpdateService::uploadLimits(),
            'easyInstallPackages' => EasyInstallPackageService::packages(),
            'importBatches' => $importBatches,
            'webPushKeyReady' => $webPushKeyReady,
            'webPushSubscriberCount' => $webPushSubscriberCount,
            'collectionManagers' => User::all('admin', null, 'active'),
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $values = [];
        $digitFields = ['monthly_penalty_rate', 'legal_monthly_penalty_rate', 'late_penalty_grace_days', 'monthly_reward_rate', 'contract_next_serial', 'contract_year', 'company_representative_national_id', 'company_postal_code', 'company_phone', 'notifications_sound_volume', 'chat_file_auto_delete_days', 'card_transfer_card_number', 'card_transfer_account_number', 'smtp_port', 'legal_delay_value', 'legal_overdue_count_threshold', 'legal_overdue_amount_threshold', 'legal_warning_before_days', 'collection_assignment_manager_id'];
        foreach (Settings::defaults() as $key => $default) {
            if (array_key_exists($key, $_POST)) {
                $rawValue = trim((string) $_POST[$key]);
                if (in_array($key, $digitFields, true)) {
                    $rawValue = to_english_digits($rawValue);
                }
                if ($key === 'credit_risk_rules_json') {
                    try { $rawValue = $this->normalizeJsonRules($rawValue, CreditRiskEngine::defaultRules()); }
                    catch (InvalidArgumentException $e) { set_flash('error', $e->getMessage()); redirect('settings', ['tab' => 'credit']); }
                } elseif ($key === 'collection_stage_rules_json') {
                    try {
                        $rawValue = $this->normalizeJsonRules($rawValue, [
                            'reminder_min_days' => 1, 'reminder_max_days' => 3,
                            'operator_task_min_days' => 4, 'operator_task_max_days' => 10,
                            'serious_warning_min_days' => 11, 'serious_warning_max_days' => 20,
                            'legal_review_min_days' => 21,
                        ]);
                    } catch (InvalidArgumentException $e) { set_flash('error', $e->getMessage()); redirect('settings', ['tab' => 'credit']); }
                } elseif ($key === 'zibal_merchant') {
                    $rawValue = preg_replace('/\s+/', '', $rawValue);
                } elseif ($key === 'footer_text') {
                    $rawValue = trim(preg_replace('/\s+/u', ' ', strip_tags($rawValue)));
                    $rawValue = mb_substr($rawValue, 0, 180, 'UTF-8');
                    if ($rawValue === '') {
                        $rawValue = Settings::defaults()['footer_text'];
                    }
                } elseif ($key === 'callback_base_url') {
                    $rawValue = $this->normalizeBaseUrl($rawValue);
                } elseif (in_array($key, ['email_from_address', 'email_reply_to'], true)) {
                    $rawValue = $rawValue === '' || filter_var($rawValue, FILTER_VALIDATE_EMAIL) ? $rawValue : '';
                } elseif ($key === 'email_transport') {
                    $rawValue = in_array($rawValue, ['mail', 'smtp'], true) ? $rawValue : 'mail';
                } elseif ($key === 'smtp_encryption') {
                    $rawValue = in_array($rawValue, ['', 'tls', 'ssl'], true) ? $rawValue : 'tls';
                } elseif ($key === 'legal_delay_unit') {
                    $rawValue = in_array($rawValue, ['day', 'month'], true) ? $rawValue : 'day';
                } elseif ($key === 'legal_eligibility_operator') {
                    $rawValue = in_array($rawValue, ['delay_only', 'and', 'or'], true) ? $rawValue : 'delay_only';
                } elseif ($key === 'projected_legal_penalty_customer_message') {
                    $rawValue = trim(preg_replace('/\s+/u', ' ', strip_tags($rawValue)));
                    $rawValue = mb_substr($rawValue, 0, 280, 'UTF-8');
                    if ($rawValue === '') {
                        $rawValue = CustomerPenaltyPresentationService::DEFAULT_MESSAGE;
                    }
                } elseif (in_array($key, ['legal_contractual_warning_template', 'legal_petition_draft_template', 'legal_complaint_draft_template'], true)) {
                    $rawValue = trim(strip_tags($rawValue));
                    $rawValue = mb_substr($rawValue, 0, 12000, 'UTF-8');
                    if ($rawValue === '') {
                        $rawValue = Settings::defaults()[$key];
                    }
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
        foreach (['password_reset_enabled', 'calendar_notifications_enabled', 'calendar_notify_admin_without_user', 'calendar_due_day_repeat_enabled', 'notifications_sound_enabled', 'web_push_enabled', 'zibal_enabled', 'zibal_test_mode', 'card_transfer_enabled', 'card_transfer_show_sheba', 'card_transfer_show_account_number', 'email_enabled', 'legal_overdue_amount_enabled', 'legal_allow_self_initiation', 'show_projected_legal_penalty_to_customer', 'payment_legal_cost_priority'] as $checkbox) {
            $values[$checkbox] = isset($_POST[$checkbox]) ? '1' : '0';
        }
        $notificationTypes = $_POST['notification_event_types'] ?? [];
        if (!is_array($notificationTypes)) $notificationTypes = [];
        $notificationTypes = array_values(array_unique(array_filter(array_map('strval', $notificationTypes), static fn (string $type): bool => (bool) preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/i', $type))));
        $values['notification_event_types_json'] = json_encode($notificationTypes, JSON_UNESCAPED_UNICODE);
        $webPushTypes = $_POST['web_push_event_types'] ?? [];
        if (!is_array($webPushTypes)) $webPushTypes = [];
        $webPushTypes = array_values(array_unique(array_filter(array_map('strval', $webPushTypes), static fn (string $type): bool => (bool) preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/i', $type))));
        $values['web_push_event_types_json'] = json_encode($webPushTypes, JSON_UNESCAPED_UNICODE);
        $values['notifications_sound_volume'] = (string) max(0, min(1, (float) ($values['notifications_sound_volume'] ?? '0.45')));
        $values['chat_file_auto_delete_days'] = (string) max(1, min(365, (int) ($values['chat_file_auto_delete_days'] ?? '7')));
        $values['late_penalty_grace_days'] = (string) max(0, min(365, (int) to_english_digits($values['late_penalty_grace_days'] ?? '0')));
        $values['legal_delay_value'] = (string) max(1, min(3650, (int) to_english_digits($values['legal_delay_value'] ?? '30')));
        $values['legal_overdue_count_threshold'] = (string) max(1, min(1000, (int) to_english_digits($values['legal_overdue_count_threshold'] ?? '1')));
        $values['legal_overdue_amount_threshold'] = (string) max(0, normalize_money($values['legal_overdue_amount_threshold'] ?? '0'));
        $values['legal_warning_before_days'] = (string) max(0, min(365, (int) to_english_digits($values['legal_warning_before_days'] ?? '0')));
        $values['card_transfer_primary_color'] = sanitize_hex_color($values['card_transfer_primary_color'] ?? '', '#7366ff');
        $values['card_transfer_secondary_color'] = sanitize_hex_color($values['card_transfer_secondary_color'] ?? '', '#16c7f9');
        $values['smtp_port'] = (string) max(1, min(65535, (int) to_english_digits($values['smtp_port'] ?? '587')));
        $managerId = (int) to_english_digits($values['collection_assignment_manager_id'] ?? Settings::get('collection_assignment_manager_id', ''));
        if ($managerId > 0) {
            $manager = Model::fetch("SELECT id FROM users WHERE id=? AND role='admin' AND status='active' AND archived_at IS NULL LIMIT 1", [$managerId]);
            if (!$manager) {
                set_flash('error', 'مسئول صف تخصیص باید مدیر فعال باشد.');
                redirect('settings', ['tab' => 'credit']);
            }
            $values['collection_assignment_manager_id'] = (string) $managerId;
        } else {
            $values['collection_assignment_manager_id'] = '';
        }
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
        if (($values['web_push_enabled'] ?? ($currentSettings['web_push_enabled'] ?? '0')) === '1') {
            try {
                WebPushService::ensureKeys();
            } catch (Throwable $e) {
                set_flash('error', 'فعال‌سازی اعلان مرورگر انجام نشد: ' . $e->getMessage());
                redirect('settings', ['tab' => 'notifications']);
            }
        }
        $previousLegalPolicy = LegalEligibilityService::defaults($currentSettings);
        Settings::saveMany($values);
        $nextLegalPolicy = LegalEligibilityService::defaults(Settings::allKeyed());
        if ($previousLegalPolicy !== $nextLegalPolicy) {
            LegalEligibilityService::publishFromSettings(Auth::id());
        }
        try {
            Chat::ensureSchema();
        } catch (Throwable $e) {
        }
        set_flash('success', 'تنظیمات ذخیره شد.');
        $tab = preg_replace('/[^a-z0-9_-]/i', '', $_POST['_active_tab'] ?? 'general') ?: 'general';
        redirect('settings', ['tab' => $tab]);
    }

    private function normalizeJsonRules(string $rawValue, array $defaults): string
    {
        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded)) throw new InvalidArgumentException('ساختار قانون‌های تنظیمی باید JSON معتبر باشد.');
        $normalized = $defaults;
        foreach ($defaults as $key => $default) {
            if (!isset($decoded[$key]) || !is_numeric($decoded[$key])) continue;
            $normalized[$key] = max(0, min(1000, (int) to_english_digits((string) $decoded[$key])));
        }
        if (isset($normalized['reminder_max_days'], $normalized['reminder_min_days']) && $normalized['reminder_max_days'] < $normalized['reminder_min_days']) throw new InvalidArgumentException('بازهٔ یادآوری وصول نامعتبر است.');
        if (isset($normalized['operator_task_max_days'], $normalized['operator_task_min_days']) && $normalized['operator_task_max_days'] < $normalized['operator_task_min_days']) throw new InvalidArgumentException('بازهٔ وظیفهٔ اپراتور نامعتبر است.');
        if (isset($normalized['serious_warning_max_days'], $normalized['serious_warning_min_days']) && $normalized['serious_warning_max_days'] < $normalized['serious_warning_min_days']) throw new InvalidArgumentException('بازهٔ هشدار جدی نامعتبر است.');
        if (isset($normalized['reminder_max_days'], $normalized['operator_task_min_days'], $normalized['operator_task_max_days'], $normalized['serious_warning_min_days'], $normalized['serious_warning_max_days'], $normalized['legal_review_min_days'])
            && !($normalized['reminder_max_days'] < $normalized['operator_task_min_days'] && $normalized['operator_task_max_days'] < $normalized['serious_warning_min_days'] && $normalized['serious_warning_max_days'] < $normalized['legal_review_min_days'])) {
            throw new InvalidArgumentException('بازه‌های مرحله‌ای باید بدون هم‌پوشانی و به‌ترتیب افزایش باشند.');
        }
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function contracts($section = '')
    {
        ContractPermission::requirePermission('contract_settings.view');
        $section = preg_replace('/[^a-z0-9_-]/i', '', (string) $section) ?: 'dashboard';
        if (!in_array($section, ['dashboard', 'numbering', 'template', 'print', 'versions', 'variables', 'preview', 'rebuild'], true)) {
            $section = 'dashboard';
        }
        $effective = ContractTemplateService::getEffectiveTemplate();
        $draft = ContractTemplateService::getDraftTemplate();
        $comparison = null;
        if ($section === 'versions' && !empty($_GET['compare']) && !empty($effective['id'])) {
            try {
                $comparison = ContractTemplateService::compareVersions((int) $_GET['compare'], (int) $effective['id']);
            } catch (Throwable $e) {
                set_flash('error', $e->getMessage());
            }
        }
        $this->render('settings/contracts', [
            'title' => 'تنظیمات قراردادها',
            'section' => $section,
            'settings' => Settings::allKeyed(),
            'effectiveTemplate' => $effective,
            'draftTemplate' => $draft,
            'editorTemplate' => $draft ?: $effective,
            'templateVersions' => ContractTemplateService::versions(!empty($_GET['show_archived'])),
            'showArchivedVersions' => !empty($_GET['show_archived']),
            'versionComparison' => $comparison,
            'templateStats' => ContractTemplateService::stats(),
            'printProfile' => ContractPrintProfile::load(),
            'printPresets' => ContractPrintProfile::presets(),
            'variableCatalog' => ContractTemplateService::variableCatalog(),
            'latestRebuildJob' => ContractTemplateService::latestRebuildJob(),
        ]);
    }

    public function saveContractDraft()
    {
        ContractPermission::requirePermission('contract_templates.edit');
        $this->onlyPost();
        try {
            $validation = ContractTemplateService::validateTemplate(
                $_POST['body_source'] ?? '',
                $_POST['body_format'] ?? ContractTemplateRenderer::FORMAT_PLAIN
            );
            $source = $_POST['body_source'] ?? '';
            $format = $_POST['body_format'] ?? ContractTemplateRenderer::FORMAT_PLAIN;
            $reason = $_POST['change_reason'] ?? '';
            $id = ContractTemplateService::createVersion(
                $source,
                $format,
                $reason,
                Auth::id()
            );
            $convertedFrom = (string) ($_POST['editor_original_format'] ?? '');
            $conversionSource = (string) ($_POST['editor_conversion_source'] ?? '');
            if ($convertedFrom === ContractTemplateRenderer::FORMAT_PLAIN
                && $format === ContractTemplateRenderer::FORMAT_HTML
                && $conversionSource === 'visual_editor') {
                $saved = ContractTemplateService::findVersion($id);
                ContractTemplateService::audit('template_format_converted', 1, $id,
                    ['format' => $convertedFrom],
                    [
                        'format' => ContractTemplateRenderer::FORMAT_HTML,
                        'conversion_source' => 'visual_editor',
                        'content_hash' => (string) ($saved['content_hash'] ?? ''),
                    ],
                    $reason,
                    Auth::id()
                );
            }
            $message = 'پیش‌نویس نسخه ' . to_persian_digits((ContractTemplateService::findVersion($id)['version_number'] ?? '')) . ' ذخیره شد.';
            if (!empty($validation['warnings'])) {
                $message .= ' هشدار: ' . implode(' ', $validation['warnings']);
            }
            set_flash('success', $message);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/template');
    }

    public function saveContractHeader()
    {
        ContractPermission::requirePermission('contract_settings.manage');
        $this->onlyPost();
        try {
            $title = trim(strip_tags((string) ($_POST['contract_document_title'] ?? '')));
            $header = trim(strip_tags((string) ($_POST['contract_document_header'] ?? '')));
            if ($title === '') {
                throw new InvalidArgumentException('عنوان چاپی قرارداد الزامی است.');
            }
            $old = ['title' => Settings::get('contract_document_title', ''), 'header' => Settings::get('contract_document_header', '')];
            Settings::saveMany(['contract_document_title' => $title, 'contract_document_header' => $header]);
            ContractTemplateService::audit('contract_header_changed', 1, null, $old, ['title' => $title, 'header' => $header], $_POST['change_reason'] ?? 'ویرایش عنوان و هدر قرارداد', Auth::id());
            set_flash('success', 'عنوان و هدر چاپی قرارداد ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/template');
    }

    public function saveContractNumbering()
    {
        ContractPermission::requirePermission('contract_settings.manage');
        $this->onlyPost();
        try {
            $prefix = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', to_english_digits($_POST['contract_prefix'] ?? 'PR'))) ?: 'PR';
            $year = preg_replace('/\D+/', '', to_english_digits($_POST['contract_year'] ?? ''));
            $serial = max(1, (int) to_english_digits($_POST['contract_next_serial'] ?? 1));
            $format = trim((string) ($_POST['contract_number_format'] ?? 'PR-{SERIAL:6}'));
            if (!preg_match('/\{SERIAL(?::[1-9][0-9]?)?\}/', $format)) {
                throw new InvalidArgumentException('فرمت شماره قرارداد باید متغیر SERIAL داشته باشد.');
            }
            Settings::saveMany(['contract_prefix' => $prefix, 'contract_year' => $year, 'contract_next_serial' => (string) $serial, 'contract_number_format' => $format]);
            ContractTemplateService::audit('contract_numbering_changed', 1, null, null, ['prefix' => $prefix, 'year' => $year, 'next_serial' => $serial, 'format' => $format], $_POST['change_reason'] ?? 'ویرایش شماره‌گذاری قرارداد', Auth::id());
            set_flash('success', 'تنظیمات شماره‌گذاری قرارداد ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/numbering');
    }

    public function publishContractTemplate($id)
    {
        ContractPermission::requirePermission('contract_templates.publish');
        $this->onlyPost();
        try {
            ContractTemplateService::publishVersion((int) $id, $_POST['change_reason'] ?? '', Auth::id());
            set_flash('success', 'نسخه قالب منتشر شد. اسناد نهایی قدیمی بدون تغییر باقی ماندند.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/versions');
    }

    public function restoreContractTemplate($id)
    {
        ContractPermission::requirePermission('contract_templates.restore');
        $this->onlyPost();
        try {
            ContractTemplateService::restoreVersion((int) $id, $_POST['change_reason'] ?? 'بازگردانی نسخه قدیمی به‌عنوان پیش‌نویس', Auth::id());
            set_flash('success', 'نسخه انتخاب‌شده به‌عنوان پیش‌نویس تازه بازیابی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/template');
    }

    public function archiveContractTemplate($id)
    {
        ContractPermission::requirePermission('contract_templates.archive');
        $this->onlyPost();
        try {
            ContractTemplateService::archiveVersion((int) $id, $_POST['change_reason'] ?? '', Auth::id());
            set_flash('success', 'نسخه قالب بایگانی شد و تاریخچه حقوقی آن محفوظ ماند.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/versions');
    }

    public function deleteContractTemplate($id)
    {
        ContractPermission::requirePermission('contract_templates.delete_unused');
        $this->onlyPost();
        try {
            if (empty($_POST['confirm_retirement']) && empty($_POST['confirm_delete'])) {
                throw new InvalidArgumentException('برای حذف نسخه، تایید نهایی را انتخاب کنید.');
            }
            $deleted = ContractTemplateService::deleteVersion((int) $id, $_POST['change_reason'] ?? '', Auth::id());
            set_flash('success', $deleted ? 'نسخه بدون استفاده حذف شد.' : 'این نسخه قبلاً حذف شده بود.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/versions');
    }

    public function resetContractTemplate()
    {
        ContractPermission::requirePermission('contract_templates.edit');
        $this->onlyPost();
        try {
            ContractTemplateService::resetToDefault($_POST['change_reason'] ?? 'بازنشانی به قالب پیش‌فرض سامانه', Auth::id());
            set_flash('success', 'قالب پیش‌فرض به‌عنوان پیش‌نویس ایجاد شد و هنوز منتشر نشده است.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/template');
    }

    public function saveContractPrint()
    {
        ContractPermission::requirePermission('contract_print_settings.manage');
        $this->onlyPost();
        try {
            $old = ContractPrintProfile::load();
            $preset = $_POST['preset'] ?? 'custom';
            $profile = !empty($_POST['apply_preset'])
                ? ContractPrintProfile::fromPreset($preset)
                : ContractPrintProfile::validate($_POST);
            Settings::saveMany(ContractPrintProfile::settingValues($profile));
            $reason = $_POST['change_reason'] ?? 'ویرایش تنظیمات چاپ';
            ContractTemplateService::audit('print_profile_updated', 1, null, $old, $profile, $reason, Auth::id());
            ContractTemplateService::audit('contract_typography_updated', 1, null, [
                'body_font_size' => $old['body_font_size'], 'body_line_height' => $old['body_line_height'],
            ], [
                'body_font_size' => $profile['body_font_size'], 'heading_font_size' => $profile['heading_font_size'],
                'important_font_size' => $profile['important_font_size'], 'body_line_height' => $profile['body_line_height'],
            ], $reason, Auth::id());
            if ($profile['preset'] === ContractPrintProfile::OFFICIAL_COMPACT) {
                ContractTemplateService::audit('compact_print_profile_activated', 1, null, null, ['preset' => $profile['preset']], $reason, Auth::id());
            }
            set_flash('success', 'تنظیمات چاپ ذخیره شد و بدون بازسازی متن روی همه چاپ‌ها اعمال می‌شود.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/print');
    }

    public function saveContractSellerSignature()
    {
        ContractPermission::requirePermission('contract_print_settings.manage');
        $this->onlyPost();
        try {
            $oldPath = trim((string) Settings::get('contract_seller_signature_path', ''));
            if (!empty($_POST['remove_seller_signature'])) {
                $newPath = '';
            } else {
                $upload = $_FILES['seller_signature'] ?? [];
                if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new InvalidArgumentException('تصویر امضای فروشنده را انتخاب کنید یا گزینه حذف را بزنید.');
                }
                $newPath = UploadHelper::storePublicImage($upload, 'contract-signatures', ['jpg', 'jpeg', 'png', 'webp'], [
                    'category' => 'contract_seller_signature',
                    'visibility' => 'public',
                    'related_entity_type' => 'contract_seller_signature',
                    'relation_type' => 'signature',
                    'description' => 'تصویر امضای فروشنده برای چاپ قرارداد',
                ]);
                if (!$newPath) throw new RuntimeException('ذخیره تصویر امضا انجام نشد.');
            }
            Settings::saveMany(['contract_seller_signature_path' => $newPath]);
            ContractTemplateService::audit('seller_signature_updated', 1, null, ['path' => $oldPath], ['path' => $newPath], trim((string) ($_POST['change_reason'] ?? 'تغییر امضای فروشنده')), Auth::id());
            set_flash('success', $newPath === '' ? 'امضای فروشنده از چاپ قرارداد حذف شد.' : 'تصویر امضای فروشنده برای چاپ قرارداد ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/print');
    }

    public function contractsPreview($versionId = null)
    {
        ContractPermission::requirePermission('contract_templates.view');
        $template = $versionId ? ContractTemplateService::findVersion((int) $versionId) : null;
        $template = $template ?: (ContractTemplateService::getDraftTemplate() ?: ContractTemplateService::getEffectiveTemplate());
        $settings = Settings::allKeyed();
        $this->render('settings/contract-preview', [
            'title' => 'پیش‌نمایش قرارداد',
            'settings' => $settings,
            'profile' => ContractPrintProfile::load($settings),
            'body' => ContractTemplateService::previewHtml($template),
            'template' => $template,
        ], null);
    }

    public function rebuildContractDocuments($jobId = null)
    {
        ContractPermission::requirePermission('contract_documents.rebuild');
        $this->onlyPost();
        try {
            $jobId = $jobId ? (int) $jobId : ContractTemplateService::startRebuildJob(Auth::id());
            $job = ContractTemplateService::processRebuildJob($jobId, 25);
            $message = ($job['status'] ?? '') === 'completed'
                ? 'بازسازی اسناد غیرنهایی کامل شد.'
                : 'یک بسته ۲۵تایی پردازش شد؛ برای ادامه دوباره دکمه ادامه را بزنید.';
            set_flash('success', $message);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('settings/contracts/rebuild');
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
