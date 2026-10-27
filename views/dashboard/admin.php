<?php
$adminName = Auth::user()['full_name'] ?? 'مدیر';
$todayLabel = jdate(date('Y-m-d'));
$reviewCount = (int) ($kpis['pending_reviews'] ?? 0);
$quickActions = [
    ['label' => 'ثبت قرارداد', 'url' => url('contracts', ['open' => 'create-contract']), 'icon' => 'file-plus', 'tone' => 'primary'],
    ['label' => 'پیگیری معوقات', 'url' => url('overdue'), 'icon' => 'phone-call', 'tone' => 'danger'],
    ['label' => 'بررسی رسیدها', 'url' => url('review', ['tab' => 'receipts']), 'icon' => 'check-square', 'tone' => 'success'],
    ['label' => 'تحلیل پرتفوی', 'url' => url('risk/portfolio'), 'icon' => 'activity', 'tone' => 'info'],
    ['label' => 'تنظیمات', 'url' => url('settings'), 'icon' => 'settings', 'tone' => 'muted'],
];
if (class_exists('PluginManager') && PluginManager::isActive('proma-accounting') && PluginManager::can('plugin.proma-accounting.view')) {
    $quickActions[] = ['label' => 'حسابداری', 'url' => url('plugin/accounting/dashboard'), 'icon' => 'book-open', 'tone' => 'info'];
}
if (class_exists('PluginManager') && PluginManager::isActive('proma-repair') && PluginManager::can('plugin.proma-repair.view_orders')) {
    $quickActions[] = ['label' => 'تعمیرات', 'url' => url('plugin/repair/dashboard'), 'icon' => 'package', 'tone' => 'warning'];
}
$mainKpis = [
    ['label' => 'مشتریان فعال', 'value' => to_persian_digits($kpis['customers'] ?? 0), 'meta' => 'پرونده مشتری', 'tone' => 'info', 'icon' => 'users'],
    ['label' => 'قرارداد فعال', 'value' => to_persian_digits($kpis['contracts'] ?? 0), 'meta' => 'از ' . to_persian_digits($kpis['contracts_total'] ?? 0) . ' قرارداد', 'tone' => 'primary', 'icon' => 'file-text'],
    ['label' => 'وصول واقعی اقساط در ماه', 'value' => money_toman($kpis['received'] ?? 0), 'meta' => 'تعهد سررسیدشده تا امروز: ' . money_toman($kpis['due_month'] ?? 0) . ' · مستقل از مجموعه دریافت‌ها', 'tone' => 'success', 'icon' => 'trending-up'],
    ['label' => 'اصل مانده اقساط', 'value' => money_toman($kpis['outstanding'] ?? 0), 'meta' => 'از وضعیت مالی canonical اقساط', 'tone' => 'warning', 'icon' => 'credit-card'],
    ['label' => 'اقساط معوق', 'value' => to_persian_digits($kpis['overdue'] ?? 0), 'meta' => 'اصل معوق: ' . money_toman($kpis['overdue_amount'] ?? 0), 'tone' => 'danger', 'icon' => 'alert-triangle'],
    ['label' => 'منتظر بررسی', 'value' => to_persian_digits($reviewCount), 'meta' => to_persian_digits($kpis['pending_receipts'] ?? 0) . ' رسید، ' . to_persian_digits($kpis['pending_identity'] ?? 0) . ' مدرک', 'tone' => 'info', 'icon' => 'inbox'],
];
$healthCards = [
    ['label' => 'نرخ وصول Cohort ماه', 'value' => isset($kpis['collection_rate']) ? (float) $kpis['collection_rate'] : null, 'hint' => money_toman($kpis['cohort_collected'] ?? 0) . ' وصول از ' . money_toman($kpis['cohort_obligation'] ?? 0) . ' اصل اقساط همین Cohort'],
    ['label' => 'سهم اصل معوق از اصل مانده', 'value' => isset($kpis['overdue_share']) ? (float) $kpis['overdue_share'] : null, 'hint' => money_toman($kpis['overdue_amount'] ?? 0) . ' اصل معوق'],
];
?>

