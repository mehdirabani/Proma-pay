<?php
$detectedBase = detected_base_url();
$callbackBase = rtrim($settings['callback_base_url'] ?: $detectedBase, '/');
$callbackUrl = $callbackBase . '/index.php?route=payments/callback';
$contractVariables = ContractDocument::variableDescriptions();
$activeContractTemplate = trim((string) ($settings['contract_template_body'] ?? ''));
if ($activeContractTemplate === '') {
    $activeContractTemplate = ContractDocument::defaultTemplate();
}
$activeTab = $activeTab ?? 'general';
$tabGroups = [
    'پایه' => [
        'general' => 'عمومی',
        'security' => 'امنیت',
    ],
    'قرارداد و مالی' => [
        'finance' => 'مالی',
        'gateway' => 'درگاه پرداخت',
        'contract' => 'قرارداد',
        'template' => 'قالب قرارداد',
    ],
    'ارتباطات' => [
        'ecommerce' => 'لندینگ فروشگاه',
        'social' => 'شبکه‌های اجتماعی',
        'notifications' => 'اعلان‌ها و چت',
        'email' => 'ایمیل',
        'sms' => 'پنل پیامکی',
        'calendar' => 'اعلان‌های تقویم',
    ],
    'داده و هوشمندی' => [
        'imports' => 'ورود دیتا',
        'ai' => 'هوش مصنوعی',
    ],
    'نگهداری' => [
        'backup' => 'بکاپ و بازیابی',
        'update' => 'بروزرسانی',
        'danger' => 'حذف داده‌ها',
    ],
];
$tabs = [];
foreach ($tabGroups as $groupTabs) {
    $tabs += $groupTabs;
}
if (!isset($tabs[$activeTab])) {
    $activeTab = 'general';
}
?>

