<?php
require_once __DIR__ . '/components/ui.php';
$series = $series ?? ['labels' => [], 'receipts' => [], 'payments' => []];
ob_start();
?><a class="btn btn-light" href="<?= e(url('plugin/accounting/help')) ?>"><i data-feather="book-open"></i><span>راهنما</span></a><a class="btn btn-primary" href="<?= e(url('plugin/accounting/accounts')) ?>"><i data-feather="users"></i><span>حساب کاربران</span></a><?php
$headerActions = trim(ob_get_clean());
?>
<div class="proma-accounting proma-accounting-dashboard">
  <?php pa_page_header('نمای مالی یکپارچه', 'داشبورد حسابداری', 'خلاصه مانده‌ها، کمیسیون‌ها و گردش مالی کاربران در یک نمای قابل پیگیری.', 'pie-chart', $headerActions); ?>

  <div class="proma-accounting-kpis">
    <article class="proma-accounting-kpi is-positive"><span class="proma-accounting-kpi-icon is-green"><i data-feather="trending-up"></i></span><div><span class="proma-accounting-kpi-label">مجموع مانده مثبت</span><strong><?= money_toman($summary['positive_balances'] ?? 0) ?></strong><small>مطالبات ثبت‌شده کاربران از مجموعه</small></div></article>
    <article class="proma-accounting-kpi is-negative"><span class="proma-accounting-kpi-icon is-red"><i data-feather="trending-down"></i></span><div><span class="proma-accounting-kpi-label">مجموع مانده منفی</span><strong><?= money_toman(abs((int) ($summary['negative_balances'] ?? 0))) ?></strong><small>بدهی ثبت‌شده کاربران به مجموعه</small></div></article>
    <article class="proma-accounting-kpi"><span class="proma-accounting-kpi-icon is-amber"><i data-feather="clock"></i></span><div><span class="proma-accounting-kpi-label">کمیسیون در انتظار تأیید</span><strong><?= money_toman($summary['pending_commissions'] ?? 0) ?></strong><small>نیازمند بررسی مدیر مالی</small></div></article>
    <article class="proma-accounting-kpi"><span class="proma-accounting-kpi-icon is-purple"><i data-feather="percent"></i></span><div><span class="proma-accounting-kpi-label">کمیسیون قابل پرداخت</span><strong><?= money_toman($summary['payable_commissions'] ?? 0) ?></strong><small>تأییدشده یا ثبت‌شده در حساب</small></div></article>
    <article class="proma-accounting-kpi"><span class="proma-accounting-kpi-icon is-blue"><i data-feather="arrow-up-left"></i></span><div><span class="proma-accounting-kpi-label">پرداخت این ماه</span><strong><?= money_toman($summary['payments_this_month'] ?? 0) ?></strong><small>کاهش مانده بابت پرداخت به کاربران</small></div></article>
    <article class="proma-accounting-kpi"><span class="proma-accounting-kpi-icon is-green"><i data-feather="arrow-down-right"></i></span><div><span class="proma-accounting-kpi-label">دریافت این ماه</span><strong><?= money_toman($summary['receipts_this_month'] ?? 0) ?></strong><small>افزایش مانده بابت دریافت از کاربران</small></div></article>
  </div>

  <div class="proma-accounting-dashboard-grid">
    <section class="proma-accounting-chart-panel">
      <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon is-purple"><i data-feather="activity"></i></span><div><h3>روند پرداخت و دریافت</h3><p>گردش ثبت‌شده دفترکل در شش ماه اخیر</p></div></div><div class="proma-accounting-chart-legend"><span class="is-receipt"><i></i>دریافت</span><span class="is-payment"><i></i>پرداخت</span></div></div>
      <div class="proma-accounting-chart-wrap"><?php if (!array_sum($series['receipts'] ?? []) && !array_sum($series['payments'] ?? [])): ?><?= pa_empty_state('داده‌ای برای نمودار نیست', 'پس از ثبت اولین پرداخت یا دریافت، روند مالی در این بخش نمایش داده می‌شود.', 'bar-chart-2') ?><?php else: ?><canvas data-accounting-chart data-labels="<?= e(json_encode($series['labels'], JSON_UNESCAPED_UNICODE)) ?>" data-receipts="<?= e(json_encode($series['receipts'])) ?>" data-payments="<?= e(json_encode($series['payments'])) ?>" aria-label="نمودار پرداخت و دریافت شش ماه اخیر" role="img"></canvas><?php endif; ?></div>
    </section>
    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="zap"></i></span><div><h3>عملیات سریع</h3><p>مسیر کوتاه برای کارهای پرتکرار</p></div></div></div>
      <div class="proma-accounting-quick-actions">
        <a class="proma-accounting-quick-action" href="<?= e(url('plugin/accounting/accounts', ['action' => 'payment'])) ?>"><span><i data-feather="send"></i></span><span><strong>ثبت پرداخت</strong><small>پرداخت وجه به کاربر</small></span></a>
        <a class="proma-accounting-quick-action" href="<?= e(url('plugin/accounting/accounts', ['action' => 'receipt'])) ?>"><span><i data-feather="download"></i></span><span><strong>ثبت دریافت</strong><small>دریافت وجه از کاربر</small></span></a>
        <a class="proma-accounting-quick-action" href="<?= e(url('plugin/accounting/accounts', ['action' => 'expense'])) ?>"><span><i data-feather="file-minus"></i></span><span><strong>ثبت هزینه</strong><small>هزینه قابل پرداخت</small></span></a>
        <a class="proma-accounting-quick-action" href="<?= e(url('plugin/accounting/rules')) ?>"><span><i data-feather="sliders"></i></span><span><strong>قانون کمیسیون</strong><small>تعریف قانون فروشنده</small></span></a>
      </div>
    </section>
  </div>

  <div class="proma-accounting-insight-grid">
    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="trending-up"></i></span><div><h3>فروشندگان برتر</h3><p>بیشترین تأمین مالی ثبت‌شده در قراردادها</p></div></div><a class="btn btn-light btn-sm" href="<?= e(url('plugin/accounting/sales')) ?>">همه فروش‌ها</a></div>
      <?php if (!empty($topSellers)): ?><div class="proma-accounting-insight-list"><?php foreach ($topSellers as $seller): ?><a class="proma-accounting-insight-row" href="<?= e(url('plugin/accounting/ledger/' . (int) $seller['id'])) ?>"><?= pa_user_cell($seller, role_label($seller['role'] ?? '')) ?><span><strong><?= money_toman($seller['financed_total'] ?? 0) ?></strong><small><?= to_persian_digits((int) ($seller['sales_count'] ?? 0)) ?> فروش</small></span></a><?php endforeach; ?></div><?php else: ?><?= pa_empty_state('هنوز فروشنده برتری ثبت نشده است', 'پس از تخصیص فروشنده به قراردادها، رتبه‌بندی اینجا نمایش داده می‌شود.', 'users') ?><?php endif; ?>
    </section>
    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="alert-triangle"></i></span><div><h3>هشدارهای حسابداری</h3><p>مواردی که به بررسی یا اقدام نیاز دارند</p></div></div></div>
      <div class="proma-accounting-alert-list">
        <?php if ((int) ($summary['pending_commissions'] ?? 0) > 0): ?><a class="proma-accounting-alert-item is-warning" href="<?= e(url('plugin/accounting/commissions', ['status' => 'pending'])) ?>"><span><i data-feather="clock"></i></span><span><strong>کمیسیون در انتظار تأیید</strong><small><?= money_toman($summary['pending_commissions']) ?> نیازمند بررسی است.</small></span></a><?php endif; ?>
        <?php if ((int) ($summary['negative_balances'] ?? 0) < 0): ?><a class="proma-accounting-alert-item is-danger" href="<?= e(url('plugin/accounting/accounts', ['balance' => 'negative'])) ?>"><span><i data-feather="trending-down"></i></span><span><strong>حساب‌های دارای مانده منفی</strong><small>جمع مانده منفی <?= money_toman(abs((int) $summary['negative_balances'])) ?> است.</small></span></a><?php endif; ?>
        <?php if ((int) ($summary['pending_commissions'] ?? 0) <= 0 && (int) ($summary['negative_balances'] ?? 0) >= 0): ?><div class="proma-accounting-alert-item is-success"><span><i data-feather="check-circle"></i></span><span><strong>مورد فوری وجود ندارد</strong><small>وضعیت حساب‌ها و کمیسیون‌های منتظر عادی است.</small></span></div><?php endif; ?>
      </div>
    </section>
  </div>

  <section class="proma-accounting-panel">
    <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="clock"></i></span><div><h3>آخرین تراکنش‌ها</h3><p>جدیدترین اثرهای ثبت‌شده روی مانده کاربران</p></div></div><a class="btn btn-light btn-sm" href="<?= e(url('plugin/accounting/accounts')) ?>">مشاهده حساب‌ها</a></div>
    <?php if (!empty($recentEntries)): ?>
      <div class="proma-accounting-table-shell is-card-mobile"><table class="proma-accounting-table"><thead><tr><th>کاربر</th><th>عملیات</th><th>اثر مالی</th><th>مانده بعد</th><th>تاریخ</th></tr></thead><tbody><?php foreach ($recentEntries as $entry): ?><tr><td><?= pa_user_cell($entry, role_label($entry['role'] ?? '')) ?></td><td data-label="عملیات"><?= e(pa_entry_type_label($entry['entry_type'] ?? '')) ?></td><td data-label="اثر مالی"><?= pa_money_effect($entry['amount'] ?? 0, $entry['direction'] ?? 'increase') ?></td><td data-label="مانده بعد"><?= pa_balance($entry['balance_after'] ?? 0) ?></td><td data-label="تاریخ"><?= e(jdatetime($entry['created_at'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div>
      <div class="proma-accounting-mobile-list"><?php foreach ($recentEntries as $entry): ?><article class="proma-accounting-data-card"><div class="proma-accounting-data-card-header"><?= pa_user_cell($entry, role_label($entry['role'] ?? '')) ?><?= pa_money_effect($entry['amount'] ?? 0, $entry['direction'] ?? 'increase', false) ?></div><div class="proma-accounting-data-card-grid"><div><span>عملیات</span><strong><?= e(pa_entry_type_label($entry['entry_type'] ?? '')) ?></strong></div><div><span>مانده بعد</span><strong><?= money_toman($entry['balance_after'] ?? 0) ?></strong></div><div><span>تاریخ</span><strong><?= e(jdatetime($entry['created_at'] ?? '')) ?></strong></div></div></article><?php endforeach; ?></div>
    <?php else: ?><?= pa_empty_state('هنوز تراکنشی ثبت نشده است', 'پس از ثبت اولین سند، تراکنش‌های اخیر اینجا دیده می‌شوند.', 'book-open', 'مشاهده حساب کاربران', url('plugin/accounting/accounts')) ?><?php endif; ?>
  </section>
</div>
