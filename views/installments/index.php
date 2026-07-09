<?php
$customerMode = $customerMode ?? false;
$paymentSettings = $paymentSettings ?? Settings::allKeyed();
$zibalEnabled = (string) ($paymentSettings['zibal_enabled'] ?? '1') === '1';
$zibalTestMode = (string) ($paymentSettings['zibal_test_mode'] ?? '0') === '1';
$zibalMerchant = trim((string) ($paymentSettings['zibal_merchant'] ?? ''));
$gatewayReady = $zibalEnabled && ($zibalTestMode || $zibalMerchant !== '');
$cardTransferEnabled = (string) ($paymentSettings['card_transfer_enabled'] ?? '1') === '1';
$cardTransferBankName = trim((string) ($paymentSettings['card_transfer_bank_name'] ?? ''));
$cardTransferBankLogoText = payment_card_logo_mark($cardTransferBankName, $paymentSettings['card_transfer_bank_logo_text'] ?? '');
$cardTransferAccountName = trim((string) ($paymentSettings['card_transfer_account_name'] ?? ''));
$cardTransferCardNumber = normalize_card_number($paymentSettings['card_transfer_card_number'] ?? '');
$cardTransferSheba = normalize_sheba($paymentSettings['card_transfer_sheba'] ?? '');
$cardTransferAccountNumber = normalize_account_number($paymentSettings['card_transfer_account_number'] ?? '');
$cardTransferPrimaryColor = sanitize_hex_color($paymentSettings['card_transfer_primary_color'] ?? '#7366ff', '#7366ff');
$cardTransferSecondaryColor = sanitize_hex_color($paymentSettings['card_transfer_secondary_color'] ?? '#16c7f9', '#16c7f9');
$cardTransferShowSheba = (string) ($paymentSettings['card_transfer_show_sheba'] ?? '1') === '1';
$cardTransferShowAccountNumber = (string) ($paymentSettings['card_transfer_show_account_number'] ?? '0') === '1';
$cardTransferQrPayload = trim((string) ($paymentSettings['card_transfer_qr_text'] ?? ''));
$cardTransferQrPayloadB64 = $cardTransferQrPayload !== '' ? base64_encode($cardTransferQrPayload) : '';
$defaultPaymentMethod = $gatewayReady ? 'gateway' : ($cardTransferEnabled ? 'card-transfer' : '');
$defaultPaymentDate = jdate(date('Y-m-d'));
$defaultPaymentTime = date('H:i');
$pagination = $pagination ?? ['total' => count($installments), 'page' => 1, 'pages' => 1, 'per_page' => count($installments) ?: 20];
$pageUrl = function ($page) {
    $params = $_GET;
    unset($params['route']);
    $params['page'] = $page;
    return url('installments', $params);
};
?>
<?php if (!$customerMode): ?>
<section class="card">
  <div class="card-header card-no-border"><h2>جستجو و فیلتر اقساط</h2></div>
  <div class="card-body">
    <form method="get" action="<?= e(url('installments')) ?>" class="form-grid four proma-installment-filters" data-ajax-filter data-ajax-target="[data-ajax-results='installments']">
      <input type="hidden" name="route" value="installments">
      <label>جستجوی کلی<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، قرارداد، موبایل یا کد ملی"></label>
      <label>نام مشتری<input name="customer_name" value="<?= e($_GET['customer_name'] ?? '') ?>"></label>
      <label>شماره قرارداد<input name="contract_number" value="<?= e($_GET['contract_number'] ?? '') ?>" dir="ltr"></label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <?php foreach (['pending', 'partial', 'paid', 'overdue'] as $status): ?>
            <option value="<?= e($status) ?>"<?= selected($_GET['status'] ?? '', $status) ?>><?= e(status_label($status)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <details class="full proma-filter-details" <?= !empty($_GET['mobile']) || !empty($_GET['national_id']) || !empty($_GET['due_from']) || !empty($_GET['due_to']) || !empty($_GET['amount_min']) || !empty($_GET['amount_max']) || !empty($_GET['payment_state']) ? 'open' : '' ?>>
        <summary>فیلترهای پیشرفته</summary>
        <div class="form-grid four">
          <label>شماره تماس<input name="mobile" value="<?= e($_GET['mobile'] ?? '') ?>" inputmode="tel"></label>
          <label>کد ملی<input name="national_id" value="<?= e($_GET['national_id'] ?? '') ?>" inputmode="numeric"></label>
          <label>سررسید از<input name="due_from" value="<?= e($_GET['due_from'] ?? '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>سررسید تا<input name="due_to" value="<?= e($_GET['due_to'] ?? '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>مبلغ از<input name="amount_min" value="<?= e($_GET['amount_min'] ?? '') ?>" data-money></label>
          <label>مبلغ تا<input name="amount_max" value="<?= e($_GET['amount_max'] ?? '') ?>" data-money></label>
          <label>نوع فیلتر
            <select name="payment_state">
              <option value="">همه اقساط</option>
              <option value="paid"<?= selected($_GET['payment_state'] ?? '', 'paid') ?>>پرداخت‌شده</option>
              <option value="unpaid"<?= selected($_GET['payment_state'] ?? '', 'unpaid') ?>>پرداخت‌نشده</option>
              <option value="overdue"<?= selected($_GET['payment_state'] ?? '', 'overdue') ?>>معوق</option>
              <option value="custom"<?= selected($_GET['payment_state'] ?? '', 'custom') ?>>سفارشی</option>
            </select>
          </label>
        </div>
      </details>
      <div class="actions full">
        <button class="btn secondary" type="submit">اعمال فیلتر</button>
        <a class="btn small secondary" href="<?= e(url('installments')) ?>">حذف فیلترها</a>
        <span class="badge info">نتایج: <?= to_persian_digits($pagination['total'] ?? 0) ?></span>
        <span class="proma-ajax-status" data-ajax-status></span>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>
<div data-ajax-results="installments">
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2><?= $customerMode ? 'اقساط قابل پرداخت' : 'فهرست اقساط' ?></h2>
      <?php if (!$customerMode): ?><button class="btn" type="button" data-open-modal="create-installment">افزودن قسط سفارشی</button><?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>قرارداد</th><th>مشتری</th><th>قسط</th><th>سررسید</th><th>مبلغ پایه</th><th>جریمه</th><th>پاداش</th><th>پرداخت شده</th><th>قابل پرداخت</th><th>وضعیت</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($installments as $item): ?>
        <tr>
          <td><?= e($item['contract_number']) ?></td>
          <td><?= e($item['customer_name']) ?></td>
          <td><?= to_persian_digits($item['installment_number']) ?></td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= money_toman($item['base_amount']) ?></td>
          <td><?= penalty_display_html($item) ?></td>
          <td><?= money_toman($item['reward']) ?></td>
          <td><?= money_toman($item['paid_amount']) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <?php if ($customerMode): ?>
              <?php if ((float) ($item['payable'] ?? 0) > 0): ?>
                <button class="btn small success" type="button" data-open-modal="pay-<?= (int) $item['id'] ?>">پرداخت</button>
              <?php endif; ?>
            <?php else: ?>
              <button class="btn small secondary" type="button" data-open-modal="manual-<?= (int) $item['id'] ?>">پرداخت دستی</button>
              <button class="btn small warning" type="button" data-open-modal="adjust-<?= (int) $item['id'] ?>">تنظیم</button>
              <form method="post" action="<?= e(url('installments/markPaid/' . $item['id'])) ?>"><?= csrf_field() ?><button class="btn small success" type="submit">تسویه</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$installments): ?><tr><td colspan="11" class="empty">قسطی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($customerMode): ?>
  <?php foreach ($installments as $item): ?>
    <?php if ((float) ($item['payable'] ?? 0) <= 0): continue; endif; ?>
    <div class="modal" id="pay-<?= (int) $item['id'] ?>">
      <div class="modal-content proma-modal-lg">
        <div class="modal-header">
          <h3>پرداخت قسط <?= to_persian_digits($item['installment_number']) ?></h3>
          <button class="icon-btn" type="button" data-close-modal>×</button>
        </div>
        <div class="modal-body">
          <div class="proma-payment-method-shell" data-payment-method-shell>
            <?php if ($defaultPaymentMethod === ''): ?>
              <div class="notice warning">در حال حاضر روش پرداخت فعالی برای این سامانه تنظیم نشده است.</div>
            <?php else: ?>
              <div class="proma-payment-method-switch" role="tablist" aria-label="روش پرداخت">
              <?php if ($gatewayReady): ?>
                <button class="<?= $defaultPaymentMethod === 'gateway' ? 'active' : '' ?>" type="button" data-payment-method-toggle="gateway" aria-selected="<?= $defaultPaymentMethod === 'gateway' ? 'true' : 'false' ?>">
                  <i data-feather="credit-card"></i>
                  <span>پرداخت آنلاین</span>
                </button>
              <?php endif; ?>
              <?php if ($cardTransferEnabled): ?>
                <button class="<?= $defaultPaymentMethod === 'card-transfer' ? 'active' : '' ?>" type="button" data-payment-method-toggle="card-transfer" aria-selected="<?= $defaultPaymentMethod === 'card-transfer' ? 'true' : 'false' ?>">
                  <i data-feather="smartphone"></i>
                  <span>کارت به کارت</span>
                </button>
              <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($gatewayReady): ?>
            <div class="proma-payment-panel <?= $defaultPaymentMethod === 'gateway' ? 'active' : '' ?>" data-payment-method-panel="gateway"<?= $defaultPaymentMethod === 'gateway' ? '' : ' hidden' ?>>
              <form method="post" action="<?= e(url('payments/zibal')) ?>" class="proma-payment-form">
                <?= csrf_field() ?>
                <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
                <div class="form-grid">
                  <label>مبلغ پرداختی<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
                  <label class="full">توضیحات<input name="description" value="پرداخت آنلاین قسط"></label>
                  <div class="proma-preview-grid full">
                    <span><small>مبلغ امروز</small><strong><?= money_toman($item['payable']) ?></strong></span>
                    <span><small>جریمه امروز</small><span class="proma-preview-amount"><?= penalty_display_html($item) ?></span></span>
                    <span><small>پاداش امروز</small><strong><?= money_toman($item['reward']) ?></strong></span>
                    <span><small>مانده قبل پرداخت</small><strong><?= money_toman($item['remaining_amount'] ?? max(0, (float) $item['base_amount'] - (float) $item['paid_amount'])) ?></strong></span>
                  </div>
                  <div class="notice info full">پرداخت آنلاین بر اساس مبلغ انتخابی شما انجام می‌شود.</div>
                </div>
                <div class="modal-footer">
                  <button class="btn success" type="submit">ادامه و پرداخت</button>
                  <button class="btn secondary" type="button" data-close-modal>بستن</button>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <?php if ($cardTransferEnabled): ?>
              <div class="proma-payment-panel <?= $defaultPaymentMethod === 'card-transfer' ? 'active' : '' ?>" data-payment-method-panel="card-transfer"<?= $defaultPaymentMethod === 'card-transfer' ? '' : ' hidden' ?>>
                <?php $paymentCardDownloadName = 'proma-payment-qr-' . (int) $item['id'] . '.html'; ?>
                <?php include __DIR__ . '/../partials/payment_card.php'; ?>
                <form method="post" action="<?= e(url('payments/cardTransfer')) ?>" enctype="multipart/form-data" class="proma-card-transfer-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
                  <div class="form-grid">
                    <label>مبلغ ثبت‌شده<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
                    <label>آپلود رسید<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
                    <div class="notice info full">پس از ثبت رسید، درخواست شما برای بررسی در سامانه ثبت می‌شود. حداکثر حجم فایل رسید ۱۰ مگابایت است.</div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn success" type="submit">ثبت رسید پرداخت</button>
                    <button class="btn secondary" type="button" data-close-modal>بستن</button>
                  </div>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <?php foreach ($installments as $item): ?>
    <div class="modal" id="manual-<?= (int) $item['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>ثبت پرداخت دستی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('installments/payment/' . $item['id'])) ?>" data-payment-preview data-preview-url="<?= e(url('installments/previewPayment')) ?>">
          <div class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
            <label>مبلغ<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
            <label>تاریخ پرداخت<input name="payment_date" value="<?= e($defaultPaymentDate) ?>" required placeholder="۱۴۰۳/۰۱/۰۱"></label>
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
    <div class="modal" id="adjust-<?= (int) $item['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>تنظیم جریمه و پاداش</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('installments/adjust/' . $item['id'])) ?>">
          <div class="modal-body form-grid">
            <?= csrf_field() ?>
            <label>افزایش جریمه<input name="manual_penalty_adjustment" data-money value="<?= e(number_format((float) $item['manual_penalty_adjustment'], 0)) ?>"></label>
            <label>افزایش پاداش<input name="manual_reward_adjustment" data-money value="<?= e(number_format((float) $item['manual_reward_adjustment'], 0)) ?>"></label>
          </div>
          <div class="modal-footer"><button class="btn" type="submit">ذخیره تنظیمات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!$customerMode && ($pagination['pages'] ?? 1) > 1): ?>
