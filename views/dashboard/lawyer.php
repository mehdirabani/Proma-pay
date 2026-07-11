<?php
$metrics = $metrics ?? [];
$cases = $cases ?? [];
$eligible = $eligible ?? [];
?>
<div class="row widget-grid proma-role-dashboard proma-role-dashboard--lawyer" style="--role-accent:#e07a19;--role-accent-soft:#fff4e8">
  <div class="col-12">
    <section class="card proma-role-hero">
      <div class="card-body">
        <div>
          <span class="badge badge-light-warning">پیشخوان حقوقی</span>
          <h4>نمای متمرکز پرونده‌های حقوقی</h4>
          <p>پرونده‌های در جریان، قراردادهای آماده شکایت و هزینه‌های ثبت‌شده را سریع بررسی کنید.</p>
        </div>
        <div class="proma-role-hero-actions">
          <a class="btn warning" href="<?= e(url('lawyer')) ?>"><i data-feather="briefcase"></i> مدیریت پرونده‌ها</a>
          <a class="btn secondary" href="<?= e(url('legal')) ?>"><i data-feather="file-text"></i> همه حقوقی</a>
          <a class="btn secondary" href="<?= e(url('calendar')) ?>"><i data-feather="calendar"></i> تقویم</a>
        </div>
      </div>
    </section>
  </div>

  <?php foreach ([
      ['پرونده‌های شما', $metrics['cases'] ?? 0, 'کل پرونده‌های تخصیص‌یافته', 'folder'],
      ['پرونده‌های باز', $metrics['open_cases'] ?? 0, 'نیازمند اقدام', 'activity'],
      ['آماده شکایت', $metrics['eligible'] ?? 0, 'قرارداد واجد شرایط', 'alert-octagon'],
      ['هزینه ثبت‌شده', money_toman($metrics['expenses'] ?? 0), 'جمع هزینه پرونده‌ها', 'credit-card'],
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

  <div class="col-xxl-7 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>پرونده‌های حقوقی اخیر</h5><a class="link-only" href="<?= e(url('lawyer')) ?>">مشاهده همه</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($cases, 0, 10) as $case): ?>
              <tr>
                <td><?= e($case['contract_number']) ?></td>
                <td><?= e($case['customer_name']) ?></td>
                <td><?= e($case['stage']) ?></td>
                <td><span class="badge <?= e(badge_class($case['status'])) ?>"><?= e(status_label($case['status'])) ?></span></td>
                <td class="actions">
                  <a class="btn small secondary" href="<?= e(url('legal/show/' . (int) $case['id'])) ?>">جزئیات</a>
                  <a class="btn small success" href="<?= e(url('contracts/printDocument/' . (int) $case['contract_id'])) ?>" target="_blank" rel="noopener"><i data-feather="printer"></i> چاپ</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$cases): ?><tr><td colspan="5" class="text-center f-light">پرونده‌ای برای نمایش وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-5 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>قراردادهای آماده شکایت</h5><a class="link-only" href="<?= e(url('legal')) ?>">بررسی حقوقی</a></div></div>
      <div class="card-body pt-0">
        <div class="proma-role-list">
          <?php foreach (array_slice($eligible, 0, 8) as $contract): ?>
            <a class="proma-role-list-item" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>">
              <span><strong><?= e($contract['customer_name']) ?></strong><small><?= e($contract['contract_number']) ?> · <?= to_persian_digits($contract['overdue_count'] ?? 0) ?> قسط معوق</small></span>
              <i data-feather="chevron-left"></i>
            </a>
          <?php endforeach; ?>
          <?php if (!$eligible): ?><div class="empty">قرارداد واجد شرایطی برای ارجاع وجود ندارد.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
