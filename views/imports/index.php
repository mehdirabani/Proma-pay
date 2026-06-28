<section class="card">
  <div class="card-header"><h2>بارگذاری فایل اکسل</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('imports/upload')) ?>" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <label class="full">فایل اکسل یا سی‌اس‌وی<input type="file" name="excel_file" accept=".xlsx,.csv" required></label>
      <div class="full"><button class="btn" type="submit">خواندن و تحلیل فایل</button></div>
    </form>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>بسته‌های اخیر</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>فایل</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($batches as $batch): ?>
        <tr>
          <td><?= e($batch['filename']) ?></td>
          <td><span class="badge <?= e(badge_class($batch['status'])) ?>"><?= e(status_label($batch['status'])) ?></span></td>
          <td><?= e(jdate($batch['created_at'])) ?></td>
          <td><a class="btn small secondary" href="<?= e(url('imports/preview/' . $batch['id'])) ?>">پیش‌نمایش</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$batches): ?><tr><td colspan="4" class="empty">هنوز فایلی بارگذاری نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
