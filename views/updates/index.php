<?php $title = 'بروزرسانی سامانه'; ?>
<section class="card">
  <div class="card-header"><h1>بروزرسانی سامانه</h1><span class="badge success">نسخه نصب‌شده: <?= e(app_version_label()) ?></span></div>
  <div class="card-body grid">
    <p>فایل آپدیت متناسب با نسخهٔ نصب‌شده را انتخاب کنید. پس از بارگذاری، بسته در فهرست زیر نمایش داده می‌شود؛ سپس دکمهٔ نصب را بزنید. پیش از نصب بکاپ ایمنی ساخته می‌شود.</p>
    <form id="standalone-update-upload" method="post" action="<?= e(url('updates/upload')) ?>" enctype="multipart/form-data" data-loading-form data-update-upload-form>
      <?= csrf_field() ?>
      <label>فایل ZIP آپدیت
        <input type="file" name="update_file" accept=".zip,application/zip" required data-update-upload-file data-max-bytes="<?= (int) $updateUploadLimits['max_bytes'] ?>" data-max-label="<?= e(number_format($updateUploadLimits['max_bytes']/1048576,1).' MB') ?>">
      </label>
      <p>حداکثر حجم قابل بارگذاری: <?= to_persian_digits(number_format($updateUploadLimits['max_bytes']/1048576,1)) ?> مگابایت.</p>
      <p data-update-upload-feedback role="status" aria-live="polite">فایل را انتخاب کنید.</p>
      <button class="btn" type="submit" data-loading-text="در حال ارسال فایل...">بارگذاری بسته</button>
    </form>
    <div class="table-wrap">
      <table><thead><tr><th scope="col">بسته</th><th scope="col">نسخه</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>
        <?php foreach ($updatePackages as $package): ?>
          <tr>
            <td><strong><?= e($package['title'] ?: 'بسته آپدیت') ?></strong><small class="ltr d-block"><?= e($package['name']) ?></small></td>
            <td><?= e($package['version'] ?: '-') ?></td>
            <td><?php if (!$package['is_valid']): ?><span class="badge danger">نامعتبر</span><p><?= e($package['error']) ?></p>
              <?php else: ?><span class="badge <?= $package['is_installable'] ? 'success' : 'muted' ?>"><?= $package['is_installable'] ? 'آماده نصب' : (($package['version_state']??'')==='current' ? 'نصب‌شده' : 'ناسازگار') ?></span><p><?= e($package['version_message']) ?></p><?php endif; ?></td>
            <td><?php if ($package['is_installable']): ?><button class="btn success" type="button" data-open-modal="install-update-<?= e(md5($package['name'])) ?>">نصب</button><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$updatePackages): ?><tr><td colspan="4">هنوز بسته‌ای بارگذاری نشده است.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <a class="btn secondary" href="<?= e(url('settings',['tab'=>'backup'])) ?>">بکاپ و بازیابی اطلاعات</a>
  </div>
</section>
<?php foreach ($updatePackages as $package): if (!$package['is_installable']) continue; $code=ConfirmationCode::hint('update_install_'.sha1($package['name'])); ?>
  <div class="modal" id="install-update-<?= e(md5($package['name'])) ?>">
    <div class="modal-content"><div class="modal-header"><h2>نصب آپدیت <?= e($package['version']) ?></h2><button type="button" data-close-modal aria-label="بستن">×</button></div>
      <form method="post" action="<?= e(url('updates/install/'.rawurlencode($package['name']))) ?>" data-loading-form>
        <?= csrf_field() ?>
        <div class="modal-body grid"><p>قبل از نصب بکاپ ایمنی ساخته می‌شود. برای تأیید عدد <?= e($code) ?> را وارد کنید.</p><label>عدد تأیید<input name="update_confirm_text" type="text" inputmode="numeric" required placeholder="<?= e($code) ?>"></label></div>
        <div class="modal-footer"><button class="btn success" type="submit" data-loading-text="در حال نصب...">نصب بروزرسانی</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
