<?php
$metrics = $metrics ?? [];
$contracts = $contracts ?? [];
$installments = $installments ?? [];
$medals = $medals ?? [];
$socialLinks = $socialLinks ?? [];
$ecommerceOrders = $ecommerceOrders ?? [];
$givenGuarantees = $givenGuarantees ?? [];
$receivedGuarantees = $receivedGuarantees ?? [];
$ecommerceEnabled = ecommerce_is_enabled();
$projectedPenaltyMessage = '';
foreach ($installments as $installment) {
  if (!empty($installment['show_projected_legal_penalty'])) {
    $projectedPenaltyMessage = (string) ($installment['projected_legal_penalty_customer_message'] ?? '');
    break;
  }
}
?>
<div class="row widget-grid proma-role-dashboard proma-role-dashboard--customer">
  <div class="col-12">
    <section class="card proma-role-hero">
      <div class="card-body">
        <div>
          <span class="badge badge-light-success">پیشخوان مشتری</span>
          <h4>سلام، <?= e(Auth::user()['full_name'] ?? 'مشتری') ?></h4>
          <p>قراردادها، برنامه اقساط، پرداخت‌ها و سوابق خرید شما در یک نمای مرتب و قابل پیگیری قرار دارد.</p>
        </div>
        <div class="proma-role-hero-actions">
          <a class="btn success" href="<?= e(url('installments/panel')) ?>"><i data-feather="credit-card"></i> پرداخت اقساط</a>
          <a class="btn secondary" href="<?= e(url('portal/history')) ?>"><i data-feather="clock"></i> سوابق خرید</a>
          <?php if ($ecommerceEnabled): ?><a class="btn secondary" href="<?= e(url('ecommerce/shop')) ?>"><i data-feather="shopping-bag"></i> فروشگاه</a><?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <?php foreach ([
      ['قراردادها', $metrics['contracts'] ?? 0, 'قرارداد ثبت‌شده', 'file-text'],
      ['اقساط باز', $metrics['open_installments'] ?? 0, 'در انتظار پرداخت', 'calendar'],
      ['اقساط معوق', $metrics['overdue'] ?? 0, 'نیازمند توجه', 'alert-triangle'],
      ['قابل پرداخت', money_toman($metrics['payable'] ?? 0), 'جمع اقساط باز', 'credit-card'],
  ] as $item): ?>
    <div class="col-xxl-3 col-md-6">
      <article class="card proma-role-kpi">
        <div class="card-body">
          <span class="proma-role-kpi-icon"><i data-feather="<?= e($item[3]) ?>"></i></span>
          <div><small><?= e($item[0]) ?></small><strong><?= is_numeric($item[1]) ? to_persian_digits($item[1]) : $item[1] ?></strong><em><?= e($item[2]) ?></em></div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <?php if ($socialLinks): ?>
    <div class="col-12">
      <section class="card proma-role-panel">
        <div class="card-header card-no-border"><div class="header-top"><h5>ارتباط با سامانه</h5><span class="f-light">لینک‌های رسمی و پشتیبانی</span></div></div>
        <div class="card-body pt-0">
          <div class="proma-social-card-grid">
            <?php foreach ($socialLinks as $social): ?>
              <a class="proma-social-card proma-social-card--<?= e($social['class']) ?>" href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer">
                <span class="proma-social-card__icon"><i data-feather="<?= e($social['icon']) ?>"></i></span>
                <span><strong><?= e($social['label']) ?></strong><small><?= e(parse_url($social['url'], PHP_URL_HOST) ?: $social['url']) ?></small></span>
                <em>مشاهده</em>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <div class="col-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>قراردادهای من</h5><a class="link-only" href="<?= e(url('portal/contracts')) ?>">مشاهده همه</a></div></div>
      <div class="card-body pt-0">
        <?php if ($projectedPenaltyMessage !== ''): ?><div class="notice info mb-3"><strong>راهنمای جریمه حقوقی:</strong> <?= e($projectedPenaltyMessage) ?></div><?php endif; ?>
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>شماره قرارداد</th><th>قابل تقسیط</th><th>تعداد قسط</th><th>شروع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($contracts, 0, 10) as $contract): ?>
              <tr>
                <td><?= e($contract['contract_number']) ?></td>
                <td><?= money_toman(max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0))) ?></td>
                <td><?= to_persian_digits($contract['months'] ?? 0) ?></td>
                <td><?= e(jdate($contract['start_date'])) ?></td>
                <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
                <td class="actions">
                  <a class="btn small secondary" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>">جزئیات</a>
                  <a class="btn small success" href="<?= e(url('contracts/printDocument/' . (int) $contract['id'])) ?>" target="_blank" rel="noopener"><i data-feather="printer"></i> چاپ</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="6" class="text-center f-light">قراردادی برای شما ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-7 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>برنامه پرداخت</h5><a class="link-only" href="<?= e(url('installments/panel')) ?>">همه اقساط</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ پایه</th><th>جریمه</th><th>قابل پرداخت</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($installments, 0, 8) as $item): ?>
              <tr>
                <td><?= to_persian_digits($item['installment_number']) ?></td>
                <td><?= e(jdate($item['due_date'])) ?></td>
                <td><?= money_toman($item['base_amount']) ?></td>
                <td><?= penalty_display_html($item) ?></td>
                <td><?= money_toman($item['payable']) ?></td>
                <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$installments): ?><tr><td colspan="6" class="text-center f-light">قسطی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <?php if ($ecommerceEnabled): ?>
    <div class="col-xxl-5 col-xl-12">
      <section class="card proma-role-panel">
        <div class="card-header card-no-border"><div class="header-top"><h5>سفارش‌های فروشگاه</h5><a class="link-only" href="<?= e(url('ecommerce/myOrders')) ?>">همه سفارش‌ها</a></div></div>
        <div class="card-body pt-0">
          <div class="proma-role-list">
            <?php foreach (array_slice($ecommerceOrders, 0, 6) as $order): ?>
              <a class="proma-role-list-item" href="<?= e(url('ecommerce/payment/' . (int) $order['id'])) ?>">
                <span><strong><?= e($order['order_number']) ?></strong><small><?= to_persian_digits($order['quantity_total'] ?? 0) ?> کالا · <?= e(jdatetime($order['created_at'] ?? '')) ?></small></span>
                <span class="badge <?= e(badge_class($order['order_status'] ?? 'pending')) ?>"><?= e(status_label($order['order_status'] ?? 'pending')) ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (!$ecommerceOrders): ?><div class="empty">هنوز سفارشی در فروشگاه ثبت نکرده‌اید.</div><?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <div class="col-xxl-6 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>ضمانت‌ها</h5><a class="link-only" href="<?= e(url('portal/guaranteed')) ?>">مشاهده همه</a></div></div>
      <div class="card-body pt-0">
        <div class="proma-role-list">
          <?php foreach (array_slice($givenGuarantees, 0, 5) as $contract): ?>
            <div class="proma-role-list-item"><span><strong><?= e($contract['contract_number']) ?></strong><small>ضامن مشتری <?= e($contract['customer_name']) ?></small></span><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></div>
          <?php endforeach; ?>
          <?php foreach (array_slice($receivedGuarantees, 0, 5) as $guarantor): ?>
            <div class="proma-role-list-item"><span><strong><?= e($guarantor['contract_number']) ?></strong><small>ضامن: <?= e($guarantor['full_name']) ?></small></span><i data-feather="shield"></i></div>
          <?php endforeach; ?>
          <?php if (!$givenGuarantees && !$receivedGuarantees): ?><div class="empty">ضمانتی برای نمایش ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-6 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>نشان‌های وفاداری</h5><a class="link-only" href="<?= e(url('profile')) ?>">پروفایل من</a></div></div>
      <div class="card-body pt-0">
        <div class="proma-role-list">
          <?php foreach (array_slice($medals, 0, 6) as $medal): ?>
            <div class="proma-role-list-item" title="<?= e($medal['how_to_earn'] ?? $medal['description'] ?? '') ?>"><span><strong><?= e($medal['title']) ?></strong><small><?= e($medal['description'] ?? '') ?></small></span><span class="badge badge-light-warning"><?= to_persian_digits($medal['points']) ?> امتیاز</span></div>
          <?php endforeach; ?>
          <?php if (!$medals): ?><div class="empty">هنوز نشانی برای شما ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
