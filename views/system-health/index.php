<?php
$stateLabels = ['healthy' => 'پایدار', 'warning' => 'نیازمند توجه', 'critical' => 'اختلال'];
$stateLabel = $stateLabels[$healthState ?? 'warning'] ?? 'نیازمند بررسی';
$stateTone = ($healthState ?? '') === 'healthy' ? 'success' : (($healthState ?? '') === 'critical' ? 'danger' : 'warning');
?>
<section class="proma-health-page">
  <header class="proma-page-hero proma-health-hero">
    <div>
      <span class="proma-page-kicker"><?= proma_icon('chart') ?> پایش داخلی و محرمانه</span>
      <h2>سلامت سامانه</h2>
      <p>وضعیت هسته، دیتابیس، فضای خصوصی، صف پردازش و رخدادهای کند را در یک نمای عملیاتی بررسی کنید.</p>
    </div>
    <div class="proma-health-hero__actions">
      <span class="proma-health-state <?= e($stateTone) ?>"><i></i><?= e($stateLabel) ?></span>
      <a class="btn secondary" href="<?= e(url('system-health/diagnostics')) ?>"><?= proma_icon('download') ?><span>بسته تشخیصی</span></a>
      <a class="proma-icon-button" href="<?= e(url('system-health')) ?>" title="بررسی دوباره" aria-label="بررسی دوباره"><?= proma_icon('history') ?></a>
    </div>
  </header>

  <section class="proma-health-overview" aria-label="خلاصه سلامت سامانه">
    <article class="proma-health-metric">
      <span><?= proma_icon('check') ?></span><div><small>هسته</small><strong><?= e($runtime['core_version']) ?></strong><em>PHP <?= e($runtime['php_version']) ?> · <?= e($runtime['server']) ?></em></div>
    </article>
    <article class="proma-health-metric <?= $database['ok'] ? '' : 'is-danger' ?>">
      <span><?= proma_icon('chart') ?></span><div><small>پاسخ دیتابیس</small><strong><?= $database['ok'] ? to_persian_digits($database['response_ms']) . ' ms' : 'ناموفق' ?></strong><em><?= e($database['version']) ?></em></div>
    </article>
    <article class="proma-health-metric <?= $storage['ok'] ? '' : 'is-danger' ?>">
      <span><?= proma_icon('file') ?></span><div><small>فضای خصوصی</small><strong><?= $storage['ok'] ? 'آماده' : 'نیازمند بررسی' ?></strong><em>خواندن <?= $storage['readable'] ? 'مجاز' : 'ناموفق' ?> · نوشتن <?= $storage['writable'] ? 'مجاز' : 'ناموفق' ?></em></div>
    </article>
    <article class="proma-health-metric <?= !$queue['available'] || !empty($queue['dead']) ? 'is-warning' : '' ?>">
      <span><?= proma_icon('history') ?></span><div><small>صف داخلی</small><strong><?= $queue['available'] ? to_persian_digits($queue['pending']) . ' در انتظار' : 'در دسترس نیست' ?></strong><em><?= to_persian_digits($queue['dead']) ?> مورد متوقف</em></div>
    </article>
  </section>

  <div class="proma-health-layout">
    <section class="card proma-health-panel">
      <div class="card-header"><div><h3>بررسی سرویس‌ها</h3><p>موارد بحرانی با رنگ وضعیت مشخص می‌شوند.</p></div></div>
      <div class="card-body proma-health-checks">
        <?php foreach ([
          ['دیتابیس', $database['ok'], $database['ok'] ? 'اتصال برقرار است.' : 'اتصال یا کوئری آزمایشی ناموفق بود.'],
          ['فضای ذخیره‌سازی', $storage['ok'], $storage['ok'] ? 'مسیر خصوصی خوانا و قابل نوشتن است.' : 'سطح دسترسی مسیر storage باید بررسی شود.'],
          ['صف داخلی', $queue['available'], $queue['available'] ? to_persian_digits($queue['pending']) . ' کار در انتظار پردازش است.' : 'جدول صف یا اتصال آن در دسترس نیست.'],
          ['افزونه‌ها', empty($pluginErrorCount), empty($pluginErrorCount) ? 'خطای فعال ثبت نشده است.' : to_persian_digits($pluginErrorCount) . ' افزونه نیازمند بررسی است.'],
        ] as $check): ?>
          <div class="<?= $check[1] ? 'is-ok' : 'is-failed' ?>"><span><?= $check[1] ? proma_icon('check') : proma_icon('slash') ?></span><div><strong><?= e($check[0]) ?></strong><small><?= e($check[2]) ?></small></div><b><?= $check[1] ? 'سالم' : 'بررسی شود' ?></b></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card proma-health-panel proma-network-panel">
      <div class="card-header"><div><h3>تشخیص اختلال IP و Timeout</h3><p>مرز بین PHP و لایه شبکه را شفاف می‌کند.</p></div></div>
      <div class="card-body">
        <div class="proma-network-facts">
          <span><small>رسیدن درخواست به PHP</small><strong class="text-success">تأیید شد</strong></span>
          <span><small>محدودسازی سراسری IP در ورود</small><strong>غیرفعال</strong></span>
          <span><small>شبکه فعلی</small><strong dir="ltr"><?= e($network['remote_ip_masked'] ?? 'نامشخص') ?></strong></span>
          <span><small>شناسه پیگیری</small><strong dir="ltr"><?= e($network['request_id'] ?? '-') ?></strong></span>
        </div>
        <div class="notice warning">اگر فقط یک اینترنت با <code>ERR_CONNECTION_TIMED_OUT</code> مواجه است و حتی <code>/health/live</code> پاسخ نمی‌دهد، درخواست قبل از PHP در فایروال هاست، ModSecurity، CDN یا لایه شبکه متوقف شده است. بسته تشخیصی این صفحه را کنار لاگ همان بازه زمانی بررسی کنید.</div>
      </div>
    </section>
  </div>

  <div class="proma-health-layout">
    <section class="card proma-health-panel">
      <div class="card-header"><div><h3>رخدادهای کند اخیر</h3><p>حداکثر ده رخداد ثبت‌شده امروز</p></div><span class="badge warning"><?= to_persian_digits(count($telemetry['slow'] ?? [])) ?></span></div>
      <div class="table-wrap">
        <table><thead><tr><th>مسیر</th><th>مدت</th><th>وضعیت</th><th>شناسه پیگیری</th></tr></thead><tbody>
        <?php foreach (array_reverse($telemetry['slow'] ?? []) as $item): ?><tr><td dir="ltr"><?= e($item['route']) ?></td><td><?= to_persian_digits($item['duration_ms']) ?> ms</td><td><span class="badge <?= (int) $item['status'] >= 500 ? 'danger' : 'warning' ?>"><?= to_persian_digits($item['status']) ?></span></td><td dir="ltr"><small><?= e($item['request_id']) ?></small></td></tr><?php endforeach; ?>
        <?php if (empty($telemetry['slow'])): ?><tr><td colspan="4" class="empty">رخداد کندی برای امروز ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </section>

    <section class="card proma-health-panel">
      <div class="card-header"><div><h3>افزونه‌ها</h3><p>وضعیت نسخه و آخرین خطای ثبت‌شده</p></div></div>
      <div class="table-wrap">
        <table><thead><tr><th>افزونه</th><th>نسخه</th><th>وضعیت</th></tr></thead><tbody>
        <?php foreach ($plugins as $plugin): ?><tr><td><strong><?= e($plugin['name']) ?></strong><?php if ($plugin['last_error']): ?><small class="text-danger"><?= e($plugin['last_error']) ?></small><?php endif; ?></td><td dir="ltr"><?= e($plugin['version']) ?></td><td><span class="badge <?= $plugin['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(PluginStatus::label($plugin['status'])) ?></span></td></tr><?php endforeach; ?>
        <?php if (empty($plugins)): ?><tr><td colspan="3" class="empty">افزونه ثبت‌شده‌ای وجود ندارد.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </section>
  </div>

  <footer class="proma-health-footer">آخرین بررسی: <?= e(jdatetime($checkedAt)) ?>. مسیر کامل سرور، کلیدها و اطلاعات اتصال در این صفحه یا بسته تشخیصی نمایش داده نمی‌شوند.</footer>
</section>
