<?php $center = $center ?? []; $metrics = $center['metrics'] ?? []; ?>
<div class="proma-collections-workspace">
<header class="proma-page-intro">
  <div><span class="proma-eyebrow">پیگیری مطالبات</span><h1>مرکز عملیات وصول</h1><p>صف‌های فوری، وعده‌های پرداخت و کارهای پیگیری را در یک نمای روزانه دنبال کنید.</p></div>
  <div class="proma-contracts-intro__actions"><a class="btn secondary" href="<?= e(url('overdue')) ?>"><?= proma_icon('history') ?><span>سررسیدهای گذشته</span></a><?php if (Auth::role() === 'admin'): ?><form method="post" action="<?= e(url('collections/runAutomation')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= proma_icon('activity') ?><span>به‌روزرسانی صف‌ها</span></button></form><?php endif; ?></div>
</header>
<section class="card proma-collection-center">
  <div class="card-body">
    <?php if (!empty($center['warnings'])): ?><div class="notice warning" role="status">بخشی از صف‌های وصول به‌دلیل خطای داده یا ساختار پایگاه‌داده بارگذاری نشد. خطا برای پیگیری در گزارش سامانه ثبت شده است؛ بقیهٔ بخش‌ها در دسترس هستند.</div><?php endif; ?>
    <div class="kpi-grid">
      <article class="kpi-card danger"><span>پرونده‌های بحرانی، بیش از ۲۰ روز</span><strong><?= to_persian_digits($metrics['critical'] ?? 0) ?></strong></article>
      <article class="kpi-card warning"><span>وعده‌های پرداخت امروز</span><strong><?= to_persian_digits($metrics['promises_today'] ?? 0) ?></strong></article>
      <article class="kpi-card"><span>معوق بدون تماس در ۷ روز</span><strong><?= to_persian_digits($metrics['no_contact_7d'] ?? 0) ?></strong></article>
      <?php if (Auth::role() === 'admin'): ?><article class="kpi-card primary"><span>واجد شرایط بررسی حقوقی</span><strong><?= to_persian_digits($metrics['legal_review'] ?? 0) ?></strong></article><?php endif; ?>
      <article class="kpi-card"><span>وظیفه‌های باز</span><strong><?= to_persian_digits($metrics['open_tasks'] ?? 0) ?></strong></article>
    </div>
    <div class="collection-queues">
      <section class="card collection-queue"><div class="card-header"><h3>وظیفه‌های باز اپراتورها</h3></div><div class="card-body">
        <?php foreach (($center['tasks'] ?? []) as $task): ?><article class="collection-row"><div><strong><?= e($task['customer_name'] ?? '') ?> · <?= e($task['stage'] ?? '') ?></strong><small>قرارداد <?= e($task['contract_number'] ?? '') ?> · مسئول: <?= e($task['assignee_name'] ?? 'تخصیص‌نیافته') ?> · موعد <?= e(jdatetime($task['due_at'] ?? '')) ?></small></div><div class="actions"><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) $task['contract_id'])) ?>">خط زمانی</a><form method="post" action="<?= e(url('collections/completeTask/' . (int) $task['id'])) ?>"><?= csrf_field() ?><button class="btn small" type="submit">تکمیل</button></form></div></article><?php endforeach; ?>
        <?php if (empty($center['tasks'])): ?><div class="empty">وظیفهٔ بازی در این صف نیست. مدیر می‌تواند صف مرحله‌ای را به‌روزرسانی کند.</div><?php endif; ?>
      </div></section>
      <?php foreach ([['critical','پرونده‌های بحرانی'],['promises','وعده‌های امروز'],['no_contact','بدون تماس اخیر']] as [$key,$label]): ?>
        <section class="card collection-queue"><div class="card-header"><h3><?= e($label) ?></h3></div><div class="card-body">
          <?php foreach (($center[$key] ?? []) as $row): $contractId = (int) ($row['contract_id'] ?? 0); ?>
            <article class="collection-row"><div><strong><?= e($row['customer_name'] ?? '') ?></strong><small>قرارداد <?= e($row['contract_number'] ?? '') ?><?= isset($row['max_overdue_days']) ? ' · ' . to_persian_digits((int) $row['max_overdue_days']) . ' روز تأخیر' : '' ?><?= !empty($row['collection_stage']) ? ' · ' . e($row['collection_stage']) : '' ?></small></div><a class="btn small secondary" href="<?= e(url('collections/timeline/' . $contractId)) ?>">خط زمانی</a></article>
          <?php endforeach; ?>
          <?php if (empty($center[$key])): ?><div class="empty">موردی در این صف نیست.</div><?php endif; ?>
        </div></section>
      <?php endforeach; ?>
      <?php if (Auth::role() === 'admin'): ?>
        <section class="card collection-queue"><div class="card-header"><h3>آمادهٔ بررسی حقوقی</h3></div><div class="card-body">
          <?php foreach (($center['legal'] ?? []) as $row): ?><article class="collection-row"><div><strong><?= e($row['customer_name'] ?? '') ?></strong><small>قرارداد <?= e($row['contract_number'] ?? '') ?> · <?= to_persian_digits((int) ($row['max_overdue_days'] ?? 0)) ?> روز تأخیر</small></div><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) ($row['contract_id'] ?? 0))) ?>">خط زمانی</a></article><?php endforeach; ?>
          <?php if (empty($center['legal'])): ?><div class="empty">پروندهٔ واجد شرایطی پیدا نشد.</div><?php endif; ?>
        </div></section>
      <?php endif; ?>
    </div>
    <p class="muted">مرحلهٔ پیگیری از تعداد روزهای تأخیر و قواعد حقوقی فعال استخراج می‌شود. قرارداد لغوشده و مختومهٔ حقوقی در صف‌های فعال معوق قرار نمی‌گیرند.</p>
  </div>
</section>
</div>
