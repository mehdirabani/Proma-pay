<?php
$legalCases = $legalCases ?? [];
$currentLegalCase = $currentLegalCase ?? null;
$legalLogs = $legalLogs ?? [];
$latestLegalLog = $latestLegalLog ?? null;
$legalAttachments = $legalAttachments ?? [];
$legalStageOptions = $legalStageOptions ?? [];
$legalCostTypeOptions = $legalCostTypeOptions ?? [];
$lawyers = $lawyers ?? [];
$financialSummary = $financialSummary ?? null;
$editableLegalLogIds = array_map('intval', $editableLegalLogIds ?? []);
$deletableLegalLogIds = array_map('intval', $deletableLegalLogIds ?? []);
$combinedLegalCost = (float) ($legalLogCostTotal ?? 0) + (float) ($legacyLegalCostTotal ?? 0);
$cancellationSummary = $cancellationSummary ?? Contract::cancellationSummary((int) $contract['id']);
$isInternalViewer = Auth::role() !== 'customer';
$renderedDocumentTitle = trim((string) ($document['rendered_title'] ?? '')) ?: ($documentTitle ?? '');
$renderedDocumentHeader = trim((string) ($document['rendered_header'] ?? '')) ?: ($documentHeader ?? '');
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
         <h2>جزئیات قرارداد <?= e($contract['contract_number']) ?> <span class="badge <?= e(badge_class($contract['status'] ?? '')) ?>"><?= e(status_label($contract['status'] ?? '')) ?></span></h2>
        <p><?= e($contract['customer_name']) ?> - <?= to_persian_digits($contract['mobile'] ?? '') ?></p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('contracts')) ?>">بازگشت</a>
        <a class="btn success" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ دفترچه</a>
        <a class="btn success" href="<?= e(url('contracts/printDocument/' . $contract['id'])) ?>" target="_blank">چاپ قرارداد</a>
        <?php if ($canManageDocument): ?>
          <a class="btn secondary icon-only" href="<?= e(url('contracts', ['open' => 'edit-contract-' . (int) $contract['id']])) ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></a>
          <?php if (($contract['status'] ?? '') !== 'cancelled'): ?><button class="btn danger" type="button" data-open-modal="cancel-contract-show-<?= (int) $contract['id'] ?>"><i data-feather="slash"></i> لغو قرارداد</button><?php endif; ?>
          <form method="post" action="<?= e(url('contracts/generateDocument/' . $contract['id'])) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><?= $document ? 'تولید مجدد قرارداد' : 'تولید قرارداد' ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>مبلغ اصل قرارداد</small><strong><?= money_toman($contract['principal_amount']) ?></strong></span>
      <span><small>پیش‌پرداخت</small><strong><?= money_toman($contract['down_payment_amount'] ?? 0) ?></strong></span>
      <span><small>مانده قابل تقسیط</small><strong><?= money_toman(max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0))) ?></strong></span>
      <span><small>تعداد اقساط</small><strong><?= to_persian_digits($contract['months']) ?></strong></span>
      <span><small>تاریخ قرارداد</small><strong><?= e(jdate($contract['start_date'])) ?></strong></span>
    </div>
  </div>
</section>

