<?php
$plugins = $plugins ?? [];
?>
<div class="page-title"><div><h2>پلاگین‌ها</h2><p class="text-muted">افزونه‌های معتبر را فقط از منبع قابل اعتماد نصب کنید.</p></div></div>

<section class="card proma-plugin-warning">
  <span class="proma-plugin-warning__icon" aria-hidden="true"><i data-feather="shield"></i></span>
  <span class="proma-plugin-warning__content">
    <strong class="proma-plugin-warning__title">هشدار امنیتی</strong>
    <span class="proma-plugin-warning__text">نصب پلاگین به معنی اجرای کد PHP آن روی سرور است. بسته قبل از انتقال به پوشه افزونه‌ها از نظر مسیر ناامن، فایل خطرناک و Manifest بررسی می‌شود.</span>
  </span>
</section>

<section class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h3 class="mb-1">مدیریت چرخه افزونه</h3><small class="text-muted">Plugin API <?= e(plugin_api_version()) ?></small></div>
    <div class="proma-plugin-toolbar">
      <?php if ($plugins): ?><button class="btn btn-danger" type="button" data-open-modal="delete-plugins-from-host"><i data-feather="trash-2"></i> حذف انتخاب‌شده‌ها از هاست</button><?php endif; ?>
      <form method="post" action="<?= e(url('plugins/rescan')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-light" type="submit"><i data-feather="refresh-cw"></i> بررسی مجدد پوشه پلاگین‌ها</button>
      </form>
      <form method="post" action="<?= e(url('plugins/upload')) ?>" enctype="multipart/form-data" class="proma-plugin-upload-form">
        <?= csrf_field() ?>
        <label class="mb-0"><span>فایل ZIP پلاگین</span><input type="file" name="plugin_zip" accept=".zip,application/zip" required></label>
        <button class="btn btn-primary" type="submit"><i data-feather="upload"></i> بارگذاری و بررسی</button>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th><input type="checkbox" data-check-all="plugin_ids" aria-label="انتخاب همه پلاگین‌ها"></th><th>افزونه</th><th>نسخه</th><th>وضعیت</th><th>مسیر</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($plugins as $plugin): ?>
        <?php $status = (string) ($plugin['status'] ?? 'discovered'); ?>
        <?php $hasFiles = !empty($plugin['has_files']); ?>
        <?php $installedVersion = (string) ($plugin['installed_version'] ?? $plugin['version'] ?? '-'); ?>
        <?php $hasStagedUpdate = !empty($plugin['has_staged_update']) || $status === 'update_available'; ?>
        <tr>
          <td><?php if ($hasFiles): ?><input type="checkbox" name="plugin_ids[]" value="<?= e($plugin['id'] ?? '') ?>" data-check-item="plugin_ids" form="plugins-delete-host-form" aria-label="انتخاب <?= e($plugin['name'] ?? $plugin['id'] ?? 'پلاگین') ?>"><?php endif; ?></td>
          <td><strong><?= e($plugin['name'] ?? $plugin['id'] ?? '-') ?></strong><br><small class="text-muted" dir="ltr"><?= e($plugin['id'] ?? '-') ?></small><?php if (!empty($plugin['last_error'])): ?><div class="text-danger small mt-1">آخرین خطای افزونه در گزارش امن سامانه ثبت شده است.</div><?php endif; ?></td>
          <td dir="ltr"><strong>v<?= e($installedVersion) ?></strong><?php if ($hasStagedUpdate && ($plugin['version'] ?? '') !== $installedVersion): ?><small class="d-block text-success">→ v<?= e($plugin['version']) ?></small><?php endif; ?></td>
          <td><span class="badge <?= e(badge_class($status)) ?>"><?= e(PluginStatus::label($status)) ?></span></td>
          <td>
            <code dir="ltr"><?= e($plugin['path'] ?? '-') ?></code>
            <?php if (!empty($plugin['technical_path'])): ?>
              <details class="proma-plugin-technical"><summary>اطلاعات فنی</summary><code dir="ltr"><?= e($plugin['technical_path']) ?></code></details>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <?php if (PluginStatus::canInstall($status, $hasFiles)): ?><form method="post" action="<?= e(url('plugins/install/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><i data-feather="download"></i> نصب پلاگین</button></form><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive'], true)): ?><form method="post" action="<?= e(url('plugins/activate/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-success" type="submit">فعال‌سازی</button></form><?php endif; ?>
              <?php if ($status === 'active'): ?><form method="post" action="<?= e(url('plugins/deactivate/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-warning" type="submit">غیرفعال‌سازی</button></form><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive', 'active', 'failed'], true)): ?><a class="btn btn-sm btn-light" href="<?= e(url('plugins/health/' . rawurlencode($plugin['id'] ?? ''))) ?>">بررسی سلامت</a><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive', 'active'], true)): ?><button class="btn btn-sm btn-secondary" type="button" data-open-modal="stage-plugin-update-<?= e(md5($plugin['id'] ?? '')) ?>"><i data-feather="upload-cloud"></i> فایل بروزرسانی</button><?php endif; ?>
              <?php if ($hasStagedUpdate): ?><button class="btn btn-sm btn-primary" type="button" data-open-modal="apply-plugin-update-<?= e(md5($plugin['id'] ?? '')) ?>"><i data-feather="refresh-cw"></i> نصب بروزرسانی</button><?php endif; ?>
              <?php if (in_array($status, ['installed', 'inactive'], true)): ?><form method="post" action="<?= e(url('plugins/uninstall/' . rawurlencode($plugin['id'] ?? ''))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light" type="submit" title="ثبت افزونه حذف می‌شود ولی فایل‌ها روی هاست می‌مانند">لغو نصب</button></form><?php endif; ?>
              <?php if (!$hasFiles): ?><span class="text-muted small align-self-center">برای نصب، پوشه یا ZIP معتبر افزونه را بارگذاری کنید.</span><?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$plugins): ?><tr><td colspan="6" class="empty">افزونه‌ای در پوشه plugins پیدا نشد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="delete-plugins-from-host">
  <div class="modal-content">
    <div class="modal-header"><h3>حذف کامل افزونه‌ها از هاست</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
    <form id="plugins-delete-host-form" method="post" action="<?= e(url('plugins/deleteFromHost')) ?>" data-loading-form data-loading-text="در حال حذف افزونه‌ها...">
      <div class="modal-body grid">
        <?= csrf_field() ?>
        <?php $deletePluginsCode = ConfirmationCode::hint('plugins_delete_from_host'); ?>
        <div class="notice error">فایل‌های افزونه‌های انتخاب‌شده از پوشه <span class="ltr">plugins/</span> هاست حذف می‌شوند و افزونه‌های فعال ابتدا غیرفعال خواهند شد. داده‌ها و جدول‌های دیتابیس حفظ می‌شوند. برای تایید عدد <strong class="ltr"><?= e($deletePluginsCode) ?></strong> را وارد کنید.</div>
        <label>عدد تایید <span class="required">*</span><input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deletePluginsCode) ?>"></label>
      </div>
      <div class="modal-footer"><button class="btn danger" type="submit"><i data-feather="trash-2"></i> حذف از هاست</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
    </form>
  </div>
