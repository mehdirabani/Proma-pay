<?php
$accountingBalance = $accountingBalance ?? null;
$metrics = $metrics ?? [];
$contracts = $contracts ?? [];
$calls = $calls ?? [];
$overdue = $overdue ?? [];
$followups = array_values(array_filter($calls, static fn ($call) => !empty($call['next_followup_date']) && $call['next_followup_date'] >= date('Y-m-d')));
usort($followups, static fn ($left, $right) => strcmp((string) ($left['next_followup_date'] ?? ''), (string) ($right['next_followup_date'] ?? '')));
$openContracts = array_values(array_filter($contracts, static fn ($contract) => ($contract['status'] ?? '') === 'active'));
$lastCallByContract = [];
foreach ($calls as $call) {
  $contractId = (int) ($call['contract_id'] ?? 0);
  if ($contractId > 0 && !isset($lastCallByContract[$contractId])) $lastCallByContract[$contractId] = $call;
}
$daysLate = static function ($dueDate): int {
  try { return max(0, (int) (new DateTime((string) $dueDate))->diff(new DateTime('today'))->format('%r%a')); }
  catch (Throwable $ignored) { return 0; }
};
$overdueBalance = array_sum(array_map(static fn ($item) => (float) ($item['payable'] ?? $item['base_amount'] ?? 0), $overdue));
?>
<main class="v7-role-dashboard v7-role-dashboard--operator" dir="rtl">
  <?php if ($accountingBalance && is_array($accountingBalance)): ?><div class="v7-role-account-balance"><?php require dirname(__DIR__, 2) . '/plugins/PromaAccounting/views/components/account-balance.php'; ?></div><?php endif; ?>
  <section class="v7-role-hero v7-role-hero--operator">
    <div class="v7-role-hero__copy"><span class="v7-role-eyebrow">میز کار اپراتور</span><h1>سلام، <?= e(Auth::user()['full_name'] ?? 'اپراتور') ?></h1><p>پرونده‌های فعال، تماس‌های موعددار و اقساط معوق را از همین صفحه پیگیری کنید.</p></div>
    <div class="v7-role-hero__actions"><a class="btn primary" href="<?= e(url('overdue')) ?>"><i data-feather="phone-call"></i> شروع پیگیری معوقات</a><a class="btn secondary" href="<?= e(url('calendar')) ?>"><i data-feather="calendar"></i> برنامه امروز</a></div>
    <div class="v7-role-hero__focus"><span>مانده صف معوق</span><strong><?= money_toman($overdueBalance) ?></strong><small><?= to_persian_digits(count($overdue)) ?> قسط در صف شما</small></div>
  </section>

  <section class="v7-role-metrics" aria-label="خلاصه عملکرد اپراتور">
    <?php foreach ([
      ['پرونده‌های فعال', $metrics['active_contracts'] ?? count($openContracts), 'قرارداد', 'file-text', 'violet'],
      ['اقساط معوق', $metrics['overdue'] ?? count($overdue), 'مورد نیازمند تماس', 'alert-triangle', 'rose'],
      ['سررسید امروز', $metrics['today'] ?? 0, 'اولویت تماس امروز', 'calendar', 'amber'],
      ['پیگیری‌های آینده', count($followups), 'تماس برنامه‌ریزی‌شده', 'clock', 'blue'],
    ] as $metric): ?>
      <article class="v7-role-metric v7-role-metric--<?= e($metric[4]) ?>"><span class="v7-role-metric__icon"><i data-feather="<?= e($metric[3]) ?>"></i></span><div><small><?= e($metric[0]) ?></small><strong><?= to_persian_digits($metric[1]) ?></strong><em><?= e($metric[2]) ?></em></div></article>
    <?php endforeach; ?>
  </section>

  <nav class="v7-role-shortcuts" aria-label="دسترسی‌های اپراتور">
    <a href="<?= e(url('overdue')) ?>"><i data-feather="alert-circle"></i><span><b>صف وصول</b><small>تماس و ثبت نتیجه</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('contracts')) ?>"><i data-feather="file-text"></i><span><b>قراردادهای من</b><small><?= to_persian_digits(count($contracts)) ?> قرارداد تخصیص‌یافته</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('calendar')) ?>"><i data-feather="calendar"></i><span><b>تقویم پیگیری</b><small>قرارهای نزدیک</small></span><i data-feather="chevron-left"></i></a>
    <a href="<?= e(url('chat')) ?>"><i data-feather="message-circle"></i><span><b>گفت‌وگو</b><small>پاسخ به مشتریان</small></span><i data-feather="chevron-left"></i></a>
  </nav>

  <div class="v7-role-columns">
    <section class="v7-role-panel v7-role-panel--priority">
      <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-rose"><i data-feather="alert-triangle"></i></span><div><h2>نیازمند پیگیری</h2><p>معوق‌ها به ترتیب تاریخ سررسید</p></div></div><a href="<?= e(url('overdue')) ?>">مشاهده صف <i data-feather="chevron-left"></i></a></header>
      <div class="v7-role-records">
        <?php foreach (array_slice($overdue, 0, 6) as $item): ?>
          <article class="v7-role-record"><span class="v7-role-record__date"><?= e(jdate($item['due_date'] ?? '')) ?><small><?= to_persian_digits($daysLate($item['due_date'] ?? 'today')) ?> روز تأخیر</small></span><div class="v7-role-record__main"><strong><?= e($item['customer_name'] ?? 'مشتری') ?></strong><small><?= e($item['contract_number'] ?? '—') ?> · قسط <?= to_persian_digits($item['installment_number'] ?? '—') ?></small></div><b class="v7-role-record__amount"><?= money_toman($item['payable'] ?? $item['base_amount'] ?? 0) ?></b><a class="v7-icon-action" href="<?= e(url('installments')) ?>" aria-label="مشاهده قسط"><i data-feather="arrow-up-left"></i></a></article>
        <?php endforeach; ?>
        <?php if (!$overdue): ?><div class="v7-role-empty"><i data-feather="check-circle"></i><strong>صف شما خالی است</strong><span>قسط معوقی برای پیگیری ندارید.</span></div><?php endif; ?>
      </div>
    </section>

    <section class="v7-role-panel">
      <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-blue"><i data-feather="clock"></i></span><div><h2>تماس‌های برنامه‌ریزی‌شده</h2><p>نوبت‌های پیگیری بعدی شما</p></div></div><a href="<?= e(url('overdue')) ?>">ثبت تماس <i data-feather="chevron-left"></i></a></header>
      <div class="v7-role-records">
        <?php foreach (array_slice($followups, 0, 6) as $call): ?>
          <article class="v7-role-record"><span class="v7-role-record__date"><?= e(jdate($call['next_followup_date'])) ?><small>پیگیری</small></span><div class="v7-role-record__main"><strong><?= e($call['customer_name'] ?? 'مشتری') ?></strong><small><?= e($call['contract_number'] ?? ($call['call_result'] ?? 'تماس ثبت‌شده')) ?></small></div><span class="v7-role-record__note"><?= e($call['notes'] ?? 'بدون یادداشت') ?></span></article>
        <?php endforeach; ?>
        <?php if (!$followups): ?><div class="v7-role-empty"><i data-feather="calendar"></i><strong>تماس زمان‌بندی‌شده‌ای ندارید</strong><span>با ثبت نتیجه تماس، نوبت بعدی را مشخص کنید.</span></div><?php endif; ?>
      </div>
    </section>
  </div>

  <section class="v7-role-panel">
    <header class="v7-role-panel__head"><div><span class="v7-role-section-mark is-violet"><i data-feather="briefcase"></i></span><div><h2>قراردادهای تحت پیگیری</h2><p>وضعیت پرونده‌های ارجاع‌شده به شما</p></div></div><a href="<?= e(url('contracts')) ?>">همه قراردادها <i data-feather="chevron-left"></i></a></header>
    <div class="v7-role-table-wrap"><table class="v7-role-table"><thead><tr><th>قرارداد</th><th>مشتری</th><th>وضعیت</th><th>آخرین نتیجه تماس</th><th></th></tr></thead><tbody>
      <?php foreach (array_slice($contracts, 0, 8) as $contract): $lastCall = $lastCallByContract[(int) ($contract['id'] ?? 0)] ?? null; ?><tr><td><b><?= e($contract['contract_number'] ?? '—') ?></b></td><td><?= e($contract['customer_name'] ?? '—') ?><small><?= to_persian_digits($contract['mobile'] ?? '') ?></small></td><td><span class="badge <?= e(badge_class($contract['status'] ?? '')) ?>"><?= e(status_label($contract['status'] ?? '')) ?></span></td><td><?= e($lastCall['call_result'] ?? 'تماسی ثبت نشده') ?></td><td><a class="v7-text-action" href="<?= e(url('contracts/show/' . (int) ($contract['id'] ?? 0))) ?>">جزئیات <i data-feather="chevron-left"></i></a></td></tr><?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="5" class="v7-role-empty">قراردادی به شما ارجاع نشده است.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main>
