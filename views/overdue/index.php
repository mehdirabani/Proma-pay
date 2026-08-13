<?php
/** @var OverdueFilterCriteria $criteria */
$criteria = $criteria ?? OverdueFilterCriteria::fromRequest($_GET);
$overdueContracts = $overdueContracts ?? [];
$contractContacts = $contractContacts ?? [];
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
    <a class="btn secondary" href="<?= e(url('contracts')) ?>"><?= proma_icon('file') ?><span>قراردادها</span></a>
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
              <thead><tr><th>مشتری و قرارداد</th><th>شاخص تاخیر</th><th>مبلغ قابل پیگیری</th><th>حقوقی و آخرین پیگیری</th><th>اقدام</th></tr></thead>
              <tbody><?php foreach ($overdueContracts as $item): ?><?php [$legalLabel, $legalClass] = $statusLabel($item); $installmentUrl = url('installments', ['tab' => 'overdue', 'contract_number' => $item['contract_number']]); $phone = normalize_iran_phone($item['mobile'] ?? ''); $contactDirectory = $contractContacts[(int) ($item['contract_id'] ?? 0)] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>
                <tr>
                  <td><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?> · <?= to_persian_digits(format_iran_phone($phone)) ?></small></td>
                  <td><div class="proma-metric-pair"><strong><?= to_persian_digits($item['overdue_count']) ?> قسط</strong><span><?= to_persian_digits($item['max_overdue_days']) ?> روز بیشترین تاخیر</span><small>قدیمی‌ترین: <?= e(jdate($item['oldest_due_date'])) ?></small></div></td>
                  <td><div class="proma-metric-pair"><strong><?= money_toman($item['total_overdue_amount'] ?? $item['estimated_payable']) ?></strong><span>جریمه <?= money_toman($item['total_penalty'] ?? $item['estimated_penalty']) ?></span></div></td>
                  <td><span class="badge <?= e($legalClass) ?>"><?= e($legalLabel) ?></span><small><?= !empty($item['last_contact_at']) ? 'آخرین پیگیری: ' . e(jdatetime($item['last_contact_at'])) : 'پیگیری ثبت نشده' ?></small></td>
                  <td><div class="proma-row-actions"><a class="btn small" href="<?= e(url('contracts/show/' . (int) $item['contract_id'])) ?>">مشاهده و پیگیری</a><button class="icon-btn" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" aria-label="تماس با مشتری و ضامن‌های <?= e($item['customer_name']) ?>" title="تماس با مشتری و ضامن‌ها"><?= proma_icon('phone') ?></button><a class="icon-btn" href="<?= e(url('chat', ['contact' => $item['customer_id']])) ?>" aria-label="گفت‌وگو با <?= e($item['customer_name']) ?>" title="گفت‌وگو"><?= proma_icon('history') ?></a><details class="proma-action-menu"><summary aria-label="عملیات بیشتر <?= e($item['contract_number']) ?>"><?= proma_icon('more') ?></summary><div><a href="<?= e($installmentUrl) ?>">اقساط معوق</a><a href="<?= e(url('contracts/show/' . (int) $item['contract_id'])) ?>">ثبت پرداخت</a><a href="<?= e(url('contracts/booklet/' . (int) $item['contract_id'])) ?>" target="_blank" rel="noopener">دفترچه اقساط</a></div></details></div></td>
                </tr>
              <?php endforeach; ?></tbody>
            </table>
          </div>
          <div class="proma-overdue-mobile-list">
            <?php foreach ($overdueContracts as $item): ?><?php [$legalLabel, $legalClass] = $statusLabel($item); $contactDirectory = $contractContacts[(int) ($item['contract_id'] ?? 0)] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>
              <article class="proma-overdue-mobile-card"><header><div><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?></small></div><span class="badge <?= e($legalClass) ?>"><?= e($legalLabel) ?></span></header><div class="proma-overdue-mobile-card__metrics"><span><small>اقساط معوق</small><strong><?= to_persian_digits($item['overdue_count']) ?></strong></span><span><small>بیشترین تاخیر</small><strong><?= to_persian_digits($item['max_overdue_days']) ?> روز</strong></span><span><small>قابل پیگیری</small><strong><?= money_toman($item['total_overdue_amount'] ?? $item['estimated_payable']) ?></strong></span></div><footer><a class="btn small" href="<?= e(url('contracts/show/' . (int) $item['contract_id'])) ?>">مشاهده و پیگیری</a><button class="btn small secondary" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>">تماس</button><a class="btn small secondary" href="<?= e(url('chat', ['contact' => $item['customer_id']])) ?>">گفت‌وگو</a></footer></article>
            <?php endforeach; ?>
          </div>
          <?= render_pagination($pagination, $pageUrl) ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>
