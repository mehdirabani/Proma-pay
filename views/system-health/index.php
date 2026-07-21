<section class="page-header proma-page-header">
  <div><span class="proma-page-eyebrow">پایش داخلی</span><h1>سلامت سامانه</h1><p>نمای امن از سرویس‌های اصلی و رخدادهای کند اخیر؛ جزئیات محرمانه و مسیرهای سرور نمایش داده نمی‌شوند.</p></div>
  <a class="btn secondary" href="<?= e(url('system-health')) ?>"><i data-feather="refresh-cw"></i><span>بررسی دوباره</span></a>
</section>

<section class="stats-grid proma-health-overview">
  <article class="stat-card"><span class="stat-icon"><i data-feather="activity"></i></span><div><small>هسته</small><strong><?= e($runtime['core_version']) ?></strong><span><?= e($runtime['server']) ?> · PHP <?= e($runtime['php_version']) ?></span></div></article>
  <article class="stat-card"><span class="stat-icon"><i data-feather="database"></i></span><div><small>دیتابیس</small><strong><?= $database['ok'] ? to_persian_digits($database['response_ms']) . ' ms' : 'ناموفق' ?></strong><span><?= e($database['version']) ?></span></div></article>
  <article class="stat-card"><span class="stat-icon"><i data-feather="hard-drive"></i></span><div><small>فضای خصوصی</small><strong><?= $storage['ok'] ? 'آماده' : 'نیازمند بررسی' ?></strong><span>خواندن <?= $storage['readable'] ? 'مجاز' : 'ناموفق' ?> · نوشتن <?= $storage['writable'] ? 'مجاز' : 'ناموفق' ?></span></div></article>
  <article class="stat-card"><span class="stat-icon"><i data-feather="inbox"></i></span><div><small>صف داخلی</small><strong><?= $queue['available'] ? to_persian_digits($queue['pending']) . ' در انتظار' : 'در دسترس نیست' ?></strong><span><?= to_persian_digits($queue['dead']) ?> مورد متوقف</span></div></article>
</section>

<div class="content-grid two proma-health-grid">
  <section class="card">
    <div class="card-header"><div><h2>افزونه‌ها</h2><p>وضعیت ثبت‌شده افزونه‌های اختیاری</p></div></div>
    <div class="card-body">
      <?php if (empty($plugins)): ?><div class="empty">افزونه ثبت‌شده‌ای وجود ندارد یا رجیستری در دسترس نیست.</div><?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>افزونه</th><th>نسخه</th><th>وضعیت</th></tr></thead><tbody>
        <?php foreach ($plugins as $plugin): ?><tr><td><strong><?= e($plugin['name']) ?></strong><?php if ($plugin['last_error']): ?><small class="text-danger"><?= e($plugin['last_error']) ?></small><?php endif; ?></td><td dir="ltr"><?= e($plugin['version']) ?></td><td><span class="badge <?= $plugin['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(PluginStatus::label($plugin['status'])) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><div><h2>رخدادهای درخواست</h2><p>آخرین داده‌های ساختاریافته امروز</p></div></div>
    <div class="card-body">
      <?php if (!$telemetry['available']): ?><div class="notice info">هنوز تله‌متری قابل نمایش برای امروز ثبت نشده است.</div><?php else: ?>
        <div class="proma-admin-status-grid"><div><span>درخواست کند</span><strong><?= to_persian_digits(count($telemetry['slow'])) ?></strong></div><div><span>خطاهای اخیر</span><strong><?= to_persian_digits(count($telemetry['errors'])) ?></strong></div><div><span>خطای سرویس بیرونی</span><strong><?= to_persian_digits($telemetry['external_failures']) ?></strong></div></div>
        <?php if (!empty($telemetry['slow'])): ?><div class="table-responsive"><table><thead><tr><th>مسیر</th><th>مدت</th><th>شناسه پیگیری</th></tr></thead><tbody><?php foreach (array_reverse($telemetry['slow']) as $item): ?><tr><td dir="ltr"><?= e($item['route']) ?></td><td><?= to_persian_digits($item['duration_ms']) ?> ms</td><td dir="ltr"><small><?= e($item['request_id']) ?></small></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="notice info">آخرین بررسی: <?= e(jdatetime($checkedAt)) ?>. نبود پاسخ TCP/HTTP در زمان قطعی شبکه از داخل PHP قابل تشخیص یا نمایش نیست و برای آن باید لاگ وب‌سرور و فایروال بررسی شود.</section>
