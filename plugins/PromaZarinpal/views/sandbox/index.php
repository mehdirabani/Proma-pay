<section class="proma-zp-page">
  <header class="proma-zp-hero"><div class="proma-zp-hero-icon"><i data-feather="shield"></i></div><div><span>محیط ایزوله تست</span><h1>زرین‌پال آزمایشی</h1><p>تراکنش‌های آزمایشی با Merchant، endpoint و برچسب جدا از محیط عملیاتی ثبت می‌شوند.</p></div><span class="proma-zp-state <?= ($settings['environment'] ?? '') === 'sandbox' ? 'is-info' : 'is-muted' ?>"><?= ($settings['environment'] ?? '') === 'sandbox' ? 'محیط فعال' : 'غیرفعال' ?></span></header>
  <div class="notice warning">درگاه زرین‌پال در حالت آزمایشی قرار دارد و پرداخت واقعی انجام نمی‌شود. اثر مالی روی اقساط نیز به صورت پیش‌فرض غیرفعال است.</div>
  <section class="proma-zp-card"><div class="proma-zp-card-head"><div><h2>آمادگی محیط</h2><p>برای اجرای پرداخت آزمایشی، Merchant ID آزمایشی را در تنظیمات ثبت و محیط را روی «آزمایشی» قرار دهید.</p></div><i data-feather="check-circle"></i></div><div class="proma-zp-checks"><span class="<?= ($settings['sandbox_merchant_id_configured'] ?? '0') === '1' ? 'is-ready' : '' ?>"><i data-feather="lock"></i> Merchant آزمایشی</span><span class="<?= ($settings['environment'] ?? '') === 'sandbox' ? 'is-ready' : '' ?>"><i data-feather="sliders"></i> انتخاب محیط آزمایشی</span><span class="<?= ($settings['enabled'] ?? '0') === '1' ? 'is-ready' : '' ?>"><i data-feather="power"></i> فعال‌سازی درگاه</span></div></section>
  <section class="proma-zp-card"><div class="proma-zp-card-head"><div><h2>Endpointهای رسمی</h2><p>افزونه فقط از API نسخه ۴ و دامنه رسمی sandbox استفاده می‌کند.</p></div><i data-feather="link"></i></div><div class="proma-zp-endpoints"><?php foreach ($endpoints as $label => $endpoint): ?><div><strong><?= e(strtoupper($label)) ?></strong><code><?= e($endpoint) ?></code></div><?php endforeach; ?></div></section>
  <section class="proma-zp-card">
    <div class="proma-zp-card-head"><div><h2>ساخت تراکنش واقعی sandbox</h2><p>اطلاعات قسط دوباره از سرور خوانده می‌شود و قبل از Verify هیچ اثر مالی ایجاد نمی‌شود.</p></div><i data-feather="play-circle"></i></div>
    <form method="post" action="<?= e(url('plugin/zarinpal/sandbox/create')) ?>" class="proma-zp-grid three" data-disable-on-submit>
      <?= csrf_field() ?>
      <label>شناسه قسط <input type="number" name="installment_id" min="1" required inputmode="numeric" placeholder="برای نمونه ۱۲۵۰"></label>
      <label>مبلغ آزمایشی تومان <input name="amount" data-money inputmode="numeric" placeholder="خالی = کل مانده قسط"></label>
      <label>کد تأیید <input name="confirmation" required inputmode="numeric" autocomplete="off" placeholder="<?= e($confirmationCode) ?>"><small>برای تأیید، عدد <?= to_persian_digits($confirmationCode) ?> را وارد کنید.</small></label>
      <div class="notice <?= ($settings['sandbox_financial_effects'] ?? '0') === '1' ? 'danger' : 'info' ?> full">اثر مالی sandbox: <strong><?= ($settings['sandbox_financial_effects'] ?? '0') === '1' ? 'فعال؛ فقط برای تست کنترل‌شده' : 'غیرفعال؛ مانده اقساط تغییر نمی‌کند' ?></strong></div>
      <div class="proma-zp-actions full"><button class="btn btn-primary" type="submit" data-submit-label="در حال ساخت درخواست..."><i data-feather="external-link"></i> ساخت و انتقال به sandbox</button></div>
    </form>
  </section>
  <div class="proma-zp-actions"><a class="btn btn-primary" href="<?= e(url('plugin/zarinpal/settings')) ?>"><i data-feather="settings"></i> تنظیم محیط آزمایشی</a><a class="btn btn-light" href="<?= e(url('installments/panel')) ?>">رفتن به اقساط</a></div>
</section>
