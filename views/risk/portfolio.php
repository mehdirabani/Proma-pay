<?php
$report = $report ?? [];
$filters = $filters ?? ($report['filters'] ?? []);
$summary = $report['summary'] ?? [];
$trend = $report['trend'] ?? [];
$aging = $report['aging'] ?? [];
$forecast = $report['forecast'] ?? [];
$riskSummary = $report['risk_summary'] ?? [];
$riskContracts = $report['risk_contracts'] ?? [];
$coverage = $report['coverage'] ?? [];
$riskLabels = ['high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم', 'unavailable' => 'ناموجود / کهنه'];
$detailUrl = static function (string $metric, array $extra = []) use ($filters): string {
    return url('risk/portfolioDetail', array_merge($filters, ['metric' => $metric], $extra));
};
$riskPageUrl = static function (int $page) use ($filters): string {
    return url('risk/portfolio', array_merge($filters, ['page' => $page])) . '#risk-contracts';
};
$trendPayload = $trend;
$allFilterParams = [
    'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '', 'group' => $filters['group'] ?? 'month',
    'horizon' => $filters['horizon'] ?? 30, 'risk' => $filters['risk'] ?? 'all', 'aging' => $filters['aging'] ?? 'all',
    'status' => $filters['status'] ?? 'all', 'q' => $filters['q'] ?? '', 'operator_id' => $filters['operator_id'] ?? '',
    'contract_id' => $filters['contract_id'] ?? '', 'customer_id' => $filters['customer_id'] ?? '',
];
?>
<section class="portfolio-analytics" aria-labelledby="portfolio-title">
  <header class="portfolio-hero">
    <div>
      <span class="proma-eyebrow">گزارش‌های مالی فقط‌خواندنی · نسخه <?= e($report['metric_version'] ?? '') ?></span>
      <h2 id="portfolio-title">تحلیل ریسک پرتفوی و جریان نقدی</h2>
      <p>تعهدات سررسیدشده، وجوه واقعی، معوقات و ورودی برنامه‌ریزی‌شده از وضعیت مالی اقساط محاسبه می‌شوند.</p>
    </div>
    <div class="portfolio-asof"><span>تاریخ مبنا</span><strong><?= e(jdate($report['as_of'] ?? date('Y-m-d'))) ?></strong><small>محاسبه <?= e(jdatetime($report['computed_at'] ?? date('Y-m-d H:i:s'))) ?></small></div>
  </header>

  <form class="card portfolio-filters" method="get" action="<?= e(url('risk/portfolio')) ?>" aria-label="فیلتر گزارش پرتفوی">
    <input type="hidden" name="route" value="risk/portfolio">
    <label>از تاریخ<input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>" required></label>
    <label>تا تاریخ<input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>" max="<?= e($report['as_of'] ?? date('Y-m-d')) ?>" required></label>
    <label>گروه‌بندی<select name="group"><option value="day"<?= selected($filters['group'] ?? 'month', 'day') ?>>روزانه</option><option value="week"<?= selected($filters['group'] ?? 'month', 'week') ?>>هفتگی</option><option value="month"<?= selected($filters['group'] ?? 'month', 'month') ?>>ماهانه</option></select></label>
    <label>افق ورودی برنامه‌ریزی‌شده<select name="horizon"><?php foreach ([7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز', 180 => '۶ ماه', 365 => '۱۲ ماه'] as $days => $label): ?><option value="<?= $days ?>"<?= selected($filters['horizon'] ?? 30, $days) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>سطح ریسک<select name="risk"><?php foreach (['all' => 'همه', 'high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم', 'unavailable' => 'ناموجود / کهنه'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['risk'] ?? 'all', $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>وضعیت قرارداد<select name="status"><?php foreach (['all' => 'همهٔ غیرلغوشده‌ها', 'active' => 'فعال', 'referred' => 'ارجاع‌شده', 'processing' => 'در حال پردازش', 'pending' => 'در انتظار', 'completed' => 'تکمیل‌شده', 'closed' => 'بسته'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['status'] ?? 'all', $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label class="portfolio-filter-wide">جستجوی مشتری یا قرارداد<input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" maxlength="100" placeholder="شماره قرارداد، نام یا موبایل"></label>
    <label>شناسهٔ اپراتور<input type="number" name="operator_id" min="1" value="<?= e($filters['operator_id'] ?? '') ?>" inputmode="numeric"></label>
    <label>شناسهٔ قرارداد<input type="number" name="contract_id" min="1" value="<?= e($filters['contract_id'] ?? '') ?>" inputmode="numeric"></label>
    <label>شناسهٔ مشتری<input type="number" name="customer_id" min="1" value="<?= e($filters['customer_id'] ?? '') ?>" inputmode="numeric"></label>
    <div class="portfolio-filter-actions"><button class="btn" type="submit"><?= proma_icon('filter') ?><span>اعمال فیلتر</span></button><a class="btn secondary" href="<?= e(url('risk/portfolio')) ?>">پاک‌کردن</a></div>
  </form>

  <?php if (!empty($summary['calculation_review_rows'])): ?>
    <div class="portfolio-notice is-warning" role="status"><strong><?= to_persian_digits($summary['calculation_review_rows']) ?> قسط</strong> به‌دلیل وضعیت مالی نیازمند بررسی از مانده‌های محاسبه‌شده و نرخ Cohort حذف شده است. اقساط برنامه‌ای جداگانه با مبلغ قرارداد فعلی نمایش داده می‌شوند.</div>
  <?php endif; ?>

  <div class="portfolio-kpi-grid" aria-label="شاخص‌های پرتفوی">
    <a class="portfolio-kpi is-blue" href="<?= e($detailUrl('due')) ?>"><span>تعهدات سررسیدشده در بازه</span><strong><?= money_toman($summary['scheduled_period_amount'] ?? 0) ?></strong><small><?= to_persian_digits($summary['scheduled_period_count'] ?? 0) ?> قسط · برنامهٔ فعلی</small></a>
    <a class="portfolio-kpi is-green" href="<?= e($detailUrl('cash')) ?>"><span>دریافت واقعی اقساط در بازه</span><strong><?= money_toman($summary['period_cash_collection'] ?? 0) ?></strong><small>پرداخت‌های paid، اصلاح‌نشده و از نوع قسط</small></a>
    <a class="portfolio-kpi is-violet" href="<?= e($detailUrl('cohort')) ?>"><span>نرخ وصول Cohort سررسید</span><strong><?= isset($summary['due_cohort_rate']) ? to_persian_digits($summary['due_cohort_rate']) . '٪' : '—' ?></strong><small><?= money_toman($summary['due_cohort_collected'] ?? 0) ?> از <?= money_toman($summary['due_cohort_obligation'] ?? 0) ?> اصل تعهد واجدشرایط</small></a>
    <a class="portfolio-kpi is-red" href="<?= e($detailUrl('aging')) ?>"><span>اصل مطالبات معوق فعلی</span><strong><?= money_toman($summary['overdue_principal'] ?? 0) ?></strong><small><?= to_persian_digits($summary['overdue_installments'] ?? 0) ?> قسط در <?= to_persian_digits($summary['overdue_contracts'] ?? 0) ?> قرارداد</small></a>
    <a class="portfolio-kpi is-amber" href="<?= e($detailUrl('aging')) ?>"><span>سهم اصل معوق از اصل مانده</span><strong><?= isset($summary['overdue_share']) ? to_persian_digits($summary['overdue_share']) . '٪' : '—' ?></strong><small>جریمه و هزینهٔ حقوقی در نسبت وارد نشده‌اند</small></a>
    <a class="portfolio-kpi is-teal" href="<?= e($detailUrl('forecast', ['horizon' => 30])) ?>"><span>ورودی برنامه‌ریزی‌شدهٔ ۳۰ روز آینده</span><strong><?= money_toman($summary['scheduled_inflow_30_days'] ?? 0) ?></strong><small>اصل باقی‌مانده طبق برنامهٔ اقساط</small></a>
    <a class="portfolio-kpi is-blue" href="<?= e($detailUrl('balance')) ?>"><span>اصل ماندهٔ کل پرتفوی</span><strong><?= money_toman($summary['outstanding_principal'] ?? 0) ?></strong><small><?= to_persian_digits($summary['active_balance_contracts'] ?? 0) ?> قرارداد دارای اصل مانده</small></a>
  </div>

  <section class="card portfolio-panel" aria-labelledby="portfolio-trend-title">
    <div class="portfolio-panel-head"><div><h3 id="portfolio-trend-title">تعهد سررسیدشده و دریافت واقعی</h3><p>هم‌زمانی در یک دوره به معنی انتساب پرداخت به اقساط همان Cohort نیست.</p></div><span class="portfolio-legend"><i class="is-due"></i> مبلغ برنامه‌ای سررسیدشده <i class="is-cash"></i> دریافت واقعی اقساط</span></div>
    <div class="portfolio-chart-wrap"><canvas data-portfolio-chart="trend" role="img" aria-label="نمودار تعهدات سررسیدشده و دریافت واقعی اقساط"></canvas></div>
    <div class="table-responsive"><table class="portfolio-data-table"><caption>داده‌های قابل‌دسترسی نمودار روند</caption><thead><tr><th scope="col">دوره</th><th scope="col">تعهد سررسیدشده</th><th scope="col">دریافت واقعی</th><th scope="col">اقساط Cohort وصول‌شده</th><th scope="col">نرخ Cohort</th></tr></thead><tbody>
      <?php foreach ($trend as $point): $periodParams = $allFilterParams; $periodParams['from'] = max((string) ($filters['from'] ?? $point['period_key']), (string) $point['period_key']); $periodParams['to'] = min((string) ($filters['to'] ?? $point['period_end'] ?? $point['period_key']), (string) ($point['period_end'] ?? $point['period_key'])); ?>
        <tr><th scope="row"><?= e($point['label']) ?></th><td><a href="<?= e(url('risk/portfolioDetail', array_merge($periodParams, ['metric' => 'due']))) ?>"><?= money_toman($point['due_amount']) ?></a></td><td><a href="<?= e(url('risk/portfolioDetail', array_merge($periodParams, ['metric' => 'cash']))) ?>"><?= money_toman($point['cash_amount']) ?></a></td><td><?= money_toman($point['cohort_collected']) ?> / <?= money_toman($point['cohort_obligation']) ?></td><td><?= isset($point['cohort_rate']) ? to_persian_digits($point['cohort_rate']) . '٪' : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$trend): ?><tr><td colspan="5">در این بازه داده‌ای وجود ندارد.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  <div class="portfolio-two-col">
    <section class="card portfolio-panel" aria-labelledby="portfolio-aging-title">
      <div class="portfolio-panel-head"><div><h3 id="portfolio-aging-title">سن مطالبات معوق فعلی</h3><p>اصل و جریمهٔ جاری هر قسط فقط در بازهٔ تأخیر همان قسط است.</p></div><a class="portfolio-text-link" href="<?= e($detailUrl('aging')) ?>">جزئیات منابع</a></div>
      <div class="portfolio-chart-wrap is-compact"><canvas data-portfolio-chart="aging" role="img" aria-label="نمودار سن مطالبات معوق"></canvas></div>
      <div class="table-responsive"><table class="portfolio-data-table"><caption>مبالغ و شمارش سن مطالبات</caption><thead><tr><th scope="col">بازهٔ تأخیر</th><th scope="col">قسط</th><th scope="col">قرارداد یکتا</th><th scope="col">اصل مانده</th><th scope="col">جریمه</th><th scope="col">مجموع</th></tr></thead><tbody>
        <?php foreach ($aging as $bucket): ?><tr><th scope="row"><a href="<?= e($detailUrl('aging', ['aging' => $bucket['key']])) ?>"><?= e($bucket['label']) ?></a></th><td><?= to_persian_digits($bucket['installments']) ?></td><td><?= to_persian_digits($bucket['contracts']) ?></td><td><?= money_toman($bucket['principal']) ?></td><td><?= money_toman($bucket['penalties']) ?></td><td><?= money_toman($bucket['total']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <p class="portfolio-footnote">هزینهٔ حقوقی در سطح قرارداد است و به بازهٔ سنی قسط تخصیص داده نشده؛ بنابراین در جدول سنی جمع نشده است.</p>
    </section>

    <section class="card portfolio-panel" aria-labelledby="portfolio-forecast-title">
      <div class="portfolio-panel-head"><div><h3 id="portfolio-forecast-title">ورودی نقدی برنامه‌ریزی‌شده</h3><p>اصل باقی‌ماندهٔ اقساط معتبر؛ دریافت واقعی تخمین زده نمی‌شود.</p></div><a class="portfolio-text-link" href="<?= e($detailUrl('forecast')) ?>">اقساط سازنده مبلغ</a></div>
      <div class="portfolio-chart-wrap is-compact"><canvas data-portfolio-chart="forecast" role="img" aria-label="نمودار ورودی نقدی برنامه‌ریزی‌شده طبق اقساط"></canvas></div>
      <div class="table-responsive"><table class="portfolio-data-table"><caption>بازه‌های پیش‌بینی قراردادی</caption><thead><tr><th scope="col">دوره</th><th scope="col">قسط</th><th scope="col">قرارداد یکتا</th><th scope="col">اصل برنامه‌ریزی‌شده</th><th scope="col">جزئیات</th></tr></thead><tbody>
        <?php foreach ($forecast as $bucket): ?><tr><th scope="row"><?= e($bucket['label']) ?></th><td><?= to_persian_digits($bucket['installments']) ?></td><td><?= to_persian_digits($bucket['contracts']) ?></td><td><?= money_toman($bucket['amount']) ?></td><td><a href="<?= e($detailUrl('forecast', ['period' => $bucket['period_key']])) ?>">مشاهده اقساط</a></td></tr><?php endforeach; ?>
        <?php if (!$forecast): ?><tr><td colspan="5">در این افق، قسط آیندهٔ واجدشرایطی وجود ندارد.</td></tr><?php endif; ?>
      </tbody></table></div>
      <p class="portfolio-footnote">مطالبات معوق قبلی به ماه‌های آینده منتقل نشده‌اند و در این پیش‌بینی نیستند.</p>
    </section>
  </div>

  <section class="card portfolio-panel" id="risk-contracts" aria-labelledby="portfolio-risk-title">
    <div class="portfolio-panel-head"><div><h3 id="portfolio-risk-title">قراردادهای نیازمند توجه</h3><p>ترتیب «اولویت پرتفوی» بر مبنای اصل معوق و بیشترین روز تأخیر است؛ این رتبه Proma Score یا احتمال نکول نیست.</p></div><span class="portfolio-stale-note">Proma Score فقط از Snapshot تازه و موجود خوانده می‌شود.</span></div>
    <div class="portfolio-risk-summary" aria-label="توزیع سطح ریسک قراردادهای دارای مانده">
      <?php foreach ($riskLabels as $level => $label): $levelSummary = $riskSummary[$level] ?? ['contracts' => 0, 'overdue_principal' => 0]; ?>
        <a class="portfolio-risk-chip is-<?= e($level) ?>" href="<?= e(url('risk/portfolio', array_merge($filters, ['risk' => $level, 'page' => 1])) . '#risk-contracts') ?>"><span><?= e($label) ?></span><strong><?= to_persian_digits($levelSummary['contracts']) ?> قرارداد</strong><small><?= money_toman($levelSummary['overdue_principal']) ?> اصل معوق</small></a>
      <?php endforeach; ?>
    </div>
    <div class="table-responsive"><table class="portfolio-data-table portfolio-risk-table"><caption>قراردادها، ماندهٔ محاسبه‌شده و وضعیت دادهٔ ریسک</caption><thead><tr><th scope="col">قرارداد / مشتری</th><th scope="col">سطح ریسک</th><th scope="col">امتیاز</th><th scope="col">اصل مانده</th><th scope="col">اصل معوق</th><th scope="col">بیشترین تأخیر</th><th scope="col">قسط معوق</th><th scope="col">پروندهٔ حقوقی فعال</th><th scope="col">تاریخ دادهٔ ریسک</th></tr></thead><tbody>
      <?php foreach ($riskContracts as $row): ?>
        <tr>
          <th scope="row"><a href="<?= e(url('contracts/show/' . (int) $row['contract_id'])) ?>"><?= e($row['contract_number']) ?></a><small><?= e($row['customer_name']) ?> · <a href="<?= e(url('risk/profile/' . (int) $row['customer_id'])) ?>">پروفایل</a> · <a href="<?= e(url('overdue', ['contract_id' => (int) $row['contract_id']])) ?>">اقساط معوق</a></small></th>
          <td><span class="portfolio-risk-badge is-<?= e($row['risk_level']) ?>"><?= e($riskLabels[$row['risk_level']] ?? $riskLabels['unavailable']) ?></span></td>
          <td><?= $row['credit_score'] === null ? '—' : to_persian_digits($row['credit_score']) ?></td>
          <td><?= money_toman($row['outstanding_principal']) ?></td><td><?= money_toman($row['overdue_principal']) ?></td>
          <td><?= to_persian_digits($row['maximum_delay_days']) ?> روز</td><td><?= to_persian_digits($row['overdue_installments']) ?></td>
          <td><?= !empty($row['active_legal_case']) ? e(status_label($row['legal_case_status'])) : 'ندارد' ?></td>
          <td><?= !empty($row['risk_updated_at']) ? e(jdatetime($row['risk_updated_at'])) : 'ناموجود / کهنه' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$riskContracts): ?><tr><td colspan="9"><div class="empty">قرارداد معوقی با فیلترهای فعلی پیدا نشد.</div></td></tr><?php endif; ?>
    </tbody></table></div>
    <?php $riskPagination = $report['risk_pagination'] ?? ['total' => count($riskContracts), 'page' => 1, 'pages' => 1, 'per_page' => 25]; ?>
    <?php if (($riskPagination['pages'] ?? 1) > 1): ?><nav class="portfolio-pagination" aria-label="صفحه‌بندی قراردادهای ریسک"><span>نمایش <?= to_persian_digits(($riskPagination['page'] - 1) * $riskPagination['per_page'] + 1) ?> تا <?= to_persian_digits(min($riskPagination['total'], $riskPagination['page'] * $riskPagination['per_page'])) ?> از <?= to_persian_digits($riskPagination['total']) ?> قرارداد</span><div><?php if ($riskPagination['page'] > 1): ?><a class="btn small secondary" href="<?= e($riskPageUrl($riskPagination['page'] - 1)) ?>">صفحه قبل</a><?php endif; ?><?php if ($riskPagination['page'] < $riskPagination['pages']): ?><a class="btn small secondary" href="<?= e($riskPageUrl($riskPagination['page'] + 1)) ?>">صفحه بعد</a><?php endif; ?></div></nav><?php endif; ?>
  </section>

  <details class="portfolio-coverage">
    <summary>تعریف شاخص‌ها و پوشش داده</summary>
    <div class="portfolio-coverage-grid">
      <p><strong>دریافت واقعی دوره:</strong> جمع <code>payments.amount</code> با وضعیت paid، پرچم اصلاح‌نشده و نوع installment بر مبنای تاریخ پرداخت؛ پرداخت پیش‌پرداخت و هزینهٔ حقوقی جدا هستند.</p>
      <p><strong>نرخ Cohort:</strong> اصل تخصیص‌یافتهٔ معتبر به اقساط سررسیدشدهٔ همان دوره تا تاریخ مبنا تقسیم بر اصل برنامهٔ فعلی همان اقساط. پرداخت دوره‌های دیگر به Cohort اشتباه منتسب نمی‌شود.</p>
      <p><strong>اصل مانده:</strong> جمع ماندهٔ اصل خروجی <code>InstallmentFinancialStateService</code>؛ جمع خام <code>base_amount - paid_amount</code> مبنا نیست. پیش‌پرداخت خارج از ماندهٔ اقساط است و جریمه جداگانه گزارش می‌شود.</p>
      <p><strong>پیش‌بینی:</strong> اصل باقی‌ماندهٔ اقساط غیرلغوشدهٔ قراردادهای باز با سررسید آینده. این عدد تعهد قراردادی است، نه برآورد وصول واقعی یا جریان نقدی خالص کسب‌وکار.</p>
      <p><strong>پوشش تاریخچه:</strong> <?= e($coverage['note'] ?? '') ?></p>
      <p><strong>دادهٔ ریسک:</strong> فقط Snapshot منطبق با قواعد فعلی و تازه‌تر از ۶ ساعت رتبه‌بندی می‌شود؛ دادهٔ غایب، قدیمی یا با قواعد متفاوت «ناموجود / کهنه» است.</p>
    </div>
  </details>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  const trend = <?= json_encode($trendPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const aging = <?= json_encode($aging, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const forecast = <?= json_encode($forecast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const detailBase = <?= json_encode(url('risk/portfolioDetail'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const baseParams = <?= json_encode($allFilterParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const money = value => new Intl.NumberFormat('fa-IR').format(value) + ' تومان';
  const makeDetailUrl = (metric, row, datasetIndex) => {
    const params = new URLSearchParams();
    Object.entries(baseParams).forEach(([key, value]) => { if (value !== '' && value !== null) params.set(key, String(value)); });
    params.set('metric', metric);
    if (row) {
      if (metric === 'due' || metric === 'cash') {
        const bucketStart = String(row.period_key);
        const bucketEnd = String(row.period_end || row.period_key);
        const filterStart = String(baseParams.from || bucketStart);
        const filterEnd = String(baseParams.to || bucketEnd);
        params.set('from', bucketStart < filterStart ? filterStart : bucketStart);
        params.set('to', bucketEnd > filterEnd ? filterEnd : bucketEnd);
      }
      if (metric === 'forecast') params.set('period', row.period_key);
      if (metric === 'aging') params.set('aging', row.key);
    }
    return detailBase + '?' + params.toString();
  };
  const commonOptions = { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { tooltip: { callbacks: { label: context => context.dataset.label + ': ' + money(context.raw || 0) } }, legend: { position: 'bottom', rtl: true } }, scales: { y: { beginAtZero: true, ticks: { callback: value => new Intl.NumberFormat('fa-IR', { notation: 'compact' }).format(value) } } } };
  document.querySelectorAll('[data-portfolio-chart="trend"]').forEach(canvas => new Chart(canvas, {
    type: 'line', data: { labels: trend.map(row => row.label), datasets: [
      { label: 'تعهد سررسیدشده', data: trend.map(row => row.due_amount), borderColor: '#4868d8', backgroundColor: 'rgba(72,104,216,.12)', tension: .25, fill: false },
      { label: 'دریافت واقعی اقساط', data: trend.map(row => row.cash_amount), borderColor: '#168b64', backgroundColor: 'rgba(22,139,100,.12)', tension: .25, fill: false }
    ] }, options: { ...commonOptions, onClick: (event, elements) => { if (!elements.length) return; const index = elements[0].index; location.href = makeDetailUrl(elements[0].datasetIndex === 0 ? 'due' : 'cash', trend[index]); } }
  }));
  document.querySelectorAll('[data-portfolio-chart="aging"]').forEach(canvas => new Chart(canvas, {
    type: 'bar', data: { labels: aging.map(row => row.label), datasets: [
      { label: 'اصل معوق', data: aging.map(row => row.principal), backgroundColor: '#dc4b56', stack: 'aging' },
      { label: 'جریمهٔ جاری', data: aging.map(row => row.penalties), backgroundColor: '#eea333', stack: 'aging' }
    ] }, options: { ...commonOptions, onClick: (event, elements) => { if (!elements.length) return; location.href = makeDetailUrl('aging', aging[elements[0].index]); } }
  }));
  document.querySelectorAll('[data-portfolio-chart="forecast"]').forEach(canvas => new Chart(canvas, {
    type: 'bar', data: { labels: forecast.map(row => row.label), datasets: [
      { label: 'اصل برنامه‌ریزی‌شده', data: forecast.map(row => row.amount), backgroundColor: '#168b91' }
    ] }, options: { ...commonOptions, onClick: (event, elements) => { if (!elements.length) return; location.href = makeDetailUrl('forecast', forecast[elements[0].index]); } }
  }));
});
</script>
