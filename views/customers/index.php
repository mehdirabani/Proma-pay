<?php
$trendLabels = [];
$trendStart = (new DateTime('first day of this month'))->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $trendLabels[] = mb_substr(jdate((clone $trendStart)->modify('+' . $i . ' months')->format('Y-m-01')), 0, 7, 'UTF-8');
}
$viewMode = in_array($_GET['view'] ?? '', ['cards', 'list'], true) ? $_GET['view'] : 'cards';
$pagination = $pagination ?? ['total' => count($customers ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($customers ?? []) ?: 24];
$pageUrl = function ($page) use ($viewMode) {
    $params = [
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'view' => $viewMode,
        'page' => (int) $page > 1 ? (int) $page : null,
    ];
    return url('customers', array_filter($params, fn($value) => $value !== null && $value !== ''));
};
?>
<section class="card proma-filter-card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>فهرست مشتریان</h2>
      <div class="actions">
        <a class="btn small secondary" data-xlsx-export href="<?= e(url('customers/export')) ?>"><?= proma_icon('download') ?><span>خروجی Excel</span></a>
        <a class="btn small <?= $viewMode === 'cards' ? '' : 'secondary' ?>" href="<?= e(url('customers', array_filter(['q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null, 'view' => 'cards', 'page' => $_GET['page'] ?? null]))) ?>">کارت‌ها</a>
        <a class="btn small <?= $viewMode === 'list' ? '' : 'secondary' ?>" href="<?= e(url('customers', array_filter(['q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null, 'view' => 'list', 'page' => $_GET['page'] ?? null]))) ?>">لیست</a>
        <button class="btn secondary" type="button" data-open-modal="merge-customers">ادغام مشتریان</button>
        <button class="btn" type="button" data-open-modal="create-customer">افزودن مشتری</button>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('customers')) ?>" class="form-grid three proma-filter-toolbar proma-filter-toolbar--customers" data-ajax-filter data-ajax-target="[data-ajax-results='customers']">
      <input type="hidden" name="route" value="customers">
      <input type="hidden" name="view" value="<?= e($viewMode) ?>">
      <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، کد ملی یا موبایل"></label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>فعال</option>
          <option value="inactive"<?= selected($_GET['status'] ?? '', 'inactive') ?>>غیرفعال</option>
        </select>
      </label>
      <div class="proma-filter-actions"><button class="btn secondary" type="submit">اعمال فیلتر</button><span class="proma-ajax-status" data-ajax-status></span></div>
    </form>
  </div>
</section>

<div data-ajax-results="customers">
<div class="proma-list-meta">
  <span class="badge info">کل مشتریان: <?= to_persian_digits($pagination['total'] ?? count($customers ?? [])) ?></span>
  <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
</div>

