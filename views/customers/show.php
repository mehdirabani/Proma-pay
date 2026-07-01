<?php
$uniqueContracts = [];
foreach ($contracts as $contract) {
    $uniqueContracts[(int) $contract['id']] = $contract;
}
$contracts = array_values($uniqueContracts);
$medals = $medals ?? [];
?>
<div class="proma-customer-summary">
  <section class="card proma-customer-card">
    <div class="card-body">
      <div class="proma-customer-avatar"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></div>
      <h4><?= e($customer['full_name']) ?></h4>
      <p><?= to_persian_digits($customer['mobile']) ?> · <?= to_persian_digits($customer['national_id']) ?></p>
      <div class="proma-medal-row proma-medal-center">
        <?php foreach (array_slice($medals, 0, 4) as $medal): ?><span class="badge badge-light-warning"><?= e($medal['title']) ?></span><?php endforeach; ?>
        <?php if (!$medals): ?><span class="badge muted">بدون مدال</span><?php endif; ?>
      </div>
      <div class="proma-customer-stats">
        <span><strong><?= to_persian_digits(count($contracts)) ?></strong><small>قرارداد</small></span>
        <span><strong><?= to_persian_digits(count($installments)) ?></strong><small>قسط</small></span>
        <span><strong><?= to_persian_digits(count($payments)) ?></strong><small>پرداخت</small></span>
      </div>
    </div>
  </section>
  <section class="card proma-customer-card light">
    <div class="card-body">
      <h5>شاخص خوش‌حسابی</h5>
      <?php $paidCount = count(array_filter($installments, function ($item) { return ($item['status'] ?? '') === 'paid'; })); ?>
      <?php $score = count($installments) ? ceil(($paidCount / count($installments)) * 100) : 0; ?>
      <strong class="proma-score"><?= to_persian_digits($score) ?>٪</strong>
      <div class="progress sm-progress-bar"><div class="progress-bar bg-success" style="width: <?= e($score) ?>%"></div></div>
      <p class="f-light mb-0"><?= to_persian_digits($paidCount) ?> قسط پرداخت شده از <?= to_persian_digits(count($installments)) ?></p>
    </div>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>تاریخچه سفارشات و قراردادهای اخیر</h2>
      <a class="link-only" href="<?= e(url('contracts', ['q' => $customer['national_id']])) ?>">مدیریت قراردادهای مشتری</a>
    </div>
  </div>
  <div class="card-body pt-0">
    <div class="proma-order-history-list">
      <?php foreach ($contracts as $contract): ?>
        <?php $financed = max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0)); ?>
        <a class="proma-order-history-item" href="<?= e(url('contracts/show/' . $contract['id'])) ?>">
          <span class="proma-order-icon"><?= e(mb_substr($contract['contract_number'], 0, 1, 'UTF-8')) ?></span>
          <span>
            <strong><?= e($contract['contract_number']) ?></strong>
            <small><?= e(jdate($contract['start_date'])) ?> · <?= $contract['interest_type'] === 'compound' ? 'سود مرکب' : 'سود ساده' ?> · <?= to_persian_digits($contract['months']) ?> قسط</small>
            <span class="proma-order-badges">
              <em class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></em>
              <em class="badge badge-light-info">پیش‌پرداخت <?= money_toman($contract['down_payment_amount'] ?? 0) ?></em>
              <em class="badge badge-light-primary">قابل تقسیط <?= money_toman($financed) ?></em>
            </span>
          </span>
          <strong class="proma-order-amount"><?= money_toman($contract['principal_amount']) ?></strong>
        </a>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><div class="empty">قراردادی برای این مشتری ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h5>خط زمانی پرداخت‌های موفق</h5></div>
  <div class="card-body">
    <div class="proma-payment-timeline">
      <?php foreach ($paymentTimeline as $payment): ?>
        <div class="proma-timeline-item">
          <span class="proma-timeline-dot"></span>
          <div>
            <strong><?= money_toman($payment['amount']) ?></strong>
            <p><?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?><?= !empty($payment['installment_number']) ? ' · قسط ' . to_persian_digits($payment['installment_number']) : '' ?> · قرارداد <?= e($payment['contract_number']) ?></p>
          </div>
          <time><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></time>
        </div>
      <?php endforeach; ?>
      <?php if (!$paymentTimeline): ?><div class="empty">پرداخت موفقی برای این مشتری ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>نمای مالی پویا</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>قرارداد</th><th>قسط</th><th>سررسید</th><th>مبلغ پایه</th><th>جریمه امروز</th><th>پاداش امروز</th><th>قابل پرداخت</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($installments as $item): ?>
        <tr>
          <td><?= e($item['contract_number']) ?></td>
          <td><?= to_persian_digits($item['installment_number']) ?></td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= money_toman($item['base_amount']) ?></td>
          <td><?= money_toman($item['penalty']) ?></td>
          <td><?= money_toman($item['reward']) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$installments): ?><tr><td colspan="8" class="empty">قسطی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>سوابق پرداخت</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>تاریخ</th><th>قرارداد</th><th>قسط</th><th>نوع پرداخت</th><th>مبلغ</th><th>روش</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <tr>
          <td><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></td>
          <td><?= e($payment['contract_number']) ?></td>
          <td><?= $payment['installment_number'] ? to_persian_digits($payment['installment_number']) : '-' ?></td>
          <td><span class="badge badge-light-info"><?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?></span></td>
          <td><?= money_toman($payment['amount']) ?></td>
          <td><?= e(payment_method_label($payment['method'])) ?></td>
          <td><span class="badge <?= e(badge_class($payment['status'])) ?>"><?= e(status_label($payment['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="7" class="empty">پرداختی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
