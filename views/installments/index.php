<?php
$customerMode = $customerMode ?? false;
$paymentSettings = $paymentSettings ?? Settings::allKeyed();
$gatewayOptions = [];
$groupGatewayOptions = [];
if ($customerMode && class_exists('PaymentGatewayRegistry')) {
    try {
        $gatewayRegistry = PaymentGatewayRegistry::boot();
        $gatewayOptions = $gatewayRegistry->customerOptions(false);
        $groupGatewayOptions = $gatewayRegistry->customerOptions(true);
    } catch (Throwable $e) {
        $gatewayOptions = [];
        $groupGatewayOptions = [];
    }
}
$gatewayReady = !empty($gatewayOptions);
$groupGatewayReady = !empty($groupGatewayOptions);
$defaultGatewayId = '';
foreach ($gatewayOptions as $gatewayOption) {
    if ($defaultGatewayId === '' || !empty($gatewayOption['default'])) {
        $defaultGatewayId = (string) $gatewayOption['id'];
    }
}
$defaultGroupGatewayId = '';
foreach ($groupGatewayOptions as $gatewayOption) {
    if ($defaultGroupGatewayId === '' || !empty($gatewayOption['default'])) {
        $defaultGroupGatewayId = (string) $gatewayOption['id'];
    }
}
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
$filters = $filters ?? [];
$installmentSummary = $installmentSummary ?? [];
$contractContacts = $contractContacts ?? [];
$installmentsRoute = $installmentsRoute ?? ($customerMode ? 'installments/panel' : 'installments');
$pageUrl = function ($page) use ($installmentsRoute) {
    $params = $_GET;
    unset($params['route']);
    $params['page'] = $page;
    return url($installmentsRoute, $params);
};
$activeInstallments = array_values(array_filter($installments, static function ($item) { return ($item['status'] ?? '') !== 'paid' && ($item['status'] ?? '') !== 'cancelled'; }));
$paidInstallments = array_values(array_filter($installments, static function ($item) { return ($item['status'] ?? '') === 'paid'; }));
$cancelledInstallments = array_values(array_filter($installments, static function ($item) { return ($item['status'] ?? '') === 'cancelled'; }));
$visibleInstallments = $customerMode ? $activeInstallments : $installments;
$projectedPenaltyMessage = '';
if ($customerMode) {
  foreach ($visibleInstallments as $installment) {
    if (!empty($installment['show_projected_legal_penalty'])) {
      $projectedPenaltyMessage = (string) ($installment['projected_legal_penalty_customer_message'] ?? '');
      break;
    }
  }
}
$installmentTabUrl = static function (string $tab) use ($filters): string {
    $params = $filters;
    $customPaymentState = ($params['payment_state'] ?? '') === 'custom' ? 'custom' : '';
    unset($params['route'], $params['page'], $params['status'], $params['payment_state'], $params['due_today']);
    if ($customPaymentState !== '') $params['payment_state'] = $customPaymentState;
    $params['tab'] = $tab;
    return url('installments', array_filter($params, static fn ($value): bool => $value !== '' && $value !== false && $value !== null));
};
?>
<?php if (!$customerMode): ?>
<section class="proma-installments-page" aria-label="مدیریت عملیاتی اقساط">
  <header class="proma-page-intro">
    <div><span class="proma-eyebrow">عملیات مالی</span><h2>مدیریت اقساط</h2><p>هر تب نتیجهٔ مستقل، شمارش واقعی و صفحه‌بندی فشرده دارد.</p></div>
    <a class="btn" href="<?= e(url('contracts')) ?>"><?= proma_icon('file') ?><span>قراردادها</span></a>
  </header>
  <div class="proma-kpi-grid proma-installment-kpis" aria-label="خلاصه اقساط">
    <article class="proma-stat-card"><span>اقساط فعال</span><strong><?= to_persian_digits($installmentSummary['active_count'] ?? 0) ?></strong></article>
    <article class="proma-stat-card is-warning"><span>سررسید امروز</span><strong><?= to_persian_digits($installmentSummary['today_count'] ?? 0) ?></strong></article>
    <article class="proma-stat-card is-danger"><span>معوق</span><strong><?= to_persian_digits($installmentSummary['overdue_count'] ?? 0) ?></strong></article>
    <article class="proma-stat-card is-warning"><span>پرداخت جزئی</span><strong><?= to_persian_digits($installmentSummary['partial_count'] ?? 0) ?></strong></article>
    <article class="proma-stat-card is-success"><span>پرداخت‌شده</span><strong><?= to_persian_digits($installmentSummary['paid_count'] ?? 0) ?></strong></article>
    <article class="proma-stat-card"><span>ماندهٔ اصل اقساط</span><strong><?= money_toman($installmentSummary['current_payable_total'] ?? 0) ?></strong></article>
  </div>
  <section class="card proma-filter-card">
    <div class="card-header card-no-border"><div class="header-top"><div><h5>جستجو و فیلتر</h5><span>با هر تغییر فیلتر، نتیجه از صفحهٔ اول نمایش داده می‌شود.</span></div></div></div>
    <div class="card-body pt-0">
    <form method="get" action="<?= e(url('installments')) ?>" class="proma-operational-filter" data-ajax-filter data-ajax-target="[data-ajax-results='installments']" data-filter-reset-page="1">
      <input type="hidden" name="route" value="installments">
      <input type="hidden" name="tab" value="<?= e($filters['tab'] ?? 'active') ?>">
      <input type="hidden" name="page" value="1" data-filter-page>
      <label class="proma-filter-search"><span>جستجوی مشتری یا قرارداد</span><input name="q" value="<?= e($filters['search'] ?? '') ?>" placeholder="نام، قرارداد، موبایل یا کد ملی"></label>
      <label><span>شماره قرارداد</span><input name="contract_number" value="<?= e($filters['contract_number'] ?? '') ?>" dir="ltr"></label>
      <label><span>سررسید از</span><input name="due_from" value="<?= e(!empty($filters['due_from']) ? jdate($filters['due_from']) : '') ?>" placeholder="۱۴۰۵/۰۱/۰۱"></label>
      <label><span>سررسید تا</span><input name="due_to" value="<?= e(!empty($filters['due_to']) ? jdate($filters['due_to']) : '') ?>" placeholder="۱۴۰۵/۱۲/۲۹"></label>
      <label><span>مرتب‌سازی</span><select name="sort"><option value="financial"<?= selected($filters['sort'] ?? '', 'financial') ?>>اولویت مالی</option><option value="due_asc"<?= selected($filters['sort'] ?? '', 'due_asc') ?>>قدیمی‌ترین سررسید</option><option value="due_desc"<?= selected($filters['sort'] ?? '', 'due_desc') ?>>جدیدترین سررسید</option><option value="amount_desc"<?= selected($filters['sort'] ?? '', 'amount_desc') ?>>بیشترین مبلغ</option><option value="customer_asc"<?= selected($filters['sort'] ?? '', 'customer_asc') ?>>نام مشتری</option></select></label>
      <label><span>تعداد در صفحه</span><select name="per_page"><?php foreach ([25, 50, 100] as $size): ?><option value="<?= $size ?>"<?= selected($filters['per_page'] ?? 25, $size) ?>><?= to_persian_digits($size) ?></option><?php endforeach; ?></select></label>
      <div class="proma-filter-submit"><button class="btn" type="submit">اعمال فیلتر</button><a class="btn secondary" href="<?= e(url('installments')) ?>">پاک‌کردن</a><span class="proma-ajax-status" data-ajax-status></span></div>
      <details class="proma-filter-drawer full" <?= !empty($filters['customer_name']) || !empty($filters['mobile']) || !empty($filters['national_id']) || !empty($filters['amount_min']) || !empty($filters['amount_max']) ? 'open' : '' ?>>
        <summary>فیلترهای پیشرفته</summary>
        <div class="proma-filter-drawer__content">
          <label><span>نام مشتری</span><input name="customer_name" value="<?= e($filters['customer_name'] ?? '') ?>"></label>
          <label><span>شماره تماس</span><input name="mobile" value="<?= e($filters['mobile'] ?? '') ?>" inputmode="tel"></label>
          <label><span>کد ملی</span><input name="national_id" value="<?= e($filters['national_id'] ?? '') ?>" inputmode="numeric"></label>
          <label><span>مبلغ از</span><input name="amount_min" value="<?= e($filters['amount_min'] ?? '') ?>" data-money></label>
          <label><span>مبلغ تا</span><input name="amount_max" value="<?= e($filters['amount_max'] ?? '') ?>" data-money></label>
          <label><span>نوع قسط</span><select name="payment_state"><option value="">همه</option><option value="custom"<?= selected($filters['payment_state'] ?? '', 'custom') ?>>سفارشی</option></select></label>
          <label class="proma-filter-check"><input type="checkbox" name="exclude_legal_cases" value="1"<?= checked(!empty($filters['exclude_legal_cases'])) ?>><span>قراردادهای دارای پروندهٔ حقوقی فعال نمایش داده نشوند</span></label>
        </div>
      </details>
    </form>
    <nav class="proma-status-tabs" aria-label="وضعیت اقساط">
      <?php foreach (['active' => ['فعال', 'active_count'], 'today' => ['سررسید امروز', 'today_count'], 'overdue' => ['معوق', 'overdue_count'], 'partial' => ['پرداخت جزئی', 'partial_count'], 'paid' => ['پرداخت‌شده', 'paid_count'], 'all' => ['همه', 'all_count']] as $key => [$label, $countKey]): ?>
        <a class="<?= ($filters['tab'] ?? 'active') === $key ? 'active' : '' ?>" href="<?= e($installmentTabUrl($key)) ?>"><?= e($label) ?><span><?= to_persian_digits($installmentSummary[$countKey] ?? 0) ?></span></a>
      <?php endforeach; ?>
    </nav>
    </div>
  </section>
