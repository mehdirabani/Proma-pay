<?php $sprite = template_asset_url('svg/icon-sprite.svg'); ?>
<div class="row widget-grid">
  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card profile-box">
      <div class="card-body">
        <div class="media media-wrapper justify-content-between">
          <div class="media-body">
            <div class="greeting-user">
              <h4 class="f-w-600">پنل پیگیری اپراتور</h4>
              <p>قراردادهای ارجاع‌شده و تماس‌های اخیر را از همین پیشخوان مدیریت کنید.</p>
              <div class="whatsnew-btn"><a class="btn btn-outline-white" href="<?= e(url('operator')) ?>">ثبت تماس</a></div>
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
          <div class="widget-round primary"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#tag"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($contracts)) ?></h4><span class="f-light">قرارداد ارجاع‌شده</span></div>
        </div>
        <div class="font-primary f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>پرونده‌های فعال</span></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round success"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#customers"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($calls)) ?></h4><span class="f-light">تماس ثبت‌شده</span></div>
        </div>
        <div class="font-success f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>گزارش پیگیری</span></div>
      </div>
    </div>
  </div>

  <div class="col-xl-7 box-col-7">
    <div class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>قراردادهای ارجاع‌شده</h5>
          <a class="link-only" href="<?= e(url('operator')) ?>">مدیریت پیگیری‌ها</a>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>مشتری</th><th>موبایل</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $contract): ?>
              <tr>
                <td><?= e($contract['contract_number']) ?></td>
                <td><?= e($contract['customer_name']) ?></td>
                <td><?= to_persian_digits($contract['mobile']) ?></td>
                <td><span class="badge badge-light-<?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="4" class="text-center f-light">قراردادی به شما ارجاع نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-5 box-col-5">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>آخرین تماس‌ها</h5></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>مشتری</th><th>نتیجه</th><th>پیگیری بعدی</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($calls, 0, 8) as $call): ?>
              <tr><td><?= e($call['customer_name']) ?></td><td><?= e($call['call_result']) ?></td><td><?= e(jdate($call['next_followup_date'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$calls): ?><tr><td colspan="3" class="text-center f-light">تماسی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
