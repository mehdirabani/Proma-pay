<?php
$confidenceLabels = [
    'high' => ['اطمینان بالا', 'success'],
    'medium' => ['نیازمند بازبینی', 'warning'],
    'review' => ['فقط بررسی دستی', 'danger'],
];
?>
<section class="card proma-page-intro">
  <div class="card-body">
    <div class="header-top">
      <div>
        <h2>بررسی قراردادهای تکراری</h2>
        <p>این ابزار فقط قرارداد بدون پرداخت، پرونده حقوقی و وابستگی حسابداری را آرشیو می‌کند. هیچ پرداخت واقعی حذف نمی‌شود.</p>
      </div>
      <a class="btn secondary" href="<?= e(url('contracts')) ?>">بازگشت به قراردادها</a>
    </div>
  </div>
</section>

<?php foreach ($groups as $group): ?>
  <?php
    $canonical = $group['canonical'];
    [$confidenceText, $confidenceClass] = $confidenceLabels[$group['confidence']] ?? $confidenceLabels['review'];
  ?>
  <section class="card proma-duplicate-group">
    <div class="card-header">
      <div>
        <h3><?= e($canonical['customer_name']) ?></h3>
        <p>قرارداد مرجع: <?= e($canonical['contract_number']) ?></p>
      </div>
      <span class="badge <?= e($confidenceClass) ?>"><?= e($confidenceText) ?></span>
    </div>
    <div class="card-body">
      <div class="proma-duplicate-summary">
        <span><small>مبلغ اصل</small><strong><?= money_toman($canonical['principal_amount']) ?></strong></span>
        <span><small>تعداد اقساط</small><strong><?= to_persian_digits($canonical['months']) ?></strong></span>
        <span><small>تاریخ شروع</small><strong><?= e(jdate($canonical['start_date'])) ?></strong></span>
        <span><small>پرداخت مؤثر مرجع</small><strong><?= to_persian_digits($canonical['effective_payment_count'] ?? 0) ?></strong></span>
      </div>

      <form method="post" action="<?= e(url('contracts/repairDuplicates')) ?>" data-disable-on-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="canonical_id" value="<?= (int) $canonical['id'] ?>">
        <div class="table-wrap">
          <table>
            <thead><tr><th>انتخاب</th><th>شماره قرارداد</th><th>زمان ایجاد</th><th>پرداخت مؤثر</th><th>پرونده حقوقی</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($group['duplicates'] as $duplicate): ?>
              <?php $eligible = (int) ($duplicate['effective_payment_count'] ?? 0) === 0 && (int) ($duplicate['legal_case_count'] ?? 0) === 0 && !in_array($duplicate['status'] ?? '', ['completed', 'closed'], true); ?>
              <tr>
                <td><input class="proma-checkbox" type="checkbox" name="duplicate_ids[]" value="<?= (int) $duplicate['id'] ?>" <?= $eligible && !empty($group['auto_repairable']) ? 'checked' : 'disabled' ?> aria-label="انتخاب قرارداد <?= e($duplicate['contract_number']) ?>"></td>
                <td><a href="<?= e(url('contracts/show/' . $duplicate['id'])) ?>"><?= e($duplicate['contract_number']) ?></a></td>
                <td><?= e(jdatetime($duplicate['created_at'])) ?></td>
                <td><?= money_toman($duplicate['effective_payment_amount'] ?? 0) ?></td>
                <td><?= to_persian_digits($duplicate['legal_case_count'] ?? 0) ?></td>
                <td><span class="badge <?= e(badge_class($duplicate['status'] ?? '')) ?>"><?= e(status_label($duplicate['status'] ?? '')) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (!empty($group['auto_repairable'])): ?>
          <div class="proma-repair-confirm">
            <label class="proma-check-row"><input class="proma-checkbox" type="checkbox" name="confirm_repair" value="1" required><span>تأیید می‌کنم قراردادهای انتخاب‌شده آرشیو و اقساط فعال آن‌ها لغو شوند.</span></label>
            <button class="btn warning" type="submit"><?= proma_icon('archive') ?> آرشیو تکراری‌ها</button>
          </div>
        <?php else: ?>
          <div class="alert warning">این گروه به علت سابقه مالی، حقوقی، وضعیت تکمیل‌شده یا فاصله زمانی زیاد فقط باید دستی بررسی شود.</div>
        <?php endif; ?>
      </form>
    </div>
  </section>
<?php endforeach; ?>

<?php if (!$groups): ?>
  <section class="card"><div class="card-body empty">قرارداد تکراری قابل بررسی پیدا نشد.</div></section>
<?php endif; ?>
