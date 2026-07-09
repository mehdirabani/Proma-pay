<?php
$search = trim((string) ($search ?? ($_GET['q'] ?? '')));
$defaultPaymentTime = date('H:i');
$singleOperator = count($operators ?? []) === 1 ? $operators[0] : null;
?>
<section class="card">
  <div class="card-header"><h2>فیلتر سررسید</h2></div>
  <div class="card-body">
    <form method="get" action="<?= e(url('overdue')) ?>" class="form-grid three" style="margin-bottom:14px" data-ajax-filter data-ajax-target="[data-ajax-results='overdue']">
      <input type="hidden" name="route" value="overdue">
      <?php if ($bucket): ?><input type="hidden" name="bucket" value="<?= e($bucket) ?>"><?php endif; ?>
      <label class="full">جستجو در مشتری و قرارداد
        <input name="q" value="<?= e($search) ?>" placeholder="نام مشتری، شماره قرارداد، کد ملی یا شماره تماس">
      </label>
      <div class="actions"><button class="btn secondary" type="submit">جستجو</button><span class="proma-ajax-status" data-ajax-status></span></div>
    </form>
    <div class="tabs">
      <a class="tab-link <?= !$bucket ? 'active' : '' ?>" href="<?= e(url('overdue', array_filter(['q' => $search ?: null]))) ?>">همه</a>
      <a class="tab-link <?= $bucket === 'today' ? 'active' : '' ?>" href="<?= e(url('overdue', array_filter(['bucket' => 'today', 'q' => $search ?: null]))) ?>">امروز</a>
      <a class="tab-link <?= $bucket === '1-7' ? 'active' : '' ?>" href="<?= e(url('overdue', array_filter(['bucket' => '1-7', 'q' => $search ?: null]))) ?>">۱ تا ۷ روز گذشته</a>
      <a class="tab-link <?= $bucket === '8-30' ? 'active' : '' ?>" href="<?= e(url('overdue', array_filter(['bucket' => '8-30', 'q' => $search ?: null]))) ?>">۸ تا ۳۰ روز گذشته</a>
      <a class="tab-link <?= $bucket === '30+' ? 'active' : '' ?>" href="<?= e(url('overdue', array_filter(['bucket' => '30+', 'q' => $search ?: null]))) ?>">بیش از ۳۰ روز گذشته</a>
    </div>
  </div>
</section>