<div class="row widget-grid proma-dashboard proma-admin-dashboard">
  <div class="col-12">
    <section class="proma-workspace-intro">
      <div>
        <span class="proma-eyebrow">نمای کلی کسب‌وکار · <?= e($todayLabel) ?></span>
        <h2>سلام، <?= e($adminName) ?></h2>
        <p>امروز روی وصول و پرونده‌های نیازمند اقدام تمرکز کنید.</p>
      </div>
      <a class="btn" href="<?= e($quickActions[0]['url']) ?>"><i data-feather="plus"></i> قرارداد جدید</a>
    </section>
  </div>

  <div class="col-12">
    <section class="proma-admin-kpi-grid" aria-label="خلاصه وضعیت کسب‌وکار">
      <?php foreach ($mainKpis as $card): ?>
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
      <?php endforeach; ?>
    </section>
  </div>

  <div class="col-12">
    <nav class="proma-workspace-shortcuts" aria-label="دسترسی سریع مدیریت">
      <?php foreach (array_slice($quickActions, 1) as $action): ?>
        <a href="<?= e($action['url']) ?>"><i data-feather="<?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span><i data-feather="arrow-left"></i></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php foreach (array_slice((array) ($dashboardWidgets ?? []), 0, 12) as $dashboardWidget): ?>
    <?php
      if (!is_array($dashboardWidget) || trim((string) ($dashboardWidget['title'] ?? '')) === '') continue;
      $widgetRoute = trim((string) ($dashboardWidget['route'] ?? ''), '/');
      if ($widgetRoute !== '' && !preg_match('#^[a-zA-Z0-9_/-]+$#', $widgetRoute)) $widgetRoute = '';
      $widgetIcon = preg_match('/^[a-zA-Z0-9-]{1,32}$/', (string) ($dashboardWidget['icon'] ?? '')) ? (string) $dashboardWidget['icon'] : 'grid';
    ?>
    <div class="col-xxl-3 col-md-6">
      <section class="card proma-admin-panel proma-plugin-dashboard-widget">
        <div class="card-body">
          <span class="proma-admin-kpi-icon"><i data-feather="<?= e($widgetIcon) ?>"></i></span>
          <div><h5><?= e($dashboardWidget['title']) ?></h5><p><?= e($dashboardWidget['description'] ?? '') ?></p>
            <?php if ($widgetRoute !== ''): ?><a class="link-only" href="<?= e(url($widgetRoute)) ?>"><?= e($dashboardWidget['action_label'] ?? 'مشاهده') ?></a><?php endif; ?>
          </div>
        </div>
      </section>
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
          <a class="link-only" href="<?= e(url('risk/portfolio')) ?>">گزارش کامل پرتفوی</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-admin-health-list">
          <?php foreach ($healthCards as $item): ?>
            <?php $healthAvailable = $item['value'] !== null; $healthTone = !$healthAvailable ? 'muted' : ($item['value'] > 65 ? 'success' : ($item['value'] > 35 ? 'warning' : 'danger')); ?>
            <div class="proma-admin-health">
              <div>
                <strong><?= e($item['label']) ?></strong>
                <small><?= $item['hint'] ?></small>
              </div>
              <span><?= $healthAvailable ? to_persian_digits($item['value']) . '٪' : '—' ?></span>
              <?php if ($healthAvailable): ?><progress class="proma-admin-health-progress is-<?= e($healthTone) ?>" value="<?= e(min(100, max(0, $item['value']))) ?>" max="100" aria-label="<?= e($item['label']) ?>"></progress><?php else: ?><small role="status">دادهٔ واجد شرایط برای این بازه موجود نیست.</small><?php endif; ?>
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
            <thead><tr><th scope="col">مشتری</th><th scope="col">قرارداد</th><th scope="col">سررسید</th><th scope="col">مبلغ</th><th scope="col">وضعیت</th></tr></thead>
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
          <h5>اولویت پیگیری معوقات</h5>
          <a class="link-only" href="<?= e(url('risk/portfolio')) ?>#risk-contracts">تحلیل ریسک</a>
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
            <thead><tr><th scope="col">مشتری</th><th scope="col">نوع</th><th scope="col">مبلغ</th><th scope="col">روش</th><th scope="col">تاریخ</th></tr></thead>
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
            <thead><tr><th scope="col">مشتری</th><th scope="col">قرارداد</th><th scope="col">سررسید</th><th scope="col">قابل پرداخت</th><th scope="col">وضعیت</th></tr></thead>
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
