<?php
$trendLabels = [];
$trendStart = (new DateTime('first day of this month'))->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $trendLabels[] = mb_substr(jdate((clone $trendStart)->modify('+' . $i . ' months')->format('Y-m-01')), 0, 7, 'UTF-8');
}
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>فهرست مشتریان</h2>
      <button class="btn" type="button" data-open-modal="create-customer">افزودن مشتری</button>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('customers')) ?>" class="form-grid three">
      <input type="hidden" name="route" value="customers">
      <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، کد ملی یا موبایل"></label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>فعال</option>
          <option value="inactive"<?= selected($_GET['status'] ?? '', 'inactive') ?>>غیرفعال</option>
        </select>
      </label>
      <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button></div>
    </form>
  </div>
</section>

<section class="proma-profile-grid">
  <?php foreach ($customers as $item): ?>
    <article class="card proma-profile-tile proma-customer-index-card">
      <div class="card-body">
        <div class="proma-profile-head">
          <span class="proma-avatar-choice <?= e($item['avatar_key'] ?: 'avatar-1') ?>"><?= e(mb_substr($item['full_name'], 0, 1, 'UTF-8')) ?></span>
          <div>
            <h5><?= e($item['full_name']) ?></h5>
            <p><?= to_persian_digits($item['mobile']) ?> · <?= to_persian_digits($item['national_id']) ?></p>
          </div>
          <span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
        </div>

        <div class="proma-customer-card-metrics">
          <span><strong><?= to_persian_digits($item['contract_count'] ?? 0) ?></strong><small>قرارداد</small></span>
          <span><strong><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></strong><small>قسط معوق</small></span>
          <span><strong><?= to_persian_digits($item['good_score'] ?? 0) ?>٪</strong><small>خوش‌حسابی</small></span>
        </div>

        <div class="proma-mini-chart">
          <?php if (array_sum($item['payment_trend'] ?? []) > 0): ?>
            <canvas data-chart="mini-line" data-title="روند پرداخت" data-labels='<?= e(json_encode($trendLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($item['payment_trend'])) ?>'></canvas>
          <?php else: ?>
            <div class="proma-empty-mini">پرداخت موفقی برای نمودار ثبت نشده است.</div>
          <?php endif; ?>
        </div>

        <div class="proma-medal-row">
          <?php foreach (array_slice($item['medals'] ?? [], 0, 3) as $medal): ?>
            <span class="badge badge-light-warning"><?= e($medal['title']) ?></span>
          <?php endforeach; ?>
          <?php if (empty($item['medals'])): ?><span class="badge muted">بدون مدال</span><?php endif; ?>
        </div>

        <div class="proma-payment-timeline compact">
          <?php foreach (($item['payment_timeline'] ?? []) as $payment): ?>
            <div class="proma-timeline-item">
              <span class="proma-timeline-dot"></span>
              <div>
                <strong><?= money_toman($payment['amount']) ?></strong>
                <p><?= e($payment['contract_number']) ?> · قسط <?= to_persian_digits($payment['installment_number']) ?> · <?= e(payment_method_label($payment['method'])) ?></p>
              </div>
              <time><?= e(jdate($payment['paid_at'] ?: $payment['created_at'])) ?></time>
            </div>
          <?php endforeach; ?>
          <?php if (empty($item['payment_timeline'])): ?><div class="empty">پرداخت موفقی ثبت نشده است.</div><?php endif; ?>
        </div>

        <div class="actions">
          <a class="btn small secondary" href="<?= e(url('customers/show/' . $item['id'])) ?>">مشاهده</a>
          <button class="btn small" type="button" data-open-modal="edit-customer-<?= (int) $item['id'] ?>">ویرایش</button>
          <form method="post" action="<?= e(url('customers/delete/' . $item['id'])) ?>">
            <?= csrf_field() ?>
            <button class="btn small danger" type="submit">حذف</button>
          </form>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$customers): ?><div class="card"><div class="empty">مشتری ثبت نشده است.</div></div><?php endif; ?>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>نمای جدولی مشتریان</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نام</th><th>کد ملی</th><th>موبایل</th><th>تلفن دوم</th><th>قرارداد</th><th>معوقه</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $item): ?>
        <tr>
          <td><a href="<?= e(url('customers/show/' . $item['id'])) ?>"><?= e($item['full_name']) ?></a></td>
          <td><?= to_persian_digits($item['national_id']) ?></td>
          <td><?= to_persian_digits($item['mobile']) ?></td>
          <td><?= to_persian_digits($item['secondary_phone']) ?></td>
          <td><?= to_persian_digits($item['contract_count'] ?? 0) ?></td>
          <td><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="7" class="empty">مشتری ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="create-customer">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('customers/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>نام کامل<input name="full_name" required></label>
        <label>کد ملی<input name="national_id" required inputmode="numeric"></label>
        <label>موبایل<input name="mobile" required inputmode="tel"></label>
        <label>تلفن دوم<input name="secondary_phone" inputmode="tel"></label>
        <label>وضعیت<select name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت مشتری</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

<?php foreach ($customers as $item): ?>
  <div class="modal" id="edit-customer-<?= (int) $item['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>ویرایش مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('customers/update/' . $item['id'])) ?>">
        <div class="modal-body form-grid three">
          <?= csrf_field() ?>
          <label>نام کامل<input name="full_name" value="<?= e($item['full_name']) ?>" required></label>
          <label>کد ملی<input name="national_id" value="<?= e($item['national_id']) ?>" required inputmode="numeric"></label>
          <label>موبایل<input name="mobile" value="<?= e($item['mobile']) ?>" required inputmode="tel"></label>
          <label>تلفن دوم<input name="secondary_phone" value="<?= e($item['secondary_phone']) ?>" inputmode="tel"></label>
          <label>وضعیت<select name="status"><option value="active"<?= selected($item['status'], 'active') ?>>فعال</option><option value="inactive"<?= selected($item['status'], 'inactive') ?>>غیرفعال</option></select></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