</div>

<?php foreach ($plugins as $plugin): ?>
  <?php $status = (string) ($plugin['status'] ?? 'discovered'); $hasStagedUpdate = !empty($plugin['has_staged_update']) || $status === 'update_available'; ?>
  <?php if (in_array($status, ['installed', 'inactive', 'active'], true)): ?>
    <div class="modal" id="stage-plugin-update-<?= e(md5($plugin['id'] ?? '')) ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>بارگذاری بروزرسانی <?= e($plugin['name'] ?? '') ?></h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
        <form method="post" action="<?= e(url('plugins/updatePackage/' . rawurlencode($plugin['id'] ?? ''))) ?>" enctype="multipart/form-data" data-loading-form data-loading-text="در حال بررسی بسته...">
          <div class="modal-body grid">
            <?= csrf_field() ?>
            <?php $stageUpdateCode = ConfirmationCode::hint('plugin_update_stage_' . sha1((string) ($plugin['id'] ?? ''))); ?>
            <div class="notice info">شناسه بسته باید <span class="ltr"><?= e($plugin['id'] ?? '') ?></span> و نسخه آن جدیدتر از <span class="ltr">v<?= e($plugin['installed_version'] ?? $plugin['version'] ?? '-') ?></span> باشد. فایل‌های فعلی به‌صورت موقت نگهداری می‌شوند. برای تایید عدد <strong class="ltr"><?= e($stageUpdateCode) ?></strong> را وارد کنید.</div>
            <label>فایل ZIP بروزرسانی <span class="required">*</span><input type="file" name="plugin_update_zip" accept=".zip,application/zip" required></label>
            <label>عدد تایید <span class="required">*</span><input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($stageUpdateCode) ?>"></label>
          </div>
          <div class="modal-footer"><button class="btn primary" type="submit"><i data-feather="upload-cloud"></i> بارگذاری نسخه جدید</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($hasStagedUpdate): ?>
    <div class="modal" id="apply-plugin-update-<?= e(md5($plugin['id'] ?? '')) ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>نصب بروزرسانی <?= e($plugin['name'] ?? '') ?></h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
        <form method="post" action="<?= e(url('plugins/update/' . rawurlencode($plugin['id'] ?? ''))) ?>" data-loading-form data-loading-text="در حال اجرای migration...">
          <div class="modal-body grid">
            <?= csrf_field() ?>
            <?php $applyUpdateCode = ConfirmationCode::hint('plugin_update_apply_' . sha1((string) ($plugin['id'] ?? ''))); ?>
            <div class="notice warning">نسخه آماده‌شده نصب و migrationهای جدید اجرا می‌شوند. تا پایان عملیات صفحه را نبندید. برای تایید عدد <strong class="ltr"><?= e($applyUpdateCode) ?></strong> را وارد کنید.</div>
            <label>عدد تایید <span class="required">*</span><input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($applyUpdateCode) ?>"></label>
          </div>
          <div class="modal-footer"><button class="btn primary" type="submit"><i data-feather="refresh-cw"></i> نصب بروزرسانی</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; ?>
