<?php
$case = $case ?? [];
$contract = $contract ?? [];
$timeline = $timeline ?? [];
$payments = $payments ?? [];
$installments = $installments ?? [];
$legalFinancials = $legalFinancials ?? ['rows' => [], 'totals' => []];
$legalRows = $legalFinancials['rows'] ?? [];
$legalTotals = $legalFinancials['totals'] ?? [];
$financialSummary = $financialSummary ?? [];
$legalCostSummary = $legalCostSummary ?? [];
$legalCosts = $legalCosts ?? [];
$financialBatchWarnings = $financialBatchWarnings ?? [];
$canOpenContract = $canOpenContract ?? false;
$canViewCosts = $canViewCosts ?? false;
$canAttachLegalFile = $canAttachLegalFile ?? false;
$canRegisterLegalCost = $canRegisterLegalCost ?? false;
$canDeleteCase = $canDeleteCase ?? false;
$legalStageOptions = $legalStageOptions ?? [];
$legalCostTypeOptions = $legalCostTypeOptions ?? [];
$legalDocuments = $legalDocuments ?? [];
$eligibility = $eligibility ?? [];
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
        <?php if ($canRegisterLegalCost): ?><button class="btn warning" type="button" data-open-modal="legal-cost-create">ثبت هزینه</button><?php endif; ?>
        <?php if ($canDeleteCase): ?><button class="btn danger icon-only" type="button" data-open-modal="delete-legal-case" title="بایگانی پرونده" aria-label="بایگانی پرونده"><i data-feather="archive"></i></button><?php endif; ?>
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

<section class="card">
  <div class="card-header card-no-border"><div><h2>خلاصه مالی مرجع پرونده</h2><p>همه مبالغ از موتور محاسبات قرارداد در همین لحظه خوانده شده‌اند؛ هزینهٔ ثبت‌شده تا تأیید مدیر وارد مبلغ قابل مطالبه نمی‌شود.</p></div></div>
  <div class="card-body">
    <?php if (($financialSummary['calculation_status'] ?? 'calculation_failed') !== 'calculated' || $financialBatchWarnings): ?>
      <div class="notice warning">محاسبه مالی نیازمند بررسی است؛ مبلغ صفر به‌عنوان نتیجه قطعی تلقی نشده و ثبت وصول تا رفع هشدار باید با احتیاط انجام شود.</div>
    <?php endif; ?>
    <div class="proma-preview-grid">
      <span><small>مانده اصل</small><strong><?= money_toman($financialSummary['remaining_principal'] ?? 0) ?></strong></span>
      <span><small>جریمه عادی واقعی</small><strong><?= money_toman($financialSummary['normal_late_penalty_total'] ?? 0) ?></strong></span>
      <span><small>جریمه حقوقی واقعی</small><strong><?= money_toman($financialSummary['legal_late_penalty_total'] ?? 0) ?></strong></span>
      <span><small>هزینه در انتظار تأیید</small><strong><?= money_toman($legalCostSummary['pending_approval_legal_costs'] ?? 0) ?></strong></span>
      <span><small>هزینه حقوقی قابل مطالبه</small><strong><?= money_toman($legalCostSummary['outstanding_chargeable_legal_costs'] ?? 0) ?></strong></span>
      <span><small>مبلغ واقعی قابل پرداخت امروز</small><strong><?= money_toman($financialSummary['final_collectable_amount'] ?? 0) ?></strong></span>
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

