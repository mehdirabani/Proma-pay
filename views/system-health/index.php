<?php
$activePluginCount = count(array_filter($plugins ?? [], static function ($plugin) {
    return ($plugin['status'] ?? '') === 'active';
}));
$hasQueueProblem = !empty($queue['dead']) || !$queue['available'];
$hasRequestProblem = !empty($telemetry['errors']) || !empty($telemetry['external_failures']);
$systemHealthy = !empty($database['ok']) && !empty($storage['ok']) && !$hasQueueProblem;
?>

<section class="proma-section-header proma-page-header proma-health-hero">
  <div><span class="proma-page-eyebrow">پایش و نگهداری</span><h1>سلامت سامانه</h1><p>وضعیت هسته، دیتابیس، فضای ذخیره‌سازی، صف داخلی و افزونه‌ها بدون نمایش اطلاعات محرمانه سرور.</p></div>
  <div class="proma-section-header__actions">
    <a class="btn secondary" href="<?= e(url('system-health/report')) ?>"><?= proma_icon('file') ?><span>گزارش امن</span></a>
    <a class="btn" href="<?= e(url('system-health')) ?>"><?= proma_icon('history') ?><span>بررسی دوباره</span></a>
  </div>
</section>

<section class="proma-health-status <?= $systemHealthy ? 'is-healthy' : 'needs-attention' ?>">
  <span class="proma-health-status__icon"><?= proma_icon($systemHealthy ? 'check' : 'archive') ?></span>
  <div><strong><?= $systemHealthy ? 'سرویس‌های اصلی آماده هستند' : 'بخشی از سامانه نیازمند بررسی است' ?></strong><p><?= $systemHealthy ? 'اتصال دیتابیس، دسترسی فضای خصوصی و صف پردازش در آخرین بررسی پاسخ مناسب داده‌اند.' : 'جزئیات سرویس‌های ناموفق در کارت‌های پایین مشخص شده است؛ پیش از عملیات مالی آن‌ها را بررسی کنید.' ?></p></div>
  <time>آخرین بررسی: <?= e(jdatetime($checkedAt)) ?></time>
</section>

<section class="proma-health-service-grid">
  <article class="proma-health-service"><span><?= proma_icon('file') ?></span><div><small>هسته سامانه</small><strong><?= e($runtime['core_version']) ?></strong><p><?= e($runtime['server']) ?> · PHP <?= e($runtime['php_version']) ?></p></div><i class="proma-health-dot ok" title="آماده"></i></article>
  <article class="proma-health-service"><span><?= proma_icon('chart') ?></span><div><small>دیتابیس</small><strong><?= $database['ok'] ? to_persian_digits($database['response_ms']) . ' میلی‌ثانیه' : 'عدم پاسخ' ?></strong><p dir="ltr"><?= e($database['version']) ?></p></div><i class="proma-health-dot <?= $database['ok'] ? 'ok' : 'error' ?>" title="<?= $database['ok'] ? 'آماده' : 'ناموفق' ?>"></i></article>
  <article class="proma-health-service"><span><?= proma_icon('archive') ?></span><div><small>فضای خصوصی</small><strong><?= $storage['ok'] ? 'خواندن و نوشتن مجاز' : 'نیازمند اصلاح دسترسی' ?></strong><p>خواندن <?= $storage['readable'] ? 'مجاز' : 'ناموفق' ?> · نوشتن <?= $storage['writable'] ? 'مجاز' : 'ناموفق' ?></p></div><i class="proma-health-dot <?= $storage['ok'] ? 'ok' : 'error' ?>"></i></article>
  <article class="proma-health-service"><span><?= proma_icon('history') ?></span><div><small>صف پردازش داخلی</small><strong><?= $queue['available'] ? to_persian_digits($queue['pending']) . ' در انتظار' : 'در دسترس نیست' ?></strong><p><?= to_persian_digits($queue['dead']) ?> مورد متوقف</p></div><i class="proma-health-dot <?= $hasQueueProblem ? 'error' : 'ok' ?>"></i></article>
</section>

