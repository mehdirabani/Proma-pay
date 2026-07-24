<?php
$filesPage = $filesPage ?? ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$filters = $filters ?? [];
$stats = $stats ?? [];
$categories = $categories ?? [];
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
$statusLabel = ['active' => 'فعال', 'archived' => 'بایگانی', 'deleted' => 'حذف نرم'];
$visibilityLabel = ['private' => 'خصوصی', 'internal' => 'داخلی', 'public' => 'عمومی'];
$pageUrl = function ($page) use ($filters) {
    return url('file-manager', array_filter([
        'q' => $filters['q'] ?? null,
        'category' => $filters['category'] ?? null,
        'status' => $filters['status'] ?? null,
        'visibility' => $filters['visibility'] ?? null,
        'page' => (int) $page > 1 ? (int) $page : null,
    ]));
};
?>

<section class="proma-page-hero">
  <div>
    <span class="proma-page-kicker"><?= proma_icon('file') ?> مخزن امن</span>
    <h2>مدیریت فایل‌ها</h2>
    <p>همه فایل‌های ثبت‌شده در سامانه از یک نقطه قابل جستجو، بایگانی، نسخه‌بندی و ممیزی هستند.</p>
  </div>
  <form method="post" action="<?= e(url('file-manager/sync')) ?>" class="proma-page-hero__actions">
    <?= csrf_field() ?>
    <button class="btn secondary" type="submit"><?= proma_icon('history') ?><span>همگام‌سازی فایل‌های قبلی</span></button>
  </form>
</section>

<?php if (!$registryReady): ?>
  <section class="card"><div class="card-body"><div class="notice error">رجیستری مرکزی فایل هنوز در پایگاه داده نصب نشده است. ابتدا بسته بروزرسانی V1.3.8 را کامل نصب کنید.</div></div></section>
