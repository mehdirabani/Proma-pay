<section class="card">
  <div class="card-header"><h2>فیلتر سررسید</h2></div>
  <div class="card-body">
    <div class="tabs">
      <a class="tab-link <?= !$bucket ? 'active' : '' ?>" href="<?= e(url('overdue')) ?>">همه</a>
      <a class="tab-link <?= $bucket === 'today' ? 'active' : '' ?>" href="<?= e(url('overdue', ['bucket' => 'today'])) ?>">امروز</a>
      <a class="tab-link <?= $bucket === '1-7' ? 'active' : '' ?>" href="<?= e(url('overdue', ['bucket' => '1-7'])) ?>">۱ تا ۷ روز گذشته</a>
      <a class="tab-link <?= $bucket === '8-30' ? 'active' : '' ?>" href="<?= e(url('overdue', ['bucket' => '8-30'])) ?>">۸ تا ۳۰ روز گذشته</a>
      <a class="tab-link <?= $bucket === '30+' ? 'active' : '' ?>" href="<?= e(url('overdue', ['bucket' => '30+'])) ?>">بیش از ۳۰ روز گذشته</a>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>اقساط نیازمند اقدام</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>مشتری و تماس</th><th>قرارداد</th><th>سررسید</th><th>جریمه</th><th>قابل پرداخت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($installments as $item): ?>
        <tr>
          <td>
            <strong><?= e($item['customer_name']) ?></strong><br>
            <span class="badge info"><?= to_persian_digits($item['mobile']) ?></span>
            <?php if ($item['secondary_phone']): ?><span class="badge muted"><?= to_persian_digits($item['secondary_phone']) ?></span><?php endif; ?>
            <?php if (!empty($item['legal_case_count']) || ($item['legal_status'] ?? '') === 'referred'): ?><span class="badge danger">شکایت شده</span><?php endif; ?>
          </td>
          <td><?= e($item['contract_number']) ?></td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= money_toman($item['penalty']) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td class="actions">
            <button class="btn small secondary" type="button" data-open-modal="details-<?= (int) $item['id'] ?>">مشاهده</button>
            <a class="btn small success" href="tel:<?= e($item['mobile']) ?>">شماره اول</a>
            <?php if (!empty($item['secondary_phone'])): ?><a class="btn small info" href="tel:<?= e($item['secondary_phone']) ?>">شماره دوم</a><?php endif; ?>
            <?php if (!empty($item['guarantor_mobile'])): ?><a class="btn small warning" href="tel:<?= e($item['guarantor_mobile']) ?>">ضامن</a><?php endif; ?>
            <?php if (Auth::role() === 'admin'): ?>
              <button class="btn small" type="button" data-open-modal="manual-overdue-<?= (int) $item['id'] ?>">پرداخت دستی</button>
              <button class="btn small warning" type="button" data-open-modal="discount-<?= (int) $item['id'] ?>">اصلاحیه</button>
              <button class="btn small info" type="button" data-open-modal="operator-<?= (int) $item['id'] ?>">ارسال به اپراتور</button>
            <?php endif; ?>
            <button class="btn small danger" type="button" data-open-modal="lawyer-<?= (int) $item['id'] ?>">ارجاع به شکایت</button>
          </td>
        </tr>
        <div class="modal" id="details-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>جزئیات قسط</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <div class="modal-body grid cols-2">
              <div><strong>مشتری:</strong> <?= e($item['customer_name']) ?></div>
              <div><strong>کد ملی:</strong> <?= to_persian_digits($item['national_id']) ?></div>
              <div><strong>موبایل:</strong> <?= to_persian_digits($item['mobile']) ?></div>
              <div><strong>تلفن دوم:</strong> <?= to_persian_digits($item['secondary_phone']) ?></div>
              <div><strong>ضامن:</strong> <?= e($item['guarantor_name'] ?: '-') ?> <?= $item['guarantor_mobile'] ? ' - ' . to_persian_digits($item['guarantor_mobile']) : '' ?></div>
              <div><strong>مبلغ پایه:</strong> <?= money_toman($item['base_amount']) ?></div>
              <div><strong>پرداخت شده:</strong> <?= money_toman($item['paid_amount']) ?></div>
              <div><strong>پاداش:</strong> <?= money_toman($item['reward']) ?></div>
              <div><strong>جریمه:</strong> <?= money_toman($item['penalty']) ?></div>
            </div>
            <div class="modal-footer"><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
          </div>
        </div>
        <div class="modal" id="manual-overdue-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>ثبت پرداخت دستی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('installments/payment/' . $item['id'])) ?>" data-payment-preview data-preview-url="<?= e(url('installments/previewPayment')) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="redirect_to" value="overdue">
                <label>مبلغ<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>"></label>
                <label>تاریخ پرداخت<input name="payment_date" value="<?= e(jdate(date('Y-m-d'))) ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
                <label>ساعت پرداخت<input name="payment_time" type="time" value="<?= e(date('H:i')) ?>" required></label>
                <label class="full">شرح<input name="description" value="پرداخت دستی"></label>
                <div class="proma-preview-grid full">
                  <span><small>مانده قبل پرداخت</small><strong data-payment-remaining-before><?= money_toman($item['remaining_amount'] ?? max(0, (float) $item['base_amount'] - (float) $item['paid_amount'])) ?></strong></span>
                  <span><small>جریمه تاریخ انتخابی</small><strong data-payment-penalty><?= money_toman($item['penalty']) ?></strong></span>
                  <span><small>پاداش تاریخ انتخابی</small><strong data-payment-reward><?= money_toman($item['reward']) ?></strong></span>
                  <span><small>قابل پرداخت</small><strong data-payment-payable><?= money_toman($item['payable']) ?></strong></span>
                  <span><small>مانده پس از پرداخت</small><strong data-payment-remaining-after>۰ تومان</strong></span>
                </div>
                <div class="notice info full" data-payment-message>محاسبه پرداخت بر اساس تاریخ انتخابی انجام می‌شود.</div>
              </div>
              <div class="modal-footer"><button class="btn" type="submit">ثبت پرداخت</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
        <div class="modal" id="discount-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>اصلاحیه جریمه</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('overdue/discount/' . $item['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <label>نوع تخفیف<select name="discount_type"><option value="fixed">مبلغ ثابت</option><option value="percent">درصدی</option></select></label>
                <label>مقدار<input name="discount_value" data-money required></label>
              </div>
              <div class="modal-footer"><button class="btn warning" type="submit">ثبت اصلاحیه</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
        <div class="modal" id="operator-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>ارسال به اپراتور</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('overdue/assignOperator/' . $item['id'])) ?>">
              <div class="modal-body"><?= csrf_field() ?><label>اپراتور<select name="operator_id" required><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label></div>
              <div class="modal-footer"><button class="btn info" type="submit">ارسال</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
        <div class="modal" id="lawyer-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>ارسال به واحد حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('overdue/sendLawyer/' . $item['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <label>وکیل<select name="lawyer_id"><option value="">بدون تعیین وکیل</option><?php foreach ($lawyers as $lawyer): ?><option value="<?= (int) $lawyer['id'] ?>"><?= e($lawyer['full_name']) ?></option><?php endforeach; ?></select></label>
                <label class="full">شرح<textarea name="notes"></textarea></label>
              </div>
              <div class="modal-footer"><button class="btn danger" type="submit">ثبت پرونده</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$installments): ?><tr><td colspan="6" class="empty">قسطی در این بازه وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