<?php if ($viewMode === 'cards'): ?>
<section class="proma-profile-grid">
  <?php foreach ($customers as $item): ?>
    <article class="card proma-profile-tile proma-customer-index-card" data-card-href="<?= e(url('customers/show/' . $item['id'])) ?>" tabindex="0" role="link" aria-label="مشاهده پرونده مشتری <?= e($item['full_name']) ?>">
      <div class="card-body">
        <div class="proma-profile-head">
          <span class="proma-progress-avatar" data-progress="<?= max(0, min(100, (int) ($item['good_score'] ?? 0))) ?>">
            <?php $customerAvatar = avatar_key_for($item['avatar_key'] ?? null, $item['id'] ?? $item['full_name']); ?>
            <span class="proma-avatar-choice <?= e($customerAvatar) ?>" aria-label="<?= e($item['full_name']) ?>"><img data-avatar-image src="<?= e(user_avatar_asset_url($item)) ?>" alt="آواتار <?= e($item['full_name']) ?>" loading="lazy"></span>
          </span>
          <div>
            <h5><?= e($item['full_name']) ?> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?><?php if (!empty($item['credit_profile'])): $credit = $item['credit_profile']; ?><span class="badge <?= ($credit['risk_level'] ?? '') === 'low' ? 'success' : (($credit['risk_level'] ?? '') === 'medium' ? 'warning' : 'danger') ?>" title="رتبهٔ اعتباری مشتری">اعتبار <?= to_persian_digits($credit['score'] ?? 0) ?> · <?= e(($credit['risk_level'] ?? '') === 'low' ? 'کم‌ریسک' : (($credit['risk_level'] ?? '') === 'medium' ? 'متوسط' : 'پرریسک')) ?></span><?php endif; ?></h5>
            <p><a class="proma-call-button" href="tel:<?= e(to_english_digits(preg_replace('/[^0-9۰-۹٠-٩+]/u', '', (string) $item['mobile']))) ?>"><?= proma_icon('phone') ?> <?= to_persian_digits($item['mobile']) ?></a> · <?= to_persian_digits($item['national_id']) ?></p>
          </div>
          <span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
        </div>

        <div class="proma-customer-card-metrics">
          <span><strong><?= to_persian_digits($item['contract_count'] ?? 0) ?></strong><small>قرارداد</small></span>
          <span><strong><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></strong><small>قسط معوق</small></span>
          <span><strong><?= to_persian_digits($item['loyalty']['points'] ?? 0) ?></strong><small>امتیاز باشگاه</small></span>
        </div>

        <div class="proma-medal-row">
          <?php foreach (array_slice($item['medals'] ?? [], 0, 3) as $medal): ?>
            <span class="badge badge-light-warning proma-medal-badge" title="<?= e($medal['description'] ?? '') ?>"><i data-feather="<?= e($medal['icon_key'] ?: 'award') ?>"></i><?= e($medal['medal_title'] ?? $medal['title']) ?></span>
          <?php endforeach; ?>
          <span class="badge info">سطح: <?= e($item['loyalty']['tier']['title'] ?? 'تازه‌وارد') ?></span>
        </div>

        <div class="actions proma-card-actions" aria-label="عملیات مشتری">
          <a class="btn small secondary" href="<?= e(url('customers/show/' . $item['id'])) ?>"><?= proma_icon('eye') ?>مشاهده</a>
          <button class="proma-icon-button info" type="button" data-open-modal="customer-chart-<?= (int) $item['id'] ?>" title="مشاهده نمودار پرداخت مشتری" aria-label="مشاهده نمودار پرداخت مشتری"><?= proma_icon('chart') ?></button>
          <button class="proma-icon-button warning" type="button" data-open-modal="customer-timeline-<?= (int) $item['id'] ?>" title="مشاهده تایم‌لاین پرداخت مشتری" aria-label="مشاهده تایم‌لاین پرداخت مشتری"><?= proma_icon('history') ?></button>
          <a class="proma-icon-button secondary" href="<?= e(url('customers/tab/' . $item['id'] . '/loyalty')) ?>" title="امتیاز و سطح مشتری" aria-label="امتیاز و سطح مشتری"><?= proma_icon('award') ?></a>
          <button class="proma-icon-button" type="button" data-open-modal="edit-customer-<?= (int) $item['id'] ?>" title="ویرایش مشتری" aria-label="ویرایش مشتری"><?= proma_icon('edit') ?></button>
          <button class="proma-icon-button danger" type="button" data-open-modal="delete-customer-<?= (int) $item['id'] ?>" title="حذف مشتری" aria-label="حذف مشتری"><?= proma_icon('trash') ?></button>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$customers): ?><div class="card"><div class="empty">مشتری ثبت نشده است.</div></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($viewMode === 'list'): ?>
<section class="card proma-list-section">
  <div class="card-header card-no-border"><h2>نمای جدولی مشتریان</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">نام</th><th scope="col">کد ملی</th><th scope="col">موبایل</th><th scope="col">تلفن دوم</th><th scope="col">قرارداد</th><th scope="col">معوقه</th><th scope="col">باشگاه مشتریان</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $item): ?>
        <tr>
          <td><a href="<?= e(url('customers/show/' . $item['id'])) ?>"><?= e($item['full_name']) ?></a> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?><?php if (!empty($item['credit_profile'])): $credit = $item['credit_profile']; ?><span class="badge <?= ($credit['risk_level'] ?? '') === 'low' ? 'success' : (($credit['risk_level'] ?? '') === 'medium' ? 'warning' : 'danger') ?>" title="رتبهٔ اعتباری مشتری">اعتبار <?= to_persian_digits($credit['score'] ?? 0) ?> · <?= e(($credit['risk_level'] ?? '') === 'low' ? 'کم‌ریسک' : (($credit['risk_level'] ?? '') === 'medium' ? 'متوسط' : 'پرریسک')) ?></span><?php endif; ?></td>
          <td><?= to_persian_digits($item['national_id']) ?></td>
          <td><a class="proma-call-button" href="tel:<?= e(to_english_digits(preg_replace('/[^0-9۰-۹٠-٩+]/u', '', (string) $item['mobile']))) ?>"><?= proma_icon('phone') ?> تماس</a> <?= to_persian_digits($item['mobile']) ?></td>
          <td><?= to_persian_digits($item['secondary_phone']) ?></td>
          <td><?= to_persian_digits($item['contract_count'] ?? 0) ?></td>
          <td><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></td>
          <td>
            <a class="badge badge-light-warning" href="<?= e(url('customers/tab/' . $item['id'] . '/loyalty')) ?>"><?= e($item['loyalty']['tier']['title'] ?? 'تازه‌وارد') ?> · <?= to_persian_digits($item['loyalty']['points'] ?? 0) ?> امتیاز</a>
          </td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <a class="btn small secondary" href="<?= e(url('customers/show/' . $item['id'])) ?>">مشاهده</a>
            <button class="btn small info" type="button" data-open-modal="customer-chart-<?= (int) $item['id'] ?>">نمودار</button>
            <button class="btn small warning" type="button" data-open-modal="customer-timeline-<?= (int) $item['id'] ?>">تایم‌لاین</button>
            <a class="btn small secondary" href="<?= e(url('customers/tab/' . $item['id'] . '/loyalty')) ?>">امتیازها</a>
            <button class="btn small icon-only" type="button" data-open-modal="edit-customer-<?= (int) $item['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
            <button class="btn small danger icon-only" type="button" data-open-modal="delete-customer-<?= (int) $item['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="9" class="empty">مشتری ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?= render_pagination($pagination, $pageUrl) ?>

