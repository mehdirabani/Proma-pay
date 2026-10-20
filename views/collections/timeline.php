<?php $data = $timeline ?? []; $contract = $data['contract'] ?? []; $summary = $data['financial_summary'] ?? []; $timelineLabels = ['principal' => 'اصل اقساط', 'normal_late_fee' => 'جریمه عادی', 'legal_late_fee' => 'جریمه حقوقی', 'legal_cost' => 'هزینه حقوقی', 'down_payment' => 'پیش‌پرداخت', 'unallocated' => 'وجه تخصیص‌نیافته', 'discount' => 'بخشش یا تخفیف', 'write_off' => 'حذف بدهی', 'charge' => 'ثبت بدهی', 'payment' => 'پرداخت', 'reversal' => 'اصلاحیه']; ?>
<section class="card">
  <div class="card-header"><div class="header-top"><div><h2>خط زمانی قرارداد <?= e($contract['contract_number'] ?? '') ?></h2><p><?= e($contract['customer_name'] ?? '') ?> · <?= e($contract['mobile'] ?? '') ?></p></div><a class="btn secondary" href="<?= e(url('collections')) ?>">بازگشت به مرکز وصول</a></div></div>
  <div class="card-body">
    <div class="kpi-grid">
      <?php foreach ([['مانده قابل وصول', $summary['final_collectable_amount'] ?? 0], ['اصل اقساط', $summary['unpaid_installments_total'] ?? 0], ['جریمه دیرکرد', $summary['late_penalty_total'] ?? 0], ['هزینه حقوقی', $summary['legal_costs_total'] ?? 0]] as [$label,$amount]): ?>
        <article class="kpi-card"><span><?= e($label) ?></span><strong><?= to_persian_digits(number_format(normalize_money($amount))) ?> <small>تومان</small></strong></article>
      <?php endforeach; ?>
    </div>
    <div class="collection-timeline">
      <?php foreach (($data['items'] ?? []) as $event): ?>
        <?php $title = (string) ($event['title'] ?? ''); foreach ($timelineLabels as $key => $label) $title = str_replace($key, $label, $title); ?>
        <article class="collection-timeline-item"><time><?= e(jdatetime($event['at'] ?? '')) ?></time><div><strong><?= e($title) ?></strong><p><?= e($event['detail'] ?? '') ?></p><?php if (!empty($event['url']) && (Auth::role() === 'admin' || strpos((string) $event['url'], 'legal/') !== 0)): ?><a href="<?= e(url($event['url'])) ?>">مشاهده جزئیات</a><?php endif; ?></div><span class="badge info"><?= e($event['status'] ?? '') ?></span></article>
      <?php endforeach; ?>
      <?php if (empty($data['items'])): ?><div class="empty">رویدادی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>
