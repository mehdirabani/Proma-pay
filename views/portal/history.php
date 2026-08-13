<?php
$summary = $summary ?? [];
$contracts = $contracts ?? [];
$lastPurchase = $summary['last_purchase'] ?? null;
?>

<section class="proma-customer-history" aria-label="سوابق خرید و قراردادها">
  <header class="proma-page-intro">
    <div><span class="proma-eyebrow">مرکز خرید من</span><h2>سوابق خرید</h2><p>از خرید تا قرارداد، اقساط و پرداخت‌ها را برای هر قرارداد در یک نمای روشن دنبال کنید.</p></div>
    <a class="btn secondary" href="<?= e(url('installments/panel')) ?>">اقساط من</a>
  </header>

  <div class="proma-history-kpis" aria-label="خلاصه خرید">
    <article class="proma-history-kpi"><span class="proma-history-kpi__icon is-primary"><i data-feather="shopping-bag"></i></span><div><small>خرید ثبت‌شده</small><strong><?= to_persian_digits($summary['purchases'] ?? 0) ?></strong><span>سوابق خرید شما</span></div></article>
    <article class="proma-history-kpi"><span class="proma-history-kpi__icon is-success"><i data-feather="file-text"></i></span><div><small>قرارداد فعال</small><strong><?= to_persian_digits($summary['active_contracts'] ?? 0) ?></strong><span>قابل پیگیری و پرداخت</span></div></article>
    <article class="proma-history-kpi"><span class="proma-history-kpi__icon is-warning"><i data-feather="credit-card"></i></span><div><small>مجموع پرداخت</small><strong><?= money_toman($summary['total_paid'] ?? 0) ?></strong><span><?= $lastPurchase ? 'آخرین خرید: ' . e($lastPurchase['contract_number'] ?? '-') : 'هنوز خریدی ثبت نشده است' ?></span></div></article>
  </div>

  <?php if (!$contracts): ?>
    <section class="card"><div class="proma-empty-state"><i data-feather="shopping-bag"></i><h3>هنوز سابقهٔ خریدی برای شما ثبت نشده است.</h3><p>پس از ثبت قرارداد، جزئیات خرید و برنامهٔ پرداخت در همین بخش نمایش داده می‌شود.</p></div></section>
  <?php else: ?>
    <div class="proma-purchase-contract-list">
      <?php foreach ($contracts as $contract): ?>
        <?php $history = $contract['history'] ?? []; $isOverdue = (int) ($history['overdue_installment_count'] ?? 0) > 0; ?>
        <article class="card proma-purchase-contract-card">
          <header class="proma-purchase-contract-card__header">
            <div><span class="proma-eyebrow">خرید و قرارداد</span><h3><?= e($contract['contract_number']) ?></h3><small>تاریخ خرید: <?= e(jdate($contract['start_date'])) ?></small></div>
            <span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span>
          </header>
          <div class="proma-purchase-contract-card__amounts">
            <span><small>مبلغ قرارداد</small><strong><?= money_toman($contract['principal_amount']) ?></strong></span>
            <span><small>پیش‌پرداخت</small><strong><?= money_toman($contract['down_payment_amount'] ?? 0) ?></strong></span>
            <span><small>پرداخت‌شده</small><strong><?= money_toman($history['paid_total'] ?? 0) ?></strong></span>
            <span><small>مانده تقریبی</small><strong><?= money_toman($history['remaining_total'] ?? 0) ?></strong></span>
          </div>
          <div class="proma-payment-progress" aria-label="<?= to_persian_digits($history['progress'] ?? 0) ?> درصد پرداخت‌شده"><div><span>پیشرفت پرداخت</span><strong><?= to_persian_digits($history['progress'] ?? 0) ?>٪</strong></div><progress value="<?= (int) ($history['progress'] ?? 0) ?>" max="100"><?= to_persian_digits($history['progress'] ?? 0) ?>٪</progress></div>
          <div class="proma-purchase-contract-card__facts">
            <span><small>اقساط</small><strong><?= to_persian_digits($history['paid_installment_count'] ?? 0) ?> از <?= to_persian_digits($history['installment_count'] ?? 0) ?> پرداخت شده</strong></span>
            <span><small>سررسید بعدی</small><strong><?= !empty($history['next_due_date']) ? e(jdate($history['next_due_date'])) : 'قسط بازی وجود ندارد' ?></strong></span>
            <span class="<?= $isOverdue ? 'is-alert' : '' ?>"><small>وضعیت پیگیری</small><strong><?= $isOverdue ? to_persian_digits($history['overdue_installment_count']) . ' قسط معوق' : 'بدون سررسید گذشته' ?></strong></span>
          </div>
          <footer class="proma-purchase-contract-card__actions">
            <a class="btn" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>">مشاهده جزئیات</a>
            <a class="btn secondary" href="<?= e(url('installments/panel')) ?>">اقساط</a>
            <a class="btn secondary" href="<?= e(url('payments', ['contract' => $contract['contract_number']])) ?>">پرداخت‌ها</a>
            <a class="link-only" href="<?= e(url('contracts/booklet/' . (int) $contract['id'])) ?>" target="_blank">قرارداد</a>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
