<?php
$metrics = $metrics ?? [];
$contracts = $contracts ?? [];
$installments = $installments ?? [];
$payments = $payments ?? [];
$openInstallments = array_values(array_filter($installments, static fn ($item) => !in_array(($item['status'] ?? ''), ['paid', 'cancelled'], true)));
usort($openInstallments, static fn ($left, $right) => strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? '')));
$nextInstallment = $openInstallments[0] ?? null;
$medals = $medals ?? [];
$loyaltyProfile = $loyaltyProfile ?? ['points' => 0, 'tier' => null, 'next_tier' => null, 'progress' => 0, 'medals' => []];
$socialLinks = $socialLinks ?? [];
$ecommerceOrders = $ecommerceOrders ?? [];
$givenGuarantees = $givenGuarantees ?? [];
$receivedGuarantees = $receivedGuarantees ?? [];
$banners = $banners ?? [];
$customerWidgets = $customerWidgets ?? [];
$ecommerceEnabled = ecommerce_is_enabled();
$projectedPenaltyMessage = '';
foreach ($installments as $installment) {
  if (!empty($installment['show_projected_legal_penalty'])) {
    $projectedPenaltyMessage = (string) ($installment['projected_legal_penalty_customer_message'] ?? '');
    break;
  }
}
?>
<div class="row widget-grid proma-role-dashboard proma-role-dashboard--customer v7-customer-dashboard">
  <div class="col-12">
    <section class="card proma-role-hero proma-customer-balance v7-customer-hero">
      <div class="card-body">
        <div class="v7-customer-hero__main">
          <div class="v7-customer-hero__copy">
            <span class="proma-eyebrow">داشبورد مشتری</span>
            <h4>سلام، <?= e(Auth::user()['full_name'] ?? 'مشتری') ?></h4>
            <p>وضعیت قراردادها و پرداخت‌های شما در یک نگاه</p>
          </div>
          <div class="v7-customer-balance-total">
            <small>مانده اقساط قابل پرداخت</small>
            <strong class="proma-balance-amount"><?= money_toman($metrics['payable'] ?? 0) ?></strong>
            <span>مجموع مبلغ اقساط باز</span>
          </div>
          <div class="proma-role-hero-actions">
            <a class="btn success" href="<?= e(url('installments/panel')) ?>"><i data-feather="credit-card"></i> پرداخت قسط</a>
            <a class="btn secondary" href="<?= e(url('portal/history')) ?>"><i data-feather="shopping-bag"></i> سوابق خرید</a>
            <?php if ($ecommerceEnabled): ?><a class="btn secondary" href="<?= e(url('ecommerce/shop')) ?>"><i data-feather="shopping-bag"></i> فروشگاه</a><?php endif; ?>
          </div>
        </div>
        <aside class="v7-customer-next-due">
          <span class="v7-role-section-mark is-blue"><i data-feather="calendar"></i></span>
          <?php if ($nextInstallment): ?>
            <div><small>نزدیک‌ترین سررسید</small><strong><?= e(jdate($nextInstallment['due_date'] ?? '')) ?></strong><span>قسط <?= to_persian_digits($nextInstallment['installment_number'] ?? '—') ?></span></div>
            <b><?= money_toman($nextInstallment['payable'] ?? $nextInstallment['base_amount'] ?? 0) ?></b>
          <?php else: ?>
            <div><small>برنامه پرداخت</small><strong>سررسید بازی ندارید</strong><span>در حال حاضر قسط باز ثبت نشده است.</span></div>
          <?php endif; ?>
        </aside>
      </div>
    </section>
  </div>

  <?php foreach ([
      ['قراردادها', $metrics['contracts'] ?? 0, 'قرارداد ثبت‌شده', 'file-text', 'violet'],
      ['اقساط باز', $metrics['open_installments'] ?? 0, 'در انتظار پرداخت', 'calendar', 'blue'],
      ['اقساط معوق', $metrics['overdue'] ?? 0, 'نیازمند توجه', 'alert-triangle', 'rose'],
      ['قابل پرداخت', money_toman($metrics['payable'] ?? 0), 'جمع اقساط باز', 'credit-card', 'teal'],
  ] as $item): ?>
    <div class="col-xxl-3 col-md-6 proma-dashboard-metric">
      <article class="card proma-role-kpi proma-role-kpi--<?= e($item[4] ?? 'violet') ?>">
        <div class="card-body">
          <span class="proma-role-kpi-icon"><i data-feather="<?= e($item[3]) ?>"></i></span>
          <div><small><?= e($item[0]) ?></small><strong><?= is_numeric($item[1]) ? to_persian_digits($item[1]) : $item[1] ?></strong><em><?= e($item[2]) ?></em></div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <div class="col-xxl-6 col-xl-12">
    <section class="card proma-role-panel v7-customer-priority-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5><i data-feather="calendar"></i> قسط بعدی</h5><a class="link-only" href="<?= e(url('installments/panel')) ?>">برنامه کامل</a></div></div>
      <div class="card-body pt-0">
        <?php if ($nextInstallment): ?>
          <div class="v7-customer-due-card"><div><small>قرارداد <?= e($nextInstallment['contract_number'] ?? '—') ?></small><strong>قسط <?= to_persian_digits($nextInstallment['installment_number'] ?? '—') ?></strong><span>سررسید <?= e(jdate($nextInstallment['due_date'] ?? '')) ?></span></div><b><?= money_toman($nextInstallment['payable'] ?? $nextInstallment['base_amount'] ?? 0) ?></b><a class="btn primary" href="<?= e(url('installments/panel')) ?>">مشاهده و پرداخت</a></div>
        <?php else: ?><div class="v7-role-empty"><i data-feather="check-circle"></i><strong>همه‌چیز به‌روز است</strong><span>قسط باز یا معوقی برای پرداخت ندارید.</span></div><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-xxl-6 col-xl-12">
    <section class="card proma-role-panel v7-customer-priority-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5><i data-feather="credit-card"></i> پرداخت‌های اخیر</h5><a class="link-only" href="<?= e(url('portal/history')) ?>">تاریخچه کامل</a></div></div>
      <div class="card-body pt-0"><div class="proma-role-list">
        <?php foreach (array_slice($payments, 0, 5) as $payment): ?><div class="proma-role-list-item"><span><strong><?= e($payment['contract_number'] ?? 'پرداخت ثبت‌شده') ?></strong><small><?= !empty($payment['installment_number']) ? 'قسط ' . to_persian_digits($payment['installment_number']) . ' · ' : '' ?><?= e(jdate($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? '')) ?></small></span><span class="badge badge-light-success"><?= money_toman($payment['amount'] ?? 0) ?></span></div><?php endforeach; ?>
        <?php if (!$payments): ?><div class="v7-role-empty"><i data-feather="credit-card"></i><strong>پرداختی ثبت نشده است</strong><span>پس از پرداخت، رسید در این بخش نمایش داده می‌شود.</span></div><?php endif; ?>
      </div></div>
    </section>
  </div>

  <?php foreach ($customerWidgets as $widget): if (!is_array($widget)) continue; ?>
    <div class="col-12">
      <section class="card proma-role-panel">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <span class="proma-role-kpi-icon"><i data-feather="<?= e($widget['icon'] ?? 'layers') ?>"></i></span>
            <div><h5 class="mb-1"><?= e($widget['title'] ?? '') ?></h5><p class="mb-0 f-light"><?= e($widget['description'] ?? '') ?></p></div>
          </div>
          <a class="btn primary" href="<?= e(url($widget['route'] ?? 'dashboard')) ?>"><?= e($widget['action_label'] ?? 'مشاهده') ?></a>
        </div>
      </section>
    </div>
  <?php endforeach; ?>

  <?php if ($banners): ?>
    <div class="col-12"><?php require __DIR__ . '/partials/customer-banners.php'; ?></div>
  <?php endif; ?>

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
          <table class="table table-bordernone proma-v2-data-table proma-customer-contract-table">
            <thead><tr><th scope="col">شماره قرارداد</th><th scope="col">قابل تقسیط</th><th scope="col">تعداد قسط</th><th scope="col">شروع</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($contracts, 0, 10) as $contract): ?>
              <tr>
                <td data-label="شماره قرارداد"><?= e($contract['contract_number']) ?></td>
                <td data-label="قابل تقسیط"><?= money_toman(max(0, normalize_money($contract['principal_amount']) - normalize_money($contract['down_payment_amount'] ?? 0))) ?></td>
                <td data-label="تعداد قسط"><?= to_persian_digits($contract['months'] ?? 0) ?></td>
                <td data-label="شروع قرارداد"><?= e(jdate($contract['start_date'])) ?></td>
                <td data-label="وضعیت"><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
                <td class="actions" data-label="عملیات">
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
          <table class="table table-bordernone proma-v2-data-table proma-customer-schedule-table">
            <thead><tr><th scope="col">قسط</th><th scope="col">سررسید</th><th scope="col">مبلغ پایه</th><th scope="col">جریمه</th><th scope="col">قابل پرداخت</th><th scope="col">وضعیت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($installments, 0, 8) as $item): ?>
              <tr>
                <td data-label="قسط"><?= to_persian_digits($item['installment_number']) ?></td>
                <td data-label="سررسید"><?= e(jdate($item['due_date'])) ?></td>
                <td data-label="مبلغ پایه"><?= money_toman($item['base_amount']) ?></td>
                <td data-label="جریمه"><?= penalty_display_html($item) ?></td>
                <td data-label="قابل پرداخت"><?= money_toman($item['payable']) ?></td>
                <td data-label="وضعیت"><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
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
      <div class="card-header card-no-border"><div class="header-top"><h5>باشگاه امتیاز مشتریان</h5><a class="link-only" href="<?= e(url('profile')) ?>">پروفایل من</a></div></div>
      <div class="card-body pt-0">
        <div class="proma-customer-card-metrics">
          <span><strong><?= to_persian_digits($loyaltyProfile['points'] ?? 0) ?></strong><small>امتیاز من</small></span>
          <span><strong><?= e($loyaltyProfile['tier']['title'] ?? 'تازه‌وارد') ?></strong><small>سطح فعلی</small></span>
          <span><strong><?= to_persian_digits($loyaltyProfile['progress'] ?? 0) ?>٪</strong><small>تا سطح بعد</small></span>
        </div>
        <?php if (!empty($loyaltyProfile['next_tier'])): ?><p class="mt-3">برای رسیدن به سطح <?= e($loyaltyProfile['next_tier']['title']) ?>، <?= to_persian_digits(max(0, (int) $loyaltyProfile['next_tier']['min_points'] - (int) $loyaltyProfile['points'])) ?> امتیاز دیگر لازم است.</p><progress max="100" value="<?= max(0, min(100, (int) ($loyaltyProfile['progress'] ?? 0))) ?>" aria-label="پیشرفت تا سطح بعد"></progress><?php endif; ?>
        <div class="proma-role-list">
          <?php foreach (array_slice($loyaltyProfile['medals'] ?? [], 0, 6) as $medal): ?>
            <div class="proma-role-list-item" title="<?= e($medal['description'] ?? '') ?>"><span><strong><?= e($medal['medal_title'] ?? $medal['title']) ?></strong><small><?= e($medal['description'] ?? '') ?></small></span><span class="badge badge-light-warning"><?= to_persian_digits($medal['points'] ?? 0) ?> امتیاز</span></div>
          <?php endforeach; ?>
          <?php if (empty($loyaltyProfile['medals'])): ?><div class="empty">با انجام فعالیت‌های امتیازی، مدال‌های سطحی دریافت می‌کنید.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
