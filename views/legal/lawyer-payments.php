<?php
$isAdmin = !empty($isAdmin);
$registryReady = !empty($registryReady);
$lawyerSummaries = $lawyerSummaries ?? [];
$selectedLawyerId = (int) ($selectedLawyerId ?? 0);
$selectedSummary = $selectedSummary ?? null;
$outstandingCosts = $outstandingCosts ?? [];
$paymentBatches = $paymentBatches ?? [];
$adjustments = $adjustments ?? [];
$signedBalance = (int) ($selectedSummary['balance_toman'] ?? 0);
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2><?= $isAdmin ? 'تسویه حساب وکلا' : 'صورت‌حساب من' ?></h2>
        <p>هزینه‌های حقوقی تأییدشده‌ای که وکیل پرداخت کرده، پرداخت‌های مدیریت به وکیل و اصلاحات مانده در این دفتر مستقل ثبت می‌شود.</p>
      </div>
      <span class="badge info">دفتر تسویه وکلا</span>
    </div>
  </div>
  <div class="card-body">
    <?php if (!$registryReady): ?>
      <div class="notice warning">ساختار دفتر تسویه وکلا نصب نشده است. بستهٔ بروزرسانی نسخهٔ 7.0.4 را اجرا کنید.</div>
    <?php elseif ($isAdmin): ?>
      <form method="get" action="<?= e(url('legal/lawyerPayments')) ?>" class="proma-filter-bar">
        <div class="proma-filter-bar__fields">
          <label>انتخاب وکیل
            <select name="lawyer_id" required>
              <?php foreach ($lawyerSummaries as $lawyer): ?>
                <option value="<?= (int) $lawyer['lawyer_id'] ?>"<?= selected($selectedLawyerId, (int) $lawyer['lawyer_id']) ?>><?= e($lawyer['lawyer_name']) ?><?= ($lawyer['lawyer_status'] ?? '') !== 'active' ? ' · غیرفعال' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="proma-filter-bar__actions"><button class="btn" type="submit">نمایش صورت‌حساب</button></div>
      </form>
      <?php if (!$lawyerSummaries): ?><div class="notice info">حساب وکیلی برای نمایش وجود ندارد.</div><?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php if ($registryReady && $selectedSummary): ?>
  <section class="card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <div><h2><?= e($selectedSummary['lawyer_name'] ?? 'وکیل') ?></h2><p>مانده‌ها بر پایهٔ هزینه‌های تأییدشده و گردش‌های ثبت‌شده در دفتر محاسبه می‌شوند.</p></div>
        <span class="badge <?= $signedBalance >= 0 ? 'success' : 'warning' ?>"><?= $signedBalance > 0 ? 'بستانکار' : ($signedBalance < 0 ? 'بدهکار' : 'تسویه') ?> · <?= money_toman(abs($signedBalance)) ?></span>
      </div>
    </div>
    <div class="card-body">
      <div class="proma-legal-summary">
        <article><span>جمع هزینه‌های تأییدشده به نفع وکیل</span><strong><?= money_toman($selectedSummary['claimed_toman'] ?? 0) ?></strong></article>
        <article><span>پرداخت خالص مدیریت به وکیل</span><strong><?= money_toman(max(0, (int) ($selectedSummary['paid_toman'] ?? 0))) ?></strong></article>
        <article><span>مانده هزینه‌های قابل بازپرداخت</span><strong><?= money_toman($selectedSummary['pending_reimbursement_toman'] ?? 0) ?></strong></article>
        <article><span>بستانکاری دستی</span><strong><?= money_toman($selectedSummary['credit_toman'] ?? 0) ?></strong></article>
        <article><span>بدهکاری دستی</span><strong><?= money_toman($selectedSummary['debit_toman'] ?? 0) ?></strong></article>
        <article><span>ماندهٔ نهایی حساب</span><strong><?= $signedBalance < 0 ? '−' : '' ?><?= money_toman(abs($signedBalance)) ?></strong></article>
      </div>
      <div class="notice info mt-2">بستانکاری دستی به مبلغی اضافه می‌کند که شرکت به وکیل بدهکار است؛ بدهکاری دستی آن را کم می‌کند. ثبت این گردش‌ها، وصول هزینه از مشتری را تغییر نمی‌دهد.</div>
    </div>
  </section>

  <?php if ($isAdmin): ?>
    <section class="card">
      <div class="card-header card-no-border">
        <div><h2>ثبت پرداخت یکجا به وکیل</h2><p>پس از انجام واریز واقعی، هزینه‌های مربوط را انتخاب کنید تا پرداخت به آن‌ها تخصیص یابد.</p></div>
        <span class="badge warning"><?= count($outstandingCosts) ?> هزینهٔ باز</span>
      </div>
      <?php if ($outstandingCosts): ?>
        <form method="post" action="<?= e(url('legal/recordLawyerPayment')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="lawyer_id" value="<?= $selectedLawyerId ?>">
          <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(24))) ?>">
          <div class="table-wrap">
            <table>
              <thead><tr><th>انتخاب</th><th>قرارداد و مشتری</th><th>هزینه</th><th>تاریخ</th><th>مبلغ تسویه (تومان)</th></tr></thead>
              <tbody>
              <?php foreach ($outstandingCosts as $cost): $costId = (int) $cost['id']; $due = normalize_money($cost['outstanding_toman'] ?? 0); ?>
                <tr>
                  <td><input type="checkbox" name="cost_ids[]" value="<?= $costId ?>" checked aria-label="انتخاب هزینه <?= e($cost['title'] ?? '') ?>"></td>
                  <td><strong><?= e($cost['contract_number'] ?? '-') ?></strong><small><?= e($cost['customer_name'] ?? '-') ?></small></td>
                  <td><strong><?= e($cost['title'] ?? 'هزینه حقوقی') ?></strong><small><?= e($cost['category'] ?? '') ?></small></td>
                  <td><?= e(jdate($cost['cost_date'] ?? '')) ?></td>
                  <td><input name="amounts[<?= $costId ?>]" data-money inputmode="numeric" value="<?= $due ?>" min="1" max="<?= $due ?>" required></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-body form-grid two">
            <label>تاریخ پرداخت<input name="paid_at" data-jalali-input required value="<?= e(jdate(date('Y-m-d'))) ?>"></label>
            <label>شناسه پیگیری یا حواله<input name="payment_reference" maxlength="190"></label>
            <label class="full">شرح پرداخت<textarea name="note" rows="2" maxlength="2000" placeholder="مثلاً شماره رسید بانکی یا توضیح پرداخت گروهی"></textarea></label>
          </div>
          <div class="card-footer"><button class="btn success" type="button" data-open-modal="confirm-lawyer-payment">مرور و ثبت پرداخت</button></div>
          <div class="modal" id="confirm-lawyer-payment">
            <div class="modal-content">
              <div class="modal-header"><h3>تأیید ثبت پرداخت وکیل</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
              <div class="modal-body"><div class="notice warning">این ثبت به هزینه‌های انتخاب‌شده تخصیص می‌یابد. پیش از ادامه، واریز واقعی به وکیل را انجام دهید و شناسه پیگیری را وارد کنید.</div></div>
              <div class="modal-footer"><button class="btn success" type="submit">تأیید و ثبت پرداخت</button><button class="btn secondary" type="button" data-close-modal>بازبینی</button></div>
            </div>
          </div>
        </form>
      <?php else: ?>
        <div class="card-body"><div class="notice success">هزینهٔ تأییدشده و باز برای بازپرداخت به این وکیل وجود ندارد.</div></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-header card-no-border"><div><h2>ثبت بستانکاری یا بدهکاری</h2><p>برای مواردی که به یک هزینهٔ مشخص مربوط نیستند، رویداد مستقل و قابل برگشت ثبت کنید.</p></div></div>
      <form method="post" action="<?= e(url('legal/addLawyerAdjustment')) ?>">
        <?= csrf_field() ?><input type="hidden" name="lawyer_id" value="<?= $selectedLawyerId ?>">
        <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(24))) ?>">
        <div class="card-body form-grid two">
          <label>نوع رویداد<select name="entry_type" required><option value="credit">بستانکاری وکیل؛ افزایش طلب از شرکت</option><option value="debit">بدهکاری وکیل؛ کاهش طلب از شرکت</option></select></label>
          <label>مبلغ (تومان)<input name="amount_toman" data-money inputmode="numeric" min="1" required></label>
          <label>تاریخ رویداد<input name="effective_at" data-jalali-input value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
          <label class="full">شرح و مستند<textarea name="description" rows="3" maxlength="2000" required minlength="3"></textarea></label>
        </div>
        <div class="card-footer"><button class="btn" type="submit">ثبت رویداد حساب</button></div>
      </form>
    </section>
  <?php endif; ?>

  <section class="card">
    <div class="card-header card-no-border"><div><h2>تاریخچه پرداخت به وکیل</h2><p>پرداخت‌های تکی، گروهی و رویدادهای برگشت در همین دفتر نگهداری می‌شوند.</p></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>نوع رویداد</th><th>مبلغ (تومان)</th><th>تاریخ</th><th>شناسه پیگیری</th><th>شرح</th><th>ثبت‌کننده</th><?php if ($isAdmin): ?><th>عملیات</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($paymentBatches as $batch): ?>
        <tr>
          <td><?= !empty($batch['is_reversal']) ? 'برگشت پرداخت' : 'پرداخت به وکیل' ?><?= !empty($batch['allocation_count']) ? ' · ' . (int) $batch['allocation_count'] . ' هزینه' : '' ?></td>
          <td><strong><?= money_toman(abs((int) ($batch['amount_toman'] ?? 0))) ?></strong></td>
          <td><?= e(jdate($batch['paid_at'] ?? '')) ?></td>
          <td><?= e($batch['payment_reference'] ?? '-') ?></td>
          <td>
            <?= e(!empty($batch['reversal_reason']) ? $batch['reversal_reason'] : ($batch['note'] ?? '-')) ?>
            <?php if (!empty($batch['allocations'])): ?><details class="mt-1"><summary>تفکیک هزینه‌ها</summary><ul class="proma-legal-cost-ledger"><?php foreach ($batch['allocations'] as $allocation): ?><li><span><?= e($allocation['contract_number'] ?? '-') ?> · <?= e($allocation['title'] ?? 'هزینه حقوقی') ?></span><strong><?= money_toman(abs((int) ($allocation['allocated_amount_toman'] ?? 0))) ?></strong></li><?php endforeach; ?></ul></details><?php endif; ?>
          </td>
          <td><?= e($batch['created_by_name'] ?? 'سامانه') ?></td>
          <?php if ($isAdmin): ?><td><?php if (empty($batch['is_reversal']) && empty($batch['has_reversal'])): ?><button class="btn small danger" type="button" data-open-modal="lawyer-payment-reverse-<?= (int) $batch['id'] ?>">ثبت برگشت</button><?php else: ?>—<?php endif; ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$paymentBatches): ?><tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" class="empty">پرداختی برای این وکیل ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><div><h2>تاریخچه بستانکاری و بدهکاری</h2><p>اصلاح هر رویداد با ثبت معکوس انجام می‌شود و سابقه اصلی باقی می‌ماند.</p></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>نوع</th><th>مبلغ (تومان)</th><th>تاریخ</th><th>شرح</th><th>ثبت‌کننده</th><?php if ($isAdmin): ?><th>عملیات</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($adjustments as $entry): ?>
        <?php $entryType = (string) ($entry['entry_type'] ?? ''); $entryLabel = $entryType === 'credit' ? 'بستانکاری وکیل · افزایش طلب از شرکت' : ($entryType === 'debit' ? 'بدهکاری وکیل · کاهش طلب از شرکت' : ((($entry['reversed_entry_type'] ?? '') === 'credit') ? 'برگشت بستانکاری' : 'برگشت بدهکاری')); ?>
        <tr>
          <td><?= e($entryLabel) ?></td>
          <td><strong><?= money_toman(abs((int) ($entry['signed_amount_toman'] ?? 0))) ?></strong></td>
          <td><?= e(jdate($entry['effective_at'] ?? '')) ?></td>
          <td><?= nl2br(e($entry['description'] ?? '')) ?></td>
          <td><?= e($entry['created_by_name'] ?? 'سامانه') ?></td>
          <?php if ($isAdmin): ?><td><?php if (in_array($entryType, ['credit', 'debit'], true) && empty($entry['has_reversal'])): ?><button class="btn small danger" type="button" data-open-modal="lawyer-adjustment-reverse-<?= (int) $entry['id'] ?>">برگشت رویداد</button><?php else: ?>—<?php endif; ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$adjustments): ?><tr><td colspan="<?= $isAdmin ? 6 : 5 ?>" class="empty">رویداد دستی برای این وکیل ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <?php if ($isAdmin): foreach ($paymentBatches as $batch): ?>
    <?php if (empty($batch['is_reversal']) && empty($batch['has_reversal'])): ?>
      <div class="modal" id="lawyer-payment-reverse-<?= (int) $batch['id'] ?>">
        <div class="modal-content"><div class="modal-header"><h3>برگشت پرداخت وکیل</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
          <form method="post" action="<?= e(url('legal/reverseLawyerPayment/' . (int) $batch['id'])) ?>">
            <?= csrf_field() ?><input type="hidden" name="lawyer_id" value="<?= $selectedLawyerId ?>">
            <div class="modal-body form-grid"><div class="notice warning">رویداد پرداخت حذف نمی‌شود؛ یک برگشت مالی ثبت می‌شود و مبلغ هزینه‌های پرداخت‌نشده دوباره در مانده می‌آید.</div><label>علت برگشت<textarea name="reason" rows="3" required minlength="3"></textarea></label></div>
            <div class="modal-footer"><button class="btn danger" type="submit">ثبت برگشت پرداخت</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; foreach ($adjustments as $entry): ?>
    <?php if (in_array((string) ($entry['entry_type'] ?? ''), ['credit', 'debit'], true) && empty($entry['has_reversal'])): ?>
      <div class="modal" id="lawyer-adjustment-reverse-<?= (int) $entry['id'] ?>">
        <div class="modal-content"><div class="modal-header"><h3>برگشت رویداد حساب وکیل</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
          <form method="post" action="<?= e(url('legal/reverseLawyerAdjustment/' . (int) $entry['id'])) ?>">
            <?= csrf_field() ?><input type="hidden" name="lawyer_id" value="<?= $selectedLawyerId ?>">
            <div class="modal-body form-grid"><div class="notice warning">رویداد قبلی حفظ می‌شود و یک مبلغ معکوس با علت زیر ثبت خواهد شد.</div><label>علت برگشت<textarea name="reason" rows="3" required minlength="3"></textarea></label></div>
            <div class="modal-footer"><button class="btn danger" type="submit">ثبت برگشت رویداد</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; endif; ?>
<?php endif; ?>
