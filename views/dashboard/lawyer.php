<?php $sprite = template_asset_url('svg/icon-sprite.svg'); ?>
<div class="row widget-grid">
  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card profile-box">
      <div class="card-body">
        <div class="media media-wrapper justify-content-between">
          <div class="media-body">
            <div class="greeting-user">
              <h4 class="f-w-600">پنل حقوقی</h4>
              <p>پرونده‌های حقوقی، قراردادهای آماده شکایت و وضعیت ارجاع‌ها اینجا دیده می‌شود.</p>
              <div class="whatsnew-btn"><a class="btn btn-outline-white" href="<?= e(url('lawyer')) ?>">مدیریت پرونده‌ها</a></div>
            </div>
          </div>
        </div>
        <div class="cartoon"><img class="img-fluid" src="<?= e(template_asset_url('images/dashboard/cartoon.svg')) ?>" alt=""></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round warning"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#return-box"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($cases)) ?></h4><span class="f-light">پرونده حقوقی</span></div>
        </div>
        <div class="font-warning f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>در جریان</span></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round secondary"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#new-order"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($eligible)) ?></h4><span class="f-light">آماده شکایت</span></div>
        </div>
        <div class="font-secondary f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>قابل ارجاع</span></div>
      </div>
    </div>
  </div>

  <div class="col-xl-7 box-col-7">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>پرونده‌های حقوقی</h5><a class="link-only" href="<?= e(url('lawyer')) ?>">مدیریت پرونده‌ها</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($cases as $case): ?>
              <tr><td><?= e($case['contract_number']) ?></td><td><?= e($case['customer_name']) ?></td><td><?= e($case['stage']) ?></td><td><span class="badge badge-light-<?= e(badge_class($case['status'])) ?>"><?= e(status_label($case['status'])) ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (!$cases): ?><tr><td colspan="4" class="text-center f-light">پرونده‌ای ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-5 box-col-5">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>قراردادهای آماده شکایت</h5></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>مشتری</th><th>موبایل</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($eligible, 0, 10) as $contract): ?>
              <tr><td><?= e($contract['contract_number']) ?></td><td><?= e($contract['customer_name']) ?></td><td><?= to_persian_digits($contract['mobile']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$eligible): ?><tr><td colspan="3" class="text-center f-light">قرارداد واجد شرایط وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
