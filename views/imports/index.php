<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>ورود دیتا</h2>
      <span class="badge badge-light-info">اکسل، سی‌اس‌وی، متن یا داده چسبانده‌شده</span>
    </div>
  </div>
  <div class="card-body">
    <form method="post" action="<?= e(url('imports/upload')) ?>" enctype="multipart/form-data" class="form-grid" data-loading-form>
      <?= csrf_field() ?>
      <label class="full">فایل داده
        <input type="file" name="data_file" accept=".xlsx,.csv,.txt">
      </label>
      <label class="full">یا متن خام را اینجا وارد کنید
        <textarea name="raw_text" placeholder="نمونه: نام مشتری، موبایل، کد ملی، مبلغ قرارداد، پیش‌پرداخت، تعداد اقساط، تاریخ سررسید و توضیحات"></textarea>
      </label>
      <div class="notice info full">داده‌ها ابتدا با هوش مصنوعی تحلیل می‌شوند و قبل از ذخیره، پیش‌نمایش و خطاهای احتمالی نمایش داده می‌شود.</div>
      <div class="full"><button class="btn" type="submit" data-loading-text="در حال تحلیل دیتا...">خواندن و تحلیل دیتا</button></div>
    </form>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>بسته‌های اخیر ورود دیتا</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>منبع</th><th>وضعیت</th><th>تاریخ</th><th>خطاها</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($batches as $batch): ?>
        <?php $errors = json_decode($batch['error_summary'] ?? '[]', true); ?>
        <tr>
          <td><?= e($batch['filename']) ?></td>
          <td><span class="badge <?= e(badge_class($batch['status'])) ?>"><?= e(status_label($batch['status'])) ?></span></td>
          <td><?= e(jdate($batch['created_at'])) ?></td>
          <td><?= $errors ? to_persian_digits(count($errors)) . ' خطا' : 'بدون خطای ثبت‌شده' ?></td>
          <td><a class="btn small secondary" href="<?= e(url('imports/preview/' . $batch['id'])) ?>">پیش‌نمایش</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$batches): ?><tr><td colspan="5" class="empty">هنوز دیتایی وارد نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
