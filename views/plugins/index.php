<?php
$plugins = $plugins ?? [];
?>
<div class="page-title"><div><h2>پلاگین‌ها</h2><p class="text-muted">افزونه‌های معتبر را فقط از منبع قابل اعتماد نصب کنید.</p></div></div>

<section class="card proma-plugin-warning">
  <strong>هشدار امنیتی</strong>
  <span>نصب پلاگین به معنی اجرای کد PHP آن روی سرور است. بسته قبل از انتقال به پوشه افزونه‌ها از نظر مسیر ناامن، فایل خطرناک و Manifest بررسی می‌شود.</span>
</section>

<section class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h3 class="mb-1">مدیریت چرخه افزونه</h3><small class="text-muted">Plugin API <?= e(plugin_api_version()) ?></small></div>
    <form method="post" action="<?= e(url('plugins/upload')) ?>" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
      <?= csrf_field() ?>
      <label class="mb-0"><span class="sr-only">فایل ZIP پلاگین</span><input type="file" name="plugin_zip" accept=".zip,application/zip" required></label>
      <button class="btn btn-primary" type="submit"><i data-feather="upload"></i> بارگذاری و بررسی</button>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>افزونه</th><th>نسخه</th><th>وضعیت</th><th>مسیر</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($plugins as $plugin): ?>
        <?php $status = (string) ($plugin['status'] ?? 'discovered'); ?>
        <tr>
          <td><strong><?= e($plugin['name'] ?? $plugin['id'] ?? '-') ?></strong><br><small class="text-muted" dir="ltr"><?= e($plugin['id'] ?? '-') ?></small><?php if (!empty($plugin['last_error'])): ?><div class="text-danger small mt-1"><?= e($plugin['last_error']) ?></div><?php endif; ?></td>
          <td dir="ltr">v<?= e($plugin['version'] ?? '-') ?></td>
          <td><span class="badge <?= e(badge_class($status)) ?>"><?= e(status_label($status)) ?></span></td>
          <td><code dir="ltr"><?= e($plugin['path'] ?? '-') ?></code></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <?php if ($status === 'discovered'): ?><form method="post" action="<?= e(url('plugins/install/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">نصب</button></form><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive'], true)): ?><form method="post" action="<?= e(url('plugins/activate/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-success" type="submit">فعال‌سازی</button></form><?php endif; ?>
              <?php if ($status === 'active'): ?><form method="post" action="<?= e(url('plugins/deactivate/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-warning" type="submit">غیرفعال‌سازی</button></form><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive', 'failed'], true)): ?><a class="btn btn-sm btn-light" href="<?= e(url('plugins/health/' . rawurlencode($plugin['id'] ?? ''))) ?>">بررسی سلامت</a><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive'], true)): ?><form method="post" action="<?= e(url('plugins/uninstall/' . rawurlencode($plugin['id'] ?? ''))) ?>" onsubmit="return confirm('ثبت افزونه غیرفعال شود؟ داده‌های افزونه حذف نمی‌شوند.')"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">حذف افزونه</button></form><?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$plugins): ?><tr><td colspan="5" class="empty">افزونه‌ای در پوشه plugins پیدا نشد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
