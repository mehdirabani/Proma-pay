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
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>فهرست مشتریان</h2>
      <div class="actions">
        <a class="btn small <?= $viewMode === 'cards' ? '' : 'secondary' ?>" href="<?= e(url('customers', array_filter(['q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null, 'view' => 'cards', 'page' => $_GET['page'] ?? null]))) ?>">کارت‌ها</a>
        <a class="btn small <?= $viewMode === 'list' ? '' : 'secondary' ?>" href="<?= e(url('customers', array_filter(['q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null, 'view' => 'list', 'page' => $_GET['page'] ?? null]))) ?>">لیست</a>
        <button class="btn secondary" type="button" data-open-modal="merge-customers">ادغام مشتریان</button>
        <button class="btn" type="button" data-open-modal="create-customer">افزودن مشتری</button>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('customers')) ?>" class="form-grid three" data-ajax-filter data-ajax-target="[data-ajax-results='customers']">
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
      <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button><span class="proma-ajax-status" data-ajax-status></span></div>
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
    <article class="card proma-profile-tile proma-customer-index-card" data-card-href="<?= e(url('customers/show/' . $item['id'])) ?>">
      <div class="card-body">
        <div class="proma-profile-head">
          <span class="proma-progress-avatar" style="--progress: <?= (int) ($item['good_score'] ?? 0) ?>">
            <span class="proma-avatar-choice <?= e($item['avatar_key'] ?: 'avatar-1') ?>"><?= e(mb_substr($item['full_name'], 0, 1, 'UTF-8')) ?></span>
          </span>
          <div>
            <h5><?= e($item['full_name']) ?> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?></h5>
            <p><?= to_persian_digits($item['mobile']) ?> · <?= to_persian_digits($item['national_id']) ?></p>
          </div>
          <span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
        </div>

        <div class="proma-customer-card-metrics">
          <span><strong><?= to_persian_digits($item['contract_count'] ?? 0) ?></strong><small>قرارداد</small></span>
          <span><strong><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></strong><small>قسط معوق</small></span>
          <span><strong><?= to_persian_digits($item['good_score'] ?? 0) ?>٪</strong><small>خوش‌حسابی</small></span>
        </div>

        <div class="proma-medal-row">
          <?php foreach (array_slice($item['medals'] ?? [], 0, 3) as $medal): ?>
            <span class="badge badge-light-warning proma-medal-badge"><i data-feather="<?= e($medal['icon_key'] ?: 'award') ?>"></i><?= e($medal['title']) ?></span>
          <?php endforeach; ?>
          <?php if (empty($item['medals'])): ?><span class="badge muted">بدون مدال</span><?php endif; ?>
        </div>

        <div class="actions">
          <a class="btn small secondary" href="<?= e(url('customers/show/' . $item['id'])) ?>">مشاهده</a>
          <button class="btn small info" type="button" data-open-modal="customer-chart-<?= (int) $item['id'] ?>">نمودار</button>
          <button class="btn small warning" type="button" data-open-modal="customer-timeline-<?= (int) $item['id'] ?>">تایم‌لاین</button>
          <button class="btn small secondary" type="button" data-open-modal="customer-medals-<?= (int) $item['id'] ?>">مدال‌ها</button>
          <button class="btn small icon-only" type="button" data-open-modal="edit-customer-<?= (int) $item['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
          <button class="btn small danger icon-only" type="button" data-open-modal="delete-customer-<?= (int) $item['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$customers): ?><div class="card"><div class="empty">مشتری ثبت نشده است.</div></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($viewMode === 'list'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>نمای جدولی مشتریان</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نام</th><th>کد ملی</th><th>موبایل</th><th>تلفن دوم</th><th>قرارداد</th><th>معوقه</th><th>مدال‌ها</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $item): ?>
        <tr>
          <td><a href="<?= e(url('customers/show/' . $item['id'])) ?>"><?= e($item['full_name']) ?></a> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?></td>
          <td><?= to_persian_digits($item['national_id']) ?></td>
          <td><?= to_persian_digits($item['mobile']) ?></td>
          <td><?= to_persian_digits($item['secondary_phone']) ?></td>
          <td><?= to_persian_digits($item['contract_count'] ?? 0) ?></td>
          <td><?= to_persian_digits($item['overdue_installments'] ?? 0) ?></td>
          <td>
            <?php foreach (array_slice($item['medals'] ?? [], 0, 2) as $medal): ?><span class="badge badge-light-warning"><?= e($medal['title']) ?></span><?php endforeach; ?>
            <?php if (empty($item['medals'])): ?><span class="badge muted">بدون مدال</span><?php endif; ?>
          </td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <a class="btn small secondary" href="<?= e(url('customers/show/' . $item['id'])) ?>">مشاهده</a>
            <button class="btn small info" type="button" data-open-modal="customer-chart-<?= (int) $item['id'] ?>">نمودار</button>
            <button class="btn small warning" type="button" data-open-modal="customer-timeline-<?= (int) $item['id'] ?>">تایم‌لاین</button>
            <button class="btn small secondary" type="button" data-open-modal="customer-medals-<?= (int) $item['id'] ?>">مدال‌ها</button>
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
        <div class="notice info full">پرونده اصلی باقی می‌ماند و قراردادها، پرونده‌های حقوقی، رسیدها، پیام‌ها، اعلان‌ها و مدال‌های مشتری تکراری به آن منتقل می‌شود. مشتری تکراری غیرفعال می‌شود.</div>
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

