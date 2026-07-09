<?php
$legalStages = app_config('legal_stages', []);
$pagination = $pagination ?? ['total' => count($cases ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($cases ?? []) ?: 30];
$pageUrl = function ($page) {
    $params = [
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'page' => (int) $page > 1 ? (int) $page : null,
    ];
    return url('legal', array_filter($params, fn($value) => $value !== null && $value !== ''));
};
?>
<div data-ajax-results="legal">
<div class="row">
  <div class="col-xxl-4 col-xl-5">
    <section class="card">
      <div class="card-header card-no-border"><h5>ثبت پرونده شکایت</h5></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('legal/create')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <label class="full">قرارداد واجد شرایط
            <span class="proma-live-search" data-contract-picker data-search-url="<?= e(url('contracts/search', ['eligible' => 'legal', 'without_open_case' => '1'])) ?>">
              <input data-contract-picker-input autocomplete="off" required placeholder="شماره قرارداد، مشتری، موبایل یا کد ملی را جستجو کنید">
              <input type="hidden" name="contract_id" data-contract-picker-id>
              <span class="proma-live-results" data-contract-picker-results hidden></span>
            </span>
          </label>
          <label class="full">وکیل مسئول
            <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'lawyer'])) ?>">
              <input data-user-search-input autocomplete="off" placeholder="نام، موبایل یا واحد وکیل">
              <input type="hidden" name="lawyer_id" data-user-id-input>
              <span class="proma-live-results" data-user-search-results hidden></span>
              <span class="proma-chip-row" data-user-chip></span>
            </span>
          </label>
          <label class="full">شرح اولیه<textarea name="notes"></textarea></label>
          <div class="full"><button class="btn danger" type="submit">ثبت شکایت</button></div>
        </form>
      </div>
    </section>
  </div>

  <div class="col-xxl-8 col-xl-7">
    <section class="card">
      <div class="card-header card-no-border">
        <div class="header-top"><h5>پرونده‌های حقوقی</h5><span class="badge badge-light-danger"><?= to_persian_digits($pagination['total'] ?? count($cases)) ?> پرونده</span></div>
      </div>
      <div class="card-body pt-0">
        <form method="get" action="<?= e(url('legal')) ?>" class="form-grid three" data-ajax-filter data-ajax-target="[data-ajax-results='legal']">
          <input type="hidden" name="route" value="legal">
          <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="قرارداد، مشتری، موبایل یا شماره شکایت"></label>
          <label>وضعیت
            <select name="status">
              <option value="">همه وضعیت‌ها</option>
              <option value="open"<?= selected($_GET['status'] ?? '', 'open') ?>>باز</option>
              <option value="referred"<?= selected($_GET['status'] ?? '', 'referred') ?>>ارجاع شده</option>
              <option value="closed"<?= selected($_GET['status'] ?? '', 'closed') ?>>بسته</option>
            </select>
          </label>
          <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button><span class="proma-ajax-status" data-ajax-status></span></div>
        </form>
      </div>
      <div class="proma-list-meta">
        <span class="badge info">کل پرونده‌ها: <?= to_persian_digits($pagination['total'] ?? count($cases)) ?></span>
        <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>قرارداد</th><th>مشتری</th><th>وکیل</th><th>مرحله</th><th>شماره شکایت</th><th>هزینه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php foreach ($cases as $case): ?>
            <tr>
              <td><?= e($case['contract_number']) ?></td>
              <td><?= e($case['customer_name']) ?><br><span class="badge muted"><?= to_persian_digits($case['mobile']) ?></span></td>
              <td><?= e($case['lawyer_name'] ?: 'ارجاع نشده') ?></td>
              <td><?= e($case['stage']) ?></td>
              <td><?= to_persian_digits($case['complaint_number']) ?></td>
              <td><?= money_toman($case['expense_amount']) ?></td>
              <td><span class="badge <?= e(badge_class($case['status'])) ?>"><?= e(status_label($case['status'])) ?></span></td>
              <td class="actions">
                <a class="btn small info" href="<?= e(url('legal/show/' . (int) $case['id'])) ?>">جزئیات و تاریخچه</a>
                <button class="btn small secondary" type="button" data-open-modal="legal-case-<?= (int) $case['id'] ?>">به‌روزرسانی</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$cases): ?><tr><td colspan="8" class="empty">پرونده حقوقی ثبت نشده است.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_pagination($pagination, $pageUrl) ?>
    </section>
  </div>
