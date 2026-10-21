<?php
/** @var OverdueFilterCriteria $criteria */
$criteria = $criteria ?? OverdueFilterCriteria::fromRequest($_GET);
$overdueContracts = $overdueContracts ?? [];
$contractContacts = $contractContacts ?? [];
$followupHistoryByContract = $followupHistoryByContract ?? [];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25, 'from' => 0, 'to' => 0, 'summary' => []];
$summary = $pagination['summary'] ?? [];
$pageUrl = static function (int $page) use ($criteria): string {
    $params = $criteria->queryParameters(false);
    if ($page > 1) $params['page'] = $page;
    return url('overdue', $params);
};
$tabUrl = static function (string $bucket) use ($criteria): string {
    $params = $criteria->queryParameters(false);
    if ($bucket === '') unset($params['bucket']); else $params['bucket'] = $bucket;
    unset($params['page']);
    return url('overdue', $params);
};
$clearUrl = url('overdue');
$statusLabel = static function (array $item): array {
    if (!empty($item['legal_case_count'])) return ['پرونده حقوقی فعال', 'danger'];
    if (!empty($item['legal_eligible'])) return ['واجد اقدام حقوقی', 'warning'];
    return ['نیازمند پیگیری', 'info'];
};
$chips = [];
foreach ([
    'q' => ['جستجو', $criteria->search],
    'min_overdue_days' => ['تاخیر از', $criteria->minOverdueDays !== null ? to_persian_digits($criteria->minOverdueDays) . ' روز' : ''],
    'min_overdue_count' => ['حداقل قسط معوق', $criteria->minOverdueCount !== null ? to_persian_digits($criteria->minOverdueCount) : ''],
    'min_overdue_amount' => ['حداقل مبلغ', $criteria->minOverdueAmount !== null ? money_toman($criteria->minOverdueAmount) : ''],
    'contract_status' => ['وضعیت قرارداد', $criteria->contractStatus !== '' ? status_label($criteria->contractStatus) : ''],
    'legal_eligible' => ['وضعیت حقوقی', $criteria->legalEligible === 'yes' ? 'واجد اقدام' : ($criteria->legalEligible === 'no' ? 'غیر واجد' : '')],
    'bucket' => ['بازه تاخیر', $criteria->bucket],
] as $key => [$label, $value]) {
    if ($value !== '') $chips[$key] = [$label, $value];
}
?>

