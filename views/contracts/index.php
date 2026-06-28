<?php
$readOnly = $readOnly ?? false;
$contractTrendLabels = [];
$contractTrendStart = (new DateTime('first day of this month'))->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $contractTrendLabels[] = mb_substr(jdate((clone $contractTrendStart)->modify('+' . $i . ' months')->format('Y-m-01')), 0, 7, 'UTF-8');
}
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2><?= $readOnly ? 'قراردادهای ضمانت شده' : 'فهرست قراردادها' ?></h2>
      <?php if (!$readOnly): ?><button class="btn" type="button" data-open-modal="create-contract">افزودن قرارداد</button><?php endif; ?>
    </div>
  </div>
  <?php if ($contracts): ?>
    <div class="card-body pt-0">
      <div class="proma-contract-card-grid">
        <?php foreach (array_slice($contracts, 0, 6) as $cardContract): ?>
          <?php
          $stats = Contract::installmentStats((int) $cardContract['id']);
          $trend = Payment::monthlyTrendForContract((int) $cardContract['id']);
          $remainingCount = max(0, (int) $stats['total'] - (int) $stats['paid']);
          ?>
          <article class="proma-contract-card">
            <div class="proma-contract-card-main">
              <span class="proma-contract-badge"><?= e($cardContract['contract_number']) ?></span>
              <h6><?= e($cardContract['customer_name']) ?></h6>
              <p><?= money_toman($cardContract['principal_amount']) ?></p>
            </div>
            <div class="proma-contract-stats four">
              <span><strong><?= to_persian_digits($stats['total']) ?></strong><small>کل اقساط</small></span>
              <span><strong><?= to_persian_digits($stats['paid']) ?></strong><small>پرداخت‌شده</small></span>
              <span><strong><?= to_persian_digits($remainingCount) ?></strong><small>باقی‌مانده</small></span>
              <span><strong><?= to_persian_digits($stats['overdue']) ?></strong><small>معوق</small></span>
            </div>
            <div class="proma-mini-chart" aria-label="روند پرداخت اقساط">
              <?php if (array_sum($trend) > 0): ?>
                <canvas data-chart="mini-line" data-title="روند پرداخت" data-labels='<?= e(json_encode($contractTrendLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($trend)) ?>'></canvas>
              <?php else: ?>
                <div class="proma-empty-mini">پرداخت موفقی برای نمودار ثبت نشده است.</div>
              <?php endif; ?>
            </div>
            <div class="proma-contract-card-footer">
              <span>مانده: <?= money_toman($stats['outstanding']) ?></span>
              <a href="<?= e(url('contracts/booklet/' . $cardContract['id'])) ?>" target="_blank">دفترچه</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>سود</th><th>اقساط</th><th>ضامنان</th><th>وضعیت</th><?php if (!$readOnly): ?><th>عملیات</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($contracts as $contract): ?>
        <?php $guarantors = Contract::guarantors($contract['id']); ?>
        <tr>
          <td><?= e($contract['contract_number']) ?></td>
          <td><?= e($contract['customer_name']) ?><br><span class="badge muted"><?= to_persian_digits($contract['mobile']) ?></span></td>
          <td><?= money_toman($contract['principal_amount']) ?></td>
          <td><?= percent_label($contract['monthly_interest_rate']) ?>، <?= $contract['interest_type'] === 'compound' ? 'مرکب' : 'ساده' ?></td>
          <td><?= to_persian_digits($contract['months']) ?></td>
          <td><?= $guarantors ? e(implode('، ', array_column($guarantors, 'full_name'))) : 'ندارد' ?></td>
          <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
          <?php if (!$readOnly): ?>
          <td class="actions">
            <button class="btn small secondary" type="button" data-open-modal="edit-contract-<?= (int) $contract['id'] ?>">ویرایش</button>
            <button class="btn small info" type="button" data-open-modal="custom-installment-<?= (int) $contract['id'] ?>">قسط دلخواه</button>
            <a class="btn small success" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ دفترچه</a>
            <button class="btn small danger" type="button" data-open-modal="delete-contract-<?= (int) $contract['id'] ?>">حذف</button>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="<?= $readOnly ? 7 : 8 ?>" class="empty">قراردادی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if (!$readOnly): ?>
