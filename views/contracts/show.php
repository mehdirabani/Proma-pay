<?php
$legalCases = $legalCases ?? [];
$currentLegalCase = $currentLegalCase ?? null;
$legalLogs = $legalLogs ?? [];
$latestLegalLog = $latestLegalLog ?? null;
$legalAttachments = $legalAttachments ?? [];
$legalStageOptions = $legalStageOptions ?? [];
$legalCostTypeOptions = $legalCostTypeOptions ?? [];
$legalCostSummary = $legalCostSummary ?? [];
$legalCosts = $legalCosts ?? [];
$lawyers = $lawyers ?? [];
$financialSummary = $financialSummary ?? null;
$editableLegalLogIds = array_map('intval', $editableLegalLogIds ?? []);
$deletableLegalLogIds = array_map('intval', $deletableLegalLogIds ?? []);
$combinedLegalCost = normalize_money($legalCostSummary['outstanding_chargeable_legal_costs'] ?? 0);
$cancellationSummary = $cancellationSummary ?? Contract::cancellationSummary((int) $contract['id']);
$deletionPreview = Contract::deletionPreview((int) $contract['id']);
$canPermanentlyDelete = !empty($deletionPreview['eligible_for_permanent_delete']);
$isInternalViewer = Auth::role() !== 'customer';
$isCancelledContract = ($contract['status'] ?? '') === 'cancelled';
$canManageActiveContract = $canManageDocument && !in_array(($contract['status'] ?? ''), ['cancelled', 'completed', 'closed'], true);
$renderedDocumentTitle = trim((string) ($document['rendered_title'] ?? '')) ?: ($documentTitle ?? '');
$renderedDocumentHeader = trim((string) ($document['rendered_header'] ?? '')) ?: ($documentHeader ?? '');
?>

<section class="card proma-contract-header" data-contract-tab-panel="summary">
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
          <?php if ($canManageActiveContract): ?>
            <a class="btn secondary icon-only" href="<?= e(url('contracts', ['open' => 'edit-contract-' . (int) $contract['id']])) ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></a>
            <button class="btn danger" type="button" data-open-modal="cancel-contract-show-<?= (int) $contract['id'] ?>"><i data-feather="slash"></i> لغو قرارداد</button>
          <?php endif; ?>
           <button class="btn danger" type="button" data-open-modal="delete-contract-show-<?= (int) $contract['id'] ?>"><i data-feather="trash-2"></i> حذف دائمی</button>
          <?php if ($canManageActiveContract): ?><form method="post" action="<?= e(url('contracts/generateDocument/' . $contract['id'])) ?>">
              <?= csrf_field() ?>
              <button class="btn" type="submit"><?= $document ? 'تولید مجدد قرارداد' : 'تولید قرارداد' ?></button>
            </form><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>مبلغ اصل قرارداد</small><strong><?= money_toman($contract['principal_amount']) ?></strong></span>
      <span><small>پیش‌پرداخت</small><strong><?= money_toman($contract['down_payment_amount'] ?? 0) ?></strong></span>
      <span><small>مانده قابل تقسیط</small><strong><?= money_toman(max(0, normalize_money($contract['principal_amount'] ?? 0) - normalize_money($contract['down_payment_amount'] ?? 0))) ?></strong></span>
      <span><small>تعداد اقساط</small><strong><?= to_persian_digits($contract['months']) ?></strong></span>
      <span><small>تاریخ قرارداد</small><strong><?= e(jdate($contract['start_date'])) ?></strong></span>
    </div>
  </div>
</section>

<nav class="proma-contract-tabs" data-contract-tabs aria-label="بخش‌های قرارداد">
  <button type="button" class="active" data-contract-tab-open="summary">خلاصه قرارداد</button>
  <button type="button" data-contract-tab-open="installments">اقساط</button>
  <button type="button" data-contract-tab-open="payments">پرداخت‌ها</button>
  <button type="button" data-contract-tab-open="settlement">تسویه و محاسبات</button>
  <?php if ($isInternalViewer): ?><button type="button" data-contract-tab-open="legal">پرونده حقوقی</button><?php endif; ?>
  <button type="button" data-contract-tab-open="files">فایل‌ها و مدارک</button>
  <?php if ($canManageDocument): ?><button type="button" data-contract-tab-open="activity">تاریخچه فعالیت</button><?php endif; ?>
</nav>

