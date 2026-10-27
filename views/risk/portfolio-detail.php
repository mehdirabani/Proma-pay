<?php
$detail = $detail ?? [];
$filters = $detail['filters'] ?? [];
$metric = $detail['metric'] ?? '';
$reportSummary = $report['summary'] ?? [];
$pageUrl = static function (int $page) use ($metric, $filters): string {
    return url('risk/portfolioDetail', array_merge($filters, ['metric' => $metric, 'page' => $page]));
};
?>
<section class="portfolio-analytics portfolio-detail" aria-labelledby="portfolio-detail-title">
  <header class="portfolio-hero">
    <div><span class="proma-eyebrow">جزئیات ردیابی منبع · <?= e($detail['metric_version'] ?? '') ?></span><h2 id="portfolio-detail-title"><?= e($metricLabel ?? 'گزارش پرتفوی') ?></h2><p>ردیف‌ها و KPI در یک snapshot فقط‌خواندنی محاسبه شده‌اند. تاریخ مبنا: <?= e(jdate($detail['as_of'] ?? date('Y-m-d'))) ?> · محاسبه <?= e(jdatetime($report['computed_at'] ?? date('Y-m-d H:i:s'))) ?></p></div>
    <div class="portfolio-detail-actions"><a class="btn secondary" href="<?= e(url('risk/portfolioExport', array_merge($filters, ['metric' => $metric]))) ?>"><?= proma_icon('download') ?><span>خروجی Excel</span></a><a class="btn secondary" href="<?= e(url('risk/portfolio', $filters)) ?>">بازگشت به تحلیل پرتفوی</a></div>
  </header>
  <section class="portfolio-detail-summary" aria-label="جمع شاخص و منبع">
    <article><span>مجموع شاخص</span><strong><?= money_toman($metricTotal ?? 0) ?></strong><small><?= to_persian_digits($detail['total'] ?? 0) ?> ردیف واجد فیلتر</small></article>
    <?php if ($metric === 'cohort'): ?><article><span>اصل سررسیدشدهٔ Cohort</span><strong><?= money_toman($reportSummary['due_cohort_obligation'] ?? 0) ?></strong><small>صورت نرخ: <?= money_toman($reportSummary['due_cohort_collected'] ?? 0) ?> اصل پرداخت‌شده</small></article><?php endif; ?>
    <article><span>صفحهٔ جاری</span><strong><?= to_persian_digits($detail['page'] ?? 1) ?> / <?= to_persian_digits($detail['pages'] ?? 1) ?></strong><small><?= to_persian_digits($detail['per_page'] ?? 25) ?> ردیف در صفحه</small></article>
    <article><span>مجموع مشارکت همین صفحه</span><strong><?= money_toman($detail['page_contribution_total'] ?? 0) ?></strong><small>مجموع کل صفحه فقط برای ردیف‌های قابل محاسبه</small></article>
  </section>
  <?php if ($metric === 'cohort' && empty($reportSummary['due_cohort_rate'])): ?><div class="portfolio-notice is-warning" role="status">برای مخرج صفر یا دادهٔ ناقص نرخ نمایش داده نمی‌شود. وضعیت ناقص تاریخچهٔ برنامهٔ فعلی را در گزارش اصلی ببینید.</div><?php endif; ?>

  <section class="card portfolio-panel">
    <div class="portfolio-panel-head"><div><h3>رکوردهای تشکیل‌دهندهٔ شاخص</h3><p>مجموع مشارکت‌های قابل جمع در تمام صفحات باید با KPI اصلی برابر باشد.</p></div></div>
    <div class="table-responsive"><table class="portfolio-data-table"><caption><?= e($metricLabel ?? 'گزارش پرتفوی') ?> · ردیف‌های منبع</caption>
      <?php if ($metric === 'cash'): ?>
        <thead><tr><th scope="col">شناسه پرداخت</th><th scope="col">تاریخ دریافت</th><th scope="col">قرارداد / مشتری</th><th scope="col">قسط</th><th scope="col">نوع</th><th scope="col">روش</th><th scope="col">وضعیت</th><th scope="col">ارجاع اصلاح</th><th scope="col">مبلغ معتبر</th></tr></thead><tbody>
        <?php foreach (($detail['rows'] ?? []) as $row): ?><tr><th scope="row"><a href="<?= e(url('payments', ['q' => (string) ($row['payment_id'] ?? '')])) ?>">#<?= to_persian_digits($row['payment_id']) ?></a></th><td><?= e(jdate($row['payment_date'] ?? '')) ?></td><td><a href="<?= e(url('contracts/show/' . (int) $row['contract_id'])) ?>"><?= e($row['contract_number'] ?? '') ?></a><small><?= e($row['customer_name'] ?? '') ?></small></td><td><?= !empty($row['installment_number']) ? to_persian_digits($row['installment_number']) : '—' ?></td><td><?= e($row['payment_type'] ?? 'installment') ?></td><td><?= e($row['method'] ?? '') ?></td><td><?= e(status_label($row['status'] ?? '')) ?></td><td><?= !empty($row['correction_reference']) ? e(jdatetime($row['correction_reference'])) : '—' ?></td><td><?= money_toman($row['contribution'] ?? 0) ?></td></tr><?php endforeach; ?>
      <?php else: ?>
        <thead><tr><th scope="col">شناسه قسط</th><th scope="col">قرارداد / مشتری</th><th scope="col">شماره قسط</th><th scope="col">سررسید</th><th scope="col">اصل برنامه</th><?php if ($metric === 'cohort'): ?><th scope="col">اصل وصول‌شده تا تاریخ مبنا</th><?php endif; ?><th scope="col">اصل مانده</th><th scope="col">جریمه جاری</th><?php if ($metric === 'aging'): ?><th scope="col">سن تأخیر</th><?php endif; ?><th scope="col">مشارکت</th><th scope="col">وضعیت محاسبه</th></tr></thead><tbody>
        <?php foreach (($detail['rows'] ?? []) as $row): ?>
          <?php $charge = (int) ($row['normal_penalty'] ?? 0) + (int) ($row['legal_penalty'] ?? 0); ?>
          <tr><th scope="row">#<?= to_persian_digits($row['id'] ?? 0) ?></th><td><a href="<?= e(url('contracts/show/' . (int) ($row['contract_id'] ?? 0))) ?>"><?= e($row['contract_number'] ?? '') ?></a><small><?= e($row['customer_name'] ?? '') ?></small></td><td><?= to_persian_digits($row['installment_number'] ?? 0) ?></td><td><?= e(jdate($row['due_date'] ?? '')) ?></td><td><?= money_toman($row['base_amount'] ?? 0) ?></td><?php if ($metric === 'cohort'): ?><td><?= money_toman($row['paid_principal'] ?? 0) ?></td><?php endif; ?><td><?= money_toman($row['remaining_principal'] ?? 0) ?></td><td><?= money_toman($charge) ?></td><?php if ($metric === 'aging'): ?><td><?= to_persian_digits($row['overdue_days'] ?? 0) ?> روز · <?= e($row['aging_bucket'] ?? '') ?></td><?php endif; ?><td><?= money_toman($row['contribution'] ?? 0) ?></td><td><?= in_array(($row['calculation_status'] ?? ''), ['calculated', 'not_applicable'], true) ? 'محاسبه‌شده' : 'نیازمند بررسی' ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if (empty($detail['rows'])): ?><tr><td colspan="12"><div class="empty">رکورد منبعی در این بازه پیدا نشد.</div></td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <?php if (($detail['pages'] ?? 1) > 1): ?><nav class="portfolio-pagination" aria-label="صفحه‌بندی جزئیات"><span>نمایش <?= to_persian_digits(($detail['page'] - 1) * $detail['per_page'] + 1) ?> تا <?= to_persian_digits(min($detail['total'], $detail['page'] * $detail['per_page'])) ?> از <?= to_persian_digits($detail['total']) ?></span><div><?php if ($detail['page'] > 1): ?><a class="btn small secondary" href="<?= e($pageUrl($detail['page'] - 1)) ?>">صفحه قبل</a><?php endif; ?><?php if ($detail['page'] < $detail['pages']): ?><a class="btn small secondary" href="<?= e($pageUrl($detail['page'] + 1)) ?>">صفحه بعد</a><?php endif; ?></div></nav><?php endif; ?>
  </section>
  <details class="portfolio-coverage"><summary>فیلترها و قواعد محاسبه</summary><div class="portfolio-coverage-grid"><p><strong>از / تا:</strong> <?= e(jdate($filters['from'] ?? '')) ?> تا <?= e(jdate($filters['to'] ?? '')) ?></p><p><strong>فیلترهای درخواست:</strong> وضعیت <?= e($filters['status'] ?? 'all') ?>، ریسک <?= e($filters['risk'] ?? 'all') ?>، سن معوق <?= e($filters['aging'] ?? 'all') ?></p><p><strong>داده مالی:</strong> مشاهدهٔ منبع فقط‌خواندنی است؛ مبلغ اصل مانده از محاسبهٔ canonical اقساط می‌آید.</p><p><strong>تغییر داده:</strong> جزئیات هنگام بازکردن در یک snapshot تازه محاسبه شده است؛ برای مشاهدهٔ آخرین وضعیت گزارش را دوباره بازآوری کنید.</p></div></details>
</section>