<div class="modal" id="create-customer">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('customers/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>نام کامل<input name="full_name" required></label>
        <label>نام پدر<input name="father_name"></label>
        <label>محل صدور<input name="issued_from"></label>
        <label>کد ملی<input name="national_id" required inputmode="numeric"></label>
        <label>موبایل<input name="mobile" required inputmode="tel"></label>
        <label>تلفن دوم<input name="secondary_phone" inputmode="tel"></label>
        <label>وضعیت<select name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>
        <label class="full">آدرس<textarea name="address" rows="3"></textarea></label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت مشتری</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

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

  <div class="modal" id="customer-medals-<?= (int) $item['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>مدال‌های <?= e($item['full_name']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <div class="proma-medal-list">
          <?php foreach (($item['medals'] ?? []) as $medal): ?>
            <div class="proma-medal-item">
              <form method="post" action="<?= e(url('customers/medalUpdate/' . $medal['id'])) ?>" class="form-grid three">
                <?= csrf_field() ?>
                <label>عنوان<input name="title" value="<?= e($medal['title']) ?>" required></label>
                <label>امتیاز<input name="points" value="<?= e(to_persian_digits($medal['points'])) ?>" inputmode="numeric"></label>
                <label>توضیح<input name="description" value="<?= e($medal['description']) ?>"></label>
                <label>آیکن<input name="icon_key" value="<?= e($medal['icon_key'] ?: 'award') ?>" placeholder="award, star, shield"></label>
                <div>
                  <span class="field-title">وضعیت</span>
                  <div class="switch-options">
                    <label><input type="checkbox" name="is_active" value="1"<?= checked((int) ($medal['is_active'] ?? 1), 1) ?>><span>فعال</span></label>
                  </div>
                </div>
                <div class="actions full">
                  <button class="btn small secondary icon-only" type="submit" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
                </div>
              </form>
              <form method="post" action="<?= e(url('customers/medalDelete/' . $medal['id'])) ?>" onsubmit="return confirm('حذف مدال تایید شود؟')">
                <?= csrf_field() ?>
                <button class="btn small danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (empty($item['medals'])): ?><div class="empty">مدالی ثبت نشده است.</div><?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('customers/medalStore/' . $item['id'])) ?>" class="form-grid three" style="margin-top:16px">
          <?= csrf_field() ?>
          <label>عنوان مدال<input name="title" required></label>
          <label>امتیاز<input name="points" inputmode="numeric" value="0"></label>
          <label>توضیح<input name="description"></label>
          <div class="full"><button class="btn warning" type="submit">افزودن مدال</button></div>
        </form>
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
      <form method="post" action="<?= e(url('customers/delete/' . $item['id'])) ?>">
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
