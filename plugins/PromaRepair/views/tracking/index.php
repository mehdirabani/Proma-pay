<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><i data-feather="map-pin"></i></span><div class="pr-hero-copy"><small>Public Repair Tracking</small><h2>پیگیری تعمیر</h2><p>کد رهگیری و شماره موبایلی را وارد کنید که هنگام پذیرش ثبت شده است.</p></div></header>
  <section class="pr-card">
    <?php if (!empty($errorMessage)): ?><div class="alert alert-light-danger" role="alert"><?= e($errorMessage) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('plugin/repair/track')) ?>" class="pr-grid two"><?= csrf_field() ?>
      <label class="pr-field"><span>کد رهگیری</span><input name="tracking_code" required autocomplete="off" dir="ltr" value="<?= e($trackingCode ?? '') ?>" placeholder="PR-REP-1405-000124"></label>
      <label class="pr-field"><span>شماره موبایل مشتری</span><input name="mobile" inputmode="tel" required autocomplete="tel"></label>
      <button class="btn primary" type="submit">نمایش وضعیت</button>
    </form>
  </section>
</div>