<?php
$settlementPreview = $settlementPreview ?? ['selected_count' => 0, 'principal_total' => 0, 'normal_penalty_total' => 0, 'legal_penalty_total' => 0, 'reward_total' => 0, 'full_settlement_total' => 0];
// The header preview is installment-only; an approved legal cost is explicitly
// added for the contract-wide settlement and re-verified by the server quote.
$settlementPreview['legal_cost_total'] = $combinedLegalCost;
$settlementPreview['full_settlement_total'] = normalize_money($settlementPreview['full_settlement_total'] ?? 0) + $combinedLegalCost;
?>
<section class="card proma-contract-settlement-card" data-contract-tab-panel="settlement" hidden>
  <div class="card-header card-no-border"><div class="header-top"><div><h2>تسویه کامل قرارداد</h2><p>محاسبهٔ سروری در تاریخ <?= e(jdate(date('Y-m-d'))) ?>؛ پیش از ثبت پرداخت دوباره قفل و محاسبه می‌شود.</p></div><strong class="proma-settlement-total"><?= money_toman($settlementPreview['full_settlement_total'] ?? 0) ?></strong></div></div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>مانده اصل اقساط</small><strong><?= money_toman($settlementPreview['principal_total'] ?? 0) ?></strong></span>
      <span><small>جریمه عادی</small><strong><?= money_toman($settlementPreview['normal_penalty_total'] ?? 0) ?></strong></span>
      <span><small>جریمه حقوقی</small><strong><?= money_toman($settlementPreview['legal_penalty_total'] ?? 0) ?></strong></span>
      <span><small>هزینه حقوقی تأییدشده</small><strong><?= money_toman($settlementPreview['legal_cost_total'] ?? 0) ?></strong></span>
      <span><small>پاداش تسویه زودهنگام</small><strong class="text-success">-<?= money_toman($settlementPreview['reward_total'] ?? 0) ?></strong></span>
    </div>
    <?php if (!$isCancelledContract && normalize_money($settlementPreview['full_settlement_total'] ?? 0) > 0): ?>
      <?php if ($canManageActiveContract): ?>
        <form method="post" action="<?= e(url('contracts/paymentGroup/' . (int) $contract['id'])) ?>" class="actions mt-3" data-contract-settlement-form data-quote-url="<?= e(url('contracts/settlementQuote/' . (int) $contract['id'])) ?>">
          <?= csrf_field() ?>
          <?php foreach ($installments as $settlementInstallment): ?><?php if (!empty($settlementInstallment['payment_allowed'])): ?><input type="hidden" name="installment_ids[]" value="<?= (int) $settlementInstallment['id'] ?>"><?php endif; ?><?php endforeach; ?>
          <input type="hidden" name="group_amount" value="<?= e((string) normalize_money($settlementPreview['full_settlement_total'] ?? 0)) ?>" data-contract-settlement-amount><input type="hidden" name="quote_uuid" value="" data-contract-settlement-quote><input type="hidden" name="settlement_scope" value="contract"><input type="hidden" name="payment_method" value="manual"><input type="hidden" name="payment_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="group_description" value="تسویه کامل قرارداد"><button class="btn success" type="submit" data-contract-settlement-submit>تأیید مبلغ و تسویه کامل <?= money_toman($settlementPreview['full_settlement_total'] ?? 0) ?></button>
        </form>
      <?php elseif (Auth::role() === 'customer'): ?>
        <a class="btn success mt-3" href="<?= e(url('installments/panel')) ?>">انتخاب روش پرداخت و تسویه کامل</a>
      <?php endif; ?>
    <?php else: ?><div class="notice success mt-3">تمام اقساط مؤثر این قرارداد تسویه شده‌اند.</div><?php endif; ?>
  </div>
</section>

<?php if ($isCancelledContract): ?>
  <div class="notice warning">این قرارداد لغو شده است؛ اطلاعات و نسخه چاپی آن فقط برای مشاهده و چاپ در دسترس هستند.</div>
<?php endif; ?>

