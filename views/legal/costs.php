<?php
$costs = $costs ?? [];
$status = $status ?? 'pending_approval';
$search = $search ?? '';
$statusLabels = [
    'pending_approval' => 'در انتظار تأیید',
    'approved' => 'تأییدشده',
    'reversed' => 'برگشت‌خورده',
];
$statusClass = [
    'pending_approval' => 'warning',
    'approved' => 'success',
    'reversed' => 'danger',
];
?>

<section class="card proma-legal-cost-approval">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>تأیید هزینه‌های حقوقی</h2>
        <p>هزینه‌هایی که وکیل یا واحد حقوقی ثبت می‌کند تا زمان تأیید مدیریت وارد مبلغ قابل مطالبه مشتری نمی‌شود.</p>
      </div>
      <span class="badge <?= e($statusClass[$status] ?? 'info') ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
    </div>
  </div>
  <div class="card-body">
    <?php if (empty($registryReady)): ?>
      <div class="notice warning">ساختار هزینه‌های حقوقی هنوز نصب نشده است. ابتدا بسته بروزرسانی پایگاه داده را اجرا کنید.</div>
    <?php else: ?>
      <form method="get" action="<?= e(url('legal/costs')) ?>" class="proma-filter-bar">
        <div class="proma-filter-bar__fields">
          <label class="proma-filter-bar__search">جستجو
            <input name="q" value="<?= e($search) ?>" placeholder="قرارداد، مشتری، عنوان یا نوع هزینه">
          </label>
          <label>وضعیت
            <select name="status">
              <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= selected($status, $key) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="proma-filter-bar__actions">
          <button class="btn" type="submit">اعمال فیلتر</button>
          <a class="btn secondary" href="<?= e(url('legal/costs')) ?>">پاک‌سازی</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!empty($registryReady)): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>قرارداد و مشتری</th>
            <th>هزینه</th>
            <th>ثبت‌کننده</th>
            <th>تاریخ</th>
            <th>وضعیت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($costs as $cost): ?>
          <?php $costId = (int) $cost['id']; ?>
          <tr>
            <td>
              <strong><?= e($cost['contract_number'] ?? '-') ?></strong>
              <small><?= e($cost['customer_name'] ?? '-') ?></small>
            </td>
            <td>
              <strong><?= money_toman($cost['amount_toman'] ?? 0) ?></strong>
              <small><?= e(($cost['category'] ?? 'سایر') . ' · ' . ($cost['title'] ?? 'هزینه حقوقی')) ?></small>
            </td>
            <td>
              <strong><?= e($cost['created_by_name'] ?? 'سیستم') ?></strong>
              <small><?= e($cost['description'] ?? '') ?></small>
            </td>
            <td><?= e(jdate($cost['cost_date'] ?? $cost['created_at'] ?? '')) ?></td>
            <td>
              <span class="badge <?= e($statusClass[$cost['approval_status'] ?? ''] ?? 'muted') ?>"><?= e($statusLabels[$cost['approval_status'] ?? ''] ?? ($cost['approval_status'] ?? '-')) ?></span>
              <?php if (!empty($cost['approved_by_name'])): ?><small>توسط <?= e($cost['approved_by_name']) ?></small><?php endif; ?>
            </td>
            <td>
              <div class="proma-table-actions">
                <a class="btn small secondary" href="<?= e(url('contracts/show/' . (int) ($cost['contract_id'] ?? 0) . '#legal')) ?>">قرارداد</a>
                <?php if (($cost['approval_status'] ?? '') === 'pending_approval'): ?>
                  <form method="post" action="<?= e(url('legal/approveCost/' . $costId)) ?>">
                    <?= csrf_field() ?>
                    <button class="btn small success" type="submit">تأیید</button>
                  </form>
                <?php endif; ?>
                <?php if (($cost['approval_status'] ?? '') !== 'reversed'): ?>
                  <button class="btn small danger" type="button" data-open-modal="legal-cost-reverse-<?= $costId ?>">برگشت</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$costs): ?><tr><td colspan="6" class="empty">هزینه‌ای مطابق فیلتر انتخاب‌شده وجود ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php foreach ($costs as $cost): ?>
  <?php $costId = (int) $cost['id']; if (($cost['approval_status'] ?? '') === 'reversed') continue; ?>
  <div class="modal" id="legal-cost-reverse-<?= $costId ?>">
    <div class="modal-content">
      <div class="modal-header">
        <h3>برگشت هزینه حقوقی</h3>
        <button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button>
      </div>
      <form method="post" action="<?= e(url('legal/reverseCost/' . $costId)) ?>">
        <?= csrf_field() ?>
        <div class="modal-body form-grid">
          <div class="notice warning">این عملیات هزینه را حذف فیزیکی نمی‌کند؛ فقط آن را از محاسبات قابل مطالبه خارج و سابقه ممیزی ثبت می‌کند.</div>
          <label>علت برگشت
            <textarea name="reason" rows="4" required minlength="3"></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn danger" type="submit">ثبت برگشت</button>
          <button class="btn secondary" type="button" data-close-modal>انصراف</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
