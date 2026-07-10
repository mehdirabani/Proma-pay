<?php
$files = $files ?? [];
$allowedExtensions = $allowedExtensions ?? [];
$formatSize = function ($bytes) {
    $bytes = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }
    return to_persian_digits(number_format($bytes, $index === 0 ? 0 : 1)) . ' ' . $units[$index];
};
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>مدیریت فایل</h2>
        <p>بارگذاری، دانلود و حذف فایل‌های مورد نیاز پروژه.</p>
      </div>
      <span class="badge info"><?= to_persian_digits(count($files)) ?> فایل</span>
    </div>
  </div>
</section>

<div class="proma-file-manager-grid">
  <section class="card">
    <div class="card-header"><h2>افزودن فایل</h2></div>
    <form method="post" action="<?= e(url('file-manager/upload')) ?>" enctype="multipart/form-data" class="card-body form-grid">
      <?= csrf_field() ?>
      <label class="full">انتخاب فایل
        <input type="file" name="managed_file" required>
      </label>
      <div class="full notice info">
        حداکثر حجم هر فایل ۱۰ مگابایت است. فرمت‌های مجاز:
        <span dir="ltr"><?= e(implode(', ', $allowedExtensions)) ?></span>
      </div>
      <div class="full actions"><button class="btn" type="submit">بارگذاری فایل</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-header"><h2>فایل‌های پروژه</h2></div>
    <div class="card-body">
      <div class="proma-file-manager-list">
        <?php foreach ($files as $file): ?>
          <article class="proma-file-manager-item">
            <span class="proma-file-manager-icon"><i data-feather="file"></i></span>
            <div class="proma-file-manager-info">
              <strong title="<?= e($file['name']) ?>"><?= e($file['name']) ?></strong>
              <small><?= e(strtoupper($file['extension'] ?? '')) ?> · <?= e($formatSize($file['size'] ?? 0)) ?> · <?= e(jdatetime(date('Y-m-d H:i:s', (int) ($file['modified_at'] ?? time())))) ?></small>
            </div>
            <div class="proma-file-manager-actions">
              <a class="btn small secondary" href="<?= e(url('file-manager/download/' . rawurlencode($file['name']))) ?>">دانلود</a>
              <form method="post" action="<?= e(url('file-manager/delete/' . rawurlencode($file['name']))) ?>" onsubmit="return confirm('فایل حذف شود؟')">
                <?= csrf_field() ?>
                <button class="btn small danger" type="submit">حذف</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$files): ?>
          <div class="empty">هنوز فایلی در مدیریت فایل ثبت نشده است.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
