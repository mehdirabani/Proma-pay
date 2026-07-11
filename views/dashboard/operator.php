<?php
$metrics = $metrics ?? [];
$contracts = $contracts ?? [];
$calls = $calls ?? [];
$overdue = $overdue ?? [];
?>
<div class="row widget-grid proma-role-dashboard proma-role-dashboard--operator">
  <div class="col-12">
    <section class="card proma-role-hero">
      <div class="card-body">
        <div>
          <span class="badge badge-light-primary">پیشخوان عملیاتی</span>
          <h4>سلام، <?= e(Auth::user()['full_name'] ?? 'اپراتور') ?></h4>
          <p>قراردادهای ارجاع‌شده، سررسیدهای نزدیک و تماس‌های پیگیری را از یک نمای سریع مدیریت کنید.</p>
        </div>
        <div class="proma-role-hero-actions">
          <a class="btn" href="<?= e(url('overdue')) ?>"><i data-feather="alert-circle"></i> سررسید گذشته</a>
          <a class="btn secondary" href="<?= e(url('calendar')) ?>"><i data-feather="calendar"></i> تقویم پیگیری</a>
          <a class="btn secondary" href="<?= e(url('chat')) ?>"><i data-feather="message-square"></i> گفت‌وگو</a>
        </div>
      </div>
    </section>
  </div>

  <?php foreach ([
      ['قراردادهای تخصیص‌یافته', $metrics['contracts'] ?? 0, 'همه پرونده‌های شما', 'file-text'],
      ['قراردادهای فعال', $metrics['active_contracts'] ?? 0, 'قابل پیگیری', 'check-circle'],
      ['اقساط معوق', $metrics['overdue'] ?? 0, 'نیازمند تماس', 'alert-triangle'],
      ['پیگیری‌های آینده', $metrics['followups'] ?? 0, 'تماس زمان‌بندی‌شده', 'phone-call'],
  ] as $item): ?>
    <div class="col-xxl-3 col-md-6">
      <article class="card proma-role-kpi">
        <div class="card-body">
          <span class="proma-role-kpi-icon"><i data-feather="<?= e($item[3]) ?>"></i></span>
          <div><small><?= e($item[0]) ?></small><strong><?= to_persian_digits($item[1]) ?></strong><em><?= e($item[2]) ?></em></div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <div class="col-xxl-7 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>قراردادهای ارجاع‌شده</h5><a class="link-only" href="<?= e(url('contracts')) ?>">مشاهده همه</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>قرارداد</th><th>مشتری</th><th>تماس</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($contracts, 0, 10) as $contract): ?>
              <tr>
                <td><?= e($contract['contract_number']) ?></td>
                <td><?= e($contract['customer_name']) ?></td>
                <td><?= to_persian_digits($contract['mobile']) ?></td>
                <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
                <td class="actions">
                  <a class="btn small secondary" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>">جزئیات</a>
                  <a class="btn small success" href="<?= e(url('contracts/printDocument/' . (int) $contract['id'])) ?>" target="_blank" rel="noopener"><i data-feather="printer"></i> چاپ</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="5" class="text-center f-light">قراردادی به شما ارجاع نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-5 col-xl-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>اقدام‌های نزدیک</h5><a class="link-only" href="<?= e(url('overdue')) ?>">همه معوقات</a></div></div>
      <div class="card-body pt-0">
        <div class="proma-role-list">
          <?php foreach (array_slice($overdue, 0, 8) as $item): ?>
            <a class="proma-role-list-item" href="<?= e(url('installments')) ?>">
              <span><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?> · سررسید <?= e(jdate($item['due_date'])) ?></small></span>
              <span class="badge badge-light-danger"><?= money_toman($item['payable'] ?? $item['base_amount'] ?? 0) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$overdue): ?><div class="empty">در حال حاضر قسط معوقی برای پیگیری ندارید.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-12">
    <section class="card proma-role-panel">
      <div class="card-header card-no-border"><div class="header-top"><h5>آخرین تماس‌های ثبت‌شده</h5><a class="link-only" href="<?= e(url('overdue')) ?>">ثبت پیگیری جدید</a></div></div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordernone">
            <thead><tr><th>مشتری</th><th>نتیجه</th><th>پیگیری بعدی</th><th>یادداشت</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($calls, 0, 10) as $call): ?>
              <tr><td><?= e($call['customer_name']) ?></td><td><?= e($call['call_result']) ?></td><td><?= !empty($call['next_followup_date']) ? e(jdate($call['next_followup_date'])) : '-' ?></td><td><?= e($call['notes'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$calls): ?><tr><td colspan="4" class="text-center f-light">تماسی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>