<?php else: ?>
  <section class="proma-file-stats" aria-label="آمار مدیریت فایل">
    <article><span><?= proma_icon('file') ?></span><div><small>همه فایل‌ها</small><strong><?= to_persian_digits($stats['total'] ?? 0) ?></strong></div></article>
    <article><span><?= proma_icon('archive') ?></span><div><small>فضای ثبت‌شده</small><strong><?= e($formatSize($stats['total_size'] ?? 0)) ?></strong></div></article>
    <article><span><?= proma_icon('check') ?></span><div><small>فایل فعال</small><strong><?= to_persian_digits($stats['active'] ?? 0) ?></strong></div></article>
    <article><span><?= proma_icon('history') ?></span><div><small>بارگذاری هفت روز اخیر</small><strong><?= to_persian_digits($stats['recent'] ?? 0) ?></strong></div></article>
  </section>

  <section class="card proma-file-upload-card">
    <div class="card-header"><div><h3>بارگذاری امن فایل</h3><p>حداکثر حجم هر فایل ۱۰ مگابایت است. فایل اجرایی و اسکریپت‌پذیر پذیرفته نمی‌شود.</p></div></div>
    <form method="post" action="<?= e(url('file-manager/upload')) ?>" enctype="multipart/form-data" class="card-body form-grid two">
      <?= csrf_field() ?>
      <label>انتخاب فایل<input type="file" name="managed_file" required accept="<?= e('.' . implode(',.', $allowedExtensions)) ?>"></label>
      <label>نام نمایشی<input name="display_name" maxlength="190" placeholder="اختیاری؛ نام قابل نمایش در پنل"></label>
      <label>دسته‌بندی
        <select name="category">
          <option value="general">عمومی</option>
          <option value="identity">مدارک هویتی</option>
          <option value="payment">رسید پرداخت</option>
          <option value="legal">حقوقی</option>
          <option value="contract">قرارداد</option>
          <option value="chat">گفت‌وگو</option>
          <option value="report">گزارش</option>
        </select>
      </label>
      <label>سطح دسترسی
        <select name="visibility"><option value="private">خصوصی</option><option value="internal">داخلی</option></select>
      </label>
      <label class="full">توضیح<input name="description" maxlength="2000" placeholder="اختیاری"></label>
      <label class="full">برچسب‌ها<input name="tags" maxlength="400" placeholder="مثال: قرارداد، مهر، سال ۱۴۰۵"></label>
      <div class="full actions"><button class="btn" type="submit"><?= proma_icon('file') ?><span>بارگذاری و ثبت فایل</span></button><span class="proma-form-help">فرمت‌های مجاز: <span dir="ltr"><?= e(implode(', ', $allowedExtensions)) ?></span></span></div>
    </form>
  </section>

  <section class="card">
    <div class="card-header"><div><h3>فهرست فایل‌ها</h3><p>مسیر داخلی فایل نمایش داده نمی‌شود. عملیات حذف، نرم و قابل ممیزی است.</p></div><span class="badge info"><?= to_persian_digits($filesPage['total'] ?? 0) ?> فایل</span></div>
    <div class="card-body">
      <form method="get" action="<?= e(url('file-manager')) ?>" class="proma-filter-bar">
        <div class="proma-filter-bar__fields">
          <label class="proma-filter-bar__search">جستجو
            <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="نام، پسوند یا توضیح فایل">
          </label>
          <label>دسته‌بندی
            <select name="category"><option value="">همه دسته‌ها</option><?php foreach ($categories as $category): ?><option value="<?= e($category['category']) ?>"<?= selected($filters['category'] ?? '', $category['category']) ?>><?= e($category['category']) ?> (<?= to_persian_digits($category['total']) ?>)</option><?php endforeach; ?></select>
          </label>
          <label>وضعیت
            <select name="status"><option value="">فعال و بایگانی</option><option value="active"<?= selected($filters['status'] ?? '', 'active') ?>>فعال</option><option value="archived"<?= selected($filters['status'] ?? '', 'archived') ?>>بایگانی</option><option value="deleted"<?= selected($filters['status'] ?? '', 'deleted') ?>>حذف نرم</option></select>
          </label>
          <label>دسترسی
            <select name="visibility"><option value="">همه</option><option value="private"<?= selected($filters['visibility'] ?? '', 'private') ?>>خصوصی</option><option value="internal"<?= selected($filters['visibility'] ?? '', 'internal') ?>>داخلی</option><option value="public"<?= selected($filters['visibility'] ?? '', 'public') ?>>عمومی</option></select>
          </label>
        </div>
        <div class="proma-filter-bar__actions"><button class="btn secondary" type="submit">اعمال فیلتر</button><a class="btn light" href="<?= e(url('file-manager')) ?>">پاک‌سازی</a></div>
      </form>
    </div>
    <div class="table-wrap">
      <table class="proma-file-table">
        <thead><tr><th>فایل</th><th>دسته و ارتباط</th><th>بارگذار</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach (($filesPage['items'] ?? []) as $file): ?>
          <?php $fileId = (int) $file['id']; $uuid = $file['file_uuid']; ?>
          <tr>
            <td><div class="proma-file-cell"><span class="proma-file-type-icon"><?= proma_icon('file') ?></span><span><strong title="<?= e($file['display_name']) ?>"><?= e($file['display_name']) ?></strong><small dir="ltr"><?= e(strtoupper($file['extension'])) ?> · <?= e($formatSize($file['size_bytes'])) ?></small></span></div></td>
            <td><span class="badge muted"><?= e($file['category']) ?></span><small><?= to_persian_digits($file['relation_count'] ?? 0) ?> ارتباط ثبت‌شده</small></td>
            <td><strong><?= e($file['uploader_name'] ?: 'سیستم') ?></strong><small><?= e($file['uploader_role'] ?: '-') ?></small></td>
            <td><?= e(jdatetime($file['created_at'])) ?><small>نسخه <?= to_persian_digits($file['version_number']) ?></small></td>
            <td><span class="badge <?= $file['status'] === 'active' ? 'success' : ($file['status'] === 'archived' ? 'warning' : 'danger') ?>"><?= e($statusLabel[$file['status']] ?? $file['status']) ?></span><small><?= e($visibilityLabel[$file['visibility']] ?? $file['visibility']) ?></small></td>
            <td><div class="proma-table-actions">
              <?php if ($file['status'] !== 'deleted'): ?><a class="proma-icon-button" href="<?= e(url('file-manager/download/' . $uuid)) ?>" aria-label="دانلود <?= e($file['display_name']) ?>" title="دانلود"><?= proma_icon('download') ?></a><?php endif; ?>
              <button class="proma-icon-button" type="button" data-open-modal="file-edit-<?= $fileId ?>" aria-label="ویرایش اطلاعات فایل" title="ویرایش اطلاعات"><?= proma_icon('edit') ?></button>
              <?php if ($file['status'] === 'active'): ?><form method="post" action="<?= e(url('file-manager/archive/' . $uuid)) ?>"><?= csrf_field() ?><button class="proma-icon-button" type="submit" aria-label="بایگانی فایل" title="بایگانی"><?= proma_icon('archive') ?></button></form><?php elseif ($file['status'] === 'archived'): ?><form method="post" action="<?= e(url('file-manager/restore/' . $uuid)) ?>"><?= csrf_field() ?><button class="proma-icon-button" type="submit" aria-label="بازیابی فایل" title="بازیابی"><?= proma_icon('history') ?></button></form><?php endif; ?>
              <?php if ($file['status'] !== 'deleted'): ?><button class="proma-icon-button proma-icon-button--danger" type="button" data-open-modal="file-delete-<?= $fileId ?>" aria-label="حذف نرم فایل" title="حذف نرم"><?= proma_icon('trash') ?></button><?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($filesPage['items'])): ?><tr><td colspan="6" class="empty">فایلی مطابق فیلترهای انتخاب‌شده وجود ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-body"><?= render_pagination($filesPage, $pageUrl) ?></div>
  </section>

  <?php foreach (($filesPage['items'] ?? []) as $file): ?>
    <?php $fileId = (int) $file['id']; $uuid = $file['file_uuid']; ?>
    <div class="modal" id="file-edit-<?= $fileId ?>">
      <div class="modal-content"><div class="modal-header"><h3><?= proma_icon('edit') ?> اطلاعات فایل</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div>
        <form method="post" action="<?= e(url('file-manager/update/' . $uuid)) ?>"><div class="modal-body form-grid two"><?= csrf_field() ?>
          <label>نام نمایشی<input name="display_name" value="<?= e($file['display_name']) ?>" required maxlength="190"></label>
          <label>دسته‌بندی<input name="category" value="<?= e($file['category']) ?>" required maxlength="50"></label>
          <label>دسترسی<select name="visibility"><option value="private"<?= selected($file['visibility'], 'private') ?>>خصوصی</option><option value="internal"<?= selected($file['visibility'], 'internal') ?>>داخلی</option><option value="public"<?= selected($file['visibility'], 'public') ?>>عمومی</option></select></label>
          <label>برچسب‌ها<input name="tags" value="<?= e(implode('، ', json_decode((string) $file['tags_json'], true) ?: [])) ?>"></label>
          <label class="full">توضیح<textarea name="description" rows="3"><?= e($file['description']) ?></textarea></label>
          <div class="notice info full">برای جایگزینی محتوا، نسخه جدید بسازید. نسخه فعلی و سوابق آن حفظ می‌شود.</div>
          <label class="full">فایل نسخه جدید<input type="file" name="replacement_file" form="file-replace-form-<?= $fileId ?>"></label>
          <label class="full">علت جایگزینی<input name="replacement_reason" form="file-replace-form-<?= $fileId ?>" placeholder="اختیاری"></label>
          <div class="notice info full">برای اتصال به رکورد موجود، نوع و شناسه آن را وارد کنید؛ برای نمونه <span dir="ltr">contract / 125</span>.</div>
          <label>نوع رکورد مرتبط<input name="entity_type" form="file-relation-form-<?= $fileId ?>" placeholder="مثلاً contract" maxlength="60"></label>
          <label>شناسه رکورد<input name="entity_id" form="file-relation-form-<?= $fileId ?>" inputmode="numeric" placeholder="مثلاً ۱۲۵"></label>
          <label class="full">نوع ارتباط<input name="relation_type" form="file-relation-form-<?= $fileId ?>" value="attachment" maxlength="60"></label>
        </div><div class="modal-footer"><button class="btn" type="submit">ذخیره اطلاعات</button><button class="btn secondary" type="submit" form="file-replace-form-<?= $fileId ?>">ثبت نسخه جدید</button><button class="btn light" type="submit" form="file-relation-form-<?= $fileId ?>">ثبت ارتباط</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form>
        <form id="file-replace-form-<?= $fileId ?>" method="post" action="<?= e(url('file-manager/replace/' . $uuid)) ?>" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"></form>
        <form id="file-relation-form-<?= $fileId ?>" method="post" action="<?= e(url('file-manager/relate/' . $uuid)) ?>"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"></form>
      </div>
    </div>
    <div class="modal" id="file-delete-<?= $fileId ?>">
      <div class="modal-content"><div class="modal-header"><h3><?= proma_icon('trash') ?> حذف نرم فایل</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div>
        <form method="post" action="<?= e(url('file-manager/retire/' . $uuid)) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice error">فایل از فهرست فعال حذف می‌شود؛ محتوای فیزیکی و سابقه ممیزی آن برای بازبینی حفظ می‌شود.</div><label>علت حذف<textarea name="retirement_reason" required minlength="3" rows="3"></textarea></label></div><div class="modal-footer"><button class="btn danger" type="submit">حذف نرم فایل</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
