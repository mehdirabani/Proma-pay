<?php
$report = $report ?? [];
$filters = $filters ?? [];
$fmtRate = static function ($rate): string { return $rate === null ? 'داده کافی نیست' : to_persian_digits(number_format((float) $rate * 100, 1)) . '٪'; };
$fmtMoney = static fn ($amount): string => money_toman((int) ($amount ?? 0));
$followups = $report['followups'] ?? [];
$ownership = $report['ownership'] ?? [];
$tasks = $report['tasks'] ?? [];
$collection = $report['collection'] ?? [];
$promises = $report['promises'] ?? [];
$evidence = $report['evidence'] ?? ['type' => 'collection', 'items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$evidenceStatusLabels = ['pending' => 'پنجره در انتظار تکمیل', 'needs_review' => 'نیازمند بررسی تاریخ', 'collected' => 'دارای وصول', 'no_collection' => 'بدون وصول واجدشرایط', 'fulfilled' => 'تحقق کامل', 'partially_fulfilled' => 'تحقق جزئی', 'broken' => 'تحقق‌نیافته', 'amountless_excluded' => 'بدون مبلغ؛ خارج از مخرج'];
$paymentEvidenceLabel = static function (array $payments): string {
  return implode('، ', array_map(static fn (array $payment): string => '#' . (int) ($payment['payment_id'] ?? 0) . ' · ' . money_toman((int) ($payment['allocated_toman'] ?? 0)) . ' · ' . (string) ($payment['paid_at'] ?: ($payment['payment_date'] ?? '')), $payments));
};
?>
<div class="proma-collections-workspace">
  <header class="proma-page-intro"><div><span class="proma-eyebrow">نظارت مدیریتی</span><h1>گزارش عملکرد وصول</h1><p>این گزارش هم‌زمانی پیگیری و پرداخت را اندازه می‌گیرد؛ رابطهٔ علّی را اثبات نمی‌کند.</p></div><div class="proma-contracts-intro__actions"><a class="btn secondary" href="<?= e(url('collections')) ?>">بازگشت به میزکار</a></div></header>
  <section class="card"><div class="card-body">
    <form method="get" action="<?= e(url('collections/performance')) ?>" class="form-grid two">
      <label>از تاریخ<input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>" required></label>
      <label>تا تاریخ<input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label>اپراتور<select name="operator_id"><option value="0">همهٔ اپراتورها</option><?php foreach (($operators ?? []) as $operator): ?><option value="<?= (int) $operator['id'] ?>" <?= (int) ($filters['operator_id'] ?? 0) === (int) $operator['id'] ? 'selected' : '' ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label>
      <label>پنجرهٔ وصول پس از پیگیری (روز)<input type="number" min="1" max="30" name="window_days" value="<?= (int) ($filters['window_days'] ?? 7) ?>"></label>
      <button class="btn" type="submit">محاسبهٔ گزارش</button>
    </form>
  </div></section>
  <section class="card"><div class="card-body">
    <div class="notice <?= !empty($report['meta']['complete']) ? 'success' : 'warning' ?>">
      بازهٔ <?= e(jdate($report['meta']['from'] ?? '')) ?> تا <?= e(jdate($report['meta']['to'] ?? '')) ?> · پنجرهٔ وصول <?= to_persian_digits($report['meta']['window_days'] ?? 7) ?> روز · نسخه <?= e($report['meta']['version'] ?? '') ?> · منطقهٔ زمانی <?= e($report['meta']['timezone'] ?? '') ?> · تولید <?= e(jdatetime($report['meta']['generated_at'] ?? '')) ?>.
      <?php if (empty($report['meta']['complete'])): ?>داده از سقف پوشش گزارش عبور کرده یا کامل نیست؛ نرخ نهایی برای بخش مربوط نمایش داده نمی‌شود.<?php else: ?>پوشش محاسبه کامل است در محدوده و سقف‌های مستندشده.<?php endif; ?>
    </div>
    <div class="kpi-grid">
      <article class="kpi-card"><span>K1 · قراردادهای دارای پیگیری طبقه‌بندی‌شده</span><strong><?= to_persian_digits($followups['contracts'] ?? 0) ?></strong><small><?= to_persian_digits($followups['events'] ?? 0) ?> رویداد؛ <?= to_persian_digits($followups['unclassified'] ?? 0) ?> رویداد بدون طبقه‌بندی</small></article>
      <article class="kpi-card"><span>K2 · پوشش مالک معتبر و اقدام بعدی</span><strong><?= $fmtRate($ownership['rate'] ?? null) ?></strong><small><?= to_persian_digits($ownership['covered'] ?? 0) ?> از <?= to_persian_digits($ownership['eligible'] ?? 0) ?> پرونده · مالک معتبر <?= to_persian_digits($ownership['valid_owner'] ?? 0) ?> · اقدام معتبر <?= to_persian_digits($ownership['valid_next_action'] ?? 0) ?></small></article>
      <article class="kpi-card"><span>K3 · اقدام‌های تکمیل‌شده به‌موقع</span><strong><?= $fmtRate($tasks['rate'] ?? null) ?></strong><small><?= to_persian_digits($tasks['on_time'] ?? 0) ?> به‌موقع از <?= to_persian_digits($tasks['denominator'] ?? 0) ?> اقدام سررسیدشده</small></article>
      <article class="kpi-card primary"><span>K4 · قرارداد دارای وصول در پنجره</span><strong><?= $fmtRate($collection['rate'] ?? null) ?></strong><small><?= to_persian_digits($collection['collected_contracts'] ?? 0) ?> از <?= to_persian_digits($collection['matured_cohort'] ?? 0) ?> پنجرهٔ کامل؛ <?= to_persian_digits($collection['pending_window'] ?? 0) ?> هنوز کامل نشده</small></article>
      <article class="kpi-card"><span>K5 · مبلغ تخصیص‌یافته پس از پیگیری</span><strong><?= $fmtMoney($collection['gross_collected_toman'] ?? 0) ?></strong><small>پرداخت‌های واجد شرایط در پنجره‌های کامل، پیش از سقف مبلغ مبنا</small></article>
      <article class="kpi-card"><span>K6 · مبلغ بازیابی‌شده نسبت به مبنا</span><strong><?= $fmtRate($collection['recovery_rate'] ?? null) ?></strong><small><?= $fmtMoney($collection['capped_recovery_toman'] ?? 0) ?> از مبنای <?= $fmtMoney($collection['baseline_toman'] ?? 0) ?></small></article>
      <article class="kpi-card"><span>K7 · تحقق کامل قول‌های سررسیدشده</span><strong><?= $fmtRate($promises['rate'] ?? null) ?></strong><small><?= to_persian_digits($promises['fulfilled'] ?? 0) ?> کامل · <?= to_persian_digits($promises['partially_fulfilled'] ?? 0) ?> جزئی · <?= to_persian_digits($promises['broken'] ?? 0) ?> نقض‌شده · <?= to_persian_digits($promises['amountless_excluded'] ?? 0) ?> بدون مبلغ و خارج از مخرج</small></article>
    </div>
    <section class="card collection-queue"><div class="card-header"><h3>شواهد تشکیل‌دهندهٔ KPI</h3><small><?= to_persian_digits($evidence['total'] ?? 0) ?> ردیف · هر صفحه حداکثر ۵۰ ردیف</small></div><div class="card-body">
      <form method="get" action="<?= e(url('collections/performance')) ?>" class="form-grid two">
        <input type="hidden" name="from" value="<?= e($filters['from'] ?? '') ?>"><input type="hidden" name="to" value="<?= e($filters['to'] ?? '') ?>"><input type="hidden" name="operator_id" value="<?= (int) ($filters['operator_id'] ?? 0) ?>"><input type="hidden" name="window_days" value="<?= (int) ($filters['window_days'] ?? 7) ?>">
        <label>گروه شواهد<select name="evidence_type"><option value="collection" <?= ($evidence['type'] ?? '') === 'collection' ? 'selected' : '' ?>>K4–K6 · وصول پس از پیگیری</option><option value="promise" <?= ($evidence['type'] ?? '') === 'promise' ? 'selected' : '' ?>>K7 · تحقق وعده</option></select></label><button class="btn secondary" type="submit">نمایش شواهد</button>
      </form>
      <div class="table-wrap"><table><thead><?php if (($evidence['type'] ?? '') === 'promise'): ?><tr><th>قرارداد</th><th>شناسهٔ وعده</th><th>موعد</th><th>مبلغ وعده</th><th>مبلغ تخصیص‌یافته تا موعد</th><th>نتیجه</th><th>پرداخت‌های واجد شرایط</th></tr><?php else: ?><tr><th>قرارداد</th><th>شناسهٔ پیگیری</th><th>زمان اقدام</th><th>اپراتور اقدام</th><th>مبنای بدهی</th><th>وصول واجدشرایط</th><th>نتیجه</th><th>پرداخت‌های واجد شرایط</th></tr><?php endif; ?></thead><tbody>
        <?php foreach (($evidence['items'] ?? []) as $item): ?><tr>
          <?php if (($evidence['type'] ?? '') === 'promise'): ?><td><a href="<?= e(url('collections/timeline/' . (int) $item['contract_id'])) ?>">#<?= to_persian_digits($item['contract_id']) ?> · تاریخچه</a></td><td><?= to_persian_digits($item['promise_id']) ?></td><td><?= e(jdate($item['promise_date'] ?? '')) ?></td><td><?= $item['promise_amount_toman'] === null ? '—' : $fmtMoney($item['promise_amount_toman']) ?></td><td><?= $fmtMoney($item['paid_toman'] ?? 0) ?></td><td><?= e($evidenceStatusLabels[$item['status'] ?? ''] ?? 'نامشخص') ?><?= !empty($item['ambiguous_same_day']) ? ' · تاریخ هم‌روز' : '' ?></td><td><?= e($paymentEvidenceLabel($item['payments'] ?? [])) ?: '—' ?></td>
          <?php else: ?><td><a href="<?= e(url('collections/timeline/' . (int) $item['contract_id'])) ?>">#<?= to_persian_digits($item['contract_id']) ?> · تاریخچه</a></td><td><?= to_persian_digits($item['followup_id']) ?></td><td><?= e(jdatetime($item['event_at'] ?? '')) ?></td><td><?= to_persian_digits($item['operator_id'] ?? 0) ?></td><td><?= $fmtMoney($item['baseline_toman'] ?? 0) ?></td><td><?= $fmtMoney($item['collected_toman'] ?? 0) ?></td><td><?= e($evidenceStatusLabels[$item['status'] ?? ''] ?? 'نامشخص') ?><?= !empty($item['ambiguous_same_day']) ? ' · تاریخ هم‌روز' : '' ?></td><td><?= e($paymentEvidenceLabel($item['payments'] ?? [])) ?: '—' ?></td><?php endif; ?>
        </tr><?php endforeach; ?>
        <?php if (empty($evidence['items'])): ?><tr><td colspan="8" class="empty">برای این گروه در بازهٔ انتخابی ردیف شواهدی وجود ندارد.</td></tr><?php endif; ?>
      </tbody></table></div>
      <?php if (($evidence['pages'] ?? 1) > 1): ?><nav class="pagination" aria-label="صفحه‌بندی شواهد KPI"><?php foreach (array_filter([$evidence['page'] > 1 ? $evidence['page'] - 1 : null, $evidence['page'], $evidence['page'] < $evidence['pages'] ? $evidence['page'] + 1 : null]) as $evidencePage): ?><a class="btn small <?= $evidencePage === $evidence['page'] ? '' : 'secondary' ?>" href="<?= e(url('collections/performance', ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '', 'operator_id' => (int) ($filters['operator_id'] ?? 0), 'window_days' => (int) ($filters['window_days'] ?? 7), 'evidence_type' => $evidence['type'], 'evidence_page' => $evidencePage])) ?>"><?= to_persian_digits($evidencePage) ?></a><?php endforeach; ?><span>از <?= to_persian_digits($evidence['pages']) ?> صفحه</span></nav><?php endif; ?>
    </div></section>
    <?php if (($collection['needs_review_same_day'] ?? 0) > 0 || ($promises['needs_review'] ?? 0) > 0): ?><div class="notice warning">پرداخت‌های هم‌روزِ بدون زمان دقیق به‌عنوان نتیجهٔ قطعی پیگیری شمرده نشده‌اند: <?= to_persian_digits($collection['needs_review_same_day'] ?? 0) ?> مورد پیگیری و <?= to_persian_digits($promises['needs_review'] ?? 0) ?> قول نیازمند بررسی.</div><?php endif; ?>
    <section class="card collection-queue"><div class="card-header"><h3>K8 · عملکرد بر اساس ثبت‌کنندهٔ رویداد</h3><small>اقدام و وصول به actor ثبت‌شده در لحظهٔ رویداد نسبت داده می‌شود؛ تعداد قرارداد فعال، snapshot مالک فعلی است.</small></div><div class="table-wrap"><table><thead><tr><th>اپراتور</th><th>قرارداد فعال</th><th>تماس ثبت‌شده</th><th>اقدام تکمیل‌شده</th><th>اقدام به‌موقع</th><th>اقدام بازِ عقب‌افتاده</th><th>قول ثبت‌شده</th><th>قول کامل / سررسیدشده</th><th>پیگیری طبقه‌بندی‌شده</th><th>پنجرهٔ وصول کامل</th><th>قرارداد دارای وصول</th><th>نرخ وصول</th><th>وصول ناخالص</th><th>بازیابی سقف‌دار / مبنا</th></tr></thead><tbody>
      <?php foreach (($report['operators'] ?? []) as $operator): ?><tr><td><?= e($operator['operator_name'] ?? '') ?></td><td><?= to_persian_digits($operator['active_contracts'] ?? 0) ?></td><td><?= to_persian_digits($operator['calls_registered'] ?? 0) ?></td><td><?= to_persian_digits($operator['tasks_completed'] ?? 0) ?></td><td><?= to_persian_digits($operator['tasks_on_time'] ?? 0) ?> / <?= to_persian_digits($operator['tasks_due'] ?? 0) ?> (<?= $fmtRate($operator['task_on_time_rate'] ?? null) ?>)</td><td><?= to_persian_digits($operator['tasks_overdue'] ?? 0) ?></td><td><?= to_persian_digits($operator['promises_registered'] ?? 0) ?></td><td><?= to_persian_digits($operator['promises_fulfilled'] ?? 0) ?> / <?= to_persian_digits($operator['promises_matured'] ?? 0) ?> (<?= $fmtRate($operator['promise_fulfillment_rate'] ?? null) ?>)</td><td><?= to_persian_digits($operator['followup_contracts'] ?? 0) ?></td><td><?= to_persian_digits($operator['matured_cohort'] ?? 0) ?></td><td><?= to_persian_digits($operator['collected_contracts'] ?? 0) ?></td><td><?= $fmtRate($operator['collection_rate'] ?? null) ?></td><td><?= $fmtMoney($operator['gross_collected_toman'] ?? 0) ?></td><td><?= $fmtMoney($operator['capped_recovery_toman'] ?? 0) ?> / <?= $fmtMoney($operator['baseline_toman'] ?? 0) ?> (<?= $fmtRate($operator['recovery_rate'] ?? null) ?>)</td></tr><?php endforeach; ?>
      <?php if (empty($report['operators'])): ?><tr><td colspan="14" class="empty">در این بازه رویدادی ثبت نشده است.</td></tr><?php endif; ?>
    </tbody></table></div></section>
  </div></section>
  <section class="card collection-queue"><div class="card-header"><h3>تعریف و محدودیت شاخص‌ها</h3></div><div class="card-body">
    <ul>
      <li>K1: تعداد قرارداد یکتا با اقدام دارای result_code در بازه؛ رویدادهای promise و دادهٔ قدیمی/طبقه‌بندی‌نشده جداست.</li>
      <li>K2: موتور رسمی صف معوقات در زمان تولید گزارش؛ پوشش = مالک فعال معتبر و اقدام بازِ دارای موعد / همهٔ قراردادهای معوق واجد شرایط. سقف ممیزی ۲٬۰۰۰ قرارداد است.</li>
      <li>K3: اقدام‌های باز یا تکمیل‌شده که موعدشان در بازه بوده و تا زمان گزارش سررسید شده؛ لغوشده‌ها از مخرج خارج‌اند. تکمیل به‌موقع یعنی completed_at ≤ due_at.</li>
      <li>K4: اولین پیگیری طبقه‌بندی‌شدهٔ هر قرارداد در بازه که snapshot رسمی و مثبت دارد؛ مخرج فقط پنجره‌های کامل و بدون ابهام زمانی هم‌روز است. وصول = حداقل یک تخصیص پرداخت معتبر در همان قرارداد به بدهی‌ای که در زمان پیگیری سررسید شده بود.</li>
      <li>K5: جمع تخصیص‌های معتبر پس از پیگیری در پنجره؛ هر payment_id در هر قرارداد یک‌بار و پرداخت اصلاح‌شده/برگشتی خارج از محاسبه است.</li>
      <li>K6: min(وصول پنجره، snapshot بدهی زمان پیگیری) برای هر قرارداد، جمع‌شده بر جمع snapshotهای همان cohort.</li>
      <li>K7: وعدهٔ جاری با تاریخ سررسید در بازه و مبلغ مثبت؛ فقط پرداخت به همان قرارداد، برای بدهی سررسیده تا موعد وعده و در/پیش از همان موعد. قول بدون مبلغ خارج از مخرج است.</li>
      <li>K8: تماس و وعده به ثبت‌کنندهٔ رویداد، اقدام تکمیل‌شده به completed_by، اقدام باز به assigned_to، و cohort وصول به اپراتور اولین پیگیری واجدشرایط نسبت داده می‌شود. تعداد قرارداد فعال، مالک معتبر فعلی در لحظهٔ گزارش است؛ اقدام عقب‌افتاده نیز snapshot وظایف باز در زمان گزارش است.</li>
      <li>پرداختی که فقط تاریخ دارد و در همان روز اقدام ثبت شده، برای نسبت K4 قطعی فرض نمی‌شود. این گزارش رابطهٔ زمانی را می‌سنجد و ادعای اثر علّی پیگیری ندارد.</li>
      <li>سقف‌ها: ۵٬۰۰۰ cohort، ۵٬۰۰۰ وعده و ۲۰٬۰۰۰ ردیف تخصیص؛ عبور از سقف پوشش را ناقص می‌کند و نرخ مربوط را حذف می‌کند. دادهٔ قدیمی بدون snapshot از K4/K6 کنار گذاشته می‌شود.</li>
    </ul>
  </div></section>
</div>