<?php if ($canRegisterLegalCost): ?>
  <div class="modal" id="legal-cost-create">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>ثبت هزینه حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('legal/storeCost/' . (int) ($case['id'] ?? 0))) ?>" enctype="multipart/form-data">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <div class="full notice info">هزینه ابتدا با وضعیت «در انتظار تأیید» ثبت می‌شود و تنها پس از تأیید مدیر در مبلغ قابل مطالبه وارد خواهد شد.</div>
          <label>عنوان هزینه<input name="action_title" required value="ثبت هزینه حقوقی"></label>
          <label>نوع هزینه<select name="cost_type" required><option value="">انتخاب کنید</option><?php foreach ($legalCostTypeOptions as $costType): ?><option value="<?= e($costType) ?>"><?= e($costType) ?></option><?php endforeach; ?></select></label>
          <label>مبلغ (تومان)<input name="cost_amount" data-money required inputmode="numeric"></label>
          <label>تاریخ هزینه<input name="action_date" data-jalali-input required value="<?= e(jdate(date('Y-m-d'))) ?>"></label>
          <label>ساعت ثبت<input name="action_time" type="time" value="<?= e(date('H:i')) ?>"></label>
          <label>مرحله حقوقی<select name="action_stage" required><?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'] ?? '', $stage) ?>><?= e($stage) ?></option><?php endforeach; ?></select></label>
          <label>پیوست هزینه (اختیاری)<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
          <label class="full">شرح و مستند هزینه<textarea name="description" rows="4" required placeholder="علت، شماره فیش یا توضیح مستند هزینه"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn warning" type="submit">ثبت برای تأیید</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
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
          <div class="notice warning">پرونده فقط بایگانی می‌شود؛ لاگ‌ها، پرداخت‌ها و مستندات برای حفظ سابقه حقوقی باقی می‌مانند. برای تایید عدد <strong class="ltr"><?= e($deleteCaseCode) ?></strong> را وارد کنید.</div>
          <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCaseCode) ?>"></label>
        </div>
        <div class="modal-footer">
          <button class="btn danger" type="submit">بایگانی پرونده</button>
          <button class="btn secondary" type="button" data-close-modal>بستن</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canAttachLegalFile): ?>
