<?php
$counts = $dashboard['counts'] ?? [];
$latest = $dashboard['latest'] ?? [];
$readyRepairs = $dashboard['ready'] ?? [];
$accountingBalance = $accountingBalance ?? null;
$expenses = $expenses ?? [];
$pendingExpenses = array_values(array_filter($expenses, static fn ($expense) => in_array(($expense['status'] ?? ''), ['pending_approval', 'approved'], true)));
?>
<div class="proma-repair pr-technician-dashboard">
  <?php if (!empty($dashboardWarning)): ?><div class="notice warning" role="status"><?= e($dashboardWarning) ?></div><?php endif; ?>
  <?php if ($accountingBalance && is_array($accountingBalance)): ?><?php require dirname(__DIR__, 4) . '/plugins/PromaAccounting/views/components/account-balance.php'; ?><?php endif; ?>

  <header class="pr-tech-hero">
    <div class="pr-tech-hero__copy"><span>میز کار تعمیرکار</span><h1>سلام، <?= e(Auth::user()['full_name'] ?? 'تعمیرکار') ?></h1><p>پرونده‌های در جریان، دستگاه‌های آماده تحویل و درخواست هزینه را دنبال کنید.</p></div>
    <a class="btn secondary" href="<?= e(url('plugin/repair/technician-profile')) ?>"><i data-feather="user"></i> پروفایل کاری</a>
  </header>

  <section class="pr-tech-metrics" aria-label="خلاصه کار تعمیرکار">
    <?php foreach ([
      ['در حال تعمیر', (int) ($counts['repairing'] ?? 0), 'edit-3', 'violet'],
      ['در انتظار بررسی', (int) ($counts['waiting_inspection'] ?? 0), 'search', 'blue'],
      ['منتظر قطعه', (int) ($counts['waiting_parts'] ?? 0), 'package', 'amber'],
      ['آماده تحویل', (int) ($counts['ready_for_delivery'] ?? 0), 'check-circle', 'green'],
    ] as $item): ?>
      <article class="pr-tech-metric pr-tech-metric--<?= e($item[3]) ?>"><span><?= proma_icon($item[2], $item[0]) ?></span><div><small><?= e($item[0]) ?></small><strong><?= to_persian_digits($item[1]) ?></strong></div></article>
    <?php endforeach; ?>
  </section>

  <nav class="pr-tech-shortcuts" aria-label="دسترسی‌های تعمیرکار">
    <a href="<?= e(url('plugin/repair/orders')) ?>"><i data-feather="tool"></i><span><b>تعمیرات من</b><small>پرونده‌های ارجاع‌شده</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('plugin/repair/technician-requests')) ?>"><i data-feather="file-text"></i><span><b>درخواست‌های هزینه</b><small><?= to_persian_digits(count($pendingExpenses)) ?> مورد در جریان</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('plugin/repair/technician-history')) ?>"><i data-feather="clock"></i><span><b>تاریخچه تعمیرات</b><small>کارهای تکمیل‌شده</small></span><i data-feather="chevron-left"></i></a>
  </nav>

  <div class="pr-tech-columns">
    <section class="pr-card pr-tech-work-card">
      <div class="pr-card-head"><div><span class="pr-tech-section-icon is-violet"><?= proma_icon('edit-3', 'پرونده‌های فعال') ?></span><div><h2>پرونده‌های فعال من</h2><p>اول پرونده‌های قدیمی‌تر را بازبینی کنید.</p></div></div><a class="btn small secondary" href="<?= e(url('plugin/repair/orders')) ?>">همه تعمیرات</a></div>
      <?php if (!$latest): ?><div class="pr-empty">پرونده فعالی به شما ارجاع نشده است.</div><?php else: ?><div class="pr-tech-repair-list"><?php foreach (array_slice($latest, 0, 8) as $repair): ?>
        <a class="pr-tech-repair-row" href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span class="pr-tech-device-icon"><?= proma_icon('smartphone', 'دستگاه') ?></span><span class="pr-tech-repair-main"><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['tracking_code'] ?? '') ?> · <?= e(jdatetime($repair['created_at'] ?? '')) ?></small></span><span class="pr-badge is-info"><?= e(\Proma\Plugins\Repair\Domain\RepairWorkflow::label($repair['status'] ?? '')) ?></span><i data-feather="chevron-left"></i></a>
      <?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="pr-card pr-tech-ready-card">
      <div class="pr-card-head"><div><span class="pr-tech-section-icon is-green"><?= proma_icon('check', 'آماده تحویل') ?></span><div><h2>آماده تحویل</h2><p>پرونده‌هایی که پس از کنترل نهایی بسته می‌شوند.</p></div></div><a class="btn small secondary" href="<?= e(url('plugin/repair/orders')) ?>">مشاهده فهرست</a></div>
      <?php if (!$readyRepairs): ?><div class="pr-empty">دستگاهی در صف تحویل نیست.</div><?php else: ?><div class="pr-tech-ready-list"><?php foreach (array_slice($readyRepairs, 0, 5) as $repair): ?><a href="<?= e(url('plugin/repair/detail/' . (int) $repair['id'])) ?>"><span><strong><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($repair['tracking_code'] ?? '') ?> · <?= e($repair['full_name'] ?? '') ?></small></span><i data-feather="arrow-up-left"></i></a><?php endforeach; ?></div><?php endif; ?>
    </section>
  </div>

  <section class="pr-card pr-tech-expenses-card">
    <div class="pr-card-head"><div><span class="pr-tech-section-icon is-amber"><?= proma_icon('credit-card', 'درخواست هزینه') ?></span><div><h2>درخواست هزینه اخیر</h2><p>وضعیت ثبت و بررسی هزینه‌های قطعه و دستمزد</p></div></div><a class="btn small secondary" href="<?= e(url('plugin/repair/technician-expenses')) ?>">همه هزینه‌ها</a></div>
    <?php if (!$expenses): ?><div class="pr-empty">هزینه‌ای ثبت نکرده‌اید.</div><?php else: ?><div class="pr-tech-expense-list"><?php foreach (array_slice($expenses, 0, 6) as $expense): ?><article><span><strong><?= e($expense['description'] ?? 'هزینه تعمیر') ?></strong><small><?= e($expense['tracking_code'] ?? '') ?> · <?= e(jdate($expense['expense_date'] ?? $expense['created_at'] ?? '')) ?></small></span><b><?= money_toman($expense['amount'] ?? 0) ?></b><span class="pr-badge <?= ($expense['status'] ?? '') === 'paid' ? 'is-success' : ((($expense['status'] ?? '') === 'rejected') ? 'is-danger' : 'is-warning') ?>"><?= e(['pending_approval'=>'در انتظار بررسی','approved'=>'تأییدشده','rejected'=>'ردشده','paid'=>'تسویه‌شده'][$expense['status'] ?? ''] ?? 'ثبت‌شده') ?></span></article><?php endforeach; ?></div><?php endif; ?>
  </section>
</div>