<div class="content-grid two proma-health-grid">
  <section class="card proma-health-panel">
    <div class="card-header"><div><h2>افزونه‌های سامانه</h2><p><?= to_persian_digits($activePluginCount) ?> افزونه فعال از <?= to_persian_digits(count($plugins ?? [])) ?> افزونه ثبت‌شده</p></div><a class="btn small secondary" href="<?= e(url('plugins')) ?>">مدیریت افزونه‌ها</a></div>
    <div class="card-body">
      <?php if (empty($plugins)): ?><div class="empty">افزونه ثبت‌شده‌ای وجود ندارد یا رجیستری در دسترس نیست.</div><?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>افزونه</th><th>نسخه</th><th>وضعیت</th></tr></thead><tbody>
        <?php foreach ($plugins as $plugin): ?><tr><td><strong><?= e($plugin['name']) ?></strong><?php if ($plugin['last_error']): ?><small class="proma-health-error-text"><?= e($plugin['last_error']) ?></small><?php endif; ?></td><td dir="ltr"><?= e($plugin['version']) ?></td><td><span class="badge <?= $plugin['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(PluginStatus::label($plugin['status'])) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card proma-health-panel">
    <div class="card-header"><div><h2>رخدادهای امروز</h2><p>خلاصه امن درخواست‌های کند و خطاهای ثبت‌شده</p></div><span class="badge <?= $hasRequestProblem ? 'warning' : 'success' ?>"><?= $hasRequestProblem ? 'نیازمند بررسی' : 'عادی' ?></span></div>
    <div class="card-body">
      <?php if (!$telemetry['available']): ?><div class="empty">برای امروز هنوز داده تله‌متری قابل نمایش ثبت نشده است.</div><?php else: ?>
        <div class="proma-health-counters"><div><span>درخواست کند</span><strong><?= to_persian_digits(count($telemetry['slow'])) ?></strong></div><div><span>خطاهای اخیر</span><strong><?= to_persian_digits(count($telemetry['errors'])) ?></strong></div><div><span>خطای سرویس بیرونی</span><strong><?= to_persian_digits($telemetry['external_failures']) ?></strong></div></div>
        <?php if (!empty($telemetry['slow'])): ?><div class="table-responsive"><table><thead><tr><th>مسیر</th><th>مدت</th><th>شناسه پیگیری</th></tr></thead><tbody><?php foreach (array_reverse($telemetry['slow']) as $item): ?><tr><td dir="ltr"><?= e($item['route']) ?></td><td><?= to_persian_digits($item['duration_ms']) ?> ms</td><td dir="ltr"><small><?= e($item['request_id']) ?></small></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="card proma-health-connectivity">
  <div class="card-header card-no-border">
    <div><h2>تشخیص قطع ارتباط هاست</h2><p>مرز میان خطای برنامه و مسدودی پیش از PHP</p></div>
    <span class="badge info" dir="ltr"><?= e($network['request_id']) ?></span>
  </div>
  <div class="card-body">
    <div class="proma-health-connectivity__facts">
      <span><small>IP مشاهده‌شده</small><strong dir="ltr"><?= e($network['client_ip']) ?></strong></span>
      <span><small>نشست PHP</small><strong><?= e($runtime['session_handler']) ?></strong></span>
      <span><small>OPcache</small><strong><?= $runtime['opcache_enabled'] ? 'فعال' : 'غیرفعال' ?></strong></span>
      <span><small>HTTPS</small><strong><?= $network['https'] ? 'فعال' : 'غیرفعال' ?></strong></span>
    </div>
    <div class="proma-health-note"><span><?= proma_icon('archive') ?></span><p>اگر مرورگر خطای <bdi dir="ltr">ERR_CONNECTION_TIMED_OUT</bdi> نشان دهد و هیچ کد HTTP دریافت نشود، درخواست به PHP نرسیده است. گزارش امن را همراه ساعت رخداد برای بررسی ModSecurity، CSF/LFD، فایروال و محدودیت workerها به هاست بدهید.</p></div>
    <div class="actions">
      <a class="btn small secondary" href="<?= e($network['live_endpoint']) ?>" target="_blank" rel="noopener">آزمون زنده</a>
      <a class="btn small secondary" href="<?= e($network['ready_endpoint']) ?>" target="_blank" rel="noopener">آزمون آمادگی</a>
    </div>
  </div>
</section>