<form method="post" action="<?= e(url('settings/update')) ?>" enctype="multipart/form-data" class="grid proma-settings-form proma-settings-shell">
  <?= csrf_field() ?>
  <input type="hidden" name="_active_tab" value="<?= e($activeTab) ?>" data-settings-active-tab>

  <nav class="proma-settings-tabs" aria-label="تنظیمات">
    <div class="proma-settings-version">
      <div>
        <strong>نسخه نصب‌شده: <?= e(app_version_label()) ?></strong>
        <small>نسخه اسکریپت و فایل‌های فعلی سامانه</small>
      </div>
      <span class="badge badge-light-success">فعال</span>
    </div>
    <?php foreach ($tabGroups as $groupTitle => $groupTabs): ?>
      <div class="proma-settings-tab-group">
        <span><?= e($groupTitle) ?></span>
        <div>
          <?php foreach ($groupTabs as $key => $label): ?>
            <a class="tab-link <?= $activeTab === $key ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => $key])) ?>" data-settings-tab="<?= e($key) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </nav>

  <section class="card proma-settings-panel <?= $activeTab === 'general' ? 'active' : '' ?>" data-settings-panel="general">
    <div class="card-header"><h2>تنظیمات عمومی</h2></div>
    <div class="card-body form-grid">
      <label>نام سامانه<input name="system_name" value="<?= e($settings['system_name']) ?>"></label>
      <label>متن نشان<input name="logo_text" value="<?= e($settings['logo_text']) ?>"></label>
      <label class="full">متن فوتر سامانه<input name="footer_text" value="<?= e($settings['footer_text'] ?? '') ?>" maxlength="180" aria-describedby="footer-text-help"><small id="footer-text-help" class="proma-form-help">این متن در پایین صفحات سامانه نمایش داده می‌شود. HTML و اسکریپت در این بخش پذیرفته نمی‌شود.</small><button class="btn small secondary" type="button" data-reset-setting="footer_text" data-reset-value="پروما پی سامانه جامع پرداخت">بازنشانی متن پیش‌فرض</button></label>
      <label>لوگوی اصلی<input type="file" name="logo_file" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"></label>
      <label>لوگوی کوچک / آیکن<input type="file" name="logo_icon_file" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"></label>
      <label>فاوآیکن مرورگر<input type="file" name="favicon_file" accept=".ico,.jpg,.jpeg,.png,.webp,.svg,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/png,image/webp,image/svg+xml"></label>
      <div class="full proma-logo-settings-preview">
        <?php if (!empty($settings['logo_path'])): ?>
          <span><img src="<?= e(asset_url($settings['logo_path'])) ?>" alt="لوگوی اصلی"><label><input type="checkbox" name="delete_logo_path" value="1"> حذف لوگوی اصلی</label></span>
        <?php endif; ?>
        <?php if (!empty($settings['logo_icon_path'])): ?>
          <span><img src="<?= e(asset_url($settings['logo_icon_path'])) ?>" alt="لوگوی کوچک"><label><input type="checkbox" name="delete_logo_icon_path" value="1"> حذف لوگوی کوچک</label></span>
        <?php endif; ?>
        <?php if (!empty($settings['favicon_path'])): ?>
          <span><img src="<?= e(asset_url($settings['favicon_path'])) ?>" alt="فاوآیکن"><label><input type="checkbox" name="delete_favicon_path" value="1"> حذف فاوآیکن</label></span>
        <?php endif; ?>
      </div>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات عمومی</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'ecommerce' ? 'active' : '' ?>" data-settings-panel="ecommerce">
    <div class="card-header"><h2>لندینگ فروشگاه</h2></div>
    <div class="card-body form-grid">
      <div class="full proma-feature-toggle">
        <div>
          <strong>تجارت الکترونیک</strong>
          <small>فروشگاه، سبد خرید، سفارش‌ها، درخواست خرید اقساطی و منوی مرتبط را فعال یا غیرفعال می‌کند.</small>
        </div>
        <label><input type="checkbox" name="ecommerce_enabled" value="1"<?= checked($settings['ecommerce_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
      </div>
      <div class="full proma-feature-toggle">
        <div>
          <strong>صفحه لندینگ فروشگاه</strong>
          <small>در صورت خاموش بودن، مسیر اصلی مهمان به فهرست محصولات هدایت می‌شود و لینک لندینگ حذف خواهد شد.</small>
        </div>
        <label><input type="checkbox" name="landing_enabled" value="1"<?= checked($settings['landing_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
      </div>
      <div class="full notice info">متن‌های صفحه نخست فروشگاه از این بخش خوانده می‌شوند. برای نمایش نام سامانه از <span dir="ltr">{{system_name}}</span> استفاده کنید.</div>
      <label>نشان بالای هدر<input name="landing_kicker" value="<?= e($settings['landing_kicker'] ?? '') ?>"></label>
      <label>عنوان اصلی<input name="landing_title" value="<?= e($settings['landing_title'] ?? '') ?>"></label>
      <label class="full">متن معرفی<textarea name="landing_subtitle" rows="3"><?= e($settings['landing_subtitle'] ?? '') ?></textarea></label>
      <label>متن دکمه اصلی<input name="landing_primary_cta" value="<?= e($settings['landing_primary_cta'] ?? '') ?>"></label>
      <label>متن دکمه اقساطی<input name="landing_secondary_cta" value="<?= e($settings['landing_secondary_cta'] ?? '') ?>"></label>
      <label>برچسب بخش محصولات<input name="landing_featured_eyebrow" value="<?= e($settings['landing_featured_eyebrow'] ?? '') ?>"></label>
      <label>عنوان بخش محصولات<input name="landing_featured_title" value="<?= e($settings['landing_featured_title'] ?? '') ?>"></label>
      <label>برچسب مسیر خرید<input name="landing_steps_eyebrow" value="<?= e($settings['landing_steps_eyebrow'] ?? '') ?>"></label>
      <label>عنوان مسیر خرید<input name="landing_steps_title" value="<?= e($settings['landing_steps_title'] ?? '') ?>"></label>
      <div class="full actions"><button class="btn" type="submit">ذخیره لندینگ فروشگاه</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'social' ? 'active' : '' ?>" data-settings-panel="social">
    <div class="card-header"><h2>شبکه‌های اجتماعی مشتریان</h2></div>
    <div class="card-body form-grid">
      <div class="full notice info">این لینک‌ها به صورت کارت در پنل مشتری نمایش داده می‌شوند. می‌توانید آدرس کامل یا فقط نام کاربری را وارد کنید.</div>
      <label>اینستاگرام<input name="social_instagram_url" value="<?= e($settings['social_instagram_url'] ?? '') ?>" dir="ltr" placeholder="instagram.com/yourpage یا username"></label>
      <label>تلگرام<input name="social_telegram_url" value="<?= e($settings['social_telegram_url'] ?? '') ?>" dir="ltr" placeholder="t.me/yourchannel یا username"></label>
      <label>واتساپ<input name="social_whatsapp_url" value="<?= e($settings['social_whatsapp_url'] ?? '') ?>" dir="ltr" placeholder="989121234567 یا wa.me/989..."></label>
      <label>وب‌سایت<input name="social_website_url" value="<?= e($settings['social_website_url'] ?? '') ?>" dir="ltr" placeholder="example.com"></label>
      <label>فیسبوک<input name="social_facebook_url" value="<?= e($settings['social_facebook_url'] ?? '') ?>" dir="ltr" placeholder="facebook.com/yourpage"></label>
      <label>ایکس<input name="social_x_url" value="<?= e($settings['social_x_url'] ?? '') ?>" dir="ltr" placeholder="x.com/yourpage یا username"></label>
      <label>یوتیوب<input name="social_youtube_url" value="<?= e($settings['social_youtube_url'] ?? '') ?>" dir="ltr" placeholder="youtube.com/@yourchannel"></label>
      <label>لینکدین<input name="social_linkedin_url" value="<?= e($settings['social_linkedin_url'] ?? '') ?>" dir="ltr" placeholder="linkedin.com/in/yourpage"></label>
      <div class="full actions"><button class="btn" type="submit">ذخیره شبکه‌های اجتماعی</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'email' ? 'active' : '' ?>" data-settings-panel="email">
    <div class="card-header"><h2>تنظیمات ایمیل</h2></div>
    <div class="card-body form-grid">
      <div class="full notice info">ایمیل سفارش‌ها با قالب اختصاصی سامانه ارسال می‌شود. برای تست سریع می‌توانید بعد از تکمیل فیلدها، دکمه ارسال تست را بزنید.</div>
      <div>
        <span class="field-title">ارسال ایمیل</span>
        <div class="switch-options">
          <label><input type="checkbox" name="email_enabled" value="1"<?= checked($settings['email_enabled'] ?? '0', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>روش ارسال
        <select name="email_transport">
          <option value="mail"<?= selected($settings['email_transport'] ?? 'mail', 'mail') ?>>mail() هاست</option>
          <option value="smtp"<?= selected($settings['email_transport'] ?? 'mail', 'smtp') ?>>SMTP</option>
        </select>
      </label>
      <label>نام فرستنده<input name="email_from_name" value="<?= e($settings['email_from_name'] ?? '') ?>"></label>
      <label>ایمیل فرستنده<input name="email_from_address" value="<?= e($settings['email_from_address'] ?? '') ?>" dir="ltr" placeholder="no-reply@example.com"></label>
      <label>Reply-To<input name="email_reply_to" value="<?= e($settings['email_reply_to'] ?? '') ?>" dir="ltr" placeholder="support@example.com"></label>
      <label>متن بالای قالب<input name="email_header_note" value="<?= e($settings['email_header_note'] ?? '') ?>"></label>
      <label class="full">آدرس/متن فوتر ایمیل<input name="email_footer_address" value="<?= e($settings['email_footer_address'] ?? '') ?>"></label>
      <label class="full">موضوع ایمیل سفارش<input name="email_order_subject" value="<?= e($settings['email_order_subject'] ?? '') ?>" placeholder="سفارش {{order_number}} با موفقیت ثبت شد"></label>
      <label>SMTP Host<input name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" dir="ltr" placeholder="mail.example.com"></label>
      <label>SMTP Port<input name="smtp_port" value="<?= e(to_persian_digits($settings['smtp_port'] ?? '587')) ?>" inputmode="numeric" dir="ltr"></label>
      <label>SMTP Username<input name="smtp_username" value="<?= e($settings['smtp_username'] ?? '') ?>" dir="ltr"></label>
      <label>SMTP Password<input name="smtp_password" type="password" autocomplete="off" value="<?= e($settings['smtp_password'] ?? '') ?>" dir="ltr"></label>
      <label>رمزنگاری
        <select name="smtp_encryption">
          <option value="tls"<?= selected($settings['smtp_encryption'] ?? 'tls', 'tls') ?>>TLS</option>
          <option value="ssl"<?= selected($settings['smtp_encryption'] ?? 'tls', 'ssl') ?>>SSL</option>
          <option value=""<?= selected($settings['smtp_encryption'] ?? 'tls', '') ?>>بدون رمزنگاری</option>
        </select>
      </label>
      <label>ایمیل تست<input name="email_test_to" value="<?= e($settings['email_reply_to'] ?? $settings['email_from_address'] ?? '') ?>" dir="ltr" placeholder="customer@example.com"></label>
      <div class="full proma-email-preview">
        <div>
          <span><?= e($settings['email_header_note'] ?? 'برخی توضیحات') ?></span>
          <strong><?= e($settings['system_name'] ?? app_config('app_name', 'پروما')) ?></strong>
          <section>
            <h6>بازنشانی رمز عبور</h6>
            <p>قالب عمومی ایمیل‌ها با کارت سفید، دکمه اصلی و فوتر سبک نمایش داده می‌شود.</p>
            <button type="button">نمونه دکمه</button>
          </section>
        </div>
      </div>
      <div class="full actions">
        <button class="btn" type="submit">ذخیره تنظیمات ایمیل</button>
        <button class="btn secondary" type="submit" formaction="<?= e(url('settings/testEmail')) ?>">ارسال ایمیل تست</button>
      </div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'contract' ? 'active' : '' ?>" data-settings-panel="contract">
    <div class="card-header"><h2>تنظیمات قرارداد</h2></div>
    <div class="card-body form-grid">
      <label>نام مجموعه<input name="company_name" value="<?= e($settings['company_name'] ?? '') ?>"></label>
      <label>نام نماینده مجموعه<input name="company_representative_name" value="<?= e($settings['company_representative_name'] ?? '') ?>"></label>
      <label>کد ملی نماینده<input name="company_representative_national_id" value="<?= e(to_persian_digits($settings['company_representative_national_id'] ?? '')) ?>" inputmode="numeric"></label>
      <label class="full">آدرس مجموعه<input name="company_address" value="<?= e($settings['company_address'] ?? '') ?>"></label>
      <label>کد پستی مجموعه<input name="company_postal_code" value="<?= e(to_persian_digits($settings['company_postal_code'] ?? '')) ?>" inputmode="numeric"></label>
      <label>شماره تماس مجموعه<input name="company_phone" value="<?= e(to_persian_digits($settings['company_phone'] ?? '')) ?>" inputmode="tel"></label>
      <label>پیشوند شماره قرارداد<input name="contract_prefix" value="<?= e($settings['contract_prefix']) ?>" dir="ltr"></label>
      <label>شماره بعدی قرارداد<input name="contract_next_serial" value="<?= e(to_persian_digits($settings['contract_next_serial'])) ?>" inputmode="numeric"></label>
      <label class="full">قالب شماره قرارداد<input name="contract_number_format" value="<?= e($settings['contract_number_format'] ?? 'PR-{SERIAL:6}') ?>" dir="ltr" placeholder="000001 یا PR-{SERIAL:6} یا CN-{SERIAL:6}"></label>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات قرارداد</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'finance' ? 'active' : '' ?>" data-settings-panel="finance">
    <div class="card-header"><h2>تنظیمات مالی</h2></div>
    <div class="card-body form-grid">
      <label>نرخ جریمه عادی ماهانه<input name="monthly_penalty_rate" value="<?= e($settings['monthly_penalty_rate']) ?>" inputmode="decimal"></label>
      <label>نرخ جریمه حقوقی ماهانه<input name="legal_monthly_penalty_rate" value="<?= e($settings['legal_monthly_penalty_rate'] ?? $settings['monthly_penalty_rate']) ?>" inputmode="decimal"></label>
      <label>مدت تنفس دیرکرد<input name="late_penalty_grace_days" value="<?= e(to_persian_digits($settings['late_penalty_grace_days'] ?? '0')) ?>" inputmode="numeric" placeholder="مثلاً ۵ روز"></label>
      <label>نرخ پاداش ماهانه<input name="monthly_reward_rate" value="<?= e($settings['monthly_reward_rate']) ?>" inputmode="decimal"></label>
      <div class="full notice info">اگر مدت تنفس ۵ روز باشد، تا پایان روز پنجم پس از سررسید جریمه دیرکرد محاسبه نمی‌شود و محاسبه از روز بعد آغاز می‌شود.</div>
      <label class="full">متن تأیید جریمه حقوقی در قرارداد<textarea name="contract_legal_penalty_clause" rows="4"><?= e($settings['contract_legal_penalty_clause'] ?? '') ?></textarea></label>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات مالی</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'gateway' ? 'active' : '' ?>" data-settings-panel="gateway">
    <div class="card-header"><h2>درگاه پرداخت</h2></div>
    <div class="card-body form-grid">
      <div>
        <span class="field-title">پرداخت آنلاین زیبال</span>
        <div class="switch-options">
          <label><input type="checkbox" name="zibal_enabled" value="1"<?= checked($settings['zibal_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <div>
        <span class="field-title">حالت تست زیبال</span>
        <div class="switch-options">
          <label><input type="checkbox" name="zibal_test_mode" value="1"<?= checked($settings['zibal_test_mode'] ?? '0', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>مرچنت زیبال<input name="zibal_merchant" value="<?= e($settings['zibal_merchant']) ?>" dir="ltr"></label>
      <label>نشانی پایه بازگشت<input name="callback_base_url" value="<?= e($settings['callback_base_url']) ?>" placeholder="<?= e($detectedBase) ?>" dir="ltr"></label>
      <div class="full notice info">
        <strong>نشانی نهایی بازگشت:</strong>
        <span class="ltr callback-url"><?= e($callbackUrl) ?></span>
        <small class="d-block">اگر فقط دامنه وارد شود، سامانه هنگام ذخیره به صورت خودکار <span class="ltr">https://</span> را اضافه می‌کند. در حالت تست، سامانه از مرچنت آزمایشی زیبال استفاده می‌کند و مرچنت live نادیده گرفته می‌شود.</small>
      </div>
      <div class="full">
        <span class="field-title">پرداخت کارت به کارت</span>
        <div class="switch-options">
          <label><input type="checkbox" name="card_transfer_enabled" value="1"<?= checked($settings['card_transfer_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>نام بانک<input name="card_transfer_bank_name" value="<?= e($settings['card_transfer_bank_name'] ?? '') ?>"></label>
      <label>لوگوی بانک<input name="card_transfer_bank_logo_text" value="<?= e($settings['card_transfer_bank_logo_text'] ?? '') ?>" dir="ltr" placeholder="BM / ملت / پاسارگاد"></label>
      <label>نام صاحب حساب<input name="card_transfer_account_name" value="<?= e($settings['card_transfer_account_name'] ?? '') ?>"></label>
      <label>شماره کارت<input name="card_transfer_card_number" value="<?= e(to_persian_digits(format_card_number($settings['card_transfer_card_number'] ?? ''))) ?>" inputmode="numeric" dir="ltr"></label>
      <label>شماره حساب<input name="card_transfer_account_number" value="<?= e(to_persian_digits(format_account_number($settings['card_transfer_account_number'] ?? ''))) ?>" inputmode="numeric" dir="ltr"></label>
      <label>رنگ اصلی کارت<input type="color" name="card_transfer_primary_color" value="<?= e(sanitize_hex_color($settings['card_transfer_primary_color'] ?? '#7366ff', '#7366ff')) ?>"></label>
      <label>رنگ ثانویه کارت<input type="color" name="card_transfer_secondary_color" value="<?= e(sanitize_hex_color($settings['card_transfer_secondary_color'] ?? '#16c7f9', '#16c7f9')) ?>"></label>
      <div>
        <span class="field-title">نمایش شبا</span>
        <div class="switch-options">
          <label><input type="checkbox" name="card_transfer_show_sheba" value="1"<?= checked($settings['card_transfer_show_sheba'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>شماره شبا<input name="card_transfer_sheba" value="<?= e(to_persian_digits(format_sheba($settings['card_transfer_sheba'] ?? ''))) ?>" dir="ltr"></label>
      <div>
        <span class="field-title">نمایش شماره حساب</span>
        <div class="switch-options">
          <label><input type="checkbox" name="card_transfer_show_account_number" value="1"<?= checked($settings['card_transfer_show_account_number'] ?? '0', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>متن QR پرداخت<input name="card_transfer_qr_text" value="<?= e($settings['card_transfer_qr_text'] ?? '') ?>" dir="ltr" placeholder="شناسه یا متن QR پرداخت"></label>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات درگاه</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'sms' ? 'active' : '' ?>" data-settings-panel="sms">
    <div class="card-header"><h2>پنل پیامکی IPPanel</h2></div>
    <div class="card-body form-grid">
      <div>
        <span class="field-title">بازیابی رمز عبور با پیامک</span>
        <div class="switch-options">
          <label><input type="checkbox" name="password_reset_enabled" value="1"<?= checked($settings['password_reset_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>کلید API
        <input name="ippanel_api_key" type="password" autocomplete="off" value="<?= e($settings['ippanel_api_key'] ?? '') ?>" dir="ltr">
      </label>
      <label>شماره فرستنده
        <input name="ippanel_from_number" value="<?= e($settings['ippanel_from_number'] ?? '') ?>" placeholder="+983000505" dir="ltr">
      </label>
      <label>کد پترن بازیابی رمز
        <input name="ippanel_password_reset_pattern_code" value="<?= e($settings['ippanel_password_reset_pattern_code'] ?? '') ?>" dir="ltr">
      </label>
      <label>نام متغیر کد در پترن
        <input name="ippanel_password_reset_pattern_key" value="<?= e($settings['ippanel_password_reset_pattern_key'] ?? 'code') ?>" dir="ltr">
      </label>
      <div class="full notice info">
        اگر کد پترن خالی باشد، پیامک معمولی با روش webservice ارسال می‌شود. کلید API از بخش Developers &gt; Access Keys در پنل IPPanel دریافت می‌شود.
      </div>
      <div class="full proma-pattern-help">
        <strong>نمونه پیشنهادی پترن در IPPanel</strong>
        <div class="proma-pattern-grid">
          <code dir="rtl">کد بازیابی رمز عبور شما: %code%<br>پرما پرداخت</code>
          <code dir="rtl">کد تایید شما %code% است. این کد تا ۱۰ دقیقه معتبر است.</code>
        </div>
        <small>در پنل IPPanel متغیر را با نام <span class="ltr">code</span> تعریف کنید و همین مقدار را در فیلد «نام متغیر کد در پترن» بگذارید.</small>
      </div>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات پیامک</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'calendar' ? 'active' : '' ?>" data-settings-panel="calendar">
    <div class="card-header"><h2>اعلان‌های تقویم</h2></div>
    <div class="card-body form-grid">
      <div class="full notice info">برای اجرای خودکار، فایل cron یا route امن پایین همین کارت را روی هاست زمان‌بندی کنید.</div>
      <div>
        <span class="field-title">فعال بودن اعلان تقویم</span>
        <div class="switch-options">
          <label><input type="checkbox" name="calendar_notifications_enabled" value="1"<?= checked($settings['calendar_notifications_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>زمان پیش‌فرض یادآوری
        <select name="calendar_default_reminder_type">
          <?php foreach (Event::reminderOptions() as $value => $label): if ($value === '' || $value === 'custom') continue; ?>
            <option value="<?= e($value) ?>"<?= selected($settings['calendar_default_reminder_type'] ?? '1_day', $value) ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div>
        <span class="field-title">ارسال به مدیران برای رویداد بدون کاربر</span>
        <div class="switch-options">
          <label><input type="checkbox" name="calendar_notify_admin_without_user" value="1"<?= checked($settings['calendar_notify_admin_without_user'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <div>
        <span class="field-title">اعلان جداگانه در روز موعد</span>
        <div class="switch-options">
          <label><input type="checkbox" name="calendar_due_day_repeat_enabled" value="1"<?= checked($settings['calendar_due_day_repeat_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label class="full">توکن امن Cron<input name="calendar_cron_token" value="<?= e($settings['calendar_cron_token'] ?? '') ?>" dir="ltr"></label>
      <div class="full proma-cron-help">
        <strong>اجرای پیشنهادی CLI هر ۵ یا ۱۵ دقیقه:</strong>
        <code>php <?= e(str_replace('\\', '/', dirname(__DIR__, 2))) ?>/cron/calendar_reminders.php</code>
        <strong>یا route امن وب:</strong>
        <code><?= e($detectedBase) ?>/index.php?route=cron/calendar-reminders&amp;token=<?= e($settings['calendar_cron_token'] ?? '') ?></code>
      </div>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات اعلان تقویم</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'notifications' ? 'active' : '' ?>" data-settings-panel="notifications">
    <div class="card-header"><h2>اعلان‌ها و چت</h2></div>
    <div class="card-body form-grid">
      <div>
        <span class="field-title">صدای اعلان تازه</span>
        <div class="switch-options">
          <label><input type="checkbox" name="notifications_sound_enabled" value="1"<?= checked($settings['notifications_sound_enabled'] ?? '1', '1') ?>><span>فعال</span></label>
        </div>
      </div>
      <label>شدت صدا<input name="notifications_sound_volume" value="<?= e($settings['notifications_sound_volume'] ?? '0.45') ?>" inputmode="decimal" placeholder="0.45"></label>
      <label>حذف خودکار تصویرهای چت پس از چند روز<input name="chat_file_auto_delete_days" value="<?= e(to_persian_digits($settings['chat_file_auto_delete_days'] ?? '7')) ?>" inputmode="numeric"></label>
      <div class="full notice info">فایل تصویر چت بعد از این مدت از دیسک حذف می‌شود، اما رکورد و مرجع پیام در دیتابیس باقی می‌ماند.</div>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات اعلان و چت</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'ai' ? 'active' : '' ?>" data-settings-panel="ai">
    <div class="card-header"><h2>هوش مصنوعی</h2></div>
    <div class="card-body form-grid">
      <label>کلید اوپن‌روتر<input name="openrouter_api_key" type="password" autocomplete="off" value="<?= e($settings['openrouter_api_key']) ?>" dir="ltr"></label>
      <label>مدل اوپن‌روتر<input name="openrouter_model" value="<?= e($settings['openrouter_model']) ?>" dir="ltr"></label>
      <div class="full ai-test-actions">
        <button class="btn secondary" type="button" data-ai-test-url="<?= e(url('settings/testAi')) ?>">تست اتصال</button>
        <span class="ai-test-result" data-ai-test-result>برای تست، کلید و مدل را وارد کنید و دکمه تست اتصال را بزنید.</span>
      </div>
      <div class="full actions"><button class="btn" type="submit">ذخیره تنظیمات هوش مصنوعی</button></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'imports' ? 'active' : '' ?>" data-settings-panel="imports">
    <div class="card-header"><h2>ورود دیتا</h2></div>
    <div class="card-body form-grid">
      <label class="full">فایل داده
        <input type="file" name="data_file" accept=".xlsx,.csv,.txt" form="settings-import-form">
      </label>
      <label class="full">یا متن خام را اینجا وارد کنید
        <textarea name="raw_text" form="settings-import-form" placeholder="نام مشتری، موبایل، کد ملی، مبلغ قرارداد، پیش‌پرداخت، تعداد اقساط، تاریخ سررسید و توضیحات"></textarea>
      </label>
      <div class="notice info full">داده‌ها ابتدا با parser داخلی سامانه خوانده و اعتبارسنجی می‌شوند. اگر ساختار داده قابل تشخیص نباشد و کلید هوش مصنوعی تنظیم شده باشد، AI فقط به‌عنوان کمک تحلیل استفاده می‌شود.</div>
      <div class="full"><button class="btn" type="submit" form="settings-import-form" data-loading-text="در حال خواندن دیتا...">خواندن و پیش‌نمایش دیتا</button></div>
      <div class="table-wrap full">
        <table>
          <thead><tr><th>منبع</th><th>وضعیت</th><th>تاریخ</th><th>خطاها</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php foreach (($importBatches ?? []) as $batch): ?>
            <?php $errors = json_decode($batch['error_summary'] ?? '[]', true); ?>
            <tr>
              <td><?= e($batch['filename']) ?></td>
              <td><span class="badge <?= e(badge_class($batch['status'])) ?>"><?= e(status_label($batch['status'])) ?></span></td>
              <td><?= e(jdate($batch['created_at'])) ?></td>
              <td><?= $errors ? to_persian_digits(count($errors)) . ' خطا' : 'بدون خطای ثبت‌شده' ?></td>
              <td>
                <div class="actions">
                  <a class="btn small secondary" href="<?= e(url('imports/preview/' . $batch['id'])) ?>">پیش‌نمایش</a>
                  <button class="btn small danger icon-only" type="button" data-open-modal="delete-import-batch-<?= (int) $batch['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($importBatches)): ?><tr><td colspan="5" class="empty">هنوز دیتایی وارد نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'template' ? 'active' : '' ?>" data-settings-panel="template">
    <div class="card-header"><h2>قراردادها</h2></div>
    <div class="card-body grid">
      <div class="notice info">قالب قرارداد، نسخه‌ها، متغیرها و تنظیمات چاپ اکنون در بخش مستقل ذخیره می‌شوند؛ بنابراین ویرایش قالب هیچ تنظیم یا کلید محرمانه دیگری را دوباره ارسال نمی‌کند.</div>
      <div class="actions"><a class="btn" href="<?= e(url('settings/contracts')) ?>"><i data-feather="file-text"></i> ورود به تنظیمات حرفه‌ای قرارداد</a></div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'backup' ? 'active' : '' ?>" data-settings-panel="backup">
    <div class="card-header"><h2>بکاپ و بازیابی</h2></div>
    <div class="card-body grid">
      <div class="notice info">بکاپ شامل database.sql، فایل‌های آپلودی، storage، snapshot تنظیمات و metadata است و خارج از مسیر دانلود مستقیم نگهداری می‌شود.</div>
      <div class="actions">
        <button class="btn success" type="submit" formaction="<?= e(url('backup/create')) ?>">گرفتن بکاپ zip</button>
        <button class="btn danger" type="button" data-open-modal="restore-backup-modal">بازیابی بکاپ</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>فایل بکاپ</th><th>نوع</th><th>حجم</th><th>زمان ساخت</th><th>عملیات</th></tr></thead>
          <tbody>
            <?php foreach (($backupFiles ?? []) as $file): ?>
              <tr>
                <td class="ltr"><?= e($file['name']) ?></td>
                <td><span class="badge muted"><?= e(strtoupper($file['type'])) ?></span></td>
                <td><?= to_persian_digits(number_format(max(1, (int) ceil(($file['size'] ?? 0) / 1024)))) ?> KB</td>
                <td><?= e(jdatetime($file['created_at'])) ?></td>
                <td>
                  <div class="actions">
                    <a class="btn small secondary" href="<?= e(url('backup/download/' . rawurlencode($file['name']))) ?>">دانلود</a>
                    <button class="btn small warning" type="button" data-open-modal="restore-stored-backup-<?= e(md5($file['name'])) ?>">بازیابی</button>
                    <button class="btn small danger icon-only" type="button" data-open-modal="delete-backup-<?= e(md5($file['name'])) ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($backupFiles)): ?><tr><td colspan="5" class="empty">هنوز فایل بکاپی ساخته نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="proma-section-heading d-flex justify-content-between align-items-center flex-wrap gap-2"><h3 class="mb-0">لاگ عملیات بکاپ</h3><?php if (!empty($backupLogs)): ?><button class="btn small danger" type="button" data-open-modal="delete-backup-logs-modal"><i data-feather="trash-2"></i> حذف لاگ‌ها</button><?php endif; ?></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th><input type="checkbox" data-check-all="backup_log_ids" aria-label="انتخاب همه لاگ‌های بکاپ"></th><th>زمان</th><th>عملیات</th><th>فایل</th><th>وضعیت</th><th>پیام</th></tr></thead>
          <tbody>
            <?php foreach (($backupLogs ?? []) as $log): ?>
              <tr>
                <td><input type="checkbox" name="log_ids[]" value="<?= (int) $log['id'] ?>" data-check-item="backup_log_ids" form="backup-log-delete-form" aria-label="انتخاب لاگ شماره <?= (int) $log['id'] ?>"></td>
                <td><?= e(jdatetime($log['created_at'])) ?></td>
                <td><?= e($log['action']) ?></td>
                <td><?= e($log['file_name']) ?></td>
                <td><span class="badge <?= e(badge_class($log['status'])) ?>"><?= e($log['status']) ?></span></td>
                <td><?= e($log['message']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($backupLogs)): ?><tr><td colspan="6" class="empty">هنوز لاگی ثبت نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'update' ? 'active' : '' ?>" data-settings-panel="update">
    <div class="card-header"><h2>بروزرسانی اسکریپت</h2></div>
    <div class="card-body grid">
      <div class="notice info">می‌توانید بسته بروزرسانی manifestدار یا فایل کامل <span class="ltr">proma-pay_v...</span> را بارگذاری کنید. آپلود سریع ذخیره می‌شود و اعتبارسنجی کامل هنگام نصب انجام می‌شود. قبل از نصب، سامانه به صورت خودکار بکاپ ایمنی می‌گیرد و مسیرهای حساس مانند config/database.php، storage و uploads تغییر نمی‌کنند.</div>
      <div class="form-grid two">
        <label>فایل zip بروزرسانی
          <input type="file" name="update_file" accept=".zip,application/zip" form="settings-update-upload-form">
        </label>
        <div class="actions">
          <button class="btn" type="submit" form="settings-update-upload-form" data-loading-text="در حال بارگذاری بسته...">بارگذاری بسته</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>بسته</th><th>نسخه</th><th>فایل‌ها</th><th>Migration</th><th>وضعیت</th><th>عملیات</th></tr></thead>
          <tbody>
            <?php foreach (($updatePackages ?? []) as $package): ?>
              <tr>
                <td>
                  <strong><?= e($package['title'] ?: 'بسته بروزرسانی') ?></strong>
                  <small class="ltr d-block"><?= e($package['name']) ?></small>
                </td>
                <td><?= e($package['version'] ?: '-') ?></td>
                <td><?= to_persian_digits($package['files_count'] ?? 0) ?></td>
                <td><?= to_persian_digits($package['migrations_count'] ?? 0) ?></td>
                <td>
                  <?php if (!empty($package['is_valid'])): ?>
                    <span class="badge success">معتبر</span>
                  <?php else: ?>
                    <span class="badge danger">نامعتبر</span>
                    <small class="d-block"><?= e($package['error'] ?? '') ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="actions">
                    <?php if (!empty($package['is_valid'])): ?>
                      <button class="btn small success" type="button" data-open-modal="install-update-<?= e(md5($package['name'])) ?>">نصب</button>
                    <?php endif; ?>
                    <button class="btn small danger icon-only" type="button" data-open-modal="delete-update-<?= e(md5($package['name'])) ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($updatePackages)): ?><tr><td colspan="6" class="empty">هنوز بسته بروزرسانی بارگذاری نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <h3 class="proma-section-heading">بسته نصبی آسان</h3>
      <div class="notice info">این بسته برای نصب تازه ساخته می‌شود و فایل‌های حساس یا داده‌های واقعی مانند config/database.php، بکاپ‌ها، آپلودهای کاربران و لاگ‌ها داخل آن قرار نمی‌گیرد.</div>
      <div class="actions">
        <button class="btn success" type="submit" formaction="<?= e(url('install-package/create')) ?>" data-loading-text="در حال ساخت بسته نصبی...">ساخت بسته نصبی آسان</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>نام بسته</th><th>حجم</th><th>زمان ساخت</th><th>عملیات</th></tr></thead>
          <tbody>
            <?php foreach (($easyInstallPackages ?? []) as $package): ?>
              <tr>
                <td class="ltr"><?= e($package['name']) ?></td>
                <td><?= to_persian_digits(number_format(max(1, (int) ceil(($package['size'] ?? 0) / 1024)))) ?> KB</td>
                <td><?= e(jdatetime($package['created_at'])) ?></td>
                <td>
                  <div class="actions">
                    <a class="btn small secondary" href="<?= e(url('install-package/download/' . rawurlencode($package['name']))) ?>">دانلود</a>
                    <button class="btn small danger icon-only" type="button" data-open-modal="delete-easy-install-<?= e(md5($package['name'])) ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($easyInstallPackages)): ?><tr><td colspan="4" class="empty">هنوز بسته نصبی آسان ساخته نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'security' ? 'active' : '' ?>" data-settings-panel="security">
    <div class="card-header"><h2>امنیت</h2></div>
    <div class="card-body">
      <p style="color:var(--muted);margin-top:0">نشست‌ها با کوکی امن، بررسی نقش و محافظت ضد جعل درخواست کنترل می‌شوند. بکاپ و بازیابی فقط برای مدیر فعال است.</p>
      <button class="btn" type="submit">ذخیره تنظیمات</button>
    </div>
  </section>

  <section class="card proma-settings-panel <?= $activeTab === 'danger' ? 'active' : '' ?>" data-settings-panel="danger">
    <div class="card-header"><h2>حذف داده‌ها</h2></div>
    <div class="card-body grid">
      <div class="notice error">این عملیات داده‌های عملیاتی مانند مشتریان، قراردادها، اقساط، پرداخت‌ها، فایل‌های خصوصی، چت‌ها، اعلان‌ها، پرونده‌های حقوقی، ورود دیتا و سوابق تحلیل را حذف می‌کند. حساب مدیر فعلی، تنظیمات، فایل‌های بکاپ و لاگ بکاپ برای حفظ دسترسی و امکان برگشت نگه داشته می‌شوند.</div>
      <div class="actions">
        <button class="btn danger" type="button" data-open-modal="reset-data-modal">حذف داده‌های عملیاتی</button>
      </div>
    </div>
  </section>
</form>

<form id="settings-import-form" method="post" action="<?= e(url('imports/upload')) ?>" enctype="multipart/form-data" data-loading-form>
  <?= csrf_field() ?>
</form>

<form id="settings-update-upload-form" method="post" action="<?= e(url('updates/upload')) ?>" enctype="multipart/form-data" data-loading-form>
  <?= csrf_field() ?>
</form>

<div class="modal" id="delete-backup-logs-modal">
  <div class="modal-content">
    <div class="modal-header"><h3>حذف لاگ‌های بکاپ</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
    <form id="backup-log-delete-form" method="post" action="<?= e(url('backup/deleteLogs')) ?>" data-loading-form data-loading-text="در حال حذف لاگ‌ها...">
      <div class="modal-body grid">
        <?= csrf_field() ?>
        <?php $deleteBackupLogsCode = ConfirmationCode::hint('backup_logs_delete'); ?>
        <div class="notice warning">حذف لاگ‌های بکاپ روی فایل‌های بکاپ اثری ندارد. رویداد حذف در گزارش ممیزی اصلی سامانه باقی می‌ماند. برای تایید عدد <strong class="ltr"><?= e($deleteBackupLogsCode) ?></strong> را وارد کنید.</div>
        <label>دامنه حذف<select name="delete_scope"><option value="selected">فقط لاگ‌های انتخاب‌شده</option><option value="all">همه لاگ‌های بکاپ</option></select></label>
        <label>عدد تایید <span class="required">*</span><input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteBackupLogsCode) ?>"></label>
      </div>
      <div class="modal-footer"><button class="btn danger" type="submit"><i data-feather="trash-2"></i> حذف لاگ‌ها</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
    </form>
  </div>
</div>

<div class="modal" id="restore-backup-modal">
  <div class="modal-content">
    <div class="modal-header"><h3>تایید بازیابی بکاپ</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('backup/restore')) ?>" enctype="multipart/form-data" data-loading-form data-loading-text="در حال بازیابی...">
      <div class="modal-body grid">
        <?= csrf_field() ?>
        <?php $restoreUploadCode = ConfirmationCode::hint('backup_restore_upload'); ?>
        <div class="notice error">بازیابی، داده‌ها و فایل‌های فعلی را با محتوای بکاپ جایگزین می‌کند. قبل از شروع، سامانه به صورت خودکار از وضعیت فعلی بکاپ می‌گیرد. فایل می‌تواند <span class="ltr">.zip</span> یا <span class="ltr">.sql</span> باشد. برای تایید عدد <strong class="ltr"><?= e($restoreUploadCode) ?></strong> را وارد کنید.</div>
        <label>فایل بکاپ<input type="file" name="backup_file" accept=".zip,.sql,application/zip,application/sql,text/plain" required></label>
        <label>عدد تایید<input name="restore_confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($restoreUploadCode) ?>"></label>
      </div>
      <div class="modal-footer">
        <button class="btn danger" type="submit">شروع بازیابی</button>
        <button class="btn secondary" type="button" data-close-modal>بستن</button>
      </div>
    </form>
  </div>
</div>

<?php foreach (($backupFiles ?? []) as $file): ?>
  <div class="modal" id="restore-stored-backup-<?= e(md5($file['name'])) ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>بازیابی از بکاپ موجود</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('backup/restoreStored/' . rawurlencode($file['name']))) ?>" data-loading-form data-loading-text="در حال بازیابی بکاپ...">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $restoreStoredCode = ConfirmationCode::hint('backup_restore_stored_' . sha1($file['name'])); ?>
          <div class="notice error">سامانه از فایل «<span class="ltr"><?= e($file['name']) ?></span>» بازیابی می‌شود. قبل از شروع، یک بکاپ ایمنی تازه ساخته خواهد شد. برای تایید عدد <strong class="ltr"><?= e($restoreStoredCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="restore_confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($restoreStoredCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn warning" type="submit">بازیابی این نسخه</button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
  <div class="modal" id="delete-backup-<?= e(md5($file['name'])) ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف بکاپ</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('backup/delete/' . rawurlencode($file['name']))) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteBackupCode = ConfirmationCode::hint('backup_delete_' . sha1($file['name'])); ?>
          <div class="notice error">فایل بکاپ «<span class="ltr"><?= e($file['name']) ?></span>» حذف می‌شود. این عملیات قابل برگشت نیست. برای تایید عدد <strong class="ltr"><?= e($deleteBackupCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteBackupCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php foreach (($updatePackages ?? []) as $package): ?>
  <div class="modal" id="install-update-<?= e(md5($package['name'])) ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>نصب بروزرسانی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('updates/install/' . rawurlencode($package['name']))) ?>" data-loading-form data-loading-text="در حال نصب بروزرسانی...">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $installUpdateCode = ConfirmationCode::hint('update_install_' . sha1($package['name'])); ?>
          <div class="notice info">قبل از نصب، بکاپ ایمنی ساخته می‌شود. فقط فایل‌های معرفی‌شده در manifest نصب می‌شوند. برای تایید عدد <strong class="ltr"><?= e($installUpdateCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="update_confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($installUpdateCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn success" type="submit">نصب بروزرسانی</button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
  <div class="modal" id="delete-update-<?= e(md5($package['name'])) ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف بسته بروزرسانی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('updates/delete/' . rawurlencode($package['name']))) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteUpdateCode = ConfirmationCode::hint('update_delete_' . sha1($package['name'])); ?>
          <div class="notice error">بسته «<span class="ltr"><?= e($package['name']) ?></span>» از storage/updates حذف می‌شود. برای تایید عدد <strong class="ltr"><?= e($deleteUpdateCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteUpdateCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php foreach (($easyInstallPackages ?? []) as $package): ?>
  <div class="modal" id="delete-easy-install-<?= e(md5($package['name'])) ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف بسته نصبی آسان</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('install-package/delete/' . rawurlencode($package['name']))) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteEasyInstallCode = ConfirmationCode::hint('easy_install_delete_' . sha1($package['name'])); ?>
          <div class="notice error">بسته «<span class="ltr"><?= e($package['name']) ?></span>» از storage/releases حذف می‌شود. برای تایید عدد <strong class="ltr"><?= e($deleteEasyInstallCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteEasyInstallCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php foreach (($importBatches ?? []) as $batch): ?>
  <div class="modal" id="delete-import-batch-<?= (int) $batch['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف بسته ورود دیتا</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('imports/delete/' . (int) $batch['id'])) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteImportCode = ConfirmationCode::hint('import_batch_delete_' . (int) $batch['id']); ?>
          <div class="notice error">سوابق خام و پیش‌نمایش بسته «<?= e($batch['filename']) ?>» حذف می‌شود. داده‌هایی که قبلاً در مشتری/قرارداد ذخیره شده‌اند با این عملیات حذف نمی‌شوند. برای تایید عدد <strong class="ltr"><?= e($deleteImportCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteImportCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal" id="reset-data-modal">
  <div class="modal-content">
    <div class="modal-header"><h3>تأیید حذف داده‌های عملیاتی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('settings/resetData')) ?>" data-loading-form data-loading-text="در حال حذف داده‌ها...">
      <div class="modal-body grid">
        <?= csrf_field() ?>
        <?php $resetCode = ConfirmationCode::hint('system_reset_data'); ?>
        <div class="notice error">قبل از حذف داده‌ها، بکاپ کامل ساخته می‌شود. حساب مدیر فعلی و تنظیمات حفظ می‌شوند تا سامانه قابل ورود بماند. برای تایید عدد <strong class="ltr"><?= e($resetCode) ?></strong> را وارد کنید.</div>
        <label>رمز عبور مدیر<input type="password" name="admin_password" required autocomplete="current-password"></label>
        <label>عدد تایید<input name="reset_confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($resetCode) ?>"></label>
      </div>
      <div class="modal-footer">
        <button class="btn danger" type="submit">حذف داده‌ها</button>
        <button class="btn secondary" type="button" data-close-modal>بستن</button>
      </div>
    </form>
  </div>
</div>
