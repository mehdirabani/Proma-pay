<?php
$environment = $settings['environment'] ?? 'production';
$enabled = ($settings['enabled'] ?? '0') === '1';
?>
<section class="proma-zp-page">
  <header class="proma-zp-hero">
    <div class="proma-zp-hero-icon"><i data-feather="shield"></i></div>
    <div><span>درگاه پرداخت افزونه‌ای</span><h1>تنظیمات زرین‌پال</h1><p>محیط عملیاتی و آزمایشی کاملاً جدا نگه‌داری می‌شوند و Merchant ID به صورت رمزگذاری‌شده ذخیره می‌شود.</p></div>
    <span class="proma-zp-state <?= $enabled ? 'is-success' : 'is-muted' ?>"><?= $enabled ? 'فعال' : 'غیرفعال' ?></span>
  </header>
  <?php if ($environment === 'sandbox'): ?><div class="notice warning">درگاه زرین‌پال در حالت آزمایشی قرار دارد و پرداخت واقعی انجام نمی‌شود.</div><?php endif; ?>

  <form method="post" action="<?= e(url('plugin/zarinpal/settings/save')) ?>" class="proma-zp-form" data-zarinpal-settings>
    <?= csrf_field() ?>
    <section class="proma-zp-card">
      <div class="proma-zp-card-head"><div><h2>وضعیت و محیط</h2><p>فعال‌سازی عملیاتی فقط پس از ثبت Merchant همان محیط ممکن است.</p></div><i data-feather="toggle-right"></i></div>
      <div class="proma-zp-grid three">
        <label class="proma-zp-toggle"><input type="checkbox" name="enabled" value="1"<?= checked($enabled) ?>><span></span><strong>فعال‌سازی زرین‌پال</strong></label>
        <label>حالت درگاه
          <select name="environment" data-zarinpal-environment>
            <option value="production"<?= selected($environment, 'production') ?>>عملیاتی</option>
            <option value="sandbox"<?= selected($environment, 'sandbox') ?>>آزمایشی</option>
          </select>
        </label>
        <label>واحد پول
          <select name="currency"><option value="IRT"<?= selected($settings['currency'] ?? 'IRT', 'IRT') ?>>تومان (IRT)</option><option value="IRR"<?= selected($settings['currency'] ?? 'IRT', 'IRR') ?>>ریال (IRR)</option></select>
        </label>
        <label>Merchant ID عملیاتی
          <input type="password" name="production_merchant_id" value="<?= e($settings['production_merchant_id'] ?? '') ?>" dir="ltr" autocomplete="new-password" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
          <small><?= ($settings['production_merchant_id_configured'] ?? '0') === '1' ? 'Merchant عملیاتی ذخیره شده است.' : 'هنوز تنظیم نشده است.' ?></small>
        </label>
        <label>Merchant ID آزمایشی
          <input type="password" name="sandbox_merchant_id" value="<?= e($settings['sandbox_merchant_id'] ?? '') ?>" dir="ltr" autocomplete="new-password" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
          <small><?= ($settings['sandbox_merchant_id_configured'] ?? '0') === '1' ? 'Merchant آزمایشی ذخیره شده است.' : 'هنوز تنظیم نشده است.' ?></small>
        </label>
        <label>عنوان درگاه<input name="gateway_title" value="<?= e($settings['gateway_title'] ?? 'زرین‌پال') ?>" maxlength="80"></label>
      </div>
    </section>

    <section class="proma-zp-card">
      <div class="proma-zp-card-head"><div><h2>درخواست پرداخت</h2><p>فقط داده‌های انتخاب‌شده در metadata ارسال می‌شوند؛ کد ملی و اطلاعات مالی مشتری هرگز ارسال نمی‌شود.</p></div><i data-feather="send"></i></div>
      <div class="proma-zp-grid two">
        <label class="full">الگوی توضیح تراکنش<input name="description_template" value="<?= e($settings['description_template'] ?? '') ?>" maxlength="255" dir="rtl"><small>{{contract_number}}، {{installment_number}}، {{customer_name}}، {{payment_group_number}}، {{application_name}}</small></label>
        <label>حداقل مبلغ تومان<input name="minimum_amount_toman" value="<?= e($settings['minimum_amount_toman'] ?? '1000') ?>" data-money inputmode="numeric"></label>
        <label>حداکثر مبلغ تومان<input name="maximum_amount_toman" value="<?= e($settings['maximum_amount_toman'] ?? '1000000000') ?>" data-money inputmode="numeric"></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="send_mobile" value="1"<?= checked($settings['send_mobile'] ?? '1', '1') ?>><span></span><strong>ارسال شماره موبایل</strong></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="send_email" value="1"<?= checked($settings['send_email'] ?? '0', '1') ?>><span></span><strong>ارسال ایمیل</strong></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="send_order_id" value="1"<?= checked($settings['send_order_id'] ?? '1', '1') ?>><span></span><strong>ارسال شناسه سفارش</strong></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="is_default" value="1"<?= checked($settings['is_default'] ?? '0', '1') ?>><span></span><strong>درگاه پیش‌فرض</strong></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="allow_customer_selection" value="1"<?= checked($settings['allow_customer_selection'] ?? '1', '1') ?>><span></span><strong>نمایش انتخاب درگاه به مشتری</strong></label>
      </div>
    </section>

    <section class="proma-zp-card">
      <div class="proma-zp-card-head"><div><h2>اتصال و ثبت فنی</h2><p>دامنه Callback باید با دامنه همین سامانه یکسان و HTTPS باشد.</p></div><i data-feather="lock"></i></div>
      <div class="proma-zp-grid three">
        <label class="full">آدرس پایه Callback<input name="callback_base_url" value="<?= e($settings['callback_base_url'] ?? '') ?>" dir="ltr" placeholder="<?= e(detected_base_url()) ?>"><small>آدرس نهایی: <?= e($callbackUrl) ?></small></label>
        <label>مهلت اتصال (ثانیه)<input type="number" name="connect_timeout" min="3" max="30" value="<?= (int) ($settings['connect_timeout'] ?? 10) ?>"></label>
        <label>مهلت پاسخ (ثانیه)<input type="number" name="response_timeout" min="3" max="60" value="<?= (int) ($settings['response_timeout'] ?? 30) ?>"></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="technical_logging" value="1"<?= checked($settings['technical_logging'] ?? '1', '1') ?>><span></span><strong>ثبت لاگ فنی امن</strong></label>
        <label class="proma-zp-toggle"><input type="checkbox" name="show_technical_errors_admin" value="1"<?= checked($settings['show_technical_errors_admin'] ?? '1', '1') ?>><span></span><strong>جزئیات فنی فقط برای مدیر</strong></label>
        <?php if (PluginManager::can('plugin.proma-zarinpal.use_zarinpal_sandbox')): ?><label class="proma-zp-toggle"><input type="checkbox" name="sandbox_financial_effects" value="1"<?= checked($settings['sandbox_financial_effects'] ?? '0', '1') ?>><span></span><strong>اعمال اثر مالی پرداخت آزمایشی</strong></label><?php endif; ?>
        <div class="notice warning full">این گزینه فقط برای تست توسعه است. در حالت پیش‌فرض، پرداخت sandbox تأیید می‌شود اما مانده اقساط واقعی را تغییر نمی‌دهد.</div>
      </div>
    </section>

    <div class="proma-zp-actions"><button class="btn btn-primary" type="submit"><i data-feather="save"></i> ذخیره تنظیمات</button></div>
  </form>
</section>
