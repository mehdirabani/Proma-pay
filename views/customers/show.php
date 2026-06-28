<div class="proma-customer-summary">
  <section class="card proma-customer-card">
    <div class="card-body">
      <div class="proma-customer-avatar"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></div>
      <h4><?= e($customer['full_name']) ?></h4>
      <p><?= to_persian_digits($customer['mobile']) ?> · <?= to_persian_digits($customer['national_id']) ?></p>
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
  <div class="card-header"><h2>قراردادها</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>شماره قرارداد</th><th>مبلغ</th><th>سود ماهانه</th><th>نوع سود</th><th>وضعیت</th><th>دفترچه</th></tr></thead>
      <tbody>
      <?php foreach ($contracts as $contract): ?>
        <tr>
          <td><?= e($contract['contract_number']) ?></td>
          <td><?= money_toman($contract['principal_amount']) ?></td>
          <td><?= percent_label($contract['monthly_interest_rate']) ?></td>
          <td><?= $contract['interest_type'] === 'compound' ? 'مرکب ماهانه' : 'ساده ماهانه' ?></td>
          <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
          <td><a class="btn small secondary" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ دفترچه</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="6" class="empty">قراردادی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
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
            <p>قسط <?= to_persian_digits($payment['installment_number']) ?> قرارداد <?= e($payment['contract_number']) ?></p>
          </div>
          <time><?= e(jdate($payment['paid_at'] ?: $payment['created_at'])) ?></time>
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
      <thead><tr><th>تاریخ</th><th>قرارداد</th><th>قسط</th><th>مبلغ</th><th>روش</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <tr>
          <td><?= e(jdate($payment['paid_at'])) ?></td>
          <td><?= e($payment['contract_number']) ?></td>
          <td><?= to_persian_digits($payment['installment_number']) ?></td>
          <td><?= money_toman($payment['amount']) ?></td>
          <td><?= e(payment_method_label($payment['method'])) ?></td>
          <td><span class="badge <?= e(badge_class($payment['status'])) ?>"><?= e(status_label($payment['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="6" class="empty">پرداختی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
