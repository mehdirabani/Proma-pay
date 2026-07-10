<?php
$adminName = Auth::user()['full_name'] ?? 'مدیر';
$todayLabel = jdate(date('Y-m-d'));
$reviewCount = (int) ($kpis['pending_reviews'] ?? 0);
$quickActions = [
    ['label' => 'ثبت قرارداد', 'url' => url('contracts'), 'icon' => 'file-plus', 'tone' => 'primary'],
    ['label' => 'پیگیری معوقات', 'url' => url('overdue'), 'icon' => 'phone-call', 'tone' => 'danger'],
    ['label' => 'بررسی رسیدها', 'url' => url('review', ['tab' => 'receipts']), 'icon' => 'check-square', 'tone' => 'success'],
    ['label' => 'تنظیمات', 'url' => url('settings'), 'icon' => 'settings', 'tone' => 'muted'],
];
$mainKpis = [
    ['label' => 'وصول ماه جاری', 'value' => money_toman($kpis['received'] ?? 0), 'meta' => 'از ' . money_toman($kpis['due_month'] ?? 0) . ' سررسید', 'tone' => 'success', 'icon' => 'trending-up'],
    ['label' => 'مانده وصول نشده', 'value' => money_toman($kpis['outstanding'] ?? 0), 'meta' => 'کل اقساط پرداخت نشده', 'tone' => 'warning', 'icon' => 'credit-card'],
    ['label' => 'اقساط معوق', 'value' => to_persian_digits($kpis['overdue'] ?? 0), 'meta' => money_toman($kpis['overdue_amount'] ?? 0), 'tone' => 'danger', 'icon' => 'alert-triangle'],
    ['label' => 'منتظر بررسی', 'value' => to_persian_digits($reviewCount), 'meta' => to_persian_digits($kpis['pending_receipts'] ?? 0) . ' رسید، ' . to_persian_digits($kpis['pending_identity'] ?? 0) . ' مدرک', 'tone' => 'info', 'icon' => 'inbox'],
];
$healthCards = [
    ['label' => 'نرخ وصول ماه', 'value' => (float) ($kpis['collection_rate'] ?? 0), 'hint' => money_toman($kpis['received'] ?? 0) . ' دریافتی'],
    ['label' => 'سهم معوق از مانده', 'value' => (float) ($kpis['overdue_share'] ?? 0), 'hint' => money_toman($kpis['overdue_amount'] ?? 0) . ' معوق'],
];
?>