<section class="card">
  <div class="card-header card-no-border"><h2>اسناد و اقدام‌های حقوقی داخلی</h2></div>
  <div class="card-body">
    <div class="notice info">«اخطار قراردادی»، «پیش‌نویس دادخواست/شکواییه» و «تشکیل پرونده داخلی» اسناد داخلی‌اند. تا زمانی که کاربر حقوقی مرجع، شماره پیگیری و تاریخ ثبت واقعی را وارد و تایید نکند، هیچ‌یک ابلاغ رسمی یا ثبت رسمی قضایی نیست.</div>
    <div class="proma-preview-grid">
      <span><small>سیاست قرارداد</small><strong>نسخه <?= to_persian_digits($eligibility['policy_version'] ?? 0) ?></strong></span>
      <span><small>وضعیت احراز</small><strong><?= !empty($eligibility['eligible']) ? 'واجد شرایط بررسی' : 'در انتظار احراز' ?></strong></span>
      <span><small>مانده معوق موثر</small><strong><?= money_toman($eligibility['overdue_amount'] ?? 0) ?></strong></span>
      <span><small>روز تاخیر</small><strong><?= to_persian_digits($eligibility['delay_days'] ?? 0) ?></strong></span>
    </div>
    <div class="actions" style="margin-top:14px">
      <button type="button" class="btn warning" data-open-modal="legal-document-contractual_warning">صدور اخطار قراردادی داخلی</button>
      <button type="button" class="btn secondary" data-open-modal="legal-document-petition_draft">تهیه پیش‌نویس دادخواست</button>
      <button type="button" class="btn secondary" data-open-modal="legal-document-complaint_draft">تهیه پیش‌نویس شکواییه</button>
    </div>
  </div>
  <div class="table-wrap"><table><thead><tr><th>نوع</th><th>عنوان</th><th>وضعیت</th><th>مهلت</th><th>ثبت‌کننده</th><th>مرجع/پیگیری</th><th>عملیات</th></tr></thead><tbody>
  <?php foreach ($legalDocuments as $document): ?>
    <tr><td><?= e(['contractual_warning' => 'اخطار قراردادی داخلی', 'petition_draft' => 'پیش‌نویس دادخواست', 'complaint_draft' => 'پیش‌نویس شکواییه'][$document['document_type']] ?? $document['document_type']) ?></td><td><?= e($document['title']) ?></td><td><span class="badge <?= e(($document['document_status'] ?? '') === 'external_submission_confirmed' ? 'success' : 'muted') ?>"><?= e(($document['document_status'] ?? '') === 'external_submission_confirmed' ? 'ثبت بیرونی تاییدشده' : 'پیش‌نویس داخلی') ?></span></td><td><?= !empty($document['deadline_date']) ? e(jdate($document['deadline_date'])) : '-' ?></td><td><?= e($document['created_by_name'] ?? '-') ?></td><td><?= !empty($document['external_reference']) ? e($document['external_authority'] . ' / ' . $document['external_reference']) : 'ثبت نشده' ?></td><td><?php if (($document['document_status'] ?? '') !== 'external_submission_confirmed'): ?><button class="btn small info" type="button" data-open-modal="legal-confirm-external-<?= (int) $document['id'] ?>">تایید ثبت بیرونی</button><?php endif; ?></td></tr>
    <div class="modal" id="legal-confirm-external-<?= (int) $document['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>تایید ثبت واقعی بیرونی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('legal/confirmExternalDocument/' . (int) $document['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="full notice warning">فقط پس از ثبت واقعی در مرجع مربوط این فرم را تایید کنید؛ این سامانه خودکار چیزی را ثبت یا ابلاغ نمی‌کند.</div><label>مرجع ثبت بیرونی<input name="external_authority" required placeholder="نام مرجع/سامانه"></label><label>شماره پیگیری واقعی<input name="external_reference" required></label><label>تاریخ ثبت واقعی<input name="external_submitted_at" data-jalali-input required value="<?= e(jdate(date('Y-m-d'))) ?>"></label><label class="full"><input type="checkbox" name="confirm_external_submission" value="1" required> ثبت واقعی را بررسی و شخصاً تایید می‌کنم.</label></div><div class="modal-footer"><button type="submit" class="btn success">ثبت تایید</button><button type="button" class="btn secondary" data-close-modal>بستن</button></div></form></div></div>
  <?php endforeach; ?>
  <?php if (!$legalDocuments): ?><tr><td colspan="7" class="empty">هنوز سند حقوقی داخلی ثبت نشده است.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php foreach (['contractual_warning' => 'اخطار قراردادی داخلی', 'petition_draft' => 'پیش‌نویس دادخواست مطالبه وجه', 'complaint_draft' => 'پیش‌نویس شکواییه / درخواست حقوقی'] as $documentType => $documentLabel): ?>
  <div class="modal" id="legal-document-<?= e($documentType) ?>"><div class="modal-content proma-modal-lg"><div class="modal-header"><h3><?= e($documentLabel) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('legal/createDocument/' . (int) $case['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><input type="hidden" name="document_type" value="<?= e($documentType) ?>"><input type="hidden" name="request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><div class="full notice info">این سند داخلی است و تا زمان ثبت و تایید واقعی بیرونی، ابلاغ رسمی/ثبت قضایی محسوب نمی‌شود.</div><label>عنوان<input name="title" value="<?= e($documentLabel) ?>"></label><label>مهلت پیشنهادی<input name="deadline_date" data-jalali-input value=""></label><label class="full">متن سند<textarea name="content" rows="8" placeholder="در صورت خالی بودن، قالب امن داخلی استفاده می‌شود."></textarea></label></div><div class="modal-footer"><button type="submit" class="btn">ثبت پیش‌نویس داخلی</button><button type="button" class="btn secondary" data-close-modal>بستن</button></div></form></div></div>
<?php endforeach; ?>
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
        <div><small>شماره پیگیری/مرجع بیرونی</small><strong><?= e(to_persian_digits($case['complaint_number'] ?: 'ثبت نشده')) ?></strong></div>
        <div><small>وضعیت قرارداد</small><strong><?= e(status_label($contract['status'] ?? '')) ?></strong></div>
        <div><small>تاریخ ثبت/پیگیری بیرونی</small><strong><?= !empty($case['notice_date']) ? e(jdate($case['notice_date'])) : '-' ?></strong></div>
        <div><small>ارجاع رسمی مبنای جریمه حقوقی</small><strong><?= !empty($case['legal_referred_at']) ? e(jdatetime($case['legal_referred_at'])) : 'ثبت نشده' ?></strong></div>
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
        <span><small>جریمه عادی واقعی</small><strong><?= money_toman($legalTotals['normal_penalty'] ?? 0) ?></strong></span>
        <span><small>جریمه حقوقی واقعی</small><strong><?= money_toman($legalTotals['legal_penalty'] ?? 0) ?></strong></span>
        <span><small>جریمه حقوقی بالقوه (تحلیلی)</small><strong><?= money_toman($legalTotals['projected_legal_penalty'] ?? 0) ?></strong></span>
        <span><small>جمع جریمه قابل پرداخت واقعی</small><strong><?= money_toman($legalTotals['effective_penalty_payable'] ?? 0) ?></strong></span>
        <span><small>هزینه حقوقی قابل مطالبه</small><strong><?= money_toman($legalTotals['complaint_costs'] ?? 0) ?></strong></span>
        <span><small>قابل وصول واقعی با هزینه حقوقی</small><strong><?= money_toman($legalTotals['legal_collectable'] ?? 0) ?></strong></span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>پرداخت‌شده</th><th>مانده</th><th>جریمه عادی واقعی</th><th>جریمه حقوقی واقعی</th><th>حقوقی بالقوه (تحلیلی)</th><th>جمع جریمه قابل پرداخت</th><th>قابل وصول واقعی</th><th>وضعیت</th></tr></thead>
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
            <td><?= money_toman($installment['projected_legal_penalty_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['effective_penalty_payable_amount'] ?? 0) ?></td>
            <td><?= money_toman($installment['legal_payable_amount'] ?? 0) ?></td>
            <td><span class="badge <?= e(badge_class($installment['status'] ?? '')) ?>"><?= e(status_label($installment['status'] ?? '')) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$legalRows): ?><tr><td colspan="11" class="empty">قسطی برای این قرارداد ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php if ($canViewCosts): ?>
<section class="card">
  <div class="card-header card-no-border"><div><h2>ریز هزینه‌های حقوقی</h2><p>هزینه‌های «در انتظار تأیید» صرفاً ثبت شده‌اند و تا تأیید مدیریت به بدهی مشتری اضافه نمی‌شوند.</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>تاریخ</th><th>عنوان</th><th>نوع</th><th>مبلغ</th><th>وضعیت تأیید</th><th>وضعیت وصول</th><th>ثبت‌کننده</th></tr></thead><tbody>
  <?php foreach ($legalCosts as $cost): ?>
    <?php $approval = (string) ($cost['approval_status'] ?? 'pending_approval'); ?>
    <tr>
      <td><?= e(jdate($cost['cost_date'] ?? '')) ?></td><td><?= e($cost['title'] ?? '-') ?></td><td><?= e($cost['category'] ?? '-') ?></td><td><?= money_toman($cost['amount_toman'] ?? 0) ?></td>
      <td><span class="badge <?= e($approval === 'approved' ? 'success' : ($approval === 'reversed' ? 'danger' : 'warning')) ?>"><?= e($approval === 'approved' ? 'تأیید شده' : ($approval === 'reversed' ? 'برگشت خورده' : 'در انتظار تأیید')) ?></span></td>
      <td><?= e(status_label($cost['payment_status'] ?? 'pending')) ?></td><td><?= e($cost['created_by_name'] ?? '-') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$legalCosts): ?><tr><td colspan="7" class="empty">هزینه‌ای برای این قرارداد ثبت نشده است.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

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
