<?php
$case = $case ?? [];
$contract = $contract ?? [];
$timeline = $timeline ?? [];
$payments = $payments ?? [];
$installments = $installments ?? [];
$legalFinancials = $legalFinancials ?? ['rows' => [], 'totals' => []];
$legalRows = $legalFinancials['rows'] ?? [];
$legalTotals = $legalFinancials['totals'] ?? [];
$canOpenContract = $canOpenContract ?? false;
$canViewCosts = $canViewCosts ?? false;
$canAttachLegalFile = $canAttachLegalFile ?? false;
$canDeleteCase = $canDeleteCase ?? false;
$legalStageOptions = $legalStageOptions ?? [];
$overdueCount = (int) ($overdueCount ?? 0);
$totalPaid = 0;
foreach ($payments as $payment) {
    if (($payment['status'] ?? '') === 'paid') {
        $totalPaid += (float) ($payment['amount'] ?? 0);
    }
}
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>جزئیات و تاریخچه پرونده حقوقی</h2>
        <p><?= e($case['contract_number'] ?? '') ?> - <?= e($case['customer_name'] ?? '') ?></p>
      </div>
      <div class="actions">
        <?php if (Auth::role() === 'admin'): ?><a class="btn secondary" href="<?= e(url('legal')) ?>">بازگشت به حقوقی</a><?php endif; ?>
        <?php if (Auth::role() === 'lawyer'): ?><a class="btn secondary" href="<?= e(url('lawyer')) ?>">بازگشت به پنل حقوقی</a><?php endif; ?>
        <?php if ($canAttachLegalFile): ?><button class="btn secondary" type="button" data-open-modal="legal-attachment-upload">ارسال ضمیمه</button><?php endif; ?>
        <?php if ($canDeleteCase): ?><button class="btn danger icon-only" type="button" data-open-modal="delete-legal-case" title="حذف پرونده" aria-label="حذف پرونده"><i data-feather="trash-2"></i></button><?php endif; ?>
        <?php if ($canOpenContract): ?><a class="btn" href="<?= e(url('contracts/show/' . (int) ($case['contract_id'] ?? 0))) ?>">مشاهده قرارداد</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>وضعیت جاری</small><strong><span class="badge <?= e(badge_class($case['status'] ?? '')) ?>"><?= e(status_label($case['status'] ?? '')) ?></span></strong></span>
      <span><small>مرحله حقوقی</small><strong><?= e($case['stage'] ?? '-') ?></strong></span>
      <span><small>وکیل مرتبط</small><strong><?= e($case['lawyer_name'] ?: 'تعیین نشده') ?></strong></span>
      <span><small>تاریخ ایجاد</small><strong><?= e(jdatetime($case['created_at'] ?? '')) ?></strong></span>
      <span><small>اقساط معوقه</small><strong><?= to_persian_digits($overdueCount) ?></strong></span>
      <span><small>پرداخت موفق مرتبط</small><strong><?= money_toman($totalPaid) ?></strong></span>
    </div>
  </div>
</section>