<div class="modal" id="merge-customers">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>ادغام مشتریان</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('customers/merge')) ?>">
      <div class="modal-body form-grid two">
        <?= csrf_field() ?>
        <div class="notice info full">پرونده اصلی باقی می‌ماند و قراردادها، پرونده‌های حقوقی، رسیدها، پیام‌ها، اعلان‌ها، دفترکل امتیاز و مدال‌های سطحی مشتری تکراری به آن منتقل می‌شود. مشتری تکراری غیرفعال می‌شود.</div>
        <label>پرونده اصلی که باقی بماند
          <span class="proma-live-search" data-customer-live-search data-search-url="<?= e(url('contracts/search-customers')) ?>">
            <input data-customer-search-input autocomplete="off" placeholder="نام، موبایل یا کد ملی مشتری اصلی">
            <input type="hidden" name="keep_customer_id" data-customer-select>
            <span class="proma-live-results" data-customer-search-results hidden></span>
          </span>
        </label>
        <label>مشتری تکراری برای ادغام
          <span class="proma-live-search" data-customer-live-search data-search-url="<?= e(url('contracts/search-customers')) ?>">
            <input data-customer-search-input autocomplete="off" placeholder="نام، موبایل یا کد ملی مشتری تکراری">
            <input type="hidden" name="merge_customer_id" data-customer-select>
            <span class="proma-live-results" data-customer-search-results hidden></span>
          </span>
        </label>
      </div>
      <div class="modal-footer"><button class="btn warning" type="submit">ادغام با حفظ اطلاعات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_create_modal.php'; ?>

<?php foreach ($customers as $item): ?>
  <div class="modal" id="customer-chart-<?= (int) $item['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>نمودار پرداخت <?= e($item['full_name']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <?php if (array_sum($item['payment_trend'] ?? []) > 0): ?>
          <canvas data-chart="line" data-title="روند پرداخت" data-labels='<?= e(json_encode($trendLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($item['payment_trend'])) ?>'></canvas>
        <?php else: ?>
          <div class="empty">پرداخت موفقی برای نمودار ثبت نشده است.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="modal" id="customer-timeline-<?= (int) $item['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تایم‌لاین پرداخت <?= e($item['full_name']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <div class="proma-payment-timeline compact">
          <?php foreach (($item['payment_timeline'] ?? []) as $payment): ?>
            <div class="proma-timeline-item">
              <span class="proma-timeline-dot"></span>
              <div>
                <strong><?= money_toman($payment['amount']) ?></strong>
                <p><?= e($payment['contract_number']) ?> · <?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?><?= !empty($payment['installment_number']) ? ' · قسط ' . to_persian_digits($payment['installment_number']) : '' ?> · <?= e(payment_method_label($payment['method'])) ?></p>
              </div>
              <time><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></time>
            </div>
          <?php endforeach; ?>
          <?php if (empty($item['payment_timeline'])): ?><div class="empty">پرداخت موفقی ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="edit-customer-<?= (int) $item['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>ویرایش مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('customers/update/' . $item['id'])) ?>">
        <div class="modal-body form-grid three">
          <?= csrf_field() ?>
          <label>نام کامل<input name="full_name" value="<?= e($item['full_name']) ?>" required></label>
          <label>نام پدر<input name="father_name" value="<?= e($item['father_name'] ?? '') ?>"></label>
          <label>محل صدور<input name="issued_from" value="<?= e($item['issued_from'] ?? '') ?>"></label>
          <label>کد ملی<input name="national_id" value="<?= e($item['national_id']) ?>" required inputmode="numeric"></label>
          <label>موبایل<input name="mobile" value="<?= e($item['mobile']) ?>" required inputmode="tel"></label>
          <label>تلفن دوم<input name="secondary_phone" value="<?= e($item['secondary_phone']) ?>" inputmode="tel"></label>
          <label>وضعیت<select name="status"><option value="active"<?= selected($item['status'], 'active') ?>>فعال</option><option value="inactive"<?= selected($item['status'], 'inactive') ?>>غیرفعال</option></select></label>
          <label class="full">آدرس<textarea name="address" rows="3"><?= e($item['address'] ?? '') ?></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="delete-customer-<?= (int) $item['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تأیید حذف مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('customers/retire/' . $item['id'])) ?>">
        <div class="modal-body">
          <?= csrf_field() ?>
          <?php $deleteCode = ConfirmationCode::hint('customer_delete_' . (int) $item['id']); ?>
          <div class="notice error">اگر مشتری قرارداد فعال داشته باشد حذف انجام نمی‌شود. برای تأیید حذف <?= e($item['full_name']) ?> عدد <strong class="ltr"><?= e($deleteCode) ?></strong> را وارد کنید.</div>
          <label>عدد تأیید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCode) ?>"></label>
        </div>
        <div class="modal-footer"><button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
