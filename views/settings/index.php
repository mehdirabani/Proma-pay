<?php
$detectedBase = rtrim(((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_base_url(), '/');
$callbackBase = rtrim($settings['callback_base_url'] ?: $detectedBase, '/');
$callbackUrl = $callbackBase . '/index.php?route=payments/callback';
?>
<form method="post" action="<?= e(url('settings/update')) ?>" class="grid">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card-header"><h2>تنظیمات سامانه</h2></div>
    <div class="card-body form-grid">
      <label>نام سامانه<input name="system_name" value="<?= e($settings['system_name']) ?>"></label>
      <label>متن نشان<input name="logo_text" value="<?= e($settings['logo_text']) ?>"></label>
      <label class="full">متن فوتر<input name="footer_text" value="<?= e($settings['footer_text'] ?? '') ?>"></label>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>تنظیمات قرارداد</h2></div>
    <div class="card-body form-grid">
      <label>پیشوند قرارداد<input name="contract_prefix" value="<?= e($settings['contract_prefix']) ?>" dir="ltr"></label>
      <label>سریال بعدی<input name="contract_next_serial" value="<?= e($settings['contract_next_serial']) ?>" inputmode="numeric"></label>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>تنظیمات مالی</h2></div>
    <div class="card-body form-grid">
      <label>نرخ جریمه ماهانه<input name="monthly_penalty_rate" value="<?= e($settings['monthly_penalty_rate']) ?>" inputmode="decimal"></label>
      <label>نرخ پاداش ماهانه<input name="monthly_reward_rate" value="<?= e($settings['monthly_reward_rate']) ?>" inputmode="decimal"></label>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>درگاه پرداخت</h2></div>
    <div class="card-body form-grid">
      <label>مرچنت زیبال<input name="zibal_merchant" value="<?= e($settings['zibal_merchant']) ?>" dir="ltr"></label>
      <label>نشانی پایه بازگشت<input name="callback_base_url" value="<?= e($settings['callback_base_url']) ?>" placeholder="<?= e($detectedBase) ?>" dir="ltr"></label>
      <div class="full notice info">
        <strong>نشانی نهایی بازگشت:</strong>
        <span class="ltr callback-url"><?= e($callbackUrl) ?></span>
        <p>در این فیلد فقط آدرس پایه سایت را وارد کنید؛ برنامه خودش مسیر <span class="ltr">/index.php?route=payments/callback</span> را به زیبال ارسال می‌کند.</p>
      </div>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>تنظیمات هوش مصنوعی</h2></div>
    <div class="card-body form-grid">
      <label>کلید اوپن‌روتر<input name="openrouter_api_key" type="password" autocomplete="off" value="<?= e($settings['openrouter_api_key']) ?>" dir="ltr"></label>
      <label>مدل اوپن‌روتر<input name="openrouter_model" value="<?= e($settings['openrouter_model']) ?>" dir="ltr"></label>
      <div class="full ai-test-actions">
        <button class="btn secondary" type="button" data-ai-test-url="<?= e(url('settings/testAi')) ?>">تست اتصال</button>
        <span class="ai-test-result" data-ai-test-result>برای تست، کلید و مدل را وارد کنید و دکمه تست اتصال را بزنید.</span>
      </div>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>کاربران و امنیت</h2></div>
    <div class="card-body">
      <p style="color:var(--muted);margin-top:0">نشست‌ها با کوکی امن، بررسی نقش و محافظت ضد جعل درخواست کنترل می‌شوند.</p>
      <button class="btn" type="submit">ذخیره همه تنظیمات</button>
    </div>
  </section>
</form>