</section>
<?php endif; ?>
<div data-ajax-results="installments">
<?php if ($customerMode && !empty($installmentGroups)): ?>
<section class="card proma-installment-group-payment">
  <div class="card-header card-no-border"><div><h2>پرداخت آنلاین چند قسطی</h2><p class="text-muted">مبلغ فقط بین اقساط انتخاب‌شده و با ترتیب مالی ثابت تخصیص می‌یابد؛ مبلغ اضافی پذیرفته نمی‌شود.</p></div></div>
  <div class="card-body">
    <?php if (!$groupGatewayReady): ?><div class="notice warning">درگاه آنلاین برای پرداخت گروهی فعال یا تنظیم نشده است.</div><?php endif; ?>
    <?php foreach ($installmentGroups as $group): ?>
      <form method="post" action="<?= e(url('payments/gatewayGroup')) ?>" class="proma-installment-group-form" data-payment-group-form data-quote-url="<?= e(url('contracts/settlementQuote/' . (int) $group['contract_id'])) ?>" data-disable-on-submit>
        <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $group['contract_id'] ?>"><input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(24))) ?>"><input type="hidden" name="payment_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="quote_uuid" value="" data-group-quote-uuid>
        <div class="proma-installment-group-head"><strong>قرارداد <?= e($group['contract_number']) ?></strong><span data-group-total><?= money_toman($group['total']) ?></span></div>
        <div class="proma-installment-group-items">
          <?php foreach ($group['items'] as $groupItem): ?><label class="proma-group-check"><input type="checkbox" name="installment_ids[]" value="<?= (int) $groupItem['id'] ?>" data-group-item checked><span>قسط <?= to_persian_digits($groupItem['installment_number']) ?> - <?= e(jdate($groupItem['due_date'])) ?></span><strong><?= money_toman($groupItem['payable']) ?></strong></label><?php endforeach; ?>
        </div>
        <div class="proma-preview-grid" data-group-breakdown aria-live="polite"><span><small>مبلغ تسویه اقساط انتخاب‌شده</small><strong data-group-total><?= money_toman($group['total']) ?></strong></span><span><small>برای مبلغ دلخواه، مبلغ کمتر وارد کنید</small><strong>بدون انتقال به اقساط انتخاب‌نشده</strong></span></div>
        <label class="proma-group-amount">مبلغ پرداخت<input name="amount" data-group-amount value="<?= e((string) normalize_money($group['total'])) ?>" inputmode="numeric" required aria-describedby="group-payment-help"></label><small id="group-payment-help">پیش از انتقال به درگاه، مبلغ و اقساط روی سرور دوباره بررسی می‌شوند.</small>
        <?php if ($groupGatewayReady): ?>
          <div class="proma-gateway-choice" role="radiogroup" aria-label="انتخاب درگاه پرداخت گروهی">
            <?php foreach ($groupGatewayOptions as $gatewayOption): ?>
              <label class="proma-gateway-card">
                <input type="radio" name="gateway_id" value="<?= e($gatewayOption['id']) ?>"<?= checked($defaultGroupGatewayId === $gatewayOption['id']) ?>>
                <span class="proma-gateway-card-icon"><i data-feather="credit-card"></i></span>
                <span><strong><?= e($gatewayOption['name']) ?></strong><small><?= e($gatewayOption['environment']) ?></small></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <button class="btn success" type="submit"<?= !$groupGatewayReady ? ' disabled' : '' ?> data-submit-label="در حال اتصال به درگاه..."><i data-feather="credit-card"></i> پرداخت انتخاب‌شده‌ها</button>
      </form>
    <?php endforeach; ?>
  </div>
