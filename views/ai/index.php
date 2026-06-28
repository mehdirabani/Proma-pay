<?php
$actionTypeLabels = [
    'create_customer' => 'ایجاد مشتری',
    'create_contract' => 'ایجاد قرارداد',
    'manual_review' => 'بررسی دستی',
];
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>دستیار هوشمند سامانه</h2>
      <span class="badge badge-light-info">بدون تأیید مدیر هیچ تغییری اعمال نمی‌شود.</span>
    </div>
  </div>
  <div class="card-body">
    <form method="post" action="<?= e(url('ai/analyze')) ?>" class="form-grid" data-loading-form>
      <?= csrf_field() ?>
      <label class="full">دستور فارسی مدیر
        <textarea name="text" required placeholder="مثلاً: اقساط عقب‌افتاده مشتری علی رضایی را بررسی کن"><?= e($text ?? '') ?></textarea>
      </label>
      <div class="full">
        <button class="btn" type="submit" data-loading-text="در حال تحلیل...">تحلیل و پیشنهاد</button>
      </div>
    </form>
  </div>
</section>

<?php if ($analysis): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>نتیجه دستیار</h2></div>
  <div class="card-body">
    <?php if ($analysis['ok'] && !empty($structured)): ?>
      <div class="ai-result">
        <div class="notice info"><?= e($structured['summary']) ?></div>

        <?php if (!empty($structured['findings'])): ?>
          <h5>یافته‌ها</h5>
          <ul class="ai-list">
            <?php foreach ($structured['findings'] as $item): ?><li><?= e(is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : $item) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if (!empty($structured['data'])): ?>
          <h5>داده‌های مرتبط</h5>
          <div class="ai-json"><?= e(json_encode($structured['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></div>
        <?php endif; ?>

        <?php if (!empty($structured['warnings'])): ?>
          <h5>هشدارها</h5>
          <ul class="ai-list warning">
            <?php foreach ($structured['warnings'] as $item): ?><li><?= e(is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : $item) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if (!empty($structured['proposed_actions'])): ?>
          <h5>اقدام‌های پیشنهادی</h5>
          <div class="table-wrap">
            <table>
              <thead><tr><th>عنوان</th><th>نوع</th><th>شرح</th><th>نیاز به تأیید</th></tr></thead>
              <tbody>
              <?php foreach ($structured['proposed_actions'] as $action): ?>
                <tr>
                  <td><?= e($action['label'] ?? 'اقدام پیشنهادی') ?></td>
                  <td><?= e($actionTypeLabels[$action['type'] ?? 'manual_review'] ?? 'بررسی دستی') ?></td>
                  <td><?= e($action['description'] ?? '') ?></td>
                  <td><?= !empty($action['requires_confirmation']) ? 'بله' : 'خیر' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <form method="post" action="<?= e(url('ai/confirm/' . (int) ($structured['log_id'] ?? 0))) ?>" style="margin-top:14px">
            <?= csrf_field() ?>
            <button class="btn success" type="submit">تأیید و اعمال موارد مجاز</button>
          </form>
        <?php else: ?>
          <div class="notice">اقدام قابل اعمالی پیشنهاد نشده است.</div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="notice error"><?= e($analysis['error'] ?? 'تحلیل انجام نشد.') ?></div>
      <?php if (!empty($analysis['details'])): ?><div class="ai-json"><?= e($analysis['details']) ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>سوابق دستیار</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>دستور</th><th>وضعیت</th><th>مدیر</th><th>تاریخ</th><th>نتیجه</th></tr></thead>
      <tbody>
      <?php foreach (($logs ?? []) as $log): ?>
        <tr>
          <td><?= e(mb_substr($log['instruction'], 0, 90, 'UTF-8')) ?></td>
          <td><span class="badge <?= e(badge_class($log['status'])) ?>"><?= e(status_label($log['status'])) ?></span></td>
          <td><?= e($log['full_name']) ?></td>
          <td><?= e(jdate($log['created_at'])) ?></td>
          <td><?= e($log['applied_summary'] ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?><tr><td colspan="5" class="empty">هنوز تحلیلی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
