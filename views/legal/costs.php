<?php
$costs = $costs ?? [];
$status = $status ?? 'pending_approval';
$companyPayment = $companyPayment ?? 'all';
$search = $search ?? '';
$canManageCosts = !empty($canManageCosts);
$thirdPartyLedgerReady = !empty($thirdPartyLedgerReady);
$statusLabels = [
    'pending_approval' => 'در انتظار تأیید',
    'approved' => 'تأییدشده',
    'reversed' => 'برگشت‌خورده',
];
$statusClass = [
    'pending_approval' => 'warning',
    'approved' => 'success',
    'reversed' => 'danger',
];
$companyStatusLabels = [
    'awaiting_approval' => ['در انتظار تأیید هزینه', 'warning'],
    'lawyer_reimbursement' => ['بازپرداخت به وکیل', 'info'],
    'unpaid' => ['پرداخت به مرجع نشده', 'warning'],
    'partial' => ['پرداخت جزئی به مرجع', 'info'],
    'paid' => ['پرداخت مرجع کامل', 'success'],
    'not_payable' => ['مبلغ قابل پرداخت ندارد', 'muted'],
];
?>

<section class="card proma-legal-cost-approval">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2><?= $canManageCosts ? 'تأیید هزینه‌های حقوقی و پرداخت به مرجع' : 'هزینه‌های حقوقی پرونده‌های من' ?></h2>
        <p><?= $canManageCosts ? 'هزینه را ابتدا تأیید کنید؛ سپس پرداخت انجام‌شده به مرجع را با تاریخ و شناسه پیگیری ثبت کنید. ثبت پرداخت مرجع، بدهی یا دریافتی مشتری را تغییر نمی‌دهد.' : 'هزینه‌های ثبت‌شده تا زمان تأیید مدیریت وارد مبلغ قابل مطالبه مشتری نمی‌شوند.' ?></p>
      </div>
      <span class="badge <?= e($statusClass[$status] ?? 'info') ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
    </div>
  </div>
  <?php if ($canManageCosts): ?>
    <div class="card-body proma-legal-cost-shortcuts" aria-label="صف‌های مدیریت هزینه حقوقی">
      <a class="btn<?= $status === 'pending_approval' && $companyPayment === 'all' ? ' success' : ' secondary' ?>" href="<?= e(url('legal/costs', ['status' => 'pending_approval'])) ?>">هزینه‌های در انتظار تأیید</a>
      <?php if ($thirdPartyLedgerReady): ?>
        <a class="btn<?= $companyPayment === 'unpaid' ? ' success' : ' secondary' ?>" href="<?= e(url('legal/costs', ['status' => 'approved', 'company_payment' => 'unpaid'])) ?>">پرداخت‌نشده به مرجع</a>
        <a class="btn<?= $companyPayment === 'partial' ? ' success' : ' secondary' ?>" href="<?= e(url('legal/costs', ['status' => 'approved', 'company_payment' => 'partial'])) ?>">پرداخت جزئی</a>
        <a class="btn<?= $companyPayment === 'paid' ? ' success' : ' secondary' ?>" href="<?= e(url('legal/costs', ['status' => 'approved', 'company_payment' => 'paid'])) ?>">پرداخت کامل</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="card-body">
    <?php if (empty($registryReady)): ?>
      <div class="notice warning">ساختار هزینه‌های حقوقی هنوز نصب نشده است. ابتدا بسته بروزرسانی پایگاه داده را اجرا کنید.</div>
    <?php else: ?>
      <?php if ($canManageCosts && $companyPayment !== 'all' && !$thirdPartyLedgerReady): ?>
        <div class="notice warning">دفتر پرداخت به مرجع نصب نشده است؛ برای جلوگیری از نمایش ماندهٔ نادرست، صف پرداخت قابل نمایش نیست.</div>
      <?php endif; ?>
      <form method="get" action="<?= e(url('legal/costs')) ?>" class="proma-filter-bar">
        <div class="proma-filter-bar__fields">
          <label class="proma-filter-bar__search">جستجو
            <input name="q" value="<?= e($search) ?>" placeholder="قرارداد، مشتری، عنوان یا نوع هزینه">
          </label>
          <label>وضعیت
            <select name="status">
              <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= selected($status, $key) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if ($canManageCosts && $thirdPartyLedgerReady): ?>
            <label>پرداخت مجموعه به مرجع
              <select name="company_payment">
                <option value="all"<?= selected($companyPayment, 'all') ?>>همه</option>
                <option value="unpaid"<?= selected($companyPayment, 'unpaid') ?>>پرداخت‌نشده</option>
                <option value="partial"<?= selected($companyPayment, 'partial') ?>>پرداخت جزئی</option>
                <option value="paid"<?= selected($companyPayment, 'paid') ?>>پرداخت کامل</option>
              </select>
            </label>
          <?php endif; ?>
        </div>
        <div class="proma-filter-bar__actions">
          <button class="btn" type="submit">اعمال فیلتر</button>
          <a class="btn secondary" href="<?= e(url('legal/costs')) ?>">پاک‌سازی</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!empty($registryReady)): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th scope="col">قرارداد و مشتری</th>
            <th scope="col">هزینه</th>
            <th scope="col">گردش مالی هزینه</th>
            <th scope="col">ثبت‌کننده</th>
            <th scope="col">تاریخ</th>
            <th scope="col">وضعیت</th>
            <?php if ($canManageCosts && $thirdPartyLedgerReady): ?><th scope="col">وضعیت پرداخت به مرجع</th><?php endif; ?>
            <th scope="col">عملیات</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($costs as $cost): ?>
          <?php $costId = (int) $cost['id']; $thirdPartyPayments = $cost['third_party_payments'] ?? []; $reversedDisbursements = []; foreach ($thirdPartyPayments as $ledgerEntry) { if (!empty($ledgerEntry['reversal_of_id'])) $reversedDisbursements[(int) $ledgerEntry['reversal_of_id']] = true; } ?>
          <tr>
            <td>
              <strong><?= e($cost['contract_number'] ?? '-') ?></strong>
              <small><?= e($cost['customer_name'] ?? '-') ?></small>
            </td>
            <td>
              <strong><?= money_toman($cost['amount_toman'] ?? 0) ?></strong>
              <small><?= e(($cost['category'] ?? 'سایر') . ' · ' . ($cost['title'] ?? 'هزینه حقوقی')) ?></small>
            </td>
            <td>
              <strong>وصول از مشتری: <?= money_toman($cost['paid_amount_toman'] ?? 0) ?></strong>
              <small>پرداخت مجموعه به مرجع: <?= money_toman($cost['company_paid_amount_toman'] ?? 0) ?></small>
              <small>مانده قابل وصول از مشتری: <?= money_toman($cost['customer_receivable_toman'] ?? 0) ?></small>
              <?php if (!empty($cost['reimburse_lawyer_id'])): ?><small>متقاضی بازپرداخت: <?= e($cost['reimbursement_lawyer_name'] ?? 'وکیل پرونده') ?></small><?php endif; ?>
              <?php if (!empty($cost['third_party_payments'])): ?>
                <details class="mt-1"><summary>تاریخچه پرداخت به مرجع</summary>
                  <ul class="proma-legal-cost-ledger">
                    <?php foreach ($cost['third_party_payments'] as $entry): ?>
                      <li>
                        <span><?= !empty($entry['is_reversal']) ? 'برگشت' : 'پرداخت' ?> · <?= e(jdate($entry['paid_at'] ?? '')) ?></span>
                        <strong><?= money_toman(abs(normalize_money($entry['amount_toman'] ?? 0))) ?></strong>
                        <small><?= e($entry['recipient'] ?? '') ?><?= !empty($entry['payment_reference']) ? ' · ' . e($entry['payment_reference']) : '' ?><?= !empty($entry['created_by_name']) ? ' · ' . e($entry['created_by_name']) : '' ?></small>
                        <?php if (!empty($entry['reversal_reason'])): ?><small>علت برگشت: <?= e($entry['reversal_reason']) ?></small><?php endif; ?>
                        <?php if ($canManageCosts && empty($entry['is_reversal']) && empty($reversedDisbursements[(int) $entry['id']])): ?><button class="btn small danger" type="button" data-open-modal="legal-disbursement-reverse-<?= (int) $entry['id'] ?>">ثبت برگشت</button><?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($cost['created_by_name'] ?? 'سیستم') ?></strong>
              <small><?= e($cost['description'] ?? '') ?></small>
            </td>
            <td><?= e(jdate($cost['cost_date'] ?? $cost['created_at'] ?? '')) ?></td>
            <td>
              <span class="badge <?= e($statusClass[$cost['approval_status'] ?? ''] ?? 'muted') ?>"><?= e($statusLabels[$cost['approval_status'] ?? ''] ?? ($cost['approval_status'] ?? '-')) ?></span>
              <?php if (!empty($cost['approved_by_name'])): ?><small>توسط <?= e($cost['approved_by_name']) ?></small><?php endif; ?>
            </td>
            <?php if ($canManageCosts && $thirdPartyLedgerReady): ?>
              <?php [$companyStatusLabel, $companyStatusClass] = $companyStatusLabels[$cost['company_payment_status'] ?? ''] ?? ['نامشخص', 'muted']; ?>
              <td><span class="badge <?= e($companyStatusClass) ?>"><?= e($companyStatusLabel) ?></span><small>پرداخت‌شده <?= money_toman(max(0, $cost['company_paid_amount_toman'] ?? 0)) ?> · مانده <?= money_toman($cost['company_payment_remaining_toman'] ?? 0) ?></small></td>
            <?php endif; ?>
            <td>
              <div class="proma-table-actions">
                <a class="btn small secondary" href="<?= e(url('contracts/show/' . (int) ($cost['contract_id'] ?? 0) . '#legal')) ?>">قرارداد</a>
                <?php if ($canManageCosts && !empty($cost['can_pay_to_authority'])): ?>
                  <button class="btn small secondary" type="button" data-open-modal="legal-cost-third-party-<?= $costId ?>">ثبت پرداخت به مرجع</button>
                <?php endif; ?>
                <?php if ($canManageCosts && ($cost['approval_status'] ?? '') === 'approved' && empty($cost['reimburse_lawyer_id']) && !empty($cost['assigned_lawyer_id'])): ?>
                  <form method="post" action="<?= e(url('legal/markLawyerReimbursement/' . $costId)) ?>">
                    <?= csrf_field() ?>
                    <button class="btn small secondary" type="submit">ثبت طلب وکیل</button>
                  </form>
                <?php endif; ?>
                <?php if ($canManageCosts && ($cost['approval_status'] ?? '') === 'pending_approval'): ?>
                  <form method="post" action="<?= e(url('legal/approveCost/' . $costId)) ?>">
                    <?= csrf_field() ?>
                    <button class="btn small success" type="submit">تأیید</button>
                  </form>
                <?php endif; ?>
                <?php if ($canManageCosts && ($cost['approval_status'] ?? '') !== 'reversed'): ?>
                  <button class="btn small danger" type="button" data-open-modal="legal-cost-reverse-<?= $costId ?>">برگشت</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$costs): ?><tr><td colspan="<?= $canManageCosts && $thirdPartyLedgerReady ? 8 : 7 ?>" class="empty">هزینه‌ای مطابق فیلتر انتخاب‌شده وجود ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($canManageCosts): foreach ($costs as $cost): ?>
  <?php $costId = (int) $cost['id']; ?>
  <?php if (($cost['approval_status'] ?? '') !== 'reversed'): ?>
  <div class="modal" id="legal-cost-reverse-<?= $costId ?>">
    <div class="modal-content">
      <div class="modal-header">
        <h3>برگشت هزینه حقوقی</h3>
        <button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button>
      </div>
      <form method="post" action="<?= e(url('legal/reverseCost/' . $costId)) ?>">
        <?= csrf_field() ?>
        <div class="modal-body form-grid">
          <div class="notice warning">این عملیات هزینه را حذف فیزیکی نمی‌کند؛ فقط آن را از محاسبات قابل مطالبه خارج و سابقه ممیزی ثبت می‌کند.</div>
          <label>علت برگشت
            <textarea name="reason" rows="4" required minlength="3"></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn danger" type="submit">ثبت برگشت</button>
          <button class="btn secondary" type="button" data-close-modal>انصراف</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <?php if (!empty($cost['can_pay_to_authority'])): ?>
    <div class="modal" id="legal-cost-third-party-<?= $costId ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h3>ثبت پرداخت مجموعه به مرجع ثالث</h3>
          <button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button>
        </div>
        <form method="post" action="<?= e(url('legal/recordThirdPartyCostPayment/' . $costId)) ?>">
          <?= csrf_field() ?>
          <div class="modal-body form-grid two">
            <div class="notice info full">این فرم پرداخت بانکی را آغاز نمی‌کند؛ پرداخت انجام‌شدهٔ مجموعه به مرجع را در دفتر هزینه ثبت می‌کند و مبلغ وصول‌شده یا مانده بدهی مشتری را تغییر نمی‌دهد. سقف باقیمانده: <?= money_toman($cost['company_payment_remaining_toman'] ?? 0) ?>.</div>
            <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(24))) ?>">
            <label>مبلغ پرداخت‌شده<input name="amount_toman" data-money inputmode="numeric" required max="<?= (int) ($cost['company_payment_remaining_toman'] ?? 0) ?>"></label>
            <label>مرجع یا دریافت‌کننده<input name="recipient" maxlength="190" required></label>
            <label>تاریخ پرداخت<input name="paid_at" data-jalali-input inputmode="numeric" value="<?= e(jdate(date('Y-m-d'))) ?>" required></label>
            <label>شناسه پیگیری<input name="payment_reference" maxlength="190"></label>
            <label class="full">شرح<textarea name="note" rows="3" maxlength="2000"></textarea></label>
          </div>
          <div class="modal-footer"><button class="btn success" type="submit">ثبت پرداخت مجموعه</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>
  <?php $thirdPartyPayments = $cost['third_party_payments'] ?? []; $reversedDisbursements = []; foreach ($thirdPartyPayments as $ledgerEntry) { if (!empty($ledgerEntry['reversal_of_id'])) $reversedDisbursements[(int) $ledgerEntry['reversal_of_id']] = true; } ?>
  <?php foreach ($thirdPartyPayments as $entry): ?>
    <?php if (!empty($entry['is_reversal']) || !empty($reversedDisbursements[(int) $entry['id']])) continue; ?>
    <div class="modal" id="legal-disbursement-reverse-<?= (int) $entry['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>برگشت پرداخت به مرجع ثالث</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><?= proma_icon('close') ?></button></div>
        <form method="post" action="<?= e(url('legal/reverseThirdPartyCostPayment/' . (int) $entry['id'])) ?>">
          <?= csrf_field() ?>
          <div class="modal-body form-grid"><div class="notice warning">این رویداد حذف نمی‌شود؛ یک رویداد برگشت ممیزی‌پذیر ثبت خواهد شد و ماندهٔ بدهی مشتری تغییر نمی‌کند.</div><label>علت برگشت<textarea name="reason" rows="3" required minlength="3"></textarea></label></div>
          <div class="modal-footer"><button class="btn danger" type="submit">ثبت برگشت پرداخت</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; endif; ?>
