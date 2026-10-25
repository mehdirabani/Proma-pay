<?php
$accountingBalance = $accountingBalance ?? null;
$metrics = $metrics ?? [];
$cases = $cases ?? [];
$eligible = $eligible ?? [];
$openCases = array_values(array_filter($cases, static fn ($case) => ($case['status'] ?? '') !== 'closed'));
?>
<main class="v7-role-dashboard v7-role-dashboard--lawyer" dir="rtl">
  <?php if ($accountingBalance && is_array($accountingBalance)): ?><div class="v7-role-account-balance"><?php require dirname(__DIR__, 2) . '/plugins/PromaAccounting/views/components/account-balance.php'; ?></div><?php endif; ?>
  <section class="v7-role-hero v7-role-hero--lawyer">
    <div class="v7-role-hero__copy"><span class="v7-role-eyebrow">میز کار حقوقی</span><h1>پرونده‌های حقوقی</h1><p>پرونده‌های باز، ارجاع‌های تازه و قراردادهای آماده بررسی را در یک صف کاری ببینید.</p></div>
    <div class="v7-role-hero__actions"><a class="btn primary" href="<?= e(url('legal')) ?>"><i data-feather="folder"></i> باز کردن پرونده‌ها</a><a class="btn secondary" href="<?= e(url('legal/eligible')) ?>"><i data-feather="scale"></i> بررسی واجد شرایط‌ها</a></div>
    <div class="v7-role-hero__focus"><span>هزینه ثبت‌شده پرونده‌ها</span><strong><?= money_toman($metrics['expenses'] ?? 0) ?></strong><small><?= to_persian_digits(count($cases)) ?> پرونده تخصیص‌یافته</small></div>
  </section>

  <section class="v7-role-metrics" aria-label="خلاصه پرونده‌های حقوقی">
    <?php foreach ([
      ['همه پرونده‌ها', $metrics['cases'] ?? count($cases), 'تخصیص‌یافته به شما', 'folder', 'violet'],
      ['پرونده‌های باز', $metrics['open_cases'] ?? count($openCases), 'در انتظار اقدام', 'activity', 'blue'],
      ['آماده بررسی حقوقی', $metrics['eligible'] ?? count($eligible), 'قرارداد واجد شرایط', 'scale', 'rose'],
      ['هزینه ثبت‌شده', money_toman($metrics['expenses'] ?? 0), 'جمع هزینه پرونده‌ها', 'credit-card', 'amber'],
    ] as $metric): ?>
      <article class="v7-role-metric v7-role-metric--<?= e($metric[4]) ?>"><span class="v7-role-metric__icon"><i data-feather="<?= e($metric[3]) ?>"></i></span><div><small><?= e($metric[0]) ?></small><strong><?= is_numeric($metric[1]) ? to_persian_digits($metric[1]) : $metric[1] ?></strong><em><?= e($metric[2]) ?></em></div></article>
    <?php endforeach; ?>
  </section>

  <nav class="v7-role-shortcuts" aria-label="دسترسی‌های حقوقی">
    <a href="<?= e(url('legal')) ?>"><i data-feather="folder"></i><span><b>پرونده‌های من</b><small>مرحله و وضعیت رسیدگی</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('legal/eligible')) ?>"><i data-feather="scale"></i><span><b>آماده بررسی حقوقی</b><small><?= to_persian_digits(count($eligible)) ?> قرارداد در صف</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('legal/costs')) ?>"><i data-feather="credit-card"></i><span><b>هزینه‌های حقوقی</b><small>ثبت و بررسی هزینه پرونده</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('legal/lawyerPayments')) ?>"><i data-feather="dollar-sign"></i><span><b>صورت‌حساب من</b><small>مانده و پرداخت‌های ثبت‌شده</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('calendar')) ?>"><i data-feather="calendar"></i><span><b>تقویم جلسات</b><small>موعدهای پیش‌رو</small></span><i data-feather="chevron-left"></i></a>
  </nav>

  <div class="v7-role-columns v7-role-columns--lawyer">
    <section class="v7-role-panel v7-role-panel--legal-queue">
      <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-rose"><i data-feather="scale"></i></span><div><h2>قراردادهای آماده بررسی</h2><p>پرونده‌های قابل ارجاع به واحد حقوقی</p></div></div><a href="<?= e(url('legal/eligible')) ?>">مشاهده همه <i data-feather="chevron-left"></i></a></header>
      <div class="v7-role-records">
        <?php foreach (array_slice($eligible, 0, 7) as $contract): ?>
          <a class="v7-role-record v7-role-record--linked" href="<?= e(url('contracts/show/' . (int) ($contract['id'] ?? 0))) ?>"><span class="v7-role-record__date"><i data-feather="file-text"></i><small><?= e($contract['contract_number'] ?? '—') ?></small></span><span class="v7-role-record__main"><strong><?= e($contract['customer_name'] ?? 'مشتری') ?></strong><small><?= to_persian_digits($contract['overdue_count'] ?? 0) ?> قسط معوق</small></span><b class="v7-role-record__amount"><?= money_toman($contract['debt'] ?? $contract['overdue_amount'] ?? 0) ?></b><i data-feather="chevron-left"></i></a>
        <?php endforeach; ?>
        <?php if (!$eligible): ?><div class="v7-role-empty"><i data-feather="check-circle"></i><strong>موردی در صف ارجاع نیست</strong><span>قراردادهای واجد شرایط پس از بررسی در اینجا نمایش داده می‌شوند.</span></div><?php endif; ?>
      </div>
    </section>

    <section class="v7-role-panel v7-role-panel--legal-cases">
      <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-violet"><i data-feather="briefcase"></i></span><div><h2>پرونده‌های در جریان</h2><p>پرونده‌های باز تخصیص‌یافته به شما</p></div></div><a href="<?= e(url('legal')) ?>">همه پرونده‌ها <i data-feather="chevron-left"></i></a></header>
      <div class="v7-role-records">
        <?php foreach (array_slice($openCases, 0, 7) as $case): ?>
          <article class="v7-role-record"><span class="v7-role-record__date"><i data-feather="briefcase"></i><small><?= e($case['contract_number'] ?? '—') ?></small></span><div class="v7-role-record__main"><strong><?= e($case['customer_name'] ?? 'مشتری') ?></strong><small><?= e($case['stage'] ?? 'مرحله ثبت نشده') ?></small></div><span class="badge <?= e(badge_class($case['status'] ?? '')) ?>"><?= e(status_label($case['status'] ?? '')) ?></span><a class="v7-icon-action" href="<?= e(url('legal/show/' . (int) ($case['id'] ?? 0))) ?>" aria-label="جزئیات پرونده"><i data-feather="arrow-up-left"></i></a></article>
        <?php endforeach; ?>
        <?php if (!$openCases): ?><div class="v7-role-empty"><i data-feather="folder"></i><strong>پرونده بازی ندارید</strong><span>پرونده‌های جدید پس از تخصیص در این قسمت قرار می‌گیرند.</span></div><?php endif; ?>
      </div>
    </section>
  </div>

  <section class="v7-role-panel v7-role-panel--cost-ledger">
    <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-amber"><i data-feather="credit-card"></i></span><div><h2>هزینه پرونده‌ها</h2><p>مبالغ ثبت‌شده برای پرونده‌های شما</p></div></div><a href="<?= e(url('legal/costs')) ?>">مدیریت هزینه‌ها <i data-feather="chevron-left"></i></a></header>
    <div class="v7-role-table-wrap"><table class="v7-role-table"><thead><tr><th>قرارداد</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th><th>هزینه ثبت‌شده</th><th></th></tr></thead><tbody>
      <?php foreach (array_slice($cases, 0, 8) as $case): ?><tr><td><b><?= e($case['contract_number'] ?? '—') ?></b></td><td><?= e($case['customer_name'] ?? '—') ?></td><td><?= e($case['stage'] ?? '—') ?></td><td><span class="badge <?= e(badge_class($case['status'] ?? '')) ?>"><?= e(status_label($case['status'] ?? '')) ?></span></td><td><?= money_toman($case['expense_amount'] ?? 0) ?></td><td><a class="v7-text-action" href="<?= e(url('legal/show/' . (int) ($case['id'] ?? 0))) ?>">جزئیات <i data-feather="chevron-left"></i></a></td></tr><?php endforeach; ?>
      <?php if (!$cases): ?><tr><td colspan="6" class="v7-role-empty">پرونده‌ای برای نمایش وجود ندارد.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main>