<?php if ($canAttachLegalFile): ?>
  <div class="modal" id="legal-attachment-upload">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>ارسال فایل ضمیمه پرونده</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('legal/storeAttachment/' . (int) ($case['id'] ?? 0))) ?>" enctype="multipart/form-data">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <label>مرحله حقوقی
            <select name="action_stage" required>
              <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'] ?? '', $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>عنوان ضمیمه<input name="action_title" required value="ارسال فایل ضمیمه پرونده"></label>
          <label>تاریخ ثبت<input name="action_date" data-jalali-input required value="<?= e(jdate(date('Y-m-d'))) ?>"></label>
          <label>ساعت ثبت<input name="action_time" type="time" value="<?= e(date('H:i')) ?>"></label>
          <label>فایل ضمیمه<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
          <label class="full">توضیحات<textarea name="description" rows="4" placeholder="شرح کوتاه فایل، شماره نامه یا توضیح تکمیلی"></textarea></label>
        </div>
        <div class="modal-footer">
          <button class="btn success" type="submit">ثبت ضمیمه</button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canDeleteCase): ?>
  <div class="modal" id="delete-legal-case">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف پرونده حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('legal/retireCase/' . (int) ($case['id'] ?? 0))) ?>">
        <div class="modal-body grid">
          <?= csrf_field() ?>
          <?php $deleteCaseCode = ConfirmationCode::hint('legal_case_delete_' . (int) ($case['id'] ?? 0)); ?>
          <div class="notice error">پرونده حقوقی این قرارداد و لاگ‌های ضمیمه‌شده به همین پرونده حذف می‌شود. برای تایید عدد <strong class="ltr"><?= e($deleteCaseCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCaseCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>مشخصات پرونده و طرفین</h2></div>
    <div class="card-body">
      <div class="proma-legal-meta-grid">
        <div><small>مشتری</small><strong><?= e($case['customer_name'] ?? '-') ?></strong></div>
        <div><small>موبایل</small><strong><?= to_persian_digits($case['mobile'] ?? '-') ?></strong></div>
        <div><small>کد ملی</small><strong><?= to_persian_digits($case['national_id'] ?? '-') ?></strong></div>
        <div><small>قرارداد</small><strong><?= e($case['contract_number'] ?? '-') ?></strong></div>
        <div><small>شماره شکایت</small><strong><?= e(to_persian_digits($case['complaint_number'] ?: 'ثبت نشده')) ?></strong></div>
        <div><small>وضعیت قرارداد</small><strong><?= e(status_label($contract['status'] ?? '')) ?></strong></div>
        <div><small>تاریخ ابلاغ</small><strong><?= !empty($case['notice_date']) ? e(jdate($case['notice_date'])) : '-' ?></strong></div>
        <div><small>تاریخ دادگاه</small><strong><?= !empty($case['court_date']) ? e(jdate($case['court_date'])) : '-' ?></strong></div>
      </div>
      <?php if (!empty($case['notes'])): ?>
        <div class="proma-legal-notes" style="margin-top:16px"><?= nl2br(e($case['notes'])) ?></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>اقساط پرونده</h2></div>
    <div class="card-body">
      <div class="proma-preview-grid">
        <span><small>مانده اقساط</small><strong><?= money_toman($legalTotals['remaining_amount'] ?? 0) ?></strong></span>
        <span><small>جریمه عادی</small><strong><?= money_toman($legalTotals['normal_penalty'] ?? 0) ?></strong></span>
        <span><small>جریمه حقوقی</small><strong><?= money_toman($legalTotals['legal_penalty'] ?? 0) ?></strong></span>
        <span><small>هزینه‌های شکایت</small><strong><?= money_toman($legalTotals['complaint_costs'] ?? 0) ?></strong></span>
        <span><small>جمع با شرایط عادی</small><strong><?= money_toman($legalTotals['normal_collectable'] ?? 0) ?></strong></span>
        <span><small>جمع با شرایط شکایت</small><strong><?= money_toman($legalTotals['legal_collectable'] ?? 0) ?></strong></span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>پرداخت‌شده</th><th>مانده</th><th>جریمه عادی</th><th>جریمه حقوقی</th><th>قابل وصول عادی</th><th>قابل وصول شکایت</th><th>وضعیت</th></tr></thead>
        <tbody>
        <?php foreach ($legalRows as $installment): ?>
          <tr>
            <td><?= to_persian_digits($installment['installment_number'] ?? '') ?></td>
            <td><?= e(jdate($installment['due_date'] ?? '')) ?></td>
            <td><?= money_toman($installment['base_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['paid_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['remaining_today'] ?? 0) ?></td>
            <td><?= money_toman($installment['normal_penalty_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['legal_penalty_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['normal_payable_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['legal_payable_amount'] ?? 0) ?></td>
            <td><span class="badge <?= e(badge_class($installment['status'] ?? '')) ?>"><?= e(status_label($installment['status'] ?? '')) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$legalRows): ?><tr><td colspan="10" class="empty">قسطی برای این قرارداد ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="card">
  <div class="card-header card-no-border"><h2>تاریخچه رویدادهای پرونده</h2></div>
  <div class="card-body">
    <div class="proma-payment-timeline proma-legal-timeline">
      <?php foreach ($timeline as $item): ?>
        <div class="proma-timeline-item proma-legal-entry">
          <span class="proma-timeline-dot"></span>
          <div class="proma-legal-entry-content">
            <div class="proma-legal-entry-head">
              <div>
                <strong><?= e($item['title'] ?? '') ?></strong>
                <p><?= e($item['stage'] ?? '') ?><?= !empty($item['status']) ? ' | ' . e(status_label($item['status'])) : '' ?></p>
              </div>
              <?php if (!empty($item['attachment_id'])): ?>
                <a class="btn small secondary" href="<?= e(url('contracts/legalAttachment/' . (int) $item['attachment_id'])) ?>" target="_blank">مشاهده پیوست</a>
              <?php endif; ?>
            </div>
            <div class="proma-legal-entry-meta">
              <span>ثبت‌کننده: <?= e($item['actor_name'] ?? 'سامانه') ?></span>
              <?php if (!empty($item['assignee_name'])): ?><span>مسئول: <?= e($item['assignee_name']) ?></span><?php endif; ?>
              <?php if ($canViewCosts && !empty($item['cost_amount'])): ?><span>هزینه: <?= money_toman($item['cost_amount']) ?><?= !empty($item['cost_type']) ? ' - ' . e($item['cost_type']) : '' ?></span><?php endif; ?>
            </div>
            <?php if (!empty($item['description'])): ?><div class="proma-legal-description"><?= nl2br(e($item['description'])) ?></div><?php endif; ?>
          </div>
          <time><?= e(jdatetime($item['happened_at'] ?? '')) ?></time>
        </div>
      <?php endforeach; ?>
      <?php if (!$timeline): ?><div class="empty">رویدادی برای این پرونده ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="card">
  <div class="card-header card-no-border"><h2>لاگ پرداخت‌های مرتبط</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>تاریخ</th><th>قسط</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>شرح</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <tr>
          <td><?= e(jdatetime($payment['paid_at'] ?? $payment['payment_date'] ?? $payment['created_at'])) ?></td>
          <td><?= !empty($payment['installment_number']) ? to_persian_digits($payment['installment_number']) : '-' ?></td>
          <td><?= money_toman($payment['amount'] ?? 0) ?></td>
          <td><?= e(payment_method_label($payment['method'] ?? '')) ?></td>
          <td><span class="badge <?= e(badge_class($payment['status'] ?? '')) ?>"><?= e(status_label($payment['status'] ?? '')) ?></span></td>
          <td><?= e($payment['description'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="6" class="empty">پرداختی برای این پرونده ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