<nav class="proma-pagination">
  <?php for ($page = 1; $page <= (int) $pagination['pages']; $page++): ?>
    <a class="tab-link <?= $page === (int) $pagination['page'] ? 'active' : '' ?>" href="<?= e($pageUrl($page)) ?>"><?= to_persian_digits($page) ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

<?php if (!$customerMode): ?>
<div class="modal" id="create-installment">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن قسط سفارشی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('installments/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>قرارداد
          <span class="proma-live-search" data-contract-picker data-search-url="<?= e(url('contracts/search')) ?>">
            <input data-contract-picker-input autocomplete="off" required placeholder="شماره قرارداد، نام مشتری، موبایل یا کد ملی">
            <input type="hidden" name="contract_id" data-contract-picker-id>
            <span class="proma-live-results" data-contract-picker-results hidden></span>
          </span>
        </label>
        <label>سررسید<input name="due_date" value="<?= e($defaultDueDate ?? '') ?>" required placeholder="۱۴۰۳/۰۱/۰۱"></label>
        <label>مبلغ پایه<input name="base_amount" data-money required></label>
        <label>شناسه ضمانت<input name="guarantee_serial" dir="ltr" placeholder="شماره چک یا سفته"></label>
        <label class="full">توضیحات<input name="notes" required placeholder="علت یا توضیح قسط سفارشی"></label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
</div>
