<?php $data = $timeline ?? []; $contract = $data['contract'] ?? []; $summary = $data['financial_summary'] ?? []; $page = $data['page'] ?? ['page' => 1, 'pages' => 1, 'total' => 0]; $promise = $data['active_promise'] ?? null; $timelineLabels = ['principal' => 'اصل اقساط', 'normal_late_fee' => 'جریمه عادی', 'legal_late_fee' => 'جریمه حقوقی', 'down_payment' => 'پیش‌پرداخت', 'unallocated' => 'وجه تخصیص‌نیافته', 'discount' => 'بخشش یا تخفیف', 'write_off' => 'حذف بدهی', 'charge' => 'ثبت بدهی', 'payment' => 'پرداخت', 'reversal' => 'اصلاحیه']; ?>
<section class="card">
  <div class="card-header"><div class="header-top"><div><h2>خط زمانی قرارداد <?= e($contract['contract_number'] ?? '') ?></h2><p><?= e($contract['customer_name'] ?? '') ?> · <?= e($contract['mobile'] ?? '') ?></p></div><a class="btn secondary" href="<?= e(url('collections')) ?>">بازگشت به مرکز وصول</a></div></div>
  <div class="card-body">
    <div class="kpi-grid">
      <?php foreach ([['مانده قابل وصول', $summary['final_collectable_amount'] ?? 0], ['اصل اقساط', $summary['unpaid_installments_total'] ?? 0], ['جریمه دیرکرد', $summary['late_penalty_total'] ?? 0], ['هزینه حقوقی', $summary['legal_costs_total'] ?? 0]] as [$label,$amount]): ?>
        <article class="kpi-card"><span><?= e($label) ?></span><strong><?= to_persian_digits(number_format(normalize_money($amount))) ?> <small>تومان</small></strong></article>
      <?php endforeach; ?>
    </div>
    <?php if ($promise): ?>
      <section class="card collection-queue"><div class="card-header"><h3>قول پرداخت فعال</h3><span><?= e($promise['derived_status'] ?? 'نیازمند بررسی') ?></span></div><div class="card-body">
        <p>موعد: <?= e(jdate($promise['promise_payment_date'] ?? '')) ?> · مبلغ ثبت‌شده: <?= !empty($promise['promise_amount_toman']) ? money_toman($promise['promise_amount_toman']) : 'نامشخص' ?> · پرداخت واجد شرایط پس از ثبت: <?= money_toman($promise['eligible_paid_toman'] ?? 0) ?></p>
        <form method="post" action="<?= e(url('collections/promiseAction/' . (int) $promise['id'])) ?>" class="form-grid two">
          <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) ($contract['id'] ?? 0) ?>">
          <label>تغییر<select name="action"><option value="reschedule">تغییر موعد</option><option value="cancel">لغو قول</option></select></label>
          <label>موعد جدید<input name="promise_payment_date" class="jalali-date" autocomplete="off" placeholder="برای تغییر موعد الزامی است"></label>
          <label>مبلغ جدید (تومان، اختیاری)<input name="promise_amount_toman" inputmode="numeric" min="1" value="<?= e($promise['promise_amount_toman'] ?? '') ?>"></label>
          <label>دلیل تغییر<span class="required-mark" aria-hidden="true">*</span><input name="reason" maxlength="500" required></label>
          <button class="btn" type="submit">ثبت رویداد تکمیلی</button>
        </form>
      </div></section>
    <?php endif; ?>
    <div class="collection-timeline">
      <?php foreach (($data['items'] ?? []) as $event): ?>
        <?php $title = (string) ($event['title'] ?? ''); foreach ($timelineLabels as $key => $label) $title = str_replace($key, $label, $title); ?>
        <article class="collection-timeline-item"><time><?= e(jdatetime($event['at'] ?? '')) ?></time><div><strong><?= e($title) ?></strong><p><?= e($event['detail'] ?? '') ?></p><?php if (!empty($event['url']) && (Auth::role() === 'admin' || strpos((string) $event['url'], 'legal/') !== 0)): ?><a href="<?= e(url($event['url'])) ?>">مشاهده جزئیات</a><?php endif; ?></div><span class="badge info"><?= e($event['status'] ?? '') ?></span></article>
      <?php endforeach; ?>
      <?php if (empty($data['items'])): ?><div class="empty">رویدادی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
    </div>
    <?php if (($page['pages'] ?? 1) > 1): ?><nav class="pagination" aria-label="صفحه‌بندی تاریخچه"><?php if (($page['page'] ?? 1) > 1): ?><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) ($contract['id'] ?? 0), ['page' => (int) $page['page'] - 1])) ?>">رویدادهای قدیمی‌تر</a><?php endif; ?><span>صفحهٔ <?= to_persian_digits($page['page']) ?> از <?= to_persian_digits($page['pages']) ?> · <?= to_persian_digits($page['total']) ?> رویداد</span><?php if (($page['page'] ?? 1) < ($page['pages'] ?? 1)): ?><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) ($contract['id'] ?? 0), ['page' => (int) $page['page'] + 1])) ?>">رویدادهای جدیدتر</a><?php endif; ?></nav><?php endif; ?>
  </div>
</section>