<?php if ($canManageActiveContract): ?>
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
            <span><small>وضعیت فعلی</small><strong><?= e(status_label($contract['status'] ?? '')) ?></strong></span>
            <span><small>اقساط فعال</small><strong><?= to_persian_digits($cancellationSummary['active_installments'] ?? 0) ?></strong></span>
            <span><small>اقساط معوق</small><strong><?= to_persian_digits($cancellationSummary['overdue_installments'] ?? 0) ?></strong></span>
            <span><small>مانده فعال</small><strong><?= money_toman($cancellationSummary['outstanding_amount'] ?? 0) ?></strong></span>
            <span><small>پرداخت ثبت‌شده</small><strong><?= money_toman($cancellationSummary['confirmed_payment_amount'] ?? 0) ?></strong></span>
            <span><small>تعداد پرداخت</small><strong><?= to_persian_digits($cancellationSummary['confirmed_payment_count'] ?? 0) ?></strong></span>
            <span><small>سند قرارداد</small><strong><?= to_persian_digits($cancellationSummary['document_count'] ?? 0) ?></strong></span>
            <span><small>پرونده حقوقی</small><strong><?= to_persian_digits($cancellationSummary['legal_case_count'] ?? 0) ?></strong></span>
            <span><small>وابستگی حسابداری</small><strong><?= to_persian_digits($cancellationSummary['accounting_relation_count'] ?? 0) ?></strong></span>
          </div>
          <label class="full">علت لغو قرارداد<textarea name="cancellation_reason" required minlength="3" rows="4" placeholder="علت انصراف مشتری یا لغو قرارداد را وارد کنید."></textarea></label>
          <label class="full proma-confirm-check"><input type="checkbox" name="confirm_cancel" value="1" required> پیامدهای لغو قرارداد را مطالعه کردم.</label>
          <label class="full proma-confirm-check proma-danger-check"><input type="checkbox" name="correct_contract_payments" value="1"> برای پرداخت‌های موفق همین قرارداد، اصلاحیه مالی ثبت شود و اثر آن‌ها در محاسبات داخلی صفر شود. این عملیات بازگشت وجه بانکی انجام نمی‌دهد.</label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit">تأیید و لغو قرارداد</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canManageDocument): ?>
  <div class="modal" id="delete-contract-show-<?= (int) $contract['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>حذف قرارداد آزمایشی یا اشتباهی</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
      <form method="post" action="<?= e(url('contracts/retire/' . (int) $contract['id'])) ?>">
        <div class="modal-body form-grid two">
          <?= csrf_field() ?>
          <div class="notice <?= $canPermanentlyDelete ? 'success' : 'warning' ?> full"><?= $canPermanentlyDelete ? 'این قرارداد وابستگی فعالی ندارد و پس از ثبت آرشیو ایمن قابل حذف است.' : 'این قرارداد سابقه وابسته دارد. مدیریت می‌تواند با تأیید صریح، قرارداد و سوابق وابسته را پس از آرشیو کامل حذف کند.' ?></div>
          <div class="proma-cancellation-summary full"><span><small>کل پرداخت‌ها</small><strong><?= to_persian_digits($deletionPreview['payment_count'] ?? 0) ?></strong></span><span><small>رسیدها</small><strong><?= to_persian_digits($deletionPreview['dependencies']['payment_receipt_count'] ?? 0) ?></strong></span><span><small>پرونده حقوقی</small><strong><?= to_persian_digits($deletionPreview['legal_case_count'] ?? 0) ?></strong></span><span><small>اسناد قرارداد</small><strong><?= to_persian_digits(($deletionPreview['dependencies']['generated_document_count'] ?? 0) + ($deletionPreview['dependencies']['document_version_count'] ?? 0)) ?></strong></span></div>
          <?php if (!$canPermanentlyDelete): ?><p class="full small text-muted">سوابق وابسته: <?= e(implode('، ', $deletionPreview['blocking_dependency_labels'] ?? [])) ?></p><?php endif; ?>
          <label class="full required-field">علت حذف<textarea name="retirement_reason" required minlength="5" rows="4" placeholder="علت دقیق حذف قرارداد را ثبت کنید"></textarea></label>
          <label class="full required-field">برای تأیید، شماره قرارداد را وارد کنید<input name="confirm_contract_number" required autocomplete="off" value="" placeholder="<?= e($contract['contract_number']) ?>"></label>
          <?php if (!$canPermanentlyDelete): ?><label class="full proma-confirm-check proma-danger-check"><input type="checkbox" name="include_related_history" value="1" required> قرارداد و تمام سوابق مالی، اقساط، حقوقی، اسناد و عملیات وابسته حذف شوند؛ آرشیو کامل پیش از حذف ثبت می‌شود.</label><?php endif; ?>
          <?php if ((int) ($deletionPreview['gateway_payment_count'] ?? 0) > 0): ?><label class="full proma-confirm-check proma-danger-check"><input type="checkbox" name="accept_gateway_notice" value="1" required> می‌دانم این عملیات بازگشت وجه بانکی انجام نمی‌دهد و بازپرداخت واقعی باید جداگانه انجام شود.</label><?php endif; ?>
          <label class="full proma-confirm-check proma-danger-check"><input type="checkbox" name="confirm_mistake" value="1" required> حذف دائمی این قرارداد و پیامدهای آن را بررسی و تأیید می‌کنم.</label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit"><?= proma_icon('trash') ?><span>حذف قطعی قرارداد</span></button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canViewFinancialSummary && $financialSummary): ?>
  <section class="card proma-management-card" data-contract-tab-panel="summary">
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
        <article><small>جریمه عادی واقعی</small><strong><?= money_toman($financialSummary['normal_late_penalty_total'] ?? 0) ?></strong></article>
        <article><small>جریمه حقوقی واقعی</small><strong><?= money_toman($financialSummary['legal_late_penalty_total'] ?? 0) ?></strong></article>
        <article><small>جریمه حقوقی بالقوه (تحلیلی)</small><strong<?= !empty($financialSummary['show_projected_legal_penalty']) ? ' class="proma-projected-penalty"' : '' ?>><?= money_toman($financialSummary['projected_legal_penalty_total'] ?? 0) ?></strong><?php if (!empty($financialSummary['show_projected_legal_penalty'])): ?><small><span class="badge muted">فعلاً اعمال نشده</span></small><?php endif; ?></article>
        <article><small>جمع جریمه قابل پرداخت واقعی</small><strong><?= money_toman($financialSummary['effective_penalty_payable_total'] ?? 0) ?></strong></article>
        <article><small>پاداش تسویه زودهنگام</small><strong><?= money_toman($financialSummary['early_settlement_reward_total'] ?? 0) ?></strong></article>
        <article><small>هزینه‌های حقوقی</small><strong><?= money_toman($financialSummary['legal_costs_total'] ?? 0) ?></strong></article>
        <article class="proma-financial-final"><small>مبلغ نهایی قابل دریافت امروز</small><strong><?= money_toman($financialSummary['final_collectable_amount'] ?? 0) ?></strong></article>
      </div>
    </div>
    <?php if (!empty($financialSummary['calculation_warnings'])): ?>
      <div class="notice warning">محاسبه مالی یک یا چند قسط نیازمند بررسی است. تا رفع هشدار، مبلغ جریمه حقوقی «صفر واقعی» تلقی نمی‌شود و ثبت پرداخت برای قسط‌های مربوطه متوقف است.</div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<div class="proma-contract-workspace" data-contract-tab-panel="installments" hidden>
  <?php if ($guarantorPeople): ?>
  <section class="card proma-contract-sidebar">
    <div class="card-header card-no-border"><div class="header-top"><h2>متن قرارداد</h2><?php if ($document): ?><button class="btn secondary small" type="button" data-contract-copy-textarea="resolved-contract-text"><i data-feather="copy"></i> کپی متن قرارداد تولیدشده</button><?php endif; ?></div></div>
    <div class="card-body">
      <?php if ($document): ?>
        <textarea id="resolved-contract-text" hidden><?= e(trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</h2>', '</h3>', '</li>', '</tr>'], ["\n", "\n", "\n", "\n\n", "\n\n", "\n\n", "\n", "\n"], (string) $document['rendered_body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></textarea>
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
    <?php if ($canManageActiveContract): ?>
      <div class="card-body">
        <form method="post" action="<?= e(url('contracts/saveDocument/' . $contract['id'])) ?>" class="form-grid">
          <?= csrf_field() ?>
          <label class="full">عنوان چاپی قرارداد<input name="rendered_title" value="<?= e($renderedDocumentTitle) ?>" required></label>
          <label class="full">هدر چاپی قرارداد<textarea name="rendered_header" rows="4"><?= e($renderedDocumentHeader) ?></textarea></label>
          <label class="full">ویرایش دستی متن قرارداد<textarea name="rendered_body" rows="18" data-rich-editor data-rich-editor-height="520" required><?= e($document['rendered_body'] ?? ContractDocument::render((int) $contract['id'])) ?></textarea></label>
          <label class="full">دلیل ویرایش<input name="change_reason" required placeholder="علت ویرایش نسخه نهایی"></label>
           <div class="actions"><button class="btn" type="submit">ذخیره نسخه نهایی</button></div>
         </form>
         <?php if (!empty($documentVersions)): ?>
           <div class="table-wrap mt-3"><table><thead><tr><th>نسخه</th><th>منبع</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
           <?php foreach ($documentVersions as $documentVersion): ?><tr><td>v<?= to_persian_digits($documentVersion['version_number']) ?></td><td><?= e($documentVersion['source']) ?></td><td><?= e(jdatetime($documentVersion['created_at'])) ?></td><td><?= !empty($documentVersion['is_finalized']) ? 'نهایی' : (!empty($documentVersion['is_published']) ? 'منتشرشده' : 'پیش‌نویس') ?></td><td class="actions"><?php if (empty($documentVersion['is_published'])): ?><form method="post" action="<?= e(url('contracts/publishDocumentVersion/' . (int) $documentVersion['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>"><button class="btn small secondary" type="submit">انتشار</button></form><?php endif; ?><?php if (empty($documentVersion['is_finalized'])): ?><form method="post" action="<?= e(url('contracts/finalizeDocumentVersion/' . (int) $documentVersion['id'])) ?>" onsubmit="return confirm('این نسخه نهایی شود و دیگر بازنویسی خودکار نشود؟')"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>"><button class="btn small warning" type="submit">نهایی‌سازی</button></form><?php endif; ?></td></tr><?php endforeach; ?>
           </tbody></table></div>
         <?php endif; ?>
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

<div class="grid cols-2" data-contract-tab-panel="files" hidden>
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
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <section class="card proma-contract-installments-card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <h2>اقساط</h2>
        <?php if ($canManageActiveContract): ?><button class="btn small" type="button" data-open-modal="add-contract-installment">افزودن قسط</button><?php endif; ?>
      </div>
    </div>
    <?php if ($canManageActiveContract): ?><form method="post" action="<?= e(url('contracts/bulkInstallmentAction/' . (int) $contract['id'])) ?>" id="installment-bulk-form"><?= csrf_field() ?><?php endif; ?>
    <div class="table-wrap">
      <table>
        <thead><tr><?php if ($canManageActiveContract): ?><th><input type="checkbox" data-check-all="installment_ids" aria-label="انتخاب همه اقساط"></th><?php endif; ?><th>قسط</th><th>سررسید</th><th>جزئیات مالی امروز</th><th>وضعیت</th><?php if ($canManageActiveContract): ?><th>عملیات</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($installments as $installment): ?>
          <tr>
            <?php if ($canManageActiveContract): ?><td><?php if (!empty($installment['payment_allowed'])): ?><input type="checkbox" name="installment_ids[]" value="<?= (int) $installment['id'] ?>" data-check-item="installment_ids" aria-label="انتخاب قسط <?= e($installment['installment_number']) ?>"><?php else: ?><span aria-label="قسط قابل پرداخت نیست">—</span><?php endif; ?></td><?php endif; ?>
            <td>
              <?= to_persian_digits($installment['installment_number']) ?>
              <?php if (!empty($installment['is_custom'])): ?>
                <span class="badge info">قسط دلخواه</span>
                <?php $contractCustomDescription = trim((string) ($installment['custom_description'] ?? $installment['notes'] ?? '')); ?>
                <?php if ($contractCustomDescription !== ''): ?><small class="proma-custom-installment-description">دلیل ایجاد: <?= e($contractCustomDescription) ?></small><?php endif; ?>
                <?php if ($canManageActiveContract && !empty($installment['internal_note'])): ?><small class="proma-custom-installment-description muted">یادداشت داخلی: <?= e($installment['internal_note']) ?></small><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= e(jdate($installment['due_date'])) ?></td>
            <td>
              <div class="proma-installment-financials">
                <span><small>مبلغ پایه</small><strong><?= money_toman($installment['base_amount']) ?></strong></span>
                <span><small>پرداخت‌شده</small><strong><?= money_toman($installment['effective_paid_principal'] ?? 0) ?></strong></span>
                <span><small>مانده اصل</small><strong><?= money_toman($installment['remaining_principal'] ?? 0) ?></strong></span>
                <span><small>جریمه عادی</small><strong><?= money_toman($installment['normal_penalty_accrued'] ?? 0) ?></strong></span>
                <span><small><?= !empty($installment['canonical_legal_referral_at']) ? 'جریمه حقوقی اعمال‌شده' : 'جریمه حقوقی احتمالی' ?></small><strong<?= empty($installment['canonical_legal_referral_at']) ? ' class="proma-projected-penalty"' : '' ?>><?= money_toman(!empty($installment['canonical_legal_referral_at']) ? ($installment['legal_penalty_accrued'] ?? 0) : ($installment['projected_legal_penalty'] ?? 0)) ?></strong></span>
                <span class="proma-installment-payable"><small>قابل پرداخت امروز</small><strong><?= money_toman($installment['final_payable'] ?? 0) ?></strong></span>
              </div>
              <?php if (!in_array((string) ($installment['calculation_status'] ?? 'calculated'), ['calculated', 'not_applicable'], true)): ?><small class="text-danger">نیازمند بررسی محاسبه حقوقی</small><?php endif; ?>
            </td>
            <td><span class="badge <?= e(badge_class($installment['status'])) ?>"><?= e(status_label($installment['status'])) ?></span></td>
            <?php if ($canManageActiveContract): ?>
              <td class="actions">
                <?php if (!empty($installment['payment_allowed'])): ?><button class="btn small success" type="button" data-open-modal="pay-installment-<?= (int) $installment['id'] ?>">پرداخت</button><?php endif; ?>
                <button class="btn small secondary" type="button" data-open-modal="edit-installment-<?= (int) $installment['id'] ?>">ویرایش قسط</button>
                <button class="btn small danger" type="button" data-open-modal="void-installment-<?= (int) $installment['id'] ?>">ابطال</button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$installments): ?><tr><td colspan="<?= $canManageActiveContract ? 6 : 4 ?>" class="empty">قسطی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canManageActiveContract): ?><div class="proma-payment-workspace mt-3"><div class="proma-payment-workspace__summary"><strong>پرداخت انتخاب‌شده‌ها</strong><span>قسط‌ها را انتخاب کنید؛ مبلغ قطعی فقط در سرور محاسبه و ثبت می‌شود.</span></div><div class="form-grid three"><input type="hidden" name="payment_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="quote_uuid" value=""><label>علت عملیات<input name="bulk_reason" required placeholder="علت ثبت پرداخت"></label><label>مبلغ پرداخت گروهی<input name="group_amount" data-money inputmode="numeric" placeholder="برای پرداخت گروهی"></label><label>روش پرداخت<select name="payment_method"><option value="manual">دستی</option><option value="card_transfer">کارت به کارت</option><option value="cash">نقدی</option><option value="pos">دستگاه کارت‌خوان</option><option value="bank_transfer">واریز بانکی</option><option value="check">چک</option></select></label><label>تاریخ پرداخت<input name="payment_date" value="<?= e(jdate(date('Y-m-d'))) ?>"></label><label>ساعت پرداخت<input name="payment_time" type="time" value="<?= e(date('H:i')) ?>"></label></div><div class="notice info">اولویت تخصیص: جریمه حقوقی، جریمه عادی و سپس اصل. ابطال هر قسط فقط از منوی امن همان قسط انجام می‌شود.</div><div class="actions"><button class="btn success" type="submit" name="bulk_action" value="payment_group">ثبت پرداخت انتخاب‌شده‌ها</button><button class="btn secondary" type="submit" name="bulk_action" value="recalculate">محاسبه مجدد وضعیت</button></div></div></form><?php endif; ?>
  </section>