</div>

<?php foreach ($cases as $case): ?>
  <div class="modal" id="legal-details-<?= (int) $case['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>جزئیات پرونده حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <div class="grid cols-2">
          <div><strong>مشتری:</strong> <?= e($case['customer_name']) ?></div>
          <div><strong>تماس:</strong> <?= to_persian_digits($case['mobile']) ?></div>
          <div><strong>قرارداد:</strong> <?= e($case['contract_number']) ?></div>
          <div><strong>وکیل:</strong> <?= e($case['lawyer_name'] ?: 'ارجاع نشده') ?></div>
          <div><strong>مرحله:</strong> <?= e($case['stage']) ?></div>
          <div><strong>هزینه:</strong> <?= money_toman($case['expense_amount']) ?></div>
          <div><strong>تاریخ ابلاغ:</strong> <?= !empty($case['notice_date']) ? e(jdate($case['notice_date'])) : '-' ?></div>
          <div><strong>تاریخ دادگاه:</strong> <?= !empty($case['court_date']) ? e(jdate($case['court_date'])) : '-' ?></div>
          <div><strong>تاریخ جلسه رسیدگی:</strong> <?= !empty($case['hearing_date']) ? e(jdate($case['hearing_date'])) : '-' ?></div>
          <div><strong>علت هزینه:</strong> <?= e($case['expense_reason'] ?: '-') ?></div>
        </div>
        <div class="proma-payment-timeline compact" style="margin-top:16px">
          <div class="proma-timeline-item">
            <span class="proma-timeline-dot"></span>
            <div><strong>ثبت پرونده</strong><p><?= e($case['notes'] ?: 'بدون یادداشت') ?></p></div>
            <time><?= e(jdate($case['created_at'])) ?></time>
          </div>
          <div class="proma-timeline-item">
            <span class="proma-timeline-dot"></span>
            <div><strong>آخرین به‌روزرسانی</strong><p><?= e(status_label($case['status'])) ?> - <?= e($case['stage']) ?></p></div>
            <time><?= e(jdate($case['updated_at'])) ?></time>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="legal-case-<?= (int) $case['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>به‌روزرسانی پرونده</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('legal/update/' . $case['id'])) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <label>وکیل
            <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'lawyer'])) ?>">
              <input data-user-search-input autocomplete="off" value="<?= e($case['lawyer_name'] ?? '') ?>" placeholder="نام، موبایل یا واحد وکیل">
              <input type="hidden" name="lawyer_id" value="<?= (int) ($case['lawyer_id'] ?? 0) ?>" data-user-id-input>
              <span class="proma-live-results" data-user-search-results hidden></span>
              <span class="proma-chip-row" data-user-chip></span>
            </span>
          </label>
          <label>مرحله
            <select name="stage" required>
              <?php foreach ($legalStages as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'], $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>وضعیت<select name="status"><option value="open"<?= selected($case['status'], 'open') ?>>باز</option><option value="referred"<?= selected($case['status'], 'referred') ?>>ارجاع شده</option><option value="closed"<?= selected($case['status'], 'closed') ?>>بسته</option></select></label>
          <label>شماره شکایت<input name="complaint_number" value="<?= e($case['complaint_number']) ?>"></label>
          <label>تاریخ ابلاغ<input name="notice_date" value="<?= e(!empty($case['notice_date']) ? jdate($case['notice_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>تاریخ دادگاه<input name="court_date" value="<?= e(!empty($case['court_date']) ? jdate($case['court_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>تاریخ جلسه رسیدگی<input name="hearing_date" value="<?= e(!empty($case['hearing_date']) ? jdate($case['hearing_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>هزینه حقوقی<input name="expense_amount" data-money value="<?= e(number_format(ceil((float) $case['expense_amount']), 0)) ?>"></label>
          <label class="full">علت هزینه<input name="expense_reason" value="<?= e($case['expense_reason'] ?? '') ?>" placeholder="برای هر هزینه، علت را ثبت کنید"></label>
          <label class="full">یادداشت<textarea name="notes"><?= e($case['notes']) ?></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
