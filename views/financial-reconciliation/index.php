<?php
$summary = $summary ?? [];
$filters = $filters ?? [];
$list = $list ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$run = $run ?? [];
$runs = $runs ?? [];
$statusLabels = ['open' => 'باز', 'under_review' => 'در حال بررسی', 'proposed_resolution' => 'پیشنهاد اصلاح', 'pending_approval' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'correction_applied' => 'اصلاح انجام شد', 'reverified' => 'تطبیق مجدد', 'resolved' => 'برطرف‌شده', 'rejected' => 'ردشده', 'reopened' => 'دوباره بازشده', 'known_historical_gap' => 'شکاف تاریخی', 'accepted_exception' => 'استثنا پذیرفته‌شده', 'needs_more_evidence' => 'شواهد ناکافی', 'failed_verification' => 'تطبیق ناموفق'];
$classificationLabels = ['verified_mismatch' => 'مغایرت تأییدشده', 'historical_coverage_gap' => 'شکاف پوشش تاریخی', 'insufficient_evidence' => 'شواهد ناکافی'];
$severityLabels = ['critical' => 'بحرانی', 'high' => 'زیاد', 'review' => 'نیازمند بررسی', 'info' => 'اطلاعاتی'];
$ruleLabels = ['R01' => 'پرداخت و تخصیص', 'R02' => 'پرداخت و دفترکل', 'R03' => 'مانده قسط', 'R04' => 'وضعیت تسویه', 'R05' => 'اصلاح و برگشت', 'R06' => 'هزینه حقوقی', 'R07' => 'ارجاعات', 'R08' => 'پوشش تاریخی'];
$queryFilters = array_filter($filters, static function ($value) { return $value !== ''; });
?>
<main class="page-body proma-recon" dir="rtl">
  <header class="proma-recon__heading">
    <div>
      <span class="proma-recon__eyebrow">مالی / کنترل و بررسی</span>
      <h1>مرکز تطبیق مالی</h1>
      <p>این مرکز اختلاف را از شواهد ثبت‌شده شناسایی می‌کند. اسکن و بررسی پرونده به‌تنهایی مجوز تغییر مبلغ یا مانده نیست.</p>
    </div>
    <a class="btn secondary" href="<?= e(url('financial-reconciliation/export', $queryFilters)) ?>">دریافت CSV برای Excel</a>
  </header>

  <section class="proma-recon__metrics" aria-label="آمار پرونده‌های تطبیق">
    <?php foreach ([
      ['open_count', 'مغایرت‌های باز', 'open'], ['critical_count', 'موارد بحرانی', 'critical'],
      ['review_count', 'در حال بررسی', 'review'], ['approval_count', 'در انتظار تأیید', 'approval'],
      ['resolved_count', 'برطرف‌شده', 'resolved'], ['historical_gap_count', 'شکاف پوشش تاریخی', 'history'],
      ['evidence_count', 'نیازمند شواهد', 'evidence'],
    ] as [$key, $label, $tone]): ?>
      <article class="proma-recon__metric proma-recon__metric--<?= e($tone) ?>"><span><?= e($label) ?></span><strong><?= to_persian_digits((int) ($summary[$key] ?? 0)) ?></strong></article>
    <?php endforeach; ?>
  </section>

  <section class="proma-recon__panel proma-recon__scan">
    <div class="proma-recon__panel-title"><div><h2>اجرای اسکن</h2><p>تاریخ مبنا و نسخهٔ قواعد در هر اجرا ذخیره می‌شود. اسکن فقط جداول پرونده‌های تطبیق را می‌نویسد.</p></div></div>
    <form method="post" action="<?= e(url('financial-reconciliation/start')) ?>" class="proma-recon__scan-form">
      <?= csrf_field() ?>
      <label>شماره یا شناسه قرارداد <input type="text" name="contract_id" inputmode="numeric" placeholder="خالی = همه قراردادها"></label>
      <label>تعداد قرارداد در هر نوبت <select name="batch_size"><option value="10">۱۰</option><option value="20" selected>۲۰</option><option value="50">۵۰</option></select></label>
      <button class="btn primary" type="submit">شروع اسکن</button>
    </form>
    <?php if (!empty($run)): $runStatus = (string) ($run['status'] ?? ''); $progress = (int) ($run['target_count'] ?? 0) > 0 ? min(100, (int) floor(100 * (int) ($run['checked_count'] ?? 0) / (int) $run['target_count'])) : 100; ?>
      <div class="proma-recon__run" role="status">
        <div class="proma-recon__run-head"><strong>اجرای <?= e($run['run_type'] === 'contract' ? 'قرارداد ' . to_persian_digits($run['scope_contract_id']) : 'مجموعه') ?></strong><span class="proma-recon__badge proma-recon__badge--<?= e($runStatus) ?>"><?= e(['running' => 'در حال اجرا', 'completed' => 'کامل', 'partial' => 'ناقص', 'failed' => 'ناموفق'][$runStatus] ?? $runStatus) ?></span></div>
        <div class="proma-recon__progress"><span style="width:<?= (int) $progress ?>%"></span></div>
        <div class="proma-recon__run-meta"><span><?= to_persian_digits((int) ($run['checked_count'] ?? 0)) ?> از <?= to_persian_digits((int) ($run['target_count'] ?? 0)) ?> قرارداد</span><span><?= to_persian_digits((int) ($run['discrepancy_count'] ?? 0)) ?> مشاهده</span><span><?= e(jdatetime($run['started_at'] ?? '')) ?></span><span>قاعده <?= e($run['rule_version'] ?? '') ?></span></div>
        <?php if (($runStatus ?? '') === 'running'): ?><form method="post" action="<?= e(url('financial-reconciliation/continueRun/' . (int) $run['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="batch_size" value="20"><button class="btn small secondary" type="submit">ادامه اسکن</button></form><?php endif; ?>
        <?php if (!empty($run['error_summary'])): ?><details class="proma-recon__errors"><summary>خطاهای این اجرای اسکن</summary><pre><?= e($run['error_summary']) ?></pre></details><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="proma-recon__coverage-note"><strong>محدودیت پوشش دفترکل:</strong> تاریخ اولین رویداد Core موجود فقط کران پایین قابل‌مشاهده است و اثبات نمی‌کند همه پرداخت‌های بعد از آن رویداد، ledger دارند. پرداخت تاریخی بدون رویداد به‌صورت «شکاف تاریخی» یا «شواهد ناکافی» طبقه‌بندی می‌شود؛ به‌تنهایی خطای قطعی نیست. Ledger افزونهٔ PromaAccounting با Core Ledger یکی فرض نشده است.</div>
  </section>

  <?php if (!empty($runs)): ?>
  <section class="proma-recon__panel proma-recon__runs"><div class="proma-recon__panel-title"><div><h2>اجراهای اخیر</h2><p>اجرای ناقص به‌عنوان اجرای موفق گزارش نمی‌شود و پرونده‌ها را خودکار نمی‌بندد.</p></div></div>
    <div class="proma-recon__runs-list">
      <?php foreach ($runs as $recentRun): ?><a href="<?= e(url('financial-reconciliation', ['run_id' => (int) $recentRun['id']])) ?>" class="proma-recon__run-link"><span class="proma-recon__badge proma-recon__badge--<?= e($recentRun['status']) ?>"><?= e(['running' => 'در حال اجرا', 'completed' => 'کامل', 'partial' => 'ناقص', 'failed' => 'ناموفق'][$recentRun['status']] ?? $recentRun['status']) ?></span><span><?= e($recentRun['run_type'] === 'contract' ? 'قرارداد ' . to_persian_digits($recentRun['scope_contract_id']) : 'اسکن مجموعه') ?></span><small><?= to_persian_digits((int) $recentRun['checked_count']) ?> / <?= to_persian_digits((int) $recentRun['target_count']) ?> قرارداد</small><small><?= e(jdatetime($recentRun['started_at'])) ?></small></a><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="proma-recon__panel">
    <div class="proma-recon__panel-title"><div><h2>پرونده‌های تطبیق</h2><p>مبالغ اختلاف در این جدول جداگانه نمایش داده می‌شوند و با هم جمع نمی‌شوند؛ چند قاعده ممکن است به یک رخداد اشاره کند.</p></div><span class="proma-recon__count"><?= to_persian_digits((int) $list['total']) ?> پرونده</span></div>
    <form method="get" action="<?= e(url('financial-reconciliation')) ?>" class="proma-recon__filters">
      <input type="hidden" name="route" value="financial-reconciliation">
      <label>قرارداد<input name="contract" value="<?= e($filters['contract']) ?>" placeholder="شماره یا شناسه"></label>
      <label>مشتری<input name="customer" value="<?= e($filters['customer']) ?>" placeholder="نام یا شناسه"></label>
      <label>از تاریخ<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
      <label>تا تاریخ<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
      <label>قاعده<select name="rule_id"><option value="">همه قواعد</option><?php foreach ($ruleLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= $filters['rule_id'] === $key ? ' selected' : '' ?>><?= e($key . ' · ' . $label) ?></option><?php endforeach; ?></select></label>
      <label>شدت<select name="severity"><option value="">همه</option><?php foreach ($severityLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= $filters['severity'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>وضعیت<select name="status"><option value="">همه</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>طبقه‌بندی<select name="classification"><option value="">همه</option><?php foreach ($classificationLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= $filters['classification'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>مسئول (شناسه)<input name="assigned_user_id" inputmode="numeric" value="<?= e($filters['assigned_user_id']) ?>"></label>
      <label>شناسه پرداخت<input name="payment_id" inputmode="numeric" value="<?= e($filters['payment_id']) ?>"></label>
      <label>شناسه رویداد دفترکل<input name="ledger_event_id" inputmode="numeric" value="<?= e($filters['ledger_event_id']) ?>"></label>
      <div class="proma-recon__filter-actions"><button type="submit" class="btn small primary">اعمال فیلتر</button><a class="btn small secondary" href="<?= e(url('financial-reconciliation')) ?>">پاک‌کردن</a></div>
    </form>
    <div class="table-responsive proma-recon__table-wrap"><table class="table proma-recon__table"><thead><tr><th>شناسه</th><th>قرارداد / مشتری</th><th>نوع</th><th>مورد انتظار</th><th>واقعی</th><th>اختلاف</th><th>شدت</th><th>طبقه‌بندی / وضعیت</th><th>مسئول</th><th>آخرین مشاهده</th><th>جزئیات</th></tr></thead><tbody>
      <?php if (empty($list['items'])): ?><tr><td colspan="11" class="proma-recon__empty">پرونده‌ای با این فیلترها پیدا نشد.</td></tr><?php endif; ?>
      <?php foreach ($list['items'] as $item): ?>
        <tr>
          <td><strong>#<?= to_persian_digits((int) $item['id']) ?></strong><small><?= e($item['rule_id']) ?></small></td>
          <td><?php if (!empty($item['contract_id'])): ?><a href="<?= e(url('contracts/show/' . (int) $item['contract_id'])) ?>"><?= e($item['contract_number'] ?: 'قرارداد #' . $item['contract_id']) ?></a><?php else: ?>—<?php endif; ?><small><?= e($item['customer_name'] ?: ('مشتری #' . ($item['customer_id'] ?? '—'))) ?></small></td>
          <td><?= e($ruleLabels[$item['rule_id']] ?? $item['issue_type']) ?><small><?= e($item['issue_type']) ?></small></td>
          <td><?= $item['expected_toman'] === null ? '—' : money_toman($item['expected_toman']) ?></td>
          <td><?= $item['actual_toman'] === null ? '—' : money_toman($item['actual_toman']) ?></td>
          <td><?= $item['difference_toman'] === null ? '—' : money_toman($item['difference_toman']) ?></td>
          <td><span class="proma-recon__badge proma-recon__badge--<?= e($item['severity']) ?>"><?= e($severityLabels[$item['severity']] ?? $item['severity']) ?></span></td>
          <td><span class="proma-recon__classification proma-recon__classification--<?= e($item['classification']) ?>"><?= e($classificationLabels[$item['classification']] ?? $item['classification']) ?></span><small><?= e($statusLabels[$item['status']] ?? $item['status']) ?></small></td>
          <td><?= e($item['assigned_name'] ?: '—') ?></td>
          <td><?= e(jdatetime($item['last_seen_at'] ?? '')) ?></td>
          <td><a class="btn small secondary" href="<?= e(url('financial-reconciliation/show/' . (int) $item['id'])) ?>">بررسی</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php if ((int) $list['pages'] > 1): ?><nav class="proma-recon__pagination" aria-label="صفحه‌بندی پرونده‌ها"><?php for ($p = max(1, (int) $list['page'] - 2); $p <= min((int) $list['pages'], (int) $list['page'] + 2); $p++): ?><a class="<?= $p === (int) $list['page'] ? 'is-current' : '' ?>" href="<?= e(url('financial-reconciliation', $queryFilters + ['page' => $p])) ?>"><?= to_persian_digits($p) ?></a><?php endfor; ?></nav><?php endif; ?>
  </section>
</main>
