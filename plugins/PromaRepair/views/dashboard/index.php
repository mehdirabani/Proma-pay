<?php
$counts = $dashboard['counts'] ?? [];
$items = [
  ['ثبت امروز', (int) ($counts['received_today'] ?? 0), 'archive'],
  ['در انتظار بررسی', (int) ($counts['waiting_inspection'] ?? 0), 'eye'],
  ['در حال عیب‌یابی', (int) ($counts['diagnosing'] ?? 0), 'eye'],
  ['منتظر تأیید مشتری', (int) ($counts['waiting_customer_approval'] ?? 0), 'phone'],
  ['تأییدشده', (int) ($counts['approved'] ?? 0), 'check'],
  ['در حال تعمیر', (int) ($counts['repairing'] ?? 0), 'edit'],
  ['منتظر قطعه', (int) ($counts['waiting_parts'] ?? 0), 'archive'],
  ['تعمیر انجام‌شده', (int) ($counts['repair_completed'] ?? 0), 'check'],
  ['بررسی نهایی', (int) ($counts['quality_control'] ?? 0), 'eye'],
  ['آماده تحویل', (int) ($counts['ready_for_delivery'] ?? 0), 'check'],
  ['تحویل‌شده', (int) ($counts['delivered'] ?? 0), 'check'],
  ['لغوشده', (int) ($counts['cancelled'] ?? 0), 'close'],
  ['غیرقابل تعمیر', (int) ($counts['unable_to_repair'] ?? 0), 'slash'],
];
$financialItems = [['فروش خدمات', $financial['revenue'] ?? 0], ['مبلغ دریافت‌شده', $financial['received'] ?? 0], ['مانده مشتریان', $financial['balance'] ?? 0], ['هزینه قطعات', $financial['part_costs'] ?? 0], ['دستمزد تعمیرکاران', $financial['technician_costs'] ?? 0], ['سایر هزینه‌ها', $financial['other_costs'] ?? 0]];
if (!empty($canProfit)) {
  $financialItems[] = ['سود ناخالص', $financial['gross_profit'] ?? 0];
  $financialItems[] = ['سود خالص', $financial['profit'] ?? 0];
}
?>
<div class="proma-repair">
  <?php if (!empty($dashboardWarning)): ?><div class="notice warning" role="status"><?= e($dashboardWarning) ?></div><?php endif; ?>
  <header class="pr-hero">
    <span class="pr-hero-icon"><?= proma_icon('tool', 'تعمیرات') ?></span>
    <div class="pr-hero-copy"><small>Proma Pay Repair Management</small><h2>مدیریت تعمیرات</h2><p>پذیرش، پیگیری وضعیت و تحویل دستگاه‌ها</p></div>
    <div class="pr-hero-actions"><?php if ($canCreate): ?><button class="btn primary" type="button" data-open-modal="repair-intake-modal"><i data-feather="plus"></i> پذیرش تعمیر</button><?php endif; ?><a class="btn secondary" href="<?= e(url('plugin/repair/orders')) ?>">همه پرونده‌ها</a></div>
  </header>

  <section class="pr-kpis">
    <?php foreach ($items as $item): ?>
      <article class="pr-kpi"><span class="pr-kpi-icon"><?= proma_icon($item[2], $item[0]) ?></span><div><span><?= e($item[0]) ?></span><strong><?= to_persian_digits($item[1]) ?></strong></div></article>
    <?php endforeach; ?>
  </section>

  <?php if ($canFinancial && $financial): ?>
    <section class="pr-kpis">
      <?php foreach ($financialItems as $item): ?>
        <article class="pr-kpi"><span class="pr-kpi-icon"><?= proma_icon('chart', $item[0]) ?></span><div><span><?= e($item[0]) ?></span><strong><?= money_toman($item[1]) ?></strong><small>در همه پرونده‌ها</small></div></article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-xl-7">
      <section class="pr-card">
        <div class="pr-card-head"><div><h3>آخرین تعمیرات</h3><p>پرونده‌هایی که اخیراً ثبت شده‌اند</p></div><a class="link-only" href="<?= e(url('plugin/repair/orders')) ?>">مشاهده همه</a></div>
        <?php if (empty($dashboard['latest'])): ?><div class="pr-empty">هنوز پرونده تعمیری ثبت نشده است.</div>
        <?php else: ?><div class="pr-row-list"><?php foreach ($dashboard['latest'] as $repair): ?>
          <a href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['customer_name']) ?> · <span class="pr-code"><?= e($repair['tracking_code']) ?></span></small></span><span class="pr-badge is-info"><?= e(\Proma\Plugins\Repair\Domain\RepairWorkflow::label($repair['status'])) ?></span></a>
        <?php endforeach; ?></div><?php endif; ?>
      </section>
    </div>
    <div class="col-xl-5">
      <section class="pr-card">
        <div class="pr-card-head"><div><h3>پرونده‌های بدون تعمیرکار</h3><p>نیازمند ارجاع</p></div></div>
        <?php if (empty($dashboard['unassigned'])): ?><div class="pr-empty">همه پرونده‌های فعال ارجاع شده‌اند.</div>
        <?php else: ?><div class="pr-row-list"><?php foreach ($dashboard['unassigned'] as $repair): ?>
          <a href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['full_name']) ?> · <span class="pr-code"><?= e($repair['tracking_code']) ?></span></small></span><span class="pr-badge is-warning">ارجاع نشده</span></a>
        <?php endforeach; ?></div><?php endif; ?>
      </section>
    </div>
  </div>

  <section class="pr-card">
    <div class="pr-card-head"><div><h3>تعمیرات در جریان</h3><p>قدیمی‌ترین پرونده‌های باز</p></div></div>
    <?php if (empty($dashboard['overdue'])): ?><div class="pr-empty">پرونده باز ندارید.</div>
    <?php else: ?><div class="pr-table-wrap"><table class="pr-table"><thead><tr><th scope="col">کد رهگیری</th><th scope="col">دستگاه</th><th scope="col">مشتری</th><th scope="col">وضعیت</th><th scope="col">ثبت</th><th scope="col"></th></tr></thead><tbody>
      <?php foreach ($dashboard['overdue'] as $repair): ?><tr>
        <td data-label="کد رهگیری"><span class="pr-code"><?= e($repair['tracking_code']) ?></span></td>
        <td data-label="دستگاه"><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: '—') ?></td>
        <td data-label="مشتری"><?= e($repair['full_name']) ?></td>
        <td data-label="وضعیت"><span class="pr-badge is-info"><?= e(\Proma\Plugins\Repair\Domain\RepairWorkflow::label($repair['status'])) ?></span></td>
        <td data-label="ثبت"><?= e(jdatetime($repair['created_at'])) ?></td>
        <td><a class="btn small secondary" href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>">پرونده</a></td>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <?php if (!empty($dashboard['ready'])): ?><section class="pr-card"><div class="pr-card-head"><div><h3>آماده تحویل</h3><p>پرونده‌های منتظر دریافت مشتری</p></div></div><div class="pr-row-list"><?php foreach ($dashboard['ready'] as $repair): ?><a href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['full_name']) ?> · <span class="pr-code"><?= e($repair['tracking_code']) ?></span></small></span><span class="pr-badge is-success">آماده تحویل</span></a><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($canFinancial && !empty($dashboard['debts'])): ?><section class="pr-card"><div class="pr-card-head"><div><h3>مانده مشتریان</h3><p>بیشترین مانده در پرونده‌های باز</p></div></div><div class="pr-row-list"><?php foreach ($dashboard['debts'] as $repair): ?><a href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['full_name']) ?> · <span class="pr-code"><?= e($repair['tracking_code']) ?></span></small></span><b><?= money_toman($repair['balance']) ?></b></a><?php endforeach; ?></div></section><?php endif; ?>
  <?php if (!empty($dashboard['technician_performance'])): ?><section class="pr-card"><div class="pr-card-head"><div><h3>عملکرد تعمیرکاران</h3><p>پرونده‌های فعال و میانگین زمان تحویل</p></div></div><div class="pr-table-wrap"><table class="pr-table"><thead><tr><th scope="col">تعمیرکار</th><th scope="col">ارجاع‌شده</th><th scope="col">تحویل‌شده</th><th scope="col">میانگین زمان (ساعت)</th></tr></thead><tbody><?php foreach ($dashboard['technician_performance'] as $tech): ?><tr><td data-label="تعمیرکار"><?= e($tech['full_name']) ?></td><td data-label="ارجاع‌شده"><?= to_persian_digits($tech['assigned_repairs']) ?></td><td data-label="تحویل‌شده"><?= to_persian_digits($tech['completed_repairs']) ?></td><td data-label="میانگین زمان"><?= to_persian_digits(round((float) ($tech['average_hours'] ?? 0), 1)) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
</div>
<?php require __DIR__ . '/../intake/index.php'; ?>
