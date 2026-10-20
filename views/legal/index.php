<?php
$legalStages = app_config('legal_stages', []);
$legalStageOptions = $legalStageOptions ?? $legalStages;
$legalCostTypeOptions = $legalCostTypeOptions ?? [];
$legalQueue = $legalQueue ?? 'main';
$legalQueueRoute = $legalQueueRoute ?? 'legal';
$isLawyer = Auth::role() === 'lawyer';
$pagination = $pagination ?? ['total' => count($cases ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($cases ?? []) ?: 30];
$legalSummary = $legalSummary ?? ['total' => 0, 'open_count' => 0, 'referred_count' => 0, 'closed_count' => 0];
$pageUrl = function ($page) use ($legalQueueRoute) {
    $params = [
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'page' => (int) $page > 1 ? (int) $page : null,
    ];
    return url($legalQueueRoute, array_filter($params, fn($value) => $value !== null && $value !== ''));
};
?>
<div data-ajax-results="legal">
<div class="row">
  <div class="col-12">
    <section class="card">
      <div class="card-header card-no-border">
        <div class="header-top"><div><h5><?= $isLawyer ? 'پرونده‌های حقوقی من' : 'پرونده‌های حقوقی' ?></h5><small class="text-muted">پرونده‌ها بر اساس وضعیت و مرحله حقوقی از هم جدا شده‌اند.</small></div><div class="actions"><span class="badge badge-light-danger"><?= to_persian_digits($pagination['total'] ?? count($cases)) ?> پرونده</span><a class="btn small secondary" data-xlsx-export href="<?= e(url($legalQueue === 'referred' ? 'legal/export/referred' : 'legal/export')) ?>"><?= proma_icon('download') ?><span>خروجی Excel</span></a></div></div>
      </div>
      <div class="card-body pt-0"><nav class="proma-contract-tabs" aria-label="صف پرونده‌های حقوقی">
        <a class="<?= $legalQueue === 'main' ? 'active' : '' ?>" href="<?= e(url('legal')) ?>">پرونده‌های حقوقی</a>
        <a class="<?= $legalQueue === 'referred' ? 'active' : '' ?>" href="<?= e(url('legal/referred')) ?>">پرونده‌های ارجاع‌شده</a>
        <a href="<?= e(url('legal/eligible')) ?>">واجد شرایط بررسی حقوقی</a>
      </nav>
      <div class="proma-legal-summary" aria-label="خلاصه پرونده‌های حقوقی">
        <article class="is-open"><span>پرونده‌های باز</span><strong><?= to_persian_digits($legalSummary['open_count'] ?? 0) ?></strong></article>
        <article class="is-referred"><span>ارجاع‌شده</span><strong><?= to_persian_digits($legalSummary['referred_count'] ?? 0) ?></strong></article>
        <article class="is-closed"><span>بسته‌شده</span><strong><?= to_persian_digits($legalSummary['closed_count'] ?? 0) ?></strong></article>
        <article class="is-total"><span>کل پرونده‌ها</span><strong><?= to_persian_digits($legalSummary['total'] ?? 0) ?></strong></article>
      </div></div>
      <div class="card-body pt-0">
        <form method="get" action="<?= e(url($legalQueueRoute)) ?>" class="form-grid three" data-ajax-filter data-ajax-target="[data-ajax-results='legal']">
          <input type="hidden" name="route" value="<?= e($legalQueueRoute) ?>">
          <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="قرارداد، مشتری، موبایل یا شماره شکایت"></label>
          <label>وضعیت
            <select name="status">
              <option value="">همه وضعیت‌ها</option>
              <option value="open"<?= selected($_GET['status'] ?? '', 'open') ?>>باز</option>
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
          <thead><tr><th scope="col">قرارداد</th><th scope="col">مشتری</th><th scope="col">وکیل</th><th scope="col">مرحله</th><th scope="col">شماره شکایت</th><th scope="col">هزینه</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead>
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
              <td class="actions proma-legal-row-actions">
                <a class="btn small info icon-only" href="<?= e(url('legal/show/' . (int) $case['id'])) ?>" title="جزئیات و تاریخچه" aria-label="جزئیات و تاریخچه"><i data-feather="eye"></i></a>
                <button class="btn small icon-only" type="button" data-open-modal="legal-quick-update-<?= (int) $case['id'] ?>" title="اقدام سریع" aria-label="اقدام سریع"><i data-feather="activity"></i></button>
                <?php if (!$isLawyer): ?>
                  <button class="btn small secondary icon-only" type="button" data-open-modal="legal-case-<?= (int) $case['id'] ?>" title="ویرایش پرونده" aria-label="ویرایش پرونده"><i data-feather="edit-3"></i></button>
                  <button class="btn small danger icon-only" type="button" data-open-modal="delete-legal-case-<?= (int) $case['id'] ?>" title="حذف پرونده" aria-label="حذف پرونده"><i data-feather="trash-2"></i></button>
                <?php endif; ?>
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
  <div class="modal" id="legal-quick-update-<?= (int) $case['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>اقدام سریع پرونده <?= e($case['contract_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <div class="proma-quick-action-grid proma-quick-action-grid--legal-list">
          <form class="proma-quick-action-card proma-quick-action-card--form" method="post" action="<?= e(url('legal/storeProgress/' . (int) $case['id'])) ?>">
            <?= csrf_field() ?>
            <strong>ثبت روند / مرحله</strong>
            <span>بدون ورود به صفحه جزئیات، مرحله جدید پرونده را ثبت کنید.</span>
            <label>مرحله
              <select name="action_stage" required>
                <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'], $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label>عنوان<input name="action_title" required value="<?= e($case['stage'] ?: 'ثبت روند پرونده') ?>"></label>
            <label>وضعیت
              <select name="next_status">
                <option value="open"<?= selected($case['status'], 'open') ?>>باز</option>
                <option value="referred"<?= selected($case['status'], 'referred') ?>>ارجاع شده</option>
                <option value="closed"<?= selected($case['status'], 'closed') ?>>بسته</option>
              </select>
            </label>
            <label>تاریخ<input name="action_date" data-jalali-input required value="<?= e(jdate(date('Y-m-d'))) ?>"></label>
            <label class="full">شرح کوتاه<textarea name="description" rows="3" placeholder="توضیح روند، پاسخ مشتری یا اقدام انجام‌شده"></textarea></label>
            <button class="btn" type="submit">ثبت روند</button>
          </form>

          <form class="proma-quick-action-card proma-quick-action-card--form" method="post" action="<?= e(url('legal/storeCost/' . (int) $case['id'])) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <strong>ثبت هزینه حقوقی</strong>
            <span>هزینه در انتظار تأیید مدیریت ثبت می‌شود و سابقه آن حفظ می‌ماند.</span>
            <input type="hidden" name="action_stage" value="<?= e($case['stage'] ?: 'سایر') ?>">
            <label>نوع هزینه
              <select name="cost_type" required>
                <option value="">انتخاب کنید</option>
                <?php foreach ($legalCostTypeOptions as $costType): ?><option value="<?= e($costType) ?>"><?= e($costType) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label>مبلغ<input name="cost_amount" data-money inputmode="numeric" required></label>
            <label>عنوان<input name="action_title" required value="ثبت هزینه حقوقی"></label>
            <label>پیوست اختیاری<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
            <label class="full">شرح هزینه<textarea name="description" rows="3" required></textarea></label>
            <button class="btn warning" type="submit">ثبت هزینه</button>
          </form>

          <form class="proma-quick-action-card proma-quick-action-card--form" method="post" action="<?= e(url('legal/storeAttachment/' . (int) $case['id'])) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <strong>ارسال ضمیمه</strong>
            <span>فایل ضمیمه مستقیماً به پرونده، قرارداد و لاگ حقوقی مرتبط می‌شود.</span>
            <label>مرحله
              <select name="action_stage" required>
                <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'], $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label>عنوان فایل<input name="action_title" required value="ارسال فایل ضمیمه پرونده"></label>
            <label>فایل<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
            <label class="full">توضیحات<textarea name="description" rows="3"></textarea></label>
            <button class="btn success" type="submit">ثبت ضمیمه</button>
          </form>
        </div>
      </div>
      <div class="modal-footer"><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </div>
  </div>

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
        <div class="proma-payment-timeline compact mt-3">
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

  <?php if (!$isLawyer): ?><div class="modal" id="legal-case-<?= (int) $case['id'] ?>">
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
          <label>تاریخ ابلاغ<input name="notice_date" data-jalali-input value="<?= e(!empty($case['notice_date']) ? jdate($case['notice_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>تاریخ دادگاه<input name="court_date" data-jalali-input value="<?= e(!empty($case['court_date']) ? jdate($case['court_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>تاریخ جلسه رسیدگی<input name="hearing_date" data-jalali-input value="<?= e(!empty($case['hearing_date']) ? jdate($case['hearing_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>هزینه حقوقی<input name="expense_amount" data-money value="<?= e(number_format(ceil((float) $case['expense_amount']), 0)) ?>"></label>
          <label class="full">علت هزینه<input name="expense_reason" value="<?= e($case['expense_reason'] ?? '') ?>" placeholder="برای هر هزینه، علت را ثبت کنید"></label>
          <label class="full">یادداشت<textarea name="notes"><?= e($case['notes']) ?></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
  <div class="modal" id="delete-legal-case-<?= (int) $case['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف پرونده حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('legal/retireCase/' . (int) $case['id'])) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteCaseCode = ConfirmationCode::hint('legal_case_delete_' . (int) $case['id']); ?>
          <div class="notice error">پرونده حقوقی «<?= e($case['contract_number']) ?>» و لاگ‌های ضمیمه‌شده به همین پرونده حذف می‌شود. برای تایید عدد <strong class="ltr"><?= e($deleteCaseCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCaseCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
<?php endforeach; ?>
</div>