</section>
  <script>
  document.querySelectorAll('[data-payment-group-form]').forEach(function (form) {
    var amount = form.querySelector('[data-group-amount]');
    var total = form.querySelector('[data-group-total]');
    var quoteInput = form.querySelector('[data-group-quote-uuid]');
    var breakdown = form.querySelector('[data-group-breakdown]');
    var timer = null, controller = null, customAmount = false;
    amount.addEventListener('input', function () { customAmount = true; });
    var refresh = function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var ids = Array.prototype.map.call(form.querySelectorAll('[data-group-item]:checked'), function (item) { return item.value; });
        if (!ids.length) { quoteInput.value = ''; total.textContent = 'قسطی انتخاب نشده است'; return; }
        if (controller) controller.abort();
        controller = new AbortController();
        var query = ids.map(function (id) { return 'installment_ids[]=' + encodeURIComponent(id); }).join('&');
        fetch(form.getAttribute('data-quote-url') + '&scope=selected&' + query, { credentials: 'same-origin', signal: controller.signal })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data.ok) throw new Error(data.message || 'محاسبه مبلغ انجام نشد.');
            var quote = data.quote || {};
            quoteInput.value = quote.quote_uuid || '';
            total.textContent = (quote.formatted && quote.formatted.final_payable) || '۰ تومان';
            if (!customAmount) amount.value = String(quote.full_settlement_total || quote.final_payable || 0);
            if (breakdown && quote.formatted) breakdown.innerHTML = '<span><small>مانده اصل</small><strong>' + quote.formatted.principal_total + '</strong></span><span><small>جریمه عادی</small><strong>' + quote.formatted.normal_penalty_total + '</strong></span><span><small>جریمه حقوقی</small><strong>' + quote.formatted.legal_penalty_total + '</strong></span><span><small>پاداش تسویه</small><strong>' + quote.formatted.reward_total + '-</strong></span>';
          })
          .catch(function (error) { if (error.name !== 'AbortError') { quoteInput.value = ''; total.textContent = 'نیازمند محاسبه مجدد'; } });
      }, 650);
    };
    form.querySelectorAll('[data-group-item]').forEach(function (item) { item.addEventListener('change', refresh); });
  });
  </script>
