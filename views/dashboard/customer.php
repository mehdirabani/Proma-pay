<?php $sprite = template_asset_url('svg/icon-sprite.svg'); ?>
<div class="row widget-grid">
  <div class="col-xxl-4 col-sm-6 box-col-6">
    <div class="card profile-box">
      <div class="card-body">
        <div class="media media-wrapper justify-content-between">
          <div class="media-body">
            <div class="greeting-user">
              <h4 class="f-w-600">پیشخوان مشتری</h4>
              <p>قراردادها، اقساط و نشان‌های تشویقی شما در این بخش نمایش داده می‌شود.</p>
              <div class="whatsnew-btn"><a class="btn btn-outline-white" href="<?= e(url('portal/installments')) ?>">پرداخت اقساط</a></div>
            </div>
          </div>
        </div>
        <div class="cartoon"><img class="img-fluid" src="<?= e(template_asset_url('images/dashboard/cartoon.svg')) ?>" alt=""></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round primary"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#tag"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($contracts)) ?></h4><span class="f-light">قرارداد من</span></div>
        </div>
        <div class="font-primary f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>فعال در سامانه</span></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round warning"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#return-box"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($installments)) ?></h4><span class="f-light">قسط ثبت‌شده</span></div>
        </div>
        <div class="font-warning f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>برنامه پرداخت</span></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-auto col-xl-3 col-sm-6 box-col-6">
    <div class="card widget-1">
      <div class="card-body">
        <div class="widget-content">
          <div class="widget-round success"><div class="bg-round"><svg class="svg-fill"><use href="<?= e($sprite) ?>#rate"></use></svg><svg class="half-circle svg-fill"><use href="<?= e($sprite) ?>#halfcircle"></use></svg></div></div>
          <div><h4><?= to_persian_digits(count($medals)) ?></h4><span class="f-light">نشان تشویقی</span></div>
        </div>
        <div class="font-success f-w-500"><i class="icon-arrow-up icon-rotate me-1"></i><span>امتیاز وفاداری</span></div>
      </div>
    </div>
  </div>

  <div class="col-xl-6 box-col-6">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>قراردادها</h5><a class="link-only" href="<?= e(url('portal/contracts')) ?>">مشاهده همه</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>شماره</th><th>مبلغ</th><th>نوع سود</th><th>شروع</th><th>وضعیت</th><th>دفترچه</th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $contract): ?>
              <tr>
                <td><?= e($contract['contract_number']) ?></td>
                <td><?= money_toman($contract['principal_amount']) ?></td>
                <td><?= $contract['interest_type'] === 'compound' ? 'مرکب ماهانه' : 'ساده ماهانه' ?></td>
                <td><?= e(jdate($contract['start_date'])) ?></td>
                <td><span class="badge badge-light-<?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
                <td><a class="btn small secondary" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="6" class="text-center f-light">قراردادی برای شما ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6 box-col-6">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>نمای مالی امروز</h5><a class="link-only" href="<?= e(url('portal/installments')) ?>">پرداخت</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قسط</th><th>سررسید</th><th>جریمه</th><th>پاداش</th><th>قابل پرداخت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($installments, 0, 8) as $item): ?>
              <tr>
                <td><?= to_persian_digits($item['installment_number']) ?></td>
                <td><?= e(jdate($item['due_date'])) ?></td>
                <td><?= money_toman($item['penalty']) ?></td>
                <td><?= money_toman($item['reward']) ?></td>
                <td><?= money_toman($item['payable']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$installments): ?><tr><td colspan="5" class="text-center f-light">قسطی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-12">
    <div class="card">
      <div class="card-header card-no-border"><div class="header-top"><h5>نشان‌ها و پاداش‌ها</h5></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>عنوان</th><th>امتیاز</th><th>توضیح</th></tr></thead>
            <tbody>
            <?php foreach ($medals as $medal): ?>
              <tr><td><?= e($medal['title']) ?></td><td><?= to_persian_digits($medal['points']) ?></td><td><?= e($medal['description']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$medals): ?><tr><td colspan="3" class="text-center f-light">هنوز نشانی برای شما ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