<div class="modal" id="create-contract">
  <div class="modal-content proma-modal-xl">
    <div class="modal-header"><h3>افزودن قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('contracts/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>پیشوند قرارداد<input name="prefix" value="<?= e($settings['contract_prefix'] ?? 'Pr') ?>" dir="ltr"></label>
        <label>مشتری
          <input data-select-filter="customer-select" placeholder="جست‌وجوی نام، کد ملی یا موبایل">
          <select id="customer-select" name="customer_id">
            <option value="">انتخاب مشتری موجود</option>
            <?php foreach ($customers as $customer): ?>
              <option value="<?= (int) $customer['id'] ?>"><?= e($customer['full_name']) ?> - <?= to_persian_digits($customer['national_id']) ?> - <?= to_persian_digits($customer['mobile']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>اپراتور پیگیری
          <select name="assigned_operator_id"><option value="">بدون ارجاع</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"><?= e($operator['full_name']) ?></option><?php endforeach; ?></select>
        </label>
        <label>نام مشتری تازه<input name="new_customer_full_name" placeholder="در صورت نبودن مشتری"></label>
        <label>کد ملی مشتری تازه<input name="new_customer_national_id" inputmode="numeric"></label>
        <label>موبایل مشتری تازه<input name="new_customer_mobile" inputmode="tel"></label>
        <label>تلفن دوم مشتری تازه<input name="new_customer_secondary_phone" inputmode="tel"></label>
        <label>مبلغ اصل قرارداد<input name="principal_amount" data-money required placeholder="مبلغ به تومان"></label>
        <label>نرخ سود ماهانه<input name="monthly_interest_rate" required inputmode="decimal" placeholder="درصد"></label>
        <label>تعداد اقساط<input name="months" required inputmode="numeric" value="12"></label>
        <label>تاریخ شروع<input name="start_date" required value="<?= e($defaultStartDate ?? '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
        <label>نخستین سررسید<input name="first_due_date" required value="<?= e($defaultFirstDueDate ?? '') ?>" placeholder="۱۴۰۳/۰۲/۰۱"></label>
        <div class="full">
          <span class="field-title">نوع سود</span>
          <div class="switch-options" data-exclusive>
            <label><input type="checkbox" name="interest_type" value="simple" checked><span>ساده ماهانه</span></label>
            <label><input type="checkbox" name="interest_type" value="compound"><span>مرکب ماهانه</span></label>
          </div>
        </div>
        <label class="full">ضامنان
          <input data-select-filter="guarantor-select" placeholder="جست‌وجوی ضامن">
          <select id="guarantor-select" name="guarantors[]" multiple>
            <?php foreach ($customers as $customer): ?>
              <option value="<?= (int) $customer['id'] ?>"><?= e($customer['full_name']) ?> - <?= to_persian_digits($customer['national_id']) ?> - <?= to_persian_digits($customer['mobile']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="full">یادداشت<textarea name="notes"></textarea></label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت و ساخت اقساط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

<?php foreach ($contracts as $contract): ?>
  <?php $guarantors = Contract::guarantors($contract['id']); ?>
  <div class="modal" id="edit-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content proma-modal-xl">
      <div class="modal-header"><h3>ویرایش قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/update/' . $contract['id'])) ?>">
        <div class="modal-body form-grid three">
          <?= csrf_field() ?>
          <label>مشتری<select name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= selected($contract['customer_id'], $customer['id']) ?>><?= e($customer['full_name']) ?></option><?php endforeach; ?></select></label>
          <label>اپراتور<select name="assigned_operator_id"><option value="">بدون ارجاع</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"<?= selected($contract['assigned_operator_id'], $operator['id']) ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label>
          <label>مبلغ اصل<input name="principal_amount" data-money value="<?= e(number_format((float) $contract['principal_amount'], 0)) ?>"></label>
          <label>نرخ سود ماهانه<input name="monthly_interest_rate" value="<?= e($contract['monthly_interest_rate']) ?>"></label>
          <label>تعداد اقساط<input name="months" value="<?= e($contract['months']) ?>"></label>
          <label>تاریخ شروع<input name="start_date" value="<?= e(jdate($contract['start_date'])) ?>"></label>
          <label>نخستین سررسید<input name="first_due_date" value="<?= e(jdate($contract['first_due_date'])) ?>"></label>
          <div class="full">
            <span class="field-title">نوع سود</span>
            <div class="switch-options" data-exclusive>
              <label><input type="checkbox" name="interest_type" value="simple"<?= checked($contract['interest_type'], 'simple') ?>><span>ساده ماهانه</span></label>
              <label><input type="checkbox" name="interest_type" value="compound"<?= checked($contract['interest_type'], 'compound') ?>><span>مرکب ماهانه</span></label>
            </div>
          </div>
          <label class="full">ضامنان<select name="guarantors[]" multiple><?php $selectedGuarantors = array_column($guarantors, 'id'); foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= in_array($customer['id'], $selectedGuarantors) ? ' selected' : '' ?>><?= e($customer['full_name']) ?></option><?php endforeach; ?></select></label>
          <label class="full">یادداشت<textarea name="notes"><?= e($contract['notes']) ?></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="custom-installment-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>افزودن قسط دلخواه</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('installments/store')) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <input type="hidden" name="redirect_to" value="contracts">
          <label>قرارداد<input value="<?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?>" disabled></label>
          <label>سررسید<input name="due_date" value="<?= e($defaultFirstDueDate ?? '') ?>" required placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>مبلغ پایه<input name="base_amount" data-money required></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="delete-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تأیید دو مرحله‌ای حذف</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/delete/' . $contract['id'])) ?>">
        <div class="modal-body">
          <?= csrf_field() ?>
          <p>برای حذف قرارداد <?= e($contract['contract_number']) ?> عبارت «حذف قطعی» را وارد کنید.</p>
          <label>عبارت تأیید<input name="confirm_text" required></label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit">حذف قرارداد</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