</div>

<?php if ($isInternalViewer): ?>
  <section class="card proma-legal-card" data-contract-tab-panel="legal" hidden>
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
            <p>فقط هزینه‌های تأییدشده، قابل مطالبه و تسویه‌نشده در مبلغ امروز وارد می‌شوند.</p>
          <?php else: ?>
            <strong>فقط برای کاربران مجاز</strong>
            <p>نمایش مبلغ محدود شده است.</p>
          <?php endif; ?>
        </article>
      </div>

      <?php if ($canViewLegalCosts): ?>
        <section class="proma-legal-cost-table">
          <div class="header-top"><div><h3>هزینه‌های حقوقی</h3><p>هزینهٔ پیش‌نویس تا تأیید مدیریت وارد بدهی یا تسویه نمی‌شود.</p></div></div>
          <div class="proma-legal-cost-kpis">
            <span><small>کل</small><strong><?= money_toman($legalCostSummary['total_legal_costs'] ?? 0) ?></strong></span>
            <span><small>تأییدشده</small><strong><?= money_toman($legalCostSummary['approved_legal_costs'] ?? 0) ?></strong></span>
            <span><small>قابل مطالبه امروز</small><strong><?= money_toman($legalCostSummary['outstanding_chargeable_legal_costs'] ?? 0) ?></strong></span>
            <span><small>برگشت‌خورده</small><strong><?= money_toman($legalCostSummary['reversed_legal_costs'] ?? 0) ?></strong></span>
          </div>
          <div class="table-wrap"><table><thead><tr><th>عنوان</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th>قابل مطالبه</th><?php if (Auth::role() === 'admin'): ?><th>عملیات</th><?php endif; ?></tr></thead><tbody>
          <?php foreach ($legalCosts as $legalCost): ?>
            <tr><td><?= e($legalCost['title']) ?><small class="d-block"><?= e($legalCost['category']) ?></small></td><td><?= e(jdate($legalCost['cost_date'])) ?></td><td><?= money_toman($legalCost['amount_toman']) ?></td><td><span class="badge <?= ($legalCost['approval_status'] ?? '') === 'approved' ? 'success' : (($legalCost['approval_status'] ?? '') === 'reversed' ? 'danger' : 'warning') ?>"><?= e(($legalCost['approval_status'] ?? '') === 'approved' ? 'تأییدشده' : (($legalCost['approval_status'] ?? '') === 'reversed' ? 'برگشت‌خورده' : 'در انتظار تأیید')) ?></span></td><td><?= !empty($legalCost['chargeable_to_customer']) ? 'بله' : 'خیر' ?></td><?php if (Auth::role() === 'admin'): ?><td class="actions"><?php if (($legalCost['approval_status'] ?? '') === 'pending_approval'): ?><form method="post" action="<?= e(url('contracts/approveLegalCost/' . (int) $contract['id'] . '/' . (int) $legalCost['id'])) ?>"><?= csrf_field() ?><button class="btn success small" type="submit">تأیید</button></form><?php endif; ?><?php if (($legalCost['approval_status'] ?? '') !== 'reversed'): ?><button class="btn danger small" type="button" data-open-modal="reverse-legal-cost-<?= (int) $legalCost['id'] ?>">برگشت</button><?php endif; ?></td><?php endif; ?></tr>
          <?php endforeach; ?>
          <?php if (!$legalCosts): ?><tr><td colspan="<?= Auth::role() === 'admin' ? 6 : 5 ?>" class="empty">هزینه ثبت‌شده‌ای وجود ندارد.</td></tr><?php endif; ?>
          </tbody></table></div>
        </section>
      <?php endif; ?>

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