<?php if ($canManageDocument && ($contract['status'] ?? '') !== 'cancelled'): ?>
  <div class="modal" id="cancel-contract-show-<?= (int) $contract['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>لغو قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/cancel/' . (int) $contract['id'])) ?>">
        <div class="modal-body form-grid two">
          <?= csrf_field() ?>
          <div class="notice error full">با لغو این قرارداد، قرارداد حذف نمی‌شود اما تمام اقساط فعال آن لغو خواهند شد و دیگر در محاسبات مطالبات و معوقات قرار نمی‌گیرند.</div>
          <div class="proma-cancellation-summary full">
            <span><small>شماره قرارداد</small><strong><?= e($contract['contract_number']) ?></strong></span>
            <span><small>مشتری</small><strong><?= e($contract['customer_name']) ?></strong></span>
            <span><small>اقساط فعال</small><strong><?= to_persian_digits($cancellationSummary['active_installments'] ?? 0) ?></strong></span>
            <span><small>مانده فعال</small><strong><?= money_toman($cancellationSummary['outstanding_amount'] ?? 0) ?></strong></span>
            <span><small>پرداخت ثبت‌شده</small><strong><?= money_toman($cancellationSummary['confirmed_payment_amount'] ?? 0) ?></strong></span>
          </div>
          <label class="full">علت لغو قرارداد<textarea name="cancellation_reason" required minlength="3" rows="4" placeholder="علت انصراف مشتری یا لغو قرارداد را وارد کنید."></textarea></label>
          <label class="full proma-confirm-check"><input type="checkbox" name="confirm_cancel" value="1" required> از لغو قرارداد و اقساط فعال آن اطمینان دارم.</label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit">تأیید و لغو قرارداد</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canViewFinancialSummary && $financialSummary): ?>
  <section class="card proma-management-card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <div>
          <h2>وضعیت مالی امروز قرارداد</h2>
          <p>محاسبه در تاریخ <?= e(jdate($financialSummaryDate ?? date('Y-m-d'))) ?></p>
        </div>
        <span class="badge info">داخلی</span>
      </div>
    </div>
    <div class="card-body">
      <div class="proma-financial-summary">
        <article><small>مبلغ کل قرارداد</small><strong><?= money_toman($financialSummary['contract_total'] ?? 0) ?></strong></article>
        <article><small>پرداخت‌شده تا امروز</small><strong><?= money_toman($financialSummary['paid_total'] ?? 0) ?></strong></article>
        <article><small>اقساط سررسیدشده</small><strong><?= money_toman($financialSummary['due_installments_total'] ?? 0) ?></strong></article>
        <article><small>اقساط پرداخت‌نشده</small><strong><?= money_toman($financialSummary['unpaid_installments_total'] ?? 0) ?></strong></article>
        <article><small>اصل مانده</small><strong><?= money_toman($financialSummary['remaining_principal'] ?? 0) ?></strong></article>
        <article><small>جریمه دیرکرد</small><span class="proma-preview-amount"><?= penalty_display_html([
          'penalty' => $financialSummary['late_penalty_total'] ?? 0,
          'normal_penalty' => $financialSummary['normal_late_penalty_total'] ?? ($financialSummary['late_penalty_total'] ?? 0),
          'legal_penalty' => $financialSummary['legal_late_penalty_total'] ?? ($financialSummary['late_penalty_total'] ?? 0),
          'penalty_mode' => $financialSummary['late_penalty_mode'] ?? 'normal',
        ]) ?></span></article>
        <article><small>پاداش تسویه زودهنگام</small><strong><?= money_toman($financialSummary['early_settlement_reward_total'] ?? 0) ?></strong></article>
        <article><small>هزینه‌های حقوقی</small><strong><?= money_toman($financialSummary['legal_costs_total'] ?? 0) ?></strong></article>
        <article class="proma-financial-final"><small>مبلغ نهایی قابل دریافت امروز</small><strong><?= money_toman($financialSummary['final_collectable_amount'] ?? 0) ?></strong></article>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>متن قرارداد</h2></div>
    <div class="card-body">
      <?php if ($document): ?>
        <div class="contract-document-preview">
          <div class="contract-document-preview-header">
            <strong><?= e($renderedDocumentTitle) ?></strong>
            <?php if ($renderedDocumentHeader !== ''): ?><p><?= nl2br(e($renderedDocumentHeader), false) ?></p><?php endif; ?>
          </div>
          <?= $document['rendered_body'] ?>
        </div>
      <?php else: ?>
        <div class="empty">هنوز متن قرارداد تولید نشده است.</div>
      <?php endif; ?>
    </div>
    <?php if ($canManageDocument): ?>
      <div class="card-body">
        <form method="post" action="<?= e(url('contracts/saveDocument/' . $contract['id'])) ?>" class="form-grid">
          <?= csrf_field() ?>
          <label class="full">عنوان چاپی قرارداد<input name="rendered_title" value="<?= e($renderedDocumentTitle) ?>" required></label>
          <label class="full">هدر چاپی قرارداد<textarea name="rendered_header" rows="4"><?= e($renderedDocumentHeader) ?></textarea></label>
          <label class="full">ویرایش دستی متن قرارداد<textarea name="rendered_body" rows="18" data-rich-editor data-rich-editor-height="520" required><?= e($document['rendered_body'] ?? ContractDocument::render((int) $contract['id'])) ?></textarea></label>
          <label class="full">دلیل ویرایش<input name="change_reason" required placeholder="علت ویرایش نسخه نهایی"></label>
          <div class="actions"><button class="btn" type="submit">ذخیره نسخه نهایی</button></div>
        </form>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>کالاهای قرارداد</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>مدل کالا</th><th>IMEI 1</th><th>IMEI 2</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr><td><?= e($item['product_model']) ?></td><td dir="ltr"><?= e(to_persian_digits($item['imei_1'] ?? '')) ?></td><td dir="ltr"><?= e(to_persian_digits($item['imei_2'] ?? '')) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="3" class="empty">کالایی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card-header card-no-border"><h2>ضمانت‌ها</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>نوع</th><th>تعداد</th><th>شناسه</th><th>توضیحات</th></tr></thead>
        <tbody>
        <?php foreach ($guarantees as $guarantee): ?>
          <tr>
            <td><?= e($guarantee['guarantee_type']) ?></td>
            <td><?= to_persian_digits($guarantee['guarantee_count']) ?></td>
            <td dir="ltr"><?= e(to_persian_digits($guarantee['guarantee_serial'] ?? '')) ?></td>
            <td><?= nl2br(e($guarantee['guarantee_description'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guarantees): ?><tr><td colspan="4" class="empty">ضمانتی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>ضامن‌ها</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>نام</th><th>کد ملی</th><th>تماس</th><th>نسبت</th></tr></thead>
        <tbody>
        <?php foreach ($guarantorPeople as $person): ?>
          <tr>
            <td><?= e($person['full_name']) ?><br><small><?= e($person['father_name'] ?? '') ?></small></td>
            <td><?= to_persian_digits($person['national_id'] ?? '') ?></td>
            <td><?= to_persian_digits($person['mobile'] ?? '') ?></td>
            <td><?= e($person['relationship'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guarantorPeople): ?><tr><td colspan="4" class="empty">ضامنی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <h2>اقساط</h2>
        <?php if ($canManageDocument): ?><button class="btn small" type="button" data-open-modal="add-contract-installment">افزودن قسط</button><?php endif; ?>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>شناسه ضمانت</th><th>وضعیت</th><?php if ($canManageDocument): ?><th>عملیات</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($installments as $installment): ?>
          <tr>
            <td><?= to_persian_digits($installment['installment_number']) ?></td>
            <td><?= e(jdate($installment['due_date'])) ?></td>
            <td><?= money_toman($installment['base_amount']) ?></td>
            <td dir="ltr"><?= e(to_persian_digits($installment['guarantee_serial'] ?? '')) ?></td>
            <td><span class="badge <?= e(badge_class($installment['status'])) ?>"><?= e(status_label($installment['status'])) ?></span></td>
            <?php if ($canManageDocument): ?>
              <td class="actions">
                <button class="btn small success" type="button" data-open-modal="pay-installment-<?= (int) $installment['id'] ?>">پرداخت</button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$installments): ?><tr><td colspan="<?= $canManageDocument ? 6 : 5 ?>" class="empty">قسطی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php if ($isInternalViewer): ?>
  <section class="card proma-legal-card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <div>
          <h2>حقوقی و هزینه‌ها</h2>
          <p>ثبت مراحل پرونده، هزینه‌ها و پیوست‌های حقوقی همین قرارداد</p>
        </div>
        <div class="actions">
          <?php if ($canReferToLegal): ?><button class="btn danger" type="button" data-open-modal="refer-legal-case">ارجاع به واحد حقوقی</button><?php endif; ?>
          <?php if ($canManageLegalLogs): ?><button class="btn secondary" type="button" data-open-modal="add-legal-log">افزودن مرحله حقوقی</button><?php endif; ?>
          <?php if ($canEditLegalCosts): ?><button class="btn secondary" type="button" data-open-modal="add-legal-cost">افزودن هزینه</button><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="proma-legal-stats">
        <article>
          <small>وضعیت فعلی پرونده</small>
          <?php if ($currentLegalCase): ?>
            <strong><span class="badge <?= e(badge_class($currentLegalCase['status'])) ?>"><?= e(status_label($currentLegalCase['status'])) ?></span></strong>
            <p><?= e($currentLegalCase['stage']) ?></p>
          <?php else: ?>
            <strong>بدون پرونده فعال</strong>
            <p><?= !empty($contract['legal_status']) ? e(status_label($contract['legal_status'])) : 'هنوز ارجاع نشده' ?></p>
          <?php endif; ?>
        </article>
        <article>
          <small>آخرین اقدام حقوقی</small>
          <strong><?= e($latestLegalLog['action_title'] ?? 'ثبت نشده') ?></strong>
          <p><?= $latestLegalLog ? e(jdate($latestLegalLog['action_date'])) . (!empty($latestLegalLog['action_time']) ? ' - ' . e(to_persian_digits(substr($latestLegalLog['action_time'], 0, 5))) : '') : 'بدون لاگ' ?></p>
        </article>
        <article>
          <small>پرونده‌های ثبت‌شده</small>
          <strong><?= to_persian_digits(count($legalCases)) ?></strong>
          <p><?= $currentLegalCase ? e($currentLegalCase['lawyer_name'] ?: 'بدون وکیل') : 'در انتظار ارجاع' ?></p>
        </article>
        <article>
          <small>مجموع هزینه‌های حقوقی</small>
          <?php if ($canViewLegalCosts): ?>
            <strong><?= money_toman($combinedLegalCost) ?></strong>
            <p>لاگ‌ها: <?= money_toman($legalLogCostTotal ?? 0) ?><?php if (($legacyLegalCostTotal ?? 0) > 0): ?> | پرونده: <?= money_toman($legacyLegalCostTotal) ?><?php endif; ?></p>
          <?php else: ?>
            <strong>فقط برای کاربران مجاز</strong>
            <p>نمایش مبلغ محدود شده است.</p>
          <?php endif; ?>
        </article>
      </div>

      <div class="proma-legal-overview">
        <div class="proma-legal-pane">
          <h3>خلاصه پرونده</h3>
          <?php if ($currentLegalCase): ?>
            <div class="proma-legal-meta-grid">
              <div><small>شماره پرونده</small><strong><?= e($currentLegalCase['complaint_number'] ?: 'ثبت نشده') ?></strong></div>
              <div><small>وکیل / مسئول</small><strong><?= e($currentLegalCase['lawyer_name'] ?: 'تعیین نشده') ?></strong></div>
              <div><small>تاریخ ابلاغ</small><strong><?= !empty($currentLegalCase['notice_date']) ? e(jdate($currentLegalCase['notice_date'])) : '-' ?></strong></div>
              <div><small>تاریخ دادگاه</small><strong><?= !empty($currentLegalCase['court_date']) ? e(jdate($currentLegalCase['court_date'])) : '-' ?></strong></div>
              <div><small>جلسه رسیدگی</small><strong><?= !empty($currentLegalCase['hearing_date']) ? e(jdate($currentLegalCase['hearing_date'])) : '-' ?></strong></div>
              <div><small>آخرین بروزرسانی</small><strong><?= e(jdatetime($currentLegalCase['updated_at'])) ?></strong></div>
            </div>
            <?php if (!empty($currentLegalCase['notes'])): ?>
              <div class="proma-legal-notes"><?= nl2br(e($currentLegalCase['notes'])) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="empty">برای این قرارداد هنوز پرونده حقوقی فعالی ثبت نشده است.</div>
          <?php endif; ?>
        </div>

        <div class="proma-legal-pane">
          <h3>پیوست‌ها</h3>
          <div class="proma-attachment-list">
            <?php foreach ($legalAttachments as $attachment): ?>
              <a class="proma-attachment-item" href="<?= e(url('contracts/legalAttachment/' . (int) $attachment['id'])) ?>" target="_blank">
                <i data-feather="paperclip"></i>
                <span>
                  <strong><?= e($attachment['action_title']) ?></strong>
                  <small><?= e(jdate($attachment['action_date'])) ?></small>
                </span>
              </a>
            <?php endforeach; ?>
            <?php if (!$legalAttachments): ?><div class="empty">پیوستی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="proma-payment-timeline proma-legal-timeline">
        <?php foreach ($legalLogs as $log): ?>
          <?php $statusText = in_array($log['next_status'] ?? '', ['open', 'referred', 'closed'], true) ? status_label($log['next_status']) : ($log['next_status'] ?: 'بدون تغییر وضعیت'); ?>
          <div class="proma-timeline-item proma-legal-entry">
            <span class="proma-timeline-dot"></span>
            <div class="proma-legal-entry-content">
              <div class="proma-legal-entry-head">
                <div>
                  <strong><?= e($log['action_title']) ?></strong>
                  <p><?= e($log['action_stage']) ?> | <?= e($statusText) ?></p>
                </div>
                <div class="actions">
                  <?php if (!empty($log['attachment_path'])): ?>
                    <a class="btn small secondary" href="<?= e(url('contracts/legalAttachment/' . (int) $log['id'])) ?>" target="_blank">مشاهده پیوست</a>
                  <?php endif; ?>
                  <?php if (in_array((int) $log['id'], $editableLegalLogIds, true)): ?>
                    <button class="btn secondary icon-only" type="button" data-open-modal="edit-legal-log-<?= (int) $log['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
                  <?php endif; ?>
                  <?php if (in_array((int) $log['id'], $deletableLegalLogIds, true)): ?>
                    <button class="btn danger icon-only" type="button" data-open-modal="delete-legal-log-<?= (int) $log['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
                  <?php endif; ?>
                </div>
              </div>
              <div class="proma-legal-entry-meta">
                <span>ثبت‌کننده: <?= e($log['registered_by_name'] ?: 'سامانه') ?></span>
                <span>مسئول: <?= e($log['assigned_lawyer_name'] ?: 'ثبت نشده') ?></span>
                <span>تاریخ: <?= e(jdate($log['action_date'])) ?><?= !empty($log['action_time']) ? ' - ' . e(to_persian_digits(substr($log['action_time'], 0, 5))) : '' ?></span>
                <?php if ($canViewLegalCosts): ?><span>هزینه: <?= money_toman($log['cost_amount'] ?? 0) ?><?= !empty($log['cost_type']) ? ' - ' . e($log['cost_type']) : '' ?></span><?php endif; ?>
              </div>
              <?php if (!empty($log['description'])): ?>
                <div class="proma-legal-description"><?= nl2br(e($log['description'])) ?></div>
              <?php endif; ?>
            </div>
            <time><?= e(jdatetime($log['created_at'])) ?></time>
          </div>
        <?php endforeach; ?>
        <?php if (!$legalLogs): ?><div class="empty">هنوز مرحله حقوقی یا هزینه‌ای برای این قرارداد ثبت نشده است.</div><?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <div class="card-header card-no-border"><h2>تایم‌لاین پرداخت قرارداد</h2></div>
  <div class="card-body">
    <div class="proma-payment-timeline compact">
      <?php foreach (($paymentTimeline ?? []) as $payment): ?>
        <div class="proma-timeline-item">
          <span class="proma-timeline-dot"></span>
          <div>
            <strong><?= money_toman($payment['amount']) ?></strong>
            <p><?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?><?= !empty($payment['installment_number']) ? ' - قسط ' . to_persian_digits($payment['installment_number']) : '' ?></p>
          </div>
          <time><?= e(jdatetime($payment['paid_at'] ?? $payment['payment_date'] ?? $payment['created_at'])) ?></time>
        </div>
      <?php endforeach; ?>
      <?php if (empty($paymentTimeline)): ?><div class="empty">پرداخت موفقی ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($canManageDocument): ?>
<section class="card">
  <div class="card-header card-no-border"><h2>تاریخچه تغییرات قرارداد</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نوع تغییر</th><th>کاربر</th><th>دلیل</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= e($log['change_type']) ?></td>
          <td><?= e($log['changed_by_name'] ?? 'سامانه') ?></td>
          <td><?= e($log['reason'] ?? '') ?></td>
          <td><?= e(jdatetime($log['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="4" class="empty">لاگی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if ($canManageDocument): ?>
  <div class="modal" id="add-contract-installment">
    <div class="modal-content">
      <div class="modal-header"><h3>افزودن قسط جدید</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('installments/store')) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <input type="hidden" name="redirect_to" value="contract">
          <label>قرارداد<input value="<?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?>" disabled></label>
          <label>سررسید<input name="due_date" value="<?= e(jdate(FinanceHelper::addMonths(date('Y-m-d'), 1))) ?>" required placeholder="۱۴۰۵/۰۱/۰۱"></label>
          <label>مبلغ پایه<input name="base_amount" data-money required></label>
          <label>شناسه ضمانت<input name="guarantee_serial" dir="ltr" placeholder="شماره چک یا سفته"></label>
          <label class="full">توضیحات<input name="notes" required placeholder="علت یا توضیح قسط دلخواه"></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <?php foreach ($installments as $installment): ?>
    <div class="modal" id="pay-installment-<?= (int) $installment['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>ثبت پرداخت قسط <?= to_persian_digits($installment['installment_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('installments/payment/' . $installment['id'])) ?>">
          <div class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="contract">
            <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
            <label>مبلغ پرداختی<input name="amount" data-money required value="<?= e(number_format((float) ($installment['payable'] ?? $installment['remaining_amount'] ?? $installment['base_amount']), 0)) ?>"></label>
            <label>تاریخ پرداخت<input name="payment_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
            <label>ساعت پرداخت<input name="payment_time" type="time" value="<?= e(date('H:i')) ?>"></label>
            <label>روش پرداخت<select name="method"><option value="manual">پرداخت دستی</option></select></label>
            <label class="full">توضیحات<input name="description" placeholder="توضیحات پرداخت"></label>
          </div>
          <div class="modal-footer"><button class="btn success" type="submit">ثبت پرداخت</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($isInternalViewer && $canReferToLegal): ?>
  <div class="modal" id="refer-legal-case">
    <div class="modal-content">
      <div class="modal-header"><h3>ارجاع قرارداد به واحد حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/referLegal/' . (int) $contract['id'])) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <label>قرارداد<input value="<?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?>" disabled></label>
          <label>وکیل / مسئول
            <select name="lawyer_id">
              <option value="">تخصیص بعدا</option>
              <?php foreach ($lawyers as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"><?= e($lawyer['full_name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <input type="hidden" name="stage" value="ارجاع به واحد حقوقی">
          <label class="full">عنوان اقدام<input name="action_title" value="ارجاع پرونده به واحد حقوقی" required></label>
          <label class="full">شرح / توضیحات<textarea name="notes" rows="4" placeholder="علت ارجاع، پیگیری‌های قبلی و نکات مهم پرونده"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit">ثبت ارجاع</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($isInternalViewer && $canManageLegalLogs): ?>
  <div class="modal" id="add-legal-log">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>افزودن مرحله حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/storeLegalLog/' . (int) $contract['id'])) ?>" enctype="multipart/form-data">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <?php if (count($legalCases) > 1): ?>
            <label>پرونده مرتبط
              <select name="legal_case_id">
                <option value="">بدون انتخاب پرونده</option>
                <?php foreach ($legalCases as $case): ?>
                  <option value="<?= (int) $case['id'] ?>"<?= selected($currentLegalCase['id'] ?? '', $case['id']) ?>><?= e($case['stage']) ?> - <?= e(status_label($case['status'])) ?><?= !empty($case['complaint_number']) ? ' - ' . e($case['complaint_number']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php elseif ($currentLegalCase): ?>
            <input type="hidden" name="legal_case_id" value="<?= (int) $currentLegalCase['id'] ?>">
          <?php endif; ?>
          <label>مرحله حقوقی
            <select name="action_stage" required>
              <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"><?= e($stage) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>عنوان اقدام<input name="action_title" required placeholder="مثلا ثبت اخطار اول"></label>
          <label>تاریخ اقدام<input name="action_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
          <label>ساعت اقدام<input name="action_time" type="time" value="<?= e(date('H:i')) ?>"></label>
          <label>وکیل / مسئول پرونده
            <select name="assigned_lawyer_id">
              <option value="">بدون تعیین</option>
              <?php foreach ($lawyers as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"<?= selected($currentLegalCase['lawyer_id'] ?? '', $lawyer['id']) ?>><?= e($lawyer['full_name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>وضعیت بعد از اقدام<input name="next_status" value="<?= e($currentLegalCase['status'] ?? 'referred') ?>" placeholder="open / referred / closed یا متن دلخواه"></label>
          <?php if ($canEditLegalCosts): ?>
            <label>هزینه مرتبط با همین اقدام<input name="cost_amount" data-money></label>
            <label>نوع هزینه
              <select name="cost_type">
                <option value="">بدون هزینه</option>
                <?php foreach ($legalCostTypeOptions as $costType): ?><option value="<?= e($costType) ?>"><?= e($costType) ?></option><?php endforeach; ?>
              </select>
            </label>
          <?php endif; ?>
          <label>پیوست<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
          <label class="full">توضیحات<textarea name="description" rows="4" placeholder="شرح اقدام، نتیجه پیگیری و نکات بعدی"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت مرحله</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($isInternalViewer && $canEditLegalCosts): ?>
  <div class="modal" id="add-legal-cost">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>افزودن هزینه حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/storeLegalLog/' . (int) $contract['id'])) ?>" enctype="multipart/form-data">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <?php if (count($legalCases) > 1): ?>
            <label>پرونده مرتبط
              <select name="legal_case_id">
                <option value="">بدون انتخاب پرونده</option>
                <?php foreach ($legalCases as $case): ?><option value="<?= (int) $case['id'] ?>"<?= selected($currentLegalCase['id'] ?? '', $case['id']) ?>><?= e($case['stage']) ?> - <?= e(status_label($case['status'])) ?></option><?php endforeach; ?>
              </select>
            </label>
          <?php elseif ($currentLegalCase): ?>
            <input type="hidden" name="legal_case_id" value="<?= (int) $currentLegalCase['id'] ?>">
          <?php endif; ?>
          <label>مرحله حقوقی
            <select name="action_stage" required>
              <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($stage, 'سایر') ?>><?= e($stage) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>عنوان هزینه<input name="action_title" required value="ثبت هزینه حقوقی"></label>
          <label>تاریخ هزینه<input name="action_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
          <label>ساعت هزینه<input name="action_time" type="time" value="<?= e(date('H:i')) ?>"></label>
          <label>نوع هزینه
            <select name="cost_type" required>
              <?php foreach ($legalCostTypeOptions as $costType): ?><option value="<?= e($costType) ?>"><?= e($costType) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>مبلغ<input name="cost_amount" data-money required></label>
          <label>وکیل / مسئول پرونده
            <select name="assigned_lawyer_id">
              <option value="">بدون تعیین</option>
              <?php foreach ($lawyers as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"<?= selected($currentLegalCase['lawyer_id'] ?? '', $lawyer['id']) ?>><?= e($lawyer['full_name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>وضعیت بعد از اقدام<input name="next_status" value="<?= e($currentLegalCase['status'] ?? 'open') ?>"></label>
          <label>پیوست / رسید<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
          <label class="full">توضیحات<textarea name="description" rows="4" placeholder="شرح هزینه، شماره رسید یا توضیح تکمیلی"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت هزینه</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($isInternalViewer && $legalLogs): ?>
  <?php foreach ($legalLogs as $log): ?>
    <?php if (in_array((int) $log['id'], $editableLegalLogIds, true)): ?>
      <div class="modal" id="edit-legal-log-<?= (int) $log['id'] ?>">
        <div class="modal-content proma-modal-lg">
          <div class="modal-header"><h3>ویرایش لاگ حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
          <form method="post" action="<?= e(url('contracts/updateLegalLog/' . (int) $log['id'])) ?>" enctype="multipart/form-data">
            <div class="modal-body form-grid">
              <?= csrf_field() ?>
              <?php if (count($legalCases) > 1): ?>
                <label>پرونده مرتبط
                  <select name="legal_case_id">
                    <option value="">بدون انتخاب پرونده</option>
                    <?php foreach ($legalCases as $case): ?><option value="<?= (int) $case['id'] ?>"<?= selected($log['legal_case_id'] ?? '', $case['id']) ?>><?= e($case['stage']) ?> - <?= e(status_label($case['status'])) ?></option><?php endforeach; ?>
                  </select>
                </label>
              <?php elseif ($currentLegalCase): ?>
                <input type="hidden" name="legal_case_id" value="<?= (int) ($log['legal_case_id'] ?: $currentLegalCase['id']) ?>">
              <?php endif; ?>
              <label>مرحله حقوقی
                <select name="action_stage" required>
                  <?php foreach ($legalStageOptions as $stage): ?><option value="<?= e($stage) ?>"<?= selected($log['action_stage'], $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
                </select>
              </label>
              <label>عنوان اقدام<input name="action_title" required value="<?= e($log['action_title']) ?>"></label>
              <label>تاریخ اقدام<input name="action_date" value="<?= e(jdate($log['action_date'])) ?>" required></label>
              <label>ساعت اقدام<input name="action_time" type="time" value="<?= e(substr((string) ($log['action_time'] ?? ''), 0, 5)) ?>"></label>
              <label>وکیل / مسئول پرونده
                <select name="assigned_lawyer_id">
                  <option value="">بدون تعیین</option>
                  <?php foreach ($lawyers as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"<?= selected($log['assigned_lawyer_id'] ?? '', $lawyer['id']) ?>><?= e($lawyer['full_name']) ?></option><?php endforeach; ?>
                </select>
              </label>
              <label>وضعیت بعد از اقدام<input name="next_status" value="<?= e($log['next_status'] ?? '') ?>"></label>
              <?php if ($canEditLegalCosts): ?>
                <label>هزینه مرتبط<input name="cost_amount" data-money value="<?= e(number_format((float) ($log['cost_amount'] ?? 0), 0)) ?>"></label>
                <label>نوع هزینه
                  <select name="cost_type">
                    <option value="">بدون هزینه</option>
                    <?php foreach ($legalCostTypeOptions as $costType): ?><option value="<?= e($costType) ?>"<?= selected($log['cost_type'] ?? '', $costType) ?>><?= e($costType) ?></option><?php endforeach; ?>
                  </select>
                </label>
              <?php endif; ?>
              <label>پیوست جدید<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
              <?php if (!empty($log['attachment_path'])): ?>
                <label class="full"><input type="checkbox" name="remove_attachment" value="1"> حذف پیوست فعلی</label>
              <?php endif; ?>
              <label class="full">توضیحات<textarea name="description" rows="4"><?= e($log['description'] ?? '') ?></textarea></label>
            </div>
            <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if (in_array((int) $log['id'], $deletableLegalLogIds, true)): ?>
      <div class="modal" id="delete-legal-log-<?= (int) $log['id'] ?>">
        <div class="modal-content">
          <div class="modal-header"><h3>حذف لاگ حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
          <form method="post" action="<?= e(url('contracts/deleteLegalLog/' . (int) $log['id'])) ?>">
            <div class="modal-body grid">
              <?= csrf_field() ?>
              <?php $deleteCode = ConfirmationCode::hint('legal_log_delete_' . (int) $log['id']); ?>
              <p>آیا از حذف این لاگ مطمئن هستید؟ برای تایید عدد <strong class="ltr"><?= e($deleteCode) ?></strong> را وارد کنید.</p>
              <div class="proma-delete-hint">
                <strong><?= e($log['action_title']) ?></strong>
                <small><?= e(jdate($log['action_date'])) ?><?= !empty($log['action_time']) ? ' - ' . e(to_persian_digits(substr($log['action_time'], 0, 5))) : '' ?></small>
              </div>
              <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCode) ?>"></label>
            </div>
            <div class="modal-footer"><button class="btn danger" type="submit">حذف</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