<section class="proma-overdue-page" aria-label="صف پیگیری سررسیدهای گذشته">
  <header class="proma-page-intro">
    <div>
      <span class="proma-eyebrow">صف پیگیری قراردادها</span>
      <h2>سررسیدهای گذشته</h2>
      <p>هر قرارداد فقط یک‌بار نمایش داده می‌شود؛ تعداد اقساط، مبلغ و تاخیر در همان سطح تجمیع شده‌اند.</p>
    </div>
    <div class="actions"><a class="btn secondary" data-xlsx-export href="<?= e(url('overdue/export')) ?>"><?= proma_icon('download') ?><span>خروجی Excel</span></a><a class="btn secondary" href="<?= e(url('contracts')) ?>"><?= proma_icon('file') ?><span>قراردادها</span></a></div>
  </header>

  <div class="proma-kpi-grid proma-overdue-kpis" aria-label="خلاصه سررسیدها">
    <article class="proma-stat-card"><span>قراردادهای نیازمند پیگیری</span><strong><?= to_persian_digits($summary['contracts'] ?? 0) ?></strong></article>
    <article class="proma-stat-card"><span>اقساط معوق</span><strong><?= to_persian_digits($summary['installments'] ?? 0) ?></strong></article>
    <article class="proma-stat-card"><span>مبلغ برآوردی قابل پیگیری</span><strong><?= money_toman($summary['estimated_payable'] ?? 0) ?></strong></article>
    <article class="proma-stat-card is-warning"><span>بیشترین تاخیر</span><strong><?= to_persian_digits($summary['max_overdue_days'] ?? 0) ?> روز</strong></article>
  </div>

  <section class="card proma-filter-card">
    <div class="card-header card-no-border"><div class="header-top"><div><h5>فیلتر و اولویت‌بندی</h5><span>با تغییر هر فیلتر، صفحه به نتیجهٔ اول بازمی‌گردد.</span></div></div></div>
    <div class="card-body pt-0">
      <form method="get" action="<?= e(url('overdue')) ?>" class="proma-operational-filter" data-ajax-filter data-ajax-target="[data-ajax-results='overdue']" data-filter-reset-page="1">
        <input type="hidden" name="route" value="overdue">
        <input type="hidden" name="page" value="1" data-filter-page>
        <label class="proma-filter-search"><span>جستجوی مشتری یا قرارداد</span><input name="q" value="<?= e($criteria->search) ?>" placeholder="نام، موبایل، کد ملی یا شماره قرارداد"></label>
        <label><span>حداقل تاخیر</span><input name="min_overdue_days" value="<?= e($criteria->minOverdueDays ?? '') ?>" inputmode="numeric" placeholder="روز"></label>
        <label><span>حداقل اقساط معوق</span><input name="min_overdue_count" value="<?= e($criteria->minOverdueCount ?? '') ?>" inputmode="numeric" placeholder="تعداد"></label>
        <label><span>حداقل مبلغ معوق</span><input name="min_overdue_amount" value="<?= e($criteria->minOverdueAmount ?? '') ?>" data-money placeholder="تومان"></label>
        <label><span>وضعیت قرارداد</span><select name="contract_status"><option value="">همه</option><?php foreach (OverdueFilterCriteria::CONTRACT_STATUSES as $status): ?><option value="<?= e($status) ?>"<?= selected($criteria->contractStatus, $status) ?>><?= e(status_label($status)) ?></option><?php endforeach; ?></select></label>
        <label><span>وضعیت حقوقی</span><select name="legal_eligible"><option value="">همه</option><option value="yes"<?= selected($criteria->legalEligible, 'yes') ?>>واجد اقدام</option><option value="no"<?= selected($criteria->legalEligible, 'no') ?>>غیر واجد</option></select></label>
        <label class="proma-filter-check"><input type="checkbox" name="exclude_legal_cases" value="1"<?= checked($criteria->excludeLegalCases) ?>><span>قراردادهای دارای پروندهٔ حقوقی فعال نمایش داده نشوند</span></label>
        <label><span>مرتب‌سازی</span><select name="sort">
          <option value="oldest"<?= selected($criteria->sort, 'oldest') ?>>قدیمی‌ترین سررسید</option>
          <option value="newest"<?= selected($criteria->sort, 'newest') ?>>جدیدترین سررسید</option>
          <option value="overdue_count_desc"<?= selected($criteria->sort, 'overdue_count_desc') ?>>بیشترین تعداد اقساط معوق</option>
          <option value="overdue_count_asc"<?= selected($criteria->sort, 'overdue_count_asc') ?>>کمترین تعداد اقساط معوق</option>
          <option value="delay_desc"<?= selected($criteria->sort, 'delay_desc') ?>>بیشترین روز تاخیر</option>
          <option value="amount_desc"<?= selected($criteria->sort, 'amount_desc') ?>>بیشترین مبلغ معوق</option>
          <option value="penalty_desc"<?= selected($criteria->sort, 'penalty_desc') ?>>بیشترین جریمه</option>
          <option value="legal_action"<?= selected($criteria->sort, 'legal_action') ?>>نزدیک‌ترین اقدام حقوقی</option>
          <option value="name_asc"<?= selected($criteria->sort, 'name_asc') ?>>نام مشتری</option>
          <option value="contract_asc"<?= selected($criteria->sort, 'contract_asc') ?>>شماره قرارداد</option>
        </select></label>
        <div class="proma-filter-submit"><button class="btn" type="submit">اعمال فیلتر</button><a class="btn secondary" href="<?= e($clearUrl) ?>">پاک‌کردن</a><span class="proma-ajax-status" data-ajax-status></span></div>
        <details class="proma-filter-drawer full">
          <summary>فیلترهای پیشرفته<?= $criteria->activeFilterCount() > 0 ? ' (' . to_persian_digits($criteria->activeFilterCount()) . ')' : '' ?></summary>
          <div class="proma-filter-drawer__content">
            <label><span>شناسه مشتری</span><input name="customer_id" value="<?= e($criteria->customerId ?? '') ?>" inputmode="numeric"></label>
            <label><span>شناسه قرارداد</span><input name="contract_id" value="<?= e($criteria->contractId ?? '') ?>" inputmode="numeric"></label>
            <label><span>حداکثر تاخیر</span><input name="max_overdue_days" value="<?= e($criteria->maxOverdueDays ?? '') ?>" inputmode="numeric"></label>
            <label><span>حداکثر اقساط معوق</span><input name="max_overdue_count" value="<?= e($criteria->maxOverdueCount ?? '') ?>" inputmode="numeric"></label>
            <label><span>حداکثر مبلغ معوق</span><input name="max_overdue_amount" value="<?= e($criteria->maxOverdueAmount ?? '') ?>" data-money></label>
            <label><span>سررسید از</span><input name="due_date_from" value="<?= e($criteria->dueDateFrom ? jdate($criteria->dueDateFrom) : '') ?>" placeholder="۱۴۰۵/۰۱/۰۱"></label>
            <label><span>سررسید تا</span><input name="due_date_to" value="<?= e($criteria->dueDateTo ? jdate($criteria->dueDateTo) : '') ?>" placeholder="۱۴۰۵/۱۲/۲۹"></label>
            <label><span>پرونده حقوقی</span><select name="legal_case_exists"><option value="">همه</option><option value="yes"<?= selected($criteria->legalCaseExists, 'yes') ?>>دارد</option><option value="no"<?= selected($criteria->legalCaseExists, 'no') ?>>ندارد</option></select></label>
            <label><span>وضعیت اخطار</span><select name="warning_status"><option value="">همه</option><option value="sent"<?= selected($criteria->warningStatus, 'sent') ?>>ثبت‌شده</option><option value="not_sent"<?= selected($criteria->warningStatus, 'not_sent') ?>>ثبت‌نشده</option></select></label>
            <label><span>وعده پرداخت</span><select name="promise_state"><option value="">همه</option><option value="active"<?= selected($criteria->promiseState, 'active') ?>>فعال</option><option value="expired"<?= selected($criteria->promiseState, 'expired') ?>>منقضی</option><option value="none"<?= selected($criteria->promiseState, 'none') ?>>ندارد</option></select></label>
            <label><span>بدون تماس در</span><input name="no_contact_days" value="<?= e($criteria->noContactDays ?? '') ?>" inputmode="numeric" placeholder="روز"></label>
            <?php if (Auth::role() === 'admin'): ?><label><span>اپراتور</span><select name="operator_id"><option value="">همه اپراتورها</option><?php foreach (($operators ?? []) as $operator): ?><option value="<?= (int) $operator['id'] ?>"<?= selected($criteria->operatorId, $operator['id']) ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <label><span>وکیل</span><select name="lawyer_id"><option value="">همه وکلا</option><?php foreach (($lawyers ?? []) as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"<?= selected($criteria->lawyerId, $lawyer['id']) ?>><?= e($lawyer['full_name']) ?></option><?php endforeach; ?></select></label>
            <label><span>تعداد در صفحه</span><select name="per_page"><?php foreach ([25, 50, 100] as $size): ?><option value="<?= $size ?>"<?= selected($criteria->perPage, $size) ?>><?= to_persian_digits($size) ?></option><?php endforeach; ?></select></label>
          </div>
        </details>
      </form>
      <?php if ($chips): ?><div class="proma-filter-chips" aria-label="فیلترهای فعال"><?php foreach ($chips as [$label, $value]): ?><span><?= e($label) ?>: <strong><?= e($value) ?></strong></span><?php endforeach; ?><a href="<?= e($clearUrl) ?>">حذف همه</a></div><?php endif; ?>
      <nav class="proma-status-tabs" aria-label="بازه سررسید">
        <a class="<?= $criteria->bucket === '' ? 'active' : '' ?>" href="<?= e($tabUrl('')) ?>">همه</a>
        <a class="<?= $criteria->bucket === 'today' ? 'active' : '' ?>" href="<?= e($tabUrl('today')) ?>">امروز</a>
        <a class="<?= $criteria->bucket === '1-7' ? 'active' : '' ?>" href="<?= e($tabUrl('1-7')) ?>">۱ تا ۷ روز</a>
        <a class="<?= $criteria->bucket === '8-30' ? 'active' : '' ?>" href="<?= e($tabUrl('8-30')) ?>">۸ تا ۳۰ روز</a>
        <a class="<?= $criteria->bucket === '30+' ? 'active' : '' ?>" href="<?= e($tabUrl('30+')) ?>">بیش از ۳۰ روز</a>
      </nav>
    </div>
  </section>

  <div data-ajax-results="overdue">
    <section class="card proma-overdue-results">
      <div class="card-header card-no-border"><div class="header-top"><div><h5>قراردادهای نیازمند اقدام</h5><span>نمایش <?= to_persian_digits($pagination['from'] ?? 0) ?> تا <?= to_persian_digits($pagination['to'] ?? 0) ?> از <?= to_persian_digits($pagination['total'] ?? 0) ?> قرارداد</span></div></div></div>
      <div class="card-body pt-0">
        <?php if (!$overdueContracts): ?>
          <div class="proma-empty-state">
            <?= proma_icon('history') ?>
            <h3><?= $criteria->activeFilterCount() ? 'نتیجه‌ای مطابق فیلترهای انتخاب‌شده پیدا نشد.' : 'سررسید گذشته‌ای برای پیگیری وجود ندارد.' ?></h3>
            <p><?= $criteria->activeFilterCount() ? 'فیلترها را پاک کنید یا بازهٔ تاخیر را تغییر دهید.' : 'با ایجاد سررسید گذشته، قراردادها در همین صف نمایش داده می‌شوند.' ?></p>
            <a class="btn secondary" href="<?= e($criteria->activeFilterCount() ? $clearUrl : url('installments')) ?>"><?= $criteria->activeFilterCount() ? 'پاک‌کردن فیلترها' : 'مشاهده اقساط' ?></a>
          </div>
        <?php else: ?>
          <div class="table-responsive proma-overdue-table">
            <table class="table table-bordernone">
              <thead><tr><th scope="col">مشتری و قرارداد</th><th scope="col">شاخص تاخیر</th><th scope="col">مبلغ قابل پیگیری</th><th scope="col">حقوقی و آخرین پیگیری</th><th scope="col">اقدام</th></tr></thead>
              <tbody><?php foreach ($overdueContracts as $item): ?><?php [$legalLabel, $legalClass] = $statusLabel($item); $contractId = (int) ($item['contract_id'] ?? 0); $installmentUrl = url('installments', ['tab' => 'overdue', 'contract_number' => $item['contract_number']]); $phone = normalize_iran_phone($item['mobile'] ?? ''); $contactDirectory = $contractContacts[$contractId] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; $primaryInstallmentId = (int) (($item['installment_ids'][0] ?? 0) ?: 0); $hasActivePromise = !empty($item['latest_promise_date']) && (string) $item['latest_promise_date'] >= date('Y-m-d'); $recentFollowups = $followupHistoryByContract[$contractId] ?? []; $hasRecordedFollowup = !empty($recentFollowups); $lastFollowup = $recentFollowups[0] ?? null; $followupButtonTitle = $hasActivePromise ? 'پیگیری فعال تا ' . jdate($item['latest_promise_date']) : ($lastFollowup ? 'آخرین پیگیری: ' . jdatetime($lastFollowup['created_at'] ?? '') : 'ثبت پیگیری'); ?>
                <tr>
                  <td><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?> · <?php if ($phone !== ''): ?><button class="proma-phone-link" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($item['customer_name']) ?>"><?= to_persian_digits(format_iran_phone($phone)) ?></button><?php else: ?><?= to_persian_digits(format_iran_phone($phone)) ?><?php endif; ?></small></td>
                  <td><div class="proma-metric-pair"><strong><?= to_persian_digits($item['overdue_count']) ?> قسط</strong><span><?= to_persian_digits($item['max_overdue_days']) ?> روز بیشترین تاخیر</span><small>قدیمی‌ترین: <?= e(jdate($item['oldest_due_date'])) ?></small></div></td>
                  <td><div class="proma-metric-pair"><strong><?= money_toman($item['total_overdue_amount'] ?? $item['estimated_payable']) ?></strong><span>اقساط <?= money_toman($item['overdue_installments_total'] ?? $item['remaining_principal']) ?> · جریمه <?= money_toman($item['total_penalty'] ?? $item['estimated_penalty']) ?></span><small>هزینه حقوقی <?= money_toman($item['outstanding_legal_costs'] ?? 0) ?></small></div></td>
                  <td><span class="badge <?= e($legalClass) ?>"><?= e($legalLabel) ?></span><small><?= !empty($item['last_contact_at']) ? 'آخرین پیگیری: ' . e(jdatetime($item['last_contact_at'])) : 'پیگیری ثبت نشده' ?></small></td>
                  <td><div class="proma-row-actions"><?php if ($contactDirectory): ?><button class="btn small success proma-call-action" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($item['customer_name']) ?>"><?= proma_icon('phone') ?><span>تماس</span></button><?php endif; ?><button class="btn small secondary" type="button" data-open-modal="overdue-debt-<?= $contractId ?>">جزئیات بدهی</button><a class="btn small" href="<?= e(url('contracts/show/' . $contractId . '#contract-summary')) ?>">مشاهده قرارداد</a><?php if ($primaryInstallmentId): ?><button class="btn small <?= $hasRecordedFollowup ? 'success proma-followup-recorded' : 'secondary' ?><?= $hasActivePromise ? ' proma-followup-active' : '' ?>" type="button" data-open-modal="overdue-followup-<?= $primaryInstallmentId ?>" aria-label="<?= $hasRecordedFollowup ? 'پیگیری ثبت‌شده' : 'ثبت پیگیری' ?> <?= e($item['contract_number']) ?>" title="<?= e($followupButtonTitle) ?>"><?= proma_icon($hasRecordedFollowup ? 'check-circle' : 'history') ?><span><?= $hasRecordedFollowup ? 'پیگیری ثبت شد' : 'ثبت پیگیری' ?></span></button><?php endif; ?><?php if ($primaryInstallmentId && empty($item['legal_case_count'])): ?><button class="icon-btn" type="button" data-open-modal="overdue-legal-<?= $primaryInstallmentId ?>" aria-label="ارجاع مستقیم به حقوقی" title="ارجاع به حقوقی"><?= proma_icon('file') ?></button><?php endif; ?><a class="icon-btn" href="<?= e(url('chat', ['contact' => $item['customer_id']])) ?>" aria-label="گفت‌وگو با <?= e($item['customer_name']) ?>" title="گفت‌وگو"><?= proma_icon('history') ?></a><details class="proma-action-menu"><summary aria-label="عملیات بیشتر <?= e($item['contract_number']) ?>"><?= proma_icon('more') ?></summary><div><a href="<?= e($installmentUrl) ?>">اقساط معوق</a><a href="<?= e(url('contracts/show/' . $contractId . '#contract-installments')) ?>">ثبت پرداخت</a><a href="<?= e(url('contracts/booklet/' . $contractId)) ?>" target="_blank" rel="noopener">دفترچه اقساط</a></div></details></div></td>
                </tr>
              <?php endforeach; ?></tbody>
            </table>
          </div>
          <div class="proma-overdue-mobile-list">
            <?php foreach ($overdueContracts as $item): ?><?php [$legalLabel, $legalClass] = $statusLabel($item); $contractId = (int) ($item['contract_id'] ?? 0); $contactDirectory = $contractContacts[$contractId] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; $primaryInstallmentId = (int) (($item['installment_ids'][0] ?? 0) ?: 0); $recentFollowups = $followupHistoryByContract[$contractId] ?? []; $hasRecordedFollowup = !empty($recentFollowups); $hasActivePromise = !empty($item['latest_promise_date']) && (string) $item['latest_promise_date'] >= date('Y-m-d'); $phone = normalize_iran_phone($item['mobile'] ?? ''); ?>
              <article class="proma-overdue-mobile-card"><header><div><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?></small><?php if ($phone !== ''): ?><button class="proma-overdue-mobile-card__phone" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($item['customer_name']) ?>"><?= proma_icon('phone') ?> <?= to_persian_digits(format_iran_phone($phone)) ?></button><?php endif; ?></div><span class="badge <?= e($legalClass) ?>"><?= e($legalLabel) ?></span></header><div class="proma-overdue-mobile-card__metrics"><span><small>اقساط معوق</small><strong><?= to_persian_digits($item['overdue_count']) ?></strong></span><span><small>بیشترین تاخیر</small><strong><?= to_persian_digits($item['max_overdue_days']) ?> روز</strong></span><span><small>قابل پیگیری</small><strong><?= money_toman($item['total_overdue_amount'] ?? $item['estimated_payable']) ?></strong></span></div><footer><?php if ($contactDirectory): ?><button class="btn small success proma-call-action" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($item['customer_name']) ?>"><?= proma_icon('phone') ?><span>تماس</span></button><?php endif; ?><?php if ($primaryInstallmentId): ?><button class="btn small <?= $hasRecordedFollowup ? 'success proma-followup-recorded' : 'secondary' ?><?= $hasActivePromise ? ' proma-followup-active' : '' ?>" type="button" data-open-modal="overdue-followup-<?= $primaryInstallmentId ?>" aria-label="<?= $hasRecordedFollowup ? 'پیگیری ثبت‌شده' : 'ثبت پیگیری' ?> <?= e($item['contract_number']) ?>" title="<?= $hasRecordedFollowup ? 'آخرین پیگیری: ' . e(jdatetime($recentFollowups[0]['created_at'] ?? '')) : 'ثبت پیگیری' ?>"><?= proma_icon($hasRecordedFollowup ? 'check-circle' : 'history') ?> <?= $hasRecordedFollowup ? 'پیگیری ثبت شد' : 'ثبت پیگیری' ?></button><?php endif; ?><button class="btn small secondary" type="button" data-open-modal="overdue-debt-<?= $contractId ?>">تفکیک بدهی</button><a class="btn small" href="<?= e(url('contracts/show/' . $contractId . '#contract-summary')) ?>">مشاهده قرارداد</a></footer></article>
            <?php endforeach; ?>
          </div>
          <?= render_pagination($pagination, $pageUrl) ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php foreach ($overdueContracts as $item): ?>
  <?php $breakdown = $item['financial_breakdown'] ?? []; $contractId = (int) ($item['contract_id'] ?? 0); ?>
  <div class="modal" id="overdue-debt-<?= $contractId ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تفکیک بدهی قرارداد <?= e($item['contract_number']) ?></h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div>
      <div class="modal-body">
        <?php if (!empty($item['calculation_warnings'])): ?><div class="notice warning">محاسبهٔ یک یا چند قسط نیازمند بررسی است؛ مبالغ را پیش از دریافت با جزئیات قرارداد تطبیق دهید.</div><?php endif; ?>
        <dl class="proma-overdue-debt-breakdown">
          <div><dt>ماندهٔ اصل اقساط معوق</dt><dd><?= money_toman($breakdown['overdue_installments_total'] ?? $item['overdue_installments_total'] ?? 0) ?></dd></div>
          <div><dt>ماندهٔ جریمهٔ دیرکرد عادی</dt><dd><?= money_toman($breakdown['outstanding_normal_late_fees'] ?? 0) ?></dd></div>
          <div><dt>ماندهٔ جریمهٔ دیرکرد حقوقی</dt><dd><?= money_toman($breakdown['outstanding_legal_late_fees'] ?? 0) ?></dd></div>
          <div><dt>ماندهٔ هزینه‌های حقوقی قابل وصول</dt><dd><?= money_toman($breakdown['outstanding_legal_costs'] ?? 0) ?></dd></div>
          <div class="total"><dt>مجموع بدهی سررسیدشده</dt><dd><?= money_toman($breakdown['total_overdue_debt'] ?? $item['total_overdue_amount'] ?? 0) ?></dd></div>
        </dl>
        <div class="notice info">هزینهٔ حقوقی در این جمع فقط یک بار برای قرارداد محاسبه شده است. بدهی اقساط آینده در این مبلغ نیست.</div>
      </div>
      <div class="modal-footer"><a class="btn" href="<?= e(url('contracts/show/' . $contractId . '#contract-summary')) ?>">سابقه و جزئیات قرارداد</a><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </div>
  </div>
<?php endforeach; ?>

<?php foreach ($overdueContracts as $item): ?>
  <?php $contractId = (int) ($item['contract_id'] ?? 0); $recentFollowups = $followupHistoryByContract[$contractId] ?? []; $primaryInstallmentId = (int) (($item['installment_ids'][0] ?? 0) ?: 0); $contactDirectory = $contractContacts[$contractId] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; if (!$primaryInstallmentId) continue; ?>
  <div class="modal" id="overdue-followup-<?= $primaryInstallmentId ?>">
    <div class="modal-content">
      <div class="modal-header">
        <h3><?= proma_icon('history') ?> ثبت پیگیری <?= e($item['contract_number']) ?></h3>
        <?php if ($contactDirectory): ?><button class="btn small success" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($item['customer_name']) ?>"><i data-feather="phone-call"></i><span>انتخاب شماره</span></button><?php endif; ?>
        <button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button>
      </div>
      <form method="post" action="<?= e(url('overdue/followup/' . $primaryInstallmentId)) ?>">
        <?= csrf_field() ?>
        <div class="modal-body form-grid two">
          <label>نتیجه تماس / وضعیت پیگیری
            <input name="call_result" required maxlength="190" placeholder="مثلاً وعده پرداخت، عدم پاسخ، نیازمند تماس مجدد">
          </label>
          <label>تاریخ وعده پرداخت
            <input name="promise_payment_date" placeholder="۱۴۰۵/۰۷/۳۰" value="<?= e(!empty($item['latest_promise_date']) ? jdate($item['latest_promise_date']) : '') ?>">
          </label>
          <label class="full">توضیحات
            <textarea name="notes" rows="4" placeholder="جزئیات صحبت با مشتری، شماره تماس، پیگیری بعدی یا توضیحات اپراتور"></textarea>
          </label>
          <?php if (!empty($item['latest_promise_date']) && (string) $item['latest_promise_date'] >= date('Y-m-d')): ?>
            <div class="notice warning full">برای این قرارداد تا <?= e(jdate($item['latest_promise_date'])) ?> پیگیری فعال ثبت شده است.</div>
          <?php endif; ?>
          <section class="proma-followup-history full" aria-label="آخرین پیگیری‌های قرارداد">
            <div class="proma-followup-history__heading"><strong>آخرین پیگیری‌ها</strong><small><?= to_persian_digits(count($recentFollowups)) ?> از ۵ مورد</small></div>
            <?php foreach ($recentFollowups as $recentFollowup): ?>
              <article class="proma-followup-history__item">
                <header><strong><?= e(trim((string) ($recentFollowup['call_result'] ?? '')) ?: 'پیگیری اپراتور') ?></strong><time><?= e(jdatetime($recentFollowup['created_at'] ?? '')) ?></time></header>
                <?php if (trim((string) ($recentFollowup['notes'] ?? '')) !== ''): ?><p><?= nl2br(e($recentFollowup['notes'])) ?></p><?php endif; ?>
                <small>ثبت‌کننده: <?= e($recentFollowup['operator_name'] ?? 'اپراتور نامشخص') ?><?php if (!empty($recentFollowup['promise_payment_date'])): ?> · وعده پرداخت: <?= e(jdate($recentFollowup['promise_payment_date'])) ?><?php endif; ?></small>
              </article>
            <?php endforeach; ?>
            <?php if (!$recentFollowups): ?><p class="proma-followup-history__empty">هنوز پیگیری‌ای ثبت نشده است.</p><?php endif; ?>
          </section>
        </div>
        <div class="modal-footer">
          <button class="btn" type="submit">ثبت پیگیری</button>
          <button class="btn secondary" type="button" data-close-modal>انصراف</button>
        </div>
      </form>
    </div>
  </div>
  <?php if (empty($item['legal_case_count'])): ?>
    <div class="modal" id="overdue-legal-<?= $primaryInstallmentId ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?= proma_icon('file') ?> ارجاع مستقیم به حقوقی</h3>
          <button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button>
        </div>
        <form method="post" action="<?= e(url('overdue/sendLawyer/' . $primaryInstallmentId)) ?>">
          <?= csrf_field() ?>
          <div class="modal-body form-grid two">
            <label>مرحله / دلیل ارجاع
              <input name="reason" required maxlength="190" value="ارجاع اپراتور به واحد حقوقی">
            </label>
            <label>وکیل مسئول
              <select name="lawyer_id">
                <option value="">انتخاب خودکار / بعداً تعیین شود</option>
                <?php foreach (($lawyers ?? []) as $lawyer): ?>
                  <option value="<?= (int) $lawyer['id'] ?>"><?= e($lawyer['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="full">یادداشت داخلی
              <textarea name="notes" rows="4" placeholder="علت ارجاع، خلاصه پیگیری‌های قبلی و مدارک موجود"></textarea>
            </label>
            <div class="notice warning full">این عملیات بدون ورود به تب پرونده حقوقی، برای قرارداد <?= e($item['contract_number']) ?> پرونده حقوقی داخلی ایجاد می‌کند.</div>
          </div>
          <div class="modal-footer">
            <button class="btn danger" type="submit">ارجاع به حقوقی</button>
            <button class="btn secondary" type="button" data-close-modal>انصراف</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; ?>