<?php endif; ?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div><h2><?= $customerMode ? 'اقساط قابل پرداخت' : 'نتایج اقساط' ?></h2><?php if (!$customerMode): ?><span>نمایش <?= to_persian_digits($pagination['total'] ?? 0) ?> نتیجه در تب «<?= e(['active' => 'فعال', 'today' => 'سررسید امروز', 'overdue' => 'معوق', 'partial' => 'پرداخت جزئی', 'paid' => 'پرداخت‌شده', 'all' => 'همه'][$filters['tab'] ?? 'active'] ?? 'فعال') ?>»</span><?php endif; ?></div>
      <?php if (!$customerMode): ?><a class="btn" href="<?= e(url('contracts')) ?>"><i data-feather="file-text"></i><span>افزودن قسط از جزئیات قرارداد</span></a><?php endif; ?>
    </div>
  </div>
  <?php if (!$visibleInstallments): ?>
    <div class="card-body pt-0"><div class="proma-empty-state"><i data-feather="calendar"></i><h3><?= $customerMode ? 'قسط قابل پرداختی وجود ندارد.' : 'نتیجه‌ای برای این تب و فیلترها پیدا نشد.' ?></h3><p><?= $customerMode ? 'با ثبت قرارداد یا نزدیک‌شدن سررسید، اقساط شما در این بخش ظاهر می‌شوند.' : 'تب دیگری را انتخاب کنید یا فیلترها را پاک کنید.' ?></p><?php if (!$customerMode): ?><a class="btn secondary" href="<?= e(url('installments')) ?>">حذف فیلترها</a><?php endif; ?></div></div>
  <?php else: ?><div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>قرارداد</th><th>مشتری</th><th>قسط</th><th>سررسید</th><th>مبلغ پایه</th><th>جریمه</th><th>پاداش</th><th>پرداخت شده</th><th>قابل پرداخت</th><th>وضعیت</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody>
       <?php foreach ($visibleInstallments as $item): ?>
        <?php
          $contactDirectory = $contractContacts[(int) ($item['contract_id'] ?? 0)] ?? [];
          $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
        ?>
        <tr>
          <td><?= e($item['contract_number']) ?></td>
          <td><?= e($item['customer_name']) ?></td>
          <td>
            <strong><?= to_persian_digits($item['installment_number']) ?></strong>
            <?php if (!empty($item['is_custom'])): ?>
              <span class="badge info">قسط دلخواه</span>
              <?php if (!$customerMode || !empty($item['customer_visible'])): ?>
                <?php $customInstallmentDescription = trim((string) ($item['custom_description'] ?? $item['notes'] ?? '')); ?>
                <?php if ($customInstallmentDescription !== ''): ?><small class="proma-custom-installment-description">دلیل ایجاد: <?= e($customInstallmentDescription) ?></small><?php endif; ?>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= money_toman($item['base_amount']) ?></td>
          <td><?= penalty_display_html($item) ?></td>
          <td><?= money_toman($item['reward']) ?></td>
          <td><?= money_toman($item['paid_amount']) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <?php if ($customerMode): ?>
              <?php if (!empty($item['payment_allowed'])): ?>
                <button class="btn small success" type="button" data-open-modal="pay-<?= (int) $item['id'] ?>">پرداخت</button>
              <?php endif; ?>
            <?php else: ?>
              <?php if (!empty($item['payment_allowed'])): ?><button class="btn small secondary" type="button" data-open-modal="manual-<?= (int) $item['id'] ?>">پرداخت دستی</button><?php endif; ?>
              <button class="proma-icon-button info" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($item['contract_number']) ?>" title="تماس با مشتری و ضامن‌ها" aria-label="تماس با مشتری و ضامن‌های قرارداد <?= e($item['contract_number']) ?>"><?= proma_icon('phone') ?></button>
              <button class="btn small warning" type="button" data-open-modal="adjust-<?= (int) $item['id'] ?>">تنظیم</button>
              <?php if (!empty($item['payment_allowed'])): ?><form method="post" action="<?= e(url('installments/markPaid/' . $item['id'])) ?>"><?= csrf_field() ?><button class="btn small success" type="submit">تسویه</button></form><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($customerMode && $paidInstallments): ?>