<div class="row widget-grid proma-dashboard proma-admin-dashboard">
  <div class="col-12">
    <section class="card proma-admin-hero">
      <div class="card-body">
        <div class="proma-admin-hero-main">
          <span class="badge badge-light-primary"><?= e($todayLabel) ?></span>
          <h4>سلام، <?= e($adminName) ?></h4>
          <p>نمای خلاصه مدیریت برای تصمیم‌گیری سریع درباره وصول، معوقات، رسیدهای در انتظار بررسی و پرونده‌های پرریسک.</p>
        </div>
        <div class="proma-admin-actions">
          <?php foreach ($quickActions as $action): ?>
            <a class="proma-admin-action proma-admin-action--<?= e($action['tone']) ?>" href="<?= e($action['url']) ?>">
              <i data-feather="<?= e($action['icon']) ?>"></i>
              <span><?= e($action['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <?php foreach ($mainKpis as $card): ?>
    <div class="col-xxl-3 col-md-6">
      <article class="card proma-admin-kpi proma-admin-kpi--<?= e($card['tone']) ?>">
        <div class="card-body">
          <span class="proma-admin-kpi-icon"><i data-feather="<?= e($card['icon']) ?>"></i></span>
          <div>
            <small><?= e($card['label']) ?></small>
            <strong><?= $card['value'] ?></strong>
            <em><?= $card['meta'] ?></em>
          </div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <div class="col-xxl-8 col-xl-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>جریان وصول ۶ ماه اخیر</h5>
          <a class="link-only" href="<?= e(url('payments')) ?>">دفتر پرداخت‌ها</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-chart proma-chart-lg">
          <canvas data-chart="line" data-title="وصول" data-labels='<?= e(json_encode($chartLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($chartData)) ?>'></canvas>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-xl-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>سلامت وصول</h5>
          <a class="link-only" href="<?= e(url('installments')) ?>">مدیریت اقساط</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-admin-health-list">
          <?php foreach ($healthCards as $item): ?>
            <div class="proma-admin-health">
              <div>
                <strong><?= e($item['label']) ?></strong>
                <small><?= $item['hint'] ?></small>
              </div>
              <span><?= to_persian_digits($item['value']) ?>٪</span>
              <div class="progress sm-progress-bar">
                <div class="progress-bar <?= $item['value'] > 65 ? 'bg-success' : ($item['value'] > 35 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= e(min(100, max(0, $item['value']))) ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="proma-chart proma-chart-sm">
          <canvas data-chart="bar" data-title="سن معوقات" data-labels='<?= e(json_encode($overdueBuckets['labels'], JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($overdueBuckets['data'])) ?>' data-colors='<?= e(json_encode(['#16c7f9', '#ffaa05', '#fc4438', '#7366ff'])) ?>'></canvas>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-5 col-lg-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>اقدام‌های فوری</h5>
          <a class="link-only" href="<?= e(url('overdue')) ?>">همه معوقات</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-admin-task-list">
          <a href="<?= e(url('overdue', ['bucket' => 'today'])) ?>">
            <span><i data-feather="calendar"></i> سررسید امروز</span>
            <strong><?= to_persian_digits($kpis['due_today'] ?? 0) ?></strong>
          </a>
          <a href="<?= e(url('overdue')) ?>">
            <span><i data-feather="alert-circle"></i> اقساط معوق</span>
            <strong><?= to_persian_digits($kpis['overdue'] ?? 0) ?></strong>
          </a>
          <a href="<?= e(url('review', ['tab' => 'receipts'])) ?>">
            <span><i data-feather="image"></i> رسید کارت به کارت</span>
            <strong><?= to_persian_digits($kpis['pending_receipts'] ?? 0) ?></strong>
          </a>
          <a href="<?= e(url('review', ['tab' => 'identity'])) ?>">
            <span><i data-feather="user-check"></i> مدارک هویتی</span>
            <strong><?= to_persian_digits($kpis['pending_identity'] ?? 0) ?></strong>
          </a>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-7 col-lg-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>سررسیدهای پیش‌رو</h5>
          <a class="link-only" href="<?= e(url('installments')) ?>">لیست اقساط</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>مشتری</th><th>قرارداد</th><th>سررسید</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $item): ?>
              <tr>
                <td><h6 class="mb-0"><?= e($item['customer_name']) ?></h6><span class="f-light"><?= e($item['mobile']) ?></span></td>
                <td><?= e($item['contract_number']) ?></td>
                <td><?= e(jdate($item['due_date'])) ?></td>
                <td><?= money_toman($item['base_amount']) ?></td>
                <td><span class="badge badge-light-<?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?><tr><td colspan="5" class="text-center f-light">تا دو هفته آینده سررسید بازی وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-lg-6">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>پرریسک‌ترین مشتریان</h5>
          <a class="link-only" href="<?= e(url('overdue')) ?>">پیگیری</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-risk-list">
          <?php foreach ($riskCustomers as $customer): ?>
            <a class="proma-risk-item" href="<?= e(url('customers/show/' . $customer['id'])) ?>">
              <span class="proma-risk-avatar danger"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></span>
              <span>
                <strong><?= e($customer['full_name']) ?></strong>
                <small><?= e($customer['mobile']) ?> · <?= to_persian_digits($customer['overdue_count']) ?> قسط معوق</small>
              </span>
              <em><?= money_toman($customer['debt']) ?></em>
            </a>
          <?php endforeach; ?>
          <?php if (!$riskCustomers): ?><div class="empty">مشتری پرریسکی در حال حاضر ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-lg-6">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>بار پیگیری اپراتورها</h5>
          <a class="link-only" href="<?= e(url('users', ['role' => 'operator'])) ?>">اپراتورها</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-operator-list">
          <?php foreach ($operatorLoad as $operator): ?>
            <div class="proma-operator-item">
              <div>
                <h6 class="mb-1"><?= e($operator['full_name']) ?></h6>
                <span class="f-light"><?= to_persian_digits($operator['contracts']) ?> قرارداد اختصاص‌یافته</span>
              </div>
              <span class="badge badge-light-warning"><?= to_persian_digits($operator['overdue_count']) ?> معوق</span>
            </div>
          <?php endforeach; ?>
          <?php if (!$operatorLoad): ?><div class="empty">اپراتوری برای نمایش ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-lg-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>وضعیت قرارداد و اقساط</h5>
          <a class="link-only" href="<?= e(url('contracts')) ?>">قراردادها</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-admin-status-grid">
          <div><small>مشتریان فعال</small><strong><?= to_persian_digits($kpis['customers'] ?? 0) ?></strong></div>
          <div><small>قرارداد فعال</small><strong><?= to_persian_digits($kpis['contracts'] ?? 0) ?></strong></div>
          <div><small>کل قراردادها</small><strong><?= to_persian_digits($kpis['contracts_total'] ?? 0) ?></strong></div>
          <div><small>پرونده حقوقی باز</small><strong><?= to_persian_digits($kpis['legal_open'] ?? 0) ?></strong></div>
        </div>
        <div class="proma-chart proma-chart-sm">
          <canvas data-chart="doughnut" data-title="اقساط" data-labels='<?= e(json_encode($installmentStatus['labels'], JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($installmentStatus['data'])) ?>' data-colors='<?= e(json_encode($installmentStatus['colors'])) ?>'></canvas>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-6 col-lg-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>پرداخت‌های اخیر</h5>
          <a class="link-only" href="<?= e(url('payments')) ?>">گزارش پرداخت‌ها</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>مشتری</th><th>نوع</th><th>مبلغ</th><th>روش</th><th>تاریخ</th></tr></thead>
            <tbody>
            <?php foreach ($recentPayments as $payment): ?>
              <tr>
                <td><h6 class="mb-0"><?= e($payment['customer_name']) ?></h6><span class="f-light"><?= e($payment['contract_number']) ?></span></td>
                <td><?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?><?= !empty($payment['installment_number']) ? ' · ' . to_persian_digits($payment['installment_number']) : '' ?></td>
                <td><?= money_toman($payment['amount']) ?></td>
                <td><span class="badge badge-light-info"><?= e(payment_method_label($payment['method'])) ?></span></td>
                <td><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentPayments): ?><tr><td colspan="5" class="text-center f-light">هنوز پرداخت تاییدشده‌ای ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-6 col-lg-12">
    <section class="card proma-admin-panel">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>اقساط نیازمند تماس</h5>
          <a class="link-only" href="<?= e(url('overdue')) ?>">صف پیگیری</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>مشتری</th><th>قرارداد</th><th>سررسید</th><th>قابل پرداخت</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $item): ?>
              <tr>
                <td><h6 class="mb-0"><?= e($item['customer_name']) ?></h6><span class="f-light"><?= e($item['mobile'] ?? '') ?></span></td>
                <td><?= e($item['contract_number']) ?></td>
                <td><?= e(jdate($item['due_date'])) ?></td>
                <td><?= money_toman($item['payable']) ?></td>
                <td><span class="badge badge-light-<?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$overdue): ?><tr><td colspan="5" class="text-center f-light">موردی برای پیگیری فوری وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>