<div data-ajax-results="overdue">
<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>اقساط نیازمند اقدام</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>مشتری و تماس</th><th>قرارداد</th><th>سررسید</th><th>جریمه</th><th>قابل پرداخت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($installments as $item): ?>
        <?php
          $guarantors = Contract::guarantors((int) $item['contract_id']);
          $guarantorContacts = Contract::guarantorContacts((int) $item['contract_id']);
        ?>
        <tr>
          <td>
            <strong><?= e($item['customer_name']) ?></strong><br>
            <span class="badge info"><?= to_persian_digits($item['mobile']) ?></span>
            <?php if ($item['secondary_phone']): ?><span class="badge muted"><?= to_persian_digits($item['secondary_phone']) ?></span><?php endif; ?>
          </td>
          <td>
            <?= e($item['contract_number']) ?>
            <?php if ((int) ($item['legal_case_count'] ?? 0) > 0): ?><span class="badge danger">شکایت شده</span><?php endif; ?>
          </td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= penalty_display_html($item) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td class="actions">
            <a class="icon-btn" href="<?= e(url('chat', ['contact' => $item['customer_id']])) ?>" title="باز کردن چت با مشتری"><i data-feather="message-square"></i></a>
            <button class="btn small secondary" type="button" data-open-modal="details-<?= (int) $item['id'] ?>">مشاهده</button>
            <?php if (Auth::role() === 'admin'): ?>
              <button class="btn small" type="button" data-open-modal="manual-overdue-<?= (int) $item['id'] ?>">پرداخت دستی</button>
              <button class="btn small warning" type="button" data-open-modal="discount-<?= (int) $item['id'] ?>">اصلاحیه</button>
              <button class="btn small info" type="button" data-open-modal="operator-<?= (int) $item['id'] ?>">ارسال به اپراتور</button>
              <button class="btn small danger" type="button" data-open-modal="lawyer-<?= (int) $item['id'] ?>">ارجاع حقوقی</button>
            <?php else: ?>
              <button class="btn small success" type="button" data-open-modal="call-<?= (int) $item['id'] ?>">تماس با مشتری</button>
              <button class="btn small danger" type="button" data-open-modal="lawyer-<?= (int) $item['id'] ?>">ارسال به واحد حقوقی</button>
              <button class="btn small info" type="button" data-open-modal="followup-<?= (int) $item['id'] ?>">ثبت نتیجه پیگیری</button>
            <?php endif; ?>
          </td>
        </tr>
        <div class="modal" id="call-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>تماس با مشتری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <div class="modal-body">
              <div class="proma-contact-list">
                <div class="proma-contact-row">
                  <span><strong><?= e($item['customer_name']) ?></strong><small>مشتری</small></span>
                  <a class="btn small success" href="tel:<?= e(to_english_digits($item['mobile'])) ?>"><?= to_persian_digits($item['mobile']) ?></a>
                </div>
                <?php if (!empty($item['secondary_phone'])): ?>
                  <div class="proma-contact-row">
                    <span><strong><?= e($item['customer_name']) ?></strong><small>شماره دوم مشتری</small></span>
                    <a class="btn small success" href="tel:<?= e(to_english_digits($item['secondary_phone'])) ?>"><?= to_persian_digits($item['secondary_phone']) ?></a>
                  </div>
                <?php endif; ?>
                <?php foreach ($guarantorContacts as $contact): ?>
                  <div class="proma-contact-row">
                    <span><strong><?= e($contact['full_name']) ?></strong><small><?= e($contact['relationship'] ?: 'ضامن') ?></small></span>
                    <a class="btn small warning" href="tel:<?= e(to_english_digits($contact['mobile'])) ?>"><?= to_persian_digits($contact['mobile']) ?></a>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($guarantorContacts)): ?><div class="empty">شماره ضامنی برای این قرارداد ثبت نشده است.</div><?php endif; ?>
              </div>
            </div>
            <div class="modal-footer">
              <form method="post" action="<?= e(url('overdue/callLog/' . $item['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="notes" value="تماس از modal سررسید گذشته"><button class="btn success" type="submit">ثبت در سوابق</button></form>
              <button class="btn secondary" type="button" data-close-modal>بستن</button>
            </div>
          </div>
        </div>
        <div class="modal" id="details-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>جزئیات قسط</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <div class="modal-body grid cols-2">
              <div><strong>مشتری:</strong> <?= e($item['customer_name']) ?></div>
              <div><strong>کد ملی:</strong> <?= to_persian_digits($item['national_id']) ?></div>
              <div><strong>موبایل:</strong> <?= to_persian_digits($item['mobile']) ?></div>
              <div><strong>تلفن دوم:</strong> <?= to_persian_digits($item['secondary_phone']) ?></div>
              <div class="full"><strong>ضامنان:</strong>
                <?php if ($guarantors): ?>
                  <?php foreach ($guarantors as $guarantor): ?>
                    <span class="badge muted"><?= e($guarantor['full_name']) ?> - <?= to_persian_digits($guarantor['mobile']) ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="badge muted">ثبت نشده</span>
                <?php endif; ?>
              </div>
              <div><strong>مبلغ پایه:</strong> <?= money_toman($item['base_amount']) ?></div>
              <div><strong>پرداخت شده:</strong> <?= money_toman($item['paid_amount']) ?></div>
              <div><strong>پاداش:</strong> <?= money_toman($item['reward']) ?></div>
              <div><strong>جریمه:</strong> <?= penalty_display_html($item) ?></div>
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
                <label>ساعت پرداخت<input name="payment_time" type="time" value="<?= e($defaultPaymentTime) ?>" required></label>
                <label class="full">شرح<input name="description" value="پرداخت دستی"></label>
                <div class="proma-preview-grid full">
                  <span><small>مانده قبل پرداخت</small><strong data-payment-remaining-before><?= money_toman($item['remaining_amount'] ?? max(0, (float) $item['base_amount'] - (float) $item['paid_amount'])) ?></strong></span>
                  <span><small>جریمه تاریخ انتخابی</small><span class="proma-preview-amount" data-payment-penalty><?= penalty_display_html($item) ?></span></span>
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
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <?php if ($singleOperator): ?>
                  <label class="full">اپراتور<input value="<?= e($singleOperator['full_name']) ?>" disabled><input type="hidden" name="operator_id" value="<?= (int) $singleOperator['id'] ?>"></label>
                <?php else: ?>
                  <label class="full">اپراتور
                    <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'operator'])) ?>">
                      <input data-user-search-input placeholder="جستجوی نام، موبایل یا واحد اپراتور">
                      <input type="hidden" name="operator_id" data-user-id-input>
                      <span class="proma-live-results" data-user-search-results hidden></span>
                      <span class="proma-chip-row" data-user-chip></span>
                    </span>
                  </label>
                <?php endif; ?>
              </div>
              <div class="modal-footer"><button class="btn info" type="submit">ارسال</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
        <div class="modal" id="followup-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>ثبت نتیجه پیگیری</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('overdue/followup/' . $item['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <label>نتیجه تماس<input name="call_result" required placeholder="مثلاً مشتری وعده پرداخت داد"></label>
                <label>تاریخ وعده پرداخت<input name="promise_payment_date" placeholder="۱۴۰۳/۰۱/۰۱"></label>
                <label class="full">توضیحات<textarea name="notes"></textarea></label>
              </div>
              <div class="modal-footer"><button class="btn info" type="submit">ثبت نتیجه</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
        <div class="modal" id="lawyer-<?= (int) $item['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>ارسال به واحد حقوقی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('overdue/sendLawyer/' . $item['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <label>وکیل
                  <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'lawyer'])) ?>">
                    <input data-user-search-input autocomplete="off" placeholder="نام، موبایل یا واحد وکیل">
                    <input type="hidden" name="lawyer_id" data-user-id-input>
                    <span class="proma-live-results" data-user-search-results hidden></span>
                    <span class="proma-chip-row" data-user-chip></span>
                  </span>
                </label>
                <label>دلیل ارجاع به حقوقی<input name="reason" required></label>
                <label class="full">توضیحات تکمیلی<textarea name="notes"></textarea></label>
              </div>
              <div class="modal-footer"><button class="btn danger" type="submit">ثبت ارجاع حقوقی</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$installments): ?><tr><td colspan="6" class="empty">قسطی در این بازه وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
</div>