<details class="card proma-paid-installments"<?= count($paidInstallments) <= 3 ? ' open' : '' ?>>
  <summary><strong>اقساط پرداخت‌شده (<?= to_persian_digits(count($paidInstallments)) ?>)</strong><span>برای مشاهده تاریخ و مبلغ تسویه باز کنید</span></summary>
  <div class="table-wrap"><table><thead><tr><th>قرارداد</th><th>مشتری</th><th>قسط</th><th>سررسید</th><th>مبلغ پرداخت‌شده</th><th>روش / پیگیری</th><th>تاریخ تسویه</th><th>وضعیت</th></tr></thead><tbody>
    <?php foreach ($paidInstallments as $item): ?><tr><td><?= e($item['contract_number']) ?></td><td><?= e($item['customer_name']) ?></td><td><?= to_persian_digits($item['installment_number']) ?></td><td><?= e(jdate($item['due_date'])) ?></td><td><?= money_toman($item['paid_amount']) ?></td><td><?= !empty($item['last_payment_method']) ? e(payment_method_label($item['last_payment_method'])) : '—' ?><?php if (!empty($item['last_payment_reference'])): ?><br><small class="ltr"><?= e($item['last_payment_reference']) ?></small><?php endif; ?></td><td><?= !empty($item['effective_settlement_date']) ? e(jdate($item['effective_settlement_date'])) : '—' ?></td><td><span class="badge success">تسویه‌شده</span></td></tr><?php endforeach; ?>
  </tbody></table></div>
