<?php
$sprite = template_asset_url('svg/icon-sprite.svg');
$statCards = [
    [
        'title' => 'وصول ماه جاری',
        'value' => money_toman($kpis['received']),
        'hint' => 'پرداخت‌های تاییدشده',
        'icon' => 'income',
        'color' => 'success',
    ],
    [
        'title' => 'مانده وصول نشده',
        'value' => money_toman($kpis['outstanding']),
        'hint' => 'اقساط پرداخت‌نشده',
        'icon' => 'expense',
        'color' => 'warning',
    ],
    [
        'title' => 'سررسید ۷ روز آینده',
        'value' => to_persian_digits($kpis['due_week']),
        'hint' => 'نیازمند یادآوری',
        'icon' => 'new-order',
        'color' => 'info',
    ],
    [
        'title' => 'پرونده حقوقی باز',
        'value' => to_persian_digits($kpis['legal_open']),
        'hint' => 'در جریان پیگیری',
        'icon' => 'orders',
        'color' => 'danger',
    ],
];
?>
<div class="row widget-grid proma-dashboard">
  <div class="col-xxl-5 col-xl-12 box-col-12">
    <div class="card profile-box proma-dashboard-hero">
      <div class="card-body">
        <div class="media media-wrapper justify-content-between">
          <div class="media-body">
            <div class="greeting-user">
              <h4 class="f-w-600">سلام، <?= e(Auth::user()['full_name'] ?? 'مدیر') ?></h4>
              <p>نمای مدیریتی امروز برای وصول، ریسک مشتریان، سررسیدها و پرونده‌های فعال آماده است.</p>
              <div class="proma-hero-actions">
                <a href="<?= e(url('overdue')) ?>">
                  <span><?= to_persian_digits($kpis['overdue']) ?></span>
                  <small>اقساط معوق</small>
                </a>
                <a href="<?= e(url('installments')) ?>">
                  <span><?= to_persian_digits($kpis['due_today']) ?></span>
                  <small>سررسید امروز</small>
                </a>
                <a href="<?= e(url('payments')) ?>">
                  <span><?= to_persian_digits($kpis['pending_payments']) ?></span>
                  <small>پرداخت در انتظار</small>
                </a>
              </div>
            </div>
          </div>
          <div class="proma-hero-clock">
            <div class="clockbox">
              <svg id="clock" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600">
                <g id="face"><circle class="circle" cx="300" cy="300" r="253.9"></circle></g>
                <g id="hands"><line id="hour" x1="300" y1="300" x2="300" y2="170"></line><line id="minute" x1="300" y1="300" x2="300" y2="110"></line></g>
              </svg>
            </div>
            <div class="badge f-10 p-0" id="txt"></div>
          </div>
        </div>
        <div class="cartoon"><img class="img-fluid" src="<?= e(template_asset_url('images/dashboard/cartoon.svg')) ?>" alt=""></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-7 col-xl-12 box-col-12">
    <div class="row">
      <?php foreach ($statCards as $card): ?>
        <div class="col-xl-3 col-sm-6">
          <div class="card widget-1 proma-metric-card">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round <?= e($card['color']) ?>">
                  <div class="bg-round">
                    <svg class="svg-fill"><use href="<?= e($sprite) ?>#<?= e($card['icon']) ?>"></use></svg>
                    <svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4><?= $card['value'] ?></h4>
                  <span class="f-light"><?= e($card['title']) ?></span>
                </div>
              </div>
              <div class="font-<?= e($card['color']) ?> f-w-500">
                <i class="icon-arrow-up icon-rotate me-1"></i><span><?= e($card['hint']) ?></span>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card widget-1 proma-metric-card">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round secondary">
            <div class="bg-round">
              <svg class="svg-fill"><use href="<?= e($sprite) ?>#customers"></use></svg>
              <svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg>
            </div>
          </div>
          <div><h4><?= to_persian_digits($kpis['customers']) ?></h4><span class="f-light">مشتریان فعال</span></div>
        </div>
        <div class="font-secondary f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>حساب‌های قابل پیگیری</span></div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card widget-1 proma-metric-card">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round primary">
            <div class="bg-round">
              <svg class="svg-fill"><use href="<?= e($sprite) ?>#tag"></use></svg>
              <svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg>
            </div>
          </div>
          <div><h4><?= to_persian_digits($kpis['contracts']) ?></h4><span class="f-light">قرارداد فعال</span></div>
        </div>
        <div class="font-primary f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span><?= to_persian_digits($kpis['contracts_total']) ?> قرارداد کل</span></div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card proma-health-card">
      <div class="card-body">
        <div class="proma-health-top">
          <span class="badge badge-light-success">وصول</span>
          <strong><?= to_persian_digits($kpis['collection_rate']) ?>٪</strong>
        </div>
        <h6>نرخ وصول ماه</h6>
        <div class="progress sm-progress-bar">
          <div class="progress-bar bg-success" style="width: <?= e($kpis['collection_rate']) ?>%"></div>
        </div>
        <p class="f-light mb-0">از <?= money_toman($kpis['due_month']) ?> سررسید ماهانه</p>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6">
    <div class="card proma-health-card">
      <div class="card-body">
        <div class="proma-health-top">
          <span class="badge badge-light-danger">ریسک</span>
          <strong><?= to_persian_digits($kpis['overdue_share']) ?>٪</strong>
        </div>
        <h6>سهم معوق از مانده</h6>
        <div class="progress sm-progress-bar">
          <div class="progress-bar bg-danger" style="width: <?= e($kpis['overdue_share']) ?>%"></div>
        </div>
        <p class="f-light mb-0"><?= money_toman($kpis['overdue_amount']) ?> مانده معوق</p>
      </div>
    </div>
  </div>

  <div class="col-xxl-8 col-lg-12 box-col-12">
    <div class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>روند وصول ۶ ماه اخیر</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('payments')) ?>">جزئیات پرداخت‌ها</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <ul class="balance-data proma-chart-legend">
          <li><span class="circle bg-primary"></span><span class="f-light ms-1">مبلغ دریافتی</span></li>
          <li><span class="circle bg-success"></span><span class="f-light ms-1">تومان</span></li>
        </ul>
        <div class="proma-chart proma-chart-lg">
          <canvas data-chart="line" data-title="وصول" data-labels='<?= e(json_encode($chartLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($chartData)) ?>'></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-lg-12 box-col-12">
    <div class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>وضعیت اقساط</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('installments')) ?>">لیست اقساط</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-chart proma-chart-sm">
          <canvas data-chart="doughnut" data-title="اقساط" data-labels='<?= e(json_encode($installmentStatus['labels'], JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($installmentStatus['data'])) ?>' data-colors='<?= e(json_encode($installmentStatus['colors'])) ?>'></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-5 col-lg-6 box-col-6">
    <div class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>سن معوقات</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('overdue')) ?>">پیگیری</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-chart proma-chart-md">
          <canvas data-chart="bar" data-title="تعداد قسط" data-labels='<?= e(json_encode($overdueBuckets['labels'], JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($overdueBuckets['data'])) ?>' data-colors='<?= e(json_encode(['#16c7f9', '#ffaa05', '#fc4438', '#7366ff'])) ?>'></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-3 col-lg-6 box-col-6">
    <div class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>وضعیت قراردادها</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('contracts')) ?>">قراردادها</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-chart proma-chart-md">
          <canvas data-chart="doughnut" data-title="قراردادها" data-labels='<?= e(json_encode($contractStatus['labels'], JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($contractStatus['data'])) ?>' data-colors='<?= e(json_encode($contractStatus['colors'])) ?>'></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-lg-12 box-col-12">
    <div class="card appointment-detail">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>مشتریان پرریسک</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('overdue')) ?>">همه معوقات</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-risk-list">
          <?php foreach ($riskCustomers as $customer): ?>
            <a class="proma-risk-item" href="<?= e(url('customers')) ?>">
              <span class="proma-risk-avatar"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></span>
              <span>
                <strong><?= e($customer['full_name']) ?></strong>
                <small><?= e($customer['mobile']) ?> · قدیمی‌ترین سررسید <?= e(jdate($customer['oldest_due_date'])) ?></small>
              </span>
              <em><?= money_toman($customer['debt']) ?></em>
            </a>
          <?php endforeach; ?>
          <?php if (!$riskCustomers): ?><div class="empty">مشتری پرریسکی در حال حاضر ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-lg-6 box-col-6">
    <div class="card appointment-detail proma-ranking-card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>برترین مشتریان</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('customers')) ?>">پرونده‌ها</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-risk-list">
          <?php foreach ($bestCustomers as $customer): ?>
            <a class="proma-risk-item" href="<?= e(url('customers/show/' . $customer['id'])) ?>">
              <span class="proma-risk-avatar"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></span>
              <span>
                <strong><?= e($customer['full_name']) ?></strong>
                <small><?= to_persian_digits($customer['paid_count']) ?> پرداخت به‌موقع · <?= money_toman($customer['total_paid']) ?></small>
              </span>
              <em class="success-text"><?= to_persian_digits($customer['score']) ?></em>
            </a>
          <?php endforeach; ?>
          <?php if (!$bestCustomers): ?><div class="empty">داده‌ای برای رتبه‌بندی وجود ندارد.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-lg-6 box-col-6">
    <div class="card appointment-detail proma-ranking-card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>بدحساب‌ترین مشتریان</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('overdue')) ?>">پیگیری</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-risk-list">
          <?php foreach ($worstCustomers as $customer): ?>
            <a class="proma-risk-item" href="<?= e(url('customers/show/' . $customer['id'])) ?>">
              <span class="proma-risk-avatar danger"><?= e(mb_substr($customer['full_name'], 0, 1, 'UTF-8')) ?></span>
              <span>
                <strong><?= e($customer['full_name']) ?></strong>
                <small><?= to_persian_digits($customer['overdue_count']) ?> معوق · میانگین تأخیر <?= to_persian_digits(round($customer['avg_delay'])) ?> روز</small>
              </span>
              <em><?= to_persian_digits($customer['score']) ?></em>
            </a>
          <?php endforeach; ?>
          <?php if (!$worstCustomers): ?><div class="empty">داده‌ای برای رتبه‌بندی وجود ندارد.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-8 col-lg-12 box-col-12">
    <div class="card appointment-detail">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>سررسیدهای پیش‌رو</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('installments')) ?>">مدیریت اقساط</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead>
              <tr><th>مشتری</th><th>قرارداد</th><th>سررسید</th><th>مبلغ پایه</th><th>وضعیت</th></tr>
            </thead>
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
    </div>
  </div>

  <div class="col-xxl-7 col-lg-12 box-col-12">
    <div class="card appointment-detail">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>پرداخت‌های اخیر</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('payments')) ?>">دفتر پرداخت</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead>
              <tr><th>مشتری</th><th>قسط</th><th>مبلغ</th><th>روش</th><th>تاریخ</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentPayments as $payment): ?>
              <tr>
                <td><h6 class="mb-0"><?= e($payment['customer_name']) ?></h6><span class="f-light"><?= e($payment['contract_number']) ?></span></td>
                <td><?= to_persian_digits($payment['installment_number']) ?></td>
                <td><?= money_toman($payment['amount']) ?></td>
                <td><span class="badge badge-light-info"><?= e(payment_method_label($payment['method'])) ?></span></td>
                <td><?= e(jdate($payment['paid_at'] ?: $payment['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentPayments): ?><tr><td colspan="5" class="text-center f-light">هنوز پرداخت تاییدشده‌ای ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-5 col-lg-12 box-col-12">
    <div class="card appointment-detail">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>بار پیگیری اپراتورها</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('users')) ?>">کاربران</a></div>
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
    </div>
  </div>

  <div class="col-12">
    <div class="card appointment-detail">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>اقساط نیازمند اقدام فوری</h5>
          <div class="card-header-right-icon"><a class="link-only" href="<?= e(url('overdue')) ?>">همه موارد</a></div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="appointment-table table-responsive">
          <table class="table table-bordernone">
            <thead>
              <tr><th>مشتری</th><th>قرارداد</th><th>سررسید</th><th>مبلغ قابل پرداخت</th><th>وضعیت</th></tr>
            </thead>
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
    </div>
  </div>
</div>
