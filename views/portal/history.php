<?php
$summary = $summary ?? [];
$contracts = $contracts ?? [];
$payments = $payments ?? [];
$timeline = $timeline ?? [];
$lastPurchase = $summary['last_purchase'] ?? null;
?>

<div class="row widget-grid">
  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round primary"><div class="bg-round"><i data-feather="shopping-bag"></i></div></div>
          <div><h4><?= to_persian_digits($summary['purchases'] ?? 0) ?></h4><span class="f-light">خرید ثبت‌شده</span></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round success"><div class="bg-round"><i data-feather="file-text"></i></div></div>
          <div><h4><?= to_persian_digits($summary['contracts'] ?? 0) ?></h4><span class="f-light">قرارداد</span></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round warning"><div class="bg-round"><i data-feather="clock"></i></div></div>
          <div><h4><?= $lastPurchase ? e(jdate($lastPurchase['start_date'])) : '-' ?></h4><span class="f-light">آخرین خرید</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>سوابق سفارش‌ها</h5></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>شماره</th><th>تاریخ</th><th>مبلغ قرارداد</th><th>پیش‌پرداخت</th><th>وضعیت</th><th>پرونده</th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $contract): ?>
              <tr>
                <td><?= e($contract['contract_number']) ?></td>
                <td><?= e(jdate($contract['start_date'])) ?></td>
                <td><?= money_toman($contract['principal_amount']) ?></td>
                <td><?= money_toman($contract['down_payment_amount'] ?? 0) ?></td>
                <td><span class="badge badge-light-<?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
                <td><a class="btn small secondary" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">مشاهده</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="6" class="text-center f-light">سوابق خریدی برای شما ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header card-no-border"><h5>وضعیت قراردادها</h5></div>
      <div class="card-body pt-0">
        <div class="proma-status-stack">
          <?php foreach (($summary['statuses'] ?? []) as $status => $count): ?>
            <span><strong><?= e(status_label($status)) ?></strong><b><?= to_persian_digits($count) ?></b></span>
          <?php endforeach; ?>
          <?php if (empty($summary['statuses'])): ?><div class="empty">داده‌ای برای نمایش وجود ندارد.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-xl-6">
    <div class="card">
      <div class="card-header card-no-border"><h5>خط زمانی پرداخت</h5></div>
      <div class="card-body pt-0">
        <ul class="proma-timeline-list">
          <?php foreach ($payments as $payment): ?>
            <li>
              <span class="badge badge-light-success"><?= e(payment_method_label($payment['method'])) ?></span>
              <div>
                <strong><?= money_toman($payment['amount']) ?></strong>
                <small><?= e($payment['contract_number']) ?><?= !empty($payment['installment_number']) ? ' / قسط ' . to_persian_digits($payment['installment_number']) : '' ?></small>
              </div>
              <time><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></time>
            </li>
          <?php endforeach; ?>
          <?php if (!$payments): ?><li class="empty">پرداختی ثبت نشده است.</li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card">
      <div class="card-header card-no-border"><h5>برنامه اقساط</h5></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>قسط</th><th>سررسید</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($timeline, 0, 12) as $item): ?>
              <tr>
                <td><?= e($item['contract_number']) ?></td>
                <td><?= to_persian_digits($item['installment_number']) ?></td>
                <td><?= e(jdate($item['due_date'])) ?></td>
                <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$timeline): ?><tr><td colspan="4" class="text-center f-light">قسطی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