</details>
<?php endif; ?>
<?php if ($cancelledInstallments): ?><details class="card proma-paid-installments"><summary><strong>اقساط لغوشده (<?= to_persian_digits(count($cancelledInstallments)) ?>)</strong></summary><div class="notice warning">اقساط لغوشده از اقساط فعال و قابل پرداخت جدا نگه داشته شده‌اند.</div></details><?php endif; ?>

<?php if ($customerMode): ?>
  <?php foreach ($installments as $item): ?>
    <?php if (empty($item['payment_allowed'])): continue; endif; ?>
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
              <form method="post" action="<?= e(url('payments/gateway')) ?>" class="proma-payment-form" data-disable-on-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(24))) ?>">
                <div class="form-grid">
                  <div class="full proma-gateway-field">
                    <span class="proma-field-label">انتخاب درگاه پرداخت</span>
                    <div class="proma-gateway-choice" role="radiogroup" aria-label="انتخاب درگاه پرداخت">
                      <?php foreach ($gatewayOptions as $gatewayOption): ?>
                        <label class="proma-gateway-card">
                          <input type="radio" name="gateway_id" value="<?= e($gatewayOption['id']) ?>"<?= checked($defaultGatewayId === $gatewayOption['id']) ?>>
                          <span class="proma-gateway-card-icon"><i data-feather="credit-card"></i></span>
                          <span><strong><?= e($gatewayOption['name']) ?></strong><small><?= e($gatewayOption['environment']) ?> · <?= e($gatewayOption['description']) ?></small></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <label>مبلغ پرداختی<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
                  <div class="proma-preview-grid full">
                    <span><small>مبلغ امروز</small><strong><?= money_toman($item['payable']) ?></strong></span>
                    <span><small>جریمه امروز</small><span class="proma-preview-amount"><?= penalty_display_html($item) ?></span></span>
                    <span><small>پاداش امروز</small><strong><?= money_toman($item['reward']) ?></strong></span>
                    <span><small>مانده قبل پرداخت</small><strong><?= money_toman($item['remaining_amount'] ?? max(0, (float) $item['base_amount'] - (float) $item['paid_amount'])) ?></strong></span>
                  </div>
                  <div class="notice info full">پرداخت آنلاین بر اساس مبلغ انتخابی شما انجام می‌شود.</div>
                </div>
                <div class="modal-footer">
                  <button class="btn success" type="submit" data-submit-label="در حال اتصال به درگاه...">ادامه و پرداخت</button>
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
  <?php if ($projectedPenaltyMessage !== ''): ?><div class="notice info proma-customer-penalty-note"><strong>راهنمای جریمه حقوقی:</strong> <?= e($projectedPenaltyMessage) ?></div><?php endif; ?>
  <?php foreach ($installments as $item): ?>
    <?php if (!empty($item['payment_allowed'])): ?>
    <div class="modal" id="manual-<?= (int) $item['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>ثبت پرداخت دستی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('installments/payment/' . $item['id'])) ?>" data-payment-preview data-preview-url="<?= e(url('installments/previewPayment')) ?>">
          <div class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="payment_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>">
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
    <?php endif; ?>
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
<?= render_pagination($pagination, $pageUrl) ?>
<?php endif; ?>

</div>