<?php if ($isInternalViewer && Auth::role() === 'admin'): ?>
  <?php foreach ($legalCosts as $legalCost): ?>
    <?php if (($legalCost['approval_status'] ?? '') !== 'reversed'): ?>
      <div class="modal" id="reverse-legal-cost-<?= (int) $legalCost['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>برگشت هزینه حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('contracts/reverseLegalCost/' . (int) $contract['id'] . '/' . (int) $legalCost['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice warning full"><?= e($legalCost['title']) ?> به مبلغ <?= money_toman($legalCost['amount_toman']) ?> از محاسبات آتی خارج می‌شود؛ سابقه آن حذف نمی‌شود.</div><label class="full required-field">علت برگشت<textarea name="reason" rows="3" required minlength="3"></textarea></label></div><div class="modal-footer"><button class="btn danger" type="submit">ثبت برگشت</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<section class="card" data-contract-tab-panel="payments" hidden>
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
<section class="card" data-contract-tab-panel="activity" hidden>
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

<?php if (!empty($operatorDebtCards)): ?>
  <section class="card proma-operator-debt-summary" data-contract-tab-panel="summary">
    <div class="card-header card-no-border">
      <div class="header-top">
        <div><h2>خلاصه بدهی برای پیگیری</h2><p>سناریوی حقوقی احتمالی، بدهی قابل وصول نیست و در مبلغ واقعی پرداخت امروز وارد نمی‌شود.</p></div>
        <span class="badge info">نمای اپراتور</span>
      </div>
    </div>
    <div class="card-body"><div class="proma-operator-debt-grid">
      <?php foreach ($operatorDebtCards as $debtCard): ?>
        <article class="<?= !empty($debtCard['emphasis']) ? 'proma-operator-debt-card--emphasis' : '' ?>">
          <small><?= e($debtCard['label']) ?></small>
          <strong<?= empty($debtCard['is_payable']) ? ' class="proma-projected-penalty"' : '' ?>><?= money_toman($debtCard['amount']) ?></strong>
          <span class="badge <?= !empty($debtCard['is_payable']) ? 'success' : 'muted' ?>"><?= e($debtCard['status']) ?></span>
        </article>
      <?php endforeach; ?>
    </div></div>
  </section>
<?php endif; ?>

<?php if ($canManageActiveContract): ?>
  <div class="modal" id="add-contract-installment">
    <div class="modal-content">
      <div class="modal-header"><h3>افزودن قسط جدید</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('installments/store')) ?>" data-disable-on-submit>
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <input type="hidden" name="redirect_to" value="contract">
          <label><span class="proma-form-label">قرارداد</span><input value="<?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?>" disabled></label>
          <label><span class="proma-form-label">سررسید</span><input name="due_date" value="<?= e(jdate(FinanceHelper::addMonths(date('Y-m-d'), 1))) ?>" required placeholder="۱۴۰۵/۰۱/۰۱"></label>
          <label><span class="proma-form-label">مبلغ پایه</span><input name="base_amount" data-money required></label>
          <label><span class="proma-form-label">عنوان قسط</span><input name="custom_title" maxlength="100" placeholder="مثلاً هزینه خدمات اضافه"></label>
          <label><span class="proma-form-label">شناسه ضمانت</span><input name="guarantee_serial" dir="ltr" placeholder="شماره چک یا سفته"></label>
          <label class="full"><span class="proma-form-label">توضیح قسط برای مشتری</span><textarea name="customer_description" required rows="3" placeholder="علت ایجاد این قسط را به زبان قابل نمایش برای مشتری بنویسید."></textarea><small class="proma-form-help">این توضیح در پنل مدیریت و پنل مشتری نمایش داده می‌شود.</small></label>
          <label class="full"><span class="proma-form-label">یادداشت داخلی</span><textarea name="internal_note" rows="2" placeholder="نکته داخلی برای مدیریت؛ این متن به مشتری نمایش داده نمی‌شود."></textarea><small class="proma-form-help">این یادداشت فقط برای کاربران مجاز مدیریت قابل مشاهده است.</small></label>
          <label class="proma-confirm-check full"><input type="checkbox" name="customer_visible" value="1" checked> توضیح قسط در پنل مشتری نمایش داده شود.</label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit" data-submit-label="در حال ثبت...">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <?php foreach ($installments as $installment): ?>
    <?php $installmentVersionToken = InstallmentChangeService::versionToken($installment); ?>
    <?php if (!empty($installment['payment_allowed'])): ?>
    <div class="modal" id="pay-installment-<?= (int) $installment['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>ثبت پرداخت قسط <?= to_persian_digits($installment['installment_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('installments/payment/' . $installment['id'])) ?>" data-payment-preview data-preview-url="<?= e(url('installments/previewPayment')) ?>" data-disable-on-submit>
          <div class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="contract">
            <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
            <input type="hidden" name="installment_id" value="<?= (int) $installment['id'] ?>">
            <input type="hidden" name="payment_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>">
            <label>مبلغ پرداختی<input name="amount" data-money required value="<?= e(number_format(normalize_money($installment['payable'] ?? $installment['remaining_amount'] ?? $installment['base_amount']), 0)) ?>"></label>
            <label>تاریخ پرداخت<input name="payment_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
            <label>ساعت پرداخت<input name="payment_time" type="time" value="<?= e(date('H:i')) ?>"></label>
            <label>روش پرداخت<select name="method"><option value="manual">پرداخت دستی</option></select></label>
            <label class="full">توضیحات<input name="description" placeholder="توضیحات پرداخت"></label>
          </div>
          <div class="modal-footer"><button class="btn success" type="submit">ثبت پرداخت</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="modal" id="edit-installment-<?= (int) $installment['id'] ?>">
      <div class="modal-content proma-modal-lg">
        <div class="modal-header"><h3>ویرایش امن قسط <?= to_persian_digits($installment['installment_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('contracts/changeInstallment/' . (int) $contract['id'])) ?>" data-disable-on-submit>
          <?= csrf_field() ?><input type="hidden" name="installment_id" value="<?= (int) $installment['id'] ?>"><input type="hidden" name="version_token" value="<?= e($installmentVersionToken) ?>">
          <div class="modal-body form-grid two">
            <div class="notice info full">مبلغ قبلی: <?= money_toman($installment['base_amount']) ?> | سررسید قبلی: <?= e(jdate($installment['due_date'])) ?> | مانده اصل: <?= money_toman($installment['remaining_principal'] ?? 0) ?></div>
            <label>تاریخ سررسید جدید<input name="due_date" value="<?= e(jdate($installment['due_date'])) ?>" required></label>
            <label>مبلغ جدید<input name="base_amount" data-money value="<?= e(number_format(normalize_money($installment['base_amount']), 0)) ?>" required></label>
            <label>عنوان قسط<input name="custom_title" maxlength="100" value="<?= e($installment['custom_title'] ?? '') ?>"></label>
            <label>روش اختلاف مبلغ<select name="difference_mode"><option value="transfer_to_last_unpaid">انتقال اختلاف به آخرین قسط پرداخت‌نشده</option><option value="selected_distribution">توزیع بین اقساط انتخاب‌شده (درخواست بررسی)</option><option value="contract_adjustment">ثبت اصلاحیه مستقل قرارداد (درخواست بررسی)</option><option value="manual_schedule">بازطراحی دستی برنامه (درخواست بررسی)</option></select></label>
            <label class="full">توضیح قابل نمایش<input name="custom_description" value="<?= e($installment['custom_description'] ?? $installment['notes'] ?? '') ?>"></label>
            <label class="full">یادداشت داخلی<textarea name="internal_note" rows="3"><?= e($installment['internal_note'] ?? '') ?></textarea></label>
            <label class="full required-field">علت تغییر<input name="reason" required minlength="3" placeholder="اثر مالی، علت تغییر و تأیید مسئول را بنویسید"></label>
          </div>
          <div class="modal-footer"><button class="btn" type="submit">بررسی و ثبت تغییر</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
        </form>
      </div>
    </div>

    <div class="modal" id="void-installment-<?= (int) $installment['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>لغو / ابطال امن قسط</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('contracts/voidInstallment/' . (int) $contract['id'])) ?>" data-disable-on-submit>
          <?= csrf_field() ?><input type="hidden" name="installment_id" value="<?= (int) $installment['id'] ?>"><input type="hidden" name="version_token" value="<?= e($installmentVersionToken) ?>">
          <div class="modal-body form-grid">
            <div class="notice warning full">قسط <?= to_persian_digits($installment['installment_number']) ?>، مبلغ <?= money_toman($installment['base_amount']) ?> و سررسید <?= e(jdate($installment['due_date'])) ?>. اگر پرداخت، پرونده حقوقی یا سند حسابداری داشته باشد، هیچ سابقه‌ای حذف نمی‌شود و فقط درخواست اصلاح برنامه ثبت خواهد شد.</div>
            <label class="full required-field">علت ابطال<textarea name="reason" required minlength="3" rows="3"></textarea></label>
            <label class="full required-field">برای تأیید دقیقاً بنویسید: <strong><?= e('ابطال قسط شماره ' . to_persian_digits($installment['installment_number'])) ?></strong><input name="typed_confirmation" required autocomplete="off"></label>
            <label class="proma-confirm-check full"><input type="checkbox" name="confirm_void" value="1" required> اثر ابطال بر مانده قرارداد و پیگیری‌ها را بررسی کردم.</label>
          </div>
          <div class="modal-footer"><button class="btn danger" type="submit">تأیید ابطال</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
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
                <label>هزینه مرتبط<input name="cost_amount" data-money value="<?= e(number_format(normalize_money($log['cost_amount'] ?? 0), 0)) ?>"></label>
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
          <form method="post" action="<?= e(url('contracts/retireLegalLog/' . (int) $log['id'])) ?>">
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
